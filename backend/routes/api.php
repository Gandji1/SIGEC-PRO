<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TenantController;
use App\Http\Controllers\Api\TenantConfigurationController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\CollaboratorController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\PurchaseController;
use App\Http\Controllers\Api\TransferController;
use App\Http\Controllers\Api\StockController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\AccountingController;
use App\Http\Controllers\Api\ChartOfAccountsController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\WarehouseController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\LowStockAlertController;
use App\Http\Controllers\Api\TransferBondController;
use App\Http\Controllers\Api\DeliveryNoteController;
use App\Http\Controllers\Api\ProcurementDocumentController;
use App\Http\Controllers\Api\ApprovisionnementController;
use App\Http\Controllers\Api\InventoryReconciliationController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'time' => now()->toISOString(),
    ]);
});

// Routes d'authentification avec rate limiting
Route::middleware('throttle.auth:register')->post('/register', [AuthController::class, 'register']);
Route::middleware('throttle.auth:login')->post('/login', [AuthController::class, 'login']);

// 2FA verification (public - avant auth complète) avec rate limiting
Route::middleware('throttle.auth:2fa')->post('/2fa/verify', [\App\Http\Controllers\Api\TwoFactorController::class, 'verify']);

// Password Reset routes (public) avec rate limiting
Route::middleware('throttle.auth:login')->group(function () {
    Route::post('/password/forgot', [\App\Http\Controllers\Api\PasswordResetController::class, 'sendResetLink']);
    Route::post('/password/verify-token', [\App\Http\Controllers\Api\PasswordResetController::class, 'verifyToken']);
    Route::post('/password/reset', [\App\Http\Controllers\Api\PasswordResetController::class, 'resetPassword']);
});

