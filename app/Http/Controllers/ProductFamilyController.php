<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\ProductFamily;
use Illuminate\Http\Request;

class ProductFamilyController extends Controller
{
    public function index()
    {
        $families = ProductFamily::with('category')->withCount('products')->orderBy('name')->paginate(20);

        return view('product-families.index', compact('families'));
    }

    public function create()
    {
        $categories = Category::orderBy('name')->get();

        return view('product-families.create', compact('categories'));
    }

    public function store(Request $request)
    {
        ProductFamily::create($this->validated($request));

        return redirect()->route('product-families.index')->with('success', 'Famille de produits créée.');
    }

    public function edit(ProductFamily $productFamily)
    {
        $categories = Category::orderBy('name')->get();

        return view('product-families.edit', ['family' => $productFamily, 'categories' => $categories]);
    }

    public function update(Request $request, ProductFamily $productFamily)
    {
        $productFamily->update($this->validated($request));

        return redirect()->route('product-families.index')->with('success', 'Famille de produits mise à jour.');
    }

    public function destroy(ProductFamily $productFamily)
    {
        $productFamily->delete();

        return redirect()->route('product-families.index')->with('success', 'Famille de produits envoyée à la corbeille.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'description' => ['nullable', 'string'],
        ]);
    }
}
