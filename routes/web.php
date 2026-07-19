<?php

use App\Http\Controllers\AgentController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpiryController;
use App\Http\Controllers\ExpiryLossRecommendationController;
use App\Http\Controllers\HelpCenterController;
use App\Http\Controllers\IngredientController;
use App\Http\Controllers\LowStockController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\PurchaseOrderDemoController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\StockMemoryDemoController;
use App\Http\Controllers\StockMovementController;
use App\Http\Controllers\StockPlannerController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SupplierEmailDraftController;
use App\Http\Controllers\SupplierReturnController;
use App\Http\Controllers\SystemController;
use App\Models\AgentRun;
use App\Models\PurchaseOrder;
use App\Services\Agent\PhaseOneCapabilityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home', ['title' => 'Ting Hao | Baking Ingredient Supplier']);
})->name('home');

Route::get('/health', function () {
    try {
        DB::select('select 1');
        $database = 'connected';
    } catch (Throwable) {
        $database = 'error';
    }

    return response()->json([
        'status' => 'ok',
        'service' => 'TingHao Agent',
        'architecture' => 'Laravel full-stack app',
        'track' => 'Track 4 Autopilot Agent',
        'qwen_configured' => filled(config('qwen.api_key')),
        'mock_mode' => filter_var(config('qwen.mock_mode', true), FILTER_VALIDATE_BOOLEAN) || blank(config('qwen.api_key')),
        'database' => $database,
    ]);
})
    ->name('health');

Route::get('/agent/proof', function () {
    return response()->json([
        'service' => 'TingHao Agent',
        'cloud_backend_target' => 'Alibaba Cloud ECS',
        'qwen_server_side' => true,
        'api_key_exposed' => false,
        'agent_features' => [
            'Smart Procurement Inbox',
            'Autonomous Restock Engine',
            'Supplier Email Draft',
            'Expiry Loss Prevention',
            'Reasoning Activity',
            'Human-in-the-Loop Guardrails',
        ],
    ]);
})->name('agent.proof');

Route::get('/demo', function (PhaseOneCapabilityService $capabilityService) {
    return view('demo', [
        'title' => 'TingHao Agent | Demo Guide',
        'recentAgentRuns' => AgentRun::query()->latest()->limit(5)->get(['id', 'input_text', 'status', 'created_at']),
        'pendingPurchaseOrders' => PurchaseOrder::query()
            ->where('status', PurchaseOrder::STATUS_PENDING_APPROVAL)
            ->latest()
            ->limit(5)
            ->get(['id', 'po_number', 'status', 'created_at']),
        'phaseOneCapabilities' => $capabilityService->map(),
    ]);
})->name('demo');

