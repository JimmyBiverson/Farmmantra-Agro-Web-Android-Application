<?php

namespace Tests\Feature;

use App\Models\Franchise;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LiveDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_live_dashboard_reads_new_sales_from_the_database(): void
    {
        $role = Role::create(['name' => 'System Administrator', 'guard_name' => 'web']);
        $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        $franchise = Franchise::create(['name' => 'Live Partner', 'code' => 'LIVE', 'is_active' => true]);
        $this->actingAs($user)->getJson('/dashboard/live')->assertOk()->assertJsonStructure(['html', 'updated_at']);
        $this->sale($franchise, '123456.00');
        $response = $this->getJson('/dashboard/live')->assertOk();
        $this->assertStringContainsString('123,456', $response->json('html'));
    }

    public function test_live_franchise_dashboard_does_not_include_another_partners_sales(): void
    {
        $role = Role::create(['name' => 'Franchise Partner', 'guard_name' => 'web']);
        $own = Franchise::create(['name' => 'Own Partner', 'code' => 'OWN', 'is_active' => true]);
        $other = Franchise::create(['name' => 'Other Partner', 'code' => 'OTHER', 'is_active' => true]);
        $user = User::factory()->create(['role_id' => $role->id, 'franchise_id' => $own->id, 'is_active' => true, 'terms_accepted_at' => now(), 'terms_version' => 1]);
        $this->sale($own, '123456.00');
        $this->sale($other, '987654.00');
        $response = $this->actingAs($user)->getJson('/dashboard/live')->assertOk();
        $this->assertStringContainsString('123,456', $response->json('html'));
        $this->assertStringNotContainsString('987,654', $response->json('html'));
    }

    private function sale(Franchise $franchise, string $amount): void
    {
        DB::table('sales')->insert(['sale_number' => 'LIVE-'.$franchise->id, 'franchise_id' => $franchise->id, 'total_amount' => $amount, 'final_amount' => $amount, 'sale_date' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now()]);
    }
}
