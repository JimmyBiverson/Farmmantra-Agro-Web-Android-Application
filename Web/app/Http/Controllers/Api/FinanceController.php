<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentSubmission;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinanceController extends Controller
{
    public function pendingPayments(): JsonResponse
    {
        $payments = PaymentSubmission::where('status', 'pending')
            ->with(['franchise', 'orders:id,order_number,total_amount,tax_amount,status,delivery_status'])
            ->latest('submitted_at')
            ->paginate(20);

        return response()->json($payments);
    }

    public function showPayment(PaymentSubmission $paymentSubmission): JsonResponse
    {
        $paymentSubmission->load([
            'franchise',
            'orders' => fn ($q) => $q->with('items.product.category'),
            'verifier:id,name',
            'acceptor:id,name',
            'rejector:id,name',
            'infoRequestedBy:id,name',
        ]);

        return response()->json(['data' => $paymentSubmission]);
    }

    public function verify(Request $request, PaymentSubmission $paymentSubmission): JsonResponse
    {
        $request->validate([
            'verified_amount' => ['required', 'numeric', 'min:0', 'max:'.$paymentSubmission->amount],
            'finance_notes' => 'nullable|string',
        ]);

        $payment = PaymentService::verify($paymentSubmission, $request->user(), (float) $request->verified_amount, $request->finance_notes);

        return response()->json(['message' => 'Payment verified.', 'data' => $payment]);
    }

    public function accept(Request $request, PaymentSubmission $paymentSubmission): JsonResponse
    {
        [$payment, $franchise] = PaymentService::accept($paymentSubmission, $request->user());

        return response()->json([
            'message' => 'Payment accepted. Franchise balance updated.',
            'data' => [
                'payment' => $payment,
                'new_balance' => $franchise->account_balance,
            ],
        ]);
    }

    public function reject(Request $request, PaymentSubmission $paymentSubmission): JsonResponse
    {
        $request->validate(['rejection_reason' => 'required|string']);

        $payment = PaymentService::reject($paymentSubmission, $request->user(), $request->rejection_reason);

        return response()->json(['message' => 'Payment rejected.', 'data' => $payment]);
    }

    public function requestInfo(Request $request, PaymentSubmission $paymentSubmission): JsonResponse
    {
        $request->validate(['info_request_note' => 'required|string']);

        if (! in_array($paymentSubmission->status, ['pending', 'verified'])) {
            return response()->json(['message' => 'Payment cannot be requested for information in its current status.'], 422);
        }

        $paymentSubmission->update([
            'status' => 'info_requested',
            'info_requested_by' => $request->user()->id,
            'info_requested_at' => now(),
            'info_request_note' => $request->info_request_note,
        ]);

        $franchiseUser = User::where('franchise_id', $paymentSubmission->franchise_id)
            ->whereHas('role', fn ($q) => $q->where('name', 'Franchise Partner'))
            ->pluck('id')
            ->all();

        NotificationService::send(
            $franchiseUser,
            'More Info Needed — '.$paymentSubmission->payment_number,
            'Finance requested more information for your payment: '.$request->info_request_note,
            'payment',
            PaymentSubmission::class,
            $paymentSubmission->id,
            'franchise/payments/detail',
        );

        return response()->json([
            'message' => 'Information requested from the franchise partner.',
            'data' => $paymentSubmission->fresh('franchise'),
        ]);
    }

    public function allPayments(Request $request): JsonResponse
    {
        $query = PaymentSubmission::with(['franchise', 'orders:id,order_number']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('franchise_id')) {
            $query->where('franchise_id', $request->franchise_id);
        }

        $payments = $query->latest('submitted_at')->paginate(20);

        return response()->json($payments);
    }
}
