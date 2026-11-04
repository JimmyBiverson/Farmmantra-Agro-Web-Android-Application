<?php

namespace Tests\Feature;

use App\Models\Franchise;
use App\Models\Order;
use App\Models\PaymentSubmission;
use App\Models\Sale;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Opens every parameter-less GET page of the web dashboard as every role on
 * the full demo dataset. Pages must never error (403 for other roles is fine).
 */
class SmokeAllPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_page_renders_for_every_role_with_demo_data(): void
    {
        $this->seed(DatabaseSeeder::class);
        User::query()->update(['terms_accepted_at' => now(), 'terms_version' => 1]);

        $routes = collect(Route::getRoutes())->filter(function ($r) {
            return in_array('GET', $r->methods(), true)
                && str_starts_with((string) $r->getName(), 'web.')
                && ! str_contains($r->uri(), '{')
                && ! in_array($r->getName(), ['web.admin.reports.export'], true);
        })->map->getName()->values();

        $this->assertGreaterThan(40, $routes->count());

        $emails = ['admin@farmmantra.co.ug', 'staff@farmmantra.co.ug', 'warehouse@farmmantra.co.ug', 'finance@farmmantra.co.ug', 'franchise@farmmantra.co.ug'];
        foreach ($emails as $email) {
            $user = User::where('email', $email)->firstOrFail();
            $this->actingAs($user);
            foreach ($routes as $name) {
                $status = $this->get(route($name))->getStatusCode();
                $this->assertContains($status, [200, 302, 403], "{$email} → {$name} returned {$status}");
            }
        }

        // Printable documents on real data.
        $admin = User::where('email', 'admin@farmmantra.co.ug')->first();
        $this->actingAs($admin);
        foreach (Order::limit(5)->pluck('id') as $id) {
            $this->get(route('web.print', ['type' => 'order', 'id' => $id]))->assertOk();
            $this->get(route('web.print', ['type' => 'delivery-note', 'id' => $id]))->assertOk();
        }
        foreach (PaymentSubmission::limit(3)->pluck('id') as $id) {
            $this->get(route('web.print', ['type' => 'payment', 'id' => $id]))->assertOk();
        }
        foreach (Sale::limit(3)->pluck('id') as $id) {
            $this->get(route('web.print', ['type' => 'sale', 'id' => $id]))->assertOk();
        }
        foreach (Franchise::pluck('id') as $id) {
            $this->get(route('web.print', ['type' => 'statement', 'id' => $id]))->assertOk();
            $this->get(route('web.accounts.statements', ['franchise_id' => $id]))->assertOk();
        }
        $this->get(route('web.staff.orders', ['overdue' => 1]))->assertOk();
    }
}
