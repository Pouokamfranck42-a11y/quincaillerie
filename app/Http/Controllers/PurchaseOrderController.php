<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseOrderController extends Controller
{
    public function index()
    {
        $purchaseOrders = PurchaseOrder::with('supplier')->withCount('lines')->latest()->paginate(20);

        return view('purchase-orders.index', compact('purchaseOrders'));
    }

    public function create()
    {
        $suppliers = Supplier::orderBy('name')->get();
        $products = Product::where('active', true)->orderBy('name')
            ->get(['id', 'name', 'reference', 'purchase_price', 'unit', 'purchase_unit', 'purchase_unit_factor']);

        return view('purchase-orders.create', compact('suppliers', 'products'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'notes' => ['nullable', 'string'],
            'extra_costs' => ['nullable', 'numeric', 'min:0'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'exists:products,id'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        $purchaseOrder = DB::transaction(function () use ($data, $request) {
            $purchaseOrder = PurchaseOrder::create([
                'supplier_id' => $data['supplier_id'],
                'user_id' => $request->user()->id,
                'status' => PurchaseOrder::STATUS_ORDERED,
                'ordered_at' => now(),
                'notes' => $data['notes'] ?? null,
                'extra_costs' => $data['extra_costs'] ?? 0,
            ]);

            foreach ($data['lines'] as $line) {
                $purchaseOrder->lines()->create($line);
            }

            return $purchaseOrder;
        });

        return redirect()->route('purchase-orders.show', $purchaseOrder)->with('success', 'Commande fournisseur créée.');
    }

    public function show(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->load(['supplier', 'user', 'lines.product']);

        return view('purchase-orders.show', compact('purchaseOrder'));
    }

    public function edit(PurchaseOrder $purchaseOrder)
    {
        abort_unless($purchaseOrder->status === PurchaseOrder::STATUS_DRAFT, 403, 'Seule une commande en brouillon peut être modifiée.');

        $purchaseOrder->load('lines.product');
        $products = Product::where('active', true)->orderBy('name')->get(['id', 'name', 'reference']);

        return view('purchase-orders.edit', compact('purchaseOrder', 'products'));
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder)
    {
        abort_unless($purchaseOrder->status === PurchaseOrder::STATUS_DRAFT, 403, 'Seule une commande en brouillon peut être modifiée.');

        $data = $request->validate([
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.id' => ['required', 'exists:purchase_order_lines,id'],
            'lines.*.product_id' => ['required', 'exists:products,id'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($data, $purchaseOrder) {
            foreach ($data['lines'] as $lineData) {
                $purchaseOrder->lines()->whereKey($lineData['id'])->firstOrFail()->update([
                    'product_id' => $lineData['product_id'],
                    'quantity' => $lineData['quantity'],
                    'unit_price' => $lineData['unit_price'],
                ]);
            }
        });

        return redirect()->route('purchase-orders.show', $purchaseOrder)->with('success', 'Lignes de la commande mises à jour.');
    }

    public function place(Request $request, PurchaseOrder $purchaseOrder)
    {
        if ($purchaseOrder->status !== PurchaseOrder::STATUS_DRAFT) {
            return back()->with('error', 'Seule une commande en brouillon peut être passée.');
        }

        $purchaseOrder->update(['status' => PurchaseOrder::STATUS_ORDERED, 'ordered_at' => now()]);

        return redirect()->route('purchase-orders.show', $purchaseOrder)->with('success', 'Commande passée auprès du fournisseur.');
    }

    public function receive(Request $request, PurchaseOrder $purchaseOrder)
    {
        if ($purchaseOrder->status === PurchaseOrder::STATUS_RECEIVED) {
            return back()->with('error', 'Cette commande a déjà été réceptionnée.');
        }

        $data = $request->validate([
            'quantities' => ['sometimes', 'array'],
            'quantities.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        $purchaseOrder->load('lines.product');
        // Clés converties en entier (Laravel indexe les tableaux HTML par chaîne).
        $quantities = collect($data['quantities'] ?? [])->mapWithKeys(fn ($qty, $lineId) => [(int) $lineId => (float) $qty])->all();

        $purchaseOrder->receive($request->user()->id, $quantities);

        $message = $purchaseOrder->fresh()->status === PurchaseOrder::STATUS_RECEIVED
            ? 'Commande réceptionnée intégralement : le stock a été mis à jour.'
            : 'Réception partielle enregistrée : le stock a été mis à jour, un reliquat reste à recevoir.';

        return redirect()->route('purchase-orders.show', $purchaseOrder)->with('success', $message);
    }

    public function returnLine(Request $request, PurchaseOrder $purchaseOrder, PurchaseOrderLine $line)
    {
        abort_if($line->purchase_order_id !== $purchaseOrder->id, 404);

        $data = $request->validate([
            'quantity' => ['required', 'numeric', 'min:0.01', 'max:'.$line->returnableQuantity()],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $line->returnQuantity((float) $data['quantity'], $request->user()->id, $data['reason'] ?? null);

        return redirect()->route('purchase-orders.show', $purchaseOrder)->with('success', 'Retour fournisseur enregistré : le stock a été mis à jour.');
    }

    /**
     * Quantité suggérée pour reconstituer le stock : comble l'écart jusqu'au stock maximum
     * (ou, à défaut, point de commande + stock de sécurité + EOQ), en tenant compte de ce qui
     * est déjà en commande, avec l'EOQ comme plancher pour éviter les micro-réappros.
     */
    private function suggestedQuantity(Product $product): float
    {
        $target = $product->max_stock !== null
            ? (float) $product->max_stock
            : $product->effectiveReorderPoint() + (float) $product->security_stock + $product->economicOrderQuantity();

        $deficit = $target - ($product->availableStock() + $product->incomingStock());

        return round(max($deficit, $product->economicOrderQuantity(), 1), 2);
    }

    /** Produits actifs sous leur point de commande, groupés par fournisseur, avec une quantité de réappro suggérée. */
    public function suggestions()
    {
        $lowStock = Product::query()
            ->where('active', true)
            ->whereNotNull('supplier_id')
            ->with('supplier')
            ->get()
            ->filter(fn (Product $p) => $p->needsReorder())
            ->each(fn (Product $p) => $p->suggested_quantity = $this->suggestedQuantity($p))
            ->groupBy('supplier_id');

        return view('purchase-orders.suggestions', compact('lowStock'));
    }

    /** Crée une commande fournisseur en brouillon par fournisseur concerné, à partir des suggestions. */
    public function createSuggestions(Request $request)
    {
        $data = $request->validate([
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['exists:products,id'],
        ]);

        $products = Product::whereIn('id', $data['product_ids'])
            ->whereNotNull('supplier_id')
            ->get()
            ->groupBy('supplier_id');

        $created = DB::transaction(function () use ($products, $request) {
            $orders = collect();

            foreach ($products as $supplierId => $supplierProducts) {
                $purchaseOrder = PurchaseOrder::create([
                    'supplier_id' => $supplierId,
                    'user_id' => $request->user()->id,
                    'status' => PurchaseOrder::STATUS_DRAFT,
                    'notes' => 'Générée automatiquement à partir des suggestions de réapprovisionnement.',
                ]);

                foreach ($supplierProducts as $product) {
                    $purchaseOrder->lines()->create([
                        'product_id' => $product->id,
                        'quantity' => $this->suggestedQuantity($product),
                        'unit_price' => $product->purchase_price,
                    ]);
                }

                $orders->push($purchaseOrder);
            }

            return $orders;
        });

        return redirect()->route('purchase-orders.index')
            ->with('success', $created->count().' commande(s) fournisseur créée(s) en brouillon — à compléter et passer.');
    }
}
