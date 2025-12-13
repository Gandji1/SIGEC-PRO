<?php

namespace App\Services;

use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Notification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Service de notifications pour le flux Fournisseur <-> Entreprise
 * Gère les notifications in-app et les emails pour les commandes d'achat
 */
class SupplierNotificationService
{
    /**
     * Notifier le fournisseur d'une nouvelle commande
     * Appelé quand le Tenant/Gérant soumet une commande au fournisseur
     */
    public function notifySupplierNewOrder(Purchase $purchase): void
    {
        $supplier = $purchase->supplier;
        if (!$supplier) return;

        $tenant = $purchase->tenant;
        $directLink = $this->generateSupplierOrderLink($purchase);

        // Notification in-app si le fournisseur a un compte utilisateur
        if ($supplier->user_id) {
            Notification::notify(
                $purchase->tenant_id,
                'supplier_new_order',
                'Nouvelle commande reçue',
                "Vous avez reçu une commande #{$purchase->reference} de {$tenant->name} pour un montant de " . number_format($purchase->total, 0, ',', ' ') . " FCFA",
                [
                    'purchase_id' => $purchase->id,
                    'reference' => $purchase->reference,
                    'total' => $purchase->total,
                    'tenant_name' => $tenant->name,
                    'link' => $directLink,
                ],
                $supplier->user_id,
                'high'
            );
        }

        // Email au fournisseur
        $email = $supplier->portal_email ?? $supplier->email;
        if ($email) {
            $this->sendSupplierEmail(
                $email,
                "Nouvelle commande #{$purchase->reference} - {$tenant->name}",
                'supplier_new_order',
                [
                    'supplier_name' => $supplier->contact_person ?? $supplier->name,
                    'tenant_name' => $tenant->name,
                    'reference' => $purchase->reference,
                    'total' => number_format($purchase->total, 0, ',', ' ') . ' FCFA',
                    'items_count' => $purchase->items()->count(),
                    'expected_date' => $purchase->expected_delivery_date?->format('d/m/Y') ?? 'Non spécifiée',
                    'direct_link' => $directLink,
                    'notes' => $purchase->notes,
                ]
            );
        }
    }

    /**
     * Notifier l'entreprise (Tenant/Gérant) que le fournisseur a confirmé la commande
     */
    public function notifyTenantOrderConfirmed(Purchase $purchase): void
    {
        $supplier = $purchase->supplier;
        $tenant = $purchase->tenant;
        $directLink = $this->generateTenantOrderLink($purchase);

        // Notifier tous les managers du tenant
        $this->notifyTenantManagers(
            $purchase->tenant_id,
            'purchase_confirmed',
            'Commande confirmée par le fournisseur',
            "La commande #{$purchase->reference} a été confirmée par {$supplier->name}",
            [
                'purchase_id' => $purchase->id,
                'reference' => $purchase->reference,
                'supplier_name' => $supplier->name,
                'link' => $directLink,
            ],
            'normal'
        );

        // Email au créateur de la commande
        $creator = $purchase->createdBy ?? $purchase->user;
        if ($creator && $creator->email) {
            $this->sendTenantEmail(
                $creator->email,
                "Commande #{$purchase->reference} confirmée par {$supplier->name}",
                'tenant_order_confirmed',
                [
                    'user_name' => $creator->name,
                    'supplier_name' => $supplier->name,
                    'reference' => $purchase->reference,
                    'total' => number_format($purchase->total, 0, ',', ' ') . ' FCFA',
                    'confirmed_at' => $purchase->confirmed_at?->format('d/m/Y H:i'),
                    'direct_link' => $directLink,
                ]
            );
        }
    }

    /**
     * Notifier l'entreprise que le fournisseur a préparé/expédié la commande
     */
    public function notifyTenantOrderShipped(Purchase $purchase): void
    {
        $supplier = $purchase->supplier;
        $directLink = $this->generateTenantOrderLink($purchase);

        $this->notifyTenantManagers(
            $purchase->tenant_id,
            'purchase_shipped',
            'Commande préparée - En cours de livraison',
            "La commande #{$purchase->reference} a été préparée par {$supplier->name} et est en cours de livraison",
            [
                'purchase_id' => $purchase->id,
                'reference' => $purchase->reference,
                'supplier_name' => $supplier->name,
                'tracking_number' => $purchase->tracking_number,
                'link' => $directLink,
            ],
            'high'
        );

        // Email
        $creator = $purchase->createdBy ?? $purchase->user;
        if ($creator && $creator->email) {
            $this->sendTenantEmail(
                $creator->email,
                "Commande #{$purchase->reference} expédiée - {$supplier->name}",
                'tenant_order_shipped',
                [
                    'user_name' => $creator->name,
                    'supplier_name' => $supplier->name,
                    'reference' => $purchase->reference,
                    'tracking_number' => $purchase->tracking_number ?? 'Non disponible',
                    'shipped_at' => $purchase->shipped_at?->format('d/m/Y H:i'),
                    'direct_link' => $directLink,
                ]
            );
        }
    }

