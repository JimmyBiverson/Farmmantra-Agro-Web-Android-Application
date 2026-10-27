<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Faq;
use App\Models\Franchise;
use App\Models\FranchiseInventory;
use App\Models\News;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Page;
use App\Models\PaymentSubmission;
use App\Models\PriceSlab;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalesTarget;
use App\Models\Setting;
use App\Models\Slide;
use App\Models\StockMovement;
use App\Models\StockReceipt;
use App\Models\User;
use App\Models\WarehouseInventory;
use App\Services\ActivityLogger;
use App\Services\BusinessResetService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WebController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('web.dashboard');
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! $user->is_active) {
            return back()->withErrors(['email' => 'Your account has been deactivated.']);
        }

        if ($user->franchise_id && ! $user->franchise?->is_active) {
            return back()->withErrors(['email' => 'Your franchise account has been deactivated.']);
        }

        Auth::login($user, true);
        $user->update(['last_login_at' => now()]);

        return redirect()->intended(route('web.dashboard'));
    }

    public function logout()
    {
        Auth::logout();

        return redirect()->route('login');
    }

    public function dashboard()
    {
        $user = Auth::user();
        $role = $user->role?->name;
        $now = now();
        $dashboard = [];

        if ($role === 'System Administrator') {
            $dashboard['summary'] = [
                'total_franchises' => Franchise::count(),
                'active_franchises' => Franchise::where('is_active', true)->count(),
                'total_users' => User::count(),
                'pending_orders' => Order::where('status', 'pending')->count(),
                'pending_payments' => PaymentSubmission::where('status', 'pending')->count(),
                'total_sales_this_month' => Sale::whereMonth('sale_date', $now->month)->whereYear('sale_date', $now->year)->sum('final_amount'),
                'total_sales_last_month' => Sale::whereMonth('sale_date', $now->copy()->subMonth()->month)->whereYear('sale_date', $now->copy()->subMonth()->year)->sum('final_amount'),
                'total_sales_ytd' => Sale::whereYear('sale_date', $now->year)->sum('final_amount'),
                'total_outstanding' => Franchise::where('account_balance', '>', 0)->sum('account_balance'),
                'low_stock_products' => WarehouseInventory::whereColumn('quantity', '<=', 'reorder_level')->count(),
                'total_inventory_value' => WarehouseInventory::join('products', 'products.id', '=', 'warehouse_inventories.product_id')
                    ->sum(DB::raw('warehouse_inventories.quantity * products.standard_price')),
            ];
            $dashboard['sales_by_franchise'] = Sale::select('franchise_id', DB::raw('SUM(final_amount) as total_sales'), DB::raw('COUNT(*) as sale_count'))->whereMonth('sale_date', $now->month)->whereYear('sale_date', $now->year)->with('franchise:id,name,code')->groupBy('franchise_id')->orderByDesc('total_sales')->get();
            $dashboard['sales_trend'] = Sale::select(DB::raw('DATE(sale_date) as date'), DB::raw('SUM(final_amount) as total_sales'), DB::raw('COUNT(*) as sale_count'))->where('sale_date', '>=', $now->copy()->subDays(30)->startOfDay())->groupBy(DB::raw('DATE(sale_date)'))->orderBy('date')->get();
            $dashboard['top_products'] = SaleItem::select('product_id', DB::raw('SUM(quantity) as total_qty'), DB::raw('SUM(subtotal) as total_revenue'))->with('product:id,name,sku')->groupBy('product_id')->orderByDesc('total_revenue')->limit(10)->get();
            $dashboard['recent_orders'] = Order::with(['franchise:id,name,code', 'items.product:id,name'])->latest()->limit(10)->get();
            $dashboard['franchise_performance'] = Franchise::select('franchises.*')->withCount(['orders as total_orders' => fn ($q) => $q->whereMonth('created_at', $now->month)->whereYear('created_at', $now->year)])->withSum(['orders as total_order_value' => fn ($q) => $q->whereMonth('created_at', $now->month)->whereYear('created_at', $now->year)], 'total_amount')->withCount(['sales as total_sales_count' => fn ($q) => $q->whereMonth('sale_date', $now->month)->whereYear('sale_date', $now->year)])->withSum(['sales as total_sales_value' => fn ($q) => $q->whereMonth('sale_date', $now->month)->whereYear('sale_date', $now->year)], 'final_amount')->orderByDesc('total_sales_value')->get();
            $dashboard['order_status_breakdown'] = Order::select('status', DB::raw('COUNT(*) as count'))->groupBy('status')->get();
        } elseif ($role === 'Farmmantra Staff') {
            $dashboard['summary'] = [
                'pending_orders' => Order::where('status', 'pending')->count(),
                'approved_orders_today' => Order::where('status', 'approved')->whereDate('approved_at', $now->toDateString())->count(),
                'total_orders_this_month' => Order::whereMonth('created_at', $now->month)->whereYear('created_at', $now->year)->count(),
                'declined_orders_this_month' => Order::where('status', 'declined')->whereMonth('created_at', $now->month)->whereYear('created_at', $now->year)->count(),
                'total_orders_value_this_month' => Order::whereMonth('created_at', $now->month)->whereYear('created_at', $now->year)->sum('total_amount'),
                'low_stock_products' => WarehouseInventory::whereColumn('quantity', '<=', 'reorder_level')->count(),
                'total_warehouse_value' => WarehouseInventory::join('products', 'products.id', '=', 'warehouse_inventories.product_id')
                    ->sum(DB::raw('warehouse_inventories.quantity * products.standard_price')),
                'active_franchises' => Franchise::where('is_active', true)->count(),
            ];
            $dashboard['pending_orders_list'] = Order::where('status', 'pending')->with(['franchise:id,name,code', 'items.product:id,name,sku'])->latest()->limit(10)->get();
            $dashboard['recent_approved_orders'] = Order::where('status', 'approved')->with(['franchise:id,name,code', 'items.product:id,name'])->latest()->limit(5)->get();
            $dashboard['warehouse_stock'] = WarehouseInventory::with('product:id,name,sku,unit_of_measure')->get();
            $dashboard['orders_by_status'] = Order::select('status', DB::raw('COUNT(*) as count'))->groupBy('status')->get();
            $dashboard['orders_trend'] = Order::select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as order_count'), DB::raw('SUM(total_amount) as total_value'))->where('created_at', '>=', $now->copy()->subDays(30)->startOfDay())->groupBy(DB::raw('DATE(created_at)'))->orderBy('date')->get();
        } elseif ($role === 'Finance Department') {
            $dashboard['summary'] = [
                'pending_payments_count' => PaymentSubmission::where('status', 'pending')->count(),
                'pending_payments_total' => PaymentSubmission::where('status', 'pending')->sum('amount'),
                'accepted_this_month' => PaymentSubmission::where('status', 'accepted')->whereMonth('accepted_at', $now->month)->count(),
                'accepted_amount_this_month' => PaymentSubmission::where('status', 'accepted')->whereMonth('accepted_at', $now->month)->sum('verified_amount'),
                'total_outstanding' => Franchise::where('account_balance', '>', 0)->sum('account_balance'),
                'total_collected_ytd' => PaymentSubmission::where('status', 'accepted')->whereYear('accepted_at', $now->year)->sum('verified_amount'),
            ];
            $dashboard['outstanding_by_franchise'] = Franchise::where('account_balance', '>', 0)->select('id', 'name', 'code', 'account_balance', 'credit_limit')->orderByDesc('account_balance')->get()->map(fn ($f) => ['id' => $f->id, 'name' => $f->name, 'code' => $f->code, 'balance' => $f->account_balance, 'credit_limit' => $f->credit_limit, 'utilization' => $f->credit_limit > 0 ? round(($f->account_balance / $f->credit_limit) * 100, 1) : 0]);
            $dashboard['recent_pending'] = PaymentSubmission::where('status', 'pending')->with('franchise:id,name,code')->latest('submitted_at')->limit(10)->get();
        } elseif ($role === 'Franchise Partner') {
            $franchiseId = $user->franchise_id;
            $dashboard['summary'] = [
                'total_sales_this_month' => Sale::where('franchise_id', $franchiseId)->whereMonth('sale_date', $now->month)->whereYear('sale_date', $now->year)->sum('final_amount'),
                'total_sales_ytd' => Sale::where('franchise_id', $franchiseId)->whereYear('sale_date', $now->year)->sum('final_amount'),
                'total_inventory_value' => FranchiseInventory::where('franchise_id', $franchiseId)->sum('total_value'),
                'pending_orders' => Order::where('franchise_id', $franchiseId)->where('status', 'pending')->count(),
                'pending_payments' => PaymentSubmission::where('franchise_id', $franchiseId)->where('status', 'pending')->count(),
                'outstanding_balance' => $user->franchise?->account_balance ?? 0,
                'credit_limit' => $user->franchise?->credit_limit ?? 0,
                'credit_used_percentage' => $user->franchise?->credit_limit > 0 ? round(($user->franchise->account_balance / $user->franchise->credit_limit) * 100, 1) : 0,
                'low_stock_items' => FranchiseInventory::where('franchise_id', $franchiseId)->whereColumn('quantity', '<=', 'reorder_level')->count(),
            ];
            $dashboard['sales_by_category'] = DB::table('sale_items')->join('sales', 'sales.id', '=', 'sale_items.sale_id')->join('products', 'products.id', '=', 'sale_items.product_id')->join('categories', 'categories.id', '=', 'products.category_id')->where('sales.franchise_id', $franchiseId)->whereMonth('sales.sale_date', $now->month)->whereYear('sales.sale_date', $now->year)->select('categories.name as category_name', DB::raw('SUM(sale_items.subtotal) as total_sales'), DB::raw('SUM(sale_items.quantity) as total_qty'))->groupBy('categories.name')->orderByDesc('total_sales')->get();
            $dashboard['sales_targets'] = SalesTarget::where('franchise_id', $franchiseId)->where('month', $now->month)->where('year', $now->year)->with('productCategory:id,name')->get()->map(function ($target) use ($franchiseId, $now) {
                $actualSales = Sale::where('franchise_id', $franchiseId)->whereMonth('sale_date', $now->month)->whereYear('sale_date', $now->year);
                if ($target->product_category_id) {
                    $actualSales = $actualSales->whereHas('items.product', fn ($q) => $q->where('category_id', $target->product_category_id));
                }
                $actual = $actualSales->sum('final_amount');

                return ['target_id' => $target->id, 'category' => $target->productCategory?->name ?? 'All Products', 'target_amount' => $target->target_amount, 'actual_amount' => $actual, 'achievement_percentage' => $target->target_amount > 0 ? round(($actual / $target->target_amount) * 100, 1) : 0];
            });
            $dashboard['inventory_status'] = FranchiseInventory::where('franchise_id', $franchiseId)->with('product:id,name,sku,selling_price')->get()->map(fn ($item) => ['product_name' => $item->product?->name, 'sku' => $item->product?->sku, 'quantity' => $item->quantity, 'reorder_level' => $item->reorder_level, 'is_low_stock' => $item->quantity <= $item->reorder_level, 'value' => $item->quantity * ($item->product?->selling_price ?? 0)]);
        }

        return view('dashboard', compact('dashboard'));
    }

    // ── Admin Pages ──────────────────────────────────────────
    public function adminFranchises()
    {
        $franchises = Franchise::withCount(['users', 'orders', 'sales'])->latest()->paginate(20);

        return view('admin.franchises', compact('franchises'));
    }

    public function adminUsers()
    {
        $users = User::with(['role', 'franchise'])->latest()->paginate(20);
        $roles = Role::orderBy('name')->get();
        $franchises = Franchise::orderBy('name')->get();

        return view('admin.users', compact('users', 'roles', 'franchises'));
    }

    public function adminProducts()
    {
        $products = Product::with(['category', 'priceSlabs', 'warehouseInventory'])->latest()->paginate(20);
        $categories = Category::orderBy('sort_order')->get();

        return view('admin.products', compact('products', 'categories'));
    }

    public function adminCategories()
    {
        $categories = Category::withCount('products')->orderBy('sort_order')->get();

        return view('admin.categories', compact('categories'));
    }

    public function adminOrders()
    {
        $orders = Order::with(['franchise:id,name,code', 'items.product:id,name', 'orderedByUser:id,name'])
            ->latest()
            ->paginate(20);
        $summary = [
            'total' => Order::count(),
            'pending' => Order::where('status', 'pending')->count(),
            'approved' => Order::where('status', 'approved')->count(),
            'declined' => Order::where('status', 'declined')->count(),
        ];

        return view('admin.orders', compact('orders', 'summary'));
    }

    public function adminPayments()
    {
        $payments = PaymentSubmission::with('franchise:id,name,code')
            ->latest('submitted_at')
            ->paginate(20);
        $summary = [
            'total' => PaymentSubmission::count(),
            'pending' => PaymentSubmission::where('status', 'pending')->count(),
            'accepted' => PaymentSubmission::where('status', 'accepted')->count(),
            'rejected' => PaymentSubmission::where('status', 'rejected')->count(),
        ];

        return view('admin.payments', compact('payments', 'summary'));
    }

    public function adminBusinessReset(Request $request, BusinessResetService $resetService)
    {
        $request->validate([
            'confirmation' => ['required', 'in:START FRESH'],
        ]);

        $result = $resetService->reset();

        ActivityLogger::log('business.reset', 'Business data reset by admin', null, auth()->id(), [
            'total_deleted' => $result['total_deleted'],
            'balances_reset' => $result['balances_reset'],
        ]);

        return redirect()->route('web.admin.orders')->with('success', sprintf(
            'Fresh start complete: %d operational records cleared and %d franchise balances reset.',
            $result['total_deleted'],
            $result['balances_reset']
        ));
    }

    public function adminReports(Request $request)
    {
        $type = $request->get('type', '');
        $from = $request->get('from', now()->startOfMonth()->format('Y-m-d'));
        $to = $request->get('to', now()->format('Y-m-d'));
        $franchiseId = $request->get('franchise_id', '');
        $franchises = Franchise::where('is_active', true)->orderBy('name')->get();

        $reportData = null;
        $summary = [];
        $reportTitle = '';

        if ($type) {
            $fromDate = Carbon::parse($from)->startOfDay();
            $toDate = Carbon::parse($to)->endOfDay();

            switch ($type) {
                case 'sales':
                    $reportTitle = 'Sales Report';
                    $query = Sale::with(['franchise', 'customer', 'creator', 'items.product'])
                        ->whereBetween('sale_date', [$fromDate, $toDate]);
                    if ($franchiseId) {
                        $query->where('franchise_id', $franchiseId);
                    }
                    $reportData = $query->latest('sale_date')->get();

                    $summary = [
                        ['label' => 'Total Sales', 'value' => $reportData->sum('final_amount'), 'icon' => 'fa-money-bill-wave', 'color' => 'gradient-indigo', 'format' => 'currency'],
                        ['label' => 'Transactions', 'value' => $reportData->count(), 'icon' => 'fa-receipt', 'color' => 'gradient-green', 'format' => 'number'],
                        ['label' => 'Avg. Per Sale', 'value' => $reportData->count() ? $reportData->avg('final_amount') : 0, 'icon' => 'fa-chart-line', 'color' => 'gradient-amber', 'format' => 'currency'],
                        ['label' => 'Total Discount', 'value' => $reportData->sum('discount'), 'icon' => 'fa-percent', 'color' => 'gradient-cyan', 'format' => 'currency'],
                        ['label' => 'Paid', 'value' => $reportData->where('payment_status', 'paid')->count(), 'icon' => 'fa-check-circle', 'color' => 'gradient-green', 'format' => 'number'],
                        ['label' => 'Unpaid', 'value' => $reportData->where('payment_status', '!=', 'paid')->count(), 'icon' => 'fa-exclamation-circle', 'color' => 'gradient-rose', 'format' => 'number'],
                    ];
                    break;

                case 'orders':
                    $reportTitle = 'Order Report';
                    $query = Order::with(['franchise', 'orderedByUser', 'items.product'])
                        ->whereBetween('created_at', [$fromDate, $toDate]);
                    if ($franchiseId) {
                        $query->where('franchise_id', $franchiseId);
                    }
                    $reportData = $query->latest()->get();

                    $summary = [
                        ['label' => 'Total Orders', 'value' => $reportData->count(), 'icon' => 'fa-clipboard-list', 'color' => 'gradient-indigo', 'format' => 'number'],
                        ['label' => 'Total Value', 'value' => $reportData->sum('total_amount'), 'icon' => 'fa-money-bill-wave', 'color' => 'gradient-green', 'format' => 'currency'],
                        ['label' => 'Pending', 'value' => $reportData->where('status', 'pending')->count(), 'icon' => 'fa-clock', 'color' => 'gradient-amber', 'format' => 'number'],
                        ['label' => 'Approved', 'value' => $reportData->where('status', 'approved')->count(), 'icon' => 'fa-check-circle', 'color' => 'gradient-green', 'format' => 'number'],
                        ['label' => 'Declined', 'value' => $reportData->where('status', 'declined')->count(), 'icon' => 'fa-times-circle', 'color' => 'gradient-rose', 'format' => 'number'],
                        ['label' => 'Avg. Order Value', 'value' => $reportData->count() ? $reportData->avg('total_amount') : 0, 'icon' => 'fa-chart-bar', 'color' => 'gradient-cyan', 'format' => 'currency'],
                    ];
                    break;

                case 'payments':
                    $reportTitle = 'Payment Report';
                    $query = PaymentSubmission::with('franchise')
                        ->whereBetween('submitted_at', [$fromDate, $toDate]);
                    if ($franchiseId) {
                        $query->where('franchise_id', $franchiseId);
                    }
                    $reportData = $query->latest()->get();

                    $summary = [
                        ['label' => 'Total Submissions', 'value' => $reportData->count(), 'icon' => 'fa-credit-card', 'color' => 'gradient-indigo', 'format' => 'number'],
                        ['label' => 'Total Amount', 'value' => $reportData->sum('amount'), 'icon' => 'fa-money-bill-wave', 'color' => 'gradient-green', 'format' => 'currency'],
                        ['label' => 'Accepted', 'value' => $reportData->where('status', 'accepted')->count(), 'icon' => 'fa-check-circle', 'color' => 'gradient-green', 'format' => 'number'],
                        ['label' => 'Pending', 'value' => $reportData->where('status', 'pending')->count(), 'icon' => 'fa-clock', 'color' => 'gradient-amber', 'format' => 'number'],
                        ['label' => 'Rejected', 'value' => $reportData->where('status', 'rejected')->count(), 'icon' => 'fa-times-circle', 'color' => 'gradient-rose', 'format' => 'number'],
                        ['label' => 'Accepted Amount', 'value' => $reportData->where('status', 'accepted')->sum('verified_amount'), 'icon' => 'fa-hand-holding-usd', 'color' => 'gradient-cyan', 'format' => 'currency'],
                    ];
                    break;

                case 'inventory':
                    $reportTitle = 'Inventory Report';
                    $products = Product::with(['warehouseInventory', 'franchiseInventories.franchise', 'category'])
                        ->where('is_active', true)->orderBy('name')->get();

                    $summary = [
                        ['label' => 'Total Products', 'value' => $products->count(), 'icon' => 'fa-boxes-stacked', 'color' => 'gradient-indigo', 'format' => 'number'],
                        ['label' => 'Warehouse Stock', 'value' => $products->sum(fn ($p) => $p->warehouseInventory?->quantity ?? 0), 'icon' => 'fa-warehouse', 'color' => 'gradient-green', 'format' => 'number'],
                        ['label' => 'Low Stock Items', 'value' => $products->filter(fn ($p) => ($p->warehouseInventory?->quantity ?? 0) <= ($p->warehouseInventory?->reorder_level ?? 0) && ($p->warehouseInventory?->quantity ?? 0) > 0)->count(), 'icon' => 'fa-exclamation-triangle', 'color' => 'gradient-amber', 'format' => 'number'],
                        ['label' => 'Out of Stock', 'value' => $products->filter(fn ($p) => ($p->warehouseInventory?->quantity ?? 0) == 0)->count(), 'icon' => 'fa-times-circle', 'color' => 'gradient-rose', 'format' => 'number'],
                        ['label' => 'Total Stock Value', 'value' => $products->sum(fn ($p) => ($p->warehouseInventory?->quantity ?? 0) * $p->standard_price), 'icon' => 'fa-coins', 'color' => 'gradient-cyan', 'format' => 'currency'],
                        ['label' => 'Franchise Stocks', 'value' => $products->sum(fn ($p) => $p->franchiseInventories->sum('quantity')), 'icon' => 'fa-store', 'color' => 'gradient-purple', 'format' => 'number'],
                    ];
                    $reportData = $products;
                    break;

                case 'franchise':
                    $reportTitle = 'Franchise Performance';
                    $franchiseData = Franchise::withCount(['orders as total_orders' => function ($q) use ($fromDate, $toDate) {
                        $q->whereBetween('created_at', [$fromDate, $toDate]);
                    }])
                        ->withCount(['sales as total_sales' => function ($q) use ($fromDate, $toDate) {
                            $q->whereBetween('sale_date', [$fromDate, $toDate]);
                        }])
                        ->withSum(['sales as sales_revenue' => function ($q) use ($fromDate, $toDate) {
                            $q->whereBetween('sale_date', [$fromDate, $toDate]);
                        }], 'final_amount')
                        ->withSum(['orders as orders_value' => function ($q) use ($fromDate, $toDate) {
                            $q->whereBetween('created_at', [$fromDate, $toDate]);
                        }], 'total_amount')
                        ->withCount(['paymentSubmissions as payments_count' => function ($q) use ($fromDate, $toDate) {
                            $q->whereBetween('created_at', [$fromDate, $toDate]);
                        }])
                        ->where('is_active', true)->orderBy('sales_revenue', 'desc')->get();

                    $reportData = $franchiseData;
                    $summary = [
                        ['label' => 'Active Franchises', 'value' => $franchiseData->count(), 'icon' => 'fa-store', 'color' => 'gradient-indigo', 'format' => 'number'],
                        ['label' => 'Total Orders', 'value' => $franchiseData->sum('total_orders'), 'icon' => 'fa-clipboard-list', 'color' => 'gradient-green', 'format' => 'number'],
                        ['label' => 'Total Sales Revenue', 'value' => $franchiseData->sum('sales_revenue'), 'icon' => 'fa-money-bill-wave', 'color' => 'gradient-amber', 'format' => 'currency'],
                        ['label' => 'Total Orders Value', 'value' => $franchiseData->sum('orders_value'), 'icon' => 'fa-shopping-cart', 'color' => 'gradient-cyan', 'format' => 'currency'],
                        ['label' => 'Total Payments', 'value' => $franchiseData->sum('payments_count'), 'icon' => 'fa-credit-card', 'color' => 'gradient-purple', 'format' => 'number'],
                        ['label' => 'Avg Sales/Franchise', 'value' => $franchiseData->count() ? $franchiseData->avg('sales_revenue') : 0, 'icon' => 'fa-chart-pie', 'color' => 'gradient-rose', 'format' => 'currency'],
                    ];
                    break;

                case 'profit-loss':
                    $reportTitle = 'Profit & Loss Report';
                    $salesQuery = Sale::whereBetween('sale_date', [$fromDate, $toDate]);
                    if ($franchiseId) {
                        $salesQuery->where('franchise_id', $franchiseId);
                    }
                    $totalSales = $salesQuery->sum('final_amount');
                    $totalDiscounts = (clone $salesQuery)->sum('discount');
                    $netRevenue = $totalSales - $totalDiscounts;

                    $costQuery = OrderItem::whereHas('order', function ($q) use ($fromDate, $toDate, $franchiseId) {
                        $q->whereBetween('created_at', [$fromDate, $toDate]);
                        if ($franchiseId) {
                            $q->where('franchise_id', $franchiseId);
                        }
                    });
                    $totalCOGS = $costQuery->sum(\DB::raw('quantity * unit_price'));

                    $netProfit = $netRevenue - $totalCOGS;
                    $margin = $netRevenue > 0 ? ($netProfit / $netRevenue * 100) : 0;

                    $summary = [
                        ['label' => 'Gross Revenue', 'value' => $totalSales, 'icon' => 'fa-money-bill-wave', 'color' => 'gradient-indigo', 'format' => 'currency'],
                        ['label' => 'Discounts', 'value' => $totalDiscounts, 'icon' => 'fa-percent', 'color' => 'gradient-amber', 'format' => 'currency'],
                        ['label' => 'Net Revenue', 'value' => $netRevenue, 'icon' => 'fa-chart-line', 'color' => 'gradient-green', 'format' => 'currency'],
                        ['label' => 'Cost of Goods', 'value' => $totalCOGS, 'icon' => 'fa-truck', 'color' => 'gradient-rose', 'format' => 'currency'],
                        ['label' => 'Net Profit', 'value' => $netProfit, 'icon' => 'fa-piggy-bank', 'color' => $netProfit >= 0 ? 'gradient-green' : 'gradient-rose', 'format' => 'currency'],
                        ['label' => 'Profit Margin', 'value' => round($margin, 1), 'icon' => 'fa-percentage', 'color' => 'gradient-cyan', 'format' => 'percent'],
                    ];
                    break;
            }
        }

        return view('admin.reports', compact('type', 'from', 'to', 'franchiseId', 'franchises', 'reportData', 'summary', 'reportTitle'));
    }

    public function adminAudit()
    {
        $logs = ActivityLog::with('user:id,name,email')->latest()->paginate(50);

        return view('admin.audit', compact('logs'));
    }

    public function adminNews()
    {
        $news = News::latest()->paginate(20);

        return view('admin.news', compact('news'));
    }

    public function adminFaqs()
    {
        $faqs = Faq::orderBy('sort_order')->get();

        return view('admin.faqs', compact('faqs'));
    }

    public function adminSlides()
    {
        $slides = Slide::orderBy('sort_order')->get();

        return view('admin.slides', compact('slides'));
    }

    public function adminPages()
    {
        $pages = Page::latest()->paginate(20);

        return view('admin.pages', compact('pages'));
    }

    public function adminSettings()
    {
        return view('admin.settings');
    }

    public function adminSettingsSite()
    {
        return view('admin.settings-site');
    }

    public function adminSettingsSiteUpdate(Request $request)
    {
        $request->validate([
            'site_name' => 'required|string|max:255',
            'site_tagline' => 'nullable|string|max:255',
            'site_phone' => 'nullable|string|max:30',
            'site_email' => 'nullable|email|max:255',
            'site_address' => 'nullable|string|max:500',
            'site_favicon' => 'nullable|image|mimes:ico,png,jpeg,jpg,webp,svg|max:1024',
            'site_logo' => 'nullable|image|mimes:png,jpeg,jpg,webp,svg|max:2048',
            'og_image' => 'nullable|image|mimes:png,jpeg,jpg,webp|max:2048',
            'theme_accent' => 'nullable|string|max:7',
            'theme_success' => 'nullable|string|max:7',
            'theme_warning' => 'nullable|string|max:7',
            'theme_danger' => 'nullable|string|max:7',
            'theme_info' => 'nullable|string|max:7',
        ]);

        $textFields = ['site_name', 'site_tagline', 'site_phone', 'site_email', 'site_address'];
        foreach ($textFields as $field) {
            if ($request->has($field)) {
                Setting::set($field, $request->input($field, ''), 'site');
            }
        }

        $themeFields = ['theme_accent', 'theme_success', 'theme_warning', 'theme_danger', 'theme_info'];
        foreach ($themeFields as $field) {
            if ($request->has($field)) {
                Setting::set($field, $request->input($field, ''), 'theme');
            }
        }

        if ($request->hasFile('site_favicon')) {
            if ($old = Setting::get('site_favicon')) {
                Storage::disk('public')->delete($old);
            }
            $path = $request->file('site_favicon')->store('site', 'public');
            Setting::set('site_favicon', $path, 'site');
        }

        if ($request->hasFile('site_logo')) {
            if ($old = Setting::get('site_logo')) {
                Storage::disk('public')->delete($old);
            }
            $path = $request->file('site_logo')->store('site', 'public');
            Setting::set('site_logo', $path, 'site');
        }

        if ($request->hasFile('og_image')) {
            if ($old = Setting::get('og_image')) {
                Storage::disk('public')->delete($old);
            }
            $path = $request->file('og_image')->store('site', 'public');
            Setting::set('og_image', $path, 'site');
        }

        cache()->forget('site_settings');

        return redirect()->route('web.admin.settings.site')->with('success', 'Site identity updated successfully!');
    }

    public function adminSettingsUsers()
    {
        $users = User::with(['role', 'franchise'])->latest()->paginate(20);

        return view('admin.settings-users', compact('users'));
    }

    public function adminSettingsRoles()
    {
        $roles = Role::withCount('users')->get();

        return view('admin.settings-roles', compact('roles'));
    }

    public function adminSettingsNotifications()
    {
        return view('admin.settings-notifications');
    }

    public function adminSettingsNotificationsUpdate(Request $request)
    {
        $toggles = [
            'notif_email_new_order', 'notif_email_order_status', 'notif_email_payment_submitted',
            'notif_email_payment_verified', 'notif_email_low_stock', 'notif_email_new_user',
            'notif_email_franchise_deactivated',
            'notif_inapp_badge_counts', 'notif_inapp_toasts', 'notif_inapp_auto_refresh',
        ];
        foreach ($toggles as $key) {
            Setting::set($key, $request->boolean($key) ? '1' : '0', 'notifications');
        }
        $emailFields = ['notif_admin_email', 'notif_finance_email'];
        foreach ($emailFields as $field) {
            Setting::set($field, $request->input($field, ''), 'notifications');
        }
        cache()->forget('site_settings');
        cache()->forget('notif_settings');

        return redirect()->route('web.admin.settings.notifications')->with('success', 'Notification settings saved!');
    }

    public function adminSettingsSystem()
    {
        return view('admin.settings-system');
    }

    // ── Admin: Stock Movements ──────────────────────────────────
    public function adminStockMovements(Request $request)
    {
        $query = StockMovement::with(['product:id,name,sku', 'user:id,name'])
            ->latest();

        if ($request->has('product_id')) {
            $query->where('product_id', $request->product_id);
        }
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }
        if ($request->has('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->has('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        $movements = $query->paginate(30);
        $products = Product::orderBy('name')->get();

        return view('admin.stock-movements', compact('movements', 'products'));
    }

    // ── Admin: Report Export (server-side CSV) ──────────────────
    public function adminReportExport(Request $request)
    {
        $type = $request->get('type', '');
        $from = $request->get('from', now()->startOfMonth()->format('Y-m-d'));
        $to = $request->get('to', now()->format('Y-m-d'));
        $franchiseId = $request->get('franchise_id', '');

        $fromDate = Carbon::parse($from)->startOfDay();
        $toDate = Carbon::parse($to)->endOfDay();

        $rows = [];
        $filename = "report-{$type}-{$from}-{$to}.csv";

        switch ($type) {
            case 'sales':
                $query = Sale::with(['franchise:id,name', 'customer:id,name'])
                    ->whereBetween('sale_date', [$fromDate, $toDate]);
                if ($franchiseId) {
                    $query->where('franchise_id', $franchiseId);
                }
                $sales = $query->latest('sale_date')->get();
                $rows[] = ['Sale No', 'Date', 'Franchise', 'Customer', 'Total', 'Discount', 'Final', 'Method', 'Status'];
                foreach ($sales as $s) {
                    $rows[] = [
                        $s->sale_number,
                        $s->sale_date->format('d M Y'),
                        $s->franchise?->name ?? '',
                        $s->customer?->name ?? 'Walk-in',
                        $s->total_amount,
                        $s->discount,
                        $s->final_amount,
                        $s->payment_method ?? '',
                        $s->payment_status,
                    ];
                }
                break;

            case 'orders':
                $query = Order::with(['franchise:id,name', 'orderedByUser:id,name'])
                    ->whereBetween('created_at', [$fromDate, $toDate]);
                if ($franchiseId) {
                    $query->where('franchise_id', $franchiseId);
                }
                $orders = $query->latest()->get();
                $rows[] = ['Order No', 'Date', 'Franchise', 'Ordered By', 'Items', 'Amount', 'Status'];
                foreach ($orders as $o) {
                    $rows[] = [
                        $o->order_number,
                        $o->created_at->format('d M Y'),
                        $o->franchise?->name ?? '',
                        $o->orderedByUser?->name ?? '',
                        $o->items->count(),
                        $o->total_amount,
                        $o->status,
                    ];
                }
                break;

            case 'payments':
                $query = PaymentSubmission::with('franchise:id,name')
                    ->whereBetween('submitted_at', [$fromDate, $toDate]);
                if ($franchiseId) {
                    $query->where('franchise_id', $franchiseId);
                }
                $payments = $query->latest('submitted_at')->get();
                $rows[] = ['Payment No', 'Date', 'Franchise', 'Amount', 'Method', 'Reference', 'Status'];
                foreach ($payments as $p) {
                    $rows[] = [
                        $p->payment_number,
                        $p->submitted_at?->format('d M Y') ?? $p->created_at->format('d M Y'),
                        $p->franchise?->name ?? '',
                        $p->amount,
                        $p->payment_method ?? '',
                        $p->transaction_reference ?? '',
                        $p->status,
                    ];
                }
                break;

            case 'inventory':
                $products = Product::with(['warehouseInventory', 'category'])
                    ->where('is_active', true)->orderBy('name')->get();
                $rows[] = ['Product', 'SKU', 'Category', 'Unit Price', 'Warehouse Qty', 'Reorder Level', 'Stock Value', 'Status'];
                foreach ($products as $p) {
                    $qty = $p->warehouseInventory?->quantity ?? 0;
                    $reorder = $p->warehouseInventory?->reorder_level ?? 0;
                    $rows[] = [
                        $p->name,
                        $p->sku,
                        $p->category?->name ?? '',
                        $p->standard_price,
                        $qty,
                        $reorder,
                        $qty * $p->standard_price,
                        $qty == 0 ? 'Out of Stock' : ($qty <= $reorder ? 'Low Stock' : 'In Stock'),
                    ];
                }
                break;

            case 'stock-movements':
                $query = StockMovement::with(['product:id,name,sku', 'user:id,name'])->latest();
                if ($request->has('product_id')) {
                    $query->where('product_id', $request->product_id);
                }
                if ($request->has('movement_type')) {
                    $query->where('type', $request->movement_type);
                }
                $movements = $query->get();
                $rows[] = ['Date', 'Product', 'SKU', 'Type', 'Quantity', 'Unit Price', 'Total Value', 'Notes', 'User'];
                foreach ($movements as $m) {
                    $rows[] = [
                        $m->created_at->format('d M Y H:i'),
                        $m->product?->name ?? '',
                        $m->product?->sku ?? '',
                        $m->type,
                        $m->quantity,
                        $m->unit_price,
                        $m->total_value,
                        $m->notes ?? '',
                        $m->user?->name ?? '',
                    ];
                }
                break;
        }

        $callback = function () use ($rows) {
            $handle = fopen('php://output', 'w');
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    // ── Admin: Password Reset ──────────────────────────────────
    public function adminResetPassword(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::findOrFail($request->user_id);
        $user->update(['password' => Hash::make($request->new_password)]);

        return back()->with('success', "Password reset for {$user->name} successfully!");
    }

    // ── Staff Pages ──────────────────────────────────────────
    public function staffOrders()
    {
        $orders = Order::with(['franchise', 'items.product', 'orderedByUser'])->latest()->paginate(20);

        return view('staff.orders', compact('orders'));
    }

    public function staffInventory()
    {
        $stock = WarehouseInventory::with('product:id,name,sku,unit_of_measure')->get();

        return view('staff.inventory', compact('stock'));
    }

    public function staffFranchiseStock()
    {
        $stocks = FranchiseInventory::with(['franchise:id,name,code', 'product:id,name,sku,unit_of_measure,selling_price'])->get();

        return view('staff.franchise-stock', compact('stocks'));
    }

    // ── Finance Pages ────────────────────────────────────────
    public function financePayments()
    {
        $payments = PaymentSubmission::with('franchise')->latest('submitted_at')->paginate(20);

        return view('finance.payments', compact('payments'));
    }

    public function financeReports()
    {
        $now = now();
        $monthSql = fn (string $column) => DB::getDriverName() === 'sqlite'
            ? "strftime('%m', {$column})"
            : "MONTH({$column})";
        $data = [
            'total_collected_ytd' => PaymentSubmission::where('status', 'accepted')->whereYear('accepted_at', $now->year)->sum('verified_amount'),
            'total_collected_month' => PaymentSubmission::where('status', 'accepted')->whereMonth('accepted_at', $now->month)->sum('verified_amount'),
            'total_outstanding' => Franchise::where('account_balance', '>', 0)->sum('account_balance'),
            'monthly_collections' => PaymentSubmission::where('status', 'accepted')->select(DB::raw("{$monthSql('accepted_at')} as month"), DB::raw('SUM(verified_amount) as total'))->whereYear('accepted_at', $now->year)->groupBy(DB::raw($monthSql('accepted_at')))->orderBy('month')->get(),
            'monthly_reconciliation' => collect(),
        ];

        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthStart = Carbon::create($now->year, $m, 1)->startOfMonth();
            $monthEnd = $monthStart->copy()->endOfMonth();

            if ($monthEnd->isAfter(now())) {
                break;
            }

            $submitted = PaymentSubmission::whereMonth('submitted_at', $m)
                ->whereYear('submitted_at', $now->year)->sum('amount');
            $accepted = PaymentSubmission::where('status', 'accepted')
                ->whereMonth('accepted_at', $m)->whereYear('accepted_at', $now->year)->sum('verified_amount');
            $rejected = PaymentSubmission::where('status', 'rejected')
                ->whereMonth('updated_at', $m)->whereYear('updated_at', $now->year)->sum('amount');
            $pending = PaymentSubmission::where('status', 'pending')
                ->whereMonth('created_at', $m)->whereYear('created_at', $now->year)->sum('amount');
            $sales = Sale::whereMonth('sale_date', $m)->whereYear('sale_date', $now->year)->sum('final_amount');
            $newOutstanding = Franchise::where('account_balance', '>', 0)->sum('account_balance');

            $months[] = [
                'month' => $monthStart->format('M'),
                'month_num' => $m,
                'submitted' => $submitted,
                'accepted' => $accepted,
                'rejected' => $rejected,
                'pending' => $pending,
                'sales' => $sales,
                'outstanding' => $newOutstanding,
            ];
        }
        $data['monthly_reconciliation'] = collect($months);

        $data['franchise_reconciliation'] = Franchise::where('is_active', true)
            ->withSum(['paymentSubmissions as total_submitted' => fn ($q) => $q->whereYear('created_at', $now->year)], 'amount')
            ->withSum(['paymentSubmissions as total_accepted' => fn ($q) => $q->where('status', 'accepted')->whereYear('accepted_at', $now->year)], 'verified_amount')
            ->withCount(['paymentSubmissions as total_payments'])
            ->withSum(['sales as total_sales' => fn ($q) => $q->whereYear('sale_date', $now->year)], 'final_amount')
            ->get();

        return view('finance.reports', compact('data'));
    }

    // ── Franchise Pages ──────────────────────────────────────
    public function franchiseOrders()
    {
        $orders = Order::forFranchise(auth()->user()->franchise_id)->with('items.product')->latest()->paginate(20);

        return view('franchise.orders', compact('orders'));
    }

    public function franchiseSales()
    {
        $sales = Sale::where('franchise_id', auth()->user()->franchise_id)->with(['customer', 'items.product'])->latest()->paginate(20);

        return view('franchise.sales', compact('sales'));
    }

    public function franchiseInventory()
    {
        $inventory = FranchiseInventory::where('franchise_id', auth()->user()->franchise_id)->with('product:id,name,sku,unit_of_measure,selling_price')->get();

        return view('franchise.inventory', compact('inventory'));
    }

    public function franchisePayments()
    {
        $payments = PaymentSubmission::where('franchise_id', auth()->user()->franchise_id)->latest('submitted_at')->paginate(20);

        return view('franchise.payments', compact('payments'));
    }

    public function franchiseChat()
    {
        return view('franchise.chat');
    }

    // ── Profile ─────────────────────────────────────────────
    public function profile()
    {
        $user = auth()->user()->load(['role', 'franchise', 'branch']);

        return view('profile', compact('user'));
    }

    public function profileUpdate(Request $request)
    {
        $user = auth()->user();
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,'.$user->id,
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'gender' => 'nullable|in:male,female,other',
            'date_of_birth' => 'nullable|date|before:today',
        ]);
        $user->update($validated);

        return redirect()->route('web.profile')->with('success', 'Profile updated successfully!');
    }

    public function profilePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password' => 'required|string|min:8|confirmed',
        ]);
        $user = auth()->user();
        if (! Hash::check($request->current_password, $user->password)) {
            return back()->withErrors(['current_password' => 'The current password is incorrect.']);
        }
        $user->update(['password' => Hash::make($request->password)]);

        return redirect()->route('web.profile')->with('success', 'Password changed successfully!');
    }

    public function profileAvatar(Request $request)
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);
        $user = auth()->user();
        if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }
        $path = $request->file('avatar')->store('avatars', 'public');
        $user->update(['avatar' => $path]);

        return redirect()->route('web.profile')->with('success', 'Profile picture updated!');
    }

    // ── Admin CRUD: Products ──────────────────────────────────
    public function adminStoreProduct(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'required|string|max:50|unique:products,sku',
            'category_id' => 'required|exists:categories,id',
            'unit_of_measure' => 'required|string|max:50',
            'selling_price' => 'required|numeric|min:0',
            'standard_price' => 'required|numeric|min:0',
            'packaging_details' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'tax_enabled' => 'nullable|boolean',
            'tax_type' => 'nullable|string|in:percentage,fixed',
            'tax_rate' => 'nullable|numeric|min:0',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'images.*' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);
        $data = $request->only('name', 'sku', 'category_id', 'unit_of_measure', 'selling_price', 'standard_price', 'packaging_details', 'description');

        $taxEnabled = $request->boolean('tax_enabled');
        $taxType = $request->input('tax_type', 'percentage');
        $taxRate = (float) $request->input('tax_rate', 0);
        $standardPrice = (float) $request->input('standard_price', 0);
        $taxAmount = 0.00;
        if ($taxEnabled) {
            $taxAmount = ($taxType === 'percentage') ? round($standardPrice * ($taxRate / 100), 2) : $taxRate;
        }
        $finalPrice = round($standardPrice + $taxAmount, 2);

        $data['tax_enabled'] = $taxEnabled;
        $data['tax_type'] = $taxType;
        $data['tax_rate'] = $taxRate;
        $data['tax_amount'] = $taxAmount;
        $data['base_price'] = $standardPrice;
        $data['final_price'] = $finalPrice;

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('products', 'public');
        }

        $product = Product::create($data);

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $idx => $file) {
                if ($file && $file->isValid()) {
                    $path = $file->store('products', 'public');
                    if (empty($product->image) && $idx === 0) {
                        $product->update(['image' => $path]);
                    } else {
                        $product->images()->create([
                            'image_path' => $path,
                            'sort_order' => $idx,
                        ]);
                    }
                }
            }
        }

        // Create or update warehouse inventory record
        $initialStock = (float) ($request->input('initial_stock', 0));
        $reorderLevel = (float) ($request->input('reorder_level', 0));
        WarehouseInventory::updateOrCreate(
            ['product_id' => $product->id],
            [
                'quantity' => $initialStock,
                'reserved_quantity' => 0,
                'reorder_level' => $reorderLevel,
                'last_restocked_at' => $initialStock > 0 ? now() : null,
            ]
        );

        return redirect()->route('web.admin.products')->with('success', 'Product created successfully!');
    }

    public function adminDeleteProduct(Request $request)
    {
        $request->validate(['id' => 'required|exists:products,id']);
        $product = Product::with('images')->findOrFail($request->id);
        if ($product->image) {
            Storage::disk('public')->delete($product->image);
        }
        foreach ($product->images as $img) {
            if ($img->image_path) {
                Storage::disk('public')->delete($img->image_path);
            }
        }
        $product->delete();

        return redirect()->route('web.admin.products')->with('success', 'Product deleted.');
    }

    public function adminDeleteProductImage(Request $request)
    {
        $request->validate(['id' => 'required|exists:product_images,id']);
        $img = ProductImage::findOrFail($request->id);
        if ($img->image_path) {
            Storage::disk('public')->delete($img->image_path);
        }
        $img->delete();

        return back()->with('success', 'Image removed.');
    }

    public function adminUpdateProduct(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:products,id',
            'name' => 'required|string|max:255',
            'sku' => 'required|string|max:50|unique:products,sku,'.$request->id,
            'category_id' => 'required|exists:categories,id',
            'unit_of_measure' => 'required|string|max:50',
            'selling_price' => 'required|numeric|min:0',
            'standard_price' => 'required|numeric|min:0',
            'packaging_details' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'tax_enabled' => 'nullable|boolean',
            'tax_type' => 'nullable|string|in:percentage,fixed',
            'tax_rate' => 'nullable|numeric|min:0',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'images.*' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'is_active' => 'nullable|boolean',
            'delete_images' => 'nullable|array',
            'delete_images.*' => 'integer|exists:product_images,id',
        ]);
        $product = Product::findOrFail($request->id);
        $data = $request->only('name', 'sku', 'category_id', 'unit_of_measure', 'selling_price', 'standard_price', 'packaging_details', 'description');
        $data['is_active'] = $request->has('is_active') ? (bool) $request->is_active : $product->is_active;

        $taxEnabled = $request->boolean('tax_enabled');
        $taxType = $request->input('tax_type', 'percentage');
        $taxRate = (float) $request->input('tax_rate', 0);
        $standardPrice = (float) $request->input('standard_price', $product->standard_price);
        $taxAmount = 0.00;
        if ($taxEnabled) {
            $taxAmount = ($taxType === 'percentage') ? round($standardPrice * ($taxRate / 100), 2) : $taxRate;
        }
        $finalPrice = round($standardPrice + $taxAmount, 2);

        $data['tax_enabled'] = $taxEnabled;
        $data['tax_type'] = $taxType;
        $data['tax_rate'] = $taxRate;
        $data['tax_amount'] = $taxAmount;
        $data['base_price'] = $standardPrice;
        $data['final_price'] = $finalPrice;

        if ($request->hasFile('image')) {
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }
            $data['image'] = $request->file('image')->store('products', 'public');
        }

        $product->update($data);

        if ($request->filled('delete_images')) {
            $imagesToDelete = ProductImage::where('product_id', $product->id)
                ->whereIn('id', $request->delete_images)
                ->get();

            foreach ($imagesToDelete as $img) {
                if ($img->image_path) {
                    Storage::disk('public')->delete($img->image_path);
                }
                $img->delete();
            }
        }

        if ($request->hasFile('images')) {
            $maxSort = $product->images()->max('sort_order') ?? 0;
            foreach ($request->file('images') as $idx => $file) {
                if ($file && $file->isValid()) {
                    $path = $file->store('products', 'public');
                    if (empty($product->image) && $idx === 0) {
                        $product->update(['image' => $path]);
                    } else {
                        $product->images()->create([
                            'image_path' => $path,
                            'sort_order' => $maxSort + $idx + 1,
                        ]);
                    }
                }
            }
        }

        // Update warehouse inventory if admin supplied a new quantity
        if ($request->filled('warehouse_qty')) {
            WarehouseInventory::updateOrCreate(
                ['product_id' => $product->id],
                [
                    'quantity' => (float) $request->input('warehouse_qty'),
                    'reorder_level' => (float) ($request->input('reorder_level', 0)),
                    'last_restocked_at' => now(),
                ]
            );
        } elseif ($request->filled('reorder_level')) {
            WarehouseInventory::updateOrCreate(
                ['product_id' => $product->id],
                ['reorder_level' => (float) $request->input('reorder_level')]
            );
        }

        return redirect()->route('web.admin.products')->with('success', 'Product updated successfully!');
    }

    // ── Admin CRUD: Users ──────────────────────────────────
    public function adminStoreUser(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
            'role_id' => 'required|exists:roles,id',
            'franchise_id' => 'nullable|exists:franchises,id',
            'phone' => 'nullable|string|max:20',
        ]);
        $data = $request->only('name', 'email', 'role_id', 'franchise_id', 'phone');
        $data['password'] = Hash::make($request->password);
        $data['is_active'] = true;
        User::create($data);

        return redirect()->route('web.admin.users')->with('success', 'User created successfully!');
    }

    public function adminDeleteUser(Request $request)
    {
        $request->validate(['id' => 'required|exists:users,id']);
        if (auth()->id() == $request->id) {
            return back()->with('error', 'You cannot delete your own account.');
        }
        User::destroy($request->id);

        return redirect()->route('web.admin.users')->with('success', 'User deleted.');
    }

    public function adminUpdateUser(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:users,id',
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,'.$request->id,
            'role_id' => 'required|exists:roles,id',
            'franchise_id' => 'nullable|exists:franchises,id',
            'phone' => 'nullable|string|max:20',
            'password' => 'nullable|string|min:6|confirmed',
        ]);
        $user = User::findOrFail($request->id);
        $data = $request->only('name', 'email', 'role_id', 'franchise_id', 'phone');
        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }
        $user->update($data);

        return redirect()->route('web.admin.users')->with('success', 'User updated successfully!');
    }

    // ── Admin CRUD: Categories ──────────────────────────────────
    public function adminStoreCategory(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
        ]);
        $data = $request->only('name', 'description', 'sort_order');
        $data['slug'] = $request->slug ?: Str::slug($request->name);
        $data['is_active'] = true;
        Category::create($data);

        return redirect()->route('web.admin.categories')->with('success', 'Category created!');
    }

    public function adminDeleteCategory(Request $request)
    {
        $request->validate(['id' => 'required|exists:categories,id']);
        Category::destroy($request->id);

        return redirect()->route('web.admin.categories')->with('success', 'Category deleted.');
    }

    public function adminUpdateCategory(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
        ]);
        $category = Category::findOrFail($request->id);
        $data = $request->only('name', 'description', 'sort_order');
        $data['slug'] = Str::slug($request->name);
        $category->update($data);

        return redirect()->route('web.admin.categories')->with('success', 'Category updated!');
    }

    // ── Admin: Order Actions ──────────────────────────────────
    public function adminApproveOrder(Request $request, $id)
    {
        $order = Order::findOrFail($id);
        if ($order->status !== 'pending') {
            return back()->with('error', 'Only pending orders can be approved.');
        }
        $order->update(['status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => now()]);

        return back()->with('success', "Order {$order->order_number} approved!");
    }

    public function adminDeclineOrder(Request $request, $id)
    {
        $request->validate(['decline_reason' => 'required|string|max:500']);
        $order = Order::findOrFail($id);
        if ($order->status !== 'pending') {
            return back()->with('error', 'Only pending orders can be declined.');
        }
        $order->update(['status' => 'declined', 'approved_by' => auth()->id(), 'approved_at' => now(), 'decline_reason' => $request->decline_reason]);

        return back()->with('success', "Order {$order->order_number} declined.");
    }

    // ── Admin: Payment Actions ──────────────────────────────────
    public function adminAcceptPayment(Request $request, $id)
    {
        $payment = PaymentSubmission::findOrFail($id);
        if ($payment->status !== 'pending') {
            return back()->with('error', 'Only pending payments can be accepted.');
        }
        $payment->update([
            'status' => 'accepted',
            'accepted_by' => auth()->id(),
            'accepted_at' => now(),
            'verified_amount' => $payment->amount,
        ]);
        $franchise = $payment->franchise;
        if ($franchise) {
            $franchise->update(['account_balance' => $franchise->account_balance + $payment->amount]);
        }

        return back()->with('success', "Payment {$payment->payment_number} accepted!");
    }

    public function adminRejectPayment(Request $request, $id)
    {
        $request->validate(['rejection_reason' => 'required|string|max:500']);
        $payment = PaymentSubmission::findOrFail($id);
        if ($payment->status !== 'pending') {
            return back()->with('error', 'Only pending payments can be rejected.');
        }
        $payment->update(['status' => 'rejected', 'rejection_reason' => $request->rejection_reason]);

        return back()->with('success', "Payment {$payment->payment_number} rejected.");
    }

    // ── Admin CRUD: News ──────────────────────────────────
    public function adminStoreNews(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'excerpt' => 'nullable|string',
            'body' => 'nullable|string',
        ]);
        $data = $request->only('title', 'excerpt', 'body');
        $data['slug'] = Str::slug($request->title);
        $data['is_published'] = true;
        $data['published_at'] = now();
        News::create($data);

        return redirect()->route('web.admin.news')->with('success', 'Article published!');
    }

    public function adminDeleteNews(Request $request)
    {
        $request->validate(['id' => 'required|exists:news,id']);
        News::destroy($request->id);

        return redirect()->route('web.admin.news')->with('success', 'Article deleted.');
    }

    public function adminUpdateNews(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:news,id',
            'title' => 'required|string|max:255',
            'excerpt' => 'nullable|string',
            'body' => 'nullable|string',
            'is_published' => 'nullable|boolean',
        ]);
        $news = News::findOrFail($request->id);
        $data = $request->only('title', 'excerpt', 'body');
        $data['slug'] = Str::slug($request->title);
        $data['is_published'] = $request->has('is_published') ? (bool) $request->is_published : $news->is_published;
        $news->update($data);

        return redirect()->route('web.admin.news')->with('success', 'Article updated!');
    }

    // ── Admin CRUD: FAQs ──────────────────────────────────
    public function adminStoreFaq(Request $request)
    {
        $request->validate([
            'question' => 'required|string|max:500',
            'answer' => 'required|string',
            'sort_order' => 'nullable|integer|min:0',
        ]);
        $data = $request->only('question', 'answer', 'sort_order');
        $data['is_active'] = true;
        Faq::create($data);

        return redirect()->route('web.admin.faqs')->with('success', 'FAQ added!');
    }

    public function adminDeleteFaq(Request $request)
    {
        $request->validate(['id' => 'required|exists:faqs,id']);
        Faq::destroy($request->id);

        return redirect()->route('web.admin.faqs')->with('success', 'FAQ deleted.');
    }

    public function adminUpdateFaq(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:faqs,id',
            'question' => 'required|string|max:500',
            'answer' => 'required|string',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);
        $faq = Faq::findOrFail($request->id);
        $data = $request->only('question', 'answer', 'sort_order');
        $data['is_active'] = $request->has('is_active') ? (bool) $request->is_active : $faq->is_active;
        $faq->update($data);

        return redirect()->route('web.admin.faqs')->with('success', 'FAQ updated!');
    }

    // ── Admin CRUD: Slides ──────────────────────────────────
    public function adminStoreSlide(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'button_text' => 'nullable|string|max:100',
            'button_url' => 'nullable|url',
            'sort_order' => 'nullable|integer|min:0',
        ]);
        $data = $request->only('title', 'subtitle', 'button_text', 'button_url', 'sort_order');
        $data['is_active'] = true;
        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('slides', 'public');
        }
        Slide::create($data);

        return redirect()->route('web.admin.slides')->with('success', 'Slide created!');
    }

    public function adminDeleteSlide(Request $request)
    {
        $request->validate(['id' => 'required|exists:slides,id']);
        Slide::destroy($request->id);

        return redirect()->route('web.admin.slides')->with('success', 'Slide deleted.');
    }

    public function adminUpdateSlide(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:slides,id',
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'button_text' => 'nullable|string|max:100',
            'button_url' => 'nullable|url',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);
        $slide = Slide::findOrFail($request->id);
        $data = $request->only('title', 'subtitle', 'button_text', 'button_url', 'sort_order');
        $data['is_active'] = $request->has('is_active') ? (bool) $request->is_active : $slide->is_active;
        if ($request->hasFile('image')) {
            if ($slide->image) {
                Storage::disk('public')->delete($slide->image);
            }
            $data['image'] = $request->file('image')->store('slides', 'public');
        }
        $slide->update($data);

        return redirect()->route('web.admin.slides')->with('success', 'Slide updated!');
    }

    // ── Admin CRUD: Pages ──────────────────────────────────
    public function adminStorePage(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'nullable|string',
            'meta_description' => 'nullable|string|max:500',
        ]);
        $data = $request->only('title', 'body', 'meta_description');
        $data['slug'] = Str::slug($request->title);
        $data['is_published'] = true;
        Page::create($data);

        return redirect()->route('web.admin.pages')->with('success', 'Page published!');
    }

    public function adminDeletePage(Request $request)
    {
        $request->validate(['id' => 'required|exists:pages,id']);
        Page::destroy($request->id);

        return redirect()->route('web.admin.pages')->with('success', 'Page deleted.');
    }

    public function adminUpdatePage(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:pages,id',
            'title' => 'required|string|max:255',
            'body' => 'nullable|string',
            'meta_description' => 'nullable|string|max:500',
            'is_published' => 'nullable|boolean',
        ]);
        $page = Page::findOrFail($request->id);
        $data = $request->only('title', 'body', 'meta_description');
        $data['slug'] = Str::slug($request->title);
        $data['is_published'] = $request->has('is_published') ? (bool) $request->is_published : $page->is_published;
        $page->update($data);

        return redirect()->route('web.admin.pages')->with('success', 'Page updated!');
    }

    // ── Staff: Order Actions ──────────────────────────────────
    public function staffApproveOrder(Request $request, $id)
    {
        $order = Order::findOrFail($id);
        if ($order->status !== 'pending') {
            return back()->with('error', 'Only pending orders can be approved.');
        }
        $order->update(['status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => now()]);

        return back()->with('success', "Order {$order->order_number} approved!");
    }

    public function staffDeclineOrder(Request $request, $id)
    {
        $request->validate(['decline_reason' => 'required|string|max:500']);
        $order = Order::findOrFail($id);
        if ($order->status !== 'pending') {
            return back()->with('error', 'Only pending orders can be declined.');
        }
        $order->update(['status' => 'declined', 'approved_by' => auth()->id(), 'approved_at' => now(), 'decline_reason' => $request->decline_reason]);

        return back()->with('success', "Order {$order->order_number} declined.");
    }

    // ── Finance: Payment Actions ──────────────────────────────────
    public function financeAcceptPayment(Request $request, $id)
    {
        $payment = PaymentSubmission::findOrFail($id);
        if ($payment->status !== 'pending') {
            return back()->with('error', 'Only pending payments can be accepted.');
        }
        $payment->update([
            'status' => 'accepted',
            'accepted_by' => auth()->id(),
            'accepted_at' => now(),
            'verified_amount' => $payment->amount,
        ]);
        $franchise = $payment->franchise;
        if ($franchise) {
            $franchise->update(['account_balance' => $franchise->account_balance + $payment->amount]);
        }

        return back()->with('success', "Payment {$payment->payment_number} accepted!");
    }

    public function financeRejectPayment(Request $request, $id)
    {
        $request->validate(['rejection_reason' => 'required|string|max:500']);
        $payment = PaymentSubmission::findOrFail($id);
        if ($payment->status !== 'pending') {
            return back()->with('error', 'Only pending payments can be rejected.');
        }
        $payment->update(['status' => 'rejected', 'rejection_reason' => $request->rejection_reason]);

        return back()->with('success', "Payment {$payment->payment_number} rejected.");
    }

    // ── Admin: Franchise CRUD ──────────────────────────────────
    public function adminStoreFranchise(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:20|unique:franchises,code',
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'region' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:500',
            'credit_limit' => 'nullable|numeric|min:0',
        ]);
        $data = $request->only('name', 'code', 'contact_person', 'phone', 'email', 'region', 'address', 'credit_limit');
        $data['is_active'] = true;
        $data['account_balance'] = 0;
        Franchise::create($data);

        return redirect()->route('web.admin.franchises')->with('success', 'Franchise created successfully!');
    }

    public function adminDeleteFranchise(Request $request)
    {
        $request->validate(['id' => 'required|exists:franchises,id']);
        $franchise = Franchise::findOrFail($request->id);
        if ($franchise->users()->count() > 0) {
            return back()->with('error', 'Cannot delete franchise with linked users. Remove users first.');
        }
        $franchise->delete();

        return redirect()->route('web.admin.franchises')->with('success', 'Franchise deleted.');
    }

    public function adminUpdateFranchise(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:franchises,id',
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:20|unique:franchises,code,'.$request->id,
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'region' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:500',
            'credit_limit' => 'nullable|numeric|min:0',
        ]);
        $franchise = Franchise::findOrFail($request->id);
        $franchise->update($request->only('name', 'code', 'contact_person', 'phone', 'email', 'region', 'address', 'credit_limit'));

        return redirect()->route('web.admin.franchises')->with('success', 'Franchise updated successfully!');
    }

    public function adminToggleFranchise(Request $request)
    {
        $request->validate(['id' => 'required|exists:franchises,id']);
        $franchise = Franchise::findOrFail($request->id);
        $franchise->update(['is_active' => ! $franchise->is_active]);
        $status = $franchise->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "Franchise {$status} successfully!");
    }

    // ── Admin: User Toggle ──────────────────────────────────
    public function adminToggleUser(Request $request)
    {
        $request->validate(['id' => 'required|exists:users,id']);
        if (auth()->id() == $request->id) {
            return back()->with('error', 'You cannot deactivate your own account.');
        }
        $user = User::findOrFail($request->id);
        $user->update(['is_active' => ! $user->is_active]);
        $status = $user->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "User {$status} successfully!");
    }

    // ── Admin: Price Slab CRUD ──────────────────────────────────
    public function adminStorePriceSlab(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'min_qty' => 'required|integer|min:1',
            'max_qty' => 'nullable|integer|min:1|gte:min_qty',
            'slab_price' => 'required|numeric|min:0',
        ]);
        PriceSlab::create([
            'product_id' => $request->product_id,
            'min_quantity' => $request->min_qty,
            'max_quantity' => $request->max_qty,
            'slab_price' => $request->slab_price,
        ]);

        return back()->with('success', 'Price slab added!');
    }

    public function adminDeletePriceSlab(Request $request)
    {
        $request->validate(['id' => 'required|exists:price_slabs,id']);
        PriceSlab::destroy($request->id);

        return back()->with('success', 'Price slab removed.');
    }

    public function adminUpdatePriceSlab(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:price_slabs,id',
            'product_id' => 'required|exists:products,id',
            'min_qty' => 'required|integer|min:1',
            'max_qty' => 'nullable|integer|min:1|gte:min_qty',
            'slab_price' => 'required|numeric|min:0',
        ]);
        $slab = PriceSlab::findOrFail($request->id);
        $slab->update([
            'product_id' => $request->product_id,
            'min_quantity' => $request->min_qty,
            'max_quantity' => $request->max_qty,
            'slab_price' => $request->slab_price,
        ]);

        return back()->with('success', 'Price slab updated!');
    }

    // ── Admin: Sales Targets CRUD ──────────────────────────────
    public function adminStoreSalesTarget(Request $request)
    {
        $request->validate([
            'franchise_id' => 'required|exists:franchises,id',
            'product_category_id' => 'nullable|exists:categories,id',
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2024',
            'target_amount' => 'required|numeric|min:0',
        ]);
        SalesTarget::create($request->only('franchise_id', 'product_category_id', 'month', 'year', 'target_amount'));

        return back()->with('success', 'Sales target set!');
    }

    public function adminDeleteSalesTarget(Request $request)
    {
        $request->validate(['id' => 'required|exists:sales_targets,id']);
        SalesTarget::destroy($request->id);

        return back()->with('success', 'Sales target removed.');
    }

    public function adminUpdateSalesTarget(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:sales_targets,id',
            'franchise_id' => 'required|exists:franchises,id',
            'product_category_id' => 'nullable|exists:categories,id',
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2024',
            'target_amount' => 'required|numeric|min:0',
        ]);
        $target = SalesTarget::findOrFail($request->id);
        $target->update($request->only('franchise_id', 'product_category_id', 'month', 'year', 'target_amount'));

        return back()->with('success', 'Sales target updated!');
    }

    // ── Staff: Stock Update ──────────────────────────────────
    public function staffUpdateWarehouseStock(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|numeric|min:0',
            'reorder_level' => 'nullable|numeric|min:0',
        ]);

        $existing = WarehouseInventory::where('product_id', $request->product_id)->first();
        $previousQuantity = $existing?->quantity ?? 0;
        $quantityChange = $request->quantity - $previousQuantity;

        $inventory = WarehouseInventory::updateOrCreate(
            ['product_id' => $request->product_id],
            [
                'quantity' => $request->quantity,
                'reorder_level' => $request->reorder_level ?? 0,
                'last_restocked_at' => now(),
            ]
        );

        if ($quantityChange != 0) {
            StockMovement::log(
                $quantityChange > 0 ? 'warehouse_in' : 'adjustment',
                $request->product_id,
                $quantityChange,
                0,
                WarehouseInventory::class,
                $inventory->id,
                'Manual stock update by staff',
                auth()->id()
            );
        }

        return back()->with('success', 'Warehouse stock updated successfully!');
    }

    // ── Staff: Stock Receipts ──────────────────────────────────
    public function staffStockReceipts()
    {
        $receipts = StockReceipt::with(['franchise:id,name,code', 'items.product:id,name,sku', 'order:id,order_number'])
            ->latest()
            ->paginate(20);

        return view('staff.stock-receipts', compact('receipts'));
    }

    // ── Franchise: Place Order ──────────────────────────────────
    public function franchisePlaceOrder(Request $request)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'notes' => 'nullable|string|max:500',
        ]);

        $user = auth()->user();

        $order = DB::transaction(function () use ($request, $user) {
            $order = Order::create([
                'order_number' => Order::generateOrderNumber(),
                'franchise_id' => $user->franchise_id,
                'ordered_by' => $user->id,
                'status' => 'pending',
                'notes' => $request->notes,
            ]);

            $totalAmount = 0;
            foreach ($request->items as $item) {
                $product = Product::findOrFail($item['product_id']);
                $quantity = $item['quantity'];
                $unitPrice = $product->getBestPrice($quantity);
                $subtotal = $quantity * $unitPrice;
                $order->items()->create([
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'original_unit_price' => $product->standard_price,
                    'subtotal' => $subtotal,
                ]);
                $totalAmount += $subtotal;
            }
            $order->update(['total_amount' => $totalAmount]);

            return $order;
        });

        ActivityLogger::orderPlaced($order);

        return redirect()->route('web.franchise.orders')->with('success', "Order {$order->order_number} placed successfully!");
    }

    // ── Franchise: Create Sale ──────────────────────────────────
    public function franchiseCreateSale(Request $request)
    {
        $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:cash,mobile_money,bank_transfer,credit',
            'discount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        $user = auth()->user();

        $sale = DB::transaction(function () use ($request, $user) {
            $totalAmount = 0;
            foreach ($request->items as $item) {
                $inventory = FranchiseInventory::where('franchise_id', $user->franchise_id)
                    ->where('product_id', $item['product_id'])->first();
                if (! $inventory || $inventory->quantity < $item['quantity']) {
                    return back()->with('error', 'Insufficient stock for one or more products.');
                }
                $unitPrice = $inventory->product->getBestPrice($item['quantity']);
                $totalAmount += $item['quantity'] * $unitPrice;
            }

            $discount = $request->discount ?? 0;
            $finalAmount = $totalAmount - $discount;

            $sale = Sale::create([
                'sale_number' => Sale::generateSaleNumber(),
                'franchise_id' => $user->franchise_id,
                'customer_id' => $request->customer_id,
                'created_by' => $user->id,
                'total_amount' => $totalAmount,
                'discount' => $discount,
                'final_amount' => $finalAmount,
                'payment_method' => $request->payment_method,
                'payment_status' => $request->payment_method === 'credit' ? 'pending' : 'paid',
                'notes' => $request->notes,
                'sale_date' => now()->toDateString(),
            ]);

            foreach ($request->items as $item) {
                $product = Product::find($item['product_id']);
                $unitPrice = $product->getBestPrice($item['quantity']);
                $subtotal = $item['quantity'] * $unitPrice;
                $sale->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                ]);

                $inventory = FranchiseInventory::where('franchise_id', $user->franchise_id)
                    ->where('product_id', $item['product_id'])->first();
                $inventory->quantity -= $item['quantity'];
                $inventory->total_value = $inventory->quantity * $product->standard_price;
                $inventory->save();

                StockMovement::log('franchise_out', $item['product_id'], -$item['quantity'], $unitPrice, Sale::class, $sale->id, "Sale {$sale->sale_number}", $user->id);
            }

            return $sale;
        });

        if (is_array($sale)) {
            return back()->with('error', 'Sale creation failed.');
        }

        ActivityLogger::saleCreated($sale);

        return redirect()->route('web.franchise.sales')->with('success', "Sale {$sale->sale_number} recorded!");
    }

    // ── Franchise: Create Customer ──────────────────────────────
    public function franchiseCreateCustomer(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:500',
            'is_wholesale' => 'nullable|boolean',
        ]);

        $user = auth()->user();
        $data = $request->only('name', 'phone', 'email', 'address');
        $data['franchise_id'] = $user->franchise_id;
        $data['customer_code'] = 'C-'.strtoupper(uniqid());
        $data['is_wholesale'] = $request->boolean('is_wholesale');
        $data['is_active'] = true;
        Customer::create($data);

        return back()->with('success', 'Customer created successfully!');
    }

    // ── Franchise: Submit Payment ──────────────────────────────
    public function franchiseSubmitPayment(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'payment_method' => 'required|in:cash,mobile_money,bank_transfer',
            'transaction_reference' => 'nullable|string|max:100',
            'proof_of_payment' => 'nullable|image|mimes:jpeg,png,jpg,pdf|max:5120',
        ]);

        $user = auth()->user();
        $data = [
            'payment_number' => 'PAY-'.strtoupper(uniqid()),
            'franchise_id' => $user->franchise_id,
            'amount' => $request->amount,
            'payment_method' => $request->payment_method,
            'transaction_reference' => $request->transaction_reference,
            'status' => 'pending',
            'submitted_at' => now(),
        ];

        if ($request->hasFile('proof_of_payment')) {
            $data['proof_of_payment_path'] = $request->file('proof_of_payment')->store('payment-proofs', 'public');
        }

        PaymentSubmission::create($data);

        return redirect()->route('web.franchise.payments')->with('success', 'Payment submitted successfully!');
    }

    // ── Franchise: Chat ──────────────────────────────────────────
    public function franchiseChatMessages(Request $request)
    {
        $user = auth()->user();
        $conversations = Conversation::with(['latestMessage.sender', 'creator'])
            ->where(function ($q) use ($user) {
                $q->where('created_by', $user->id)
                    ->orWhere('franchise_id', $user->franchise_id);
            })
            ->latest()
            ->get();

        if ($request->ajax()) {
            return response()->json($conversations);
        }

        return view('franchise.chat', compact('conversations'));
    }

    public function franchiseChatSend(Request $request)
    {
        $request->validate([
            'conversation_id' => 'required|exists:conversations,id',
            'message' => 'required|string|max:2000',
        ]);

        $user = auth()->user();
        $conversation = Conversation::findOrFail($request->conversation_id);

        if ($user->role?->name === 'Franchise Partner') {
            if ($conversation->franchise_id !== $user->franchise_id && $conversation->created_by !== $user->id) {
                return back()->with('error', 'Unauthorized.');
            }
        }

        $conversation->messages()->create([
            'sender_id' => $user->id,
            'message' => $request->message,
        ]);

        return back()->with('success', 'Message sent!');
    }

    public function franchiseChatCreate(Request $request)
    {
        $request->validate([
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:2000',
        ]);

        $user = auth()->user();
        $conversation = Conversation::create([
            'franchise_id' => $user->franchise_id,
            'created_by' => $user->id,
            'subject' => $request->subject,
            'priority' => 'normal',
            'status' => 'open',
        ]);
        $conversation->messages()->create([
            'sender_id' => $user->id,
            'message' => $request->message,
        ]);

        return back()->with('success', 'Conversation started!');
    }

    // ── Admin Chat System ──────────────────────────────────
    public function adminChat(Request $request)
    {
        $conversations = Conversation::with(['creator:id,name,email', 'franchise:id,name,code', 'latestMessage.sender:id,name'])
            ->latest('updated_at')
            ->get();

        $activeConversation = null;
        $activeId = $request->query('conversation_id');

        if ($activeId) {
            $activeConversation = Conversation::with(['creator:id,name,email', 'franchise:id,name', 'messages.sender:id,name', 'messages.replyTo.sender:id,name'])
                ->findOrFail($activeId);
        } elseif ($conversations->isNotEmpty()) {
            $activeConversation = Conversation::with(['creator:id,name,email', 'franchise:id,name', 'messages.sender:id,name', 'messages.replyTo.sender:id,name'])
                ->find($conversations->first()->id);
        }

        return view('admin.chat', compact('conversations', 'activeConversation'));
    }

    public function adminChatMessages($id)
    {
        $conversation = Conversation::with(['messages.sender:id,name', 'creator:id,name'])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'conversation' => [
                'id' => $conversation->id,
                'subject' => $conversation->subject,
                'status' => $conversation->status,
                'creator_name' => $conversation->creator?->name ?? 'User',
            ],
            'messages' => $conversation->messages->map(function ($msg) {
                return [
                    'id' => $msg->id,
                    'sender_id' => $msg->sender_id,
                    'sender_name' => $msg->sender?->name ?? 'System',
                    'is_admin' => $msg->sender_id === auth()->id() || $msg->sender?->role?->name === 'System Administrator',
                    'message' => $msg->message,
                    'reply_to' => $msg->replyTo ? [
                        'id' => $msg->replyTo->id,
                        'sender_name' => $msg->replyTo->sender?->name ?? 'User',
                        'message' => $msg->replyTo->message,
                    ] : null,
                    'created_at' => $msg->created_at->format('d M Y, h:i A'),
                    'time_ago' => $msg->created_at->diffForHumans(),
                ];
            }),
        ]);
    }

    public function adminChatSend(Request $request, $id)
    {
        $request->validate([
            'message' => 'required|string|max:2000',
            'reply_to_message_id' => 'nullable|integer|exists:messages,id',
        ]);

        $conversation = Conversation::findOrFail($id);
        $user = auth()->user();
        $replyToMessage = null;

        if ($request->filled('reply_to_message_id')) {
            $replyToMessage = $conversation->messages()->findOrFail($request->integer('reply_to_message_id'));
        }

        $message = $conversation->messages()->create([
            'sender_id' => $user->id,
            'message' => $request->message,
            'reply_to_message_id' => $replyToMessage?->id,
        ]);

        $conversation->touch(); // update updated_at

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => [
                    'id' => $message->id,
                    'sender_id' => $user->id,
                    'sender_name' => $user->name,
                    'is_admin' => true,
                    'message' => $message->message,
                    'reply_to' => $replyToMessage ? [
                        'id' => $replyToMessage->id,
                        'sender_name' => $replyToMessage->sender?->name ?? 'User',
                        'message' => $replyToMessage->message,
                    ] : null,
                    'created_at' => $message->created_at->format('d M Y, h:i A'),
                    'time_ago' => $message->created_at->diffForHumans(),
                ],
            ]);
        }

        return back()->with('success', 'Message sent successfully!');
    }
}
