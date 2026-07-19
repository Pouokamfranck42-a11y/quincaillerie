<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Ai\GeminiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Phase 3 — réapprovisionnement intelligent (date limite de commande basée sur le délai
 * fournisseur, jamais exploité jusqu'ici) et détection dormants/surstock avec action suggérée.
 * Le calcul est statistique (fiable, testable) ; l'IA n'est qu'un résumé en surcouche qui
 * dégrade proprement à null si Gemini échoue — jamais bloquant pour l'écran.
 */
class SmartReorderAndDormantStockTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate('admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    // --- Product::recommendedOrderByDate() / isUrgentReorder() ---

    public function test_recommended_order_date_accounts_for_supplier_lead_time(): void
    {
        $supplier = Supplier::create(['name' => 'Fournisseur rapide', 'lead_time_days' => 10]);
        $product = Product::create([
            'reference' => 'REAPPRO-1', 'name' => 'Produit vitesse connue', 'purchase_price' => 100, 'sale_price' => 200,
            'unit' => 'unité', 'low_stock_threshold' => 5, 'supplier_id' => $supplier->id,
        ]);
        StockMovement::create(['product_id' => $product->id, 'type' => 'entree', 'quantity' => 100]);
        // 30 sorties sur les 30 derniers jours -> vitesse ≈ 1/jour -> ~70 jours de stock restant.
        StockMovement::create(['product_id' => $product->id, 'type' => 'sortie', 'quantity' => -30, 'created_at' => now()->subDays(5)]);

        $stockoutDate = $product->projectedStockoutDate();
        $orderByDate = $product->recommendedOrderByDate();

        $this->assertNotNull($stockoutDate);
        $this->assertNotNull($orderByDate);
        // La date limite de commande doit précéder la rupture d'exactement le délai fournisseur
        // (abs + delta : diffInDays() est signé et de précision flottante depuis Carbon 2, deux
        // appels distincts à now() à quelques microsecondes d'écart suffisent à le faire dévier
        // de zéro sans jamais changer le jour calendaire réel).
        $this->assertEqualsWithDelta(10, abs($stockoutDate->diffInDays($orderByDate)), 0.01);
    }

    public function test_product_without_a_supplier_has_no_recommended_order_date(): void
    {
        $product = Product::create([
            'reference' => 'REAPPRO-2', 'name' => 'Produit sans fournisseur', 'purchase_price' => 100, 'sale_price' => 200,
            'unit' => 'unité', 'low_stock_threshold' => 5,
        ]);
        StockMovement::create(['product_id' => $product->id, 'type' => 'entree', 'quantity' => 10]);
        StockMovement::create(['product_id' => $product->id, 'type' => 'sortie', 'quantity' => -5, 'created_at' => now()->subDays(2)]);

        $this->assertNull($product->recommendedOrderByDate());
    }

    // --- Product::isDormant() / capitalTiedUp() ---

    public function test_a_product_with_no_recent_sale_is_flagged_dormant(): void
    {
        $product = Product::create([
            'reference' => 'DORMANT-1', 'name' => 'Produit dormant', 'purchase_price' => 1000, 'sale_price' => 2000,
            'unit' => 'unité', 'low_stock_threshold' => 1,
        ]);
        StockMovement::create(['product_id' => $product->id, 'type' => 'entree', 'quantity' => 20]);

        $this->assertTrue($product->isDormant());
        $this->assertEquals(20000, $product->capitalTiedUp());
    }

    public function test_a_recently_sold_product_is_not_dormant(): void
    {
        $product = Product::create([
            'reference' => 'DORMANT-2', 'name' => 'Produit actif', 'purchase_price' => 1000, 'sale_price' => 2000,
            'unit' => 'unité', 'low_stock_threshold' => 1,
        ]);
        StockMovement::create(['product_id' => $product->id, 'type' => 'entree', 'quantity' => 20]);
        StockMovement::create(['product_id' => $product->id, 'type' => 'sortie', 'quantity' => -1, 'created_at' => now()->subDays(2)]);

        $this->assertFalse($product->isDormant());
    }

    // --- Écrans (contrôleurs) ---

    public function test_smart_reorder_screen_lists_products_needing_reorder_and_shows_ai_summary(): void
    {
        $admin = $this->admin();
        $supplier = Supplier::create(['name' => 'Fournisseur A', 'lead_time_days' => 7]);
        $product = Product::create([
            'reference' => 'REAPPRO-3', 'name' => 'Produit à réapprovisionner', 'purchase_price' => 100, 'sale_price' => 200,
            'unit' => 'unité', 'low_stock_threshold' => 10, 'supplier_id' => $supplier->id,
        ]);
        StockMovement::create(['product_id' => $product->id, 'type' => 'entree', 'quantity' => 3]);

        $this->mock(GeminiService::class, function ($mock) {
            $mock->shouldReceive('generateText')->once()->andReturn('Priorisez le produit X, rupture imminente.');
        });

        $response = $this->actingAs($admin)->get(route('reorder.index'));

        $response->assertOk();
        $response->assertSee('Produit à réapprovisionner');
        $response->assertSee('Priorisez le produit X, rupture imminente.');
    }

    public function test_smart_reorder_screen_degrades_gracefully_when_gemini_fails(): void
    {
        $admin = $this->admin();
        $supplier = Supplier::create(['name' => 'Fournisseur B', 'lead_time_days' => 7]);
        $product = Product::create([
            'reference' => 'REAPPRO-4', 'name' => 'Produit sans résumé IA', 'purchase_price' => 100, 'sale_price' => 200,
            'unit' => 'unité', 'low_stock_threshold' => 10, 'supplier_id' => $supplier->id,
        ]);
        StockMovement::create(['product_id' => $product->id, 'type' => 'entree', 'quantity' => 3]);

        $this->mock(GeminiService::class, function ($mock) {
            $mock->shouldReceive('generateText')->once()->andReturn('');
        });

        $response = $this->actingAs($admin)->get(route('reorder.index'));

        $response->assertOk();
        $response->assertSee('Produit sans résumé IA');
        $response->assertSee('Résumé IA momentanément indisponible');
    }

    public function test_dormant_stock_screen_shows_suggested_action_without_requiring_ai(): void
    {
        $admin = $this->admin();
        $product = Product::create([
            'reference' => 'DORMANT-3', 'name' => 'Produit stock mort', 'purchase_price' => 5000, 'sale_price' => 8000,
            'unit' => 'unité', 'low_stock_threshold' => 1,
        ]);
        StockMovement::create(['product_id' => $product->id, 'type' => 'entree', 'quantity' => 5]);

        $this->mock(GeminiService::class, function ($mock) {
            $mock->shouldReceive('generateText')->once()->andReturn('');
        });

        $response = $this->actingAs($admin)->get(route('dormant-stock.index'));

        $response->assertOk();
        $response->assertSee('Produit stock mort');
        $response->assertSee('promotion', false);
    }
}
