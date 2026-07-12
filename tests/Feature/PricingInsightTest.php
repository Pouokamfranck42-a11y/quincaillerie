<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PricingInsightTest extends TestCase
{
    use RefreshDatabase;

    public function test_dormant_product_shows_a_price_decrease_suggestion(): void
    {
        Role::findOrCreate('admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $product = Product::create([
            'reference' => 'DORM-1', 'name' => 'Produit dormant', 'purchase_price' => 100, 'sale_price' => 150,
            'unit' => 'unité', 'low_stock_threshold' => 5,
        ]);
        StockMovement::create(['product_id' => $product->id, 'type' => StockMovement::TYPE_ENTREE, 'quantity' => 20]);

        $response = $this->actingAs($admin)->get(route('products.show', $product));

        $response->assertOk();
        $response->assertViewHas('pricing', fn ($pricing) => $pricing['is_dormant'] === true && str_contains($pricing['suggestion'], 'dormant'));
    }

    public function test_active_product_with_no_history_has_no_suggestion(): void
    {
        Role::findOrCreate('admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $category = Category::create(['name' => 'Test']);
        $product = Product::create([
            'reference' => 'NEUF-1', 'name' => 'Produit neuf sans stock', 'purchase_price' => 100, 'sale_price' => 150,
            'unit' => 'unité', 'low_stock_threshold' => 5, 'category_id' => $category->id,
        ]);

        $response = $this->actingAs($admin)->get(route('products.show', $product));

        $response->assertOk();
        $response->assertViewHas('pricing', fn ($pricing) => $pricing['is_dormant'] === false && $pricing['suggestion'] === null);
    }
}
