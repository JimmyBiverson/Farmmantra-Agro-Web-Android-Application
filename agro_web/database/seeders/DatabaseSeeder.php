<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Franchise;
use App\Models\PriceSlab;
use App\Models\Product;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── Roles ─────────────────────────────────────────────
        $adminRole = Role::firstOrCreate(['name' => 'System Administrator', 'guard_name' => 'web']);
        $staffRole = Role::firstOrCreate(['name' => 'Farmmantra Staff', 'guard_name' => 'web']);
        $financeRole = Role::firstOrCreate(['name' => 'Finance Department', 'guard_name' => 'web']);
        $franchiseRole = Role::firstOrCreate(['name' => 'Franchise Partner', 'guard_name' => 'web']);
        $warehouseRole = Role::firstOrCreate(['name' => 'Warehouse', 'guard_name' => 'web']);

        // ── Admin User ────────────────────────────────────────
        User::firstOrCreate(
            ['email' => 'admin@farmmantra.co.ug'],
            [
                'name' => 'System Admin',
                'password' => Hash::make('password123'),
                'role_id' => $adminRole->id,
                'phone' => '+256700000001',
                'is_active' => true,
            ]
        );

        // ── Staff User ────────────────────────────────────────
        User::firstOrCreate(
            ['email' => 'staff@farmmantra.co.ug'],
            [
                'name' => 'Farmmantra Staff',
                'password' => Hash::make('password123'),
                'role_id' => $staffRole->id,
                'phone' => '+256700000002',
                'is_active' => true,
            ]
        );

        // ── Finance User ──────────────────────────────────────
        User::firstOrCreate(
            ['email' => 'finance@farmmantra.co.ug'],
            [
                'name' => 'Finance Officer',
                'password' => Hash::make('password123'),
                'role_id' => $financeRole->id,
                'phone' => '+256700000003',
                'is_active' => true,
            ]
        );

        // ── Warehouse User (accepts & dispatches orders) ──────
        User::firstOrCreate(
            ['email' => 'warehouse@farmmantra.co.ug'],
            [
                'name' => 'Warehouse Officer',
                'password' => Hash::make('password123'),
                'role_id' => $warehouseRole->id,
                'phone' => '+256700000004',
                'is_active' => true,
            ]
        );

        // ── Franchises ────────────────────────────────────────
        $kampala = Franchise::firstOrCreate(
            ['code' => 'FRN-001'],
            [
                'name' => 'Kampala Franchise',
                'contact_person' => 'James Okello',
                'phone' => '+256712000001',
                'email' => 'kampala@farmmantra.co.ug',
                'region' => 'Central',
                'address' => 'Plot 12, Kampala Road',
                'credit_limit' => 20000000,
                'account_balance' => 0,
                'monthly_target' => 10000000,
                'is_active' => true,
            ]
        );

        $jinja = Franchise::firstOrCreate(
            ['code' => 'FRN-002'],
            [
                'name' => 'Jinja Franchise',
                'contact_person' => 'Sarah Nakamya',
                'phone' => '+256712000002',
                'email' => 'jinja@farmmantra.co.ug',
                'region' => 'Eastern',
                'address' => 'Nkrumah Road, Jinja',
                'credit_limit' => 15000000,
                'account_balance' => 0,
                'monthly_target' => 8000000,
                'is_active' => true,
            ]
        );

        // ── Franchise Partner Users ───────────────────────────
        User::firstOrCreate(
            ['email' => 'franchise@farmmantra.co.ug'],
            [
                'name' => 'Kampala Franchise',
                'password' => Hash::make('password123'),
                'role_id' => $franchiseRole->id,
                'franchise_id' => $kampala->id,
                'phone' => '+256712345678',
                'is_active' => true,
            ]
        );

        User::firstOrCreate(
            ['email' => 'franchise2@farmmantra.co.ug'],
            [
                'name' => 'Jinja Franchise',
                'password' => Hash::make('password123'),
                'role_id' => $franchiseRole->id,
                'franchise_id' => $jinja->id,
                'phone' => '+256787654321',
                'is_active' => true,
            ]
        );

        // ── Product Categories ────────────────────────────────
        $categories = [
            ['name' => 'Herbicides', 'slug' => 'herbicides', 'description' => 'Weed control products', 'sort_order' => 1],
            ['name' => 'Insecticides', 'slug' => 'insecticides', 'description' => 'Pest control products', 'sort_order' => 2],
            ['name' => 'Fungicides', 'slug' => 'fungicides', 'description' => 'Fungal disease control', 'sort_order' => 3],
            ['name' => 'Organic Products', 'slug' => 'organic-products', 'description' => 'Organic farming inputs', 'sort_order' => 4],
            ['name' => 'Seeds', 'slug' => 'seeds', 'description' => 'Crop seeds', 'sort_order' => 5],
            ['name' => 'Fertilizers', 'slug' => 'fertilizers', 'description' => 'Plant nutrition products', 'sort_order' => 6],
            ['name' => 'PGR', 'slug' => 'pgr', 'description' => 'Plant Growth Regulators', 'sort_order' => 7],
        ];

        $catModels = [];
        foreach ($categories as $cat) {
            $catModels[$cat['slug']] = Category::firstOrCreate(
                ['slug' => $cat['slug']],
                ['name' => $cat['name'], 'description' => $cat['description'], 'sort_order' => $cat['sort_order'], 'is_active' => true]
            );
        }

        // ── Products ──────────────────────────────────────────
        $products = [
            ['name' => 'Roundup PowerMax', 'sku' => 'HERB-001', 'cat' => 'herbicides', 'uom' => 'Litres', 'pkg' => '1L, 5L, 20L', 'price' => 45000],
            ['name' => 'Thunder 145-SE', 'sku' => 'INSE-001', 'cat' => 'insecticides', 'uom' => 'Litres', 'pkg' => '1L, 5L', 'price' => 35000],
            ['name' => 'Ridomil Gold', 'sku' => 'FUNG-001', 'cat' => 'fungicides', 'uom' => 'Kg', 'pkg' => '1kg, 5kg', 'price' => 55000],
            ['name' => 'Neem Oil Extract', 'sku' => 'ORGA-001', 'cat' => 'organic-products', 'uom' => 'Litres', 'pkg' => '500ml, 1L', 'price' => 25000],
            ['name' => 'NAARI 505 Maize Seed', 'sku' => 'SEED-001', 'cat' => 'seeds', 'uom' => 'Kg', 'pkg' => '2kg, 10kg', 'price' => 18000],
            ['name' => 'NPK 17:17:17', 'sku' => 'FERT-001', 'cat' => 'fertilizers', 'uom' => 'Kg', 'pkg' => '5kg, 50kg', 'price' => 12000],
            ['name' => 'Gibberellic Acid 10% WP', 'sku' => 'PGR-001', 'cat' => 'pgr', 'uom' => 'Kg', 'pkg' => '250g, 1kg', 'price' => 85000],
            ['name' => 'Dual Gold 960 EC', 'sku' => 'HERB-002', 'cat' => 'herbicides', 'uom' => 'Litres', 'pkg' => '5L', 'price' => 62000],
            ['name' => 'Decis Forte', 'sku' => 'INSE-002', 'cat' => 'insecticides', 'uom' => 'Litres', 'pkg' => '1L', 'price' => 48000],
            ['name' => 'Bravo 500 SC', 'sku' => 'FUNG-002', 'cat' => 'fungicides', 'uom' => 'Litres', 'pkg' => '1L, 5L', 'price' => 42000],
            ['name' => 'CAN Fertilizer', 'sku' => 'FERT-002', 'cat' => 'fertilizers', 'uom' => 'Kg', 'pkg' => '50kg', 'price' => 8500],
            ['name' => 'Bean Seed NAROBEAN', 'sku' => 'SEED-002', 'cat' => 'seeds', 'uom' => 'Kg', 'pkg' => '5kg, 10kg', 'price' => 22000],
        ];

        $productModels = [];
        foreach ($products as $p) {
            $image = 'products/'.$p['sku'].'.png';
            $productModels[$p['sku']] = Product::firstOrCreate(
                ['sku' => $p['sku']],
                [
                    'name' => $p['name'],
                    'category_id' => $catModels[$p['cat']]->id,
                    'unit_of_measure' => $p['uom'],
                    'packaging_details' => $p['pkg'],
                    'standard_price' => $p['price'],
                    'selling_price' => $p['price'],
                    'image' => $image,
                    'is_active' => true,
                ]
            );
        }

        // ── Price Slabs ───────────────────────────────────────
        foreach ($productModels as $sku => $product) {
            PriceSlab::firstOrCreate(
                ['product_id' => $product->id, 'min_quantity' => 1],
                ['max_quantity' => 9, 'slab_price' => $product->standard_price, 'is_active' => true]
            );
            PriceSlab::firstOrCreate(
                ['product_id' => $product->id, 'min_quantity' => 10],
                ['max_quantity' => 49, 'slab_price' => round($product->standard_price * 0.93), 'is_active' => true]
            );
            PriceSlab::firstOrCreate(
                ['product_id' => $product->id, 'min_quantity' => 50],
                ['max_quantity' => null, 'slab_price' => round($product->standard_price * 0.85), 'is_active' => true]
            );
        }

        // Now run the dashboard data seeder for orders, sales, payments, inventory
        $this->call(DashboardDataSeeder::class);
        $this->call(SettingsSeeder::class);

        // Link demo franchises to their business regions and make every
        // account statement agree with the seeded balances.
        foreach (['FRN-001' => 'Central', 'FRN-002' => 'Eastern'] as $code => $regionName) {
            $regionId = Region::where('name', $regionName)->value('id');
            if ($regionId) {
                Franchise::where('code', $code)->whereNull('region_id')->update(['region_id' => $regionId]);
            }
        }
        Artisan::call('ledger:sync-opening-balances');
    }
}
