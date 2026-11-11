<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('orders')->where('status', 'pending')->whereNull('review_stage')->whereNull('approved_at')->whereNull('dispatched_at')->whereNull('received_at')->update(['review_stage' => 'sales_head']);
    }

    public function down(): void
    {
        // Approval history is business data. Rolling back schema must not undo review decisions.
    }
};
