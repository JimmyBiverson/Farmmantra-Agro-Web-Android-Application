<?php

namespace App\Services;

use App\Models\SmsMessage;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Bulk SMS. The driver is chosen with SMS_DRIVER:
 *  - "log" (default): messages are written to the log and the SMS history,
 *    nothing is sent. Safe for development.
 *  - "africastalking": sends through Africa's Talking (common in Uganda).
 *    Requires SMS_AT_USERNAME, SMS_AT_API_KEY and optionally SMS_SENDER_ID.
 */
class SmsService
{
    public static function normalizeNumber(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $phone);
        if ($digits === '') {
            return null;
        }
        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            $digits = '256'.substr($digits, 1);
        } elseif (strlen($digits) === 9) {
            $digits = '256'.$digits;
        }

        return strlen($digits) >= 11 && strlen($digits) <= 13 ? '+'.$digits : null;
    }

    /** @param  array<int, string>  $numbers */
    public static function send(User $sender, array $numbers, string $message, string $audience): SmsMessage
    {
        $recipients = array_values(array_unique(array_filter(array_map([self::class, 'normalizeNumber'], $numbers))));
        $driver = config('services.sms.driver', 'log');

        $record = SmsMessage::create([
            'sent_by' => $sender->id,
            'audience' => $audience,
            'message' => $message,
            'recipients' => $recipients,
            'recipient_count' => count($recipients),
            'driver' => $driver,
            'status' => 'queued',
        ]);

        if (empty($recipients)) {
            $record->update(['status' => 'failed', 'provider_response' => 'No valid phone numbers in the selected audience.']);

            return $record;
        }

        if ($driver === 'africastalking') {
            try {
                $response = Http::asForm()
                    ->withHeaders(['apiKey' => (string) config('services.sms.at_api_key'), 'Accept' => 'application/json'])
                    ->timeout(30)
                    ->post(config('services.sms.at_endpoint'), array_filter([
                        'username' => config('services.sms.at_username'),
                        'to' => implode(',', $recipients),
                        'message' => $message,
                        'from' => config('services.sms.sender_id'),
                    ]));
                $record->update([
                    'status' => $response->successful() ? 'sent' : 'failed',
                    'provider_response' => mb_substr($response->body(), 0, 5000),
                ]);
            } catch (\Throwable $e) {
                $record->update(['status' => 'failed', 'provider_response' => $e->getMessage()]);
            }
        } else {
            Log::info('[SMS:log driver] '.count($recipients).' recipient(s): '.$message, ['to' => $recipients]);
            $record->update(['status' => 'logged', 'provider_response' => 'SMS_DRIVER=log — not sent. Configure a provider to deliver messages.']);
        }

        ActivityLogger::log('sms.bulk_sent', "Bulk SMS to {$record->recipient_count} recipient(s) ({$audience}) — {$record->status}", $record, $sender->id);

        return $record;
    }
}
