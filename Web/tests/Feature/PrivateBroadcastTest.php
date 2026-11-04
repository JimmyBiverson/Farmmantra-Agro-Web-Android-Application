<?php

namespace Tests\Feature;

use App\Events\NewNotification;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrivateBroadcastTest extends TestCase
{
    use RefreshDatabase;

    public function test_notifications_use_private_channels_and_reject_other_users(): void
    {
        config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb.key' => 'testing-key', 'broadcasting.connections.reverb.secret' => 'testing-secret', 'broadcasting.connections.reverb.app_id' => 'testing-app']);
        require base_path('routes/channels.php');
        $role = Role::create(['name' => 'System Administrator', 'guard_name' => 'web']);
        $owner = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        $other = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        $event = new NewNotification($owner->id, 'Private', 'Only for the owner');
        $this->assertSame('private-App.Models.User.'.$owner->id, $event->broadcastOn()[0]->name);
        $this->actingAs($other)->postJson('/broadcasting/auth', ['socket_id' => '123.456', 'channel_name' => 'private-App.Models.User.'.$owner->id])->assertForbidden();
        $this->actingAs($owner)->postJson('/broadcasting/auth', ['socket_id' => '123.456', 'channel_name' => 'private-App.Models.User.'.$owner->id])->assertOk()->assertJsonStructure(['auth']);
    }
}
