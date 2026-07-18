<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\InventoryCount;
use App\Models\Product;
use App\Models\ProductFamily;
use App\Models\Warehouse;
use App\Services\Ai\AnomalyDetector;
use App\Services\Catalog\ProductImportParser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ProductImportController extends Controller
{
    public function create()
    {
        return view('products.import', [
            'knownHeaders' => ProductImportParser::KNOWN_HEADERS,
            'requiredHeaders' => ProductImportParser::REQUIRED_HEADERS,
            'maxRows' => ProductImportParser::MAX_ROWS,
        ]);
    }

    /** Modèle CSV téléchargeable, toujours généré depuis la même liste de colonnes que le parseur. */
    public function template()
    {
        $headers = ProductImportParser::KNOWN_HEADERS;
        $example = [
            'VIS-M6X20', 'Vis M6x20 tête hexagonale inox', 'Facome', '', 'Visserie', 'Quincaillerie du Centre',
            'FMV6X20', 'Vis M6', 'taille=M6x20|materiau=inox', '25', '45', '40', '3401234567890',
            'Allée 3 · Rayon B · Casier 12', 'unité', 'boîte de 100', '100', '', '1', '20', '10', '', '', 'non', 'oui', '150',
        ];

        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, $headers, ';');
        fputcsv($stream, $example, ';');
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="modele-import-catalogue.csv"',
        ]);
    }

    /** Analyse le fichier et affiche la page de contrôle — aucune écriture en base à cette étape. */
    public function analyze(Request $request, ProductImportParser $parser)
    {
        $request->validate([
            'catalogue' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $result = $parser->parse($request->file('catalogue'));

        if (! empty($result['fileErrors'])) {
            return back()->withErrors(['catalogue' => $result['fileErrors'][0]]);
        }

        $counts = [
            'new' => 0,
            'update' => 0,
            'error' => 0,
        ];
        foreach ($result['rows'] as $row) {
            $counts[$row['status']]++;
        }

        return view('products.import-review', [
            'rows' => $result['rows'],
            'counts' => $counts,
        ]);
    }

    /** Persiste les lignes revues et cochées par l'utilisateur. */
    public function store(Request $request)
    {
        $request->validate([
            'rows' => ['required', 'array', 'min:1'],
        ]);

        $created = 0;
        $updated = 0;
        $pendingInitialStock = [];
        $inventoryCountId = null;
        $seenBarcodes = [];

        DB::transaction(function () use ($request, &$created, &$updated, &$pendingInitialStock, &$inventoryCountId, &$seenBarcodes) {
            foreach ($request->input('rows', []) as $row) {
                if (($row['include'] ?? '0') !== '1' || ($row['status'] ?? '') === 'error') {
                    continue;
                }

                $barcodeValue = ($row['barcode'] ?? null) ?: null;
                if ($barcodeValue !== null && isset($seenBarcodes[$barcodeValue])) {
                    continue;
                }

                $existingByReference = Product::withTrashed()->where('reference', 'ilike', $row['reference'] ?? '')->first();

                $validator = Validator::make($row, [
                    'reference' => ['required', 'string', 'max:100'],
                    'name' => ['required', 'string', 'max:255'],
                    'purchase_price' => ['required', 'numeric', 'min:0'],
                    'sale_price' => ['required', 'numeric', 'min:0'],
                    'pro_price' => ['nullable', 'numeric', 'min:0'],
                    // Filet de sécurité : normalement déjà écarté à l'étape analyze(), mais
                    // évite qu'un code-barres en conflit ne fasse échouer toute la transaction
                    // (donc tout le lot importé) avec une erreur SQL brute non interceptée.
                    'barcode' => ['nullable', 'string', 'max:100', Rule::unique('products', 'barcode')->ignore($existingByReference?->id)],
                    'sale_unit_factor' => ['required', 'numeric', 'min:0.001'],
                    'purchase_unit_factor' => ['required', 'numeric', 'min:0.001'],
                    'low_stock_threshold' => ['required', 'numeric', 'min:0'],
                    'security_stock' => ['required', 'numeric', 'min:0'],
                ]);

                if ($validator->fails()) {
                    continue;
                }

                $categoryId = $row['category_id'] ?? null ?: null;
                if (! $categoryId && filled($row['category_name'] ?? null)) {
                    $category = Category::where('name', 'ilike', $row['category_name'])->first()
                        ?? Category::create(['name' => $row['category_name']]);
                    $categoryId = $category->id;
                }

                $familyId = $row['product_family_id'] ?? null ?: null;
                if (! $familyId && filled($row['family_name'] ?? null)) {
                    $family = ProductFamily::where('name', 'ilike', $row['family_name'])->first()
                        ?? ProductFamily::create(['name' => $row['family_name']]);
                    $familyId = $family->id;
                }

                $variantAttributes = json_decode($row['variant_attributes'] ?? '[]', true) ?: null;

                $data = [
                    'reference' => $row['reference'],
                    'name' => $row['name'],
                    'brand' => ($row['brand'] ?? null) ?: null,
                    'description' => ($row['description'] ?? null) ?: null,
                    'category_id' => $categoryId,
                    'supplier_id' => ($row['supplier_id'] ?? null) ?: null,
                    'supplier_sku' => ($row['supplier_sku'] ?? null) ?: null,
                    'product_family_id' => $familyId,
                    'variant_attributes' => $variantAttributes,
                    'purchase_price' => (float) $row['purchase_price'],
                    'sale_price' => (float) $row['sale_price'],
                    'pro_price' => filled($row['pro_price'] ?? null) ? (float) $row['pro_price'] : null,
                    'barcode' => ($row['barcode'] ?? null) ?: null,
                    'location' => ($row['location'] ?? null) ?: null,
                    'unit' => ($row['unit'] ?? null) ?: 'unité',
                    'sale_unit' => ($row['sale_unit'] ?? null) ?: null,
                    'sale_unit_factor' => (float) $row['sale_unit_factor'],
                    'purchase_unit' => ($row['purchase_unit'] ?? null) ?: null,
                    'purchase_unit_factor' => (float) $row['purchase_unit_factor'],
                    'low_stock_threshold' => (float) $row['low_stock_threshold'],
                    'security_stock' => (float) $row['security_stock'],
                    'reorder_point' => filled($row['reorder_point'] ?? null) ? (float) $row['reorder_point'] : null,
                    'max_stock' => filled($row['max_stock'] ?? null) ? (float) $row['max_stock'] : null,
                    'tracks_lots' => ($row['tracks_lots'] ?? '0') === '1',
                    'active' => ($row['active'] ?? '1') === '1',
                ];

                if ($barcodeValue !== null) {
                    $seenBarcodes[$barcodeValue] = true;
                }

                $existing = $existingByReference;

                if ($existing) {
                    if ($existing->trashed()) {
                        $existing->restore();
                    }

                    $priceFields = ['purchase_price', 'sale_price', 'pro_price'];
                    $oldPrices = $existing->only($priceFields);

                    $existing->update($data);

                    $newPrices = $existing->only($priceFields);
                    if ($oldPrices != $newPrices) {
                        AuditLog::record('product.price_changed', $existing, $oldPrices, $newPrices, $request->user()->id);
                        AnomalyDetector::checkPriceChange($existing, (float) $oldPrices['sale_price'], (float) $newPrices['sale_price']);
                    }

                    $updated++;
                } else {
                    $product = Product::create($data);
                    $created++;

                    $stockInitial = $row['stock_initial'] ?? '';
                    if ($stockInitial !== '' && is_numeric($stockInitial) && (float) $stockInitial > 0) {
                        $pendingInitialStock[$product->id] = (float) $stockInitial;
                    }
                }
            }

            if (! empty($pendingInitialStock)) {
                $count = InventoryCount::create([
                    'warehouse_id' => Warehouse::where('is_default', true)->value('id'),
                    'user_id' => $request->user()->id,
                    'type' => InventoryCount::TYPE_COMPLET,
                    'status' => InventoryCount::STATUS_IN_PROGRESS,
                    'notes' => 'Stock initial généré automatiquement depuis un import de catalogue.',
                ]);

                foreach ($pendingInitialStock as $productId => $quantity) {
                    $count->lines()->create([
                        'product_id' => $productId,
                        'expected_quantity' => 0,
                        'counted_quantity' => $quantity,
                    ]);
                }

                $inventoryCountId = $count->id;
            }
        });

        $message = "{$created} produit(s) créé(s), {$updated} mis à jour.";

        if ($inventoryCountId !== null) {
            return redirect()->route('inventory-counts.show', $inventoryCountId)
                ->with('success', $message.' Un inventaire initial a été pré-rempli avec les quantités du fichier — vérifiez puis clôturez-le pour enregistrer le stock physique.');
        }

        return redirect()->route('products.index')->with('success', $message);
    }
}
