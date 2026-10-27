<?php

namespace App\Services;

use App\Models\AccountTransaction;
use App\Models\Franchise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Single place where a franchise's amount owed changes. Every change writes
 * a statement line and keeps franchises.account_balance in step with it.
 */
class LedgerService
{
    /** Increase the amount owed (purchase / opening balance). */
    public static function charge(Franchise $franchise, string $type, float $amount, string $description, ?Model $reference = null, ?string $referenceNumber = null, ?int $userId = null): AccountTransaction
    {
        return self::post($franchise, $type, round($amount, 2), 0, $description, $reference, $referenceNumber, $userId);
    }

    /** Reduce the amount owed (payment / credit note / sales return). */
    public static function settle(Franchise $franchise, string $type, float $amount, string $description, ?Model $reference = null, ?string $referenceNumber = null, ?int $userId = null): AccountTransaction
    {
        return self::post($franchise, $type, 0, round($amount, 2), $description, $reference, $referenceNumber, $userId);
    }

    private static function post(Franchise $franchise, string $type, float $credit, float $debit, string $description, ?Model $reference, ?string $referenceNumber, ?int $userId): AccountTransaction
    {
        return DB::transaction(function () use ($franchise, $type, $credit, $debit, $description, $reference, $referenceNumber, $userId) {
            $locked = Franchise::whereKey($franchise->id)->lockForUpdate()->firstOrFail();
            $locked->account_balance = round((float) $locked->account_balance + $credit - $debit, 2);
            $locked->save();
            $franchise->account_balance = $locked->account_balance;

            return AccountTransaction::create([
                'franchise_id' => $locked->id,
                'type' => $type,
                'reference_number' => $referenceNumber,
                'reference_type' => $reference ? get_class($reference) : null,
                'reference_id' => $reference?->getKey(),
                'description' => $description,
                'credit' => $credit,
                'debit' => $debit,
                'transaction_date' => now()->toDateString(),
                'created_by' => $userId ?? auth()->id(),
            ]);
        });
    }

    /**
     * Statement for a period: opening balance brought forward, lines with a
     * running balance, totals and closing balance.
     */
    public static function statement(Franchise $franchise, ?string $from = null, ?string $to = null): array
    {
        $fromDate = $from ? Carbon::parse($from)->startOfDay() : null;
        $toDate = $to ? Carbon::parse($to)->endOfDay() : null;

        $opening = 0.0;
        if ($fromDate) {
            $opening = (float) AccountTransaction::where('franchise_id', $franchise->id)
                ->where('transaction_date', '<', $fromDate->toDateString())
                ->sum(DB::raw('credit - debit'));
        }

        $lines = AccountTransaction::where('franchise_id', $franchise->id)
            ->when($fromDate, fn ($q) => $q->where('transaction_date', '>=', $fromDate->toDateString()))
            ->when($toDate, fn ($q) => $q->where('transaction_date', '<=', $toDate->toDateString()))
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();

        $running = $opening;
        $rows = $lines->map(function (AccountTransaction $t) use (&$running) {
            $running = round($running + (float) $t->credit - (float) $t->debit, 2);

            return [
                'id' => $t->id,
                'date' => $t->transaction_date?->toDateString(),
                'type' => $t->type,
                'type_label' => $t->type_label,
                'reference_number' => $t->reference_number,
                'description' => $t->description,
                'credit' => (float) $t->credit,
                'debit' => (float) $t->debit,
                'balance' => $running,
            ];
        })->values();

        return [
            'franchise' => [
                'id' => $franchise->id,
                'name' => $franchise->name,
                'code' => $franchise->code,
                'contact_person' => $franchise->contact_person,
                'phone' => $franchise->phone,
                'email' => $franchise->email,
                'location' => trim(implode(', ', array_filter([$franchise->address, $franchise->district?->name, $franchise->regionModel?->name ?? $franchise->region])), ', '),
                'credit_limit' => (float) $franchise->credit_limit,
            ],
            'period' => [
                'from' => $fromDate?->toDateString(),
                'to' => ($toDate ?? now())->toDateString(),
            ],
            'opening_balance' => round($opening, 2),
            'transactions' => $rows,
            'totals' => [
                'credit' => round((float) $lines->sum('credit'), 2),
                'debit' => round((float) $lines->sum('debit'), 2),
            ],
            'closing_balance' => round($running, 2),
            'current_balance' => (float) $franchise->fresh()->account_balance,
        ];
    }
}
