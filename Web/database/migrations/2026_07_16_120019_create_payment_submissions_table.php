<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('payment_number')->unique();
            $table->foreignId('franchise_id')->constrained()->onDelete('cascade');
            $table->decimal('amount', 15, 2);
            $table->string('payment_method')->default('bank_transfer');
            $table->string('transaction_reference')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('proof_of_payment_path')->nullable();
            $table->enum('status', ['pending', 'verified', 'accepted', 'rejected'])->default('pending');
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('accepted_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('accepted_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('finance_notes')->nullable();
            $table->decimal('verified_amount', 15, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_submissions');
    }
};
