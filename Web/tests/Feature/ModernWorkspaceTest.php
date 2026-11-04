<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ModernWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_keeps_branding_and_rejects_invalid_credentials(): void
    {
        $this->get('/')->assertRedirect('/dashboard');
        $this->get('/login')->assertOk()->assertSee('Welcome back.')
            ->assertDontSee('@vite', false)->assertSee('/build/assets/', false);
        $this->post('/login', ['email' => 'missing@example.com', 'password' => 'incorrect'])->assertSessionHasErrors('email');
    }

    public function test_versioned_api_uses_the_same_authentication_and_database(): void
    {
        $role = Role::create(['name' => 'System Administrator', 'guard_name' => 'web']);
        User::factory()->create(['email' => 'test@example.com', 'password' => Hash::make('test-password'), 'role_id' => $role->id, 'is_active' => true]);
        $token = $this->postJson('/api/v1/login', ['email' => 'test@example.com', 'password' => 'test-password'])->assertOk()->json('token');
        $this->withToken($token)->getJson('/api/v1/me')->assertOk()->assertJsonPath('user.email', 'test@example.com');
    }

    public function test_business_reset_is_disabled_without_demo_mode(): void
    {
        config(['app.demo_data' => false]);
        $role = Role::create(['name' => 'System Administrator', 'guard_name' => 'web']);
        $admin = User::factory()->create(['role_id' => $role->id, 'password' => Hash::make('test-password'), 'is_active' => true]);
        $this->actingAs($admin)->post(route('web.admin.businessReset'), ['confirmation' => 'START FRESH', 'confirm_password' => 'test-password', 'action_reason' => 'Test safety guard'])->assertForbidden();
    }
}
