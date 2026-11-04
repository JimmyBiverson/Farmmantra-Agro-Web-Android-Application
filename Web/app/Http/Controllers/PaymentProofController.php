<?php

namespace App\Http\Controllers;

use App\Models\PaymentSubmission;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PaymentProofController extends Controller
{
    public function __invoke(PaymentSubmission $payment): BinaryFileResponse
    {
        $path = $payment->proof_of_payment_path;
        abort_unless($path && str_starts_with($path, 'payment-proofs/') && ! str_contains($path, '..'), 404);
        abort_unless(Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path), [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }
}
