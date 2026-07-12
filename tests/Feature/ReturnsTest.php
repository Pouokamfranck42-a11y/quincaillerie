<?php

namespace Tests\Feature;

use App\Models\CashRegisterSession;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReturnsTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_return_reintegrates_stock(): void
    {
        $user = User::factory()->create();
        $product = Product::create([
            'reference' => 'RET-1', 'name' => 'Produit', 'purchase_price' => 100, 'sale_price' => 200,
            'unit' => 'unité', 'low_stock_threshold' => 5,
        ]);
        StockMovement::create(['product_id' => $product->id, 'type' => 'entree', 'quantity' => 10]);

        $session = CashRegisterSession::create(['user_id' => $user->id, 'opened_at' => now(), 'opening_amount' => 0, 'status' => 'open']);
        $sale = Sale::checkout([['product' => $product, 'quantity' => 4]], $session, $user->id, null, 'especes', 0);

        $this->assertEquals(6, $product->fresh()->currentStock());

        $line = $sale->lines->first();
        $line->returnQuantity(2, $user->id, 'Article défectueux');

        $this->assertEquals(8, $product->fresh()->currentStock());
        $this->assertEquals(2, $line->fresh()->returned_quantity);
        $this->assertEquals(2, $line->fresh()->returnableQuantity());
    }

    public function test_supplier_return_removes_stock(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::create(['name' => 'Fournisseur', 'lead_time_days' => 5]);
        $product = Product::create([
            'reference' => 'RET-2', 'name' => 'Produit défectueux', 'purchase_price' => 100, 'sale_price' => 150,
            'unit' => 'unité', 'low_stock_threshold' => 5, 'supplier_id' => $supplier->id,
        ]);

        $po = PurchaseOrder::create(['supplier_id' => $supplier->id, 'user_id' => $user->id, 'status' => PurchaseOrder::STATUS_ORDERED, 'ordered_at' => now()]);
        $po->lines()->create(['product_id' => $product->id, 'quantity' => 10, 'unit_price' => 100]);
        $po->load('lines.product');
        $po->receive($user->id);

        $this->assertEquals(10, $product->fresh()->currentStock());

        $line = $po->lines->first();
        $line->returnQuantity(3, $user->id, 'Marchandise abîmée');

        $this->assertEquals(7, $product->fresh()->currentStock());
        $this->assertEquals(3, $line->fresh()->returned_quantity);
    }
}
