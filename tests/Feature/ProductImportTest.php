<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\InventoryCount;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'magasinier', 'caissier'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        Warehouse::create(['name' => 'Magasin principal', 'is_default' => true]);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    public function test_analyzing_a_valid_csv_classifies_rows_without_persisting_anything(): void
    {
        $admin = $this->admin();

        Product::create([
            'reference' => 'VIS-1', 'name' => 'Vis à bois', 'purchase_price' => 100, 'sale_price' => 150,
            'unit' => 'unité', 'low_stock_threshold' => 5,
        ]);

        $csv = "reference;nom;prix_achat;prix_vente;categorie;stock_initial\n"
            ."VIS-1;Vis à bois (mise à jour);120;180;Visserie;\n"
            ."CLOU-1;Clou 50mm;50;80;Visserie;200\n";

        $response = $this->actingAs($admin)->post(route('products.import.analyze'), [
            'catalogue' => UploadedFile::fake()->createWithContent('catalogue.csv', $csv),
        ]);

        $response->assertOk();
        $response->assertViewHas('rows', function ($rows) {
            return count($rows) === 2
                && $rows[0]['status'] === 'update'
                && $rows[1]['status'] === 'new'
                && $rows[1]['stock_initial'] === 200.0;
        });
        $this->assertDatabaseCount('products', 1);
    }

    public function test_csv_exported_from_excel_in_windows_1252_is_converted_to_utf8(): void
    {
        $admin = $this->admin();

        // "Vis à tête fraisée" encodé en Windows-1252 (ANSI), comme le fait Excel FR par défaut.
        $name = mb_convert_encoding('Vis à tête fraisée', 'Windows-1252', 'UTF-8');
        $csv = "reference;nom;prix_achat;prix_vente\nVIS-ANSI;{$name};100;150\n";

        $response = $this->actingAs($admin)->post(route('products.import.analyze'), [
            'catalogue' => UploadedFile::fake()->createWithContent('catalogue.csv', $csv),
        ]);

        $response->assertViewHas('rows', function ($rows) {
            return $rows[0]['status'] === 'new' && $rows[0]['data']['name'] === 'Vis à tête fraisée';
        });
    }

    public function test_variant_attributes_use_pipe_separator_to_avoid_csv_delimiter_conflict(): void
    {
        $admin = $this->admin();

        $csv = "reference;nom;prix_achat;prix_vente;variante\n"
            ."VIS-M6X20;Vis M6x20;25;45;\"taille=M6x20|materiau=inox\"\n";

        $response = $this->actingAs($admin)->post(route('products.import.analyze'), [
            'catalogue' => UploadedFile::fake()->createWithContent('catalogue.csv', $csv),
        ]);

        $response->assertViewHas('rows', function ($rows) {
            return $rows[0]['status'] === 'new'
                && $rows[0]['data']['variant_attributes'] === ['taille' => 'M6x20', 'materiau' => 'inox'];
        });
    }

    public function test_import_rejects_row_with_barcode_already_used_by_another_product(): void
    {
        $admin = $this->admin();

        Product::create([
            'reference' => 'CIM-50', 'name' => 'Sac de ciment', 'purchase_price' => 5000, 'sale_price' => 6000,
            'barcode' => '3401234567890', 'unit' => 'sac', 'low_stock_threshold' => 5,
        ]);

        $csv = "reference;nom;prix_achat;prix_vente;code_barre\n"
            ."CIM-100;Sac de ciment 100kg;9000;11000;3401234567890\n";

        $response = $this->actingAs($admin)->post(route('products.import.analyze'), [
            'catalogue' => UploadedFile::fake()->createWithContent('catalogue.csv', $csv),
        ]);

        $response->assertViewHas('rows', function ($rows) {
            return $rows[0]['status'] === 'error' && $rows[0]['importable'] === false;
        });
    }

    public function test_import_rejects_duplicate_reference_within_the_same_file(): void
    {
        $admin = $this->admin();

        $csv = "reference;nom;prix_achat;prix_vente\n"
            ."TUY-1;Tuyau PVC 1m;500;800\n"
            ."TUY-1;Tuyau PVC 1m (bis);500;800\n";

        $response = $this->actingAs($admin)->post(route('products.import.analyze'), [
            'catalogue' => UploadedFile::fake()->createWithContent('catalogue.csv', $csv),
        ]);

        $response->assertViewHas('rows', function ($rows) {
            return $rows[0]['status'] === 'new' && $rows[1]['status'] === 'error';
        });
    }

    public function test_analyze_rejects_file_missing_required_columns(): void
    {
        $admin = $this->admin();

        $csv = "nom;prix_achat\nVis;100\n";

        $response = $this->actingAs($admin)->post(route('products.import.analyze'), [
            'catalogue' => UploadedFile::fake()->createWithContent('catalogue.csv', $csv),
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('catalogue');
    }

    public function test_storing_confirmed_rows_creates_and_updates_products(): void
    {
        $admin = $this->admin();

        $existing = Product::create([
            'reference' => 'VIS-1', 'name' => 'Vis à bois', 'purchase_price' => 100, 'sale_price' => 150,
            'unit' => 'unité', 'low_stock_threshold' => 5,
        ]);

        $rows = [
            [
                'include' => '1', 'status' => 'update', 'reference' => 'VIS-1', 'name' => 'Vis à bois inox',
                'brand' => '', 'description' => '', 'category_id' => '', 'category_name' => '',
                'supplier_id' => '', 'supplier_sku' => '', 'product_family_id' => '', 'family_name' => '',
                'variant_attributes' => '[]', 'purchase_price' => '130', 'sale_price' => '190', 'pro_price' => '',
                'barcode' => '', 'location' => '', 'unit' => 'unité', 'sale_unit' => '', 'sale_unit_factor' => '1',
                'purchase_unit' => '', 'purchase_unit_factor' => '1', 'low_stock_threshold' => '5',
                'security_stock' => '0', 'reorder_point' => '', 'max_stock' => '', 'tracks_lots' => '0',
                'active' => '1', 'stock_initial' => '',
            ],
            [
                'include' => '1', 'status' => 'new', 'reference' => 'CLOU-1', 'name' => 'Clou 50mm',
                'brand' => '', 'description' => '', 'category_id' => '', 'category_name' => 'Visserie',
                'supplier_id' => '', 'supplier_sku' => '', 'product_family_id' => '', 'family_name' => '',
                'variant_attributes' => '{"taille":"50mm"}', 'purchase_price' => '50', 'sale_price' => '80', 'pro_price' => '',
                'barcode' => '', 'location' => '', 'unit' => 'unité', 'sale_unit' => '', 'sale_unit_factor' => '1',
                'purchase_unit' => '', 'purchase_unit_factor' => '1', 'low_stock_threshold' => '5',
                'security_stock' => '0', 'reorder_point' => '', 'max_stock' => '', 'tracks_lots' => '0',
                'active' => '1', 'stock_initial' => '200',
            ],
        ];

        $response = $this->actingAs($admin)->post(route('products.import.store'), ['rows' => $rows]);

        $existing->refresh();
        $this->assertSame(190.0, (float) $existing->sale_price);

        $newProduct = Product::where('reference', 'CLOU-1')->firstOrFail();
        $this->assertSame(['taille' => '50mm'], $newProduct->variant_attributes);
        $category = Category::where('name', 'Visserie')->first();
        $this->assertNotNull($category);
        $this->assertSame($category->id, $newProduct->category_id);

        $inventoryCount = InventoryCount::firstOrFail();
        $response->assertRedirect(route('inventory-counts.show', $inventoryCount));
        $line = $inventoryCount->lines()->where('product_id', $newProduct->id)->firstOrFail();
        $this->assertSame(200.0, (float) $line->counted_quantity);

        // Le stock physique n'est écrit qu'à la clôture de l'inventaire, pas à l'import.
        $this->assertSame(0.0, $newProduct->currentStock());
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_storing_ignores_excluded_and_error_rows(): void
    {
        $admin = $this->admin();

        $rows = [
            [
                'include' => '0', 'status' => 'new', 'reference' => 'SKIP-1', 'name' => 'Ignoré',
                'brand' => '', 'description' => '', 'category_id' => '', 'category_name' => '',
                'supplier_id' => '', 'supplier_sku' => '', 'product_family_id' => '', 'family_name' => '',
                'variant_attributes' => '[]', 'purchase_price' => '10', 'sale_price' => '20', 'pro_price' => '',
                'barcode' => '', 'location' => '', 'unit' => 'unité', 'sale_unit' => '', 'sale_unit_factor' => '1',
                'purchase_unit' => '', 'purchase_unit_factor' => '1', 'low_stock_threshold' => '5',
                'security_stock' => '0', 'reorder_point' => '', 'max_stock' => '', 'tracks_lots' => '0',
                'active' => '1', 'stock_initial' => '',
            ],
        ];

        $this->actingAs($admin)->post(route('products.import.store'), ['rows' => $rows]);

        $this->assertDatabaseCount('products', 0);
    }

    public function test_cashier_cannot_access_catalogue_import(): void
    {
        $cashier = User::factory()->create();
        $cashier->assignRole('caissier');

        $this->actingAs($cashier)->get(route('products.import'))->assertForbidden();
    }

    public function test_template_download_lists_all_known_columns(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get(route('products.import.template'));

        $response->assertOk();
        $response->assertSee('reference', false);
        $response->assertSee('stock_initial', false);
    }
}
