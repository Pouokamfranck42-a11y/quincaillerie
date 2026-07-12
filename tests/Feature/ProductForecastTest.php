<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductForecastTest extends TestCase
{
    use RefreshDatabase;

    public function test_forecasted_monthly_demand_weighs_recent_months_more_heavily(): void
    {
        $product = Product::create([
            'reference' => 'FORECAST-1', 'name' => 'Produit prévision', 'purchase_price' => 100, 'sale_price' => 150,
            'unit' => 'unité', 'low_stock_threshold' => 5,
        ]);

        // Mois ancien : faible demande. Mois récent : forte demande.
        StockMovement::create(['product_id' => $product->id, 'type' => StockMovement::TYPE_SORTIE, 'quantity' => -10])
            ->forceFill(['created_at' => now()->subMonths(4)])->save();
        StockMovement::create(['product_id' => $product->id, 'type' => StockMovement::TYPE_SORTIE, 'quantity' => -100])
            ->forceFill(['created_at' => now()])->save();

        $forecast = $product->forecastedMonthlyDemand();

        // Moyenne pondérée : proche de la valeur récente (100) plutôt que la simple moyenne (55).
        $this->assertGreaterThan(55, $forecast);
        $this->assertLessThanOrEqual(100, $forecast);
    }

    public function test_forecasted_monthly_demand_is_zero_without_history(): void
    {
        $product = Product::create([
            'reference' => 'FORECAST-2', 'name' => 'Produit sans historique', 'purchase_price' => 100, 'sale_price' => 150,
            'unit' => 'unité', 'low_stock_threshold' => 5,
        ]);

        $this->assertSame(0.0, $product->forecastedMonthlyDemand());
    }
}
