<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\FieldZone;
use App\Models\Franchise;
use App\Models\FranchiseInventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\StockReceipt;
use App\Models\User;
use App\Models\WarehouseInventory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
}
