<?php

use App\Http\Controllers\AccountingExportController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\ErrorLogController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CashFlowReportController;
use App\Http\Controllers\CustomerCreditReportController;
use App\Http\Controllers\CashRegisterSessionController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DormantStockController;
use App\Http\Controllers\InventoryCountController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OnlineOrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductDescriptionController;
use App\Http\Controllers\ProductFamilyController;
use App\Http\Controllers\ProductImportController;
use App\Http\Controllers\ProductRecognitionController;
use App\Http\Controllers\Payment\WebhookController as PaymentWebhookController;
use App\Http\Controllers\PurchaseInvoiceImportController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\ServiceTicketController;
use App\Http\Controllers\Shop\AccountController as ShopAccountController;
use App\Http\Controllers\Shop\CartController as ShopCartController;
use App\Http\Controllers\Shop\CatalogController as ShopCatalogController;
use App\Http\Controllers\Shop\CheckoutController as ShopCheckoutController;
use App\Http\Controllers\Shop\ForgotPasswordController as ShopForgotPasswordController;
use App\Http\Controllers\Shop\LoginController as ShopLoginController;
use App\Http\Controllers\Shop\ResetPasswordController as ShopResetPasswordController;
use App\Http\Controllers\Shop\NotificationController as ShopNotificationController;
use App\Http\Controllers\Shop\PaymentSimulationController as ShopPaymentSimulationController;
use App\Http\Controllers\Shop\RegisterController as ShopRegisterController;
use App\Http\Controllers\SmartReorderController;
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

    Route::get('/assistant', [ChatbotController::class, 'index'])->name('chatbot.index')->middleware('permission:ia.chatbot');

    // --- Caisse & ventes comptoir ---
    Route::get('/pos', [CashRegisterSessionController::class, 'terminal'])->name('pos.index')->middleware('permission:caisse.encaisser');
    Route::post('/cash-sessions/open', [CashRegisterSessionController::class, 'open'])->name('cash-sessions.open')->middleware('permission:caisse.cloturer');
    Route::post('/cash-sessions/close', [CashRegisterSessionController::class, 'close'])->name('cash-sessions.close')->middleware('permission:caisse.cloturer');

    Route::resource('customers', CustomerController::class)->except('show')
        ->middlewareFor(['index'], 'permission:clients.voir')
        ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], 'permission:clients.gerer');
    Route::get('/customers/{customer}/statement', [CustomerController::class, 'statement'])->name('customers.statement')->middleware('permission:clients.voir');
    Route::post('/customers/{customer}/sales/{sale}/payment', [CustomerController::class, 'recordPayment'])->name('customers.record-payment')->middleware('permission:clients.gerer');
    Route::post('/customers/{customer}/reset-password', [CustomerController::class, 'sendPasswordReset'])->name('customers.send-password-reset')->middleware('permission:clients.gerer');

    Route::resource('quotes', QuoteController::class)->only(['index', 'create', 'store', 'show'])->middleware('permission:ventes.creer');
    Route::post('/quotes/{quote}/convert', [QuoteController::class, 'convert'])->name('quotes.convert')->middleware('permission:ventes.creer');
    Route::post('/quotes/{quote}/convert-to-order', [QuoteController::class, 'convertToOrder'])->name('quotes.convert-to-order')->middleware('permission:ventes.creer');
    Route::get('/quotes/{quote}/print', [QuoteController::class, 'print'])->name('quotes.print')->middleware('permission:ventes.creer');

    Route::get('/sales', [SaleController::class, 'index'])->name('sales.index')->middleware('permission:ventes.historique');
    Route::get('/sales/{sale}', [SaleController::class, 'show'])->name('sales.show')->middleware('permission:ventes.historique');
    Route::get('/sales/{sale}/print', [SaleController::class, 'print'])->name('sales.print')->middleware('permission:ventes.historique');
    Route::post('/sales/{sale}/lines/{sale_line}/return', [SaleController::class, 'returnLine'])->name('sales.return-line')->middleware('permission:ventes.annuler');
    Route::post('/sales/{sale}/annuler', [SaleController::class, 'cancel'])->name('sales.cancel')->middleware('permission:ventes.annuler');

    Route::middleware('permission:sav.gerer')->group(function () {
        Route::get('/service-tickets', [ServiceTicketController::class, 'index'])->name('service-tickets.index');
        Route::get('/sales/{sale}/lines/{sale_line}/service-tickets/create', [ServiceTicketController::class, 'create'])->name('service-tickets.create');
        Route::post('/sales/{sale}/lines/{sale_line}/service-tickets', [ServiceTicketController::class, 'store'])->name('service-tickets.store');
        Route::get('/service-tickets/{service_ticket}', [ServiceTicketController::class, 'show'])->name('service-tickets.show');
        Route::post('/service-tickets/{service_ticket}/resolve', [ServiceTicketController::class, 'resolve'])->name('service-tickets.resolve');
    });
    Route::post('/sales/{sale}/invoice', [InvoiceController::class, 'store'])->name('invoices.store')->middleware('permission:ventes.historique');
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show')->middleware('permission:ventes.historique');

    Route::middleware('permission:ecommerce.commandes')->group(function () {
        Route::get('/commandes-en-ligne', [OnlineOrderController::class, 'index'])->name('online-orders.index');
        Route::get('/commandes-en-ligne/{order}', [OnlineOrderController::class, 'show'])->name('online-orders.show');
        Route::post('/commandes-en-ligne/{order}/confirmer-paiement-livraison', [OnlineOrderController::class, 'confirmCashOnDelivery'])->name('online-orders.confirm-cod');
        Route::post('/commandes-en-ligne/{order}/preparer', [OnlineOrderController::class, 'startPreparation'])->name('online-orders.start-preparation');
        Route::post('/commandes-en-ligne/{order}/prete', [OnlineOrderController::class, 'markReady'])->name('online-orders.mark-ready');
        Route::post('/commandes-en-ligne/{order}/livrer', [OnlineOrderController::class, 'deliver'])->name('online-orders.deliver');
        Route::post('/commandes-en-ligne/{order}/retrait', [OnlineOrderController::class, 'pickUp'])->name('online-orders.pick-up');
        Route::post('/commandes-en-ligne/{order}/annuler', [OnlineOrderController::class, 'cancel'])->name('online-orders.cancel');
    });

    // --- Catalogue, stock & achats ---
    Route::resource('categories', CategoryController::class)->except('show')
        ->middlewareFor(['index'], 'permission:catalogue.voir')
        ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], 'permission:catalogue.gerer');
    Route::resource('suppliers', SupplierController::class)->except('show')
        ->middlewareFor(['index'], 'permission:fournisseurs.voir')
        ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], 'permission:fournisseurs.gerer');
    Route::resource('product-families', ProductFamilyController::class)->except('show')
        ->middlewareFor(['index'], 'permission:catalogue.voir')
        ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], 'permission:catalogue.gerer');

    Route::middleware('permission:produits.importer')->group(function () {
        Route::get('/products/import', [ProductImportController::class, 'create'])->name('products.import');
        Route::get('/products/import/template', [ProductImportController::class, 'template'])->name('products.import.template');
        Route::post('/products/import/analyze', [ProductImportController::class, 'analyze'])->name('products.import.analyze');
        Route::post('/products/import', [ProductImportController::class, 'store'])->name('products.import.store');
        Route::post('/products/recognize-photo', [ProductRecognitionController::class, 'recognize'])->name('products.recognize-photo');
    });

    Route::resource('products', ProductController::class)
        ->middlewareFor(['index', 'show'], 'permission:produits.voir')
        ->middlewareFor(['create', 'store'], 'permission:produits.creer')
        ->middlewareFor(['edit', 'update'], 'permission:produits.modifier')
        ->middlewareFor(['destroy'], 'permission:produits.supprimer');
    Route::get('/products/{product}/label', [ProductController::class, 'label'])->name('products.label')->middleware('permission:produits.voir');
    Route::post('/products/generate-description', [ProductDescriptionController::class, 'generate'])->name('products.generate-description')->middleware('permission:produits.modifier');

    Route::middleware('permission:stock.voir')->group(function () {
        Route::get('/stock-movements', [StockMovementController::class, 'index'])->name('stock-movements.index');
    });
    Route::middleware('permission:stock.ajuster')->group(function () {
        Route::get('/stock-movements/create', [StockMovementController::class, 'create'])->name('stock-movements.create');
        Route::post('/stock-movements', [StockMovementController::class, 'store'])->name('stock-movements.store');
    });

    Route::get('/purchase-orders/suggestions', [PurchaseOrderController::class, 'suggestions'])->name('purchase-orders.suggestions')->middleware('permission:achats.voir');
    Route::post('/purchase-orders/suggestions', [PurchaseOrderController::class, 'createSuggestions'])->name('purchase-orders.create-suggestions')->middleware('permission:achats.gerer');
    Route::get('/reapprovisionnement-intelligent', [SmartReorderController::class, 'index'])->name('reorder.index')->middleware('permission:achats.voir');
    Route::get('/articles-dormants', [DormantStockController::class, 'index'])->name('dormant-stock.index')->middleware('permission:rapports.voir');
    Route::middleware('permission:achats.gerer')->group(function () {
        Route::get('/purchase-orders/import-invoice', [PurchaseInvoiceImportController::class, 'create'])->name('purchase-orders.import-invoice');
        Route::post('/purchase-orders/import-invoice/analyze', [PurchaseInvoiceImportController::class, 'analyze'])->name('purchase-orders.import-invoice.analyze');
        Route::post('/purchase-orders/import-invoice', [PurchaseInvoiceImportController::class, 'store'])->name('purchase-orders.import-invoice.store');
    });
    Route::resource('purchase-orders', PurchaseOrderController::class)->only(['index', 'create', 'store', 'show'])
        ->middlewareFor(['index', 'show'], 'permission:achats.voir')
        ->middlewareFor(['create', 'store'], 'permission:achats.gerer');
    Route::middleware('permission:achats.gerer')->group(function () {
        Route::get('/purchase-orders/{purchase_order}/edit', [PurchaseOrderController::class, 'edit'])->name('purchase-orders.edit');
        Route::put('/purchase-orders/{purchase_order}', [PurchaseOrderController::class, 'update'])->name('purchase-orders.update');
        Route::post('/purchase-orders/{purchase_order}/place', [PurchaseOrderController::class, 'place'])->name('purchase-orders.place');
        Route::post('/purchase-orders/{purchase_order}/receive', [PurchaseOrderController::class, 'receive'])->name('purchase-orders.receive');
        Route::post('/purchase-orders/{purchase_order}/lines/{line}/return', [PurchaseOrderController::class, 'returnLine'])->name('purchase-orders.return-line');
    });

    Route::resource('stock-transfers', StockTransferController::class)->only(['index', 'create', 'store', 'show'])->middleware('permission:stock.transferer');
    Route::post('/stock-transfers/{stock_transfer}/execute', [StockTransferController::class, 'execute'])->name('stock-transfers.execute')->middleware('permission:stock.transferer');

    Route::resource('inventory-counts', InventoryCountController::class)->only(['index', 'create', 'store', 'show'])->middleware('permission:stock.inventaire');
    Route::post('/inventory-counts/{inventory_count}/lines', [InventoryCountController::class, 'updateLines'])->name('inventory-counts.update-lines')->middleware('permission:stock.inventaire');
    Route::post('/inventory-counts/{inventory_count}/complete', [InventoryCountController::class, 'complete'])->name('inventory-counts.complete')->middleware('permission:stock.inventaire');

    // --- Pilotage ---
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index')->middleware('permission:rapports.voir');
    Route::get('/reports/stock', [StockReportController::class, 'index'])->name('reports.stock')->middleware('permission:rapports.voir');
    Route::get('/reports/encours-clients', [CustomerCreditReportController::class, 'index'])->name('reports.customer-credit')->middleware('permission:rapports.voir');
    Route::get('/reports/cash-flow', [CashFlowReportController::class, 'index'])->name('reports.cash-flow')->middleware('permission:ia.previsions');
    Route::get('/export-comptable', [AccountingExportController::class, 'index'])->name('accounting-export.index')->middleware('permission:rapports.exporter');
    Route::get('/export-comptable/telecharger', [AccountingExportController::class, 'export'])->name('accounting-export.export')->middleware('permission:rapports.exporter');

    Route::resource('users', UserController::class)->except('show')
        ->middlewareFor(['index', 'create', 'store', 'edit', 'update', 'destroy'], 'permission:utilisateurs.creer');
    Route::resource('roles', RoleController::class)->except('show')->middleware('permission:utilisateurs.permissions');

    Route::resource('warehouses', WarehouseController::class)->except(['show', 'destroy'])->middleware('permission:configuration.systeme');
    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index')->middleware('permission:configuration.systeme');
    Route::get('/error-logs', [ErrorLogController::class, 'index'])->name('error-logs.index')->middleware('permission:configuration.systeme');

    Route::middleware('permission:configuration.systeme')->group(function () {
        Route::get('/trash', [TrashController::class, 'index'])->name('trash.index');
        Route::post('/trash/{type}/{id}/restore', [TrashController::class, 'restore'])->name('trash.restore');
        Route::delete('/trash/{type}/{id}', [TrashController::class, 'forceDelete'])->name('trash.force-delete');
    });
});

