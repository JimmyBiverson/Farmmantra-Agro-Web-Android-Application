<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Per-item tax breakdown on orders ─────────────────────────
        Schema::table('order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('order_items', 'base_unit_price')) {
                $table->decimal('base_unit_price', 15, 2)->nullable()->after('unit_price');
            }
            if (! Schema::hasColumn('order_items', 'tax_rate')) {
                $table->decimal('tax_rate', 8, 2)->default(0.00)->after('base_unit_price');
            }
            if (! Schema::hasColumn('order_items', 'tax_amount')) {
                $table->decimal('tax_amount', 15, 2)->default(0.00)->after('tax_rate');
            }
        });

        // ── "Request more information" payment state ─────────────────
        Schema::table('payment_submissions', function (Blueprint $table) {
            if (! Schema::hasColumn('payment_submissions', 'info_requested_by')) {
                $table->foreignId('info_requested_by')->nullable()->after('finance_notes')->constrained('users')->onDelete('set null');
                $table->timestamp('info_requested_at')->nullable()->after('info_requested_by');
                $table->text('info_request_note')->nullable()->after('info_requested_at');
            }
        });

        if (Schema::hasTable('payment_submissions')) {
            Schema::table('payment_submissions', function (Blueprint $table) {
                if (Schema::hasColumn('payment_submissions', 'status')) {
                    $table->string('status')->default('pending')->change();
                }
            });
        }

        // ── Payment ⇄ Orders many-to-many linking ────────────────────
        if (! Schema::hasTable('payment_order')) {
            Schema::create('payment_order', function (Blueprint $table) {
                $table->id();
                $table->foreignId('payment_submission_id')->constrained()->onDelete('cascade');
                $table->foreignId('order_id')->constrained()->onDelete('cascade');
                $table->decimal('allocated_amount', 15, 2)->default(0);
                $table->timestamps();
                $table->unique(['payment_submission_id', 'order_id']);
            });
        }

        // ── User-level real-time + notification preferences ──────────
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'fcm_token')) {
                $table->string('fcm_token', 500)->nullable()->after('date_of_birth');
            }
            if (! Schema::hasColumn('users', 'notification_preferences')) {
                $table->json('notification_preferences')->nullable()->after('fcm_token');
            }
        });

        // ── Message delivery status (delivered / read) ────────────────
        Schema::table('messages', function (Blueprint $table) {
            if (! Schema::hasColumn('messages', 'is_delivered')) {
                $table->boolean('is_delivered')->default(false)->after('is_read');
                $table->timestamp('delivered_at')->nullable()->after('read_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['base_unit_price', 'tax_rate', 'tax_amount']);
        });

        Schema::table('payment_submissions', function (Blueprint $table) {
            $table->dropColumn(['info_requested_by', 'info_requested_at', 'info_request_note']);
        });

        Schema::dropIfExists('payment_order');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['fcm_token', 'notification_preferences']);
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['is_delivered', 'delivered_at']);
        });
    }
};
