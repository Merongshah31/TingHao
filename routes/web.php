<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpiryController;
use App\Http\Controllers\IngredientController;
use App\Http\Controllers\LowStockController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\StockMemoryDemoController;
use App\Http\Controllers\StockMovementController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SystemController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home', ['title' => 'Ting Hao | Baking Ingredient Supplier']);
})->name('home');

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

    Route::get('/expiry', [ExpiryController::class, 'index'])->name('expiry.index');
    Route::post('/expiry/{ingredient}/remove', [ExpiryController::class, 'removeExpired'])
        ->middleware('role:admin')
        ->name('expiry.remove');

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
