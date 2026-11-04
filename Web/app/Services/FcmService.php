<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Real Firebase Cloud Messaging (HTTP v1) client.
 *
 * Uses a service-account JSON file to mint short-lived OAuth2 tokens,
 * then posts WhatsApp-style high-priority data messages per device token.
 *
 * If no credentials are configured the service degrades gracefully to a
 * debug log — the in-app notification (persisted by NotificationService)
 * is unaffected.
 */
class FcmService
{
    protected ?string $projectId;

    protected ?string $serviceAccountPath;

    protected ?array $serviceAccount = null;

    protected bool $disabled = false;

    public function __construct()
    {
        $this->projectId = config('fcm.project_id');
        $this->serviceAccountPath = config('fcm.service_account_path');
        $this->disabled = blank($this->projectId) || blank($this->serviceAccountPath) || ! is_file($this->serviceAccountPath);
    }

    /**
     * Send a data message to a single device token.
     *
     * @param  array<string,mixed>  $data  Flat key/value payload (deep-link route included).
     */
    public function send(string $token, string $title, string $body, array $data = []): bool
    {
        if ($this->disabled) {
            Log::debug('FCM disabled (no credentials) — would push', [
                'title' => $title,
                'tokens' => 1,
            ]);

            return false;
        }

        try {
            $this->loadServiceAccount();

            $message = [
                'token' => $token,
                'notification' => [
                    'title' => mb_substr($title, 0, 120),
                    'body' => mb_substr($body, 0, 240),
                    'sound' => 'default',
                ],
                'data' => array_merge($data, [
                    'title' => $title,
                    'body' => $body,
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    'channel_id' => config('fcm.android_channel_id'),
                ]),
                'android' => [
                    'priority' => 'high',
                    'notification' => [
                        'channel_id' => config('fcm.android_channel_id'),
                        'priority' => 'high',
                        'sound' => 'default',
                    ],
                ],
                'apns' => [
                    'headers' => ['apns-priority' => '10'],
                    'payload' => [
                        'aps' => [
                            'sound' => 'default',
                            'badge' => 1,
                        ],
                    ],
                ],
            ];

            $response = Http::withToken($this->accessToken())
                ->acceptJson()
                ->timeout(config('fcm.timeout', 10))
                ->post(sprintf(config('fcm.endpoint'), $this->projectId), ['message' => $message]);

            if (! $response->successful()) {
                Log::warning('FCM push rejected', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'token_prefix' => substr($token, 0, 10),
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('FCM push failed: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Mint a short-lived OAuth2 access token from the service account.
     */
    protected function accessToken(): string
    {
        $now = time();
        $claims = [
            'iss' => $this->serviceAccount['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ];

        $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $payload = $this->base64UrlEncode(json_encode($claims));
        $signatureInput = $header.'.'.$payload;

        $signature = '';
        openssl_sign($signatureInput, $signature, $this->serviceAccount['private_key'], OPENSSL_ALGO_SHA256);

        $jwt = $signatureInput.'.'.$this->base64UrlEncode($signature);

        $response = Http::asForm()
            ->timeout(config('fcm.timeout', 10))
            ->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('FCM token exchange failed: '.$response->body());
        }

        return $response->json('access_token');
    }

    protected function loadServiceAccount(): void
    {
        if ($this->serviceAccount !== null) {
            return;
        }

        $this->serviceAccount = json_decode(
            (string) file_get_contents($this->serviceAccountPath),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
    }

    protected function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
