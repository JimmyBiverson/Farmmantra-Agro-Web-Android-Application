<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (['Sales Head', 'Field Agronomist'] as $role) {
            DB::table('roles')->insertOrIgnore(['name' => $role, 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);
        }
        Schema::table('orders', function (Blueprint $table) {
            $table->string('review_stage')->nullable()->index();
            $table->json('review_history')->nullable();
            $table->timestamp('closed_at')->nullable();
        });
        Schema::table('products', function (Blueprint $table) {
            $table->string('batch_number')->nullable();
            $table->date('manufactured_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->text('active_ingredient')->nullable();
        });
        Schema::create('operation_requests', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('franchise_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('type')->index();
            $table->string('status')->default('submitted')->index();
            $table->string('title');
            $table->decimal('amount', 16, 2)->default(0);
            $table->date('entry_date');
            $table->json('details');
            $table->json('history')->nullable();
            $table->foreignId('fund_request_id')->nullable()->constrained('operation_requests')->restrictOnDelete();
            $table->timestamps();
            $table->index(['user_id', 'type', 'entry_date']);
        });
        Schema::create('goods_returns', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('franchise_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('sales_head')->index();
            $table->text('reason');
            $table->string('transport');
            $table->json('items');
            $table->json('photos')->nullable();
            $table->json('history')->nullable();
            $table->foreignId('credit_note_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('knowledge_articles', function (Blueprint $table) {
            $table->id();
            $table->string('kind')->index();
            $table->string('crop')->nullable()->index();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('title');
            $table->string('image')->nullable();
            $table->json('content');
            $table->boolean('published')->default(false)->index();
            $table->foreignId('updated_by')->constrained('users');
            $table->timestamps();
        });
        Schema::create('field_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedInteger('radius_meters');
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
        Schema::table('sales_targets', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('period')->default('monthly');
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('field_zones');
        Schema::dropIfExists('knowledge_articles');
        Schema::dropIfExists('goods_returns');
        Schema::dropIfExists('operation_requests');
        Schema::table('sales_targets', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['product_id']);
            $table->dropColumn(['user_id', 'product_id', 'period', 'starts_on', 'ends_on']);
        });
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn(['batch_number', 'manufactured_at', 'expires_at', 'active_ingredient']));
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn(['review_stage', 'review_history', 'closed_at']));
    }
};
