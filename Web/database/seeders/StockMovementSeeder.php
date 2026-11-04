<?php

namespace Database\Seeders;

use App\Models\Franchise;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Seeder;

class StockMovementSeeder extends Seeder
{
    public function run(): void
    {
        $products = Product::all();
        $franchises = Franchise::all();
        $staffUser = User::where('email', 'staff@farmmantra.co.ug')->first();
        $partnerUser = User::where('email', 'partner@jinja.farmmantra.co.ug')->first();
        $count = 0;

        for ($i = 0; $i < 60; $i++) {
            $product = $products->random();
            $daysAgo = rand(1, 60);
            $date = now()->subDays($daysAgo)->subHours(rand(0, 12));
            $qty = rand(5, 50);
            $price = $product->standard_price;

            StockMovement::create([
                'product_id' => $product->id,
                'type' => 'warehouse_out',
                'quantity' => -$qty,
                'unit_price' => $price,
                'total_value' => $qty * $price,
                'notes' => 'Order approved - stock dispatched',
                'user_id' => $staffUser?->id,
                'created_at' => $date,
                'updated_at' => $date,
            ]);
            $count++;

            StockMovement::create([
                'product_id' => $product->id,
                'type' => 'franchise_in',
                'quantity' => $qty,
                'unit_price' => $price,
                'total_value' => $qty * $price,
                'notes' => 'Stock receipt confirmed',
                'user_id' => $partnerUser?->id,
                'created_at' => $date->copy()->addDays(rand(1, 3)),
                'updated_at' => $date->copy()->addDays(rand(1, 3)),
            ]);
            $count++;

            if (rand(0, 3) === 0) {
                $soldQty = rand(1, min(10, $qty));
                StockMovement::create([
                    'product_id' => $product->id,
                    'type' => 'franchise_out',
                    'quantity' => -$soldQty,
                    'unit_price' => $product->selling_price,
                    'total_value' => $soldQty * $product->selling_price,
                    'notes' => 'Sale recorded',
                    'user_id' => $partnerUser?->id,
                    'created_at' => $date->copy()->addDays(rand(3, 7)),
                    'updated_at' => $date->copy()->addDays(rand(3, 7)),
                ]);
                $count++;
            }
        }

        $this->command->info("{$count} stock movements seeded.");
    }
}
