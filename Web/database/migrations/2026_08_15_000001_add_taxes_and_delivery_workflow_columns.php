<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add tax columns to products
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'tax_enabled')) {
                $table->boolean('tax_enabled')->default(false)->after('standard_price');
                $table->string('tax_type')->default('percentage')->after('tax_enabled');
                $table->decimal('tax_rate', 8, 2)->default(0.00)->after('tax_type');
                $table->decimal('tax_amount', 10, 2)->default(0.00)->after('tax_rate');
                $table->decimal('base_price', 10, 2)->nullable()->after('tax_amount');
                $table->decimal('final_price', 10, 2)->nullable()->after('base_price');
            }
        });

        // Add delivery workflow and tax columns to orders
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'delivery_status')) {
                $table->string('delivery_status')->default('pending')->after('status');
            }
            if (! Schema::hasColumn('orders', 'delivery_declined_reason')) {
                $table->text('delivery_declined_reason')->nullable()->after('decline_reason');
            }
            if (! Schema::hasColumn('orders', 'finance_verified_by')) {
                $table->unsignedBigInteger('finance_verified_by')->nullable()->after('approved_by');
                $table->timestamp('finance_verified_at')->nullable()->after('approved_at');
            }
            if (! Schema::hasColumn('orders', 'tax_amount')) {
                $table->decimal('tax_amount', 10, 2)->default(0.00)->after('total_amount');
            }
        });

        // Link payment_submissions to specific order
        Schema::table('payment_submissions', function (Blueprint $table) {
            if (! Schema::hasColumn('payment_submissions', 'order_id')) {
                $table->unsignedBigInteger('order_id')->nullable()->after('franchise_id');
            }
        });

        // Add read receipts to messages
        Schema::table('messages', function (Blueprint $table) {
            if (! Schema::hasColumn('messages', 'is_read')) {
                $table->boolean('is_read')->default(false)->after('message');
                $table->timestamp('read_at')->nullable()->after('is_read');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['tax_enabled', 'tax_type', 'tax_rate', 'tax_amount', 'base_price', 'final_price']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['delivery_status', 'delivery_declined_reason', 'finance_verified_by', 'finance_verified_at', 'tax_amount']);
        });

        Schema::table('payment_submissions', function (Blueprint $table) {
            $table->dropColumn(['order_id']);
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['is_read', 'read_at']);
        });
    }
};
