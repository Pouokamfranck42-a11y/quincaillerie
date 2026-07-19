<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Phase 4 — recherche floue (pg_trgm) : jusqu'ici, toute recherche produit était un ILIKE
 * substring pur, sans aucune tolérance à la faute de frappe.
 */
class FuzzySearchTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate('admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    public function test_search_scope_finds_a_product_despite_a_typo(): void
    {
        Product::create([
            'reference' => 'PERCEUSE-1', 'name' => 'Perceuse-visseuse sans fil 18V', 'purchase_price' => 100, 'sale_price' => 200,
            'unit' => 'unité',
        ]);

        // "perceuze" au lieu de "perceuse" — ne matcherait jamais un ILIKE '%perceuze%'.
        $results = Product::query()->search('perceuze')->get();

        $this->assertCount(1, $results);
        $this->assertSame('Perceuse-visseuse sans fil 18V', $results->first()->name);
    }

    public function test_search_scope_still_finds_exact_substring_matches(): void
    {
        Product::create([
            'reference' => 'MARTEAU-1', 'name' => 'Marteau de charpentier', 'purchase_price' => 100, 'sale_price' => 200,
            'unit' => 'unité',
        ]);

        $results = Product::query()->search('marteau')->get();

        $this->assertCount(1, $results);
    }

    public function test_search_scope_ignores_completely_unrelated_terms(): void
    {
        Product::create([
            'reference' => 'MARTEAU-2', 'name' => 'Marteau de charpentier', 'purchase_price' => 100, 'sale_price' => 200,
            'unit' => 'unité',
        ]);

        $results = Product::query()->search('xyzabc123')->get();

        $this->assertCount(0, $results);
    }

    public function test_product_index_search_tolerates_a_typo(): void
    {
        $admin = $this->admin();
        Product::create([
            'reference' => 'CABLE-TYPO', 'name' => 'Câble électrique souple', 'purchase_price' => 100, 'sale_price' => 200,
            'unit' => 'mètre',
        ]);

        // Sans accents ("cable" au lieu de "câble") — doit quand même remonter.
        $response = $this->actingAs($admin)->get(route('products.index', ['q' => 'cable electrique']));

        $response->assertOk();
        $response->assertSee('Câble électrique souple');
    }
}
