<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\Ai\GeminiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductAiHelpersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'magasinier'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    public function test_photo_recognition_returns_matching_products(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Product::create([
            'reference' => 'MARTEAU-1', 'name' => 'Marteau de charpentier', 'purchase_price' => 1000, 'sale_price' => 1500,
            'unit' => 'unité', 'low_stock_threshold' => 5,
        ]);

        $this->mock(GeminiService::class, function ($mock) {
            $mock->shouldReceive('extractStructured')->once()->andReturn([
                'object_type' => 'marteau',
                'probable_material' => 'acier et bois',
                'probable_use' => 'enfoncer des clous',
                'keywords' => ['marteau'],
            ]);
            $mock->shouldReceive('lastErrorMessage')->andReturn(null);
        });

        $response = $this->actingAs($admin)->post(route('products.recognize-photo'), [
            'photo' => UploadedFile::fake()->image('objet.jpg'),
        ]);

        $response->assertOk();
        $response->assertJsonFragment(['object_type' => 'marteau']);
        $response->assertJsonCount(1, 'matches');
    }

    public function test_generate_description_returns_ai_text(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $category = Category::create(['name' => 'Outillage']);

        $this->mock(GeminiService::class, function ($mock) {
            $mock->shouldReceive('generateText')->once()->andReturn('Un marteau robuste pour tous vos travaux de charpente.');
            $mock->shouldReceive('lastErrorMessage')->andReturn(null);
        });

        $response = $this->actingAs($admin)->postJson(route('products.generate-description'), [
            'name' => 'Marteau de charpentier',
            'brand' => 'Stanley',
            'category_id' => $category->id,
        ]);

        $response->assertOk();
        $response->assertJson(['description' => 'Un marteau robuste pour tous vos travaux de charpente.']);
    }
}