// Email Verification (public - pour vérifier avec token)
Route::post('/email/verify', [\App\Http\Controllers\Api\EmailVerificationController::class, 'verifyEmail']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth routes
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);

    // Email Verification routes (authenticated)
    Route::prefix('email')->group(function () {
        Route::post('/send-verification', [\App\Http\Controllers\Api\EmailVerificationController::class, 'sendVerificationEmail']);
        Route::get('/status', [\App\Http\Controllers\Api\EmailVerificationController::class, 'status']);
    });

    // 2FA routes (authenticated)
    Route::prefix('2fa')->group(function () {
        Route::get('/status', [\App\Http\Controllers\Api\TwoFactorController::class, 'status']);
        Route::post('/enable', [\App\Http\Controllers\Api\TwoFactorController::class, 'enable']);
        Route::post('/confirm', [\App\Http\Controllers\Api\TwoFactorController::class, 'confirm']);
        Route::post('/disable', [\App\Http\Controllers\Api\TwoFactorController::class, 'disable']);
        Route::post('/regenerate-codes', [\App\Http\Controllers\Api\TwoFactorController::class, 'regenerateRecoveryCodes']);
    });

    // Tenant Configuration routes (Owner/SuperAdmin ONLY - Manager n'a plus accès)
    Route::get('tenant-config/payment-methods', [TenantConfigurationController::class, 'paymentMethods']);
    Route::middleware('role:owner,admin,super_admin')->prefix('tenant-config')->group(function () {
        Route::get('/', [TenantConfigurationController::class, 'show']);
        Route::put('/', [TenantConfigurationController::class, 'update']);
        Route::post('/payment-methods', [TenantConfigurationController::class, 'configurePaymentMethod']);
        Route::get('/pos', [TenantConfigurationController::class, 'posList']);
        Route::post('/pos', [TenantConfigurationController::class, 'createPos']);
    });

    // Tenant Settings routes (Owner/SuperAdmin ONLY - Manager n'a plus accès)
    Route::middleware('role:owner,admin,super_admin')->prefix('tenant')->group(function () {
        Route::get('/settings', [TenantController::class, 'getSettings']);
        Route::put('/settings', [TenantController::class, 'updateSettings']);
    });

    // Tenant Payment Config routes (Owner ONLY - clés PSP du tenant)
    Route::middleware('role:owner,admin')->prefix('tenant/payment-config')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\TenantPaymentConfigController::class, 'index']);
        Route::get('/{provider}', [\App\Http\Controllers\Api\TenantPaymentConfigController::class, 'show']);
        Route::post('/', [\App\Http\Controllers\Api\TenantPaymentConfigController::class, 'store']);
        Route::post('/{provider}/toggle', [\App\Http\Controllers\Api\TenantPaymentConfigController::class, 'toggle']);
        Route::post('/{provider}/test', [\App\Http\Controllers\Api\TenantPaymentConfigController::class, 'test']);
        Route::get('/logs/webhooks', [\App\Http\Controllers\Api\TenantPaymentConfigController::class, 'webhookLogs']);
    }); 

    // Subscription Status routes (pour le tenant courant)
    Route::prefix('subscription')->group(function () {
        Route::get('/status', [\App\Http\Controllers\Api\SubscriptionStatusController::class, 'status']);
        Route::get('/modules', [\App\Http\Controllers\Api\SubscriptionStatusController::class, 'modules']);
        Route::get('/check-module/{moduleCode}', [\App\Http\Controllers\Api\SubscriptionStatusController::class, 'checkModule']);
        Route::get('/check-limit/{limitType}', [\App\Http\Controllers\Api\SubscriptionStatusController::class, 'checkLimit']);
        Route::get('/plans', [\App\Http\Controllers\Api\SubscriptionStatusController::class, 'plans']);
        Route::post('/subscribe', [\App\Http\Controllers\Api\SubscriptionStatusController::class, 'subscribe']);
    });

    // Tenant Management routes (Super Admin only)
    Route::middleware('role:super_admin')->prefix('tenants')->group(function () {
        Route::get('/', [TenantController::class, 'index']);
        Route::post('/', [TenantController::class, 'store']);
        Route::get('/{tenant}', [TenantController::class, 'show']);
        Route::put('/{tenant}', [TenantController::class, 'update']);
        Route::delete('/{tenant}', [TenantController::class, 'destroy']);
        Route::post('/{tenant}/suspend', [TenantController::class, 'suspend']);
        Route::post('/{tenant}/activate', [TenantController::class, 'activate']);
        Route::post('/{tenant}/upload-logo', [TenantController::class, 'uploadLogo']);
        Route::delete('/{tenant}/delete-logo', [TenantController::class, 'deleteLogo']);
    });

    // User Management routes (Owner ONLY - Manager n'a plus accès)
    Route::middleware('role:owner,admin,super_admin')->prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::post('/', [UserController::class, 'store']);
        Route::get('/assignable', [UserController::class, 'getAssignable']);
        Route::get('/by-pos/{posId}', [UserController::class, 'getByPos']);
        Route::get('/{user}', [UserController::class, 'show']);
        Route::put('/{user}', [UserController::class, 'update']);
        Route::delete('/{user}', [UserController::class, 'destroy']);
        Route::post('/{user}/assign-role', [UserController::class, 'assignRole']);
        Route::post('/{user}/assign-pos', [UserController::class, 'assignPos']);
        Route::post('/{user}/reset-password', [UserController::class, 'resetPassword']);
    });

    // Collaborators Management routes (Owner ONLY - Manager n'a plus accès)
    Route::middleware('role:owner,admin')->prefix('collaborators')->group(function () {
        Route::get('/', [CollaboratorController::class, 'index']);
        Route::post('/', [CollaboratorController::class, 'store']);
        Route::put('/{user}', [CollaboratorController::class, 'update']);
        Route::delete('/{user}', [CollaboratorController::class, 'destroy']);
        Route::post('/{user}/reset-password', [CollaboratorController::class, 'resetPassword']);
        Route::get('/roles', [CollaboratorController::class, 'roles']);
    });

    // Dashboard routes (NEW)
    Route::prefix('dashboard')->group(function () {
        Route::get('/stats', [\App\Http\Controllers\Api\RoleDashboardController::class, 'globalStats']);
        Route::get('/monthly-report', [DashboardController::class, 'monthlyReport']);
        Route::get('/manager/stats', [\App\Http\Controllers\Api\RoleDashboardController::class, 'managerStats']);
        Route::get('/last7days', [DashboardController::class, 'last7Days']);
        // Role-based dashboard tasks
        Route::get('/warehouse-gros/tasks', [\App\Http\Controllers\Api\RoleDashboardController::class, 'warehouseGrosTasks']);
        Route::get('/warehouse-detail/tasks', [\App\Http\Controllers\Api\RoleDashboardController::class, 'warehouseDetailTasks']);
    });

    // Expenses routes (NEW)
    Route::prefix('expenses')->group(function () {
        Route::get('/', [ExpenseController::class, 'index']);
        Route::post('/', [ExpenseController::class, 'store']);
        Route::get('/statistics', [ExpenseController::class, 'statistics']);
        Route::get('/{expense}', [ExpenseController::class, 'show']);
        Route::put('/{expense}', [ExpenseController::class, 'update']);
        Route::delete('/{expense}', [ExpenseController::class, 'destroy']);
    });

    // Reports routes (NEW - enriched)
    Route::prefix('reports')->group(function () {
        Route::get('/sales-journal', [ReportController::class, 'salesJournal']);
        Route::get('/purchases-journal', [ReportController::class, 'purchasesJournal']);
        Route::get('/profit-loss', [ReportController::class, 'profitLoss']);
        Route::get('/trial-balance', [ReportController::class, 'trialBalance']);
        Route::get('/sales-journal/export', [ReportController::class, 'exportSalesXlsx']);
    });

    // Warehouse routes (NEW)
    Route::prefix('warehouses')->group(function () {
        Route::get('/', [WarehouseController::class, 'index']);
        Route::post('/', [WarehouseController::class, 'store']);
        Route::get('/{warehouse}', [WarehouseController::class, 'show']);
        Route::put('/{warehouse}', [WarehouseController::class, 'update']);
        Route::delete('/{warehouse}', [WarehouseController::class, 'destroy']);
        Route::get('/{warehouse}/stock-value', [WarehouseController::class, 'stockValue']);
        Route::get('/{warehouse}/movements', [WarehouseController::class, 'movements']);
    });

    // Inventory routes (NEW)
    Route::prefix('inventories')->group(function () {
        Route::get('/', [InventoryController::class, 'index']);
        Route::post('/', [InventoryController::class, 'store']);
        Route::get('/{inventory}', [InventoryController::class, 'show']);
        Route::delete('/{inventory}', [InventoryController::class, 'destroy']);
        Route::post('/{inventory}/start', [InventoryController::class, 'start']);
        Route::post('/{inventory}/items', [InventoryController::class, 'addItem']);
        Route::post('/{inventory}/complete', [InventoryController::class, 'complete']);
        Route::post('/{inventory}/validate', [InventoryController::class, 'validate']);
        Route::get('/{inventory}/summary', [InventoryController::class, 'summary']);
        Route::post('/{inventory}/import-csv', [InventoryController::class, 'importCSV']);
        Route::get('/{inventory}/export-csv', [InventoryController::class, 'exportCSV']);
    });

    // Inventory Reconciliation routes (NEW - Iteration 3)
    Route::prefix('inventory-counts')->group(function () {
        Route::post('/start', [InventoryReconciliationController::class, 'start']);
        Route::post('/{count}/items', [InventoryReconciliationController::class, 'recordItem']);
        Route::post('/{count}/complete', [InventoryReconciliationController::class, 'complete']);
        Route::get('/{count}/summary', [InventoryReconciliationController::class, 'summary']);
        Route::get('/{count}/variances', [InventoryReconciliationController::class, 'variances']);
        Route::post('/{count}/cancel', [InventoryReconciliationController::class, 'cancel']);
        Route::get('/{count}/report', [InventoryReconciliationController::class, 'report']);
    });

    // Product routes
    Route::apiResource('products', ProductController::class);
    Route::get('/products/low-stock', [ProductController::class, 'lowStock']);
    Route::get('/products/barcode/{barcode}', [ProductController::class, 'byBarcode']);
    Route::post('/products/{product}/upload-image', [ProductController::class, 'uploadImage']);
    Route::delete('/products/{product}/delete-image', [ProductController::class, 'deleteImage']);

    // Sale routes
    Route::apiResource('sales', SaleController::class);
    Route::post('/sales/{sale}/complete', [SaleController::class, 'complete']);
    Route::post('/sales/{sale}/cancel', [SaleController::class, 'cancel']);
    Route::get('/sales/report', [SaleController::class, 'report']);

    // Purchase routes
    Route::apiResource('purchases', PurchaseController::class);
    Route::post('/purchases/{purchase}/add-item', [PurchaseController::class, 'addItem']);
    Route::delete('/purchases/{purchase}/items/{item}', [PurchaseController::class, 'removeItem']);
    // Nouveau flux: Gérant → Tenant → Fournisseur
    Route::post('/purchases/{purchase}/submit-for-approval', [PurchaseController::class, 'submitForApproval']); // Gérant soumet
    Route::post('/purchases/{purchase}/approve', [PurchaseController::class, 'approveByTenant']); // Tenant approuve
    Route::post('/purchases/{purchase}/reject', [PurchaseController::class, 'rejectByTenant']); // Tenant rejette
    Route::post('/purchases/{purchase}/confirm', [PurchaseController::class, 'confirm']); // Compatibilité
    Route::post('/purchases/{purchase}/receive', [PurchaseController::class, 'receive']);
    Route::post('/purchases/{purchase}/cancel', [PurchaseController::class, 'cancel']);
    Route::get('/purchases/report', [PurchaseController::class, 'report']);

    // Transfer routes
    Route::prefix('transfers')->group(function () {
        Route::get('/', [TransferController::class, 'index']);
        Route::post('/', [TransferController::class, 'store']);
        Route::get('/pending', [TransferController::class, 'pending']);
        Route::get('/statistics', [TransferController::class, 'statistics']);
        Route::get('/{transfer}', [TransferController::class, 'show']);
        Route::post('/{transfer}/approve', [TransferController::class, 'approve']);
        Route::post('/{transfer}/execute', [TransferController::class, 'execute']);
        Route::post('/{transfer}/cancel', [TransferController::class, 'cancel']);
    });

    // Stock routes
    Route::apiResource('stocks', StockController::class, ['only' => ['index', 'show']]);
    Route::post('/stocks/adjust', [StockController::class, 'adjust']);
    Route::post('/stocks/reserve', [StockController::class, 'reserve']);
    Route::post('/stocks/release', [StockController::class, 'release']);
    Route::post('/stocks/transfer', [StockController::class, 'transfer']);
    Route::get('/stocks/low-stock', [StockController::class, 'lowStock']);
    Route::get('/stocks/alerts', [StockController::class, 'alerts']);
    Route::get('/stocks/suggested-orders', [StockController::class, 'suggestedOrders']);
    Route::get('/stocks/summary', [StockController::class, 'summary']);

    // Low Stock Alerts routes (NEW)
    Route::prefix('low-stock-alerts')->group(function () {
        Route::post('/check', [LowStockAlertController::class, 'checkAlerts']);
        Route::get('/summary', [LowStockAlertController::class, 'summary']);
        Route::get('/', [LowStockAlertController::class, 'index']);
        Route::post('/', [LowStockAlertController::class, 'store']);
        Route::get('/{lowStockAlert}', [LowStockAlertController::class, 'show']);
        Route::put('/{lowStockAlert}', [LowStockAlertController::class, 'update']);
        Route::delete('/{lowStockAlert}', [LowStockAlertController::class, 'destroy']);
        Route::post('/{lowStockAlert}/resolve', [LowStockAlertController::class, 'resolve']);
        Route::post('/{lowStockAlert}/ignore', [LowStockAlertController::class, 'ignore']);
    });

    // Customer routes (Owner ONLY - Manager n'a plus accès)
    Route::middleware('role:owner,admin,super_admin')->group(function () {
        Route::apiResource('customers', CustomerController::class);
        Route::get('/customers/{customer}/statistics', [CustomerController::class, 'statistics']);
    });

    // Supplier routes (Owner, Manager, Admin, Tenant)
    Route::middleware('role:owner,manager,admin,super_admin,tenant')->group(function () {
        Route::apiResource('suppliers', SupplierController::class);
        Route::get('/suppliers/{supplier}/statistics', [SupplierController::class, 'statistics']);
    });

    // Chart of Accounts routes
    Route::prefix('chart-of-accounts')->group(function () {
        Route::post('/initialize', [ChartOfAccountsController::class, 'initialize']);
        Route::get('/', [ChartOfAccountsController::class, 'index']);
        Route::get('/summary', [ChartOfAccountsController::class, 'summary']);
        Route::get('/business-types', [ChartOfAccountsController::class, 'getBusinessTypes']);
        Route::get('/by-type/{type}', [ChartOfAccountsController::class, 'getByType']);
        Route::get('/by-subtype/{subtype}', [ChartOfAccountsController::class, 'getBySubType']);
        Route::get('/{id}', [ChartOfAccountsController::class, 'show']);
        Route::put('/{id}', [ChartOfAccountsController::class, 'update']);
    });

    // Accounting routes
    Route::prefix('accounting')->group(function () {
        Route::get('/summary', [AccountingController::class, 'summary']);
        Route::get('/income-statement', [AccountingController::class, 'incomeStatement']);
        Route::get('/balance-sheet', [AccountingController::class, 'balanceSheet']);
        Route::get('/trial-balance', [AccountingController::class, 'trialBalance']);
        Route::get('/balance', [AccountingController::class, 'trialBalance']); // Alias
        Route::get('/ledger', [AccountingController::class, 'ledger']);
        Route::get('/grand-livre', [AccountingController::class, 'ledger']); // Alias FR
        Route::get('/journal', [AccountingController::class, 'journal']);
        Route::get('/journals', [AccountingController::class, 'journal']); // Alias
        Route::get('/sig', [AccountingController::class, 'sig']); // Soldes Intermédiaires de Gestion
        Route::get('/cash-report', [AccountingController::class, 'cashReport']);
        Route::get('/caisse', [AccountingController::class, 'cashReport']); // Alias FR
        Route::post('/post-entries', [AccountingController::class, 'postEntry']);
        
        // Nouveaux états financiers
        Route::get('/financial-statements', [AccountingController::class, 'financialStatements']);
        Route::get('/etats-financiers', [AccountingController::class, 'financialStatements']); // Alias FR
        Route::get('/caf', [AccountingController::class, 'selfFinancingCapacity']); // Capacité d'Autofinancement
        Route::get('/capacite-autofinancement', [AccountingController::class, 'selfFinancingCapacity']); // Alias FR
        Route::get('/cash-flow', [AccountingController::class, 'cashFlowStatement']); // Flux de trésorerie
        Route::get('/flux-tresorerie', [AccountingController::class, 'cashFlowStatement']); // Alias FR
        Route::get('/ratios', [AccountingController::class, 'financialRatios']); // Ratios financiers
    });

    // Report routes aliases
    Route::prefix('reports')->group(function () {
        Route::get('/sales', [ReportController::class, 'salesJournal']); // Alias
    });

    // Export routes
    Route::prefix('export')->group(function () {
        Route::get('/sales/excel', [ExportController::class, 'salesToExcel']);
        Route::get('/sales/pdf', [ExportController::class, 'salesToPdf']);
        Route::get('/purchases/excel', [ExportController::class, 'purchasesToExcel']);
        Route::get('/purchases/pdf', [ExportController::class, 'purchasesToPdf']);
        Route::get('/sales/{sale}/invoice', [ExportController::class, 'generateInvoicePdf']);
        Route::get('/sales/{sale}/receipt', [ExportController::class, 'generateReceiptPdf']);
        Route::get('/accounting/report', [ExportController::class, 'exportAccountingReport']);
    });

    // Payment routes (PSP - Fedapay/Kakiapay)
    Route::prefix('payments')->group(function () {
        Route::post('/initialize', [PaymentController::class, 'initialize']);
        Route::post('/verify', [PaymentController::class, 'verify']);
        Route::get('/{reference}/status', [PaymentController::class, 'status']);
    });

    // Internal Documents routes (NEW)
    Route::prefix('transfer-bonds')->group(function () {
        Route::get('/', [TransferBondController::class, 'index']);
        Route::post('/', [TransferBondController::class, 'store']);
        Route::get('/{transferBond}', [TransferBondController::class, 'show']);
        Route::put('/{transferBond}', [TransferBondController::class, 'update']);
        Route::delete('/{transferBond}', [TransferBondController::class, 'destroy']);
        Route::post('/{transferBond}/execute', [TransferBondController::class, 'execute']);
    });

    Route::prefix('delivery-notes')->group(function () {
        Route::get('/', [DeliveryNoteController::class, 'index']);
        Route::post('/', [DeliveryNoteController::class, 'store']);
        Route::get('/{deliveryNote}', [DeliveryNoteController::class, 'show']);
        Route::put('/{deliveryNote}', [DeliveryNoteController::class, 'update']);
        Route::delete('/{deliveryNote}', [DeliveryNoteController::class, 'destroy']);
        Route::post('/{deliveryNote}/deliver', [DeliveryNoteController::class, 'deliver']);
    });

    Route::prefix('procurement-documents')->group(function () {
        Route::get('/', [ProcurementDocumentController::class, 'index']);
        Route::post('/', [ProcurementDocumentController::class, 'store']);
        Route::get('/{procurementDocument}', [ProcurementDocumentController::class, 'show']);
        Route::put('/{procurementDocument}', [ProcurementDocumentController::class, 'update']);
        Route::delete('/{procurementDocument}', [ProcurementDocumentController::class, 'destroy']);
        Route::post('/{procurementDocument}/approve', [ProcurementDocumentController::class, 'approve']);
        Route::post('/{procurementDocument}/receive', [ProcurementDocumentController::class, 'receive']);
    });

    // ========================================
    // APPROVISIONNEMENT MODULE
    // ========================================
    Route::prefix('approvisionnement')->group(function () {
        // Dashboards
        Route::get('/gros/dashboard', [ApprovisionnementController::class, 'grosDashboard']);
        Route::get('/detail/dashboard', [ApprovisionnementController::class, 'detailDashboard']);

        // Stock summary
        Route::get('/stock/summary', [ApprovisionnementController::class, 'getStockSummary']);
        Route::get('/stock/warehouse/{warehouse}', [ApprovisionnementController::class, 'getWarehouseStock']);

        // Achats (Gros)
        Route::get('/purchases', [ApprovisionnementController::class, 'listPurchases']);
        Route::post('/purchases', [ApprovisionnementController::class, 'createPurchase']);
        Route::get('/purchases/{purchase}', [ApprovisionnementController::class, 'showPurchase']);
        Route::post('/purchases/{purchase}/submit', [ApprovisionnementController::class, 'submitPurchase']);
        Route::post('/purchases/{purchase}/receive', [ApprovisionnementController::class, 'receivePurchase']);

        // Demandes de stock (Detail -> Gros)
        Route::get('/requests', [ApprovisionnementController::class, 'listRequests']);
        Route::post('/requests', [ApprovisionnementController::class, 'createRequest']);
        Route::get('/requests/{stockRequest}', [ApprovisionnementController::class, 'showRequest']);
        Route::post('/requests/{stockRequest}/submit', [ApprovisionnementController::class, 'submitRequest']);
        Route::post('/requests/{stockRequest}/approve', [ApprovisionnementController::class, 'approveRequest']);
        Route::post('/requests/{stockRequest}/reject', [ApprovisionnementController::class, 'rejectRequest']);

        // Transferts
        Route::get('/transfers', [ApprovisionnementController::class, 'listTransfers']);
        Route::get('/transfers/{transfer}', [ApprovisionnementController::class, 'showTransfer']);
        Route::post('/transfers/{transfer}/execute', [ApprovisionnementController::class, 'executeTransfer']);
        Route::post('/transfers/{transfer}/receive', [ApprovisionnementController::class, 'receiveTransfer']);
        Route::post('/transfers/{transfer}/validate', [ApprovisionnementController::class, 'validateTransfer']);

        // Inventaires
        Route::get('/inventories', [ApprovisionnementController::class, 'listInventories']);
        Route::post('/inventories', [ApprovisionnementController::class, 'createInventory']);
        Route::post('/inventories/{inventory}/validate', [ApprovisionnementController::class, 'validateInventory']);

        // Mouvements de stock
        Route::get('/movements', [ApprovisionnementController::class, 'listMovements']);

        // Exports
        Route::get('/exports/dashboard', [ApprovisionnementController::class, 'exportDashboard']);
        Route::get('/exports/stock', [ApprovisionnementController::class, 'exportStock']);
        Route::get('/exports/movements', [ApprovisionnementController::class, 'exportMovements']);
        Route::get('/exports/purchases', [ApprovisionnementController::class, 'exportPurchases']);

        // Commandes POS
        Route::get('/orders', [ApprovisionnementController::class, 'listPosOrders']);
        Route::post('/orders', [ApprovisionnementController::class, 'createPosOrder']);
        Route::get('/orders/{posOrder}', [ApprovisionnementController::class, 'showPosOrder']);
        Route::post('/orders/{posOrder}/serve', [ApprovisionnementController::class, 'servePosOrder']);
        Route::post('/orders/{posOrder}/validate', [ApprovisionnementController::class, 'validatePosOrder']);
    });
});

