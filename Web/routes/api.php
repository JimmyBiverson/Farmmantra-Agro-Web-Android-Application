<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuditController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\FinanceController;
use App\Http\Controllers\Api\FinanceDashboardController;
use App\Http\Controllers\Api\FranchiseDashboardController;
use App\Http\Controllers\Api\FranchiseInventoryController;
use App\Http\Controllers\Api\FranchiseOrderController;
use App\Http\Controllers\Api\FranchiseSalesController;
use App\Http\Controllers\Api\MasterDataController;
use App\Http\Controllers\Api\NewsController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\StaffDashboardController;
use App\Http\Controllers\Api\StaffInventoryController;
use App\Http\Controllers\Api\StaffOrderController;
use App\Http\Controllers\Api\StockMovementController;
use App\Http\Controllers\Api\StockReceiptController;
use App\Http\Controllers\Api\SupportTicketController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| Farmmantra Agro Chemicals Limited — RESTful API
| Designed for mobile (Flutter) + web consumption.
*/

// ─── Public ──────────────────────────────────────────────────────
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::get('/settings/public', [SettingsController::class, 'public']);
Route::get('/slides', [NewsController::class, 'slides']);
Route::get('/news', [NewsController::class, 'news']);
Route::get('/news/{news}', [NewsController::class, 'showNews']);

