<?php

namespace App\Http\Controllers\Api;

use App\Models\Sale;
use App\Models\Expense;
use App\Models\Purchase;
use App\Models\AccountingPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Carbon\Carbon;

/**
 * Export FEC (Fichier des Écritures Comptables)
 * Format conforme aux normes OHADA/SYSCOHADA
 */
class FECExportController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function export(Request $request)
    {
        $user = auth()->user();
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'format' => 'nullable|in:csv,txt',
        ]);

        $startDate = $validated['start_date'];
        $endDate = $validated['end_date'];
        $format = $validated['format'] ?? 'txt';

        // Vérifier que la période n'est pas verrouillée (optionnel)
        // On peut exporter même les périodes clôturées

        $entries = $this->generateFECEntries($tenantId, $startDate, $endDate);

        $filename = $this->generateFilename($tenantId, $startDate, $endDate);
        $content = $this->formatFEC($entries, $format);

        $headers = [
            'Content-Type' => $format === 'csv' ? 'text/csv' : 'text/plain',
            'Content-Disposition' => "attachment; filename=\"{$filename}.{$format}\"",
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ];

        return Response::make($content, 200, $headers);
    }

    public function preview(Request $request): JsonResponse
    {
        $user = auth()->user();
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $entries = $this->generateFECEntries(
            $tenantId,
            $validated['start_date'],
            $validated['end_date']
        );

        $limit = $validated['limit'] ?? 50;

        return response()->json([
            'total_entries' => count($entries),
            'preview' => array_slice($entries, 0, $limit),
            'columns' => $this->getFECColumns(),
        ]);
    }

    private function generateFECEntries(int $tenantId, string $startDate, string $endDate): array
    {
        $entries = [];
        $lineNumber = 1;

        // 1. Écritures de ventes
        $sales = Sale::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereIn('status', ['completed', 'validated'])
            ->with('items.product')
            ->orderBy('created_at')
            ->get();

        foreach ($sales as $sale) {
            $pieceRef = 'VTE-' . $sale->id;
            $pieceDate = Carbon::parse($sale->created_at)->format('Ymd');
            $libelle = 'Vente ' . ($sale->reference ?? $sale->id);

            // Débit: Caisse/Client (411/531)
            $entries[] = $this->createEntry(
                $lineNumber++,
                'VE',
                $pieceDate,
                $pieceRef,
                $sale->payment_method === 'cash' ? '531000' : '411000',
                $sale->payment_method === 'cash' ? 'Caisse' : 'Clients',
                $libelle,
                $sale->total,
                0,
                $sale->created_at
            );

            // Crédit: Ventes (701)
            $entries[] = $this->createEntry(
                $lineNumber++,
                'VE',
                $pieceDate,
                $pieceRef,
                '701000',
                'Ventes de marchandises',
                $libelle,
                0,
                $sale->subtotal,
                $sale->created_at
            );

            // Crédit: TVA collectée (443) si applicable
            if ($sale->tax_amount > 0) {
                $entries[] = $this->createEntry(
                    $lineNumber++,
                    'VE',
                    $pieceDate,
                    $pieceRef,
                    '443100',
                    'TVA collectée',
                    $libelle,
                    0,
                    $sale->tax_amount,
                    $sale->created_at
                );
            }
        }

        // 2. Écritures d'achats
        $purchases = Purchase::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'received')
            ->with('items.product')
            ->orderBy('created_at')
            ->get();

        foreach ($purchases as $purchase) {
            $pieceRef = 'ACH-' . $purchase->id;
            $pieceDate = Carbon::parse($purchase->created_at)->format('Ymd');
            $libelle = 'Achat ' . ($purchase->reference ?? $purchase->supplier_name);

            // Débit: Achats (601)
            $entries[] = $this->createEntry(
                $lineNumber++,
                'AC',
                $pieceDate,
                $pieceRef,
                '601000',
                'Achats de marchandises',
                $libelle,
                $purchase->total,
                0,
                $purchase->created_at
            );

            // Crédit: Fournisseurs (401)
            $entries[] = $this->createEntry(
                $lineNumber++,
                'AC',
                $pieceDate,
                $pieceRef,
                '401000',
                'Fournisseurs',
                $libelle,
                0,
                $purchase->total,
                $purchase->created_at
            );
        }

        // 3. Écritures de dépenses
        $expenses = Expense::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at')
            ->get();

        foreach ($expenses as $expense) {
            $pieceRef = 'DEP-' . $expense->id;
            $pieceDate = Carbon::parse($expense->created_at)->format('Ymd');
            $libelle = $expense->description ?? 'Dépense ' . $expense->category;

            // Débit: Compte de charge selon catégorie
            $accountCode = $this->getExpenseAccountCode($expense->category);
            $entries[] = $this->createEntry(
                $lineNumber++,
                'OD',
                $pieceDate,
                $pieceRef,
                $accountCode,
                $this->getExpenseAccountLabel($expense->category),
                $libelle,
                $expense->amount,
                0,
                $expense->created_at
            );

            // Crédit: Caisse/Banque
            $entries[] = $this->createEntry(
                $lineNumber++,
                'OD',
                $pieceDate,
                $pieceRef,
                $expense->payment_method === 'bank' ? '521000' : '531000',
                $expense->payment_method === 'bank' ? 'Banque' : 'Caisse',
                $libelle,
                0,
                $expense->amount,
                $expense->created_at
            );
        }

        return $entries;
    }

    private function createEntry(
        int $lineNumber,
        string $journalCode,
        string $pieceDate,
        string $pieceRef,
        string $accountCode,
        string $accountLabel,
        string $libelle,
        float $debit,
        float $credit,
        $ecritureDate
    ): array {
        return [
            'JournalCode' => $journalCode,
            'JournalLib' => $this->getJournalLabel($journalCode),
            'EcritureNum' => $lineNumber,
            'EcritureDate' => $pieceDate,
            'CompteNum' => $accountCode,
            'CompteLib' => $accountLabel,
            'CompAuxNum' => '',
            'CompAuxLib' => '',
            'PieceRef' => $pieceRef,
            'PieceDate' => $pieceDate,
            'EcritureLib' => substr($libelle, 0, 100),
            'Debit' => number_format($debit, 2, '.', ''),
            'Credit' => number_format($credit, 2, '.', ''),
            'EcritureLet' => '',
            'DateLet' => '',
            'ValidDate' => Carbon::parse($ecritureDate)->format('Ymd'),
            'Montantdevise' => '',
            'Idevise' => 'XAF',
        ];
    }

    private function formatFEC(array $entries, string $format): string
    {
        $columns = $this->getFECColumns();
        $separator = $format === 'csv' ? ';' : "\t";
        $lines = [];

        // En-tête
        $lines[] = implode($separator, $columns);

        // Données
        foreach ($entries as $entry) {
            $row = [];
            foreach ($columns as $col) {
                $row[] = $entry[$col] ?? '';
            }
            $lines[] = implode($separator, $row);
        }

        return implode("\r\n", $lines);
    }

    private function getFECColumns(): array
    {
        return [
            'JournalCode',
            'JournalLib',
            'EcritureNum',
            'EcritureDate',
            'CompteNum',
            'CompteLib',
            'CompAuxNum',
            'CompAuxLib',
            'PieceRef',
            'PieceDate',
            'EcritureLib',
            'Debit',
            'Credit',
            'EcritureLet',
            'DateLet',
            'ValidDate',
            'Montantdevise',
            'Idevise',
        ];
    }

    private function getJournalLabel(string $code): string
    {
        return match($code) {
            'VE' => 'Journal des Ventes',
            'AC' => 'Journal des Achats',
            'CA' => 'Journal de Caisse',
            'BQ' => 'Journal de Banque',
            'OD' => 'Opérations Diverses',
            default => 'Journal',
        };
    }

    private function getExpenseAccountCode(string $category): string
    {
        return match($category) {
            'salaries', 'wages' => '641000',
            'rent' => '613000',
            'utilities' => '605000',
            'transport' => '624000',
            'maintenance' => '615000',
            'supplies' => '606000',
            'marketing' => '627000',
            'taxes' => '635000',
            'insurance' => '616000',
            default => '658000',
        };
    }

    private function getExpenseAccountLabel(string $category): string
    {
        return match($category) {
            'salaries', 'wages' => 'Rémunérations du personnel',
            'rent' => 'Locations',
            'utilities' => 'Fournitures non stockables',
            'transport' => 'Transports',
            'maintenance' => 'Entretien et réparations',
            'supplies' => 'Achats non stockés',
            'marketing' => 'Publicité',
            'taxes' => 'Impôts et taxes',
            'insurance' => 'Assurances',
            default => 'Charges diverses',
        };
    }

    private function generateFilename(int $tenantId, string $startDate, string $endDate): string
    {
        $tenant = \App\Models\Tenant::find($tenantId);
        $siren = $tenant->tax_id ?? 'XXXXXX';
        $start = Carbon::parse($startDate)->format('Ymd');
        $end = Carbon::parse($endDate)->format('Ymd');

        return "FEC_{$siren}_{$start}_{$end}";
    }
}
