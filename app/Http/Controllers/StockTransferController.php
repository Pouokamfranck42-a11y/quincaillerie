<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockTransferController extends Controller
{
    public function index()
    {
        $transfers = StockTransfer::with(['fromWarehouse', 'toWarehouse'])->withCount('lines')->latest()->paginate(20);

        return view('stock-transfers.index', compact('transfers'));
    }

    public function create()
    {
        $warehouses = Warehouse::orderBy('name')->get();
        $products = Product::where('active', true)->orderBy('name')->get(['id', 'name', 'reference', 'unit']);

        return view('stock-transfers.create', compact('warehouses', 'products'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'from_warehouse_id' => ['required', 'exists:warehouses,id', 'different:to_warehouse_id'],
            'to_warehouse_id' => ['required', 'exists:warehouses,id'],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'exists:products,id'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.01'],
        ]);

        $transfer = DB::transaction(function () use ($data, $request) {
            $transfer = StockTransfer::create([
                'from_warehouse_id' => $data['from_warehouse_id'],
                'to_warehouse_id' => $data['to_warehouse_id'],
                'user_id' => $request->user()->id,
                'status' => StockTransfer::STATUS_DRAFT,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['lines'] as $line) {
                $transfer->lines()->create($line);
            }

            return $transfer;
        });

        return redirect()->route('stock-transfers.show', $transfer)->with('success', 'Transfert créé — à exécuter pour déplacer le stock.');
    }

    public function show(StockTransfer $stockTransfer)
    {
        $stockTransfer->load(['fromWarehouse', 'toWarehouse', 'user', 'lines.product']);

        return view('stock-transfers.show', compact('stockTransfer'));
    }

    public function execute(Request $request, StockTransfer $stockTransfer)
    {
        if ($stockTransfer->status === StockTransfer::STATUS_COMPLETED) {
            return back()->with('error', 'Ce transfert a déjà été exécuté.');
        }

        $stockTransfer->load('lines.product');
        $stockTransfer->execute($request->user()->id);

        return redirect()->route('stock-transfers.show', $stockTransfer)->with('success', 'Transfert exécuté : le stock a été déplacé.');
    }
}