    /**
     * Notifier l'entreprise que le fournisseur a livré la commande
     */
    public function notifyTenantOrderDelivered(Purchase $purchase): void
    {
        $supplier = $purchase->supplier;
        $directLink = $this->generateTenantOrderLink($purchase);

        $this->notifyTenantManagers(
            $purchase->tenant_id,
            'purchase_delivered',
            'Commande livrée - À réceptionner',
            "La commande #{$purchase->reference} de {$supplier->name} a été livrée. Veuillez la réceptionner.",
            [
                'purchase_id' => $purchase->id,
                'reference' => $purchase->reference,
                'supplier_name' => $supplier->name,
                'link' => $directLink,
            ],
            'high'
        );

        // Email
        $creator = $purchase->createdBy ?? $purchase->user;
        if ($creator && $creator->email) {
            $this->sendTenantEmail(
                $creator->email,
                "URGENT: Commande #{$purchase->reference} livrée - À réceptionner",
                'tenant_order_delivered',
                [
                    'user_name' => $creator->name,
                    'supplier_name' => $supplier->name,
                    'reference' => $purchase->reference,
                    'total' => number_format($purchase->total, 0, ',', ' ') . ' FCFA',
                    'delivered_at' => $purchase->delivered_at?->format('d/m/Y H:i'),
                    'direct_link' => $directLink,
                ]
            );
        }
    }

    /**
     * Notifier le fournisseur que la commande a été réceptionnée
     */
    public function notifySupplierOrderReceived(Purchase $purchase): void
    {
        $supplier = $purchase->supplier;
        if (!$supplier) return;

        $tenant = $purchase->tenant;
        $directLink = $this->generateSupplierOrderLink($purchase);

        // Notification in-app
        if ($supplier->user_id) {
            Notification::notify(
                $purchase->tenant_id,
                'supplier_order_received',
                'Commande réceptionnée',
                "Votre commande #{$purchase->reference} a été réceptionnée par {$tenant->name}",
                [
                    'purchase_id' => $purchase->id,
                    'reference' => $purchase->reference,
                    'tenant_name' => $tenant->name,
                    'link' => $directLink,
                ],
                $supplier->user_id,
                'normal'
            );
        }

        // Email
        $email = $supplier->portal_email ?? $supplier->email;
        if ($email) {
            $this->sendSupplierEmail(
                $email,
                "Commande #{$purchase->reference} réceptionnée - {$tenant->name}",
                'supplier_order_received',
                [
                    'supplier_name' => $supplier->contact_person ?? $supplier->name,
                    'tenant_name' => $tenant->name,
                    'reference' => $purchase->reference,
                    'total' => number_format($purchase->total, 0, ',', ' ') . ' FCFA',
                    'received_at' => $purchase->received_at?->format('d/m/Y H:i'),
                    'direct_link' => $directLink,
                ]
            );
        }
    }

    /**
     * Notifier le fournisseur que le paiement a été effectué
     */
    public function notifySupplierPaymentMade(Purchase $purchase, float $amount): void
    {
        $supplier = $purchase->supplier;
        if (!$supplier) return;

        $tenant = $purchase->tenant;
        $directLink = $this->generateSupplierOrderLink($purchase);

        // Notification in-app
        if ($supplier->user_id) {
            Notification::notify(
                $purchase->tenant_id,
                'supplier_payment_received',
                'Paiement reçu',
                "Vous avez reçu un paiement de " . number_format($amount, 0, ',', ' ') . " FCFA pour la commande #{$purchase->reference}",
                [
                    'purchase_id' => $purchase->id,
                    'reference' => $purchase->reference,
                    'amount' => $amount,
                    'tenant_name' => $tenant->name,
                    'link' => $directLink,
                ],
                $supplier->user_id,
                'high'
            );
        }

        // Email
        $email = $supplier->portal_email ?? $supplier->email;
        if ($email) {
            $this->sendSupplierEmail(
                $email,
                "Paiement reçu - Commande #{$purchase->reference}",
                'supplier_payment_received',
                [
                    'supplier_name' => $supplier->contact_person ?? $supplier->name,
                    'tenant_name' => $tenant->name,
                    'reference' => $purchase->reference,
                    'amount' => number_format($amount, 0, ',', ' ') . ' FCFA',
                    'total' => number_format($purchase->total, 0, ',', ' ') . ' FCFA',
                    'amount_paid' => number_format($purchase->amount_paid, 0, ',', ' ') . ' FCFA',
                    'remaining' => number_format($purchase->total - $purchase->amount_paid, 0, ',', ' ') . ' FCFA',
                    'direct_link' => $directLink,
                ]
            );
        }
    }

