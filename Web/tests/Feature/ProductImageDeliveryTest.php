<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductImageDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_uploaded_photos_are_available_to_frontend_without_storage_link(): void
    {
        Storage::fake('public');
        $role = Role::create(['name' => 'System Administrator', 'guard_name' => 'web']);
        $admin = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        $category = Category::create(['name' => 'Seeds', 'slug' => 'seeds']);
        $this->actingAs($admin)->post(route('web.admin.products.store'), [
            'name' => 'Photo test', 'sku' => 'PHOTO-001', 'category_id' => $category->id,
            'unit_of_measure' => 'Kg', 'selling_price' => 120, 'standard_price' => 100,
            'image' => UploadedFile::fake()->image('cover.png'),
            'images' => [UploadedFile::fake()->image('gallery.jpg')],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $product = Product::with('images')->where('sku', 'PHOTO-001')->firstOrFail();
        $this->assertCount(2, $product->all_images);
        foreach ($product->all_images as $url) {
            $this->assertStringContainsString('/product-asset/products/', $url);
            $this->get($url)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        }
        $this->get(route('web.admin.products'))->assertOk()->assertSee($product->image_url);
        $product->update(['image' => null]);
        $this->assertSame($product->images->first()->image_url, $product->image_url);
    }

    public function test_photo_endpoint_does_not_expose_other_uploads_or_missing_files(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('payment-proofs/private.png', 'private');
        Storage::disk('public')->put('products/script.php', '<?php echo 1;');
        foreach (['payment-proofs/private.png', 'products/script.php', 'products/missing.png', 'products/../payment-proofs/private.png'] as $path) {
            $this->get('/product-asset/'.$path)->assertNotFound();
        }
    }
}
