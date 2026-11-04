<?php

namespace Database\Seeders;

use App\Models\Franchise;
use App\Models\FranchiseInventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentSubmission;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Models\WarehouseInventory;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DashboardDataSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $franchises = Franchise::where('is_active', true)->get();
            if ($franchises->isEmpty()) {
                $this->command->info('No franchises found. Skipping dashboard data seeding.');

                return;
            }
            $products = Product::where('is_active', true)->get();
            if ($products->isEmpty()) {
                $this->command->info('No products found. Skipping dashboard data seeding.');

                return;
            }
            $staff = User::where('name', 'Farmmantra Staff')->first();
            $finance = User::where('name', 'Finance Officer')->first();

            if (! $staff || ! $finance) {
                $this->command->info('Staff or Finance user not found. Skipping dashboard data seeding.');

                return;
            }

            $now = Carbon::now();
            $thisMonth = $now->copy()->startOfMonth();
            $lastMonth = $now->copy()->subMonth()->startOfMonth();

            // --- SALES: 80 over last 60 days ---
            for ($i = 0; $i < 80; $i++) {
                $franchise = $franchises->random();
                $daysAgo = rand(0, 59);
                $saleDate = $now->copy()->subDays($daysAgo)->startOfDay()->addHours(rand(8, 17))->addMinutes(rand(0, 59));

                $numItems = rand(1, 4);
                $saleProducts = $products->random($numItems);
                $totalAmount = 0;

                $sale = Sale::create([
                    'sale_number' => Sale::generateSaleNumber(),
                    'franchise_id' => $franchise->id,
                    'customer_id' => null,
                    'created_by' => $franchise->users()->first()?->id ?? $staff->id,
                    'total_amount' => 0,
                    'discount' => rand(0, 3) === 0 ? rand(5000, 25000) : 0,
                    'final_amount' => 0,
                    'payment_method' => ['cash', 'mobile_money', 'bank_transfer'][rand(0, 2)],
                    'payment_status' => $daysAgo > 14 ? 'paid' : (rand(0, 3) === 0 ? 'credit' : 'paid'),
                    'notes' => null,
                    'sale_date' => $saleDate,
                ]);

                foreach ($saleProducts as $product) {
                    $qty = rand(1, 8);
                    $price = $product->standard_price;
                    $subtotal = $qty * $price;
                    $totalAmount += $subtotal;

                    SaleItem::create([
                        'sale_id' => $sale->id,
                        'product_id' => $product->id,
                        'quantity' => $qty,
                        'unit_price' => $price,
                        'subtotal' => $subtotal,
                    ]);
                }

                $finalAmount = $totalAmount - $sale->discount;
                $sale->update(['total_amount' => $totalAmount, 'final_amount' => $finalAmount]);
            }

            // --- ORDERS: 50 over last 60 days ---
            for ($i = 0; $i < 50; $i++) {
                $franchise = $franchises->random();
                $daysAgo = rand(0, 59);
                $createdAt = $now->copy()->subDays($daysAgo)->startOfDay()->addHours(rand(8, 17));
                $status = ['pending', 'approved', 'approved', 'approved', 'declined'][rand(0, 4)];

                $numItems = rand(1, 5);
                $orderProducts = $products->random($numItems);
                $totalAmount = 0;

                $order = Order::create([
                    'order_number' => Order::generateOrderNumber(),
                    'franchise_id' => $franchise->id,
                    'ordered_by' => $franchise->users()->first()?->id ?? $staff->id,
                    'status' => $status,
                    'total_amount' => 0,
                    'notes' => null,
                    'expected_delivery_date' => $createdAt->copy()->addDays(rand(3, 10)),
                    'approved_by' => $status !== 'pending' ? $staff->id : null,
                    'approved_at' => $status !== 'pending' ? $createdAt->copy()->addHours(rand(1, 24)) : null,
                    'decline_reason' => $status === 'declined' ? 'Insufficient stock' : null,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);

                foreach ($orderProducts as $product) {
                    $qty = rand(2, 15);
                    $price = $product->standard_price;
                    $subtotal = $qty * $price;
                    $totalAmount += $subtotal;

                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $product->id,
                        'quantity' => $qty,
                        'adjusted_quantity' => null,
                        'unit_price' => $price,
                        'original_unit_price' => $price,
                        'subtotal' => $subtotal,
                        'status' => 'pending',
                    ]);
                }

                $order->update(['total_amount' => $totalAmount]);
            }

            // --- PAYMENT SUBMISSIONS: 30 ---
            $sales = Sale::orderBy('created_at')->get();
            for ($i = 0; $i < min(30, $sales->count()); $i++) {
                $sale = $sales[$i];
                $franchise = Franchise::find($sale->franchise_id);
                $daysAgo = max(0, $now->diffInDays($sale->sale_date));
                $submittedAt = $sale->sale_date->copy()->addDays(rand(1, 5));
                $paymentStatus = ['pending', 'pending', 'accepted', 'accepted', 'accepted', 'rejected'][rand(0, 5)];

                PaymentSubmission::create([
                    'payment_number' => PaymentSubmission::generatePaymentNumber(),
                    'franchise_id' => $sale->franchise_id,
                    'amount' => $sale->final_amount,
                    'payment_method' => $sale->payment_method ?? 'cash',
                    'transaction_reference' => 'TXN-'.strtoupper(uniqid()),
                    'bank_name' => null,
                    'proof_of_payment_path' => null,
                    'status' => $paymentStatus,
                    'submitted_at' => $submittedAt,
                    'verified_by' => $paymentStatus !== 'pending' ? $finance->id : null,
                    'verified_at' => $paymentStatus !== 'pending' ? $submittedAt->copy()->addDays(rand(1, 3)) : null,
                    'accepted_by' => $paymentStatus === 'accepted' ? $finance->id : null,
                    'accepted_at' => $paymentStatus === 'accepted' ? $submittedAt->copy()->addDays(rand(1, 3)) : null,
                    'rejection_reason' => $paymentStatus === 'rejected' ? 'Invalid proof of payment' : null,
                    'verified_amount' => $paymentStatus === 'accepted' ? $sale->final_amount : null,
                ]);
            }

            // --- WAREHOUSE INVENTORY ---
            foreach ($products as $product) {
                WarehouseInventory::updateOrCreate(
                    ['product_id' => $product->id],
                    [
                        'quantity' => rand(20, 200),
                        'reserved_quantity' => rand(0, 10),
                        'reorder_level' => rand(10, 30),
                        'last_restocked_at' => $now->copy()->subDays(rand(1, 30)),
                    ]
                );
            }

            // --- FRANCHISE INVENTORY for each franchise x some products ---
            foreach ($franchises as $franchise) {
                $stockProducts = $products->random(min(rand(8, 15), $products->count()));
                foreach ($stockProducts as $product) {
                    FranchiseInventory::updateOrCreate(
                        [
                            'franchise_id' => $franchise->id,
                            'product_id' => $product->id,
                        ],
                        [
                            'quantity' => rand(5, 50),
                            'reorder_level' => rand(3, 10),
                            'total_value' => rand(5, 50) * $product->selling_price,
                        ]
                    );
                }
            }

            // Update franchise account balances and targets
            foreach ($franchises as $franchise) {
                $totalSales = Sale::where('franchise_id', $franchise->id)->sum('final_amount');
                $totalPayments = PaymentSubmission::where('franchise_id', $franchise->id)->where('status', 'accepted')->sum('verified_amount');
                $balance = max(0, $totalSales - $totalPayments);

                $franchise->update([
                    'account_balance' => $balance,
                    'credit_limit' => rand(5000000, 20000000),
                    'monthly_target' => rand(5000000, 15000000),
                ]);
            }
        });

        $this->command->info('Dashboard data seeded successfully!');
        $this->command->info('  Sales: '.Sale::count());
        $this->command->info('  Sale Items: '.SaleItem::count());
        $this->command->info('  Orders: '.Order::count());
        $this->command->info('  Order Items: '.OrderItem::count());
        $this->command->info('  Payments: '.PaymentSubmission::count());
    }
}
