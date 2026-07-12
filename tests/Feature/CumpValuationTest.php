<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CumpValuationTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_price_is_a_weighted_average_across_receptions_at_different_costs(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::create(['name' => 'Fournisseur', 'lead_time_days' => 5]);
        $product = Product::create([
            'reference' => 'CUMP-1', 'name' => 'Produit CUMP', 'purchase_price' => 0, 'sale_price' => 200,
            'unit' => 'unité', 'low_stock_threshold' => 5, 'supplier_id' => $supplier->id,
        ]);

        // 10 unités à 100 FCFA
        $po1 = PurchaseOrder::create(['supplier_id' => $supplier->id, 'user_id' => $user->id, 'status' => PurchaseOrder::STATUS_ORDERED, 'ordered_at' => now()]);
        $po1->lines()->create(['product_id' => $product->id, 'quantity' => 10, 'unit_price' => 100]);
        $po1->load('lines.product');
        $po1->receive($user->id);

        $this->assertEquals(100, (float) $product->fresh()->purchase_price);

        // 10 unités supplémentaires à 200 FCFA -> moyenne pondérée = (10*100 + 10*200) / 20 = 150
        $po2 = PurchaseOrder::create(['supplier_id' => $supplier->id, 'user_id' => $user->id, 'status' => PurchaseOrder::STATUS_ORDERED, 'ordered_at' => now()]);
        $po2->lines()->create(['product_id' => $product->id, 'quantity' => 10, 'unit_price' => 200]);
        $po2->load('lines.product');
        $po2->receive($user->id);

        $this->assertEquals(150, (float) $product->fresh()->purchase_price);
        $this->assertEquals(20, $product->fresh()->currentStock());
    }

    public function test_extra_costs_are_allocated_pro_rata_into_landed_cost(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::create(['name' => 'Fournisseur', 'lead_time_days' => 5]);

        $cheapProduct = Product::create([
            'reference' => 'CUMP-2', 'name' => 'Produit A', 'purchase_price' => 0, 'sale_price' => 200,
            'unit' => 'unité', 'low_stock_threshold' => 5,
        ]);
        $expensiveProduct = Product::create([
            'reference' => 'CUMP-3', 'name' => 'Produit B', 'purchase_price' => 0, 'sale_price' => 200,
            'unit' => 'unité', 'low_stock_threshold' => 5,
        ]);

        // A : 10 x 100 = 1000 (25% de la commande) ; B : 10 x 300 = 3000 (75%) ; frais annexes 400
        $po = PurchaseOrder::create([
            'supplier_id' => $supplier->id, 'user_id' => $user->id, 'status' => PurchaseOrder::STATUS_ORDERED,
            'ordered_at' => now(), 'extra_costs' => 400,
        ]);
        $po->lines()->create(['product_id' => $cheapProduct->id, 'quantity' => 10, 'unit_price' => 100]);
        $po->lines()->create(['product_id' => $expensiveProduct->id, 'quantity' => 10, 'unit_price' => 300]);
        $po->load('lines.product');
        $po->receive($user->id);

        // A reçoit 25% des frais (100), soit 10 FCFA/unité de plus -> 110
        $this->assertEquals(110, (float) $cheapProduct->fresh()->purchase_price);
        // B reçoit 75% des frais (300), soit 30 FCFA/unité de plus -> 330
        $this->assertEquals(330, (float) $expensiveProduct->fresh()->purchase_price);
    }
}
