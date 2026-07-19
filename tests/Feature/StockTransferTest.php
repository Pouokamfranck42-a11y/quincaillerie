<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockTransferTest extends TestCase
{
    use RefreshDatabase;

    public function test_executing_a_transfer_moves_stock_between_warehouses(): void
    {
        $user = User::factory()->create();
        $mainWarehouse = Warehouse::where('is_default', true)->first();
        $secondWarehouse = Warehouse::create(['name' => 'Dépôt annexe']);

        $product = Product::create([
            'reference' => 'TRANS-1', 'name' => 'Produit transféré', 'purchase_price' => 100, 'sale_price' => 150,
            'unit' => 'unité', 'low_stock_threshold' => 5,
        ]);
        StockMovement::create(['product_id' => $product->id, 'warehouse_id' => $mainWarehouse->id, 'type' => 'entree', 'quantity' => 20]);

        $transfer = StockTransfer::create([
            'from_warehouse_id' => $mainWarehouse->id,
            'to_warehouse_id' => $secondWarehouse->id,
            'user_id' => $user->id,
            'status' => StockTransfer::STATUS_DRAFT,
        ]);
        $transfer->lines()->create(['product_id' => $product->id, 'quantity' => 8]);

        $transfer->execute($user->id);

        $this->assertEquals(StockTransfer::STATUS_COMPLETED, $transfer->fresh()->status);
        // le stock total ne change pas, seule sa répartition par entrepôt change
        $this->assertEquals(20, $product->fresh()->currentStock());

        $fromStock = (float) StockMovement::where('product_id', $product->id)->where('warehouse_id', $mainWarehouse->id)->sum('quantity');
        $toStock = (float) StockMovement::where('product_id', $product->id)->where('warehouse_id', $secondWarehouse->id)->sum('quantity');
        $this->assertEquals(12, $fromStock);
        $this->assertEquals(8, $toStock);
    }

    /**
     * execute() créait auparavant les mouvements de stock directement, en contournant le
     * noyau unifié (StockService) — aucune vérification de disponibilité, un transfert
     * pouvait donc faire passer le stock global en négatif. execute() passe désormais par
     * StockService::withdraw(), qui vérifie la disponibilité avant d'agir.
     */
    public function test_executing_a_transfer_exceeding_available_stock_is_rejected(): void
    {
        $user = User::factory()->create();
        $mainWarehouse = Warehouse::where('is_default', true)->first();
        $secondWarehouse = Warehouse::create(['name' => 'Dépôt annexe 2']);

        $product = Product::create([
            'reference' => 'TRANS-2', 'name' => 'Produit transfert insuffisant', 'purchase_price' => 100, 'sale_price' => 150,
            'unit' => 'unité', 'low_stock_threshold' => 5,
        ]);
        StockMovement::create(['product_id' => $product->id, 'warehouse_id' => $mainWarehouse->id, 'type' => 'entree', 'quantity' => 5]);

        $transfer = StockTransfer::create([
            'from_warehouse_id' => $mainWarehouse->id,
            'to_warehouse_id' => $secondWarehouse->id,
            'user_id' => $user->id,
            'status' => StockTransfer::STATUS_DRAFT,
        ]);
        $transfer->lines()->create(['product_id' => $product->id, 'quantity' => 8]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $transfer->execute($user->id);

        $this->assertEquals(5, $product->fresh()->currentStock());
        $this->assertEquals(StockTransfer::STATUS_DRAFT, $transfer->fresh()->status);
    }
}
