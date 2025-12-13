<?php

namespace App\Http\Controllers\Api;

use App\Models\CashRegisterSession;
use App\Models\CashMovement;
use App\Models\CashRemittance;
use App\Models\PosOrder;
use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class CashRegisterController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    // ========================================
    // SESSIONS DE CAISSE
    // ========================================

    /**
     * Session de caisse en cours
     */
    public function currentSession(Request $request): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $posId = $request->query('pos_id');

        $session = CashRegisterSession::getOpenSession($tenantId, $posId);

        if (!$session) {
            return response()->json(['data' => null, 'message' => 'Aucune session ouverte']);
        }

        $session->load(['openedByUser:id,name', 'pos:id,name']);

        return response()->json(['data' => $session]);
    }

    /**
     * Ouvrir une session de caisse
     */
    public function openSession(Request $request): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $userId = auth()->id();

        $validated = $request->validate([
            'opening_balance' => 'required|numeric|min:0',
            'pos_id' => 'nullable|exists:pos,id',
        ]);

        // Vérifier qu'il n'y a pas déjà une session ouverte
        $existing = CashRegisterSession::getOpenSession($tenantId, $validated['pos_id'] ?? null);
        if ($existing) {
            return response()->json([
                'message' => 'Une session de caisse est déjà ouverte',
                'data' => $existing,
            ], 422);
        }

        $session = CashRegisterSession::openSession(
            $tenantId,
            $userId,
            $validated['opening_balance'],
            $validated['pos_id'] ?? null
        );

        // Enregistrer le mouvement d'ouverture
        CashMovement::record(
            $tenantId,
            $userId,
            'in',
            'opening',
            $validated['opening_balance'],
            'Ouverture de caisse',
            $session->id,
            $validated['pos_id'] ?? null,
            'cash'
        );

        return response()->json(['data' => $session], 201);
    }

    /**
     * Fermer une session de caisse
     */
    public function closeSession(Request $request): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $userId = auth()->id();

        $validated = $request->validate([
            'session_id' => 'required|exists:cash_register_sessions,id',
            'closing_balance' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        $session = CashRegisterSession::where('tenant_id', $tenantId)
            ->where('id', $validated['session_id'])
            ->where('status', 'open')
            ->first();

        if (!$session) {
            return response()->json(['message' => 'Session non trouvée ou déjà fermée'], 404);
        }

        $session->close($validated['closing_balance'], $userId, $validated['notes'] ?? null);

        // Enregistrer le mouvement de fermeture
        CashMovement::record(
            $tenantId,
            $userId,
            'out',
            'closing',
            $validated['closing_balance'],
            'Fermeture de caisse',
            $session->id,
            $session->pos_id,
            'cash'
        );

        $session->load(['openedByUser:id,name', 'closedByUser:id,name']);

        return response()->json(['data' => $session]);
    }

    /**
     * Valider une session fermée (gérant)
     */
    public function validateSession(Request $request, $sessionId): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $userId = auth()->id();

        $session = CashRegisterSession::where('tenant_id', $tenantId)
            ->where('id', $sessionId)
            ->where('status', 'closed')
            ->first();

        if (!$session) {
            return response()->json(['message' => 'Session non trouvée ou non fermée'], 404);
        }

        $session->validate($userId);

        return response()->json(['data' => $session]);
    }

    /**
     * Historique des sessions
     */
    public function sessions(Request $request): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->toDateString());
        $posId = $request->query('pos_id');
        $status = $request->query('status');

        $query = CashRegisterSession::where('tenant_id', $tenantId)
            ->whereBetween('opened_at', [$from, $to . ' 23:59:59'])
            ->with(['openedByUser:id,name', 'closedByUser:id,name', 'pos:id,name']);

        if ($posId) {
            $query->where('pos_id', $posId);
        }

        if ($status) {
            $query->where('status', $status);
        }

        $sessions = $query->orderByDesc('opened_at')->paginate(20);

        return response()->json($sessions);
    }

    /**
     * Détail d'une session
     */
    public function sessionDetail($sessionId): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;

        $session = CashRegisterSession::where('tenant_id', $tenantId)
            ->where('id', $sessionId)
            ->with([
                'openedByUser:id,name',
                'closedByUser:id,name',
                'validatedByUser:id,name',
                'pos:id,name',
                'movements',
                'remittances.fromUser:id,name',
            ])
            ->first();

        if (!$session) {
            return response()->json(['message' => 'Session non trouvée'], 404);
        }

        return response()->json(['data' => $session]);
    }

    // ========================================
    // MOUVEMENTS DE CAISSE
    // ========================================

    /**
     * Enregistrer un mouvement manuel
     */
    public function recordMovement(Request $request): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $userId = auth()->id();

        $validated = $request->validate([
            'type' => 'required|in:in,out',
            'category' => 'required|in:deposit,withdrawal,expense,adjustment',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'required|string|max:255',
            'payment_method' => 'nullable|string',
            'session_id' => 'nullable|exists:cash_register_sessions,id',
            'pos_id' => 'nullable|exists:pos,id',
        ]);

        // Vérifier la session si fournie
        if ($validated['session_id']) {
            $session = CashRegisterSession::where('tenant_id', $tenantId)
                ->where('id', $validated['session_id'])
                ->where('status', 'open')
                ->first();

            if (!$session) {
                return response()->json(['message' => 'Session non trouvée ou fermée'], 422);
            }
        }

        $movement = CashMovement::record(
            $tenantId,
            $userId,
            $validated['type'],
            $validated['category'],
            $validated['amount'],
            $validated['description'],
            $validated['session_id'] ?? null,
            $validated['pos_id'] ?? null,
            $validated['payment_method'] ?? 'cash'
        );

        return response()->json(['data' => $movement], 201);
    }

    /**
     * Liste des mouvements
     */
    public function movements(Request $request): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $from = $request->query('from', now()->startOfDay()->toDateTimeString());
        $to = $request->query('to', now()->endOfDay()->toDateTimeString());
        $type = $request->query('type');
        $category = $request->query('category');
        $sessionId = $request->query('session_id');
        $posId = $request->query('pos_id');

        $query = CashMovement::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$from, $to])
            ->with(['user:id,name', 'pos:id,name']);

        if ($type) $query->where('type', $type);
        if ($category) $query->where('category', $category);
        if ($sessionId) $query->where('session_id', $sessionId);
        if ($posId) $query->where('pos_id', $posId);

        $movements = $query->orderByDesc('created_at')->paginate(50);

        return response()->json($movements);
    }

    // ========================================
    // REMISES DE FONDS
    // ========================================

    /**
     * Créer une remise de fonds
     */
    public function createRemittance(Request $request): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $userId = auth()->id();

        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'session_id' => 'nullable|exists:cash_register_sessions,id',
            'pos_id' => 'nullable|exists:pos,id',
            'notes' => 'nullable|string|max:500',
        ]);

        $remittance = CashRemittance::createRemittance(
            $tenantId,
            $userId,
            $validated['amount'],
            $validated['session_id'] ?? null,
            $validated['pos_id'] ?? null,
            $validated['notes'] ?? null
        );

        $remittance->load('fromUser:id,name');

        return response()->json(['data' => $remittance], 201);
    }

    /**
     * Recevoir une remise (gérant)
     */
    public function receiveRemittance(Request $request, $remittanceId): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $userId = auth()->id();

        $remittance = CashRemittance::where('tenant_id', $tenantId)
            ->where('id', $remittanceId)
            ->where('status', 'pending')
            ->first();

        if (!$remittance) {
            return response()->json(['message' => 'Remise non trouvée ou déjà traitée'], 404);
        }

        $remittance->receive($userId);
        $remittance->load(['fromUser:id,name', 'toUser:id,name']);

        return response()->json(['data' => $remittance]);
    }

    /**
     * Valider une remise
     */
    public function validateRemittance(Request $request, $remittanceId): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $userId = auth()->id();

        $remittance = CashRemittance::where('tenant_id', $tenantId)
            ->where('id', $remittanceId)
            ->where('status', 'received')
            ->first();

        if (!$remittance) {
            return response()->json(['message' => 'Remise non trouvée ou non reçue'], 404);
        }

        $remittance->validate($userId);

        return response()->json(['data' => $remittance]);
    }

    /**
     * Liste des remises
     */
    public function remittances(Request $request): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->toDateString());
        $status = $request->query('status');

        $query = CashRemittance::where('tenant_id', $tenantId)
            ->whereBetween('remitted_at', [$from, $to . ' 23:59:59'])
            ->with(['fromUser:id,name', 'toUser:id,name', 'pos:id,name']);

        if ($status) {
            $query->where('status', $status);
        }

        $remittances = $query->orderByDesc('remitted_at')->paginate(20);

        return response()->json($remittances);
    }

    /**
     * Remises en attente (pour le gérant)
     */
    public function pendingRemittances(): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;

        $remittances = CashRemittance::where('tenant_id', $tenantId)
            ->where('status', 'pending')
            ->with(['fromUser:id,name', 'pos:id,name'])
            ->orderByDesc('remitted_at')
            ->get();

        return response()->json(['data' => $remittances]);
    }

    // ========================================
    // DASHBOARD GÉRANT
    // ========================================

    /**
     * Résumé consolidé pour le gérant - avec cache 30s
     */
    public function managerDashboard(Request $request): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $from = $request->query('from', now()->startOfDay()->toDateTimeString());
        $to = $request->query('to', now()->endOfDay()->toDateTimeString());

        $cacheKey = "cash_manager_dashboard_{$tenantId}_" . today()->format('Ymd');
        
        $data = Cache::remember($cacheKey, 30, function () use ($tenantId, $from, $to) {
            // Sessions ouvertes
            $openSessions = CashRegisterSession::where('tenant_id', $tenantId)
                ->where('status', 'open')
                ->with(['openedByUser:id,name', 'pos:id,name'])
                ->get();

            // Totaux du jour - une seule requête
            $todayStats = DB::table('cash_movements')
                ->where('tenant_id', $tenantId)
                ->whereBetween('created_at', [$from, $to])
                ->selectRaw("
                    SUM(CASE WHEN type = 'in' THEN amount ELSE 0 END) as total_in,
                    SUM(CASE WHEN type = 'out' THEN amount ELSE 0 END) as total_out,
                    COUNT(*) as transactions_count
                ")
                ->first();

            // Ventes par méthode de paiement
            $salesByMethod = PosOrder::where('tenant_id', $tenantId)
                ->whereBetween('created_at', [$from, $to])
                ->where('status', 'completed')
                ->selectRaw('payment_method, SUM(total) as total, COUNT(*) as count')
                ->groupBy('payment_method')
                ->get();

            // Remises - requêtes simplifiées
            $pendingRemittances = CashRemittance::where('tenant_id', $tenantId)
                ->where('status', 'pending')->sum('amount') ?? 0;

            $receivedRemittances = CashRemittance::where('tenant_id', $tenantId)
                ->whereIn('status', ['received', 'validated'])
                ->whereBetween('received_at', [$from, $to])->sum('amount') ?? 0;

            $expenses = Expense::where('tenant_id', $tenantId)
                ->whereBetween('created_at', [$from, $to])->sum('amount') ?? 0;

            return [
                'open_sessions' => $openSessions,
                'today' => [
                    'total_in' => $todayStats->total_in ?? 0,
                    'total_out' => $todayStats->total_out ?? 0,
                    'balance' => ($todayStats->total_in ?? 0) - ($todayStats->total_out ?? 0),
                    'transactions_count' => $todayStats->transactions_count ?? 0,
                ],
                'sales_by_method' => $salesByMethod,
                'remittances' => [
                    'pending' => $pendingRemittances,
                    'received_today' => $receivedRemittances,
                ],
                'expenses' => $expenses,
            ];
        });

        return response()->json(['data' => $data]);
    }

    /**
     * Journal de caisse
     */
    public function cashJournal(Request $request): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $date = $request->query('date', now()->toDateString());

        // Toutes les sessions du jour
        $sessions = CashRegisterSession::where('tenant_id', $tenantId)
            ->whereDate('opened_at', $date)
            ->with(['openedByUser:id,name', 'closedByUser:id,name', 'pos:id,name'])
            ->get();

        // Tous les mouvements du jour
        $movements = CashMovement::where('tenant_id', $tenantId)
            ->whereDate('created_at', $date)
            ->with(['user:id,name', 'pos:id,name'])
            ->orderBy('created_at')
            ->get();

        // Calcul du solde courant
        $runningBalance = 0;
        $movements = $movements->map(function ($mv) use (&$runningBalance) {
            $runningBalance += ($mv->type === 'in' ? $mv->amount : -$mv->amount);
            $mv->running_balance = $runningBalance;
            return $mv;
        });

        // Totaux
        $totals = [
            'opening' => $movements->where('category', 'opening')->sum('amount'),
            'sales' => $movements->where('category', 'sale')->sum('amount'),
            'deposits' => $movements->where('category', 'deposit')->sum('amount'),
            'withdrawals' => $movements->where('category', 'withdrawal')->sum('amount'),
            'expenses' => $movements->where('category', 'expense')->sum('amount'),
            'transfers_in' => $movements->where('category', 'transfer_in')->sum('amount'),
            'transfers_out' => $movements->where('category', 'transfer_out')->sum('amount'),
            'closing' => $movements->where('category', 'closing')->sum('amount'),
        ];

        return response()->json([
            'data' => [
                'date' => $date,
                'sessions' => $sessions,
                'movements' => $movements,
                'totals' => $totals,
                'final_balance' => $runningBalance,
            ],
        ]);
    }
}