// Platform routes (Super Admin) - Legacy
Route::middleware(['auth:sanctum', 'role:super_admin'])->prefix('platform')->group(function () {
    Route::get('/stats', [\App\Http\Controllers\Api\PlatformController::class, 'stats']);
    Route::get('/logs', [\App\Http\Controllers\Api\PlatformController::class, 'logs']);
});

// Super Admin routes - Backoffice complet
Route::middleware(['auth:sanctum', 'role:super_admin'])->prefix('superadmin')->group(function () {
    // Dashboard
    Route::get('/dashboard', [\App\Http\Controllers\Api\SuperAdmin\DashboardController::class, 'index']);
    Route::get('/dashboard/stats', [\App\Http\Controllers\Api\SuperAdmin\DashboardController::class, 'stats']);
    Route::get('/dashboard/health', [\App\Http\Controllers\Api\SuperAdmin\DashboardController::class, 'health']);

    // Gestion des Tenants
    Route::get('/tenants', [\App\Http\Controllers\Api\SuperAdmin\TenantManagementController::class, 'index']);
    Route::post('/tenants', [\App\Http\Controllers\Api\SuperAdmin\TenantManagementController::class, 'store']);
    Route::get('/tenants/{tenant}', [\App\Http\Controllers\Api\SuperAdmin\TenantManagementController::class, 'show']);
    Route::put('/tenants/{tenant}', [\App\Http\Controllers\Api\SuperAdmin\TenantManagementController::class, 'update']);
    Route::delete('/tenants/{tenant}', [\App\Http\Controllers\Api\SuperAdmin\TenantManagementController::class, 'destroy']); 
    Route::post('/tenants/{tenant}/suspend', [\App\Http\Controllers\Api\SuperAdmin\TenantManagementController::class, 'suspend']);
    Route::post('/tenants/{tenant}/activate', [\App\Http\Controllers\Api\SuperAdmin\TenantManagementController::class, 'activate']);
    Route::post('/tenants/{tenant}/impersonate', [\App\Http\Controllers\Api\SuperAdmin\TenantManagementController::class, 'impersonate']);
    Route::get('/tenants/{tenant}/stats', [\App\Http\Controllers\Api\SuperAdmin\TenantManagementController::class, 'stats']);

    // Abonnements et Plans
    Route::get('/plans', [\App\Http\Controllers\Api\SuperAdmin\SubscriptionController::class, 'plans']);
    Route::post('/plans', [\App\Http\Controllers\Api\SuperAdmin\SubscriptionController::class, 'createPlan']);
    Route::put('/plans/{plan}', [\App\Http\Controllers\Api\SuperAdmin\SubscriptionController::class, 'updatePlan']);
    Route::delete('/plans/{plan}', [\App\Http\Controllers\Api\SuperAdmin\SubscriptionController::class, 'deletePlan']);
    Route::get('/subscriptions', [\App\Http\Controllers\Api\SuperAdmin\SubscriptionController::class, 'subscriptions']);
    Route::post('/subscriptions/assign', [\App\Http\Controllers\Api\SuperAdmin\SubscriptionController::class, 'assignPlan']);
    Route::get('/payments', [\App\Http\Controllers\Api\SuperAdmin\SubscriptionController::class, 'payments']);
    Route::post('/payments', [\App\Http\Controllers\Api\SuperAdmin\SubscriptionController::class, 'recordPayment']);
    Route::get('/revenue-stats', [\App\Http\Controllers\Api\SuperAdmin\SubscriptionController::class, 'revenueStats']);

    // Paramètres Système
    Route::get('/settings', [\App\Http\Controllers\Api\SuperAdmin\SystemSettingsController::class, 'index']);
    Route::get('/settings/{key}', [\App\Http\Controllers\Api\SuperAdmin\SystemSettingsController::class, 'show']);
    Route::put('/settings', [\App\Http\Controllers\Api\SuperAdmin\SystemSettingsController::class, 'update']);
    Route::post('/settings/init-defaults', [\App\Http\Controllers\Api\SuperAdmin\SystemSettingsController::class, 'initDefaults']);

    // Modules
    Route::get('/modules', [\App\Http\Controllers\Api\SuperAdmin\SystemSettingsController::class, 'modules']);
    Route::post('/modules', [\App\Http\Controllers\Api\SuperAdmin\SystemSettingsController::class, 'saveModule']);
    Route::post('/modules/init', [\App\Http\Controllers\Api\SuperAdmin\SystemSettingsController::class, 'initModules']);
    Route::get('/tenants/{tenant}/modules', [\App\Http\Controllers\Api\SuperAdmin\SystemSettingsController::class, 'tenantModules']);
    Route::post('/tenants/{tenant}/modules', [\App\Http\Controllers\Api\SuperAdmin\SystemSettingsController::class, 'toggleTenantModule']);

    // Logs Système
    Route::get('/logs', [\App\Http\Controllers\Api\SuperAdmin\LogsController::class, 'index']);
    Route::get('/logs/stats', [\App\Http\Controllers\Api\SuperAdmin\LogsController::class, 'stats']);
    Route::get('/logs/errors', [\App\Http\Controllers\Api\SuperAdmin\LogsController::class, 'errors']);
    Route::get('/logs/audit', [\App\Http\Controllers\Api\SuperAdmin\LogsController::class, 'auditLogs']);
    Route::get('/logs/export', [\App\Http\Controllers\Api\SuperAdmin\LogsController::class, 'export']);
    Route::post('/logs/purge', [\App\Http\Controllers\Api\SuperAdmin\LogsController::class, 'purge']);
    Route::get('/logs/{log}', [\App\Http\Controllers\Api\SuperAdmin\LogsController::class, 'show']);

    // Monitoring
    Route::get('/monitoring', [\App\Http\Controllers\Api\SuperAdmin\MonitoringController::class, 'index']);
    Route::get('/monitoring/alerts', [\App\Http\Controllers\Api\SuperAdmin\MonitoringController::class, 'alerts']);
    Route::get('/monitoring/history', [\App\Http\Controllers\Api\SuperAdmin\MonitoringController::class, 'history']);
    Route::get('/monitoring/slow-queries', [\App\Http\Controllers\Api\SuperAdmin\MonitoringController::class, 'slowQueries']);

    // Comptabilité Globale
    Route::get('/accounting/global-stats', [\App\Http\Controllers\Api\SuperAdmin\AccountingController::class, 'globalStats']);
    Route::get('/accounting/by-tenant', [\App\Http\Controllers\Api\SuperAdmin\AccountingController::class, 'byTenant']);
    Route::get('/accounting/export', [\App\Http\Controllers\Api\SuperAdmin\AccountingController::class, 'export']);
    
    // Rapports comptables agrégés (identiques au tenant mais multi-tenant)
    Route::get('/accounting/summary', [\App\Http\Controllers\Api\SuperAdmin\AccountingController::class, 'summary']);
    Route::get('/accounting/income-statement', [\App\Http\Controllers\Api\SuperAdmin\AccountingController::class, 'incomeStatement']);
    Route::get('/accounting/balance-sheet', [\App\Http\Controllers\Api\SuperAdmin\AccountingController::class, 'balanceSheet']);
    Route::get('/accounting/trial-balance', [\App\Http\Controllers\Api\SuperAdmin\AccountingController::class, 'trialBalance']);
    Route::get('/accounting/journal', [\App\Http\Controllers\Api\SuperAdmin\AccountingController::class, 'journal']);
    Route::get('/accounting/cash-report', [\App\Http\Controllers\Api\SuperAdmin\AccountingController::class, 'cashReport']);
    Route::get('/accounting/sig', [\App\Http\Controllers\Api\SuperAdmin\AccountingController::class, 'sig']);
    Route::get('/accounting/ratios', [\App\Http\Controllers\Api\SuperAdmin\AccountingController::class, 'ratios']);
});

