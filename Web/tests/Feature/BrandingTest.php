<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BrandingTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(string $name = 'System Administrator'): User
    {
        $role = Role::create(['name' => $name, 'guard_name' => 'web']);
        $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_mobile_upload_replaces_web_branding_and_invalidates_cache(): void
    {
        Storage::fake('public');
        $this->signIn();
        foreach (['logo' => 'site_logo', 'favicon' => 'site_favicon', 'app_icon' => 'app_icon'] as $type => $key) {
            Setting::set($key, 'site/old.png');
            cache()->put('site_settings', ['site_logo' => 'site/old.png']);
            $response = $this->postJson('/api/v1/admin/settings/upload-image', [
                'type' => $type, 'image' => UploadedFile::fake()->image('brand.png', 512, 512),
            ])->assertOk();
            $path = Setting::get($key);
            Storage::disk('public')->assertExists($path);
            $this->assertNotEquals('site/old.png', $path);
            $this->assertNull(cache()->get('site_settings'));
            $url = route('site.asset', ['path' => $path]);
            $response->assertJsonPath('url', $url);
            $this->get($url)->assertOk();
            $this->getJson('/api/settings/public')->assertOk()->assertJsonPath('data.'.$type.'_url', $url);
        }
    }

    public function test_web_admin_can_save_app_icon_for_the_mobile_app(): void
    {
        Storage::fake('public');
        $admin = $this->signIn();
        $this->actingAs($admin)->post(route('web.admin.settings.site.update'), [
            'site_name' => 'Farmmantra',
            'app_icon' => UploadedFile::fake()->image('launcher.png', 1024, 1024),
        ])->assertSessionHasNoErrors()->assertRedirect(route('web.admin.settings.site'));
        $this->getJson('/api/v1/settings/public')->assertJsonPath('data.app_icon_url', route('site.asset', ['path' => Setting::get('app_icon')]));
    }

    public function test_launcher_upload_rejects_small_non_square_and_non_png_images(): void
    {
        Storage::fake('public');
        $this->signIn();
        foreach ([['tiny.png', 100, 100], ['wide.png', 600, 512], ['photo.jpg', 512, 512]] as [$name, $width, $height]) {
            $this->postJson('/api/admin/settings/upload-image', [
                'type' => 'app_icon', 'image' => UploadedFile::fake()->image($name, $width, $height),
            ])->assertUnprocessable()->assertJsonValidationErrors('image');
        }
        $this->assertNull(Setting::get('app_icon'));
    }

    public function test_non_admin_cannot_change_branding(): void
    {
        $this->signIn('Farmmantra Staff');
        $this->postJson('/api/admin/settings/upload-image', ['type' => 'logo'])->assertForbidden();
    }

    public function test_legacy_storage_url_is_not_prefixed_twice(): void
    {
        Setting::set('logo_url', '/storage/settings/logo.png');
        $this->getJson('/api/settings/public')->assertOk()
            ->assertJsonPath('data.logo_url', url('/storage/settings/logo.png'))
            ->assertJsonPath('data.app_icon_url', asset('images/farmmantra-icon.png'));
    }
}
