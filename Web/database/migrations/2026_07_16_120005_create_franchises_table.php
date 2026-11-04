<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('franchises', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('contact_person')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('region')->nullable();
            $table->string('address')->nullable();
            $table->decimal('credit_limit', 15, 2)->default(0);
            $table->decimal('account_balance', 15, 2)->default(0);
            $table->decimal('monthly_target', 15, 2)->default(0);
            $table->string('profile_photo')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('franchises');
    }
};