// POS routes
Route::middleware('auth:sanctum')->prefix('pos')->group(function () {
    // Tables
    Route::get('/tables', [\App\Http\Controllers\Api\POSController::class, 'tables']);
    Route::post('/tables', [\App\Http\Controllers\Api\POSController::class, 'createTable']);
    Route::put('/tables/{id}', [\App\Http\Controllers\Api\POSController::class, 'updateTable']);
    Route::delete('/tables/{id}', [\App\Http\Controllers\Api\POSController::class, 'deleteTable']);
    
    // Kitchen
    Route::get('/kitchen/orders', [\App\Http\Controllers\Api\POSController::class, 'kitchenOrders']);
    Route::post('/orders/{orderId}/status', [\App\Http\Controllers\Api\POSController::class, 'updateOrderStatus']);
    Route::put('/orders/{orderId}/items/{itemId}', [\App\Http\Controllers\Api\POSController::class, 'updateOrderItem']);
    Route::delete('/orders/{orderId}/items/{itemId}', [\App\Http\Controllers\Api\POSController::class, 'deleteOrderItem']);
    Route::post('/orders/{orderId}/cancel', [\App\Http\Controllers\Api\POSController::class, 'cancelOrder']);

    // ========================================
    // WORKFLOW COMMANDES POS (Serveur/Gérant)
    // ========================================
    
    // Routes spécifiques AVANT les routes avec paramètres
    Route::get('/orders/pending/manager', [\App\Http\Controllers\Api\PosOrderController::class, 'pendingForManager']);
    Route::get('/orders/history/by-server', [\App\Http\Controllers\Api\PosOrderController::class, 'historyByServer']);
    Route::get('/orders/sales-stats', [\App\Http\Controllers\Api\PosOrderController::class, 'salesStats']);
    
    // Liste et création de commandes
    Route::get('/orders', [\App\Http\Controllers\Api\PosOrderController::class, 'index']);
    Route::post('/orders', [\App\Http\Controllers\Api\PosOrderController::class, 'store']);
    
    // Routes avec ID
    Route::get('/orders/{id}', [\App\Http\Controllers\Api\PosOrderController::class, 'show'])->where('id', '[0-9]+');
    Route::post('/orders/{id}/approve', [\App\Http\Controllers\Api\PosOrderController::class, 'approve'])->where('id', '[0-9]+');
    Route::post('/orders/{id}/prepare', [\App\Http\Controllers\Api\PosOrderController::class, 'startPreparing'])->where('id', '[0-9]+');
    Route::post('/orders/{id}/ready', [\App\Http\Controllers\Api\PosOrderController::class, 'markReady'])->where('id', '[0-9]+');
    Route::post('/orders/{id}/serve', [\App\Http\Controllers\Api\PosOrderController::class, 'serve'])->where('id', '[0-9]+');
    Route::post('/orders/{id}/payment', [\App\Http\Controllers\Api\PosOrderController::class, 'initiatePayment'])->where('id', '[0-9]+');
    Route::post('/orders/{id}/validate-payment', [\App\Http\Controllers\Api\PosOrderController::class, 'validatePayment'])->where('id', '[0-9]+');
});

