<?php

namespace App\Services;

use App\Models\DigitalSignature;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Generates a unique digital signature number every time a user confirms an
 * order, dispatch, delivery or payment, so it is always traceable exactly who
 * confirmed what and when. The payload hash lets auditors detect later edits
 * to the signed record's key figures.
 */
class SignatureService
{
    public static function sign(User $user, string $action, Model $subject, array $payload = []): DigitalSignature
    {
        $signedAt = now();
        $hashInput = json_encode([
            'action' => $action,
            'type' => get_class($subject),
            'id' => $subject->getKey(),
            'user' => $user->id,
            'at' => $signedAt->toIso8601String(),
            'payload' => $payload,
        ]);

        return DigitalSignature::create([
            'signature_number' => self::generateNumber(),
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_role' => $user->role?->name,
            'action' => $action,
            'signable_type' => get_class($subject),
            'signable_id' => $subject->getKey(),
            'payload_hash' => hash('sha256', $hashInput),
            'ip_address' => request()?->ip(),
            'signed_at' => $signedAt,
        ]);
    }

    public static function generateNumber(): string
    {
        do {
            $number = 'FMS-'.now()->format('ymd').'-'.strtoupper(Str::random(6));
        } while (DigitalSignature::where('signature_number', $number)->exists());

        return $number;
    }
}
