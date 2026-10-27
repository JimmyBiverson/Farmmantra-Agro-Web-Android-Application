<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Franchise;
use App\Models\Order;
use App\Models\PaymentSubmission;
use App\Models\Product;
use App\Models\Role;
use App\Models\StockReceipt;
use App\Models\User;
use App\Models\WarehouseInventory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FranchiseOrderAndPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $staffUser;

    protected User $warehouseUser;

    protected User $financeUser;

    protected User $franchiseUser;

    protected Franchise $franchise;

    protected Product $product;

    protected Role $adminRole;

    protected Role $staffRole;

    protected Role $financeRole;

    protected Role $franchiseRole;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles
        $this->adminRole = Role::create(['name' => 'System Administrator', 'guard_name' => 'web']);
        $this->staffRole = Role::create(['name' => 'Farmmantra Staff', 'guard_name' => 'web']);
        $this->financeRole = Role::create(['name' => 'Finance Department', 'guard_name' => 'web']);
        $this->franchiseRole = Role::create(['name' => 'Franchise Partner', 'guard_name' => 'web']);
        $warehouseRole = Role::create(['name' => 'Warehouse', 'guard_name' => 'web']);
        $this->warehouseUser = User::create([
            'name' => 'Warehouse User',
            'email' => 'warehouse@test.com',
            'password' => bcrypt('password'),
            'role_id' => $warehouseRole->id,
            'is_active' => true,
        ]);

        // Create franchise
        $this->franchise = Franchise::create([
            'name' => 'Kampala Franchise Test',
            'code' => 'FRN-TEST-1',
            'credit_limit' => 1000000.00, // 1,000,000 Limit
            'account_balance' => 0.00,
            'is_active' => true,
        ]);

        // Create users
        $this->adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        $this->staffUser = User::create([
            'name' => 'Staff User',
            'email' => 'staff@test.com',
            'password' => bcrypt('password'),
            'role_id' => $this->staffRole->id,
            'is_active' => true,
        ]);

        $this->financeUser = User::create([
            'name' => 'Finance User',
            'email' => 'finance@test.com',
            'password' => bcrypt('password'),
            'role_id' => $this->financeRole->id,
            'is_active' => true,
        ]);

        $this->franchiseUser = User::create([
            'name' => 'Franchise Partner',
            'email' => 'franchise@test.com',
            'password' => bcrypt('password'),
            'role_id' => $this->franchiseRole->id,
            'franchise_id' => $this->franchise->id,
            'is_active' => true,
            'terms_accepted_at' => now(),
            'terms_version' => 1,
        ]);

        // Create category and product
        $category = Category::create([
            'name' => 'Herbicides',
            'slug' => 'herbicides',
        ]);

        $this->product = Product::create([
            'name' => 'Roundup Test',
            'sku' => 'HERB-TEST-1',
            'category_id' => $category->id,
            'standard_price' => 50000.00,
            'selling_price' => 50000.00,
            'is_active' => true,
        ]);

        // Add warehouse stock
        WarehouseInventory::create([
            'product_id' => $this->product->id,
            'quantity' => 100,
            'reorder_level' => 10,
        ]);
    }

    public function test_franchise_credit_limit_validation(): void
    {
        Sanctum::actingAs($this->franchiseUser);

        // Attempting to place an order that exceeds the credit limit (qty = 21 * 50k = 1.05M > 1M limit)
        $response = $this->postJson('/api/franchise/orders', [
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 21,
                ],
            ],
            'notes' => 'Oversized order',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['order']);

        // Placing an order within credit limit (qty = 10 * 50k = 500k)
        $response = $this->postJson('/api/franchise/orders', [
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 10,
                ],
            ],
            'notes' => 'Normal order',
        ]);

        $response->assertStatus(201);
        $this->assertEquals(0.00, $this->franchise->fresh()->account_balance);
    }

    public function test_delivery_increases_outstanding_balance_and_payment_reduces_it(): void
    {
        // 1. Franchise places an order for 10 items (value = 500,000)
        Sanctum::actingAs($this->franchiseUser);
        $orderResponse = $this->postJson('/api/franchise/orders', [
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 10,
                ],
            ],
            'notes' => 'Normal order',
        ]);

        $orderResponse->assertStatus(201);
        $order = Order::find($orderResponse->json('data.id'));
        $this->assertNotNull($order);

        // 2. Warehouse approves the order (sales staff only monitor)
        Sanctum::actingAs($this->staffUser);
        $this->postJson("/api/staff/orders/{$order->id}/approve", [
            'expected_delivery_date' => now()->addDays(5)->toDateString(),
        ])->assertStatus(403);
        Sanctum::actingAs($this->warehouseUser);
        $approveResponse = $this->postJson("/api/staff/orders/{$order->id}/approve", [
            'expected_delivery_date' => now()->addDays(5)->toDateString(),
            'notes' => 'Approved to dispatch',
        ]);

        $approveResponse->assertStatus(200);

        // Verify stock receipt created
        $stockReceipt = StockReceipt::where('order_id', $order->id)->first();
        $this->assertNotNull($stockReceipt);
        $this->assertEquals('pending', $stockReceipt->status);
        $this->assertEquals(0.00, $this->franchise->fresh()->account_balance);

        // 3. Warehouse dispatches (credit order: no prepayment needed)
        $this->postJson("/api/staff/orders/{$order->id}/dispatch", [
            'dispatch_means' => 'truck',
            'dispatch_vehicle' => 'UBA 123X',
        ])->assertStatus(200);

        // 4. Franchise accepts the delivery (terms must be accepted)
        Sanctum::actingAs($this->franchiseUser);
        $receiptItem = $stockReceipt->items->first();
        $this->postJson("/api/franchise/stock-receipts/{$stockReceipt->id}/confirm", [
            'items' => [['stock_receipt_item_id' => $receiptItem->id, 'received_quantity' => 10]],
        ])->assertStatus(422)->assertJsonValidationErrors('accept_terms');
        $confirmResponse = $this->postJson("/api/franchise/stock-receipts/{$stockReceipt->id}/confirm", [
            'items' => [
                [
                    'stock_receipt_item_id' => $receiptItem->id,
                    'received_quantity' => 10,
                ],
            ],
            'accept_terms' => true,
            'notes' => 'All items received intact',
        ]);

        $confirmResponse->assertStatus(200);

        // Verify outstanding balance has increased by 500,000
        $this->assertEquals(500000.00, (float) $this->franchise->fresh()->account_balance);

        // 4. Franchise submits a payment of 300,000
        $submitPaymentResponse = $this->postJson('/api/franchise/payments', [
            'amount' => 300000.00,
            'payment_method' => 'bank_transfer',
            'transaction_reference' => 'TXN123456',
            'bank_name' => 'Centenary Bank',
            'notes' => 'Part payment',
        ]);

        $submitPaymentResponse->assertStatus(201);
        $payment = PaymentSubmission::find($submitPaymentResponse->json('data.id'));

        // Outstanding balance should only update after payment acceptance
        $this->assertEquals(500000.00, (float) $this->franchise->fresh()->account_balance);

        // 5. Finance verifies the payment
        Sanctum::actingAs($this->financeUser);
        $verifyResponse = $this->postJson("/api/finance/payments/{$payment->id}/verify", [
            'verified_amount' => 300000.00,
            'finance_notes' => 'Verified against bank bank statement',
        ]);

        $verifyResponse->assertStatus(200);
        $this->assertEquals(500000.00, (float) $this->franchise->fresh()->account_balance);

        // 6. Finance accepts the payment
        $acceptResponse = $this->postJson("/api/finance/payments/{$payment->id}/accept");

        $acceptResponse->assertStatus(200);

        // Verify outstanding balance has decreased by 300,000 to become 200,000
        $this->assertEquals(200000.00, (float) $this->franchise->fresh()->account_balance);
    }
}
