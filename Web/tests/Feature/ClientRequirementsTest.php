<?php

namespace Tests\Feature;

use App\Models\AccountTransaction;
use App\Models\Category;
use App\Models\Customer;
use App\Models\DigitalSignature;
use App\Models\District;
use App\Models\Franchise;
use App\Models\FranchiseInventory;
use App\Models\News;
use App\Models\Order;
use App\Models\PaymentSubmission;
use App\Models\Product;
use App\Models\Region;
use App\Models\Role;
use App\Models\SmsMessage;
use App\Models\StockReceipt;
use App\Models\User;
use App\Models\WarehouseInventory;
use App\Services\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Covers the October 2026 client requirements (handwritten notes) and the
 * defects fixed while implementing them.
 */
class ClientRequirementsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $staff;

    private User $warehouse;

    private User $finance;

    private User $gulu;

    private User $kampala;

    private Franchise $guluFranchise;

    private Franchise $kampalaFranchise;

    private Product $abc1l;

    private Product $abc20l;

    private Region $guluRegion;

    protected function setUp(): void
    {
        parent::setUp();

        $roles = [];
        foreach (['System Administrator', 'Farmmantra Staff', 'Finance Department', 'Franchise Partner', 'Warehouse'] as $name) {
            $roles[$name] = Role::firstOrCreate(['name' => $name, 'guard_name' => 'web'])->id;
        }

        // The migration seeds districts and the four official regions; add the
        // client's example business region "Gulu Region".
        $this->guluRegion = Region::create(['name' => 'Gulu Region']);
        District::whereIn('name', ['Gulu', 'Amuru', 'Omoro'])->update(['region_id' => $this->guluRegion->id]);
        $central = Region::where('name', 'Central')->firstOrFail();

        $this->guluFranchise = Franchise::create(['name' => 'Gulu Franchise', 'code' => 'GUL', 'contact_person' => 'Okello', 'phone' => '0772000001', 'credit_limit' => 0, 'account_balance' => 0, 'is_active' => true, 'region_id' => $this->guluRegion->id]);
        $this->kampalaFranchise = Franchise::create(['name' => 'Kampala Franchise', 'code' => 'KLA', 'credit_limit' => 0, 'account_balance' => 0, 'is_active' => true, 'region_id' => $central->id]);

        $mk = fn ($name, $email, $role, $franchise = null) => User::create([
            'name' => $name, 'email' => $email, 'password' => bcrypt('password'), 'role_id' => $roles[$role],
            'franchise_id' => $franchise?->id, 'is_active' => true, 'phone' => '0701000'.rand(100, 999),
            'terms_accepted_at' => $franchise ? now() : null, 'terms_version' => $franchise ? 1 : null,
        ]);
        $this->admin = $mk('Admin', 'admin@t.test', 'System Administrator');
        $this->staff = $mk('Sales Staff', 'staff@t.test', 'Farmmantra Staff');
        $this->warehouse = $mk('Warehouse', 'wh@t.test', 'Warehouse');
        $this->finance = $mk('Finance', 'fin@t.test', 'Finance Department');
        $this->gulu = $mk('Gulu Partner', 'gulu@t.test', 'Franchise Partner', $this->guluFranchise);
        $this->kampala = $mk('Kampala Partner', 'kla@t.test', 'Franchise Partner', $this->kampalaFranchise);

        $cat = Category::create(['name' => 'Herbicides', 'slug' => 'herbicides']);
        // Client sheet: ABC 1Ltr — Franchise 10,000 / Retail 10,500 / Farmer 11,400
        $this->abc1l = Product::create(['name' => 'ABC 1Ltr', 'sku' => 'ABC-1L', 'category_id' => $cat->id, 'standard_price' => 10000, 'selling_price' => 10500, 'farmer_price' => 11400, 'is_active' => true]);
        $this->abc20l = Product::create(['name' => 'ABC 20Ltr', 'sku' => 'ABC-20L', 'category_id' => $cat->id, 'standard_price' => 200000, 'selling_price' => 205000, 'farmer_price' => 210000, 'is_active' => true]);
        WarehouseInventory::create(['product_id' => $this->abc1l->id, 'quantity' => 1000, 'reorder_level' => 10]);
        WarehouseInventory::create(['product_id' => $this->abc20l->id, 'quantity' => 100, 'reorder_level' => 5]);
    }

    private function placeOrder(User $user, array $items, string $terms = 'credit'): Order
    {
        Sanctum::actingAs($user);
        $res = $this->postJson('/api/franchise/orders', ['items' => $items, 'payment_terms' => $terms]);
        $res->assertStatus(201);

        return Order::findOrFail($res->json('data.id'));
    }

    private function approve(Order $order): void
    {
        Sanctum::actingAs($this->warehouse);
        $this->postJson("/api/staff/orders/{$order->id}/approve", ['expected_delivery_date' => now()->addDays(3)->toDateString()])->assertOk();
    }

    private function dispatch(Order $order): void
    {
        Sanctum::actingAs($this->warehouse);
        $this->postJson("/api/staff/orders/{$order->id}/dispatch", ['dispatch_means' => 'bus', 'dispatch_vehicle' => 'Gateway Bus', 'dispatch_remarks' => 'Parcel office'])->assertOk();
    }

    private function receiveAll(Order $order, User $user, array $quantities = []): void
    {
        $receipt = StockReceipt::where('order_id', $order->id)->firstOrFail();
        Sanctum::actingAs($user);
        $items = $receipt->items->map(fn ($i) => [
            'stock_receipt_item_id' => $i->id,
            'received_quantity' => $quantities[$i->product_id] ?? (float) $i->ordered_quantity,
        ])->all();
        $this->postJson("/api/franchise/stock-receipts/{$receipt->id}/confirm", ['items' => $items, 'accept_terms' => true])->assertOk();
    }

    private function pay(User $user, float $amount): PaymentSubmission
    {
        Sanctum::actingAs($user);
        $res = $this->postJson('/api/franchise/payments', ['amount' => $amount, 'payment_method' => 'mobile_money', 'transaction_reference' => 'MM'.rand(1000, 9999)]);
        $res->assertStatus(201);
        $payment = PaymentSubmission::findOrFail($res->json('data.id'));
        Sanctum::actingAs($this->finance);
        $this->postJson("/api/finance/payments/{$payment->id}/verify", ['verified_amount' => $amount])->assertOk();
        $this->postJson("/api/finance/payments/{$payment->id}/accept")->assertOk();

        return $payment->fresh();
    }

    // ── Roles: warehouse accepts & dispatches; staff only monitor ────
    public function test_warehouse_accepts_and_dispatches_while_sales_staff_only_monitor(): void
    {
        $order = $this->placeOrder($this->gulu, [['product_id' => $this->abc1l->id, 'quantity' => 10]]);

        Sanctum::actingAs($this->staff);
        $this->getJson('/api/staff/orders')->assertOk();
        $this->getJson("/api/staff/orders/{$order->id}")->assertOk();
        $this->postJson("/api/staff/orders/{$order->id}/approve", ['expected_delivery_date' => now()->addDay()->toDateString()])->assertForbidden();
        $this->postJson("/api/staff/orders/{$order->id}/decline", ['decline_reason' => 'x'])->assertForbidden();
        $this->postJson('/api/staff/warehouse-stock', ['product_id' => $this->abc1l->id, 'quantity' => 5])->assertForbidden();

        $this->approve($order);
        $order->refresh();
        $this->assertSame('approved', $order->status);
        $this->assertNotNull($order->approval_signature);
        $this->assertDatabaseHas('digital_signatures', ['signature_number' => $order->approval_signature, 'user_id' => $this->warehouse->id, 'action' => 'order_approved']);

        Sanctum::actingAs($this->staff);
        $this->postJson("/api/staff/orders/{$order->id}/dispatch", ['dispatch_means' => 'truck'])->assertForbidden();

        $this->dispatch($order);
        $order->refresh();
        $this->assertSame('out_for_delivery', $order->delivery_status);
        $this->assertSame('bus', $order->dispatch_means);
        $this->assertSame('Gateway Bus', $order->dispatch_vehicle);
        $this->assertSame($this->warehouse->id, $order->dispatched_by);
        $this->assertNotNull($order->dispatch_signature);
    }

    public function test_dispatch_requires_means_and_cash_orders_need_accepted_payment(): void
    {
        $cash = $this->placeOrder($this->gulu, [['product_id' => $this->abc1l->id, 'quantity' => 5]], 'cash');
        $this->approve($cash);

        Sanctum::actingAs($this->warehouse);
        $this->postJson("/api/staff/orders/{$cash->id}/dispatch", [])->assertStatus(422)->assertJsonValidationErrors('dispatch_means');
        $this->postJson("/api/staff/orders/{$cash->id}/dispatch", ['dispatch_means' => 'truck'])->assertStatus(422);

        // Pay for the cash order (linked) → ready → dispatch allowed.
        Sanctum::actingAs($this->gulu);
        $res = $this->postJson('/api/franchise/payments', ['amount' => 50000, 'payment_method' => 'bank_transfer', 'transaction_reference' => 'BT1', 'order_ids' => [$cash->id]])->assertStatus(201);
        Sanctum::actingAs($this->finance);
        $pid = $res->json('data.id');
        $this->postJson("/api/finance/payments/{$pid}/verify", ['verified_amount' => 50000])->assertOk();
        $this->postJson("/api/finance/payments/{$pid}/accept")->assertOk();
        $this->assertSame('ready_for_delivery', $cash->fresh()->delivery_status);
        $this->assertNotNull(PaymentSubmission::find($pid)->acceptance_signature);

        $this->dispatch($cash);
        $this->assertSame('out_for_delivery', $cash->fresh()->delivery_status);
    }

    public function test_cash_payment_methods_are_mobile_money_or_bank_transfer_only(): void
    {
        Sanctum::actingAs($this->gulu);
        $this->postJson('/api/franchise/payments', ['amount' => 1000, 'payment_method' => 'cash', 'transaction_reference' => 'X'])->assertStatus(422);
        $this->postJson('/api/franchise/payments', ['amount' => 1000, 'payment_method' => 'mobile_money'])->assertStatus(422)->assertJsonValidationErrors('transaction_reference');
        $this->postJson('/api/franchise/payments', ['amount' => 1000, 'payment_method' => 'mobile_money', 'transaction_reference' => 'MM1'])->assertStatus(201);
    }

    // ── Franchise must accept the delivered order + T&C ──────────────
    public function test_franchise_must_accept_delivery_with_terms_and_is_charged_for_what_it_received(): void
    {
        $order = $this->placeOrder($this->gulu, [['product_id' => $this->abc1l->id, 'quantity' => 10]]);
        $this->approve($order);
        $receipt = StockReceipt::where('order_id', $order->id)->firstOrFail();
        $item = $receipt->items->first();

        // Not dispatched yet → cannot accept (loophole closed).
        Sanctum::actingAs($this->gulu);
        $this->postJson("/api/franchise/stock-receipts/{$receipt->id}/confirm", ['items' => [['stock_receipt_item_id' => $item->id, 'received_quantity' => 10]], 'accept_terms' => true])->assertStatus(422);

        $this->dispatch($order);

        Sanctum::actingAs($this->gulu);
        $this->postJson("/api/franchise/stock-receipts/{$receipt->id}/confirm", ['items' => [['stock_receipt_item_id' => $item->id, 'received_quantity' => 10]]])->assertStatus(422)->assertJsonValidationErrors('accept_terms');
        // Cannot "receive" more than was dispatched.
        $this->postJson("/api/franchise/stock-receipts/{$receipt->id}/confirm", ['items' => [['stock_receipt_item_id' => $item->id, 'received_quantity' => 11]], 'accept_terms' => true])->assertStatus(422);

        // 8 of 10 arrived (2 damaged).
        $this->postJson("/api/franchise/stock-receipts/{$receipt->id}/confirm", ['items' => [['stock_receipt_item_id' => $item->id, 'received_quantity' => 8, 'discrepancy_notes' => '2 damaged']], 'accept_terms' => true])->assertOk();

        $order->refresh();
        $this->assertSame('confirmed', $order->delivery_status);
        $this->assertSame('delivered', $order->status);
        $this->assertNotNull($order->delivery_signature);
        $this->assertSame($this->gulu->id, $order->delivery_confirmed_by);
        $this->assertNotNull($receipt->fresh()->terms_accepted_at);
        $this->assertEquals(8, (float) FranchiseInventory::where('franchise_id', $this->guluFranchise->id)->value('quantity'));
        $this->assertEquals(80000, (float) $this->guluFranchise->fresh()->account_balance);
        $this->assertDatabaseHas('account_transactions', ['franchise_id' => $this->guluFranchise->id, 'type' => 'purchase', 'credit' => 80000]);
        $this->assertSame(3, DigitalSignature::where('signable_type', Order::class)->where('signable_id', $order->id)->count());
    }

    // ── Daily payments applied oldest-first (client example) ─────────
    public function test_daily_payments_are_applied_to_orders_oldest_first_like_the_client_example(): void
    {
        // Order 1 = 1,000,000 ; Order 2 = 500,000 (client's handwritten example)
        $o1 = $this->placeOrder($this->gulu, [['product_id' => $this->abc1l->id, 'quantity' => 100]]);
        $this->approve($o1);
        $this->dispatch($o1);
        $this->receiveAll($o1, $this->gulu);
        $this->pay($this->gulu, 900000);

        $this->assertEquals(900000, (float) $o1->fresh()->amount_paid);
        $this->assertEquals(100000, (float) $this->guluFranchise->fresh()->account_balance);

        $o2 = $this->placeOrder($this->gulu, [['product_id' => $this->abc1l->id, 'quantity' => 50]]);
        $this->approve($o2);
        $this->dispatch($o2);
        $this->receiveAll($o2, $this->gulu);
        $this->pay($this->gulu, 200000);

        $this->assertEquals(1000000, (float) $o1->fresh()->amount_paid, 'Order 1 is now fully paid');
        $this->assertEquals(100000, (float) $o2->fresh()->amount_paid);
        $this->assertEquals(400000, $o2->fresh()->balance_due);
        $this->assertEquals(400000, (float) $this->guluFranchise->fresh()->account_balance, 'Balance 400,000 as in the client sheet');
    }

    // ── Statement with credit notes and sales returns ────────────────
    public function test_account_statement_shows_purchases_payments_credit_notes_and_returns_with_running_balance(): void
    {
        $o = $this->placeOrder($this->gulu, [['product_id' => $this->abc1l->id, 'quantity' => 100]]);
        $this->approve($o);
        $this->dispatch($o);
        $this->receiveAll($o, $this->gulu);              // purchase 1,000,000
        $this->pay($this->gulu, 500000);                 // payment   500,000

        $this->actingAs($this->finance)->post(route('web.accounts.creditNotes.store'), [
            'type' => 'credit_note', 'franchise_id' => $this->guluFranchise->id, 'amount' => 200000, 'reason' => 'Price correction',
        ])->assertSessionHas('success');
        $this->actingAs($this->finance)->post(route('web.accounts.creditNotes.store'), [
            'type' => 'sales_return', 'franchise_id' => $this->guluFranchise->id, 'reason' => 'Expired stock returned',
            'items' => [['product_id' => $this->abc1l->id, 'quantity' => 10]],
        ])->assertSessionHas('success');

        // Sales return moves goods back to the warehouse.
        $this->assertEquals(90, (float) FranchiseInventory::where('franchise_id', $this->guluFranchise->id)->value('quantity'));
        $this->assertEquals(1000 - 100 + 10, (float) WarehouseInventory::where('product_id', $this->abc1l->id)->value('quantity'));

        Sanctum::actingAs($this->gulu);
        $statement = $this->getJson('/api/franchise/statement')->assertOk()->json('data');
        $this->assertSame(['purchase', 'payment', 'credit_note', 'sales_return'], array_column($statement['transactions'], 'type'));
        $this->assertSame([1000000, 500000, 300000, 200000], array_map(fn ($t) => (int) $t['balance'], $statement['transactions']));
        $this->assertEquals(200000, $statement['closing_balance']);
        $this->assertEquals($statement['closing_balance'], $statement['current_balance']);
        $this->assertSame('Okello', $statement['franchise']['contact_person']);

        // Finance can read any franchise statement; the franchise only its own.
        Sanctum::actingAs($this->finance);
        $this->getJson("/api/statements/{$this->guluFranchise->id}")->assertOk();
        Sanctum::actingAs($this->kampala);
        $this->getJson("/api/statements/{$this->guluFranchise->id}")->assertForbidden();

        // Web pages render.
        $this->actingAs($this->gulu)->get(route('web.franchise.statement'))->assertOk()->assertSee('Credit Note');
        $this->actingAs($this->finance)->get(route('web.accounts.statements', ['franchise_id' => $this->guluFranchise->id]))->assertOk()->assertSee('Sales Return');
    }

    // ── Pricing tiers, profit, no free pricing ───────────────────────
    public function test_sales_use_admin_fixed_retail_or_farmer_price_and_profit_is_reported(): void
    {
        FranchiseInventory::create(['franchise_id' => $this->guluFranchise->id, 'product_id' => $this->abc1l->id, 'quantity' => 100, 'total_value' => 1000000]);
        $retailer = Customer::create(['franchise_id' => $this->guluFranchise->id, 'name' => 'Agro Shop', 'customer_type' => 'retailer', 'is_active' => true]);
        $farmer = Customer::create(['franchise_id' => $this->guluFranchise->id, 'name' => 'Farmer Ann', 'customer_type' => 'farmer', 'is_active' => true]);

        Sanctum::actingAs($this->gulu);
        $r = $this->postJson('/api/franchise/sales', ['customer_id' => $retailer->id, 'items' => [['product_id' => $this->abc1l->id, 'quantity' => 2]], 'payment_method' => 'cash'])->assertStatus(201);
        $this->assertEquals(21000, (float) $r->json('data.final_amount'));
        $this->assertSame('retailer', $r->json('data.price_tier'));

        $f = $this->postJson('/api/franchise/sales', ['customer_id' => $farmer->id, 'items' => [['product_id' => $this->abc1l->id, 'quantity' => 1]], 'payment_method' => 'mobile_money'])->assertStatus(201);
        $this->assertEquals(11400, (float) $f->json('data.final_amount'));

        // Discounts are blocked unless the admin allows them.
        $this->postJson('/api/franchise/sales', ['items' => [['product_id' => $this->abc1l->id, 'quantity' => 1]], 'payment_method' => 'cash', 'discount' => 500])->assertStatus(422);

        $summary = $this->getJson('/api/franchise/dashboard')->assertOk()->json('data.summary');
        $this->assertSame(2, $summary['sales_count_this_month']);
        $this->assertEquals((21000 + 11400) - 30000, $summary['profit_this_month']);
        $this->assertEquals(97, (float) FranchiseInventory::where('franchise_id', $this->guluFranchise->id)->value('quantity'));
    }

    // ── Districts / regions / customer visibility ────────────────────
    public function test_franchise_only_sees_customers_in_its_region_and_admin_assigns_customers(): void
    {
        $guluDistrict = District::where('name', 'Gulu')->firstOrFail();
        $wakiso = District::where('name', 'Wakiso')->firstOrFail();
        $this->assertGreaterThan(130, District::count(), 'All districts of Uganda are seeded');

        // Admin creates a farmer in Amuru (Gulu Region) without picking a franchise → auto-assigned to Gulu.
        $amuru = District::where('name', 'Amuru')->firstOrFail();
        $this->actingAs($this->admin)->post(route('web.admin.customers.store'), [
            'name' => 'Amuru Farmer', 'customer_type' => 'farmer', 'district_id' => $amuru->id, 'phone' => '0772111222',
        ])->assertSessionHas('success');
        $this->assertSame($this->guluFranchise->id, Customer::where('name', 'Amuru Farmer')->value('franchise_id'));

        // A retailer registered by Kampala but located in Gulu district is visible to Gulu (region rule).
        Customer::create(['franchise_id' => $this->kampalaFranchise->id, 'name' => 'Gulu Retailer', 'customer_type' => 'retailer', 'district_id' => $guluDistrict->id, 'is_active' => true]);
        Customer::create(['franchise_id' => $this->kampalaFranchise->id, 'name' => 'Wakiso Retailer', 'customer_type' => 'retailer', 'district_id' => $wakiso->id, 'is_active' => true]);

        Sanctum::actingAs($this->gulu);
        $names = collect($this->getJson('/api/franchise/customers')->assertOk()->json('data'))->pluck('name')->all();
        $this->assertContains('Amuru Farmer', $names);
        $this->assertContains('Gulu Retailer', $names);
        $this->assertNotContains('Wakiso Retailer', $names);

        // Franchise may register customers only in its own region's districts.
        $this->postJson('/api/franchise/customers', ['name' => 'X', 'customer_type' => 'farmer', 'district_id' => $wakiso->id])->assertStatus(422);
        $this->postJson('/api/franchise/customers', ['name' => 'Omoro Farmer', 'customer_type' => 'farmer', 'district_id' => District::where('name', 'Omoro')->value('id')])->assertStatus(201);
        $districts = collect($this->getJson('/api/franchise/districts')->assertOk()->json('data'))->pluck('name')->all();
        $this->assertEqualsCanonicalizing(['Gulu', 'Amuru', 'Omoro'], $districts);

        // Cannot sell to a customer outside the region.
        FranchiseInventory::create(['franchise_id' => $this->guluFranchise->id, 'product_id' => $this->abc1l->id, 'quantity' => 5, 'total_value' => 50000]);
        $outside = Customer::where('name', 'Wakiso Retailer')->first();
        $this->postJson('/api/franchise/sales', ['customer_id' => $outside->id, 'items' => [['product_id' => $this->abc1l->id, 'quantity' => 1]], 'payment_method' => 'cash'])->assertStatus(422);

        // Admin assigns districts to a region from the Regions page.
        $this->actingAs($this->admin)->post(route('web.admin.regions.update', $this->guluRegion), [
            'name' => 'Gulu Region', 'district_ids' => [$guluDistrict->id, $amuru->id], 'franchise_ids' => [$this->guluFranchise->id],
        ])->assertSessionHas('success');
        $this->assertNull(District::where('name', 'Omoro')->value('region_id'));
    }

    // ── Terms & conditions at first login ────────────────────────────
    public function test_franchise_must_accept_terms_on_first_login_web_and_api(): void
    {
        $this->gulu->update(['terms_accepted_at' => null, 'terms_version' => null]);

        Sanctum::actingAs($this->gulu->fresh());
        $this->getJson('/api/me')->assertOk()->assertJsonPath('user.needs_terms_acceptance', true);
        $this->getJson('/api/franchise/orders')->assertForbidden()->assertJsonPath('code', 'terms_required');
        $this->getJson('/api/terms')->assertOk()->assertJsonPath('data.needs_acceptance', true);
        $this->postJson('/api/terms/accept', ['accept' => true])->assertOk();
        $this->getJson('/api/franchise/orders')->assertOk();

        // Web: redirected to the terms page until accepted.
        $this->kampala->update(['terms_accepted_at' => null]);
        $this->actingAs($this->kampala->fresh())->get('/dashboard')->assertRedirect(route('web.terms.show'));
        $this->get(route('web.terms.show'))->assertOk();
        $this->post(route('web.terms.accept'), ['accept' => '1'])->assertRedirect(route('web.dashboard'));
        $this->get('/dashboard')->assertOk();

        // Publishing a new version requires re-acceptance.
        $this->actingAs($this->admin)->post(route('web.admin.terms.update'), [
            'terms' => str_repeat('Updated terms. ', 5), 'require_reacceptance' => '1', 'dispatch_target_days' => 15,
        ])->assertSessionHas('success');
        $this->assertTrue($this->kampala->fresh()->needsToAcceptTerms());
        $this->assertFalse($this->admin->fresh()->needsToAcceptTerms(), 'Only franchise partners are gated');
    }

    // ── Admin security: deactivate instead of delete ─────────────────
    public function test_admin_deactivates_instead_of_deleting_and_deletions_require_password_and_reason(): void
    {
        // Without password: blocked.
        $this->actingAs($this->admin)->post(route('web.admin.users.delete'), ['id' => $this->staff->id, 'action_reason' => 'Left company'])->assertSessionHas('error');
        $this->assertTrue($this->staff->fresh()->is_active);

        // Wrong password: blocked and logged.
        $this->actingAs($this->admin)->post(route('web.admin.users.delete'), ['id' => $this->staff->id, 'confirm_password' => 'nope', 'action_reason' => 'Left company'])->assertSessionHas('error');
        $this->assertDatabaseHas('activity_logs', ['action' => 'security.confirmation_failed']);

        // Correct: user is deactivated, NOT deleted.
        $this->actingAs($this->admin)->post(route('web.admin.users.delete'), ['id' => $this->staff->id, 'confirm_password' => 'password', 'action_reason' => 'Left company'])->assertSessionHas('success');
        $this->assertDatabaseHas('users', ['id' => $this->staff->id, 'is_active' => false]);

        // Products and franchises are deactivated too (history preserved).
        $this->actingAs($this->admin)->post(route('web.admin.products.delete'), ['id' => $this->abc20l->id, 'confirm_password' => 'password', 'action_reason' => 'Discontinued'])->assertRedirect();
        $this->assertDatabaseHas('products', ['id' => $this->abc20l->id, 'is_active' => false]);
        $this->actingAs($this->admin)->post(route('web.admin.franchises.delete'), ['id' => $this->kampalaFranchise->id, 'confirm_password' => 'password', 'action_reason' => 'Closed'])->assertRedirect();
        $this->assertDatabaseHas('franchises', ['id' => $this->kampalaFranchise->id, 'is_active' => false]);

        // Content can still be deleted, but only with confirmation.
        $news = News::create(['title' => 'Old', 'slug' => 'old', 'body' => 'x', 'is_published' => true]);
        $this->actingAs($this->admin)->post(route('web.admin.news.delete'), ['id' => $news->id])->assertSessionHas('error');
        $this->assertDatabaseHas('news', ['id' => $news->id]);
        $this->actingAs($this->admin)->post(route('web.admin.news.delete'), ['id' => $news->id, 'confirm_password' => 'password', 'action_reason' => 'Outdated'])->assertRedirect();
        $this->assertDatabaseMissing('news', ['id' => $news->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'security.confirmed_action']);

        // A deactivated user can no longer use the web dashboard.
        $this->actingAs($this->staff->fresh())->get('/dashboard')->assertRedirect(route('login'));
    }

    // ── Printing ─────────────────────────────────────────────────────
    public function test_documents_are_printable_by_all_roles_but_franchises_only_see_their_own(): void
    {
        $order = $this->placeOrder($this->gulu, [['product_id' => $this->abc1l->id, 'quantity' => 3]]);
        $this->approve($order);

        $this->actingAs($this->gulu)->get(route('web.print', ['type' => 'order', 'id' => $order->id]))->assertOk()->assertSee($order->order_number)->assertSee($order->fresh()->approval_signature);
        $this->actingAs($this->kampala)->get(route('web.print', ['type' => 'order', 'id' => $order->id]))->assertForbidden();
        foreach ([$this->admin, $this->staff, $this->warehouse, $this->finance] as $u) {
            $this->actingAs($u)->get(route('web.print', ['type' => 'delivery-note', 'id' => $order->id]))->assertOk();
        }
        $this->actingAs($this->gulu)->get(route('web.print', ['type' => 'statement', 'id' => $this->guluFranchise->id]))->assertOk()->assertSee('Franchise Account Statement');
        $this->actingAs($this->admin)->get(route('web.print', ['type' => 'stock-report', 'id' => 0, 'scope' => 'company']))->assertOk()->assertSee('ABC 1Ltr');

        // Mobile: signed link.
        Sanctum::actingAs($this->gulu);
        $url = $this->getJson("/api/print-links/order/{$order->id}")->assertOk()->json('data.url');
        $this->get($url)->assertOk()->assertSee($order->order_number);
        $this->getJson("/api/print-links/order/{$order->id}")->assertOk();
        Sanctum::actingAs($this->kampala);
        $this->getJson("/api/print-links/order/{$order->id}")->assertForbidden();
        // Tampered link is rejected.
        $this->get(str_replace('user='.$this->gulu->id, 'user='.$this->admin->id, $url))->assertForbidden();
    }

    public function test_stock_reports_for_company_and_franchise(): void
    {
        FranchiseInventory::create(['franchise_id' => $this->guluFranchise->id, 'product_id' => $this->abc1l->id, 'quantity' => 7, 'total_value' => 70000]);
        FranchiseInventory::create(['franchise_id' => $this->kampalaFranchise->id, 'product_id' => $this->abc1l->id, 'quantity' => 9, 'total_value' => 90000]);

        Sanctum::actingAs($this->staff);
        $this->getJson('/api/stock-report?scope=company')->assertOk()->assertJsonPath('data.scope', 'company');
        $this->getJson('/api/stock-report?scope=franchise')->assertOk()->assertJsonCount(2, 'data.items');

        Sanctum::actingAs($this->gulu);
        $data = $this->getJson('/api/stock-report?scope=company')->assertOk()->json('data');
        $this->assertSame('franchise', $data['scope'], 'Franchise always gets its own stock');
        $this->assertCount(1, $data['items']);
        $this->assertEquals(70000, $data['total_value']);

        Sanctum::actingAs($this->finance);
        $this->getJson('/api/stock-report')->assertForbidden();

        $this->actingAs($this->gulu)->get(route('web.reports.stock'))->assertOk();
        $this->actingAs($this->staff)->get(route('web.reports.stock', ['scope' => 'franchise']))->assertOk()->assertSee('Kampala Franchise');
    }

    // ── 15-day dispatch target ───────────────────────────────────────
    public function test_orders_not_dispatched_within_fifteen_days_are_flagged(): void
    {
        $order = $this->placeOrder($this->gulu, [['product_id' => $this->abc1l->id, 'quantity' => 1]]);
        $this->assertFalse($order->fresh()->is_dispatch_overdue);

        $order->forceFill(['created_at' => now()->subDays(16)])->saveQuietly();
        $this->assertTrue($order->fresh()->is_dispatch_overdue);
        $this->assertSame(1, Order::dispatchOverdue()->count());

        Sanctum::actingAs($this->warehouse);
        $this->getJson('/api/staff/dashboard')->assertOk()->assertJsonPath('data.summary.overdue_dispatch_orders', 1);

        $this->approve($order);
        $this->dispatch($order);
        $this->assertSame(0, Order::dispatchOverdue()->count());
    }

    // ── Bulk SMS ─────────────────────────────────────────────────────
    public function test_bulk_sms_to_customers_of_a_region_is_recorded(): void
    {
        $gulu = District::where('name', 'Gulu')->first();
        Customer::create(['franchise_id' => $this->guluFranchise->id, 'name' => 'A', 'customer_type' => 'farmer', 'district_id' => $gulu->id, 'phone' => '0772 123 456', 'is_active' => true]);
        Customer::create(['franchise_id' => $this->guluFranchise->id, 'name' => 'B', 'customer_type' => 'farmer', 'district_id' => $gulu->id, 'phone' => '+256772123456', 'is_active' => true]);
        Customer::create(['franchise_id' => $this->guluFranchise->id, 'name' => 'C', 'customer_type' => 'retailer', 'district_id' => $gulu->id, 'phone' => '0701999888', 'is_active' => true]);

        $this->actingAs($this->admin)->post(route('web.admin.sms.send'), [
            'audience' => 'customers', 'customer_type' => 'farmer', 'region_id' => $this->guluRegion->id, 'message' => 'New stock of ABC available',
        ])->assertSessionHas('success');

        $sms = SmsMessage::latest('id')->first();
        $this->assertSame(['+256772123456'], $sms->recipients, 'Normalised and de-duplicated; retailer excluded');
        $this->assertSame('logged', $sms->status);
        $this->assertSame('+256772123456', SmsService::normalizeNumber('772123456'));

        $this->actingAs($this->staff)->get(route('web.admin.sms'))->assertForbidden();
    }

    // ── Web workflow regressions ─────────────────────────────────────
    public function test_web_order_approval_reserves_stock_and_web_payment_acceptance_reduces_balance(): void
    {
        $order = $this->placeOrder($this->gulu, [['product_id' => $this->abc1l->id, 'quantity' => 10]]);

        $this->actingAs($this->staff)->post(route('web.ops.orders.approve', $order), ['expected_delivery_date' => now()->addDays(2)->toDateString()])->assertForbidden();
        $this->actingAs($this->warehouse)->post(route('web.ops.orders.approve', $order), ['expected_delivery_date' => now()->addDays(2)->toDateString()])->assertSessionHas('success');
        $this->assertEquals(990, (float) WarehouseInventory::where('product_id', $this->abc1l->id)->value('quantity'));
        $this->assertNotNull(StockReceipt::where('order_id', $order->id)->first(), 'Web approval creates the stock receipt');

        $this->actingAs($this->warehouse)->post(route('web.ops.orders.dispatch', $order), ['dispatch_means' => 'truck', 'dispatch_vehicle' => 'UBB 001A'])->assertSessionHas('success');
        $this->actingAs($this->warehouse)->post(route('web.ops.orders.arrived', $order))->assertSessionHas('success');

        $receipt = StockReceipt::where('order_id', $order->id)->first();
        $this->actingAs($this->gulu)->get(route('web.franchise.orders.receive', $order))->assertOk()->assertSee('Accept delivery');
        $this->actingAs($this->gulu)->post(route('web.franchise.orders.receive.submit', $order), [
            'items' => [['stock_receipt_item_id' => $receipt->items->first()->id, 'received_quantity' => 10]],
        ])->assertSessionHasErrors('accept_terms');
        $this->actingAs($this->gulu)->post(route('web.franchise.orders.receive.submit', $order), [
            'accept_terms' => '1',
            'items' => [['stock_receipt_item_id' => $receipt->items->first()->id, 'received_quantity' => 10]],
        ])->assertRedirect(route('web.franchise.orders'));
        $this->assertEquals(100000, (float) $this->guluFranchise->fresh()->account_balance);

        // Web payment flow: previously ADDED the payment to the debt.
        $this->actingAs($this->gulu)->post(route('web.franchise.payments.submit'), ['amount' => 60000, 'payment_method' => 'mobile_money', 'transaction_reference' => 'MTN-1'])->assertRedirect(route('web.franchise.payments'));
        $payment = PaymentSubmission::latest('id')->first();
        $this->actingAs($this->finance)->post(route('web.finance.payments.accept', $payment->id))->assertSessionHas('success');
        $this->assertEquals(40000, (float) $this->guluFranchise->fresh()->account_balance);
        $this->assertNotNull($payment->fresh()->acceptance_signature);
    }

    public function test_adjusting_one_line_keeps_other_lines_in_the_order_total(): void
    {
        $order = $this->placeOrder($this->gulu, [
            ['product_id' => $this->abc1l->id, 'quantity' => 10],   // 100,000
            ['product_id' => $this->abc20l->id, 'quantity' => 2],   // 400,000
        ]);
        $line = $order->items()->where('product_id', $this->abc1l->id)->first();

        Sanctum::actingAs($this->warehouse);
        $this->postJson("/api/staff/orders/{$order->id}/adjust", ['items' => [['order_item_id' => $line->id, 'adjusted_quantity' => 5]]])->assertOk();
        $this->assertEquals(450000, (float) $order->fresh()->total_amount);

        // Approval uses the adjusted quantity for stock and the receipt.
        $this->approve($order);
        $this->assertEquals(995, (float) WarehouseInventory::where('product_id', $this->abc1l->id)->value('quantity'));
        $this->assertEquals(5, (float) StockReceipt::where('order_id', $order->id)->first()->items()->where('product_id', $this->abc1l->id)->value('ordered_quantity'));
    }

    public function test_new_web_pages_render(): void
    {
        $this->actingAs($this->admin);
        foreach (['web.admin.customers', 'web.admin.regions', 'web.admin.sms', 'web.admin.terms', 'web.accounts.statements', 'web.accounts.creditNotes', 'web.reports.stock', 'web.support.tickets', 'web.staff.orders'] as $route) {
            $this->get(route($route))->assertOk();
        }
        $this->actingAs($this->staff)->get(route('web.staff.orders'))->assertOk()->assertSee('monitoring mode');
        $this->actingAs($this->staff)->post(route('web.support.tickets.store'), ['subject' => 'Late dispatch', 'category' => 'Orders', 'priority' => 'high', 'message' => 'Order is late'])->assertSessionHas('success');
        $this->actingAs($this->admin)->get(route('web.support.tickets'))->assertSee('Late dispatch');
        $this->actingAs($this->finance)->get(route('web.accounts.creditNotes'))->assertOk();
        $this->actingAs($this->gulu)->get(route('web.franchise.statement'))->assertOk();
        $this->actingAs($this->gulu)->get(route('web.accounts.statements'))->assertForbidden();
        $this->actingAs($this->gulu)->get(route('web.franchise.sales'))->assertOk()->assertSee('Farmer price');
        $this->actingAs($this->gulu)->get('/dashboard')->assertOk()->assertSee('Profit this month');
    }

    public function test_ledger_always_matches_account_balance(): void
    {
        $o = $this->placeOrder($this->gulu, [['product_id' => $this->abc1l->id, 'quantity' => 30]]);
        $this->approve($o);
        $this->dispatch($o);
        $this->receiveAll($o, $this->gulu);
        $this->pay($this->gulu, 120000);

        $ledger = (float) AccountTransaction::where('franchise_id', $this->guluFranchise->id)->sum('credit')
            - (float) AccountTransaction::where('franchise_id', $this->guluFranchise->id)->sum('debit');
        $this->assertEquals($ledger, (float) $this->guluFranchise->fresh()->account_balance);
        $this->assertEquals(180000, $ledger);
    }
}
