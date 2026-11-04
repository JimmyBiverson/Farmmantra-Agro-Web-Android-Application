<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('password')->constrained('roles')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->after('role_id')->constrained('branches')->nullOnDelete();
            $table->foreignId('franchise_id')->nullable()->after('branch_id')->constrained('franchises')->nullOnDelete();
            $table->string('phone', 20)->nullable()->after('franchise_id');
            $table->string('pin_code', 6)->nullable()->after('phone');
            $table->boolean('is_active')->default(true)->after('pin_code');
            $table->string('status')->default('active')->after('is_active');
            $table->string('avatar')->nullable()->after('status');
            $table->timestamp('last_login_at')->nullable()->after('avatar');
            $table->string('employee_id')->nullable()->after('last_login_at');
            $table->text('address')->nullable()->after('employee_id');
            $table->string('gender', 20)->nullable()->after('address');
            $table->date('date_of_birth')->nullable()->after('gender');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
            $table->dropForeign(['branch_id']);
            $table->dropForeign(['franchise_id']);
            $table->dropColumn([
                'role_id', 'branch_id', 'franchise_id',
                'phone', 'pin_code', 'is_active', 'status',
                'avatar', 'last_login_at', 'employee_id',
                'address', 'gender', 'date_of_birth',
            ]);
        });
    }
};
