<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class ClientRolesSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Sales Head' => 'saleshead@farmmantra.co.ug', 'Field Agronomist' => 'agronomist@farmmantra.co.ug'] as $name => $email) {
            $role = Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            if (app()->environment('local') && env('DEMO_DATA') && env('DEMO_PASSWORD')) {
                User::firstOrCreate(['email' => $email], ['name' => $name, 'role_id' => $role->id, 'password' => env('DEMO_PASSWORD'), 'is_active' => true]);
            }
        }
    }
}
