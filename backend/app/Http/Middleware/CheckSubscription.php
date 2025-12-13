<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\System\Subscription;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscription
{
    /**
     * Vérifie que le tenant a un abonnement valide
     * Bloque l'accès si abonnement expiré ou suspendu
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        
        // Super Admin bypass
        if ($user && $user->role === 'super_admin') {
            return $next($request);
        }

        // Pas d'utilisateur ou pas de tenant
        if (!$user || !$user->tenant_id) {
            return $next($request);
        }

        $tenant = $user->tenant;
        
        // Vérifier le statut du tenant
        if ($tenant->status === 'suspended') {
            return response()->json([
                'error' => 'subscription_suspended',
                'message' => 'Votre compte est suspendu. Contactez le support.',
            ], 403);
        }

        // Vérifier l'abonnement actif
        $subscription = Subscription::where('tenant_id', $tenant->id)
            ->whereIn('status', ['active', 'trial'])
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$subscription) {
            return response()->json([
                'error' => 'no_subscription',
                'message' => 'Aucun abonnement actif. Veuillez souscrire à un plan.',
            ], 403);
        }

        // Vérifier expiration
        if ($subscription->ends_at && $subscription->ends_at->isPast()) {
            // Marquer comme expiré
            $subscription->update(['status' => 'expired']);
            
            return response()->json([
                'error' => 'subscription_expired',
                'message' => 'Votre abonnement a expiré. Veuillez renouveler.',
                'expired_at' => $subscription->ends_at->toISOString(),
            ], 403);
        }

        // Vérifier fin de période d'essai
        if ($subscription->status === 'trial' && $subscription->trial_ends_at && $subscription->trial_ends_at->isPast()) {
            $subscription->update(['status' => 'expired']);
            
            return response()->json([
                'error' => 'trial_expired',
                'message' => 'Votre période d\'essai est terminée. Veuillez souscrire à un plan.',
            ], 403);
        }

        // Ajouter les infos d'abonnement à la requête
        $request->attributes->set('subscription', $subscription);
        $request->attributes->set('subscription_plan', $subscription->plan);

        return $next($request);
    }
}
