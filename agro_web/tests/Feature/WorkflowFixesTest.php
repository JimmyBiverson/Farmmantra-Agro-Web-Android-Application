<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Conversation;
use App\Models\Franchise;
use App\Models\FranchiseInventory;
use App\Models\Order;
use App\Models\PaymentSubmission;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\User;
use App\Models\WarehouseInventory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkflowFixesTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $staffUser;

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

        $this->adminRole = Role::create(['name' => 'System Administrator', 'guard_name' => 'web']);
        $this->staffRole = Role::create(['name' => 'Farmmantra Staff', 'guard_name' => 'web']);
        $this->financeRole = Role::create(['name' => 'Finance Department', 'guard_name' => 'web']);
        $this->franchiseRole = Role::create(['name' => 'Franchise Partner', 'guard_name' => 'web']);

        $this->franchise = Franchise::create([
            'name' => 'Kampala Franchise Test',
            'code' => 'FRN-TEST-1',
            'credit_limit' => 1000000.00,
            'account_balance' => 0.00,
            'is_active' => true,
        ]);

        $this->adminUser = User::create([
            'name' => 'Admin User', 'email' => 'admin@test.com',
            'password' => bcrypt('password'), 'role_id' => $this->adminRole->id, 'is_active' => true,
        ]);
        $this->staffUser = User::create([
            'name' => 'Staff User', 'email' => 'staff@test.com',
            'password' => bcrypt('password'), 'role_id' => $this->staffRole->id, 'is_active' => true,
        ]);
        $this->financeUser = User::create([
            'name' => 'Finance User', 'email' => 'finance@test.com',
            'password' => bcrypt('password'), 'role_id' => $this->financeRole->id, 'is_active' => true,
        ]);
        $this->franchiseUser = User::create([
            'name' => 'Franchise Partner', 'email' => 'franchise@test.com',
            'password' => bcrypt('password'), 'role_id' => $this->franchiseRole->id,
            'franchise_id' => $this->franchise->id, 'is_active' => true,
        ]);

        $category = Category::create(['name' => 'Herbicides', 'slug' => 'herbicides']);

        $this->product = Product::create([
            'name' => 'Roundup Test', 'sku' => 'HERB-TEST-1', 'category_id' => $category->id,
            'standard_price' => 50000.00, 'selling_price' => 50000.00, 'is_active' => true,
        ]);

        WarehouseInventory::create([
            'product_id' => $this->product->id, 'quantity' => 100, 'reorder_level' => 10,
        ]);
    }

    private function placeOrder(int $quantity = 10): Order
    {
        Sanctum::actingAs($this->franchiseUser);
        $response = $this->postJson('/api/franchise/orders', [
            'items' => [['product_id' => $this->product->id, 'quantity' => $quantity]],
            'notes' => 'Test order',
        ]);
        $response->assertStatus(201);

        return Order::find($response->json('data.id'));
    }

    public function test_order_decline_records_audit_fields_and_item_status(): void
    {
        $order = $this->placeOrder();

        Sanctum::actingAs($this->staffUser);
        $response = $this->postJson("/api/staff/orders/{$order->id}/decline", [
            'decline_reason' => 'Stock unavailable',
        ]);

        $response->assertStatus(200);

        $order->refresh();
        $this->assertEquals('declined', $order->status);
        $this->assertEquals($this->staffUser->id, $order->declined_by);
        $this->assertNotNull($order->declined_at);
        $this->assertNull($order->approved_by);
        $this->assertEquals('Stock unavailable', $order->decline_reason);
        $this->assertEquals('declined', $order->items()->first()->status);
    }

    public function test_order_approve_marks_items_approved_and_decrements_warehouse(): void
    {
        $order = $this->placeOrder();

        Sanctum::actingAs($this->staffUser);
        $response = $this->postJson("/api/staff/orders/{$order->id}/approve", [
            'expected_delivery_date' => now()->addDays(5)->toDateString(),
        ]);

        $response->assertStatus(200);

        $order->refresh();
        $this->assertEquals('approved', $order->status);
        $this->assertEquals($this->staffUser->id, $order->approved_by);
        $this->assertNotNull($order->approved_at);
        $this->assertEquals('approved', $order->items()->first()->status);

        $warehouse = WarehouseInventory::where('product_id', $this->product->id)->first();
        $this->assertEquals(90, (float) $warehouse->quantity);
    }

    public function test_order_adjust_allows_fractional_quantity_and_marks_item_adjusted(): void
    {
        $order = $this->placeOrder(10);
        $item = $order->items()->first();

        Sanctum::actingAs($this->staffUser);
        $response = $this->postJson("/api/staff/orders/{$order->id}/adjust", [
            'items' => [
                [
                    'order_item_id' => $item->id,
                    'adjusted_quantity' => 2.5,
                    'adjustment_notes' => 'Filled in 2.5L containers',
                ],
            ],
        ]);

        $response->assertStatus(200);

        $item->refresh();
        $this->assertEquals('adjusted', $item->status);
        $this->assertEquals(2.5, (float) $item->adjusted_quantity);
        $this->assertEquals(125000.00, (float) $order->fresh()->total_amount);
    }

    public function test_credit_sale_records_credit_payment_status_and_decrements_inventory(): void
    {
        FranchiseInventory::create([
            'franchise_id' => $this->franchise->id,
            'product_id' => $this->product->id,
            'quantity' => 50,
            'reorder_level' => 5,
            'total_value' => 50 * $this->product->standard_price,
        ]);

        Sanctum::actingAs($this->franchiseUser);
        $response = $this->postJson('/api/franchise/sales', [
            'items' => [['product_id' => $this->product->id, 'quantity' => 5]],
            'payment_method' => 'credit',
            'notes' => 'Credit sale',
        ]);

        $response->assertStatus(201);

        $sale = Sale::find($response->json('data.id'));
        $this->assertEquals('credit', $sale->payment_status);

        $inventory = FranchiseInventory::where('franchise_id', $this->franchise->id)
            ->where('product_id', $this->product->id)->first();
        $this->assertEquals(45, (float) $inventory->quantity);
    }

    public function test_inventory_report_returns_computed_warehouse_total_value(): void
    {
        $warehouse = WarehouseInventory::where('product_id', $this->product->id)->first();
        $warehouse->update(['quantity' => 10]);

        Sanctum::actingAs($this->adminUser);
        $response = $this->getJson('/api/reports/inventory?type=warehouse');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertEquals(10 * $this->product->standard_price, (float) $data['summary']['total_value']);
        $this->assertEquals(10, (float) $data['summary']['total_quantity']);
    }

    public function test_finance_reject_records_rejection_audit_fields(): void
    {
        Sanctum::actingAs($this->franchiseUser);
        $paymentResponse = $this->postJson('/api/franchise/payments', [
            'amount' => 100000.00,
            'payment_method' => 'bank_transfer',
            'transaction_reference' => 'TXN-REJECT-1',
            'bank_name' => 'Centenary Bank',
        ]);
        $paymentResponse->assertStatus(201);
        $payment = PaymentSubmission::find($paymentResponse->json('data.id'));

        Sanctum::actingAs($this->financeUser);
        $this->postJson("/api/finance/payments/{$payment->id}/verify", [
            'verified_amount' => 100000.00,
        ])->assertStatus(200);

        $rejectResponse = $this->postJson("/api/finance/payments/{$payment->id}/reject", [
            'rejection_reason' => 'Mismatched reference',
        ]);
        $rejectResponse->assertStatus(200);

        $payment->refresh();
        $this->assertEquals('rejected', $payment->status);
        $this->assertEquals($this->financeUser->id, $payment->rejected_by);
        $this->assertNotNull($payment->rejected_at);
        $this->assertNull($payment->accepted_by);
    }

    public function test_admin_can_create_product_with_image_upload(): void
    {
        Storage::fake('public');

        Sanctum::actingAs($this->adminUser);
        $response = $this->postJson('/api/admin/products', [
            'name' => 'Test Herbicide X',
            'sku' => 'HERB-TEST-IMG-1',
            'category_id' => Category::where('name', 'Herbicides')->first()->id,
            'unit_of_measure' => 'Litres',
            'standard_price' => 30000.00,
            'image' => UploadedFile::fake()->image('product.png', 10, 10),
        ]);

        $response->assertStatus(201);

        $product = Product::where('sku', 'HERB-TEST-IMG-1')->first();
        $this->assertNotNull($product);
        $this->assertNotNull($product->image);
        $this->assertStringContainsString('products/', $product->image);
        Storage::disk('public')->assertExists($product->image);
        $this->assertNotNull($product->image_url);
        $this->assertStringContainsString('storage/'.$product->image, $product->image_url);
    }

    public function test_products_api_includes_image_url(): void
    {
        $this->product->update(['image' => 'products/'.$this->product->sku.'.png']);

        Sanctum::actingAs($this->franchiseUser);
        $response = $this->getJson('/api/products?search='.urlencode($this->product->sku));

        $response->assertStatus(200);
        $items = $response->json('data') ?? collect($response->json('data'));
        $found = collect($items)->firstWhere('id', $this->product->id);

        $this->assertNotNull($found);
        $this->assertEquals('products/'.$this->product->sku.'.png', $found['image']);
        $this->assertStringContainsString('storage/products/'.$this->product->sku.'.png', $found['image_url']);
    }

    public function test_admin_user_creation_requires_matching_password_confirmation(): void
    {
        $this->actingAs($this->adminUser);

        $response = $this->from('/admin/users')->post('/admin/users', [
            'name' => 'New Admin User',
            'email' => 'new-admin@test.com',
            'password' => 'StrongPass123',
            'password_confirmation' => 'DifferentPass123',
            'role_id' => $this->staffRole->id,
            'franchise_id' => null,
        ]);

        $response->assertSessionHasErrors(['password']);
        $this->assertDatabaseMissing('users', ['email' => 'new-admin@test.com']);
    }

    public function test_admin_password_reset_requires_matching_confirmation(): void
    {
        $targetUser = User::create([
            'name' => 'Reset Target User',
            'email' => 'resettarget@test.com',
            'password' => Hash::make('old-password'),
            'role_id' => $this->staffRole->id,
            'is_active' => true,
        ]);

        $this->actingAs($this->adminUser);

        $response = $this->from('/admin/settings/users')->post('/admin/users/reset-password', [
            'user_id' => $targetUser->id,
            'new_password' => 'NewSecurePass123',
            'new_password_confirmation' => 'DifferentSecurePass123',
        ]);

        $response->assertSessionHasErrors(['new_password']);
        $this->assertTrue(Hash::check('old-password', $targetUser->fresh()->password));
    }

    public function test_admin_can_send_multiple_replies_in_one_support_conversation(): void
    {
        $conversation = Conversation::create([
            'franchise_id' => $this->franchise->id,
            'created_by' => $this->franchiseUser->id,
            'subject' => 'Support request',
            'priority' => 'normal',
            'status' => 'open',
        ]);
        $conversation->messages()->create([
            'sender_id' => $this->franchiseUser->id,
            'message' => 'I need help with my order.',
        ]);

        $this->actingAs($this->adminUser);

        $this->postJson("/admin/chat/{$conversation->id}/send", ['message' => 'We are checking this now.'])
            ->assertOk()
            ->assertJsonPath('status', 'success');
        $this->postJson("/admin/chat/{$conversation->id}/send", ['message' => 'The update is complete.'])
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertSame(3, $conversation->messages()->count());
        $this->assertSame(2, $conversation->messages()->where('sender_id', $this->adminUser->id)->count());
    }

    public function test_admin_conversation_link_opens_reply_composer_for_selected_user(): void
    {
        $conversation = Conversation::create([
            'franchise_id' => $this->franchise->id,
            'created_by' => $this->franchiseUser->id,
            'subject' => 'Selected support request',
            'priority' => 'normal',
            'status' => 'open',
        ]);

        $this->actingAs($this->adminUser);

        $this->get('/admin/chat?conversation_id='.$conversation->id)
            ->assertOk()
            ->assertSee('Selected support request')
            ->assertSee('id="adminChatForm"', false)
            ->assertSee('action="'.route('web.admin.chat.send', ['id' => $conversation->id]).'"', false)
            ->assertSee('Type reply to '.$this->franchiseUser->name, false);
    }

    public function test_web_pages_render_for_all_roles(): void
    {
        // Franchise: dashboard + key pages
        $this->actingAs($this->franchiseUser);
        $this->get('/dashboard')->assertStatus(200);
        $this->get('/franchise/inventory')->assertStatus(200);
        $this->get('/franchise/orders')->assertStatus(200);
        $this->get('/franchise/sales')->assertStatus(200);
        $this->get('/franchise/payments')->assertStatus(200);
        $this->get('/profile')->assertStatus(200);

        // Staff: dashboard + operations pages
        $this->actingAs($this->staffUser);
        $this->get('/dashboard')->assertStatus(200);
        $this->get('/staff/orders')->assertStatus(200);
        $this->get('/staff/inventory')->assertStatus(200);
        $this->get('/staff/franchise-stock')->assertStatus(200);
        $this->get('/staff/stock-receipts')->assertStatus(200);

        // Finance
        $this->actingAs($this->financeUser);
        $this->get('/dashboard')->assertStatus(200);
        $this->get('/finance/payments')->assertStatus(200);
        $this->get('/finance/reports')->assertStatus(200);

        // Admin
        $this->actingAs($this->adminUser);
        $this->get('/dashboard')->assertStatus(200);
        $this->get('/admin/franchises')->assertStatus(200);
        $this->get('/admin/users')->assertStatus(200);
        $this->get('/admin/products')->assertStatus(200);
        $this->get('/admin/categories')->assertStatus(200);
        $this->get('/admin/news')->assertStatus(200);
        $this->get('/admin/faqs')->assertStatus(200);
        $this->get('/admin/slides')->assertStatus(200);
        $this->get('/admin/pages')->assertStatus(200);
        $this->get('/admin/orders')->assertStatus(200);
        $this->get('/admin/payments')->assertStatus(200);
        $this->get('/admin/reports')->assertStatus(200);
        $this->get('/admin/audit')->assertStatus(200);
        $this->get('/admin/stock-movements')->assertStatus(200);
        $this->get('/admin/settings')->assertStatus(200);
        $this->get('/admin/settings/site')->assertStatus(200);
        $this->get('/admin/settings/notifications')->assertStatus(200);
        $this->get('/admin/settings/roles')->assertStatus(200);
        $this->get('/admin/settings/system')->assertStatus(200);
        $this->get('/admin/settings/users')->assertStatus(200);
    }
}