// Caisse routes (legacy)
Route::middleware('auth:sanctum')->prefix('caisse')->group(function () {
    Route::get('/gros/movements', [\App\Http\Controllers\Api\CaisseController::class, 'grosMovements']);
    Route::get('/gros/summary', [\App\Http\Controllers\Api\CaisseController::class, 'grosSummary']);
    Route::get('/detail/movements', [\App\Http\Controllers\Api\CaisseController::class, 'detailMovements']);
    Route::get('/detail/summary', [\App\Http\Controllers\Api\CaisseController::class, 'detailSummary']);
    Route::get('/pos/movements', [\App\Http\Controllers\Api\CaisseController::class, 'posMovements']);
    Route::get('/pos/summary', [\App\Http\Controllers\Api\CaisseController::class, 'posSummary']);
});

// Cash Register routes (sessions, mouvements, remises)
Route::middleware('auth:sanctum')->prefix('cash-register')->group(function () {
    // Sessions
    Route::get('/current-session', [\App\Http\Controllers\Api\CashRegisterController::class, 'currentSession']);
    Route::post('/open-session', [\App\Http\Controllers\Api\CashRegisterController::class, 'openSession']);
    Route::post('/close-session', [\App\Http\Controllers\Api\CashRegisterController::class, 'closeSession']);
    Route::post('/sessions/{sessionId}/validate', [\App\Http\Controllers\Api\CashRegisterController::class, 'validateSession']);
    Route::get('/sessions', [\App\Http\Controllers\Api\CashRegisterController::class, 'sessions']);
    Route::get('/sessions/{sessionId}', [\App\Http\Controllers\Api\CashRegisterController::class, 'sessionDetail']);
    
    // Mouvements
    Route::post('/movements', [\App\Http\Controllers\Api\CashRegisterController::class, 'recordMovement']);
    Route::get('/movements', [\App\Http\Controllers\Api\CashRegisterController::class, 'movements']);
    
    // Remises de fonds
    Route::post('/remittances', [\App\Http\Controllers\Api\CashRegisterController::class, 'createRemittance']);
    Route::post('/remittances/{remittanceId}/receive', [\App\Http\Controllers\Api\CashRegisterController::class, 'receiveRemittance']);
    Route::post('/remittances/{remittanceId}/validate', [\App\Http\Controllers\Api\CashRegisterController::class, 'validateRemittance']);
    Route::get('/remittances', [\App\Http\Controllers\Api\CashRegisterController::class, 'remittances']);
    Route::get('/remittances/pending', [\App\Http\Controllers\Api\CashRegisterController::class, 'pendingRemittances']);
    
    // Dashboard gérant
    Route::get('/manager-dashboard', [\App\Http\Controllers\Api\CashRegisterController::class, 'managerDashboard']);
    Route::get('/journal', [\App\Http\Controllers\Api\CashRegisterController::class, 'cashJournal']);
});

