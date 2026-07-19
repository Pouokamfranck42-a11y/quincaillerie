<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Phase 4 — tableau de bord : indicateurs de pilotage réels (valeur stock, rotation, marge, dormants, surstock, ruptures). */
class DashboardIndicatorsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_stock_value_and_dormant_and_stockout_counts(): void
    {
        Role::findOrCreate('admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        // Dormant : du stock, jamais vendu.
        $dormant = Product::create([
            'reference' => 'DASH-1', 'name' => 'Produit dormant tableau de bord', 'purchase_price' => 1000, 'sale_price' => 2000,
            'unit' => 'unité', 'low_stock_threshold' => 1,
        ]);
        StockMovement::create(['product_id' => $dormant->id, 'type' => 'entree', 'quantity' => 10]);

        // Rupture : stock à zéro.
        $stockout = Product::create([
            'reference' => 'DASH-2', 'name' => 'Produit en rupture', 'purchase_price' => 500, 'sale_price' => 1000,
            'unit' => 'unité', 'low_stock_threshold' => 5,
        ]);
        StockMovement::create(['product_id' => $stockout->id, 'type' => 'entree', 'quantity' => 0]);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
        // 10 unités à 1000 FCFA de coût = 10 000 FCFA immobilisés pour le seul produit dormant.
        $response->assertSee('10 000');
        $response->assertSeeInOrder(['Ruptures']);
        $response->assertSeeInOrder(['Articles dormants']);
    }

    public function test_dashboard_dormant_link_is_hidden_without_reports_permission(): void
    {
        Role::findOrCreate('caissier', 'web');
        $caissier = User::factory()->create();
        $caissier->assignRole('caissier'); // pas de rapports.voir (voir RoleSeeder)

        $response = $this->actingAs($caissier)->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee(route('dormant-stock.index'));
    }
}
