<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseUnitConversionTest extends TestCase
{
    use RefreshDatabase;

    public function test_receiving_converts_purchase_unit_quantity_to_stock_unit(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::create(['name' => 'Fournisseur câbles', 'lead_time_days' => 5]);
        $product = Product::create([
            'reference' => 'CABLE-TEST',
            'name' => 'Câble électrique',
            'purchase_price' => 10, // prix par mètre
            'sale_price' => 15,
            'unit' => 'mètre',
            'purchase_unit' => 'rouleau',
            'purchase_unit_factor' => 50, // 1 rouleau = 50 mètres
            'low_stock_threshold' => 20,
            'supplier_id' => $supplier->id,
        ]);

        $po = PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'user_id' => $user->id,
            'status' => PurchaseOrder::STATUS_ORDERED,
            'ordered_at' => now(),
        ]);
        // 3 rouleaux à 480 FCFA le rouleau
        $po->lines()->create(['product_id' => $product->id, 'quantity' => 3, 'unit_price' => 480]);
        $po->load('lines.product');

        $po->receive($user->id);

        // 3 rouleaux * 50 mètres = 150 mètres de stock
        $this->assertEquals(150, $product->fresh()->currentStock());
        // 480 FCFA / 50 mètres = 9.6 FCFA le mètre
        $this->assertEquals(9.6, (float) $product->fresh()->purchase_price);
    }
}