// Subscription Payment routes (tenant pays for subscription)
Route::middleware('auth:sanctum')->prefix('subscription-payment')->group(function () {
    Route::post('/initialize', [\App\Http\Controllers\Api\SubscriptionPaymentController::class, 'initializePayment']);
    Route::post('/verify', [\App\Http\Controllers\Api\SubscriptionPaymentController::class, 'verifyPayment']);
});

// Comptabilité avancée routes
Route::middleware('auth:sanctum')->prefix('accounting')->group(function () {
    Route::get('/entries', [\App\Http\Controllers\Api\ComptabiliteController::class, 'entries']);
    Route::get('/grand-livre/{accountId}', [\App\Http\Controllers\Api\ComptabiliteController::class, 'grandLivre']);
    Route::get('/balance', [\App\Http\Controllers\Api\ComptabiliteController::class, 'balance']);
    
    // Immobilisations (SYSCOHADA)
    Route::get('/immobilisations', [\App\Http\Controllers\Api\ImmobilisationController::class, 'index']);
    Route::post('/immobilisations', [\App\Http\Controllers\Api\ImmobilisationController::class, 'store']);
    Route::put('/immobilisations/{id}', [\App\Http\Controllers\Api\ImmobilisationController::class, 'update']);
    Route::delete('/immobilisations/{id}', [\App\Http\Controllers\Api\ImmobilisationController::class, 'destroy']);
    Route::get('/immobilisations/{id}/tableau', [\App\Http\Controllers\Api\ImmobilisationController::class, 'tableauAmortissement']);
    Route::post('/immobilisations/calculate', [\App\Http\Controllers\Api\ImmobilisationController::class, 'calculateAmortissement']);
    
    // Rapprochement bancaire
    Route::get('/bank-statements', [\App\Http\Controllers\Api\BankReconciliationController::class, 'statements']);
    Route::post('/bank-statements', [\App\Http\Controllers\Api\BankReconciliationController::class, 'storeStatement']);
    Route::post('/entries/{id}/toggle-rapproche', [\App\Http\Controllers\Api\BankReconciliationController::class, 'toggleRapproche']);
    Route::get('/rapprochement', [\App\Http\Controllers\Api\BankReconciliationController::class, 'etatRapprochement']);
});

