<?php

namespace App\Services;

use App\Models\Franchise;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BusinessResetService
{
    /**
     * Remove period-specific data while keeping the catalogue, accounts, branding,
     * and audit trail ready for the first real business cycle.
     */
    public function reset(): array
    {
        return DB::transaction(function (): array {
            $paymentProofs = DB::table('payment_submissions')
                ->whereNotNull('proof_of_payment_path')
                ->pluck('proof_of_payment_path')
                ->filter(fn ($path) => ! filter_var($path, FILTER_VALIDATE_URL));

            $tables = [
                'payment_order',
                'stock_receipt_items',
                'stock_receipts',
                'order_items',
                'orders',
                'sale_items',
                'sales',
                'payment_submissions',
                'stock_movements',
                'sales_targets',
                'notifications',
                'messages',
                'conversations',
                'support_tickets',
                'customers',
            ];

            $deleted = [];
            foreach ($tables as $table) {
                $deleted[$table] = DB::table($table)->count();
                DB::table($table)->delete();
            }

            if ($paymentProofs->isNotEmpty()) {
                Storage::disk('public')->delete($paymentProofs->all());
            }

            $balancesReset = Franchise::where('account_balance', '!=', 0)
                ->update(['account_balance' => 0]);

            return [
                'deleted' => $deleted,
                'total_deleted' => array_sum($deleted),
                'balances_reset' => $balancesReset,
            ];
        });
    }
}
