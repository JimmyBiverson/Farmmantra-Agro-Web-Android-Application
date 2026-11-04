<?php

namespace Tests\Feature;

use App\Models\Franchise;
use App\Models\PaymentSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PaymentProofTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_proofs_require_a_valid_unexpired_signature(): void
    {
        Storage::fake('local');
        $franchise = Franchise::create(['name' => 'Demo', 'code' => 'DEMO', 'is_active' => true]);
        $payment = PaymentSubmission::create(['payment_number' => 'PAY-PRIVATE', 'franchise_id' => $franchise->id, 'amount' => '100.00', 'status' => 'pending', 'proof_of_payment_path' => 'payment-proofs/test.pdf']);
        Storage::disk('local')->put('payment-proofs/test.pdf', '%PDF-1.4 demo');
        $this->get('/payment-proofs/'.$payment->id)->assertForbidden();
        $url = URL::temporarySignedRoute('payment.proof', now()->addMinutes(5), ['payment' => $payment->id], absolute: false);
        $this->get($url)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->travel(6)->minutes();
        $this->get($url)->assertForbidden();
    }
}
