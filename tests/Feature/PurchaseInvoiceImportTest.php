<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Ai\ClaudeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PurchaseInvoiceImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'magasinier'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    public function test_analyzing_an_invoice_matches_supplier_and_products_without_persisting(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $supplier = Supplier::create(['name' => 'Quincaillerie du Port']);
        $product = Product::create([
            'reference' => 'CIM-50', 'name' => 'Sac de ciment 50kg', 'purchase_price' => 5000, 'sale_price' => 6000,
            'unit' => 'sac', 'low_stock_threshold' => 5,
        ]);

        $this->mock(ClaudeService::class, function ($mock) {
            $mock->shouldReceive('extractStructured')->once()->andReturn([
                'supplier_name' => 'Quincaillerie du Port',
                'lines' => [
                    ['description' => 'Sac de ciment 50kg', 'quantity' => 20, 'unit_price' => 4800],
                ],
            ]);
        });

        $response = $this->actingAs($admin)->post(route('purchase-orders.import-invoice.analyze'), [
            'invoice' => UploadedFile::fake()->image('facture.jpg'),
        ]);

        $response->assertOk();
        $response->assertViewHas('supplierGuessId', $supplier->id);
        $response->assertViewHas('initialLines', fn ($lines) => $lines[0]['product_id'] === $product->id);
        $this->assertDatabaseCount('purchase_orders', 0);
    }

    public function test_storing_the_reviewed_invoice_creates_a_draft_purchase_order(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $supplier = Supplier::create(['name' => 'Quincaillerie du Port']);
        $product = Product::create([
            'reference' => 'CIM-50', 'name' => 'Sac de ciment 50kg', 'purchase_price' => 5000, 'sale_price' => 6000,
            'unit' => 'sac', 'low_stock_threshold' => 5,
        ]);

        $response = $this->actingAs($admin)->post(route('purchase-orders.import-invoice.store'), [
            'supplier_id' => $supplier->id,
            'lines' => [
                ['product_id' => $product->id, 'quantity' => 20, 'unit_price' => 4800],
            ],
        ]);

        $purchaseOrder = PurchaseOrder::firstOrFail();
        $response->assertRedirect(route('purchase-orders.edit', $purchaseOrder));
        $this->assertSame(PurchaseOrder::STATUS_DRAFT, $purchaseOrder->status);
        $this->assertCount(1, $purchaseOrder->lines);
    }

    public function test_editing_lines_of_a_draft_order_updates_them(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $supplier = Supplier::create(['name' => 'Fournisseur test']);
        $product = Product::create([
            'reference' => 'VIS-1', 'name' => 'Vis à bois', 'purchase_price' => 100, 'sale_price' => 150,
            'unit' => 'unité', 'low_stock_threshold' => 5,
        ]);
        $purchaseOrder = PurchaseOrder::create(['supplier_id' => $supplier->id, 'user_id' => $admin->id, 'status' => PurchaseOrder::STATUS_DRAFT]);
        $line = $purchaseOrder->lines()->create(['product_id' => $product->id, 'quantity' => 10, 'unit_price' => 100]);

        $this->actingAs($admin)->put(route('purchase-orders.update', $purchaseOrder), [
            'lines' => [
                ['id' => $line->id, 'product_id' => $product->id, 'quantity' => 25, 'unit_price' => 90],
            ],
        ])->assertRedirect(route('purchase-orders.show', $purchaseOrder));

        $this->assertEquals(25, $line->fresh()->quantity);
        $this->assertEquals(90, $line->fresh()->unit_price);
    }

    public function test_editing_lines_is_forbidden_once_the_order_is_no_longer_a_draft(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $supplier = Supplier::create(['name' => 'Fournisseur test']);
        $purchaseOrder = PurchaseOrder::create(['supplier_id' => $supplier->id, 'user_id' => $admin->id, 'status' => PurchaseOrder::STATUS_ORDERED]);

        $this->actingAs($admin)->get(route('purchase-orders.edit', $purchaseOrder))->assertForbidden();
    }
}
