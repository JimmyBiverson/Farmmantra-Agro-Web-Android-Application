<?php

namespace App\Http\Controllers\Api\Operations;

use App\Http\Controllers\Controller;
use App\Models\FieldZone;
use App\Models\OperationRequest;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = OperationRequest::with(['user:id,name', 'franchise:id,name']);
        if (! $request->user()->isAdmin() && ! $request->user()->isFinance()) {
            $query->where('user_id', $request->user()->id);
        }
        if ($request->filled('type')) {
            $query->where('type', $request->string('type')->toString());
        }

        return response()->json($query->latest()->paginate(30));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['expense', 'funds', 'travel', 'budget', 'checkin'])],
            'title' => 'required|string|max:255', 'amount' => 'nullable|numeric|min:0|max:999999999999',
            'entry_date' => 'required|date', 'details' => 'required|array',
            'details.itinerary' => 'nullable|string|max:5000', 'details.week_start' => 'nullable|date', 'details.zone_id' => 'nullable|integer', 'details.latitude' => 'nullable|numeric', 'details.longitude' => 'nullable|numeric', 'details.accuracy' => 'nullable|numeric', 'details.captured_at' => 'nullable|date',
            'details.notes' => 'nullable|string|max:5000', 'details.category' => 'nullable|string|max:100',
            'fund_request_id' => 'nullable|integer|exists:operation_requests,id',
            'receipt' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);
        $actor = $request->user();
        $type = $data['type'];
        abort_if($type === 'budget' && ! $actor->isAdmin(), 403);
        abort_if($type === 'travel' && ! ($actor->isSalesHead() || $actor->isAgronomist()), 403);
        abort_if($type === 'checkin' && ! $actor->isAgronomist(), 403);
        if (in_array($type, ['expense', 'funds', 'budget'], true) && ($data['amount'] ?? 0) <= 0) {
            throw ValidationException::withMessages(['amount' => 'Enter an amount greater than zero.']);
        }
        $details = array_intersect_key($data['details'], array_flip(['notes', 'category', 'itinerary', 'week_start', 'zone_id', 'latitude', 'longitude', 'accuracy', 'captured_at']));
        if ($type === 'travel') {
            $request->validate(['details.week_start' => 'required|date', 'details.itinerary' => 'required|string|max:5000']);
            if (! Carbon::parse($details['week_start'])->isSunday()) {
                throw ValidationException::withMessages(['details.week_start' => 'Select the Sunday starting this weekly travel plan.']);
            }
        }
        if ($type === 'checkin') {
            $request->validate(['details.zone_id' => 'required|integer|exists:field_zones,id', 'details.latitude' => 'required|numeric|between:-90,90', 'details.longitude' => 'required|numeric|between:-180,180', 'details.accuracy' => 'required|numeric|between:0,100', 'details.captured_at' => 'required|date']);
            $zone = FieldZone::whereKey($details['zone_id'])->where('active', true)->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', $actor->id))->firstOrFail();
            if (abs(now()->diffInSeconds(Carbon::parse($details['captured_at']), false)) > 120) {
                throw ValidationException::withMessages(['location' => 'Obtain a fresh location fix and try again.']);
            }
            $lat1 = deg2rad((float) $zone->latitude);
            $lat2 = deg2rad((float) $details['latitude']);
            $a = sin(($lat2 - $lat1) / 2) ** 2 + cos($lat1) * cos($lat2) * sin(deg2rad((float) $details['longitude'] - (float) $zone->longitude) / 2) ** 2;
            $distance = 6371000 * 2 * atan2(sqrt($a), sqrt(max(0, 1 - $a)));
            $details['distance_meters'] = round($distance);
            $details['inside_zone'] = $distance + (float) $details['accuracy'] <= $zone->radius_meters;
            if (! $details['inside_zone']) {
                throw ValidationException::withMessages(['location' => 'You are outside the assigned zone or the location accuracy is insufficient.']);
            }
        }
        $record = DB::transaction(function () use ($actor, $data, $type, $details, $request) {
            if (! empty($data['fund_request_id'])) {
                $fund = OperationRequest::whereKey($data['fund_request_id'])->lockForUpdate()->firstOrFail();
                abort_unless($type === 'expense' && $fund->type === 'funds' && $fund->user_id === $actor->id && $fund->status === 'paid', 422, 'Select your own disbursed funding request.');
                $spent = OperationRequest::where('fund_request_id', $fund->id)->where('status', '!=', 'rejected')->sum('amount');
                if ($spent + $data['amount'] > $fund->amount) {
                    throw ValidationException::withMessages(['amount' => 'Expenses exceed the disbursed funds.']);
                }
            }
            if ($request->hasFile('receipt')) {
                $details['receipt'] = $request->file('receipt')->store('expense-receipts', 'local');
            }
            $record = OperationRequest::create(['number' => strtoupper(substr($type, 0, 3)).'-'.Str::upper((string) Str::ulid()), 'user_id' => $actor->id, 'franchise_id' => $actor->franchise_id, 'type' => $type, 'title' => $data['title'], 'amount' => $data['amount'] ?? 0, 'entry_date' => $data['entry_date'], 'details' => $details, 'fund_request_id' => $data['fund_request_id'] ?? null, 'status' => in_array($type, ['budget', 'checkin']) ? 'recorded' : 'submitted']);
            ActivityLogger::log('operation.submitted', $record->number.' submitted', $record, $actor->id);

            return $record;
        });
        NotificationService::send(User::whereHas('role', fn ($q) => $q->whereIn('name', ['System Administrator', 'Finance Department']))->pluck('id')->all(), 'New '.$type.' submission', $record->title, 'system', OperationRequest::class, $record->id, 'operations/requests');

        return response()->json(['data' => $record], 201);
    }

    public function review(Request $request, OperationRequest $operation): JsonResponse
    {
        $data = $request->validate(['action' => ['required', Rule::in(['approve', 'reject', 'pay'])], 'notes' => 'required|string|max:3000', 'reference' => 'required_if:action,pay|nullable|string|max:255']);
        $result = DB::transaction(function () use ($request, $operation, $data) {
            $record = OperationRequest::whereKey($operation->id)->lockForUpdate()->firstOrFail();
            $actor = $request->user();
            abort_if($record->user_id === $actor->id, 403, 'You cannot approve your own submission.');
            $isFunds = $record->type === 'funds';
            $allowed = $record->status === 'submitted' && ($isFunds || $record->type === 'travel' ? $actor->isAdmin() : $record->type === 'expense' && $actor->isFinance());
            if ($data['action'] === 'pay') {
                $allowed = $isFunds && $record->status === 'approved' && $actor->isFinance();
            }
            abort_unless($allowed, 403, 'This action is not available for your role or the current status.');
            $history = $record->history ?? [];
            $history[] = ['user_id' => $actor->id, 'name' => $actor->name, 'action' => $data['action'], 'notes' => $data['notes'], 'reference' => $data['reference'] ?? null, 'at' => now()->toIso8601String()];
            $record->update(['status' => match ($data['action']) {
                'approve' => 'approved', 'pay' => 'paid', default => 'rejected'
            }, 'history' => $history]);
            ActivityLogger::log('operation.reviewed', $record->number.' '.$record->status, $record, $actor->id, end($history));

            return $record;
        });
        NotificationService::send([$result->user_id], 'Submission '.$result->status, $result->number.' · '.$result->title, 'system', OperationRequest::class, $result->id, 'operations/requests');

        return response()->json(['data' => $result]);
    }

    public function receipt(Request $request, OperationRequest $operation): mixed
    {
        abort_unless($request->user()->isAdmin() || $request->user()->isFinance() || $operation->user_id === $request->user()->id, 403);
        abort_unless(isset($operation->details['receipt']), 404);

        return response()->file(storage_path('app/private/'.$operation->details['receipt']), ['Cache-Control' => 'private, no-store']);
    }
}
