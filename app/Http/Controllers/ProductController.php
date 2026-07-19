<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAssociation;
use App\Models\ProductFamily;
use App\Models\Supplier;
use App\Services\Ai\AnomalyDetector;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with(['category', 'supplier'])
            ->withSum('stockMovements as stock_quantity', 'quantity');

        if ($search = $request->string('q')->trim()->value()) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('reference', 'ilike', "%{$search}%")
                    ->orWhere('barcode', 'ilike', "%{$search}%");
            });
        }

        $products = $query->orderBy('name')->paginate(20)->withQueryString();

        return view('products.index', compact('products'));
    }

    public function create()
    {
        $categories = Category::orderBy('name')->get();
        $suppliers = Supplier::orderBy('name')->get();
        $families = ProductFamily::orderBy('name')->get();

        return view('products.create', compact('categories', 'suppliers', 'families'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $alternates = $this->alternateSuppliers($request);

        // 'ecommerce.publier' protège ce champ précis, indépendamment de 'produits.creer' —
        // un produit créé sans ce droit reste simplement non publié (défaut sûr).
        if (! $request->user()->can('ecommerce.publier')) {
            unset($data['published_online']);
        }

        $product = Product::create($data);
        $product->alternateSuppliers()->createMany($alternates);

        return redirect()->route('products.index')->with('success', 'Produit créé.');
    }

    public function show(Product $product)
    {
        $product->load(['category', 'supplier', 'alternateSuppliers.supplier']);
        $movements = $product->stockMovements()->latest()->limit(30)->get();
        $lots = $product->tracks_lots ? $product->lots()->get()->sortBy(fn ($lot) => $lot->expiry_date?->timestamp ?? PHP_INT_MAX) : collect();
        $associations = ProductAssociation::where('product_id', $product->id)
            ->orderByDesc('co_occurrence_count')
            ->with('associatedProduct')
            ->get()
            ->pluck('associatedProduct')
            ->filter();

        $pricing = $this->pricingInsight($product);

        return view('products.show', compact('product', 'movements', 'lots', 'associations', 'pricing'));
    }

    public function edit(Product $product)
    {
        $categories = Category::orderBy('name')->get();
        $suppliers = Supplier::orderBy('name')->get();
        $families = ProductFamily::orderBy('name')->get();
        $product->load('alternateSuppliers');

        return view('products.edit', compact('product', 'categories', 'suppliers', 'families'));
    }

    public function update(Request $request, Product $product)
    {
        $data = $this->validated($request, $product->id);
        $alternates = $this->alternateSuppliers($request);

        $priceFields = ['purchase_price', 'sale_price', 'pro_price'];
        $oldPrices = $product->only($priceFields);

        // Contrôle au niveau du champ, pas de la route : 'produits.modifier' permet d'éditer une
        // fiche produit, mais 'prix.modifier'/'ecommerce.publier' protègent spécifiquement ces
        // champs — s'ils sont absents de la requête (permission manquante), on garde la valeur
        // actuelle plutôt que de rejeter toute la mise à jour.
        if (! $request->user()->can('prix.modifier')) {
            foreach ($priceFields as $field) {
                unset($data[$field]);
            }
        }
        if (! $request->user()->can('ecommerce.publier')) {
            unset($data['published_online']);
        }

        $product->update($data);
        $product->alternateSuppliers()->delete();
        $product->alternateSuppliers()->createMany($alternates);

        $newPrices = $product->only($priceFields);
        if ($oldPrices != $newPrices) {
            AuditLog::record('product.price_changed', $product, $oldPrices, $newPrices, $request->user()->id);
            AnomalyDetector::checkPriceChange($product, (float) $oldPrices['sale_price'], (float) $newPrices['sale_price']);
        }

        return redirect()->route('products.index')->with('success', 'Produit mis à jour.');
    }

    public function label(Product $product)
    {
        return view('products.label', compact('product'));
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return redirect()->route('products.index')->with('success', 'Produit envoyé à la corbeille.');
    }

    /**
     * Suggestion de pricing purement dérivée des données existantes (marge vs moyenne catégorie,
     * rotation) — aucun appel IA, aucune persistance.
     *
     * @return array{category_avg_margin: ?float, turnover_90d: float, is_dormant: bool, suggestion: ?string}
     */
    private function pricingInsight(Product $product): array
    {
        $categoryAvgMargin = null;
        if ($product->category_id) {
            $categoryAvgMargin = Product::where('category_id', $product->category_id)
                ->where('active', true)
                ->get()
                ->avg(fn (Product $p) => $p->marginPercent());
        }

        $sold90d = (float) $product->saleLines()
            ->whereHas('sale', fn ($q) => $q->where('status', 'completed')->where('created_at', '>=', now()->subDays(90)))
            ->sum('quantity');

        $isDormant = $sold90d == 0.0 && $product->currentStock() > 0;
        $turnover = $product->currentStock() > 0 ? round($sold90d / max($product->currentStock(), 0.01), 2) : 0.0;

        $suggestion = null;
        if ($isDormant) {
            $suggestion = 'Produit dormant depuis 90 jours : envisager une baisse de prix pour écouler le stock.';
        } elseif ($categoryAvgMargin !== null && $turnover > 1 && $product->marginPercent() < $categoryAvgMargin) {
            $suggestion = 'Forte rotation avec une marge sous la moyenne de la catégorie ('.round($categoryAvgMargin, 1).'%) : marge de manœuvre pour augmenter le prix.';
        }

        return [
            'category_avg_margin' => $categoryAvgMargin,
            'turnover_90d' => $turnover,
            'is_dormant' => $isDormant,
            'suggestion' => $suggestion,
        ];
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:100', 'unique:products,reference'.($ignoreId ? ",{$ignoreId}" : '')],
            'name' => ['required', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'photo' => ['nullable', 'image', 'max:2048'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'supplier_sku' => ['nullable', 'string', 'max:100'],
            'product_family_id' => ['nullable', 'exists:product_families,id'],
            'purchase_price' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['required', 'numeric', 'min:0'],
            'pro_price' => ['nullable', 'numeric', 'min:0'],
            'barcode' => ['nullable', 'string', 'max:100', 'unique:products,barcode'.($ignoreId ? ",{$ignoreId}" : '')],
            'location' => ['nullable', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:50'],
            'sold_by_cut' => ['sometimes', 'boolean'],
            'cut_step' => ['required_if:sold_by_cut,1', 'nullable', 'numeric', 'min:0.001'],
            'sale_unit' => ['nullable', 'string', 'max:50'],
            'sale_unit_factor' => ['required', 'numeric', 'min:0.001'],
            'purchase_unit' => ['nullable', 'string', 'max:50'],
            'purchase_unit_factor' => ['required', 'numeric', 'min:0.001'],
            'low_stock_threshold' => ['required', 'numeric', 'min:0'],
            'security_stock' => ['required', 'numeric', 'min:0'],
            'max_stock' => ['nullable', 'numeric', 'min:0'],
            'reorder_point' => ['nullable', 'numeric', 'min:0'],
            'tracks_lots' => ['sometimes', 'boolean'],
            'published_online' => ['sometimes', 'boolean'],
            'variant_attrs' => ['sometimes', 'array'],
            'variant_attrs.*.key' => ['nullable', 'string', 'max:100'],
            'variant_attrs.*.value' => ['nullable', 'string', 'max:255'],
            'alt_suppliers' => ['sometimes', 'array'],
            'alt_suppliers.*.supplier_id' => ['nullable', 'exists:suppliers,id'],
            'alt_suppliers.*.supplier_sku' => ['nullable', 'string', 'max:100'],
            'alt_suppliers.*.purchase_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        $data['variant_attributes'] = collect($data['variant_attrs'] ?? [])
            ->filter(fn ($pair) => filled($pair['key'] ?? null))
            ->mapWithKeys(fn ($pair) => [$pair['key'] => $pair['value'] ?? ''])
            ->all();
        unset($data['variant_attrs'], $data['alt_suppliers']);
        if (empty($data['variant_attributes'])) {
            $data['variant_attributes'] = null;
        }

        $data['active'] = $request->boolean('active');
        $data['tracks_lots'] = $request->boolean('tracks_lots');
        $data['published_online'] = $request->boolean('published_online');
        $data['sold_by_cut'] = $request->boolean('sold_by_cut');
        $data['cut_step'] = $data['sold_by_cut'] ? $data['cut_step'] : 1;

        if ($request->hasFile('photo')) {
            $data['photo_path'] = $request->file('photo')->store('products', 'public');
        }
        unset($data['photo']);

        return $data;
    }

    /** @return array<int, array{supplier_id:int, supplier_sku:?string, purchase_price:?float}> */
    private function alternateSuppliers(Request $request): array
    {
        return collect($request->input('alt_suppliers', []))
            ->filter(fn ($row) => filled($row['supplier_id'] ?? null))
            ->map(fn ($row) => [
                'supplier_id' => $row['supplier_id'],
                'supplier_sku' => $row['supplier_sku'] ?? null,
                'purchase_price' => $row['purchase_price'] ?? null,
            ])
            ->values()
            ->all();
    }
}
