<?php

namespace Tests\Feature;

use App\Models\Franchise;
use App\Models\Order;
use App\Models\PaymentSubmission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BusinessResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_clear_period_data_without_removing_master_data(): void
    {
        Storage::fake('public');
        $role = Role::create(['name' => 'System Administrator', 'guard_name' => 'web']);
        $admin = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        $franchise = Franchise::create([
            'name' => 'Test Franchise',
            'code' => 'TEST-001',
            'credit_limit' => 1000000,
            'account_balance' => 250000,
            'is_active' => true,
        ]);

        Order::create([
            'order_number' => 'ORD-TEST-001',
            'franchise_id' => $franchise->id,
            'status' => 'pending',
            'total_amount' => 250000,
            'tax_amount' => 0,
        ]);
        PaymentSubmission::create([
            'payment_number' => 'PAY-TEST-001',
            'franchise_id' => $franchise->id,
            'amount' => 100000,
            'status' => 'pending',
            'proof_of_payment_path' => 'payments/test-proof.png',
        ]);
        Storage::disk('public')->put('payments/test-proof.png', 'test');
        DB::table('sales')->insert([
            'sale_number' => 'SALE-TEST-001',
            'franchise_id' => $franchise->id,
            'total_amount' => 100000,
            'final_amount' => 100000,
            'sale_date' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('customers')->insert([
            'franchise_id' => $franchise->id,
            'name' => 'Test Customer',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Security process: without the admin's password nothing happens.
        $this->actingAs($admin)->post(route('web.admin.businessReset'), [
            'confirmation' => 'START FRESH',
            'action_reason' => 'Start of new financial year',
        ])->assertSessionHas('error');
        $this->assertDatabaseHas('orders', ['order_number' => 'ORD-TEST-001']);

        $this->actingAs($admin)->post(route('web.admin.businessReset'), [
            'confirmation' => 'WRONG PHRASE',
            'confirm_password' => 'password',
            'action_reason' => 'Start of new financial year',
        ])->assertSessionHasErrors('confirmation');
        $this->assertDatabaseHas('orders', ['order_number' => 'ORD-TEST-001']);

        $this->actingAs($admin)->post(route('web.admin.businessReset'), [
            'confirmation' => 'START FRESH',
            'confirm_password' => 'password',
            'action_reason' => 'Start of new financial year',
        ])->assertRedirect(route('web.admin.orders'));

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payment_submissions', 0);
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('customers', 0);
        Storage::disk('public')->assertMissing('payments/test-proof.png');
        $this->assertSame(0.0, (float) $franchise->fresh()->account_balance);
        $this->assertDatabaseHas('franchises', ['id' => $franchise->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'business.reset']);
    }
}
