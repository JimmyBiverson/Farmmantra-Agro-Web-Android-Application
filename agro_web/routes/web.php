<?php

use App\Http\Controllers\Web\AccountsController;
use App\Http\Controllers\Web\AdminExtrasController;
use App\Http\Controllers\Web\OperationsController;
use App\Http\Controllers\Web\SupportTicketsController;
use App\Http\Controllers\WebController;
use App\Http\Middleware\AuthenticateWeb;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/site-asset/{path}', function (string $path) {
    $disk = Storage::disk('public');

    abort_unless(str_starts_with($path, 'site/') && $disk->exists($path), 404);

    return response()->file($disk->path($path), [
        'Content-Type' => mime_content_type($disk->path($path)) ?: 'application/octet-stream',
        'Cache-Control' => 'public, max-age=31536000, immutable',
    ]);
})->where('path', '.*')->name('site.asset');

Route::get('/', fn () => redirect()->route('web.dashboard'));

Route::get('/login', [WebController::class, 'showLogin'])->name('login');
Route::post('/login', [WebController::class, 'login'])->name('web.login.submit')->middleware('throttle:10,1');

// Printable documents opened from the mobile app (short-lived signed link).
Route::get('/print/signed', [AccountsController::class, 'printSigned'])->name('print.signed')->middleware('signed');