Route::get('/language/{locale}', function (string $locale) {
    if (! in_array($locale, ['en', 'zh_CN'], true)) {
        $locale = 'en';
    }

    session(['locale' => $locale]);
    app()->setLocale($locale);

    return back();
})->name('language.switch');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'redirect'])->name('dashboard');
    Route::get('/admin/dashboard', [DashboardController::class, 'admin'])
        ->middleware('role:admin')
        ->name('admin.dashboard');
    Route::get('/staff/dashboard', [DashboardController::class, 'staff'])
        ->middleware('role:staff')
        ->name('staff.dashboard');

    Route::get('/agent', [AgentController::class, 'index'])
        ->middleware('role:admin,staff')
        ->name('agent.index');
    Route::post('/agent/run', [AgentController::class, 'run'])
        ->middleware('role:admin,staff')
        ->name('agent.run');
    Route::get('/agent/runs/{agentRun}', [AgentController::class, 'show'])
        ->middleware('role:admin,staff')
        ->name('agent.runs.show');
    Route::get('/agent/expiry-loss', [ExpiryLossRecommendationController::class, 'agentExpiryLossPage'])
        ->middleware('role:admin,staff')
        ->name('agent.expiry-loss');
    Route::post('/agent/expiry-loss/scan', [ExpiryLossRecommendationController::class, 'scan'])
        ->middleware('role:admin')
        ->name('agent.expiry-loss.scan');

    Route::get('/inventory', [IngredientController::class, 'index'])->name('inventory.index');
    Route::get('/inventory/create', [IngredientController::class, 'create'])
        ->middleware('role:admin,staff')
        ->name('inventory.create');
    Route::post('/inventory', [IngredientController::class, 'store'])
        ->middleware('role:admin,staff')
        ->name('inventory.store');
    Route::get('/inventory/{ingredient}', [IngredientController::class, 'show'])->name('inventory.show');
    Route::get('/inventory/{ingredient}/edit', [IngredientController::class, 'edit'])
        ->middleware('role:admin')
        ->name('inventory.edit');
    Route::put('/inventory/{ingredient}', [IngredientController::class, 'update'])
        ->middleware('role:admin')
        ->name('inventory.update');
    Route::delete('/inventory/{ingredient}', [IngredientController::class, 'destroy'])
        ->middleware('role:admin')
        ->name('inventory.destroy');

    Route::get('/stock/history', [StockMovementController::class, 'index'])->name('stock.index');
    Route::get('/stock-memory-demo', [StockMemoryDemoController::class, 'index'])
        ->middleware('role:admin,staff')
        ->name('stock-memory.demo');
    Route::get('/calendar-demo', [StockMemoryDemoController::class, 'index'])
        ->middleware('role:admin,staff')
        ->name('stock-planner.calendar-redirect');
    Route::get('/stock-planner', [StockPlannerController::class, 'index'])
        ->middleware('role:admin,staff')
        ->name('stock-planner.index');
    Route::get('/stock-planner/ingredient/{ingredient}/prediction', [StockPlannerController::class, 'show'])
        ->middleware('role:admin,staff')
        ->name('stock-planner.prediction');
    Route::post('/stock-planner/ingredient/{ingredient}/refresh-prediction', [StockPlannerController::class, 'refresh'])
        ->middleware('role:admin,staff')
        ->name('stock-planner.refresh-prediction');
    Route::post('/stock-planner/ingredient/{ingredient}/explain', [StockPlannerController::class, 'explain'])
        ->middleware('role:admin,staff')
        ->name('stock-planner.explain');
    Route::post('/stock-planner/ingredient/{ingredient}/plan-restock', [StockPlannerController::class, 'planRestock'])
        ->middleware('role:admin,staff')
        ->name('stock-planner.plan-restock');
    Route::get('/help-center', [HelpCenterController::class, 'index'])
        ->middleware('role:admin,staff')
        ->name('help-center.index');
    Route::get('/inventory/{ingredient}/stock/{type}', [StockMovementController::class, 'create'])
        ->middleware('role:admin,staff')
        ->name('stock.create');
    Route::post('/inventory/{ingredient}/stock/{type}', [StockMovementController::class, 'store'])
        ->middleware('role:admin,staff')
        ->name('stock.store');

    Route::get('/alerts/low-stock', [LowStockController::class, 'index'])
        ->middleware('role:admin,staff')
        ->name('alerts.low-stock');
    Route::post('/alerts/low-stock/{ingredient}/restock', [LowStockController::class, 'requestRestock'])
        ->middleware('role:admin,staff')
        ->name('alerts.restock.request');
    Route::post('/alerts/low-stock/{ingredient}/agent-plan', [LowStockController::class, 'planRestockWithAgent'])
        ->middleware('role:admin,staff')
        ->name('alerts.restock.agent-plan');
    Route::patch('/alerts/restock/{restockRequest}', [LowStockController::class, 'updateRestock'])
        ->middleware('role:admin')
        ->name('alerts.restock.update');

    Route::get('/purchase-orders', [PurchaseOrderController::class, 'index'])
        ->middleware('role:admin,staff')
        ->name('purchase-orders.index');
    Route::get('/purchase-orders/create/from-low-stock', [PurchaseOrderController::class, 'createFromLowStock'])
        ->middleware('role:admin')
        ->name('purchase-orders.create-from-low-stock');
    Route::get('/purchase-orders/create', [PurchaseOrderController::class, 'create'])
        ->middleware('role:admin')
        ->name('purchase-orders.create');
    Route::post('/purchase-orders', [PurchaseOrderController::class, 'store'])
        ->middleware('role:admin')
        ->name('purchase-orders.store');
    Route::get('/purchase-orders/suggestions', [PurchaseOrderController::class, 'suggestions'])
        ->middleware('role:admin')
        ->name('purchase-orders.suggestions');
    Route::get('/purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'show'])
        ->middleware('role:admin,staff')
        ->name('purchase-orders.show');
    Route::get('/purchase-orders/{purchaseOrder}/edit', [PurchaseOrderController::class, 'edit'])
        ->middleware('role:admin')
        ->name('purchase-orders.edit');
    Route::put('/purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'update'])
        ->middleware('role:admin')
        ->name('purchase-orders.update');
    Route::delete('/purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'destroy'])
        ->middleware('role:admin')
        ->name('purchase-orders.destroy');
    Route::post('/purchase-orders/{purchaseOrder}/send-email', [PurchaseOrderController::class, 'sendEmail'])
        ->middleware('role:admin')
        ->name('purchase-orders.send-email');
    Route::post('/purchase-orders/{purchaseOrder}/generate-email-draft', [SupplierEmailDraftController::class, 'generate'])
        ->middleware('role:admin')
        ->name('purchase-orders.generate-email-draft');
    Route::post('/purchase-orders/{purchaseOrder}/email-draft', [SupplierEmailDraftController::class, 'generate'])
        ->middleware('role:admin')
        ->name('purchase-orders.email-draft');
    Route::post('/purchase-orders/{purchaseOrder}/approve', [PurchaseOrderController::class, 'approve'])
        ->middleware('role:admin')
        ->name('purchase-orders.approve');
    Route::post('/purchase-orders/{purchaseOrder}/reject', [PurchaseOrderController::class, 'reject'])
        ->middleware('role:admin')
        ->name('purchase-orders.reject');
    Route::post('/purchase-orders/{purchaseOrder}/confirm', [PurchaseOrderController::class, 'confirm'])
        ->middleware('role:admin')
        ->name('purchase-orders.confirm');
    Route::get('/purchase-orders/{purchaseOrder}/receive', [PurchaseOrderController::class, 'receiveForm'])
        ->middleware('role:admin,staff')
        ->name('purchase-orders.receive-form');
    Route::post('/purchase-orders/{purchaseOrder}/receive', [PurchaseOrderController::class, 'receive'])
        ->middleware('role:admin,staff')
        ->name('purchase-orders.receive');
    Route::post('/purchase-orders/{purchaseOrder}/close', [PurchaseOrderController::class, 'close'])
        ->middleware('role:admin')
        ->name('purchase-orders.close');

    Route::get('/supplier-returns', [SupplierReturnController::class, 'index'])
        ->middleware('role:admin,staff')
        ->name('supplier-returns.index');
    Route::get('/supplier-returns/{supplierReturn}', [SupplierReturnController::class, 'show'])
        ->middleware('role:admin,staff')
        ->name('supplier-returns.show');
    Route::patch('/supplier-returns/{supplierReturn}', [SupplierReturnController::class, 'update'])
        ->middleware('role:admin')
        ->name('supplier-returns.update');

    Route::get('/supplier-email-drafts/{supplierEmailDraft}', [SupplierEmailDraftController::class, 'show'])
        ->middleware('role:admin,staff')
        ->name('supplier-email-drafts.show');
    Route::put('/supplier-email-drafts/{supplierEmailDraft}', [SupplierEmailDraftController::class, 'update'])
        ->middleware('role:admin')
        ->name('supplier-email-drafts.update');
    Route::post('/supplier-email-drafts/{supplierEmailDraft}/approve', [SupplierEmailDraftController::class, 'approve'])
        ->middleware('role:admin')
        ->name('supplier-email-drafts.approve');
    Route::post('/supplier-email-drafts/{supplierEmailDraft}/mark-sent', [SupplierEmailDraftController::class, 'markSent'])
        ->middleware('role:admin')
        ->name('supplier-email-drafts.mark-sent');
    Route::post('/supplier-email-drafts/{supplierEmailDraft}/send-via-gmail', [SupplierEmailDraftController::class, 'sendViaGmail'])
        ->middleware('role:admin')
        ->name('supplier-email-drafts.send-via-gmail');
    Route::post('/supplier-email-drafts/{supplierEmailDraft}/send-resend', [SupplierEmailDraftController::class, 'sendResend'])
        ->middleware('role:admin')
        ->name('supplier-email-drafts.send-resend');
    Route::post('/supplier-email-drafts/{supplierEmailDraft}/regenerate', [SupplierEmailDraftController::class, 'regenerate'])
        ->middleware('role:admin')
        ->name('supplier-email-drafts.regenerate');

    Route::get('/purchase-order-demo', [PurchaseOrderDemoController::class, 'index'])
        ->middleware('role:admin,staff')
        ->name('po-demo.index');
    Route::get('/purchase-order-demo/create', [PurchaseOrderDemoController::class, 'create'])
        ->middleware('role:admin')
        ->name('po-demo.create');
    Route::post('/purchase-order-demo', [PurchaseOrderDemoController::class, 'store'])
        ->middleware('role:admin')
        ->name('po-demo.store');
    Route::get('/purchase-order-demo/{po}', [PurchaseOrderDemoController::class, 'show'])
        ->middleware('role:admin,staff')
        ->name('po-demo.show');
    Route::post('/purchase-order-demo/{po}/send-email-demo', [PurchaseOrderDemoController::class, 'sendEmailDemo'])
        ->middleware('role:admin')
        ->name('po-demo.send-email');
    Route::post('/purchase-order-demo/{po}/confirm-demo', [PurchaseOrderDemoController::class, 'confirmDemo'])
        ->middleware('role:admin')
        ->name('po-demo.confirm');
    Route::post('/purchase-order-demo/{po}/receive-demo', [PurchaseOrderDemoController::class, 'receiveDemo'])
        ->middleware('role:admin,staff')
        ->name('po-demo.receive');
    Route::post('/purchase-order-demo/{po}/close-demo', [PurchaseOrderDemoController::class, 'closeDemo'])
        ->middleware('role:admin')
        ->name('po-demo.close');

    Route::get('/expiry', [ExpiryController::class, 'index'])->name('expiry.index');
    Route::post('/expiry/{ingredient}/remove', [ExpiryController::class, 'removeExpired'])
        ->middleware('role:admin')
        ->name('expiry.remove');

    Route::get('/expiry-loss-recommendations/{expiryLossRecommendation}', [ExpiryLossRecommendationController::class, 'show'])
        ->middleware('role:admin,staff')
        ->name('expiry-loss-recommendations.show');
    Route::post('/expiry-loss-recommendations/{expiryLossRecommendation}/review', [ExpiryLossRecommendationController::class, 'review'])
        ->middleware('role:admin')
        ->name('expiry-loss-recommendations.review');
    Route::post('/expiry-loss-recommendations/{expiryLossRecommendation}/dismiss', [ExpiryLossRecommendationController::class, 'dismiss'])
        ->middleware('role:admin')
        ->name('expiry-loss-recommendations.dismiss');
    Route::post('/expiry-loss-recommendations/{expiryLossRecommendation}/complete', [ExpiryLossRecommendationController::class, 'complete'])
        ->middleware('role:admin')
        ->name('expiry-loss-recommendations.complete');

    Route::get('/suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
    Route::get('/suppliers/create', [SupplierController::class, 'create'])
        ->middleware('role:admin')
        ->name('suppliers.create');
    Route::post('/suppliers', [SupplierController::class, 'store'])
        ->middleware('role:admin')
        ->name('suppliers.store');
    Route::get('/suppliers/{supplier}', [SupplierController::class, 'show'])->name('suppliers.show');
    Route::get('/suppliers/{supplier}/edit', [SupplierController::class, 'edit'])
        ->middleware('role:admin')
        ->name('suppliers.edit');
    Route::put('/suppliers/{supplier}', [SupplierController::class, 'update'])
        ->middleware('role:admin')
        ->name('suppliers.update');

    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/inventory', [ReportController::class, 'inventory'])->name('reports.inventory');
    Route::get('/reports/stock', [ReportController::class, 'stock'])->name('reports.stock');
    Route::get('/reports/low-stock', [ReportController::class, 'lowStock'])->name('reports.low-stock');
    Route::get('/reports/expiry', [ReportController::class, 'expiry'])->name('reports.expiry');
    Route::get('/reports/generated-summary', [ReportController::class, 'generatedSummary'])
        ->middleware('role:admin')
        ->name('reports.generated-summary');
    Route::get('/reports/generated-summary/pdf', [ReportController::class, 'downloadGeneratedSummaryPdf'])
        ->middleware('role:admin')
        ->name('reports.generated-summary.pdf');

    Route::middleware('role:admin')->group(function () {
        Route::get('/system/settings', [SystemController::class, 'settings'])->name('system.settings');
        Route::put('/system/settings', [SystemController::class, 'updateSettings'])->name('system.settings.update');
        Route::get('/system/backups', [SystemController::class, 'backups'])->name('system.backups');
        Route::post('/system/backups', [SystemController::class, 'createBackup'])->name('system.backups.create');
        Route::post('/system/backups/cleanup', [SystemController::class, 'cleanupBackups'])->name('system.backups.cleanup');
        Route::delete('/system/backups/{backupRecord}', [SystemController::class, 'destroyBackup'])->name('system.backups.destroy');
    });

    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
});
