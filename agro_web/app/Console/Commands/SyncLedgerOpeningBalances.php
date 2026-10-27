<?php

namespace App\Console\Commands;

use App\Models\AccountTransaction;
use App\Models\Franchise;
use App\Services\PaymentService;
use Illuminate\Console\Command;

/**
 * Makes every franchise statement agree with franchises.account_balance by
 * posting an opening-balance line for any difference (for data imported or
 * seeded outside the application). The balance itself is not changed.
 */
class SyncLedgerOpeningBalances extends Command
{
    protected $signature = 'ledger:sync-opening-balances {--dry-run : Only report differences}';

    protected $description = 'Post opening-balance statement lines so ledgers match franchise balances';

    public function handle(): int
    {
        $fixed = 0;
        foreach (Franchise::all() as $franchise) {
            $ledger = (float) AccountTransaction::where('franchise_id', $franchise->id)->sum('credit')
                - (float) AccountTransaction::where('franchise_id', $franchise->id)->sum('debit');
            $diff = round((float) $franchise->account_balance - $ledger, 2);
            if (abs($diff) < 0.01) {
                continue;
            }

            $this->line(sprintf('%s: balance %s, ledger %s, difference %s', $franchise->name, number_format((float) $franchise->account_balance, 2), number_format($ledger, 2), number_format($diff, 2)));
            if ($this->option('dry-run')) {
                continue;
            }

            AccountTransaction::create([
                'franchise_id' => $franchise->id,
                'type' => 'opening_balance',
                'description' => 'Opening balance (reconciled)',
                'credit' => $diff > 0 ? $diff : 0,
                'debit' => $diff < 0 ? abs($diff) : 0,
                'transaction_date' => ($franchise->created_at ?? now())->toDateString(),
            ]);
            PaymentService::allocateOutstanding($franchise);
            $fixed++;
        }

        $this->info($this->option('dry-run') ? 'Dry run complete.' : "Reconciled {$fixed} franchise ledger(s).");

        return self::SUCCESS;
    }
}
