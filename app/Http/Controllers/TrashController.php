<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductFamily;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class TrashController extends Controller
{
    /** Types autorisés dans la corbeille — liste blanche, jamais de classe arbitraire depuis l'URL. */
    private const TYPES = [
        'products' => ['class' => Product::class, 'label' => 'Produits'],
        'categories' => ['class' => Category::class, 'label' => 'Catégories'],
        'suppliers' => ['class' => Supplier::class, 'label' => 'Fournisseurs'],
        'product-families' => ['class' => ProductFamily::class, 'label' => 'Familles de produits'],
        'customers' => ['class' => Customer::class, 'label' => 'Clients'],
        'users' => ['class' => User::class, 'label' => 'Utilisateurs'],
    ];

    public function index()
    {
        $groups = collect(self::TYPES)->map(function ($meta, $type) {
            return [
                'type' => $type,
                'label' => $meta['label'],
                'items' => $meta['class']::onlyTrashed()->latest('deleted_at')->get(),
            ];
        })->filter(fn ($group) => $group['items']->isNotEmpty());

        return view('trash.index', compact('groups'));
    }

    public function restore(Request $request, string $type, int $id)
    {
        $model = $this->resolve($type, $id);

        $model->restore();

        return back()->with('success', 'Élément restauré.');
    }

    public function forceDelete(Request $request, string $type, int $id)
    {
        $model = $this->resolve($type, $id);

        if ($model instanceof Product && $model->stockMovements()->exists()) {
            return back()->with('error', 'Ce produit a un historique de mouvements de stock : suppression définitive impossible (l\'historique doit rester consultable). Laissez-le en corbeille ou restaurez-le.');
        }

        try {
            $model->forceDelete();
        } catch (QueryException $e) {
            return back()->with('error', 'Suppression définitive impossible : cet élément est encore référencé ailleurs dans l\'application.');
        }

        return back()->with('success', 'Élément supprimé définitivement.');
    }

    private function resolve(string $type, int $id)
    {
        if (! array_key_exists($type, self::TYPES)) {
            abort(404);
        }

        $model = self::TYPES[$type]['class']::onlyTrashed()->find($id);

        if (! $model) {
            throw new ModelNotFoundException;
        }

        return $model;
    }
}
