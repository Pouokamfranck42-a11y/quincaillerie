<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductLot;
use App\Models\StockMovement;
use Illuminate\Http\Request;

class StockMovementController extends Controller
{
    public function index(Request $request)
    {
        $movements = StockMovement::with(['product', 'user'])
            ->latest()
            ->paginate(30);

        $lowStockProducts = Product::query()
            ->withSum('stockMovements as stock_quantity', 'quantity')
            ->where('active', true)
            ->get()
            ->filter(fn (Product $p) => (float) ($p->stock_quantity ?? 0) <= (float) $p->low_stock_threshold)
            ->sortBy('stock_quantity');

        return view('stock-movements.index', compact('movements', 'lowStockProducts'));
    }

    public function create()
    {
        $products = Product::where('active', true)->orderBy('name')->get();

        return view('stock-movements.create', compact('products'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'type' => ['required', 'in:entree,sortie,ajustement'],
            'subtype' => ['nullable', 'string', 'max:50'],
            'direction' => ['required_if:type,ajustement', 'in:augmente,diminue'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string', 'max:255'],
            'lot_number' => ['nullable', 'string', 'max:100'],
            'expiry_date' => ['nullable', 'date'],
        ]);

        $decreases = $data['type'] === 'sortie' || ($data['type'] === 'ajustement' && $data['direction'] === 'diminue');
        $signedQuantity = $decreases ? -$data['quantity'] : $data['quantity'];

        $product = Product::findOrFail($data['product_id']);

        if ($data['type'] === 'entree' && ! empty($data['unit_cost'])) {
            $product->applyCump((float) $data['quantity'], (float) $data['unit_cost']);
        }

        $lotId = null;
        if ($product->tracks_lots && ! empty($data['lot_number'])) {
            $lot = ProductLot::firstOrCreate(
                ['product_id' => $product->id, 'lot_number' => $data['lot_number']],
                ['expiry_date' => $data['expiry_date'] ?? null]
            );
            $lotId = $lot->id;
        }

        StockMovement::create([
            'product_id' => $data['product_id'],
            'lot_id' => $lotId,
            'type' => $data['type'],
            'subtype' => $data['subtype'] ?? null,
            'quantity' => $signedQuantity,
            'unit_cost' => $data['unit_cost'] ?? null,
            'reason' => $data['reason'] ?? 'Mouvement manuel',
            'user_id' => $request->user()->id,
        ]);

        return redirect()->route('stock-movements.index')->with('success', 'Mouvement de stock enregistré.');
    }
}
