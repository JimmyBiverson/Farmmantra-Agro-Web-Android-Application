<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('franchise_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_category_id')->nullable()->constrained('categories')->onDelete('set null');
            $table->decimal('target_amount', 15, 2)->default(0);
            $table->integer('target_quantity')->default(0);
            $table->integer('month');
            $table->integer('year');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_targets');
    }
};
