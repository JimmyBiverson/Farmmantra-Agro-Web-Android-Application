<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('franchise_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('customer_code')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('tax_id')->nullable();
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->decimal('credit_balance', 15, 2)->default(0);
            $table->boolean('is_wholesale')->default(false);
            $table->decimal('balance', 15, 2)->default(0);
            $table->integer('loyalty_points')->default(0);
            $table->foreignId('branch_id')->nullable()->constrained()->onDelete('set null');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
