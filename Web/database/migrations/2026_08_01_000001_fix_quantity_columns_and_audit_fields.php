<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Allow fractional quantities (Litres/Kg) by converting INT quantity
        // columns to DECIMAL(15,2). This matches the decimal:2 casts already
        // present on the Eloquent models.
        $decimalColumns = [
            'order_items' => ['quantity', 'adjusted_quantity'],
            'sale_items' => ['quantity'],
            'stock_receipt_items' => ['ordered_quantity', 'received_quantity'],
            'stock_movements' => ['quantity'],
            'franchise_inventories' => ['quantity', 'reorder_level'],
            'warehouse_inventories' => ['quantity', 'reserved_quantity', 'reorder_level'],
            'sales_targets' => ['target_quantity'],
        ];

        foreach ($decimalColumns as $tableName => $columns) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }
            foreach ($columns as $column) {
                if (! Schema::hasColumn($tableName, $column)) {
                    continue;
                }
                Schema::table($tableName, function (Blueprint $table) use ($column) {
                    $table->decimal($column, 15, 2)->nullable()->change();
                });
            }
        }

        // Audit fields for order declines (staff) — avoids reusing approved_by/approved_at.
        if (Schema::hasTable('orders') && ! Schema::hasColumn('orders', 'declined_by')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->foreignId('declined_by')->nullable()->after('approved_at')->constrained('users')->onDelete('set null');
                $table->timestamp('declined_at')->nullable()->after('declined_by');
            });
        }

        // Audit fields for payment rejections (finance).
        if (Schema::hasTable('payment_submissions') && ! Schema::hasColumn('payment_submissions', 'rejected_by')) {
            Schema::table('payment_submissions', function (Blueprint $table) {
                $table->foreignId('rejected_by')->nullable()->after('accepted_at')->constrained('users')->onDelete('set null');
                $table->timestamp('rejected_at')->nullable()->after('rejected_by');
            });
        }

        // Performance indexes on frequently filtered / reported columns.
        $indexes = [
            'orders' => [
                ['orders_status_idx', ['status']],
                ['orders_franchise_created_idx', ['franchise_id', 'created_at']],
            ],
            'order_items' => [
                ['order_items_product_idx', ['product_id']],
            ],
            'sales' => [
                ['sales_status_idx', ['payment_status']],
                ['sales_franchise_date_idx', ['franchise_id', 'sale_date']],
            ],
            'sale_items' => [
                ['sale_items_product_idx', ['product_id']],
            ],
            'payment_submissions' => [
                ['payments_status_idx', ['status']],
                ['payments_franchise_submitted_idx', ['franchise_id', 'submitted_at']],
            ],
            'franchise_inventories' => [
                ['franchise_inv_franchise_idx', ['franchise_id']],
                ['franchise_inv_product_idx', ['product_id']],
            ],
            'warehouse_inventories' => [
                ['warehouse_inv_product_idx', ['product_id']],
            ],
            'stock_movements' => [
                ['stock_movements_product_idx', ['product_id']],
                ['stock_movements_reference_idx', ['reference_type', 'reference_id']],
            ],
            'sales_targets' => [
                ['sales_targets_franchise_month_idx', ['franchise_id', 'month', 'year']],
            ],
            'messages' => [
                ['messages_conversation_idx', ['conversation_id']],
            ],
            'stock_receipt_items' => [
                ['stock_receipt_items_product_idx', ['product_id']],
            ],
        ];

        foreach ($indexes as $table => $tableIndexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            Schema::table($table, function (Blueprint $table) use ($tableIndexes) {
                foreach ($tableIndexes as [$indexName, $columns]) {
                    $table->index($columns, $indexName);
                }
            });
        }
    }

    public function down(): void
    {
        // Intentionally left non-destructive; columns are additive and indexes are safe to keep.
    }
};
