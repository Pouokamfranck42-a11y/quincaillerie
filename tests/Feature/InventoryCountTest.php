<?php

namespace Tests\Feature;

use App\Models\InventoryCount;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryCountTest extends TestCase
{
    use RefreshDatabase;

    public function test_completing_an_inventory_count_generates_adjustment_for_discrepancies_only(): void
    {
        $user = User::factory()->create();
        $warehouse = Warehouse::where('is_default', true)->first();

        $productWithGap = Product::create([
            'reference' => 'INV-1', 'name' => 'Produit avec écart', 'purchase_price' => 100, 'sale_price' => 150,
            'unit' => 'unité', 'low_stock_threshold' => 5,
        ]);
        StockMovement::create(['product_id' => $productWithGap->id, 'type' => 'entree', 'quantity' => 20]);

        $productWithoutGap = Product::create([
            'reference' => 'INV-2', 'name' => 'Produit sans écart', 'purchase_price' => 100, 'sale_price' => 150,
            'unit' => 'unité', 'low_stock_threshold' => 5,
        ]);
        StockMovement::create(['product_id' => $productWithoutGap->id, 'type' => 'entree', 'quantity' => 15]);

        $count = InventoryCount::create([
            'warehouse_id' => $warehouse->id, 'user_id' => $user->id,
            'type' => InventoryCount::TYPE_COMPLET, 'status' => InventoryCount::STATUS_IN_PROGRESS,
        ]);
        $lineWithGap = $count->lines()->create(['product_id' => $productWithGap->id, 'expected_quantity' => 20, 'counted_quantity' => 17]);
        $lineWithoutGap = $count->lines()->create(['product_id' => $productWithoutGap->id, 'expected_quantity' => 15, 'counted_quantity' => 15]);

        $count->load('lines');
        $count->complete($user->id);

        $this->assertEquals(InventoryCount::STATUS_COMPLETED, $count->fresh()->status);
        // écart de -3 régularisé
        $this->assertEquals(17, $productWithGap->fresh()->currentStock());
        // pas d'écart : stock inchangé, aucun mouvement d'ajustement créé
        $this->assertEquals(15, $productWithoutGap->fresh()->currentStock());
        $this->assertEquals(1, StockMovement::where('subtype', 'inventaire')->count());
    }
}