/*
|--------------------------------------------------------------------------
| Webhook de paiement (Phase 6) — public, sans CSRF (voir bootstrap/app.php),
| appelé par le serveur de l'agrégateur, jamais par un navigateur.
|--------------------------------------------------------------------------
*/
Route::post('/webhooks/paiement/{provider}', PaymentWebhookController::class)->name('webhooks.payment');

/*
|--------------------------------------------------------------------------
| Boutique en ligne (Phase 5) — guard "customer", distinct du back-office
|--------------------------------------------------------------------------
*/
Route::prefix('boutique')->name('shop.')->group(function () {
    Route::get('/', [ShopCatalogController::class, 'index'])->name('catalog.index');
    Route::get('/assistant', fn () => view('shop.assistant'))->name('assistant');
    Route::get('/produits/{product}', [ShopCatalogController::class, 'show'])->name('catalog.show');

    Route::get('/panier', [ShopCartController::class, 'index'])->name('cart.index');
    Route::post('/panier', [ShopCartController::class, 'store'])->name('cart.store');
    Route::post('/panier/mettre-a-jour', [ShopCartController::class, 'update'])->name('cart.update');
    Route::delete('/panier/{productId}', [ShopCartController::class, 'destroy'])->name('cart.destroy');

    Route::middleware('guest:customer')->group(function () {
        Route::get('/connexion', [ShopLoginController::class, 'create'])->name('login');
        Route::post('/connexion', [ShopLoginController::class, 'store']);
        Route::get('/inscription', [ShopRegisterController::class, 'create'])->name('register');
        Route::post('/inscription', [ShopRegisterController::class, 'store']);

        Route::get('/mot-de-passe-oublie', [ShopForgotPasswordController::class, 'create'])->name('password.request');
        Route::post('/mot-de-passe-oublie', [ShopForgotPasswordController::class, 'store'])->name('password.email');
        Route::get('/reinitialiser-mot-de-passe/{token}', [ShopResetPasswordController::class, 'create'])->name('password.reset');
        Route::post('/reinitialiser-mot-de-passe', [ShopResetPasswordController::class, 'store'])->name('password.update');
    });

    Route::middleware('auth:customer')->group(function () {
        Route::post('/deconnexion', [ShopLoginController::class, 'destroy'])->name('logout');

        Route::get('/commande', [ShopCheckoutController::class, 'create'])->name('checkout.create');
        Route::post('/commande', [ShopCheckoutController::class, 'store'])->name('checkout.store');

        Route::get('/compte', [ShopAccountController::class, 'index'])->name('account.index');
        Route::get('/compte/commandes', [ShopAccountController::class, 'orders'])->name('account.orders.index');
        Route::get('/compte/commandes/{order}', [ShopAccountController::class, 'showOrder'])->name('account.orders.show');
        Route::post('/commandes/{order}/simuler-paiement', [ShopPaymentSimulationController::class, 'confirm'])->name('payment.simulate');

        Route::get('/notifications', [ShopNotificationController::class, 'index'])->name('notifications.index');
        Route::post('/notifications/{notification}/read', [ShopNotificationController::class, 'markRead'])->name('notifications.mark-read');
        Route::post('/notifications/read-all', [ShopNotificationController::class, 'markAllRead'])->name('notifications.mark-all-read');
    });
});
