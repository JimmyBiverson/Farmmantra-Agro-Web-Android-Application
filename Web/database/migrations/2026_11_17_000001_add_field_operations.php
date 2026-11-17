<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('franchises', fn (Blueprint $t) => $t->string('outlet_type')->nullable());
        Schema::table('products', fn (Blueprint $t) => $t->timestamp('price_changed_at')->nullable());
        Schema::table('orders', fn (Blueprint $t) => $t->string('efris_reference')->nullable()->unique());
        foreach (['Operations Manager', 'Office Sales'] as $role) {
            DB::table('roles')->updateOrInsert(['name' => $role], ['guard_name' => 'web']);
        }
        Schema::create('stock_checks', function (Blueprint $t) {
            $t->id();
            $t->uuid('submission_id')->unique();
            $t->foreignId('user_id')->constrained();
            $t->foreignId('franchise_id')->constrained();
            $t->timestamp('counted_at');
            $t->json('lines');
            $t->string('photo')->nullable();
            $t->json('photo_location')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_checks');
        Schema::table('orders', function (Blueprint $t) {
            $t->dropUnique(['efris_reference']);
            $t->dropColumn('efris_reference');
        });
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn('price_changed_at'));
        Schema::table('franchises', fn (Blueprint $t) => $t->dropColumn('outlet_type'));
    }
};
