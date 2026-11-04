<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Notifications\GeneralNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_history_and_read_actions_are_scoped_to_the_recipient(): void
    {
        $role = Role::create(['name' => 'System Administrator', 'guard_name' => 'web']);
        $owner = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        $other = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        $owner->notify(new GeneralNotification('Order received', 'Review the new order', 'order'));
        $other->notify(new GeneralNotification('Private payment', 'Only another user should see this', 'payment'));
        $id = $owner->notifications()->first()->id;
        $otherId = $other->notifications()->first()->id;
        $this->actingAs($owner)->get('/notifications')->assertOk()->assertSee('Order received')->assertDontSee('Private payment');
        $this->getJson('/notifications/feed')->assertOk()->assertJsonPath('unread_count', 1)->assertJsonCount(1, 'notifications');
        $this->post('/notifications/'.$otherId.'/read')->assertNotFound();
        $this->post('/notifications/'.$id.'/read')->assertRedirect();
        $this->getJson('/notifications/feed')->assertJsonPath('unread_count', 0);
        $this->assertNull($other->notifications()->first()->read_at);
    }
}
