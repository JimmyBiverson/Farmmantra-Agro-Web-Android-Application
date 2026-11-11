<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! DB::table('regions')->where('name', 'Southern')->exists()) {
            DB::table('regions')->insert(['name' => 'Southern', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Preserve client district/franchise assignments if this migration is rolled back.
    }
};
