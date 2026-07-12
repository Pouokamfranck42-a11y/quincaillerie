<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CashFlowReportController;
use App\Http\Controllers\CashRegisterSessionController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryCountController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductDescriptionController;
use App\Http\Controllers\ProductFamilyController;
use App\Http\Controllers\ProductRecognitionController;
use App\Http\Controllers\PurchaseInvoiceImportController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\StockMovementController;
use App\Http\Controllers\StockReportController;
use App\Http\Controllers\StockTransferController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\TrashController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WarehouseController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.mark-read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.mark-all-read');

    Route::middleware('role:admin|magasinier|caissier')->group(function () {
        Route::get('/assistant', [ChatbotController::class, 'index'])->name('chatbot.index');
    });

    Route::middleware('role:admin|caissier')->group(function () {
        Route::get('/pos', [CashRegisterSessionController::class, 'terminal'])->name('pos.index');
        Route::post('/cash-sessions/open', [CashRegisterSessionController::class, 'open'])->name('cash-sessions.open');
        Route::post('/cash-sessions/close', [CashRegisterSessionController::class, 'close'])->name('cash-sessions.close');

        Route::resource('customers', CustomerController::class)->except('show');
        Route::get('/customers/{customer}/statement', [CustomerController::class, 'statement'])->name('customers.statement');
        Route::post('/customers/{customer}/sales/{sale}/payment', [CustomerController::class, 'recordPayment'])->name('customers.record-payment');

        Route::resource('quotes', QuoteController::class)->only(['index', 'create', 'store', 'show']);
        Route::post('/quotes/{quote}/convert', [QuoteController::class, 'convert'])->name('quotes.convert');

        Route::get('/sales', [SaleController::class, 'index'])->name('sales.index');
        Route::get('/sales/{sale}', [SaleController::class, 'show'])->name('sales.show');
        Route::get('/sales/{sale}/print', [SaleController::class, 'print'])->name('sales.print');
        Route::post('/sales/{sale}/lines/{sale_line}/return', [SaleController::class, 'returnLine'])->name('sales.return-line');
    });

    Route::middleware('role:admin|magasinier')->group(function () {
        Route::resource('categories', CategoryController::class)->except('show');
        Route::resource('suppliers', SupplierController::class)->except('show');
        Route::resource('product-families', ProductFamilyController::class)->except('show');
        Route::resource('products', ProductController::class);
        Route::get('/products/{product}/label', [ProductController::class, 'label'])->name('products.label');
        Route::post('/products/recognize-photo', [ProductRecognitionController::class, 'recognize'])->name('products.recognize-photo');
        Route::post('/products/generate-description', [ProductDescriptionController::class, 'generate'])->name('products.generate-description');

        Route::get('/stock-movements', [StockMovementController::class, 'index'])->name('stock-movements.index');
        Route::get('/stock-movements/create', [StockMovementController::class, 'create'])->name('stock-movements.create');
        Route::post('/stock-movements', [StockMovementController::class, 'store'])->name('stock-movements.store');

        Route::get('/purchase-orders/suggestions', [PurchaseOrderController::class, 'suggestions'])->name('purchase-orders.suggestions');
        Route::post('/purchase-orders/suggestions', [PurchaseOrderController::class, 'createSuggestions'])->name('purchase-orders.create-suggestions');
        Route::get('/purchase-orders/import-invoice', [PurchaseInvoiceImportController::class, 'create'])->name('purchase-orders.import-invoice');
        Route::post('/purchase-orders/import-invoice/analyze', [PurchaseInvoiceImportController::class, 'analyze'])->name('purchase-orders.import-invoice.analyze');
        Route::post('/purchase-orders/import-invoice', [PurchaseInvoiceImportController::class, 'store'])->name('purchase-orders.import-invoice.store');
        Route::resource('purchase-orders', PurchaseOrderController::class)->only(['index', 'create', 'store', 'show']);
        Route::get('/purchase-orders/{purchase_order}/edit', [PurchaseOrderController::class, 'edit'])->name('purchase-orders.edit');
        Route::put('/purchase-orders/{purchase_order}', [PurchaseOrderController::class, 'update'])->name('purchase-orders.update');
        Route::post('/purchase-orders/{purchase_order}/place', [PurchaseOrderController::class, 'place'])->name('purchase-orders.place');
        Route::post('/purchase-orders/{purchase_order}/receive', [PurchaseOrderController::class, 'receive'])->name('purchase-orders.receive');
        Route::post('/purchase-orders/{purchase_order}/lines/{line}/return', [PurchaseOrderController::class, 'returnLine'])->name('purchase-orders.return-line');

        Route::resource('stock-transfers', StockTransferController::class)->only(['index', 'create', 'store', 'show']);
        Route::post('/stock-transfers/{stock_transfer}/execute', [StockTransferController::class, 'execute'])->name('stock-transfers.execute');

        Route::resource('inventory-counts', InventoryCountController::class)->only(['index', 'create', 'store', 'show']);
        Route::post('/inventory-counts/{inventory_count}/lines', [InventoryCountController::class, 'updateLines'])->name('inventory-counts.update-lines');
        Route::post('/inventory-counts/{inventory_count}/complete', [InventoryCountController::class, 'complete'])->name('inventory-counts.complete');
    });

    Route::middleware('role:admin')->group(function () {
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/stock', [StockReportController::class, 'index'])->name('reports.stock');
        Route::get('/reports/cash-flow', [CashFlowReportController::class, 'index'])->name('reports.cash-flow');
        Route::resource('users', UserController::class)->except('show');
        Route::resource('warehouses', WarehouseController::class)->except(['show', 'destroy']);
        Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');

        Route::get('/trash', [TrashController::class, 'index'])->name('trash.index');
        Route::post('/trash/{type}/{id}/restore', [TrashController::class, 'restore'])->name('trash.restore');
        Route::delete('/trash/{type}/{id}', [TrashController::class, 'forceDelete'])->name('trash.force-delete');
    });
});
