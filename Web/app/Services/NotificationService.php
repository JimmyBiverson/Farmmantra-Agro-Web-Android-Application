<?php

namespace App\Services;

use App\Events\NewNotification;
use App\Models\User;
use App\Notifications\GeneralNotification;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Send a real-time notification to one or more users.
     *
     * Persists a database notification for in-app history and, when a
     * broadcast driver is configured (Pusher / Reverb), pushes it to the
     * recipient's private channel instantly.
     *
     * @param  array<int>|int  $userIds
     */
    public static function send(
        array|int $userIds,
        string $title,
        string $message,
        string $type = 'general',
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $route = null,
    ): void {
        $ids = is_array($userIds) ? array_values(array_unique($userIds)) : [$userIds];
        $ids = array_filter($ids);

        if (empty($ids)) {
            return;
        }

        try {
            $users = User::whereIn('id', $ids)->get();

            foreach ($users as $user) {
                if (! self::wantsNotification($user, $type)) {
                    continue;
                }

                $user->notify(new GeneralNotification(
                    title: $title,
                    message: $message,
                    type: $type,
                    referenceType: $referenceType,
                    referenceId: $referenceId,
                    route: $route,
                ));

                broadcast(new NewNotification($user->id, $title, $message, $type, $referenceType, $referenceId, $route));
            }
        } catch (\Throwable $e) {
            // Never let a notification failure break the primary action.
            Log::warning('NotificationService failed: '.$e->getMessage());
        }
    }

    /**
     * Respect the user's opt-in preferences when they have been configured.
     */
    protected static function wantsNotification(User $user, string $type): bool
    {
        $prefs = $user->notification_preferences;

        if (! is_array($prefs) || empty($prefs)) {
            return true;
        }

        // Map notification types to preference keys.
        $key = match ($type) {
            'order', 'order_approved', 'order_declined', 'delivery' => 'orders',
            'payment', 'payment_verified', 'payment_accepted', 'payment_rejected' => 'payments',
            'chat', 'support' => 'chat',
            'inventory' => 'inventory',
            default => null,
        };

        if ($key === null || ! array_key_exists($key, $prefs)) {
            return true;
        }

        return (bool) $prefs[$key];
    }

    /**
     * Real Firebase Cloud Messaging push. Persisted in-app notifications are
     * created separately via send(); this handles the WhatsApp-style lock
     * screen / heads-up delivery when FCM credentials are configured.
     */
    public static function push(array|int $userIds, string $title, string $message, ?string $route = null): void
    {
        try {
            $ids = is_array($userIds) ? $userIds : [$userIds];
            $tokens = User::whereIn('id', $ids)
                ->whereNotNull('fcm_token')
                ->where('fcm_token', '!=', '')
                ->pluck('fcm_token', 'id')
                ->all();

            if (empty($tokens)) {
                return;
            }

            $fcm = app(FcmService::class);

            foreach ($tokens as $userId => $token) {
                $fcm->send($token, $title, $message, [
                    'route' => $route,
                    'reference_type' => null,
                    'reference_id' => null,
                    'type' => 'general',
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('FCM push failed: '.$e->getMessage());
        }
    }
}
