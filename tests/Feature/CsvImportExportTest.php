<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\InventoryCount;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Phase 4 — export/import CSV pour articles, stock et clients. */
class CsvImportExportTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate('admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function csvFile(array $rows, string $name = 'import.csv'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'csv');
        $handle = fopen($path, 'w');
        foreach ($rows as $row) {
            fputcsv($handle, $row, ';');
        }
        fclose($handle);

        return new UploadedFile($path, $name, 'text/csv', null, true);
    }

    // --- Exports ---

    public function test_product_catalog_export_contains_the_product(): void
    {
        $admin = $this->admin();
        Product::create([
            'reference' => 'EXP-1', 'name' => 'Produit exporté', 'purchase_price' => 100, 'sale_price' => 200,
            'unit' => 'unité',
        ]);

        $response = $this->actingAs($admin)->get(route('products.export'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Produit exporté', $response->getContent());
    }

    public function test_customer_export_contains_the_customer(): void
    {
        $admin = $this->admin();
        Customer::create(['name' => 'Client exporté', 'email' => 'export@example.com']);

        $response = $this->actingAs($admin)->get(route('customers.export'));

        $response->assertOk();
        $this->assertStringContainsString('Client exporté', $response->getContent());
    }

    public function test_stock_export_contains_the_current_stock_level(): void
    {
        $admin = $this->admin();
        $product = Product::create([
            'reference' => 'STOCK-EXP-1', 'name' => 'Produit stock export', 'purchase_price' => 100, 'sale_price' => 200,
            'unit' => 'unité', 'low_stock_threshold' => 1,
        ]);
        StockMovement::create(['product_id' => $product->id, 'type' => 'entree', 'quantity' => 42]);

        $response = $this->actingAs($admin)->get(route('reports.stock.export'));

        $response->assertOk();
        $content = $response->getContent();
        $this->assertStringContainsString('Produit stock export', $content);
        $this->assertStringContainsString('42', $content);
    }

    // --- Import clients ---

    public function test_customer_import_creates_new_customers(): void
    {
        $admin = $this->admin();
        $file = $this->csvFile([
            ['name', 'type', 'phone', 'email', 'address', 'niu', 'credit_limit', 'payment_terms_days'],
            ['Client CSV', 'professionnel', '+237600000001', 'clientcsv@example.com', 'Douala', '', '50000', '30'],
        ]);

        $response = $this->actingAs($admin)->post(route('customers.import.store'), ['clients' => $file]);

        $response->assertRedirect(route('customers.index'));
        $this->assertDatabaseHas('customers', ['name' => 'Client CSV', 'email' => 'clientcsv@example.com', 'type' => 'professionnel']);
    }

    public function test_customer_import_updates_an_existing_customer_matched_by_email(): void
    {
        $admin = $this->admin();
        $existing = Customer::create(['name' => 'Ancien nom', 'email' => 'meme@example.com', 'credit_limit' => 0]);

        $file = $this->csvFile([
            ['name', 'type', 'phone', 'email', 'address', 'niu', 'credit_limit', 'payment_terms_days'],
            ['Nouveau nom', 'particulier', '', 'meme@example.com', '', '', '10000', '15'],
        ]);

        $this->actingAs($admin)->post(route('customers.import.store'), ['clients' => $file]);

        $this->assertSame(1, Customer::count());
        $this->assertEquals('Nouveau nom', $existing->fresh()->name);
        $this->assertEquals(10000, $existing->fresh()->credit_limit);
    }

    // --- Import recomptage stock ---

    public function test_bulk_count_import_updates_counted_quantities_matched_by_reference(): void
    {
        $admin = $this->admin();
        $warehouse = Warehouse::create(['name' => 'Entrepôt import', 'is_default' => true]);
        $product = Product::create([
            'reference' => 'COMPTE-1', 'name' => 'Produit à recompter', 'purchase_price' => 100, 'sale_price' => 200,
            'unit' => 'unité', 'low_stock_threshold' => 1,
        ]);
        StockMovement::create(['product_id' => $product->id, 'type' => 'entree', 'quantity' => 10]);

        $count = InventoryCount::create([
            'warehouse_id' => $warehouse->id, 'user_id' => $admin->id, 'type' => InventoryCount::TYPE_COMPLET,
            'status' => InventoryCount::STATUS_IN_PROGRESS,
        ]);
        $count->lines()->create(['product_id' => $product->id, 'expected_quantity' => 10]);

        $file = $this->csvFile([
            ['reference', 'name', 'counted_quantity'],
            ['COMPTE-1', 'Produit à recompter', '7'],
        ]);

        $response = $this->actingAs($admin)->post(route('inventory-counts.import-counts', $count), ['counts_file' => $file]);

        $response->assertRedirect(route('inventory-counts.show', $count));
        $this->assertEquals(7, $count->lines->first()->fresh()->counted_quantity);
    }

    public function test_bulk_count_import_reports_references_not_found_in_this_count(): void
    {
        $admin = $this->admin();
        $warehouse = Warehouse::create(['name' => 'Entrepôt import 2', 'is_default' => true]);
        $product = Product::create([
            'reference' => 'COMPTE-2', 'name' => 'Produit compté', 'purchase_price' => 100, 'sale_price' => 200,
            'unit' => 'unité', 'low_stock_threshold' => 1,
        ]);

        $count = InventoryCount::create([
            'warehouse_id' => $warehouse->id, 'user_id' => $admin->id, 'type' => InventoryCount::TYPE_COMPLET,
            'status' => InventoryCount::STATUS_IN_PROGRESS,
        ]);
        $count->lines()->create(['product_id' => $product->id, 'expected_quantity' => 0]);

        $file = $this->csvFile([
            ['reference', 'name', 'counted_quantity'],
            ['REFERENCE-INCONNUE', 'Produit fantôme', '5'],
        ]);

        $response = $this->actingAs($admin)->post(route('inventory-counts.import-counts', $count), ['counts_file' => $file]);

        $response->assertRedirect();
        $response->assertSessionHas('success', fn ($msg) => str_contains($msg, '1 référence(s) non trouvée'));
    }
}
