<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductSupplier;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\Ai\GeminiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseInvoiceImportController extends Controller
{
    public function create()
    {
        return view('purchase-orders.import-invoice');
    }

    /**
     * Analyse la facture via l'IA (OCR structuré) et affiche un formulaire de révision —
     * aucune commande n'est créée à cette étape.
     */
    public function analyze(Request $request, GeminiService $gemini)
    {
        $request->validate([
            'invoice' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:8192'],
        ]);

        $file = $request->file('invoice');
        $data = base64_encode((string) file_get_contents($file->getRealPath()));
        $isPdf = $file->getClientOriginalExtension() === 'pdf' || $file->getMimeType() === 'application/pdf';

        $documentBlock = $isPdf
            ? ['type' => 'document', 'source' => ['type' => 'base64', 'mediaType' => 'application/pdf', 'data' => $data]]
            : ['type' => 'image', 'source' => ['type' => 'base64', 'mediaType' => $file->getMimeType() ?: 'image/jpeg', 'data' => $data]];

        $result = $gemini->extractStructured(
            "Tu extrais les informations d'une facture fournisseur de quincaillerie pour préremplir un bon de commande.",
            [
                $documentBlock,
                ['type' => 'text', 'text' => "Extrait le nom du fournisseur et chaque ligne de la facture (désignation telle qu'écrite, quantité, prix unitaire)."],
            ],
            [
                'type' => 'object',
                'properties' => [
                    'supplier_name' => ['type' => 'string'],
                    'lines' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'description' => ['type' => 'string'],
                                'quantity' => ['type' => 'number'],
                                'unit_price' => ['type' => 'number'],
                            ],
                            'required' => ['description', 'quantity', 'unit_price'],
                            'additionalProperties' => false,
                        ],
                    ],
                ],
                'required' => ['lines'],
                'additionalProperties' => false,
            ],
            4096,
        );

        $supplier = filled($result['supplier_name'] ?? null)
            ? Supplier::where('name', 'ilike', '%'.$result['supplier_name'].'%')->first()
            : null;

        $suppliers = Supplier::orderBy('name')->get();
        $products = Product::where('active', true)->orderBy('name')->get(['id', 'name', 'reference']);

        $initialLines = collect($result['lines'] ?? [])->map(function ($line) {
            $description = (string) ($line['description'] ?? '');
            $product = $this->matchProduct($description);

            return [
                'description' => $description,
                'product_id' => $product?->id ?? '',
                'quantity' => (float) ($line['quantity'] ?? 1),
                'unit_price' => (float) ($line['unit_price'] ?? 0),
                'matched' => $product !== null,
            ];
        })->values()->all();

        if ($initialLines === []) {
            $initialLines = [['description' => '', 'product_id' => '', 'quantity' => 1, 'unit_price' => 0, 'matched' => false]];
        }

        return view('purchase-orders.import-invoice-review', [
            'suppliers' => $suppliers,
            'products' => $products,
            'supplierGuessId' => $supplier?->id,
            'supplierGuessName' => $result['supplier_name'] ?? null,
            'initialLines' => $initialLines,
        ]);
    }

    /** Crée réellement le brouillon de commande, une fois les données revues par l'utilisateur. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'exists:products,id'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        $purchaseOrder = DB::transaction(function () use ($data, $request) {
            $purchaseOrder = PurchaseOrder::create([
                'supplier_id' => $data['supplier_id'],
                'user_id' => $request->user()->id,
                'status' => PurchaseOrder::STATUS_DRAFT,
                'notes' => "Créée à partir de l'import de facture (OCR) — vérifiée avant enregistrement.",
            ]);

            foreach ($data['lines'] as $line) {
                $purchaseOrder->lines()->create($line);
            }

            return $purchaseOrder;
        });

        return redirect()->route('purchase-orders.edit', $purchaseOrder)
            ->with('success', 'Brouillon de commande créé à partir de la facture — vérifie les lignes avant de passer la commande.');
    }

    private function matchProduct(string $description): ?Product
    {
        if (trim($description) === '') {
            return null;
        }

        $bySku = ProductSupplier::where('supplier_sku', 'ilike', $description)->first()?->product;
        if ($bySku) {
            return $bySku;
        }

        return Product::where('active', true)
            ->where(function ($q) use ($description) {
                $q->where('name', 'ilike', "%{$description}%")
                    ->orWhere('reference', 'ilike', "%{$description}%");
            })
            ->first();
    }
}
