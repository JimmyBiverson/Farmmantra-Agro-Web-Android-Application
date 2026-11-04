<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Franchise;
use App\Models\PaymentSubmission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinanceDashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $now = now();

        $data = [
            'summary' => [
                'pending_payments_count' => PaymentSubmission::where('status', 'pending')->count(),
                'pending_payments_total' => PaymentSubmission::where('status', 'pending')->sum('amount'),
                'verified_payments_count' => PaymentSubmission::where('status', 'verified')->count(),
                'accepted_this_month' => PaymentSubmission::where('status', 'accepted')
                    ->whereMonth('accepted_at', $now->month)
                    ->whereYear('accepted_at', $now->year)
                    ->count(),
                'accepted_amount_this_month' => PaymentSubmission::where('status', 'accepted')
                    ->whereMonth('accepted_at', $now->month)
                    ->whereYear('accepted_at', $now->year)
                    ->sum('verified_amount'),
                'rejected_this_month' => PaymentSubmission::where('status', 'rejected')
                    ->whereMonth('verified_at', $now->month)
                    ->whereYear('verified_at', $now->year)
                    ->count(),
                'total_outstanding' => Franchise::where('account_balance', '>', 0)
                    ->sum('account_balance'),
                'total_collected_ytd' => PaymentSubmission::where('status', 'accepted')
                    ->whereYear('accepted_at', $now->year)
                    ->sum('verified_amount'),
            ],

            'outstanding_by_franchise' => Franchise::where('account_balance', '>', 0)
                ->select('id', 'name', 'code', 'account_balance', 'credit_limit')
                ->orderByDesc('account_balance')
                ->get()
                ->map(function ($f) {
                    return [
                        'id' => $f->id,
                        'name' => $f->name,
                        'code' => $f->code,
                        'balance' => $f->account_balance,
                        'credit_limit' => $f->credit_limit,
                        'utilization' => $f->credit_limit > 0
                            ? round(($f->account_balance / $f->credit_limit) * 100, 1)
                            : 0,
                    ];
                }),

            'payment_trend' => PaymentSubmission::where('status', 'accepted')
                ->select(
                    DB::raw('DATE(accepted_at) as date'),
                    DB::raw('COUNT(*) as payment_count'),
                    DB::raw('SUM(verified_amount) as total_amount')
                )
                ->where('accepted_at', '>=', $now->copy()->subDays(30)->startOfDay())
                ->groupBy(DB::raw('DATE(accepted_at)'))
                ->orderBy('date')
                ->get(),

            'recent_pending' => PaymentSubmission::where('status', 'pending')
                ->with('franchise:id,name,code')
                ->latest('submitted_at')
                ->limit(10)
                ->get(),

            'recent_verified' => PaymentSubmission::where('status', 'verified')
                ->with('franchise:id,name,code')
                ->latest('verified_at')
                ->limit(10)
                ->get(),

            'payment_status_breakdown' => PaymentSubmission::select('status', DB::raw('COUNT(*) as count'), DB::raw('SUM(amount) as total'))
                ->groupBy('status')
                ->get(),
        ];

        return response()->json(['data' => $data]);
    }
}
