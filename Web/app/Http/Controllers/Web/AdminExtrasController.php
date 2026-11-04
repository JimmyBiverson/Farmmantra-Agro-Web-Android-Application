<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\District;
use App\Models\Franchise;
use App\Models\Order;
use App\Models\Region;
use App\Models\Setting;
use App\Models\SmsMessage;
use App\Services\ActivityLogger;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminExtrasController extends Controller
{
    // ── Customers (retailers & farmers) ─────────────────────────
    public function customers(Request $request)
    {
        $query = Customer::with(['franchise:id,name', 'district.region']);
        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('phone', 'like', "%{$q}%"));
        }
        foreach (['customer_type', 'franchise_id', 'district_id'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->input($filter));
            }
        }
        if ($request->filled('region_id')) {
            $query->whereIn('district_id', District::where('region_id', $request->region_id)->select('id'));
        }

        return view('admin.customers', [
            'customers' => $query->orderBy('name')->paginate(25)->withQueryString(),
            'franchises' => Franchise::orderBy('name')->get(['id', 'name', 'region_id']),
            'regions' => Region::orderBy('name')->get(),
            'districts' => District::with('region:id,name')->orderBy('name')->get(),
        ]);
    }

    private function customerRules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'customer_type' => 'required|in:retailer,farmer',
            'district_id' => 'required|exists:districts,id',
            'franchise_id' => 'nullable|exists:franchises,id',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:500',
        ];
    }

    /**
     * The franchise serving a customer defaults to the franchise of the
     * district's region; the admin may override it explicitly.
     */
    private function resolveFranchiseId(Request $request): ?int
    {
        if ($request->filled('franchise_id')) {
            return (int) $request->franchise_id;
        }
        $regionId = District::whereKey($request->district_id)->value('region_id');

        return $regionId ? Franchise::where('region_id', $regionId)->where('is_active', true)->orderBy('id')->value('id') : null;
    }

    public function storeCustomer(Request $request)
    {
        $request->validate($this->customerRules());
        $franchiseId = $this->resolveFranchiseId($request);
        if (! $franchiseId) {
            return back()->withInput()->with('error', 'No franchise serves that district yet. Choose a franchise or assign the district to a franchise region first.');
        }

        $customer = Customer::create($request->only('name', 'customer_type', 'district_id', 'phone', 'email', 'address') + [
            'franchise_id' => $franchiseId,
            'customer_code' => 'C-'.strtoupper(uniqid()),
            'is_wholesale' => $request->customer_type === 'retailer',
            'is_active' => true,
            'created_by' => $request->user()->id,
        ]);
        ActivityLogger::log('customer.created', "Admin created customer {$customer->name}", $customer, $request->user()->id);

        return back()->with('success', "Customer {$customer->name} created and assigned to ".$customer->franchise?->name.'.');
    }

    public function updateCustomer(Request $request, Customer $customer)
    {
        $request->validate($this->customerRules());
        $franchiseId = $this->resolveFranchiseId($request) ?? $customer->franchise_id;
        $customer->update($request->only('name', 'customer_type', 'district_id', 'phone', 'email', 'address') + [
            'franchise_id' => $franchiseId,
            'is_wholesale' => $request->customer_type === 'retailer',
        ]);
        ActivityLogger::log('customer.updated', "Customer {$customer->name} updated", $customer, $request->user()->id);

        return back()->with('success', 'Customer updated.');
    }

    public function toggleCustomer(Request $request, Customer $customer)
    {
        $customer->update(['is_active' => ! $customer->is_active]);
        $state = $customer->is_active ? 'activated' : 'deactivated';
        ActivityLogger::log("customer.{$state}", "Customer {$customer->name} {$state}", $customer, $request->user()->id);

        return back()->with('success', "Customer {$state}.");
    }

    // ── Regions & districts ─────────────────────────────────────
    public function regions()
    {
        return view('admin.regions', [
            'regions' => Region::withCount('districts')->with(['franchises:id,name,region_id', 'districts:id,name,region_id'])->orderBy('name')->get(),
            'districts' => District::with('region:id,name')->orderBy('name')->get(),
            'franchises' => Franchise::orderBy('name')->get(['id', 'name', 'region_id']),
        ]);
    }

    public function storeRegion(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100|unique:regions,name',
            'description' => 'nullable|string|max:255',
        ]);
        $region = Region::create($request->only('name', 'description') + ['is_active' => true]);
        ActivityLogger::log('region.created', "Region {$region->name} created", $region, $request->user()->id);

        return back()->with('success', "Region {$region->name} created. Now assign its districts and franchise.");
    }

    public function updateRegion(Request $request, Region $region)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('regions', 'name')->ignore($region->id)],
            'description' => 'nullable|string|max:255',
            'district_ids' => 'nullable|array',
            'district_ids.*' => 'exists:districts,id',
            'franchise_ids' => 'nullable|array',
            'franchise_ids.*' => 'exists:franchises,id',
        ]);

        $region->update($request->only('name', 'description'));

        // Districts selected here move into this region (a district belongs
        // to exactly one region); unselected ones are released.
        $selected = array_map('intval', (array) $request->input('district_ids', []));
        District::where('region_id', $region->id)->whereNotIn('id', $selected ?: [0])->update(['region_id' => null]);
        if ($selected) {
            District::whereIn('id', $selected)->update(['region_id' => $region->id]);
        }

        $franchiseIds = array_map('intval', (array) $request->input('franchise_ids', []));
        Franchise::where('region_id', $region->id)->whereNotIn('id', $franchiseIds ?: [0])->update(['region_id' => null]);
        if ($franchiseIds) {
            Franchise::whereIn('id', $franchiseIds)->update(['region_id' => $region->id, 'region' => $region->name]);
        }

        ActivityLogger::log('region.updated', "Region {$region->name} updated", $region, $request->user()->id, [
            'districts' => $selected, 'franchises' => $franchiseIds,
        ]);

        return back()->with('success', "Region {$region->name} saved: ".count($selected).' district(s), '.count($franchiseIds).' franchise(s).');
    }

    public function storeDistrict(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100|unique:districts,name',
            'official_region' => 'nullable|in:Central,Eastern,Northern,Western',
            'region_id' => 'nullable|exists:regions,id',
        ]);
        $district = District::create($request->only('name', 'official_region', 'region_id') + ['is_active' => true]);
        ActivityLogger::log('district.created', "District {$district->name} added", $district, $request->user()->id);

        return back()->with('success', "District {$district->name} added.");
    }

    // ── Bulk SMS ────────────────────────────────────────────────
    public function sms()
    {
        return view('admin.sms', [
            'history' => SmsMessage::with('sender:id,name')->latest()->paginate(15),
            'regions' => Region::orderBy('name')->get(['id', 'name']),
            'franchises' => Franchise::orderBy('name')->get(['id', 'name']),
            'driver' => config('services.sms.driver', 'log'),
        ]);
    }

    public function sendSms(Request $request)
    {
        $request->validate([
            'audience' => 'required|in:franchises,customers,custom',
            'customer_type' => 'nullable|in:retailer,farmer',
            'region_id' => 'nullable|exists:regions,id',
            'franchise_id' => 'nullable|exists:franchises,id',
            'numbers' => 'required_if:audience,custom|nullable|string|max:20000',
            'message' => 'required|string|min:3|max:918',
        ]);

        $numbers = [];
        $label = $request->audience;

        if ($request->audience === 'franchises') {
            $query = Franchise::where('is_active', true);
            if ($request->filled('region_id')) {
                $query->where('region_id', $request->region_id);
            }
            if ($request->filled('franchise_id')) {
                $query->whereKey($request->franchise_id);
            }
            $franchises = $query->with('users:id,franchise_id,phone')->get();
            foreach ($franchises as $f) {
                $numbers[] = $f->phone;
                foreach ($f->users as $u) {
                    $numbers[] = $u->phone;
                }
            }
            $label = 'Franchise partners';
        } elseif ($request->audience === 'customers') {
            $query = Customer::where('is_active', true);
            if ($request->filled('customer_type')) {
                $query->where('customer_type', $request->customer_type);
            }
            if ($request->filled('region_id')) {
                $query->whereIn('district_id', District::where('region_id', $request->region_id)->select('id'));
            }
            if ($request->filled('franchise_id')) {
                $query->where('franchise_id', $request->franchise_id);
            }
            $numbers = $query->pluck('phone')->all();
            $label = 'Customers'.($request->customer_type ? ' ('.$request->customer_type.'s)' : '');
        } else {
            $numbers = preg_split('/[\s,;]+/', (string) $request->numbers) ?: [];
            $label = 'Custom list';
        }

        if ($request->filled('region_id')) {
            $label .= ' — '.Region::whereKey($request->region_id)->value('name');
        }

        $record = SmsService::send($request->user(), $numbers, $request->message, $label);

        return back()->with($record->status === 'failed' ? 'error' : 'success', match ($record->status) {
            'sent' => "SMS sent to {$record->recipient_count} recipient(s).",
            'logged' => "SMS recorded for {$record->recipient_count} recipient(s). No SMS provider is configured, so nothing was actually sent (SMS_DRIVER=log).",
            default => 'SMS failed: '.$record->provider_response,
        });
    }

    // ── Terms & conditions editor ───────────────────────────────
    public function termsEdit()
    {
        return view('admin.terms', [
            'terms' => Setting::get('terms_and_conditions', ''),
            'version' => (int) Setting::get('terms_version', 1),
            'dispatchDays' => Order::dispatchTargetDays(),
            'allowDiscount' => (bool) Setting::get('allow_sale_discount', '0'),
        ]);
    }

    public function termsUpdate(Request $request)
    {
        $request->validate([
            'terms' => 'required|string|min:20',
            'require_reacceptance' => 'nullable|boolean',
            'dispatch_target_days' => 'required|integer|min:1|max:90',
            'allow_sale_discount' => 'nullable|boolean',
        ]);

        Setting::set('terms_and_conditions', $request->terms, 'terms');
        $version = (int) Setting::get('terms_version', 1);
        if ($request->boolean('require_reacceptance')) {
            $version++;
            Setting::set('terms_version', (string) $version, 'terms');
        }
        Setting::set('dispatch_target_days', (string) $request->dispatch_target_days, 'operations');
        Setting::set('allow_sale_discount', $request->boolean('allow_sale_discount') ? '1' : '0', 'operations');

        ActivityLogger::log('settings.terms_updated', "Terms & operations settings updated (terms v{$version})", null, $request->user()->id);

        return back()->with('success', $request->boolean('require_reacceptance')
            ? "Saved. All franchise partners must accept version {$version} at their next login."
            : 'Saved.');
    }
}
