<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class ProductImageSeeder extends Seeder
{
    public function run(): void
    {
        $products = Product::whereNull('image')->get();

        foreach ($products as $product) {
            $candidate = 'products/'.$product->sku.'.png';

            if (Storage::disk('public')->exists($candidate)) {
                $product->update(['image' => $candidate]);
            }
        }
    }
}
