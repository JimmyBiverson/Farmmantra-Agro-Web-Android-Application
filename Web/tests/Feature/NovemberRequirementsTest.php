<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\FieldZone;
use App\Models\Franchise;
use App\Models\FranchiseInventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\SaleItem;
use App\Models\SalesTarget;
use App\Models\StockReceipt;
use App\Models\User;
use App\Models\WarehouseInventory;
use App\Services\LedgerService;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NovemberRequirementsTest extends TestCase
{
    use RefreshDatabase;

    private array $actors;

    private Product $product;

    private Franchise $franchise;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        $this->franchise = Franchise::create(['name' => 'Client franchise', 'code' => 'NOV', 'is_active' => true, 'credit_limit' => 0, 'account_balance' => 0]);
        foreach (['admin' => 'System Administrator', 'sales' => 'Sales Head', 'field' => 'Field Agronomist', 'finance' => 'Finance Department', 'warehouse' => 'Warehouse', 'franchise' => 'Franchise Partner'] as $key => $name) {
            $role = Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            $this->actors[$key] = User::factory()->create(['role_id' => $role->id, 'is_active' => true, 'franchise_id' => $key === 'franchise' ? $this->franchise->id : null, 'terms_accepted_at' => now(), 'terms_version' => 1]);
        }
        $cat = Category::create(['name' => 'Seeds', 'slug' => 'seeds']);
        $this->product = Product::create(['name' => 'Test maize', 'sku' => 'NOV-1', 'category_id' => $cat->id, 'standard_price' => 1000, 'selling_price' => 1200, 'is_active' => true]);
        WarehouseInventory::create(['product_id' => $this->product->id, 'quantity' => 100, 'reorder_level' => 10]);
    }

    private function login(string $role): void
    {
        Sanctum::actingAs($this->actors[$role]);
    }

    private function place(): Order
    {
        $this->login('franchise');
        $res = $this->postJson('/api/v1/franchise/orders', ['items' => [['product_id' => $this->product->id, 'quantity' => 10]]])->assertCreated();

        return Order::findOrFail($res->json('data.id'));
    }

    public function test_order_reviews_are_sequential_and_do_not_mutate_stock(): void
    {
        $order = $this->place();
        $this->assertSame('sales_head', $order->review_stage);
        $this->login('warehouse');
        $this->postJson("/api/v1/staff/orders/$order->id/approve", ['expected_delivery_date' => now()->addDays(3)->toDateString()])->assertUnprocessable();
        $this->login('admin');
        $this->postJson("/api/v1/operations/orders/$order->id/review", ['approve' => true, 'notes' => 'Review'])->assertForbidden();
        foreach (['sales', 'admin'] as $role) {
            $this->login($role);
            $this->postJson("/api/v1/operations/orders/$order->id/review", ['approve' => true, 'notes' => 'Checked'])->assertOk();
        }
        $this->assertEquals(100, WarehouseInventory::first()->quantity);
        $this->login('warehouse');
        $this->postJson("/api/v1/staff/orders/$order->id/approve", ['expected_delivery_date' => now()->addDays(3)->toDateString()])->assertOk();
        $this->assertEquals(90, WarehouseInventory::first()->quantity);
        $this->assertCount(2, $order->fresh()->review_history);
    }

    public function test_funding_is_admin_approved_then_finance_paid_with_owner_scope(): void
    {
        $this->login('franchise');
        $id = $this->postJson('/api/v1/operations/requests', ['type' => 'funds', 'title' => 'Shop operation', 'amount' => 10000, 'entry_date' => today()->toDateString(), 'details' => ['notes' => 'Supplies']])->assertCreated()->json('data.id');
        $this->login('finance');
        $this->postJson("/api/v1/operations/requests/$id/review", ['action' => 'pay', 'notes' => 'Paid', 'reference' => 'BANK-1'])->assertForbidden();
        $this->login('admin');
        $this->postJson("/api/v1/operations/requests/$id/review", ['action' => 'approve', 'notes' => 'Approved'])->assertOk();
        $this->login('finance');
        $this->postJson("/api/v1/operations/requests/$id/review", ['action' => 'pay', 'notes' => 'Bank checked', 'reference' => 'BANK-1'])->assertOk();
        $this->postJson("/api/v1/operations/requests/$id/review", ['action' => 'pay', 'notes' => 'Again', 'reference' => 'BANK-2'])->assertForbidden();
        $this->login('field');
        $this->getJson('/api/v1/operations/requests')->assertJsonCount(0, 'data');
        $this->postJson('/api/v1/operations/requests', ['type' => 'expense', 'title' => 'Not mine', 'amount' => 500, 'entry_date' => today()->toDateString(), 'fund_request_id' => $id, 'details' => ['notes' => 'Invalid']])->assertUnprocessable();
        $this->login('franchise');
        $this->postJson('/api/v1/operations/requests', ['type' => 'expense', 'title' => 'Too much', 'amount' => 10001, 'entry_date' => today()->toDateString(), 'fund_request_id' => $id, 'details' => ['notes' => 'Overspend']])->assertUnprocessable();
    }

    public function test_geofence_rejects_outside_stale_and_unassigned_locations(): void
    {
        $zone = FieldZone::create(['name' => 'Assigned farm', 'latitude' => 0.3, 'longitude' => 32.5, 'radius_meters' => 500, 'user_id' => $this->actors['field']->id]);
        $this->login('field');
        $data = ['type' => 'checkin', 'title' => 'Visit', 'entry_date' => today()->toDateString(), 'details' => ['zone_id' => $zone->id, 'latitude' => 0.3, 'longitude' => 32.5, 'accuracy' => 10, 'captured_at' => now()->toIso8601String()]];
        $this->postJson('/api/v1/operations/requests', $data)->assertCreated()->assertJsonPath('data.details.inside_zone', true);
        $data['details']['latitude'] = 1;
        $this->postJson('/api/v1/operations/requests', $data)->assertUnprocessable();
        $data['details']['latitude'] = 0.3;
        $data['details']['captured_at'] = now()->subMinutes(5)->toIso8601String();
        $this->postJson('/api/v1/operations/requests', $data)->assertUnprocessable();
        $zone->update(['user_id' => $this->actors['sales']->id]);
        $data['details']['captured_at'] = now()->toIso8601String();
        $this->postJson('/api/v1/operations/requests', $data)->assertNotFound();
    }

    public function test_knowledge_drafts_are_hidden_and_publication_needs_complete_advice(): void
    {
        $this->login('admin');
        $data = ['kind' => 'crop', 'crop' => 'Maize', 'category_id' => $this->product->category_id, 'title' => 'Leaf symptoms', 'published' => false, 'content' => ['notes' => 'Awaiting review']];
        $this->postJson('/api/v1/operations/knowledge', $data)->assertOk();
        $data['published'] = true;
        $this->postJson('/api/v1/operations/knowledge', $data)->assertUnprocessable();
        $this->login('field');
        $this->getJson('/api/v1/operations/knowledge')->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/operations/knowledge/facets')->assertOk()->assertJsonCount(0, 'data');
        $this->postJson('/api/v1/operations/knowledge', $data)->assertForbidden();
    }

    public function test_return_stock_and_credit_wait_for_physical_receipt(): void
    {
        $order = $this->place();
        $order->update(['status' => 'delivered', 'delivery_status' => 'confirmed', 'received_at' => now()]);
        $receipt = StockReceipt::create(['receipt_number' => 'REC-NOV', 'order_id' => $order->id, 'franchise_id' => $this->franchise->id, 'status' => 'confirmed']);
        $receipt->items()->create(['order_item_id' => $order->items->first()->id, 'product_id' => $this->product->id, 'ordered_quantity' => 10, 'received_quantity' => 10]);
        FranchiseInventory::create(['franchise_id' => $this->franchise->id, 'product_id' => $this->product->id, 'quantity' => 10, 'total_value' => 10000]);
        $data = ['order_id' => $order->id, 'items' => [['product_id' => $this->product->id, 'quantity' => 2]], 'reason' => 'Damaged', 'transport' => 'Pickup', 'photos' => [UploadedFile::fake()->image('return.jpg')]];
        $id = $this->postJson('/api/v1/operations/returns', $data)->assertCreated()->json('data.id');
        $this->assertEquals(100, WarehouseInventory::first()->quantity);
        foreach (['sales', 'admin', 'warehouse'] as $role) {
            $this->login($role);
            $this->postJson("/api/v1/operations/returns/$id/review", ['approve' => true, 'notes' => 'Inspected'])->assertOk();
        }
        $this->assertEquals(102, WarehouseInventory::first()->quantity);
        $this->assertEquals(8, FranchiseInventory::first()->quantity);
        $this->assertEquals(-2000, $this->franchise->fresh()->account_balance);
        $this->postJson("/api/v1/operations/returns/$id/review", ['approve' => true, 'notes' => 'Again'])->assertForbidden();
        $this->login('franchise');
        $data['items'][0]['quantity'] = 9;
        $this->postJson('/api/v1/operations/returns', $data)->assertUnprocessable();
    }

    public function test_ageing_uses_receipt_date_and_is_franchise_scoped(): void
    {
        $order = $this->place();
        $order->update(['status' => 'delivered', 'received_at' => now()->subDays(16), 'amount_paid' => 2000]);
        $this->login('franchise');
        $this->getJson('/api/v1/operations/dashboard')->assertOk()->assertJsonPath('data.overdue_orders.0.days', 16)->assertJsonPath('data.overdue_orders.0.amount', 8000);
        $this->login('field');
        $this->getJson('/api/v1/operations/dashboard')->assertJsonCount(0, 'data.overdue_orders');
    }

    public function test_excel_is_a_real_workbook_with_restricted_access(): void
    {
        $this->login('admin');
        $response = $this->get('/api/v1/operations/export?report=warehouse')->assertOk();
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($response->baseResponse->getFile()->getPathname()));
        $this->assertStringContainsString('Test maize', $zip->getFromName('xl/worksheets/sheet1.xml'));
        $zip->close();
        $this->login('franchise');
        $this->getJson('/api/v1/operations/export?report=warehouse')->assertForbidden();
    }

    public function test_franchise_stock_export_never_contains_another_franchises_inventory(): void
    {
        $other = Franchise::create(['name' => 'Private other branch', 'code' => 'OTHER', 'is_active' => true]);
        foreach ([$this->franchise, $other] as $franchise) {
            FranchiseInventory::create(['franchise_id' => $franchise->id, 'product_id' => $this->product->id, 'quantity' => 5, 'total_value' => 5000]);
        }
        $this->login('franchise');
        $response = $this->get('/api/v1/operations/export?report=franchise_stock&franchise_id='.$other->id)->assertOk();
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($response->baseResponse->getFile()->getPathname()));
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertStringContainsString('Client franchise', $sheet);
        $this->assertStringNotContainsString('Private other branch', $sheet);
        $zip->close();
        foreach (['sales', 'expenses', 'outstanding'] as $report) {
            $this->getJson('/api/v1/operations/export?report='.$report)->assertForbidden();
        }
    }

    public function test_finance_pending_counter_only_counts_actionable_submissions(): void
    {
        $this->login('franchise');
        $fund = $this->postJson('/api/v1/operations/requests', ['type' => 'funds', 'title' => 'Operations', 'amount' => 100, 'entry_date' => today()->toDateString(), 'details' => ['notes' => 'Supplies']])->assertCreated()->json('data.id');
        $expense = $this->postJson('/api/v1/operations/requests', ['type' => 'expense', 'title' => 'Expense', 'amount' => 50, 'entry_date' => today()->toDateString(), 'details' => ['notes' => 'Receipt']])->assertCreated()->json('data.id');
        $this->login('finance');
        $this->getJson('/api/v1/operations/dashboard')->assertJsonPath('data.pending_requests', 1);
        $this->postJson("/api/v1/operations/requests/$expense/review", ['action' => 'approve', 'notes' => 'Checked'])->assertOk();
        $this->getJson('/api/v1/operations/dashboard')->assertJsonPath('data.pending_requests', 0);
        $this->login('admin');
        $this->postJson("/api/v1/operations/requests/$fund/review", ['action' => 'approve', 'notes' => 'Approved'])->assertOk();
        $this->login('finance');
        $this->getJson('/api/v1/operations/dashboard')->assertJsonPath('data.pending_requests', 1);
        $this->postJson("/api/v1/operations/requests/$fund/review", ['action' => 'pay', 'notes' => 'Transferred', 'reference' => 'BANK-AUDIT'])->assertOk();
        $this->getJson('/api/v1/operations/dashboard')->assertJsonPath('data.pending_requests', 0);
    }

    public function test_all_five_client_business_zones_are_available(): void
    {
        $this->login('admin');
        $names = collect($this->getJson('/api/v1/operations/options')->assertOk()->json('data.regions'))->pluck('name');
        foreach (['Eastern', 'Western', 'Northern', 'Southern', 'Central'] as $name) {
            $this->assertContains($name, $names);
        }
    }

    public function test_admin_and_franchise_receive_dispatch_and_arrival_notifications(): void
    {
        $order = $this->place();
        foreach (['sales', 'admin'] as $role) {
            $this->login($role);
            $this->postJson("/api/v1/operations/orders/$order->id/review", ['approve' => true, 'notes' => 'Reviewed'])->assertOk();
        }
        OrderService::approve($order->fresh(), $this->actors['warehouse'], today()->addDay()->toDateString());
        OrderService::dispatch($order->fresh(), $this->actors['warehouse'], 'truck', 'Driver delivery');
        OrderService::markDelivered($order->fresh(), $this->actors['warehouse']);
        foreach (['admin', 'franchise'] as $role) {
            $titles = $this->actors[$role]->notifications()->get()->pluck('data.title');
            $this->assertContains('Dispatched — '.$order->order_number, $titles);
            $this->assertContains('Delivered — '.$order->order_number, $titles);
        }
    }

    public function test_linked_payments_close_the_selected_received_order_instead_of_an_older_order(): void
    {
        $first = $this->place();
        $second = $this->place();
        foreach ([$first, $second] as $order) {
            $order->update(['status' => 'delivered', 'delivery_status' => 'confirmed', 'received_at' => now(), 'received_amount' => 10000]);
            LedgerService::charge($this->franchise, 'purchase', 10000, 'Purchase', $order, $order->order_number, $this->actors['franchise']->id);
        }
        $payment = PaymentService::submit($this->actors['franchise'], ['amount' => 10000, 'payment_method' => 'bank_transfer', 'order_ids' => [$second->id]]);
        PaymentService::verify($payment, $this->actors['finance'], 10000, 'Bank checked');
        PaymentService::accept($payment->fresh(), $this->actors['finance']);
        $this->assertEquals(0, $first->fresh()->amount_paid);
        $this->assertEquals(10000, $second->fresh()->amount_paid);
        $this->assertNotNull($second->fresh()->closed_at);
        PaymentService::allocateOutstanding($this->franchise->fresh());
        $this->assertEquals(10000, $second->fresh()->amount_paid);
    }

    public function test_expense_totals_only_count_finance_approved_entries_against_monthly_budget(): void
    {
        $this->login('franchise');
        $id = $this->postJson('/api/v1/operations/requests', ['type' => 'expense', 'title' => 'Fuel', 'amount' => 100, 'entry_date' => today()->toDateString(), 'details' => ['notes' => 'Daily expense']])->assertCreated()->json('data.id');
        $this->login('admin');
        $this->postJson('/api/v1/operations/requests', ['type' => 'budget', 'title' => 'Monthly operations', 'amount' => 1000, 'entry_date' => today()->toDateString(), 'details' => ['notes' => 'Client budget']])->assertCreated();
        $this->getJson('/api/v1/operations/dashboard')->assertJsonPath('data.monthly_expenses', 0);
        $this->login('finance');
        $this->postJson('/api/v1/operations/requests/'.$id.'/review', ['action' => 'approve', 'notes' => 'Receipt checked'])->assertOk();
        $this->getJson('/api/v1/operations/dashboard')->assertJsonPath('data.monthly_expenses', 100)->assertJsonPath('data.budget_used_percentage', 10);
    }

    public function test_web_workspace_and_mobile_use_the_same_controllers_with_session_authentication(): void
    {
        $this->actingAs($this->actors['admin']);
        $this->get('/workspace')->assertOk()->assertSee('Operations workspace')->assertSee('Knowledge');
        foreach (['dashboard', 'options', 'requests', 'orders', 'returns', 'knowledge', 'targets', 'zones'] as $resource) {
            $this->getJson('/workspace/data/'.$resource)->assertOk();
        }
        $this->get('/workspace/data/export?report=warehouse')->assertOk();
    }

    public function test_legacy_return_entry_cannot_bypass_goods_return_reviews(): void
    {
        $this->actingAs($this->actors['finance']);
        $this->post(route('web.accounts.creditNotes.store'), ['type' => 'sales_return', 'franchise_id' => $this->franchise->id, 'reason' => 'Damaged products', 'items' => [['product_id' => $this->product->id, 'quantity' => 1]]]);
        $this->assertEquals(100, WarehouseInventory::first()->quantity);
        $this->assertDatabaseCount('credit_notes', 0);
    }

    public function test_daily_due_alerts_do_not_repeat_on_same_day(): void
    {
        Cache::flush();
        $order = $this->place();
        $order->update(['status' => 'delivered', 'received_at' => now()->subDays(16), 'amount_paid' => 0]);
        $this->artisan('farmmantra:operational-alerts')->assertSuccessful();
        $count = DB::table('notifications')->count();
        $this->assertGreaterThan(0, $count);
        $this->artisan('farmmantra:operational-alerts')->assertSuccessful();
        $this->assertEquals($count, DB::table('notifications')->count());
    }

    public function test_product_targets_count_only_matching_sale_lines_and_dashboard_shows_achievement(): void
    {
        $other = Product::create(['name' => 'Other seed', 'sku' => 'NOV-2', 'category_id' => $this->product->category_id, 'standard_price' => 1000, 'selling_price' => 1200, 'is_active' => true]);
        $saleId = DB::table('sales')->insertGetId(['sale_number' => 'TARGET-SALE', 'franchise_id' => $this->franchise->id, 'total_amount' => 10000, 'final_amount' => 10000, 'sale_date' => today()->toDateString(), 'created_at' => now(), 'updated_at' => now()]);
        foreach ([[$this->product->id, 2000], [$other->id, 8000]] as [$product,$amount]) {
            SaleItem::create(['sale_id' => $saleId, 'product_id' => $product, 'quantity' => 1, 'unit_price' => $amount, 'cost_price' => 1000, 'subtotal' => $amount]);
        }
        $target = SalesTarget::create(['franchise_id' => $this->franchise->id, 'product_id' => $this->product->id, 'target_amount' => 4000, 'period' => 'monthly', 'starts_on' => now()->startOfMonth()->toDateString(), 'ends_on' => now()->endOfMonth()->toDateString(), 'month' => now()->month, 'year' => now()->year]);
        $this->login('franchise');
        $this->getJson('/api/v1/operations/targets')->assertOk()->assertJsonPath('data.0.actual_amount', 2000)->assertJsonPath('data.0.achievement_percentage', 50);
        $this->getJson('/api/v1/operations/dashboard')->assertJsonPath('data.achievements.monthly.percentage', 50);
    }

    public function test_shared_view_badges_are_queried_once_per_web_request(): void
    {
        $this->actingAs($this->actors['admin']);
        $queries = [];
        DB::listen(function ($event) use (&$queries) {
            $queries[] = $event->sql;
        });
        $this->get('/workspace')->assertOk();
        $orderCounts = array_filter($queries, fn ($sql) => str_contains(strtolower($sql), 'count(*)') && str_contains(strtolower($sql), 'from "orders"'));
        $this->assertCount(1, $orderCounts, 'Layout partials must reuse request-scoped badge counts.');
    }

    public function test_pending_order_upgrade_preserves_existing_approval_decisions(): void
    {
        $pending = $this->place();
        $pending->update(['review_stage' => null]);
        $accepted = $this->place();
        $accepted->update(['status' => 'approved', 'approved_at' => now(), 'review_stage' => null]);
        $migration = require database_path('migrations/2026_11_07_173641_queue_unreviewed_pending_orders.php');
        $migration->up();
        $this->assertSame('sales_head', $pending->fresh()->review_stage);
        $this->assertNull($accepted->fresh()->review_stage);
        $this->assertSame('approved', $accepted->fresh()->status);
        $this->assertEquals(100, WarehouseInventory::first()->quantity);
    }
}
