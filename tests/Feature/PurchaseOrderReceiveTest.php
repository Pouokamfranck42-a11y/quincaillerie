<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderReceiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_receiving_a_purchase_order_creates_stock_entries_and_updates_purchase_price(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::create(['name' => 'Fournisseur test', 'lead_time_days' => 7]);
        $product = Product::create([
            'reference' => 'TEST-PO-1',
            'name' => 'Produit commandé',
            'purchase_price' => 100,
            'sale_price' => 150,
            'unit' => 'unité',
            'low_stock_threshold' => 5,
            'supplier_id' => $supplier->id,
        ]);

        $po = PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'user_id' => $user->id,
            'status' => PurchaseOrder::STATUS_ORDERED,
            'ordered_at' => now(),
        ]);
        $po->lines()->create(['product_id' => $product->id, 'quantity' => 20, 'unit_price' => 120]);
        $po->load('lines.product');

        $po->receive($user->id);

        $this->assertEquals(PurchaseOrder::STATUS_RECEIVED, $po->fresh()->status);
        $this->assertEquals(20, $product->fresh()->currentStock());
        $this->assertEquals(120, $product->fresh()->purchase_price);
    }

    public function test_receiving_an_already_received_order_is_a_no_op(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::create(['name' => 'Fournisseur test', 'lead_time_days' => 7]);
        $product = Product::create([
            'reference' => 'TEST-PO-2',
            'name' => 'Produit',
            'purchase_price' => 100,
            'sale_price' => 150,
            'unit' => 'unité',
            'low_stock_threshold' => 5,
        ]);

        $po = PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'user_id' => $user->id,
            'status' => PurchaseOrder::STATUS_ORDERED,
            'ordered_at' => now(),
        ]);
        $po->lines()->create(['product_id' => $product->id, 'quantity' => 5, 'unit_price' => 100]);
        $po->load('lines.product');
        $po->receive($user->id);

        // seconde réception : ne doit pas dupliquer les mouvements de stock
        $po->fresh()->load('lines.product')->receive($user->id);

        $this->assertEquals(5, $product->fresh()->currentStock());
    }
}
