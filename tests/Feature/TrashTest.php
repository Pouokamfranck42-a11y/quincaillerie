<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TrashTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'magasinier', 'caissier'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    public function test_deleting_a_product_soft_deletes_it_and_hides_it_from_the_catalogue(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $product = Product::create([
            'reference' => 'TRASH-1', 'name' => 'Produit à supprimer', 'purchase_price' => 100, 'sale_price' => 150,
            'unit' => 'unité', 'low_stock_threshold' => 5,
        ]);

        $this->actingAs($admin)->delete(route('products.destroy', $product))->assertRedirect();

        $this->assertSoftDeleted('products', ['id' => $product->id]);
        $this->assertDatabaseCount('products', 1); // toujours en base, juste masqué
        $this->assertNull(Product::find($product->id));
    }

    public function test_admin_can_restore_a_trashed_product(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $product = Product::create([
            'reference' => 'TRASH-2', 'name' => 'Produit restaurable', 'purchase_price' => 100, 'sale_price' => 150,
            'unit' => 'unité', 'low_stock_threshold' => 5,
        ]);
        $product->delete();

        $this->actingAs($admin)
            ->post(route('trash.restore', ['products', $product->id]))
            ->assertRedirect();

        $this->assertNotNull(Product::find($product->id));
    }

    public function test_force_delete_is_blocked_when_product_has_stock_history(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $product = Product::create([
            'reference' => 'TRASH-3', 'name' => 'Produit avec historique', 'purchase_price' => 100, 'sale_price' => 150,
            'unit' => 'unité', 'low_stock_threshold' => 5,
        ]);
        StockMovement::create(['product_id' => $product->id, 'type' => 'entree', 'quantity' => 10]);
        $product->delete();

        $this->actingAs($admin)
            ->delete(route('trash.force-delete', ['products', $product->id]))
            ->assertRedirect();

        // toujours présent (soft-deleted), la suppression définitive a été refusée
        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    public function test_force_delete_succeeds_when_product_has_no_history(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $product = Product::create([
            'reference' => 'TRASH-4', 'name' => 'Produit sans historique', 'purchase_price' => 100, 'sale_price' => 150,
            'unit' => 'unité', 'low_stock_threshold' => 5,
        ]);
        $product->delete();

        $this->actingAs($admin)
            ->delete(route('trash.force-delete', ['products', $product->id]))
            ->assertRedirect();

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_deleted_user_cannot_log_in(): void
    {
        $user = User::factory()->create(['password' => 'password123']);
        $user->assignRole('caissier');
        $user->delete();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors();
    }

    public function test_non_admin_cannot_access_the_trash(): void
    {
        $magasinier = User::factory()->create();
        $magasinier->assignRole('magasinier');

        $this->actingAs($magasinier)->get(route('trash.index'))->assertForbidden();
    }

    public function test_deleting_a_category_does_not_break_products_that_reference_it(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $category = Category::create(['name' => 'Catégorie test']);
        $product = Product::create([
            'reference' => 'TRASH-5', 'name' => 'Produit catégorisé', 'purchase_price' => 100, 'sale_price' => 150,
            'unit' => 'unité', 'low_stock_threshold' => 5, 'category_id' => $category->id,
        ]);

        $this->actingAs($admin)->delete(route('categories.destroy', $category))->assertRedirect();

        $this->assertSoftDeleted('categories', ['id' => $category->id]);
        // le produit référence toujours la catégorie en base, même si elle n'apparaît plus dans les listes actives
        $this->assertEquals($category->id, $product->fresh()->category_id);
    }
}
