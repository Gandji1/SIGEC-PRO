<?php

namespace App\Http\Controllers\Api;

use App\Models\AccountingPeriod;
use App\Models\Sale;
use App\Models\PosOrder;
use App\Models\PosOrderItem;
use App\Models\Expense;
use App\Models\Purchase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;

class AccountingPeriodController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index(Request $request): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;

        $query = AccountingPeriod::where('tenant_id', $tenantId)
            ->with('closedByUser:id,name');

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $periods = $query->orderBy('start_date', 'desc')->get();

        return response()->json(['data' => $periods]);
    }

    public function show(AccountingPeriod $accountingPeriod): JsonResponse
    {
        if ($accountingPeriod->tenant_id !== auth()->user()->tenant_id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        return response()->json($accountingPeriod->load('closedByUser:id,name'));
    }

    public function create(Request $request): JsonResponse
    {
        $user = auth()->user();

        $validated = $request->validate([
            'type' => 'required|in:monthly,quarterly,annual',
            'year' => 'required|integer|min:2020|max:2100',
            'month' => 'required_if:type,monthly|integer|min:1|max:12',
            'quarter' => 'required_if:type,quarterly|integer|min:1|max:4',
        ]);

        $start = null;
        $end = null;
        $name = '';

        switch ($validated['type']) {
            case 'monthly':
                $start = Carbon::create($validated['year'], $validated['month'], 1)->startOfMonth();
                $end = $start->copy()->endOfMonth();
                $name = $start->translatedFormat('F Y');
                break;
            case 'quarterly':
                $startMonth = ($validated['quarter'] - 1) * 3 + 1;
                $start = Carbon::create($validated['year'], $startMonth, 1)->startOfMonth();
                $end = $start->copy()->addMonths(2)->endOfMonth();
                $name = "T{$validated['quarter']} {$validated['year']}";
                break;
            case 'annual':
                $start = Carbon::create($validated['year'], 1, 1)->startOfYear();
                $end = $start->copy()->endOfYear();
                $name = "Exercice {$validated['year']}";
                break;
        }

        // Vérifier si la période existe déjà
        $existing = AccountingPeriod::where('tenant_id', $user->tenant_id)
            ->where('type', $validated['type'])
            ->where('start_date', $start->toDateString())
            ->first();

        if ($existing) {
            return response()->json(['message' => 'Cette période existe déjà', 'data' => $existing], 422);
        }

        $period = AccountingPeriod::create([
            'tenant_id' => $user->tenant_id,
            'name' => $name,
            'type' => $validated['type'],
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'status' => 'open',
        ]);

        return response()->json($period, 201);
    }

    public function close(Request $request, AccountingPeriod $accountingPeriod): JsonResponse
    {
        $user = auth()->user();

        if ($accountingPeriod->tenant_id !== $user->tenant_id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        if (!$user->isManagement()) {
            return response()->json(['message' => 'Seul un administrateur peut clôturer une période'], 403);
        }

        if ($accountingPeriod->status === 'closed') {
            return response()->json(['message' => 'Cette période est déjà clôturée'], 422);
        }

        $validated = $request->validate([
            'notes' => 'nullable|string|max:1000',
        ]);

        // Calculer le résumé de la période
        $summary = $this->calculatePeriodSummary(
            $user->tenant_id,
            $accountingPeriod->start_date,
            $accountingPeriod->end_date
        );

        try {
            $accountingPeriod->close($user->id, $summary, $validated['notes'] ?? null);

            return response()->json([
                'message' => 'Période clôturée avec succès',
                'data' => $accountingPeriod->fresh(),
            ]);

        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function reopen(AccountingPeriod $accountingPeriod): JsonResponse
    {
        $user = auth()->user();

        if ($accountingPeriod->tenant_id !== $user->tenant_id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        if (!in_array($user->role, ['owner', 'admin', 'super_admin'])) {
            return response()->json(['message' => 'Seul le propriétaire peut réouvrir une période'], 403);
        }

        try {
            $accountingPeriod->reopen($user->id);

            return response()->json([
                'message' => 'Période réouverte',
                'data' => $accountingPeriod->fresh(),
            ]);

        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function checkDate(Request $request): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'date' => 'required|date',
        ]);

        $isLocked = AccountingPeriod::isDateLocked($tenantId, $validated['date']);

        return response()->json([
            'date' => $validated['date'],
            'is_locked' => $isLocked,
            'message' => $isLocked 
                ? 'Cette date appartient à une période clôturée' 
                : 'Cette date est modifiable',
        ]);
    }

    public function summary(AccountingPeriod $accountingPeriod): JsonResponse
    {
        if ($accountingPeriod->tenant_id !== auth()->user()->tenant_id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        // Si la période est clôturée, retourner le résumé enregistré
        if ($accountingPeriod->status === 'closed' && $accountingPeriod->summary) {
            return response()->json($accountingPeriod->summary);
        }

        // Sinon, calculer en temps réel
        $summary = $this->calculatePeriodSummary(
            $accountingPeriod->tenant_id,
            $accountingPeriod->start_date,
            $accountingPeriod->end_date
        );

        return response()->json($summary);
    }

    private function calculatePeriodSummary(int $tenantId, $startDate, $endDate): array
    {
        // Use PosOrder instead of Sale for actual transactions
        $orders = PosOrder::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereIn('status', ['paid', 'validated', 'served']);

        $totalSales = (clone $orders)->sum('subtotal');
        $salesCount = (clone $orders)->count();
        
        // Calculate COGS from PosOrderItems
        $orderIds = (clone $orders)->pluck('id');
        $totalCogs = $orderIds->isNotEmpty() 
            ? PosOrderItem::whereIn('pos_order_id', $orderIds)
                ->selectRaw('SUM(COALESCE(quantity_ordered, 1) * COALESCE(unit_cost, 0)) as total')
                ->value('total') ?? 0
            : 0;

        $totalExpenses = Expense::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('amount');

        $totalPurchases = Purchase::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'received')
            ->sum('total');

        $grossProfit = $totalSales - $totalCogs;
        $netProfit = $grossProfit - $totalExpenses;

        return [
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
            'sales' => [
                'total' => (float) $totalSales,
                'count' => $salesCount,
                'cogs' => (float) $totalCogs,
            ],
            'expenses' => (float) $totalExpenses,
            'purchases' => (float) $totalPurchases,
            'gross_profit' => (float) $grossProfit,
            'net_profit' => (float) $netProfit,
            'gross_margin' => $totalSales > 0 ? round(($grossProfit / $totalSales) * 100, 2) : 0,
            'net_margin' => $totalSales > 0 ? round(($netProfit / $totalSales) * 100, 2) : 0,
            'calculated_at' => now()->toISOString(),
        ];
    }

    public function initializeYear(Request $request): JsonResponse
    {
        $user = auth()->user();

        $validated = $request->validate([
            'year' => 'required|integer|min:2020|max:2100',
        ]);

        $created = [];

        // Créer les 12 périodes mensuelles
        for ($month = 1; $month <= 12; $month++) {
            $period = AccountingPeriod::createMonthlyPeriod($user->tenant_id, $validated['year'], $month);
            if ($period->wasRecentlyCreated) {
                $created[] = $period->name;
            }
        }

        return response()->json([
            'message' => count($created) > 0 
                ? 'Périodes créées: ' . implode(', ', $created)
                : 'Toutes les périodes existent déjà',
            'created_count' => count($created),
        ]);
    }
}