// ─── Authenticated ───────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // ── Profile ──────────────────────────────────────────────
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::post('/profile/avatar', [AuthController::class, 'uploadAvatar']);
    Route::post('/device-token', [AuthController::class, 'updateDeviceToken']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);

    // ── Terms & conditions (first login + every delivery) ────
    Route::get('/terms', [AccountController::class, 'terms']);
    Route::post('/terms/accept', [AccountController::class, 'acceptTerms']);

    // ── Printable documents (signed browser links) & stock report
    Route::get('/print-links/{type}/{id}', [AccountController::class, 'printLink'])->whereNumber('id');
    Route::get('/stock-report', [AccountController::class, 'stockReport'])
        ->middleware('api.role:System Administrator,Farmmantra Staff,Warehouse,Franchise Partner');

    // ── Notifications (all authenticated) ────────────────────
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);

    // ── Master Data (all roles) ──────────────────────────────
    Route::get('/categories', [MasterDataController::class, 'categories']);
    Route::get('/products', [MasterDataController::class, 'products']);
    Route::get('/products/{product}', [MasterDataController::class, 'product']);
    Route::get('/products/{product}/price-slabs', [MasterDataController::class, 'priceSlabs']);

    // ── Stock Movements (all authenticated) ──────────────────
    Route::get('/stock-movements', [StockMovementController::class, 'index']);

    // ── Chat & Support Tickets (all authenticated) ────────────
    Route::get('/conversations', [ChatController::class, 'index']);
    Route::post('/conversations', [ChatController::class, 'store']);
    Route::get('/conversations/{conversation}', [ChatController::class, 'show']);
    Route::post('/conversations/{conversation}/messages', [ChatController::class, 'send']);
    Route::post('/conversations/{conversation}/read', [ChatController::class, 'markRead']);
    Route::get('/conversations/{conversation}/messages/since/{afterId?}', [ChatController::class, 'messagesSince']);

    Route::get('/support-tickets', [SupportTicketController::class, 'index']);
    Route::post('/support-tickets', [SupportTicketController::class, 'store']);
    Route::get('/support-tickets/{ticket}', [SupportTicketController::class, 'show']);

    // ── Reports (admin + finance) ────────────────────────────
    Route::middleware('api.role:System Administrator,Finance Department')->group(function () {
        Route::get('/reports/sales', [ReportController::class, 'salesReport']);
        Route::get('/reports/payments', [ReportController::class, 'paymentReport']);
        Route::get('/reports/orders', [ReportController::class, 'orderReport']);
        Route::get('/reports/inventory', [ReportController::class, 'inventoryReport']);
        Route::get('/reports/franchise-comparison', [ReportController::class, 'franchiseComparison']);
    });

    // ── Audit Logs (admin only) ──────────────────────────────
    Route::middleware('api.role:System Administrator')->prefix('admin')->group(function () {
        Route::get('/audit-logs', [AuditController::class, 'activityLogs']);
        Route::get('/audit-logs/summary', [AuditController::class, 'activitySummary']);
        Route::get('/users/{user}/activity', [AuditController::class, 'userActivity']);
    });

    // ══════════════════════════════════════════════════════════
    // FRANCHISE PARTNER
    // ══════════════════════════════════════════════════════════
    Route::middleware(['api.role:Franchise Partner', 'franchise.active', 'terms.accepted'])->prefix('franchise')->group(function () {

        Route::get('/dashboard', FranchiseDashboardController::class);

        Route::get('/orders', [FranchiseOrderController::class, 'index']);
        Route::post('/orders', [FranchiseOrderController::class, 'store']);
        Route::get('/orders/{order}', [FranchiseOrderController::class, 'show']);
        Route::post('/orders/{order}/confirm-delivery', [FranchiseOrderController::class, 'confirmDelivery']);

        Route::get('/stock-receipts', [StockReceiptController::class, 'index']);
        Route::get('/stock-receipts/{stockReceipt}', [StockReceiptController::class, 'show']);
        Route::post('/stock-receipts/{stockReceipt}/confirm', [StockReceiptController::class, 'confirm']);

        Route::get('/sales', [FranchiseSalesController::class, 'index']);
        Route::post('/sales', [FranchiseSalesController::class, 'store']);
        Route::get('/sales/{sale}', [FranchiseSalesController::class, 'show']);

        Route::get('/customers', [CustomerController::class, 'index']);
        Route::post('/customers', [CustomerController::class, 'store']);
        Route::get('/customers/{customer}', [CustomerController::class, 'show']);
        Route::get('/districts', [CustomerController::class, 'districts']);

        Route::get('/statement', [AccountController::class, 'myStatement']);

        Route::get('/inventory', [FranchiseInventoryController::class, 'index']);
        Route::get('/inventory/low-stock', [FranchiseInventoryController::class, 'lowStock']);

        Route::get('/payments', [PaymentController::class, 'index']);
        Route::post('/payments', [PaymentController::class, 'store']);
        Route::get('/payments/{paymentSubmission}', [PaymentController::class, 'show']);
        Route::post('/payments/{paymentSubmission}/upload-proof', [PaymentController::class, 'uploadProof']);
    });

    // ══════════════════════════════════════════════════════════
    // FARMMANTRA STAFF (monitor only) + WAREHOUSE (accept & dispatch)
    // Sales staff can view everything here; only the Warehouse role (and
    // admin) can accept, decline, adjust, dispatch or change stock.
    // ══════════════════════════════════════════════════════════
    Route::middleware('api.role:Farmmantra Staff,Warehouse,System Administrator')->prefix('staff')->group(function () {

        Route::get('/dashboard', StaffDashboardController::class);

        Route::get('/orders', [StaffOrderController::class, 'index']);
        Route::get('/orders/{order}', [StaffOrderController::class, 'show']);
        Route::get('/dispatch-options', [StaffOrderController::class, 'dispatchOptions']);

        Route::get('/warehouse-stock', [StaffInventoryController::class, 'warehouseStock']);
        Route::get('/franchise-stock', [StaffInventoryController::class, 'franchiseStock']);

        Route::middleware('api.role:Warehouse,System Administrator')->group(function () {
            Route::post('/orders/{order}/approve', [StaffOrderController::class, 'approve']);
            Route::post('/orders/{order}/decline', [StaffOrderController::class, 'decline']);
            Route::post('/orders/{order}/adjust', [StaffOrderController::class, 'adjust']);
            Route::post('/orders/{order}/dispatch', [StaffOrderController::class, 'dispatch']);
            Route::post('/orders/{order}/mark-delivered', [StaffOrderController::class, 'markDelivered']);
            Route::post('/orders/{order}/decline-delivery', [StaffOrderController::class, 'declineDelivery']);
            Route::post('/warehouse-stock', [StaffInventoryController::class, 'updateWarehouseStock']);
        });
    });

    // ══════════════════════════════════════════════════════════
    // FINANCE DEPARTMENT
    // ══════════════════════════════════════════════════════════
    Route::middleware('api.role:Finance Department')->prefix('finance')->group(function () {

        Route::get('/dashboard', FinanceDashboardController::class);

        Route::get('/payments/pending', [FinanceController::class, 'pendingPayments']);
        Route::get('/payments', [FinanceController::class, 'allPayments']);
        Route::get('/payments/{paymentSubmission}', [FinanceController::class, 'showPayment']);
        Route::post('/payments/{paymentSubmission}/verify', [FinanceController::class, 'verify']);
        Route::post('/payments/{paymentSubmission}/accept', [FinanceController::class, 'accept']);
        Route::post('/payments/{paymentSubmission}/reject', [FinanceController::class, 'reject']);
        Route::post('/payments/{paymentSubmission}/request-info', [FinanceController::class, 'requestInfo']);
    });

    // ── Franchise account statements (finance + admin) ───────
    Route::middleware('api.role:Finance Department,System Administrator')->group(function () {
        Route::get('/statements/{franchise}', [AccountController::class, 'franchiseStatement']);
    });

    // ══════════════════════════════════════════════════════════
    // SYSTEM ADMINISTRATOR
    // ══════════════════════════════════════════════════════════
    Route::middleware('api.role:System Administrator')->prefix('admin')->group(function () {

        Route::get('/dashboard', [AdminController::class, 'dashboard']);

        Route::get('/franchises', [AdminController::class, 'franchises']);
        Route::post('/franchises', [AdminController::class, 'storeFranchise']);
        Route::get('/franchises/{franchise}', [AdminController::class, 'showFranchise']);
        Route::put('/franchises/{franchise}', [AdminController::class, 'updateFranchise']);

        Route::get('/users', [AdminController::class, 'users']);
        Route::post('/users', [AdminController::class, 'storeUser']);

        Route::get('/products', [AdminController::class, 'products']);
        Route::post('/products', [AdminController::class, 'storeProduct']);
        Route::put('/products/{product}', [AdminController::class, 'updateProduct']);
        Route::post('/products/{product}/price-slabs', [AdminController::class, 'storePriceSlab']);

        Route::post('/sales-targets', [AdminController::class, 'storeSalesTarget']);

        // Settings
        Route::get('/settings', [SettingsController::class, 'index']);
        Route::put('/settings', [SettingsController::class, 'update']);
        Route::post('/settings/upload-image', [SettingsController::class, 'uploadImage']);
    });
});
