<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAssociation;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::published()->with(['category', 'family']);

        $search = $request->string('q')->trim()->value();
        if ($search) {
            // Même logique que Product::scopeSearch() (pg_trgm) avec la marque en plus, propre
            // à la boutique — voir ChatbotTools::searchProducts() pour la même raison de ne pas
            // imbriquer le scope partagé ici (son orderByRaw ne survivrait pas au grouping).
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('reference', 'ilike', "%{$search}%")
                    ->orWhere('brand', 'ilike', "%{$search}%")
                    ->orWhereRaw('similarity(name, ?) > 0.15', [$search])
                    ->orWhereRaw('similarity(reference, ?) > 0.15', [$search]);
            })->orderByRaw('GREATEST(similarity(name, ?), similarity(reference, ?)) DESC', [$search, $search]);
        }

        if ($categoryId = $request->integer('categorie')) {
            $query->where('category_id', $categoryId);
        }

        $products = $search ? $query->paginate(12)->withQueryString() : $query->orderBy('name')->paginate(12)->withQueryString();
        $categories = Category::whereHas('products', fn ($q) => $q->published())->orderBy('name')->get();

        return view('shop.catalog.index', compact('products', 'categories'));
    }

    public function show(Product $product)
    {
        abort_unless($product->published_online && $product->active, 404);

        $variants = $product->product_family_id
            ? Product::published()
                ->where('product_family_id', $product->product_family_id)
                ->where('id', '!=', $product->id)
                ->get()
            : collect();

        // Ventes croisées (Phase 7) : associations déjà calculées par app:compute-cross-sell,
        // aucun appel IA ici — pur historique de co-achat, filtré aux produits publiés en ligne.
        $crossSells = ProductAssociation::where('product_id', $product->id)
            ->orderByDesc('co_occurrence_count')
            ->with('associatedProduct')
            ->limit(6)
            ->get()
            ->pluck('associatedProduct')
            ->filter(fn (?Product $p) => $p !== null && $p->published_online && $p->active)
            ->values();

        return view('shop.catalog.show', compact('product', 'variants', 'crossSells'));
    }
}
