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

    public function complete(Request $request, InventoryCount $inventoryCount)
    {
        $inventoryCount->load('lines');
        $inventoryCount->complete($request->user()->id);

        return redirect()->route('inventory-counts.show', $inventoryCount)
            ->with('success', 'Inventaire clôturé : '.$inventoryCount->discrepancyCount().' écart(s) régularisé(s).');
    }
}
