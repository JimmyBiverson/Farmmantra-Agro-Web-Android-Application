<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ClientCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $items = json_decode(file_get_contents(__DIR__.'/client-catalog.json'), true, flags: JSON_THROW_ON_ERROR);
        DB::transaction(function () use ($items) {
            foreach ($items as $item) {
                $name = Str::title(strtolower($item['category'])).'s';
                $category = Category::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name]);
                Product::firstOrCreate(['sku' => $item['sku']], [
                    'name' => $item['name'], 'category_id' => $category->id, 'active_ingredient' => $item['active_ingredient'],
                    'packaging_details' => $item['packaging_details'], 'unit_of_measure' => $item['unit_of_measure'],
                    'description' => 'Pack contents measured in '.$item['volume_unit'].'.', 'is_active' => false,
                ]);
            }
        });
    }
}
