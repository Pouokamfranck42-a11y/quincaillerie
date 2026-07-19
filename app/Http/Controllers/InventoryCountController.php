<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\InventoryCount;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryCountController extends Controller
{
    public function index()
    {
        $counts = InventoryCount::with(['warehouse', 'category'])->withCount('lines')->latest()->paginate(20);

        return view('inventory-counts.index', compact('counts'));
    }

    public function create()
    {
        $warehouses = Warehouse::orderBy('name')->get();
        $categories = Category::orderBy('name')->get();

        return view('inventory-counts.create', compact('warehouses', 'categories'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'type' => ['required', 'in:complet,tournant'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'notes' => ['nullable', 'string'],
        ]);

        $products = Product::where('active', true)
            ->when($data['type'] === 'tournant' && ! empty($data['category_id']), fn ($q) => $q->where('category_id', $data['category_id']))
            ->get();

        $count = DB::transaction(function () use ($data, $request, $products) {
            $count = InventoryCount::create([
                'warehouse_id' => $data['warehouse_id'],
                'user_id' => $request->user()->id,
                'type' => $data['type'],
                'category_id' => $data['category_id'] ?? null,
                'status' => InventoryCount::STATUS_IN_PROGRESS,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($products as $product) {
                $count->lines()->create([
                    'product_id' => $product->id,
                    'expected_quantity' => $product->currentStock(),
                ]);
            }

            return $count;
        });

        return redirect()->route('inventory-counts.show', $count)->with('success', 'Comptage créé avec '.$products->count().' produit(s) — saisissez les quantités réelles.');
    }

    public function show(InventoryCount $inventoryCount)
    {
        $inventoryCount->load(['warehouse', 'category', 'user', 'lines.product']);

        return view('inventory-counts.show', compact('inventoryCount'));
    }

    public function updateLines(Request $request, InventoryCount $inventoryCount)
    {
        if ($inventoryCount->status === InventoryCount::STATUS_COMPLETED) {
            return back()->with('error', 'Ce comptage est déjà clôturé.');
        }

        $data = $request->validate([
            'counted' => ['sometimes', 'array'],
            'counted.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        foreach ($data['counted'] ?? [] as $lineId => $quantity) {
            if ($quantity === null || $quantity === '') {
                continue;
            }

            $inventoryCount->lines()->where('id', $lineId)->update(['counted_quantity' => $quantity]);
        }

        return redirect()->route('inventory-counts.show', $inventoryCount)->with('success', 'Comptage enregistré.');
    }

    /** Modèle CSV pré-rempli avec les références déjà attendues dans ce comptage — reste à remplir la colonne counted_quantity. */
    public function exportTemplate(InventoryCount $inventoryCount)
    {
        $inventoryCount->load('lines.product');

        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, ['reference', 'name', 'counted_quantity'], ';');
        foreach ($inventoryCount->lines as $line) {
            fputcsv($stream, [$line->product->reference, $line->product->name, ''], ';');
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="comptage-'.$inventoryCount->id.'-a-remplir.csv"',
        ]);
    }

    /**
     * Import en masse des quantités comptées (Phase 4) — pour un comptage fait au scanner/sur
     * tableur plutôt que ligne par ligne dans le navigateur. Réutilise le même champ
     * counted_quantity que updateLines() (aucune logique de stock dupliquée ici : la
     * réintégration effective n'a lieu qu'à complete(), via InventoryCount::complete() ->
     * StockService — le noyau unifié).
     */
    public function importCounts(Request $request, InventoryCount $inventoryCount)
    {
        if ($inventoryCount->status === InventoryCount::STATUS_COMPLETED) {
            return back()->with('error', 'Ce comptage est déjà clôturé.');
        }

        $request->validate([
            'counts_file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $handle = fopen($request->file('counts_file')->getRealPath(), 'r');
        $firstLine = fgets($handle);
        $delimiter = str_contains($firstLine, ';') ? ';' : ',';
        rewind($handle);

        $header = fgetcsv($handle, 0, $delimiter);
        $refIndex = $header !== false ? array_search('reference', array_map('trim', $header), true) : false;
        $qtyIndex = $header !== false ? array_search('counted_quantity', array_map('trim', $header), true) : false;

        if ($refIndex === false || $qtyIndex === false) {
            fclose($handle);

            return back()->with('error', 'Le fichier doit contenir les colonnes "reference" et "counted_quantity".');
        }

        $inventoryCount->load('lines.product');
        $linesByReference = $inventoryCount->lines->keyBy(fn ($l) => $l->product->reference);

        $updated = 0;
        $notFound = 0;

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $reference = trim($row[$refIndex] ?? '');
            $quantity = $row[$qtyIndex] ?? null;

            if ($reference === '' || ! is_numeric($quantity)) {
                continue;
            }

            $line = $linesByReference->get($reference);
            if (! $line) {
                $notFound++;

                continue;
            }

            $line->update(['counted_quantity' => (float) $quantity]);
            $updated++;
        }

        fclose($handle);

        $message = "{$updated} quantité(s) importée(s)";
        if ($notFound > 0) {
            $message .= ", {$notFound} référence(s) non trouvée(s) dans ce comptage";
        }

        return redirect()->route('inventory-counts.show', $inventoryCount)->with('success', $message.'.');
    }

    public function complete(Request $request, InventoryCount $inventoryCount)
    {
        $inventoryCount->load('lines');
        $inventoryCount->complete($request->user()->id);

        return redirect()->route('inventory-counts.show', $inventoryCount)
            ->with('success', 'Inventaire clôturé : '.$inventoryCount->discrepancyCount().' écart(s) régularisé(s).');
    }
}
