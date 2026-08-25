<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingsController extends Controller
{
    /**
     * Return public site identity settings (logo, name, brand colours etc.)
     * Accessible without authentication so the mobile splash screen can fetch them.
     */
    public function public(): JsonResponse
    {
        $keys = [
            'site_name',
            'site_tagline',
            'contact_email',
            'contact_phone',
            'address',
            'logo_url',
            'favicon_url',
            'primary_color',
            'secondary_color',
            'currency_symbol',
            'currency_code',
        ];

        $settings = [];
        foreach ($keys as $key) {
            $settings[$key] = Setting::get($key);
        }

        $settings['logo_url'] = $this->publicAssetUrl(
            Setting::get('site_logo') ?: Setting::get('logo_url')
        );
        $settings['favicon_url'] = $this->publicAssetUrl(
            Setting::get('site_favicon') ?: Setting::get('favicon_url')
        );

        return response()->json(['data' => $settings]);
    }

    private function publicAssetUrl(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }

        if (str_starts_with($value, 'site/')) {
            return route('site.asset', ['path' => $value]);
        }

        return url('/storage/'.ltrim($value, '/'));
    }

    /**
     * Return ALL settings (admin only – called by authenticated admin panel).
     */
    public function index(): JsonResponse
    {
        $settings = Setting::orderBy('group_name')->orderBy('key')->get();

        $grouped = $settings->groupBy('group_name')->map(function ($items) {
            return $items->mapWithKeys(fn ($s) => [$s->key => $s->value]);
        });

        return response()->json(['data' => $grouped]);
    }

    /**
     * Bulk-update settings (admin only).
     */
    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'settings' => 'required|array',
            'settings.*' => 'nullable|string|max:2000',
        ]);

        foreach ($request->settings as $key => $value) {
            $group = $this->resolveGroup($key);
            Setting::set($key, $value, $group);
        }

        return response()->json(['message' => 'Settings saved.', 'data' => $request->settings]);
    }

    /**
     * Upload a logo or favicon image.
     */
    public function uploadImage(Request $request): JsonResponse
    {
        $request->validate([
            'type'  => 'required|in:logo,favicon',
            'image' => 'required|image|mimes:png,jpg,jpeg,svg,ico|max:2048',
        ]);

        $type = $request->type;
        $path = $request->file('image')->store("settings/{$type}", 'public');
        $url  = Storage::url($path);

        Setting::set("{$type}_url", $url, 'branding');

        return response()->json([
            'message' => ucfirst($type) . ' uploaded successfully.',
            'url'     => $url,
        ]);
    }

    private function resolveGroup(string $key): string
    {
        $map = [
            'site_name'       => 'branding',
            'site_tagline'    => 'branding',
            'logo_url'        => 'branding',
            'favicon_url'     => 'branding',
            'primary_color'   => 'branding',
            'secondary_color' => 'branding',
            'contact_email'   => 'contact',
            'contact_phone'   => 'contact',
            'address'         => 'contact',
            'currency_symbol' => 'finance',
            'currency_code'   => 'finance',
        ];

        return $map[$key] ?? 'general';
    }
}
