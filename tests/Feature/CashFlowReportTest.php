<?php

namespace Tests\Feature;

use App\Models\CashRegisterSession;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CashFlowReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_cash_flow_report_is_accessible_to_admins_and_shows_projections(): void
    {
        Role::findOrCreate('admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $product = Product::create([
            'reference' => 'TRESO-1', 'name' => 'Produit trésorerie', 'purchase_price' => 1000, 'sale_price' => 1500,
            'unit' => 'unité', 'low_stock_threshold' => 5,
        ]);
        StockMovement::create(['product_id' => $product->id, 'type' => StockMovement::TYPE_ENTREE, 'quantity' => 10]);
        $session = CashRegisterSession::create(['user_id' => $admin->id, 'opening_amount' => 0, 'opened_at' => now()]);

        Sale::checkout([
            ['product' => $product, 'quantity' => 2],
        ], $session, $admin->id, null, 'especes', 0);

        $response = $this->actingAs($admin)->get(route('reports.cash-flow'));

        $response->assertOk();
        $response->assertViewHas('projections', fn ($projections) => $projections->pluck('days')->all() === [30, 60, 90]);
    }

    public function test_cash_flow_report_is_forbidden_to_non_admins(): void
    {
        Role::findOrCreate('caissier', 'web');
        $user = User::factory()->create();
        $user->assignRole('caissier');

        $this->actingAs($user)->get(route('reports.cash-flow'))->assertForbidden();
    }
}
