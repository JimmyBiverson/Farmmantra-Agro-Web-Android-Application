<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SiteBrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_branding_files_are_served_and_rendered_everywhere(): void
    {
        Storage::fake('public');
        $adminRole = Role::create(['name' => 'System Administrator', 'guard_name' => 'web']);
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'is_active' => true]);

        $response = $this->actingAs($admin)->post(route('web.admin.settings.site.update'), [
            'site_name' => 'Updated Farmmantra',
            'site_tagline' => 'Growing better together',
            'site_logo' => UploadedFile::fake()->image('logo.png', 200, 60),
            'site_favicon' => UploadedFile::fake()->image('favicon.png', 32, 32),
            'og_image' => UploadedFile::fake()->image('social.png', 1200, 630),
        ]);

        $response->assertRedirect(route('web.admin.settings.site'));
        $logo = Setting::get('site_logo');
        $favicon = Setting::get('site_favicon');
        $social = Setting::get('og_image');

        Storage::disk('public')->assertExists($logo);
        Storage::disk('public')->assertExists($favicon);
        Storage::disk('public')->assertExists($social);

        $this->getJson('/api/settings/public')
            ->assertOk()
            ->assertJsonPath('data.logo_url', route('site.asset', ['path' => $logo]))
            ->assertJsonPath('data.favicon_url', route('site.asset', ['path' => $favicon]));

        $this->get(route('site.asset', ['path' => $favicon]))
            ->assertOk();

        cache()->forget('site_settings');
        auth()->logout();
        $this->get('/login')
            ->assertOk()
            ->assertSee(route('site.asset', ['path' => $favicon]), false)
            ->assertSee(route('site.asset', ['path' => $logo]), false);

        cache()->forget('site_settings');
        $this->actingAs($admin)->get(route('web.dashboard'))
            ->assertOk()
            ->assertSee(route('site.asset', ['path' => $social]), false)
            ->assertSee('property="og:image"', false);
    }
}
