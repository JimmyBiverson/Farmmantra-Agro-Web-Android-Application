<?php

namespace App\Services;

use App\Models\CreditNote;
use App\Models\Franchise;
use App\Models\FranchiseInventory;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\WarehouseInventory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Credit notes (price/amount corrections) and sales returns (goods sent back
 * to the warehouse). Both reduce the franchise balance and appear in the
 * "Debit" column of the account statement.
 */
class CreditNoteService
{
    public static function issueCreditNote(Franchise $franchise, User $actor, float $amount, string $reason, ?int $orderId = null): CreditNote
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Amount must be greater than zero.']);
        }

        return DB::transaction(function () use ($franchise, $actor, $amount, $reason, $orderId) {
            $note = CreditNote::create([
                'note_number' => CreditNote::generateNumber('credit_note'),
                'franchise_id' => $franchise->id,
                'type' => 'credit_note',
                'order_id' => $orderId,
                'amount' => round($amount, 2),
                'reason' => $reason,
                'created_by' => $actor->id,
            ]);
            $note->update(['signature_number' => SignatureService::sign($actor, 'credit_note_issued', $note, ['amount' => (string) $note->amount])->signature_number]);

            LedgerService::settle($franchise, 'credit_note', (float) $note->amount, "Credit note {$note->note_number}: {$reason}", $note, $note->note_number, $actor->id);
            PaymentService::allocateOutstanding($franchise->fresh());
            ActivityLogger::log('credit_note.issued', "Credit note {$note->note_number} for {$franchise->name}", $note, $actor->id, ['amount' => $note->amount]);
            self::notify($franchise, "Credit note {$note->note_number} of ".number_format((float) $note->amount).' UGX was issued to your account.');

            return $note;
        });
    }

    /**
     * @param  array<int, array{product_id:int, quantity:numeric}>  $items
     */
    public static function recordSalesReturn(Franchise $franchise, User $actor, array $items, string $reason, ?int $orderId = null): CreditNote
    {
        if (empty($items)) {
            throw ValidationException::withMessages(['items' => 'Add at least one returned product.']);
        }

        return DB::transaction(function () use ($franchise, $actor, $items, $reason, $orderId) {
            $lines = [];
            $amount = 0.0;
            foreach ($items as $item) {
                $qty = (float) $item['quantity'];
                $product = Product::findOrFail($item['product_id']);
                $inventory = FranchiseInventory::where('franchise_id', $franchise->id)->where('product_id', $product->id)->lockForUpdate()->first();
                if ($qty <= 0 || ! $inventory || (float) $inventory->quantity < $qty) {
                    throw ValidationException::withMessages(['items' => "{$franchise->name} does not hold {$qty} of {$product->name} to return."]);
                }
                $unit = (float) $product->standard_price;
                $lines[] = ['product_id' => $product->id, 'product_name' => $product->name, 'quantity' => $qty, 'unit_price' => $unit, 'subtotal' => round($qty * $unit, 2)];
                $amount += round($qty * $unit, 2);

                $inventory->quantity -= $qty;
                $inventory->total_value = $inventory->quantity * $unit;
                $inventory->save();

                $warehouse = WarehouseInventory::firstOrCreate(['product_id' => $product->id], ['quantity' => 0, 'reorder_level' => 0]);
                $warehouse->quantity += $qty;
                $warehouse->save();
            }

            $note = CreditNote::create([
                'note_number' => CreditNote::generateNumber('sales_return'),
                'franchise_id' => $franchise->id,
                'type' => 'sales_return',
                'order_id' => $orderId,
                'amount' => round($amount, 2),
                'items' => $lines,
                'reason' => $reason,
                'created_by' => $actor->id,
            ]);
            $note->update(['signature_number' => SignatureService::sign($actor, 'sales_return_recorded', $note, ['items' => $lines])->signature_number]);

            foreach ($lines as $line) {
                StockMovement::log('franchise_out', $line['product_id'], -$line['quantity'], $line['unit_price'], CreditNote::class, $note->id, "Sales return {$note->note_number} (franchise)", $actor->id);
                StockMovement::log('warehouse_in', $line['product_id'], $line['quantity'], $line['unit_price'], CreditNote::class, $note->id, "Sales return {$note->note_number} (warehouse)", $actor->id);
            }

            LedgerService::settle($franchise, 'sales_return', (float) $note->amount, "Sales return {$note->note_number}: {$reason}", $note, $note->note_number, $actor->id);
            PaymentService::allocateOutstanding($franchise->fresh());
            ActivityLogger::log('sales_return.recorded', "Sales return {$note->note_number} for {$franchise->name}", $note, $actor->id, ['amount' => $note->amount]);
            self::notify($franchise, "Sales return {$note->note_number} of ".number_format((float) $note->amount).' UGX was credited to your account.');

            return $note;
        });
    }

    private static function notify(Franchise $franchise, string $message): void
    {
        NotificationService::send(
            OrderService::franchiseUserIds($franchise->id),
            'Account Credited',
            $message,
            'payment',
            Franchise::class,
            $franchise->id,
            'franchise/statement',
        );
    }
}