// Export FEC (Fichier des Écritures Comptables)
Route::middleware('auth:sanctum')->prefix('fec')->group(function () {
    Route::get('/export', [\App\Http\Controllers\Api\FECExportController::class, 'export']);
    Route::get('/preview', [\App\Http\Controllers\Api\FECExportController::class, 'preview']);
});

// Role-specific Dashboard routes
Route::middleware('auth:sanctum')->prefix('dashboard')->group(function () {
    Route::get('/stats', [\App\Http\Controllers\Api\RoleDashboardController::class, 'globalStats']);
    Route::get('/cashier/stats', [\App\Http\Controllers\Api\RoleDashboardController::class, 'cashierStats']);
    Route::get('/server/stats', [\App\Http\Controllers\Api\RoleDashboardController::class, 'serverStats']);
    Route::get('/warehouse-gros/tasks', [\App\Http\Controllers\Api\RoleDashboardController::class, 'warehouseGrosTasks']);
    Route::get('/warehouse-detail/tasks', [\App\Http\Controllers\Api\RoleDashboardController::class, 'warehouseDetailTasks']);
    Route::get('/accountant/stats', [\App\Http\Controllers\Api\RoleDashboardController::class, 'accountantStats']);
    Route::get('/manager/stats', [\App\Http\Controllers\Api\RoleDashboardController::class, 'managerStats']);
    Route::get('/manager/cash-register', [\App\Http\Controllers\Api\RoleDashboardController::class, 'cashRegisterManagerDashboard']);
});

// Automation routes (Owner/Manager)
Route::middleware(['auth:sanctum', 'role:owner,manager'])->prefix('automation')->group(function () {
    Route::post('/run-all', [\App\Http\Controllers\Api\AutomationController::class, 'runAll']);
    Route::get('/low-stock', [\App\Http\Controllers\Api\AutomationController::class, 'checkLowStock']);
    Route::post('/approve-urgent', [\App\Http\Controllers\Api\AutomationController::class, 'approveUrgentRequests']);
    Route::post('/create-transfers', [\App\Http\Controllers\Api\AutomationController::class, 'createTransfers']);
    Route::post('/generate-entries', [\App\Http\Controllers\Api\AutomationController::class, 'generateAccountingEntries']);
    Route::get('/daily-cash', [\App\Http\Controllers\Api\AutomationController::class, 'dailyCashSummary']);
    Route::post('/close-sessions', [\App\Http\Controllers\Api\AutomationController::class, 'closeStaleSessions']);
});