Route::middleware(AuthenticateWeb::class)->group(function () {
    Route::post('/logout', [WebController::class, 'logout'])->name('web.logout');
    Route::get('/dashboard', [WebController::class, 'dashboard'])->name('web.dashboard');

    // Profile
    Route::get('/profile', [WebController::class, 'profile'])->name('web.profile');
    Route::post('/profile', [WebController::class, 'profileUpdate'])->name('web.profile.update');
    Route::post('/profile/password', [WebController::class, 'profilePassword'])->name('web.profile.password');
    Route::post('/profile/avatar', [WebController::class, 'profileAvatar'])->name('web.profile.avatar');

    // Admin
    Route::get('/admin/franchises', [WebController::class, 'adminFranchises'])->name('web.admin.franchises');
    Route::get('/admin/users', [WebController::class, 'adminUsers'])->name('web.admin.users');
    Route::post('/admin/users', [WebController::class, 'adminStoreUser'])->name('web.admin.users.store');
    Route::post('/admin/users/update', [WebController::class, 'adminUpdateUser'])->name('web.admin.users.update');
    Route::post('/admin/users/delete', [WebController::class, 'adminDeleteUser'])->name('web.admin.users.delete')->middleware('confirm.sensitive');
    Route::get('/admin/products', [WebController::class, 'adminProducts'])->name('web.admin.products');
    Route::post('/admin/products', [WebController::class, 'adminStoreProduct'])->name('web.admin.products.store');
    Route::post('/admin/products/update', [WebController::class, 'adminUpdateProduct'])->name('web.admin.products.update');
    Route::post('/admin/products/delete', [WebController::class, 'adminDeleteProduct'])->name('web.admin.products.delete')->middleware('confirm.sensitive');
    Route::post('/admin/products/delete-image', [WebController::class, 'adminDeleteProductImage'])->name('web.admin.products.deleteImage')->middleware('confirm.sensitive');
    Route::get('/admin/categories', [WebController::class, 'adminCategories'])->name('web.admin.categories');
    Route::post('/admin/categories', [WebController::class, 'adminStoreCategory'])->name('web.admin.categories.store');
    Route::post('/admin/categories/update', [WebController::class, 'adminUpdateCategory'])->name('web.admin.categories.update');
    Route::post('/admin/categories/delete', [WebController::class, 'adminDeleteCategory'])->name('web.admin.categories.delete')->middleware('confirm.sensitive');
    Route::get('/admin/orders', [WebController::class, 'adminOrders'])->name('web.admin.orders');
    Route::post('/admin/business-reset', [WebController::class, 'adminBusinessReset'])->name('web.admin.businessReset')->middleware('confirm.sensitive');
    Route::post('/admin/orders/{id}/approve', [WebController::class, 'adminApproveOrder'])->name('web.admin.orders.approve');
    Route::post('/admin/orders/{id}/decline', [WebController::class, 'adminDeclineOrder'])->name('web.admin.orders.decline');
    Route::get('/admin/payments', [WebController::class, 'adminPayments'])->name('web.admin.payments');
    Route::post('/admin/payments/{id}/accept', [WebController::class, 'adminAcceptPayment'])->name('web.admin.payments.accept');
    Route::post('/admin/payments/{id}/reject', [WebController::class, 'adminRejectPayment'])->name('web.admin.payments.reject');
    Route::get('/admin/reports', [WebController::class, 'adminReports'])->name('web.admin.reports');
    Route::get('/admin/audit', [WebController::class, 'adminAudit'])->name('web.admin.audit');
    Route::get('/admin/news', [WebController::class, 'adminNews'])->name('web.admin.news');
    Route::post('/admin/news', [WebController::class, 'adminStoreNews'])->name('web.admin.news.store');
    Route::post('/admin/news/update', [WebController::class, 'adminUpdateNews'])->name('web.admin.news.update');
    Route::post('/admin/news/delete', [WebController::class, 'adminDeleteNews'])->name('web.admin.news.delete')->middleware('confirm.sensitive');
    Route::get('/admin/faqs', [WebController::class, 'adminFaqs'])->name('web.admin.faqs');
    Route::post('/admin/faqs', [WebController::class, 'adminStoreFaq'])->name('web.admin.faqs.store');
    Route::post('/admin/faqs/update', [WebController::class, 'adminUpdateFaq'])->name('web.admin.faqs.update');
    Route::post('/admin/faqs/delete', [WebController::class, 'adminDeleteFaq'])->name('web.admin.faqs.delete')->middleware('confirm.sensitive');
    Route::get('/admin/slides', [WebController::class, 'adminSlides'])->name('web.admin.slides');
    Route::post('/admin/slides', [WebController::class, 'adminStoreSlide'])->name('web.admin.slides.store');
    Route::post('/admin/slides/update', [WebController::class, 'adminUpdateSlide'])->name('web.admin.slides.update');
    Route::post('/admin/slides/delete', [WebController::class, 'adminDeleteSlide'])->name('web.admin.slides.delete')->middleware('confirm.sensitive');
    Route::get('/admin/pages', [WebController::class, 'adminPages'])->name('web.admin.pages');
    Route::post('/admin/pages', [WebController::class, 'adminStorePage'])->name('web.admin.pages.store');
    Route::post('/admin/pages/update', [WebController::class, 'adminUpdatePage'])->name('web.admin.pages.update');
    Route::post('/admin/pages/delete', [WebController::class, 'adminDeletePage'])->name('web.admin.pages.delete')->middleware('confirm.sensitive');
    Route::get('/admin/settings', [WebController::class, 'adminSettings'])->name('web.admin.settings.general');
    Route::get('/admin/settings/site', [WebController::class, 'adminSettingsSite'])->name('web.admin.settings.site');
    Route::post('/admin/settings/site', [WebController::class, 'adminSettingsSiteUpdate'])->name('web.admin.settings.site.update');
    Route::get('/admin/settings/users', [WebController::class, 'adminSettingsUsers'])->name('web.admin.settings.users');
    Route::get('/admin/settings/roles', [WebController::class, 'adminSettingsRoles'])->name('web.admin.settings.roles');
    Route::get('/admin/settings/notifications', [WebController::class, 'adminSettingsNotifications'])->name('web.admin.settings.notifications');
    Route::post('/admin/settings/notifications', [WebController::class, 'adminSettingsNotificationsUpdate'])->name('web.admin.settings.notifications.update');
    Route::get('/admin/settings/system', [WebController::class, 'adminSettingsSystem'])->name('web.admin.settings.system');

    // Stock Movements
    Route::get('/admin/stock-movements', [WebController::class, 'adminStockMovements'])->name('web.admin.stockMovements');

    // Report Exports
    Route::get('/admin/reports/export', [WebController::class, 'adminReportExport'])->name('web.admin.reports.export');

    // Admin Chat
    Route::get('/admin/chat', [WebController::class, 'adminChat'])->name('web.admin.chat');
    Route::get('/admin/chat/{id}/messages', [WebController::class, 'adminChatMessages'])->name('web.admin.chat.messages');
    Route::post('/admin/chat/{id}/send', [WebController::class, 'adminChatSend'])->name('web.admin.chat.send');

    // Admin Password Reset
    Route::post('/admin/users/reset-password', [WebController::class, 'adminResetPassword'])->name('web.admin.users.resetPassword');

    // Staff
    Route::get('/staff/orders', [OperationsController::class, 'orders'])->name('web.staff.orders');

    // Warehouse (and admin): accept / decline / dispatch / arrival
    Route::post('/ops/orders/{order}/approve', [OperationsController::class, 'approve'])->name('web.ops.orders.approve');
    Route::post('/ops/orders/{order}/decline', [OperationsController::class, 'decline'])->name('web.ops.orders.decline');
    Route::post('/ops/orders/{order}/dispatch', [OperationsController::class, 'dispatch'])->name('web.ops.orders.dispatch');
    Route::post('/ops/orders/{order}/arrived', [OperationsController::class, 'markDelivered'])->name('web.ops.orders.arrived');
    Route::get('/staff/inventory', [WebController::class, 'staffInventory'])->name('web.staff.inventory');
    Route::get('/staff/franchise-stock', [WebController::class, 'staffFranchiseStock'])->name('web.staff.franchiseStock');

    // Finance
    Route::get('/finance/payments', [WebController::class, 'financePayments'])->name('web.finance.payments');
    Route::post('/finance/payments/{id}/accept', [WebController::class, 'financeAcceptPayment'])->name('web.finance.payments.accept');
    Route::post('/finance/payments/{id}/reject', [WebController::class, 'financeRejectPayment'])->name('web.finance.payments.reject');
    Route::get('/finance/reports', [WebController::class, 'financeReports'])->name('web.finance.reports');

    // Franchise
    Route::get('/franchise/orders', [WebController::class, 'franchiseOrders'])->name('web.franchise.orders');
    Route::get('/franchise/sales', [WebController::class, 'franchiseSales'])->name('web.franchise.sales');
    Route::get('/franchise/inventory', [WebController::class, 'franchiseInventory'])->name('web.franchise.inventory');
    Route::get('/franchise/payments', [WebController::class, 'franchisePayments'])->name('web.franchise.payments');
    // Admin: Franchise CRUD
    Route::post('/admin/franchises', [WebController::class, 'adminStoreFranchise'])->name('web.admin.franchises.store');
    Route::post('/admin/franchises/update', [WebController::class, 'adminUpdateFranchise'])->name('web.admin.franchises.update');
    Route::post('/admin/franchises/delete', [WebController::class, 'adminDeleteFranchise'])->name('web.admin.franchises.delete')->middleware('confirm.sensitive');
    Route::post('/admin/franchises/toggle', [WebController::class, 'adminToggleFranchise'])->name('web.admin.franchises.toggle');

    // Admin: User Toggle
    Route::post('/admin/users/toggle', [WebController::class, 'adminToggleUser'])->name('web.admin.users.toggle');

    // Admin: Price Slabs
    Route::post('/admin/price-slabs', [WebController::class, 'adminStorePriceSlab'])->name('web.admin.priceSlabs.store');
    Route::post('/admin/price-slabs/update', [WebController::class, 'adminUpdatePriceSlab'])->name('web.admin.priceSlabs.update');
    Route::post('/admin/price-slabs/delete', [WebController::class, 'adminDeletePriceSlab'])->name('web.admin.priceSlabs.delete')->middleware('confirm.sensitive');

    // Admin: Sales Targets
    Route::post('/admin/sales-targets', [WebController::class, 'adminStoreSalesTarget'])->name('web.admin.salesTargets.store');
    Route::post('/admin/sales-targets/update', [WebController::class, 'adminUpdateSalesTarget'])->name('web.admin.salesTargets.update');
    Route::post('/admin/sales-targets/delete', [WebController::class, 'adminDeleteSalesTarget'])->name('web.admin.salesTargets.delete')->middleware('confirm.sensitive');

    // Staff: Stock + Receipts
    Route::post('/staff/inventory/update', [WebController::class, 'staffUpdateWarehouseStock'])->name('web.ops.inventory.update');
    Route::get('/staff/stock-receipts', [WebController::class, 'staffStockReceipts'])->name('web.staff.stockReceipts');

    // Franchise: Order + Sale + Customer + Payment + Chat
    Route::post('/franchise/orders/place', [WebController::class, 'franchisePlaceOrder'])->name('web.franchise.orders.place');
    Route::post('/franchise/sales/create', [WebController::class, 'franchiseCreateSale'])->name('web.franchise.sales.create');
    Route::post('/franchise/customers/create', [WebController::class, 'franchiseCreateCustomer'])->name('web.franchise.customers.create');
    Route::post('/franchise/payments/submit', [WebController::class, 'franchiseSubmitPayment'])->name('web.franchise.payments.submit');
    Route::get('/franchise/chat', [WebController::class, 'franchiseChatMessages'])->name('web.franchise.chat');
    Route::post('/franchise/chat/send', [WebController::class, 'franchiseChatSend'])->name('web.franchise.chat.send');
    Route::post('/franchise/chat/create', [WebController::class, 'franchiseChatCreate'])->name('web.franchise.chat.create');

    // Support tickets (all roles; admin answers)
    Route::get('/support/tickets', [SupportTicketsController::class, 'index'])->name('web.support.tickets');
    Route::post('/support/tickets', [SupportTicketsController::class, 'store'])->name('web.support.tickets.store');
    Route::post('/support/tickets/{ticket}', [SupportTicketsController::class, 'respond'])->name('web.support.tickets.respond');

    // Terms & conditions (franchise first login / new version)
    Route::get('/terms', [AccountsController::class, 'terms'])->name('web.terms.show');
    Route::post('/terms', [AccountsController::class, 'acceptTerms'])->name('web.terms.accept');

    // Printable documents (every role; franchise only its own)
    Route::get('/print/{type}/{id}', [AccountsController::class, 'print'])->name('web.print')->whereNumber('id');

    // Stock reports: company + franchise (admin, sales staff, warehouse, franchise)
    Route::get('/reports/stock', [AccountsController::class, 'stockReport'])->name('web.reports.stock');

    // Franchise: accept delivered orders, account statement
    Route::get('/franchise/orders/{order}/receive', [OperationsController::class, 'receiveForm'])->name('web.franchise.orders.receive');
    Route::post('/franchise/orders/{order}/receive', [OperationsController::class, 'receive'])->name('web.franchise.orders.receive.submit');
    Route::get('/franchise/statement', [AccountsController::class, 'myStatement'])->name('web.franchise.statement');

    // Finance + admin: statements, credit notes, sales returns
    Route::get('/accounts/statements', [AccountsController::class, 'statement'])->name('web.accounts.statements');
    Route::get('/accounts/credit-notes', [AccountsController::class, 'creditNotes'])->name('web.accounts.creditNotes');
    Route::post('/accounts/credit-notes', [AccountsController::class, 'storeCreditNote'])->name('web.accounts.creditNotes.store');

    // Admin: customers, regions & districts, bulk SMS, terms
    Route::get('/admin/customers', [AdminExtrasController::class, 'customers'])->name('web.admin.customers');
    Route::post('/admin/customers', [AdminExtrasController::class, 'storeCustomer'])->name('web.admin.customers.store');
    Route::post('/admin/customers/{customer}', [AdminExtrasController::class, 'updateCustomer'])->name('web.admin.customers.update');
    Route::post('/admin/customers/{customer}/toggle', [AdminExtrasController::class, 'toggleCustomer'])->name('web.admin.customers.toggle');
    Route::get('/admin/regions', [AdminExtrasController::class, 'regions'])->name('web.admin.regions');
    Route::post('/admin/regions', [AdminExtrasController::class, 'storeRegion'])->name('web.admin.regions.store');
    Route::post('/admin/regions/{region}', [AdminExtrasController::class, 'updateRegion'])->name('web.admin.regions.update');
    Route::post('/admin/districts', [AdminExtrasController::class, 'storeDistrict'])->name('web.admin.districts.store');
    Route::get('/admin/sms', [AdminExtrasController::class, 'sms'])->name('web.admin.sms');
    Route::post('/admin/sms', [AdminExtrasController::class, 'sendSms'])->name('web.admin.sms.send');
    Route::get('/admin/terms', [AdminExtrasController::class, 'termsEdit'])->name('web.admin.terms');
    Route::post('/admin/terms', [AdminExtrasController::class, 'termsUpdate'])->name('web.admin.terms.update');
});
