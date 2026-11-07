<?php

namespace App\Http\Controllers\Api\Operations;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Customer;
use App\Models\District;
use App\Models\FieldZone;
use App\Models\Franchise;
use App\Models\FranchiseInventory;
use App\Models\OperationRequest;
use App\Models\Order;
use App\Models\Product;
use App\Models\Region;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SalesTarget;
use App\Models\User;
use App\Services\ReviewService;
use App\Services\TargetProgressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class WorkspaceController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        $actor = $request->user();
        $franchiseId = $actor->isFranchisePartner() ? $actor->franchise_id : null;
        $orders = Order::query()->when($franchiseId, fn ($q) => $q->where('franchise_id', $franchiseId));
        if ($actor->isAgronomist()) {
            $orders->whereRaw('1=0');
        }
        $due = (clone $orders)->whereNotNull('received_at')->where('received_at', '<=', now()->subDays(15))->whereRaw('COALESCE(received_amount, total_amount) > amount_paid')->get(['id', 'order_number', 'franchise_id', 'total_amount', 'amount_paid', 'received_amount', 'received_at']);
        $expenses = OperationRequest::where('type', 'expense')->where('status', 'approved')->whereBetween('entry_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()]);
        $budget = OperationRequest::where('type', 'budget')->whereBetween('entry_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])->sum('amount');
        if (! $actor->isAdmin() && ! $actor->isFinance()) {
            $expenses->where('user_id', $actor->id);
            $budget = null;
        }
        $spent = (float) $expenses->sum('amount');
        $sales = Sale::query()->when($franchiseId, fn ($q) => $q->where('franchise_id', $franchiseId));
        $month = (clone $sales)->whereBetween('sale_date', [now()->startOfMonth(), now()->endOfMonth()]);
        $pending = $actor->isSalesHead() ? 'sales_head' : ($actor->isAdmin() ? 'admin' : 'warehouse');

        return response()->json(['data' => [
            'greeting' => 'Good '.(now()->hour < 12 ? 'morning' : (now()->hour < 17 ? 'afternoon' : 'evening')).', '.$actor->name,
            'monthly_expenses' => $spent, 'monthly_budget' => $budget, 'budget_used_percentage' => $budget > 0 ? round($spent / $budget * 100, 1) : null,
            'monthly_sales' => $actor->isAgronomist() ? null : (float) (clone $month)->sum('final_amount'),
            'monthly_margin' => $actor->isFranchisePartner() ? round((float) (clone $month)->sum('final_amount') - (float) (clone $month)->sum('total_cost'), 2) : null,
            'ytd_sales' => $actor->isAgronomist() ? null : (float) (clone $sales)->whereYear('sale_date', now()->year)->sum('final_amount'),
            'weekly_sales' => $actor->isAgronomist() ? null : (float) (clone $sales)->whereBetween('sale_date', [now()->startOfWeek(), now()->endOfWeek()])->sum('final_amount'),
            'outstanding' => $actor->isFranchisePartner() ? $actor->franchise?->account_balance : ($actor->isAgronomist() ? null : Franchise::sum('account_balance')),
            'stock_value' => $actor->isFranchisePartner() ? FranchiseInventory::where('franchise_id', $franchiseId)->sum('total_value') : null,
            'overdue_orders' => $due->map(fn ($order) => ['id' => $order->id, 'number' => $order->order_number, 'amount' => $order->balance_due, 'days' => (int) $order->received_at->diffInDays(now()), 'due_at' => $order->received_at->copy()->addDays(15)->toDateString()]),
            'pending_order_reviews' => ($actor->isAdmin() || $actor->isSalesHead() || $actor->isWarehouse()) ? (clone $orders)->where('review_stage', $pending)->count() : 0,
            'pending_requests' => ($actor->isAdmin() || $actor->isFinance()) ? OperationRequest::whereIn('status', $actor->isFinance() ? ['submitted', 'approved'] : ['submitted'])->whereIn('type', $actor->isFinance() ? ['expense', 'funds'] : ['funds', 'travel'])->count() : OperationRequest::where('user_id', $actor->id)->where('status', 'submitted')->count(),
            'achievements' => TargetProgressService::summary($actor),
            'updated_at' => now()->toIso8601String(),
        ]]);
    }

    public function reviewOrder(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate(['approve' => 'required|boolean', 'notes' => 'required|string|max:3000']);

        return response()->json(['data' => ReviewService::review($order, $request->user(), $data['approve'], $data['notes'])]);
    }

    public function targets(Request $request): JsonResponse
    {
        $actor = $request->user();
        abort_unless($actor->isAdmin() || $actor->isSalesHead() || $actor->isFranchisePartner(), 403);
        $query = SalesTarget::query();
        if ($actor->isFranchisePartner()) {
            $query->where('franchise_id', $actor->franchise_id);
        }
        if ($actor->isSalesHead()) {
            $query->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', $actor->id));
        }
        $targets = $query->orderByDesc('year')->orderByDesc('month')->paginate(30);
        $progress = TargetProgressService::calculate($targets->getCollection());
        $targets->getCollection()->transform(fn ($target) => [...$target->toArray(), ...$progress[$target->id]]);

        return response()->json($targets);
    }

    public function storeTarget(Request $request): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $data = $request->validate(['franchise_id' => 'required|integer|exists:franchises,id', 'user_id' => 'nullable|integer|exists:users,id', 'product_id' => 'nullable|integer|exists:products,id', 'product_category_id' => 'nullable|integer|exists:categories,id', 'target_amount' => 'required|numeric|gt:0', 'period' => ['required', Rule::in(['weekly', 'monthly', 'yearly'])], 'starts_on' => 'required|date', 'ends_on' => 'required|date|after_or_equal:starts_on']);
        $date = Carbon::parse($data['starts_on']);
        $data['month'] = $date->month;
        $data['year'] = $date->year;
        if (! empty($data['user_id'])) {
            abort_unless(User::findOrFail($data['user_id'])->isSalesHead(), 422, 'Choose a Sales Head for a user target.');
        }

        return response()->json(['data' => SalesTarget::create($data)], 201);
    }

    public function zones(Request $request): JsonResponse
    {
        return response()->json(['data' => FieldZone::where('active', true)->when(! $request->user()->isAdmin(), fn ($q) => $q->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', $request->user()->id)))->get()]);
    }

    public function storeZone(Request $request): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $data = $request->validate(['name' => 'required|string|max:255', 'latitude' => 'required|numeric|between:-90,90', 'longitude' => 'required|numeric|between:-180,180', 'radius_meters' => 'required|integer|between:20,50000', 'user_id' => 'nullable|integer|exists:users,id']);

        return response()->json(['data' => FieldZone::create($data)], 201);
    }

    public function options(Request $request): JsonResponse
    {
        $actor = $request->user();
        abort_unless($actor->isAdmin() || $actor->isSalesHead() || $actor->isFinance() || $actor->isWarehouse(), 403);

        return response()->json(['data' => ['franchises' => Franchise::where('is_active', true)->get(['id', 'name']), 'users' => $actor->isAdmin() ? User::with('role:id,name')->whereHas('role', fn ($q) => $q->whereIn('name', ['Sales Head', 'Field Agronomist']))->get(['id', 'name', 'role_id']) : [], 'products' => Product::where('is_active', true)->get(['id', 'name', 'sku']),
            'roles' => $actor->isAdmin() ? Role::all(['id', 'name']) : [],
            'categories' => Category::all(['id', 'name']),
            'districts' => $actor->isAdmin() ? District::orderBy('name')->get(['id', 'name', 'region_id']) : [],
            'regions' => $actor->isAdmin() ? Region::all(['id', 'name']) : [],
        ]]);
    }

    public function customers(Request $request): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        return response()->json(Customer::with(['franchise:id,name', 'district:id,name'])->latest()->paginate(30));
    }

    public function storeCustomer(Request $request): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $data = $request->validate(['name' => 'required|string|max:255', 'phone' => 'required|string|max:30', 'customer_type' => 'required|in:farmer,retailer', 'franchise_id' => 'required|integer|exists:franchises,id', 'district_id' => 'required|integer|exists:districts,id', 'address' => 'nullable|string|max:1000']);
        $franchise = Franchise::findOrFail($data['franchise_id']);
        $district = District::findOrFail($data['district_id']);
        abort_if($franchise->region_id && $district->region_id !== $franchise->region_id, 422, 'Choose a district within this franchise’s assigned region.');

        return response()->json(['data' => Customer::create([...$data, 'created_by' => $request->user()->id, 'is_active' => true, 'customer_code' => 'CUS-'.Str::upper((string) Str::ulid())])], 201);
    }
}
