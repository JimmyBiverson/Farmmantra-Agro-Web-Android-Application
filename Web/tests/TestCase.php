<?php

namespace Tests;

use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Services\ReviewService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function completeOrderReviews(Order $order): void
    {
        if ($order->fresh()->review_stage !== 'sales_head') {
            return;
        }
        foreach (['Sales Head', 'System Administrator'] as $roleName) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $actor = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
            ReviewService::review($order->fresh(), $actor, true, 'Reviewed for workflow test');
        }
    }
}
