<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\District;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    /**
     * Customers (retailers and farmers) the franchise may serve: those
     * assigned to it plus every customer in the districts of its region.
     */
    public function index(Request $request): JsonResponse
    {
        $franchise = $request->user()->franchise;
        $query = Customer::visibleToFranchise($franchise)->active()->with('district:id,name,region_id');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('customer_type')) {
            $query->where('customer_type', $request->customer_type);
        }

        return response()->json(['data' => $query->orderBy('name')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $franchise = $request->user()->franchise;
        $allowedDistricts = $franchise?->region_id
            ? District::where('region_id', $franchise->region_id)->pluck('id')->all()
            : null;

        $request->validate([
            'name' => 'required|string|max:255',
            'customer_type' => 'nullable|string|in:retailer,farmer',
            'district_id' => ['nullable', 'integer', $allowedDistricts !== null ? Rule::in($allowedDistricts) : 'exists:districts,id'],
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email',
            'address' => 'nullable|string',
            'location' => 'nullable|string',
            'is_wholesale' => 'boolean',
        ], [
            'district_id.in' => 'You can only register customers in districts of your region.',
        ]);

        $type = $request->input('customer_type') ?? ($request->boolean('is_wholesale') ? 'retailer' : 'farmer');

        $customer = Customer::create([
            'name' => $request->name,
            'customer_type' => $type,
            'district_id' => $request->district_id,
            'phone' => $request->phone ?? '',
            'email' => $request->email,
            'address' => $request->input('address') ?? $request->input('location'),
            'is_wholesale' => $type === 'retailer',
            'franchise_id' => $franchise->id,
            'customer_code' => 'C-'.strtoupper(uniqid()),
            'created_by' => $request->user()->id,
            'is_active' => true,
        ]);

        return response()->json(['message' => 'Customer created successfully.', 'data' => $customer->load('district:id,name')], 201);
    }

    public function show(Customer $customer): JsonResponse
    {
        $franchise = request()->user()->franchise;

        if (! Customer::visibleToFranchise($franchise)->whereKey($customer->id)->exists()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $customer->loadCount('sales')->load('district:id,name');

        return response()->json(['data' => $customer]);
    }

    /** Districts the franchise may register customers in (its region). */
    public function districts(Request $request): JsonResponse
    {
        $franchise = $request->user()->franchise;
        $query = District::where('is_active', true)->orderBy('name');
        if ($franchise?->region_id) {
            $query->where('region_id', $franchise->region_id);
        }

        return response()->json(['data' => $query->get(['id', 'name', 'region_id'])]);
    }
}
