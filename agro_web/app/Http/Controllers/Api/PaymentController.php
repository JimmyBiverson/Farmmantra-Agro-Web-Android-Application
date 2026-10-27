<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentSubmission;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = PaymentSubmission::with('orders:id,order_number,total_amount,tax_amount,status,delivery_status')
            ->where('franchise_id', $user->franchise_id);

        if ($request->has('status') && ! empty($request->status)) {
            $query->where('status', $request->status);
        }

        $payments = $query->latest('submitted_at')->get();

        return response()->json(['data' => $payments]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'order_id' => 'nullable|exists:orders,id',
            'order_ids' => 'nullable|array',
            'order_ids.*' => 'exists:orders,id',
            'payment_method' => 'required|string|in:'.implode(',', array_keys(PaymentService::METHODS)),
            'transaction_reference' => 'required|string|max:100',
            'bank_name' => 'nullable|string|max:100',
            'proof_of_payment' => 'nullable|file|mimes:jpeg,jpg,png,webp,pdf|max:5120',
            'notes' => 'nullable|string',
        ]);

        $payment = PaymentService::submit($request->user(), $request->all(), $request->file('proof_of_payment'));

        return response()->json(['message' => 'Payment submitted successfully.', 'data' => $payment], 201);
    }

    public function show(PaymentSubmission $paymentSubmission): JsonResponse
    {
        $user = request()->user();

        if ($paymentSubmission->franchise_id !== $user->franchise_id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $paymentSubmission->load([
            'orders:id,order_number,total_amount,tax_amount,status,delivery_status',
            'franchise:id,name,code',
        ]);

        return response()->json(['data' => $paymentSubmission]);
    }

    public function uploadProof(Request $request, PaymentSubmission $paymentSubmission): JsonResponse
    {
        $user = request()->user();

        if ($paymentSubmission->franchise_id !== $user->franchise_id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $request->validate([
            'proof_of_payment' => 'required|file|image|max:5120',
        ]);

        $proofPath = $request->file('proof_of_payment')->store('payment-proofs', 'public');

        // Allow a rejected / info-requested payment to be re-submitted for review.
        $newStatus = $paymentSubmission->status === 'pending' ? 'pending' : 'pending';

        $paymentSubmission->update([
            'proof_of_payment_path' => $proofPath,
            'status' => $newStatus,
            'rejection_reason' => null,
            'info_request_note' => null,
        ]);

        $financeIds = User::whereHas('role', fn ($q) => $q->where('name', 'Finance Department'))
            ->pluck('id')
            ->all();

        NotificationService::send(
            $financeIds,
            'Payment Re-submitted — '.$paymentSubmission->payment_number,
            "{$user->franchise?->name} uploaded updated proof for {$paymentSubmission->payment_number}.",
            'payment',
            PaymentSubmission::class,
            $paymentSubmission->id,
            'finance/payments/detail',
        );

        return response()->json([
            'message' => 'Proof of payment uploaded. Payment is pending review again.',
            'data' => $paymentSubmission->fresh(),
        ]);
    }
}
