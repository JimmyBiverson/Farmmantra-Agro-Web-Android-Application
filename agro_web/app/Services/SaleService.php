<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\FranchiseInventory;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Franchise sales to end customers. Selling prices are fixed by the admin per
 * customer type (Retail or Farmer); the franchise only chooses the tier.
 * Each line stores the franchise (cost) price so monthly profit is exact.
 */
class SaleService
{
    public const TIERS = ['retailer' => 'Retail price', 'farmer' => 'Farmer price'];

    public static function create(User $user, array $data): Sale
    {
        $franchise = $user->franchise;
        $customer = null;

        if (! empty($data['customer_id'])) {
            $customer = Customer::visibleToFranchise($franchise)->where('is_active', true)->find($data['customer_id']);
            if (! $customer) {
                throw ValidationException::withMessages(['customer_id' => 'This customer is not in your region.']);
            }
        }

        $tier = $data['price_tier'] ?? $customer?->customer_type ?? 'farmer';
        if (! array_key_exists($tier, self::TIERS)) {
            throw ValidationException::withMessages(['price_tier' => 'Choose the Retail or Farmer price.']);
        }

        $discount = (float) ($data['discount'] ?? 0);
        if ($discount > 0 && ! Setting::get('allow_sale_discount', '0')) {
            throw ValidationException::withMessages(['discount' => 'Discounts are not allowed. Sell at the listed price.']);
        }

        $sale = DB::transaction(function () use ($user, $data, $customer, $tier, $discount) {
            $lines = [];
            $productIds = collect($data['items'])->pluck('product_id')->unique()->all();
            $products = Product::whereIn('id', $productIds)->get()->keyBy('id');

            foreach ($data['items'] as $item) {
                $qty = (float) $item['quantity'];
                $inventory = FranchiseInventory::where('franchise_id', $user->franchise_id)
                    ->where('product_id', $item['product_id'])
                    ->lockForUpdate()
                    ->first();
                $product = $products->get($item['product_id']);
                if (! $product || ! $inventory || (float) $inventory->quantity < $qty) {
                    throw ValidationException::withMessages([
                        'items' => 'Insufficient stock for '.($product?->name ?? 'a selected product').'.',
                    ]);
                }
                $lines[] = [$product, $inventory, $qty, $product->priceForTier($tier), (float) $product->standard_price];
            }

            $total = array_sum(array_map(fn ($l) => round($l[2] * $l[3], 2), $lines));
            $cost = array_sum(array_map(fn ($l) => round($l[2] * $l[4], 2), $lines));
            $discountValue = min($discount, $total);

            $sale = Sale::create([
                'sale_number' => Sale::generateSaleNumber(),
                'franchise_id' => $user->franchise_id,
                'customer_id' => $customer?->id,
                'price_tier' => $tier,
                'created_by' => $user->id,
                'total_amount' => $total,
                'discount' => $discountValue,
                'final_amount' => max(0, $total - $discountValue),
                'total_cost' => $cost,
                'payment_method' => $data['payment_method'],
                'payment_status' => $data['payment_method'] === 'credit' ? 'credit' : 'paid',
                'notes' => $data['notes'] ?? null,
                'sale_date' => now()->toDateString(),
            ]);

            foreach ($lines as [$product, $inventory, $qty, $unitPrice, $costPrice]) {
                $sale->items()->create([
                    'product_id' => $product->id,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'cost_price' => $costPrice,
                    'subtotal' => round($qty * $unitPrice, 2),
                ]);

                $inventory->quantity -= $qty;
                $inventory->total_value = $inventory->quantity * $product->standard_price;
                $inventory->save();

                StockMovement::log('franchise_out', $product->id, -$qty, $unitPrice, Sale::class, $sale->id, "Sale {$sale->sale_number}", $user->id);
            }

            return $sale;
        });

        $sale->load(['customer', 'items.product']);
        ActivityLogger::saleCreated($sale);

        return $sale;
    }
}