    /**
     * Générer le lien direct pour le fournisseur
     */
    private function generateSupplierOrderLink(Purchase $purchase): string
    {
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000');
        return "{$frontendUrl}/supplier-portal/orders/{$purchase->id}";
    }

    /**
     * Générer le lien direct pour le tenant/gérant
     */
    private function generateTenantOrderLink(Purchase $purchase): string
    {
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000');
        return "{$frontendUrl}/purchases/{$purchase->id}";
    }

    /**
     * Notifier tous les managers d'un tenant
     */
    private function notifyTenantManagers(int $tenantId, string $type, string $title, string $message, array $data, string $priority): void
    {
        $managers = User::where('tenant_id', $tenantId)
            ->whereIn('role', ['owner', 'admin', 'gerant', 'manager'])
            ->get();

        foreach ($managers as $manager) {
            Notification::notify($tenantId, $type, $title, $message, $data, $manager->id, $priority);
        }
    }

    /**
     * Envoyer un email au fournisseur
     */
    private function sendSupplierEmail(string $email, string $subject, string $template, array $data): void
    {
        try {
            Mail::send("emails.supplier.{$template}", $data, function ($message) use ($email, $subject) {
                $message->to($email)
                    ->subject($subject);
            });
            Log::info("Email envoyé au fournisseur: {$email} - {$subject}");
        } catch (\Exception $e) {
            Log::error("Erreur envoi email fournisseur: {$e->getMessage()}");
            // Fallback: envoyer un email simple si le template n'existe pas
            $this->sendSimpleEmail($email, $subject, $data, 'supplier');
        }
    }

    /**
     * Envoyer un email au tenant/gérant
     */
    private function sendTenantEmail(string $email, string $subject, string $template, array $data): void
    {
        try {
            Mail::send("emails.tenant.{$template}", $data, function ($message) use ($email, $subject) {
                $message->to($email)
                    ->subject($subject);
            });
            Log::info("Email envoyé au tenant: {$email} - {$subject}");
        } catch (\Exception $e) {
            Log::error("Erreur envoi email tenant: {$e->getMessage()}");
            // Fallback: envoyer un email simple si le template n'existe pas
            $this->sendSimpleEmail($email, $subject, $data, 'tenant');
        }
    }

    /**
     * Envoyer un email simple (fallback si template n'existe pas)
     */
    private function sendSimpleEmail(string $email, string $subject, array $data, string $type): void
    {
        try {
            $directLink = $data['direct_link'] ?? '#';
            $reference = $data['reference'] ?? 'N/A';
            
            $body = $type === 'supplier' 
                ? $this->buildSupplierEmailBody($data)
                : $this->buildTenantEmailBody($data);

            Mail::raw($body, function ($message) use ($email, $subject) {
                $message->to($email)
                    ->subject($subject);
            });
        } catch (\Exception $e) {
            Log::error("Erreur envoi email simple: {$e->getMessage()}");
        }
    }

    /**
     * Construire le corps de l'email pour le fournisseur
     */
    private function buildSupplierEmailBody(array $data): string
    {
        $name = $data['supplier_name'] ?? 'Cher Fournisseur';
        $tenant = $data['tenant_name'] ?? 'Notre entreprise';
        $reference = $data['reference'] ?? 'N/A';
        $total = $data['total'] ?? 'N/A';
        $link = $data['direct_link'] ?? '#';

        return <<<EOT
Bonjour {$name},

Vous avez reçu une notification concernant la commande #{$reference} de {$tenant}.

Montant: {$total}

Pour accéder directement à cette commande, cliquez sur le lien ci-dessous:
{$link}

Cordialement,
L'équipe SIGEC
EOT;
    }

    /**
     * Construire le corps de l'email pour le tenant/gérant
     */
    private function buildTenantEmailBody(array $data): string
    {
        $name = $data['user_name'] ?? 'Cher Utilisateur';
        $supplier = $data['supplier_name'] ?? 'Le fournisseur';
        $reference = $data['reference'] ?? 'N/A';
        $total = $data['total'] ?? '';
        $link = $data['direct_link'] ?? '#';

        return <<<EOT
Bonjour {$name},

Mise à jour concernant votre commande #{$reference} auprès de {$supplier}.

{$total}

Pour accéder directement à cette commande, cliquez sur le lien ci-dessous:
{$link}

Cordialement,
L'équipe SIGEC
EOT;
    }
}
