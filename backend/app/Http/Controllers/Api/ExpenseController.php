<?php

namespace App\Http\Controllers\Api;

use App\Models\Expense;
use App\Models\CashMovement;
use App\Models\CashRegisterSession;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ExpenseController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Store a new expense
     */
    public function store(Request $request): JsonResponse
    {
        $tenant_id = auth()->user()->tenant_id;

        $validated = $request->validate([
            'category' => 'required|string|max:50',
            'description' => 'required|string|max:500',
            'amount' => 'required|numeric|min:0.01',
            'is_fixed' => 'boolean',
            'payment_method' => 'required|string|in:especes,cash,kkiapay,fedapay,virement,cheque,credit_card,card,momo',
            'expense_date' => 'required|date',
        ]);

        $expense = Expense::create([
            'tenant_id' => $tenant_id,
            'user_id' => auth()->id(),
            'recorded_by' => auth()->id(),
            'is_fixed' => $validated['is_fixed'] ?? false,
            'date' => $validated['expense_date'],  // Set date field for the old column
            ...$validated,
        ]);

        // Enregistrer le mouvement de caisse si paiement en espèces
        if (in_array($validated['payment_method'], ['especes', 'cash'])) {
            try {
                $session = CashRegisterSession::getOpenSession($tenant_id);
                CashMovement::record(
                    $tenant_id,
                    auth()->id(),
                    'out',
                    'expense',
                    $validated['amount'],
                    "Dépense: {$validated['description']}",
                    $session?->id,
                    null,
                    'cash',
                    $expense->id,
                    'expense'
                );
            } catch (\Exception $e) {
                \Log::warning("Cash movement recording failed for expense {$expense->id}: " . $e->getMessage());
            }
        }

        return response()->json([
            'data' => $expense,
            'message' => 'Charge créée avec succès'
        ], 201);
    }

    /**
     * List all expenses for the tenant
     */
    public function index(Request $request): JsonResponse
    {
        $tenant_id = auth()->user()->tenant_id;
        
        $query = Expense::where('tenant_id', $tenant_id);

        // Filter by category
        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        // Filter by type (fixed/variable)
        if ($request->has('is_fixed')) {
            $query->where('is_fixed', (bool)$request->is_fixed);
        }

        // Filter by date range
        if ($request->has('from_date')) {
            $query->whereDate('expense_date', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->whereDate('expense_date', '<=', $request->to_date);
        }

        $expenses = $query->orderBy('expense_date', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'data' => $expenses->items(),
            'pagination' => [
                'total' => $expenses->total(),
                'per_page' => $expenses->perPage(),
                'current_page' => $expenses->currentPage(),
                'last_page' => $expenses->lastPage(),
            ]
        ]);
    }

    /**
     * Get a specific expense
     */
    public function show(Expense $expense): JsonResponse
    {
        $this->authorize('view', $expense);

        return response()->json(['data' => $expense]);
    }

    /**
     * Update an expense
     */
    public function update(Request $request, Expense $expense): JsonResponse
    {
        $this->authorize('update', $expense);

        $validated = $request->validate([
            'category' => 'string|max:50',
            'description' => 'string|max:500',
            'amount' => 'numeric|min:0.01',
            'is_fixed' => 'boolean',
            'payment_method' => 'string|in:especes,kkiapay,fedapay,virement,cheque,credit_card',
            'expense_date' => 'date',
        ]);

        $expense->update($validated);

        return response()->json([
            'data' => $expense,
            'message' => 'Charge mise à jour avec succès'
        ]);
    }

    /**
     * Delete an expense
     */
    public function destroy(Expense $expense): JsonResponse
    {
        $this->authorize('delete', $expense);

        $expense->delete();

        return response()->json([
            'message' => 'Charge supprimée avec succès'
        ]);
    }

    /**
     * Get expense statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $tenant_id = auth()->user()->tenant_id;
        
        $query = Expense::where('tenant_id', $tenant_id);

        // Date range
        if ($request->has('from_date')) {
            $query->whereDate('expense_date', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->whereDate('expense_date', '<=', $request->to_date);
        }

        $expenses = $query->get();

        $fixed_total = $expenses->where('is_fixed', true)->sum('amount');
        $variable_total = $expenses->where('is_fixed', false)->sum('amount');
        $total = $expenses->sum('amount');

        $by_category = $expenses->groupBy('category')
            ->map(fn($group) => $group->sum('amount'))
            ->toArray();

        return response()->json([
            'data' => [
                'total' => $total,
                'fixed' => $fixed_total,
                'variable' => $variable_total,
                'by_category' => $by_category,
                'count' => count($expenses),
            ]
        ]);
    }
}

