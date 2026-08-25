<?php

namespace App\Providers;

use App\Models\FranchiseInventory;
use App\Models\Order;
use App\Models\PaymentSubmission;
use App\Models\Setting;
use App\Models\WarehouseInventory;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        View::composer('*', function ($view) {
            $site = [];
            $notif = [];

            try {
                $site = cache()->remember('site_settings', 3600, function () {
                    $keys = ['site_name', 'site_tagline', 'site_favicon', 'site_logo', 'og_image', 'site_phone', 'site_email', 'site_address',
                        'theme_accent', 'theme_success', 'theme_warning', 'theme_danger', 'theme_info'];

                    return Setting::whereIn('key', $keys)->pluck('value', 'key')->toArray();
                });
            } catch (\Throwable $e) {
                $site = [];
            }

            foreach (['site_logo', 'site_favicon', 'og_image'] as $assetKey) {
                if (!empty($site[$assetKey]) && Storage::disk('public')->exists($site[$assetKey])) {
                    $version = Storage::disk('public')->lastModified($site[$assetKey]);
                    $site[$assetKey.'_url'] = route('site.asset', ['path' => $site[$assetKey]]).'?v='.$version;
                }
            }

            $view->with('site', $site);

            try {
                $notif = cache()->remember('notif_settings', 3600, function () {
                    return Setting::where('group_name', 'notifications')->pluck('value', 'key')->toArray();
                });
            } catch (\Throwable $e) {
                $notif = [];
            }

            $view->with('notif', $notif);

            if (auth()->check()) {
                $user = auth()->user();
                $role = $user->role?->name;
                $showBadges = ($notif['notif_inapp_badge_counts'] ?? '1') === '1';

                try {
                    $nOrders = $showBadges ? Order::where('status', 'pending')->count() : 0;
                    $nPayments = $showBadges ? PaymentSubmission::where('status', 'pending')->count() : 0;
                    $nLowStock = $showBadges ? WarehouseInventory::whereColumn('quantity', '<=', 'reorder_level')->count() : 0;
                    $nPendingTotal = $showBadges ? PaymentSubmission::where('status', 'pending')->sum('amount') : 0;
                    $fid = $user->franchise_id;
                    $nMyOrders = $fid ? Order::where('franchise_id', $fid)->where('status', 'pending')->count() : 0;
                    $nMyPayments = $fid ? PaymentSubmission::where('franchise_id', $fid)->where('status', 'pending')->count() : 0;
                    $nMyLowStock = $fid ? FranchiseInventory::where('franchise_id', $fid)->whereColumn('quantity', '<=', 'reorder_level')->count() : 0;
                } catch (\Throwable $e) {
                    $nOrders = 0;
                    $nPayments = 0;
                    $nLowStock = 0;
                    $nPendingTotal = 0;
                    $nMyOrders = 0;
                    $nMyPayments = 0;
                    $nMyLowStock = 0;
                }

                $bellCount = 0;
                if ($role === 'System Administrator') {
                    $bellCount = ($nOrders > 0 ? 1 : 0) + ($nPayments > 0 ? 1 : 0) + ($nLowStock > 0 ? 1 : 0);
                } elseif ($role === 'Farmmantra Staff') {
                    $bellCount = ($nOrders > 0 ? 1 : 0) + ($nLowStock > 0 ? 1 : 0);
                } elseif ($role === 'Finance Department') {
                    $bellCount = $nPayments > 0 ? 1 : 0;
                } elseif ($role === 'Franchise Partner') {
                    $bellCount = ($nMyOrders > 0 ? 1 : 0) + ($nMyPayments > 0 ? 1 : 0) + ($nMyLowStock > 0 ? 1 : 0);
                }

                $view->with(compact('bellCount', 'role', 'nOrders', 'nPayments', 'nLowStock', 'nPendingTotal', 'nMyOrders', 'nMyPayments', 'nMyLowStock'));
            }
        });
    }
}
