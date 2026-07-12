<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PurchaseOrderSuggestionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_suggestions_groups_low_stock_products_by_supplier_as_draft_orders(): void
    {
        Role::findOrCreate('admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('admin');

        $supplier = Supplier::create(['name' => 'Fournisseur A', 'lead_time_days' => 5]);

        $lowStockProduct = Product::create([
            'reference' => 'LOW-1', 'name' => 'Produit en rupture', 'purchase_price' => 100, 'sale_price' => 150,
            'unit' => 'unité', 'low_stock_threshold' => 10, 'supplier_id' => $supplier->id, 'active' => true,
        ]);
        StockMovement::create(['product_id' => $lowStockProduct->id, 'type' => 'entree', 'quantity' => 3]);

        $wellStockedProduct = Product::create([
            'reference' => 'OK-1', 'name' => 'Produit bien stocké', 'purchase_price' => 100, 'sale_price' => 150,
            'unit' => 'unité', 'low_stock_threshold' => 10, 'supplier_id' => $supplier->id, 'active' => true,
        ]);
        StockMovement::create(['product_id' => $wellStockedProduct->id, 'type' => 'entree', 'quantity' => 100]);

        $response = $this->actingAs($user)->post(route('purchase-orders.create-suggestions'), [
            'product_ids' => [$lowStockProduct->id],
        ]);

        $response->assertRedirect(route('purchase-orders.index'));

        $this->assertDatabaseHas('purchase_orders', [
            'supplier_id' => $supplier->id,
            'status' => 'draft',
        ]);
        $this->assertDatabaseHas('purchase_order_lines', [
            'product_id' => $lowStockProduct->id,
        ]);
        $this->assertDatabaseMissing('purchase_order_lines', [
            'product_id' => $wellStockedProduct->id,
        ]);
    }
}