// Public PSP webhook routes (tenant sales)
Route::post('/payments/fedapay/callback', [PaymentController::class, 'fedapayCallback']);
Route::post('/payments/kkiapay/callback', [PaymentController::class, 'kkiapayCallback']);
Route::post('/payments/momo/callback', [PaymentController::class, 'momoCallback']);

// Bank transfer management (authenticated)
Route::middleware('auth:sanctum')->prefix('payments/bank')->group(function () {
    Route::get('/pending', [PaymentController::class, 'pendingBankTransfers']);
    Route::post('/confirm', [PaymentController::class, 'confirmBankTransfer']);
});

// Public PSP webhook routes (subscription payments - SuperAdmin)
Route::post('/webhooks/subscription/fedapay', [\App\Http\Controllers\Api\SubscriptionPaymentController::class, 'fedapayWebhook']);
Route::post('/webhooks/subscription/kkiapay', [\App\Http\Controllers\Api\SubscriptionPaymentController::class, 'kkiapayWebhook']);
Route::post('/webhooks/subscription/momo', [\App\Http\Controllers\Api\SubscriptionPaymentController::class, 'momoWebhook']);

// Portail Fournisseur
Route::middleware('auth:sanctum')->prefix('supplier-portal')->group(function () {
    // Actions fournisseur (role: supplier)
    Route::get('/dashboard', [\App\Http\Controllers\Api\SupplierPortalController::class, 'dashboard']);
    Route::get('/orders', [\App\Http\Controllers\Api\SupplierPortalController::class, 'orders']);
    Route::get('/history', [\App\Http\Controllers\Api\SupplierPortalController::class, 'history']);
    Route::get('/orders/{id}', [\App\Http\Controllers\Api\SupplierPortalController::class, 'orderDetail']);
    Route::post('/orders/{id}/confirm', [\App\Http\Controllers\Api\SupplierPortalController::class, 'confirmOrder']);
    Route::post('/orders/{id}/ship', [\App\Http\Controllers\Api\SupplierPortalController::class, 'markShipped']);
    Route::post('/orders/{id}/deliver', [\App\Http\Controllers\Api\SupplierPortalController::class, 'deliverOrder']);
    Route::post('/orders/{id}/validate-payment', [\App\Http\Controllers\Api\SupplierPortalController::class, 'validatePayment']);
    Route::post('/orders/{id}/reject', [\App\Http\Controllers\Api\SupplierPortalController::class, 'rejectOrder']);
});

// Gestion accès portail fournisseur (Owner/Manager/Admin)
Route::middleware(['auth:sanctum', 'role:owner,manager,admin,tenant'])->prefix('suppliers')->group(function () {
    Route::post('/{id}/enable-portal', [\App\Http\Controllers\Api\SupplierPortalController::class, 'enablePortalAccess']);
    Route::post('/{id}/disable-portal', [\App\Http\Controllers\Api\SupplierPortalController::class, 'disablePortalAccess']);
});

// Inventaire enrichi et génération automatique de commandes
Route::middleware('auth:sanctum')->prefix('inventory')->group(function () {
    Route::get('/enriched-data', [\App\Http\Controllers\Api\InventoryController::class, 'getEnrichedInventoryData']);
    Route::get('/export-enriched', [\App\Http\Controllers\Api\InventoryController::class, 'exportEnrichedInventory']);
    Route::post('/save-physical-counts', [\App\Http\Controllers\Api\InventoryController::class, 'savePhysicalCounts']);
    Route::post('/generate-purchase-order', [\App\Http\Controllers\Api\InventoryController::class, 'generatePurchaseOrder']);
    Route::post('/generate-stock-request', [\App\Http\Controllers\Api\InventoryController::class, 'generateStockRequest']);
});

// Info tenant (mode A/B et Option A/B)
Route::middleware('auth:sanctum')->get('/tenant/mode', function () {
    $tenant = auth()->user()->tenant;
    return response()->json([
        'business_type' => $tenant->business_type,
        'is_mode_a' => $tenant->isModeA(),
        'is_mode_b' => $tenant->isModeB(),
        'pos_option' => $tenant->pos_option ?? 'A',
    ]);
});

// ========================================
// OPTION B: Stock délégué aux serveurs
// ========================================
Route::middleware('auth:sanctum')->prefix('server-stock')->group(function () {
    // Liste des stocks délégués
    Route::get('/', [\App\Http\Controllers\Api\ServerStockController::class, 'index']);
    
    // Mon stock (serveur)
    Route::get('/my-stock', [\App\Http\Controllers\Api\ServerStockController::class, 'myStock']);
    
    // Déléguer du stock (gérant)
    Route::post('/delegate', [\App\Http\Controllers\Api\ServerStockController::class, 'delegate']);
    
    // Enregistrer une vente (serveur)
    Route::post('/sale', [\App\Http\Controllers\Api\ServerStockController::class, 'recordSale']);
    
    // Déclarer une perte (serveur)
    Route::post('/loss', [\App\Http\Controllers\Api\ServerStockController::class, 'declareLoss']);
    
    // Mouvements de stock serveur
    Route::get('/movements', [\App\Http\Controllers\Api\ServerStockController::class, 'movements']);
    
    // Statistiques (gérant)
    Route::get('/statistics', [\App\Http\Controllers\Api\ServerStockController::class, 'statistics']);
    
    // Réconciliation (point de caisse serveur)
    Route::post('/reconciliation/start', [\App\Http\Controllers\Api\ServerStockController::class, 'startReconciliation']);
    Route::post('/reconciliation/{id}/submit', [\App\Http\Controllers\Api\ServerStockController::class, 'submitReconciliation']);
    Route::get('/reconciliation/pending', [\App\Http\Controllers\Api\ServerStockController::class, 'pendingReconciliations']);
    Route::post('/reconciliation/{id}/validate', [\App\Http\Controllers\Api\ServerStockController::class, 'validateReconciliation']);
    Route::post('/reconciliation/{id}/dispute', [\App\Http\Controllers\Api\ServerStockController::class, 'disputeReconciliation']);
});

