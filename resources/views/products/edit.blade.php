<x-layout title="Modifier le produit">
    <div class="page-head">
        <h1><i class="bi bi-pencil-square text-primary"></i> Modifier « {{ $product->name }} »</h1>
        <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#delete-product-{{ $product->id }}"><i class="bi bi-trash3"></i> Supprimer</button>
    </div>

    <x-confirm-modal id="delete-product-{{ $product->id }}" title="Supprimer ce produit ?" body="Le produit sera déplacé vers la corbeille — tu pourras le restaurer plus tard depuis Corbeille.">
        <form method="POST" action="{{ route('products.destroy', $product) }}">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-danger"><i class="bi bi-trash3"></i> Supprimer</button>
        </form>
    </x-confirm-modal>

    <div class="card" style="max-width:680px">
        <form method="POST" action="{{ route('products.update', $product) }}" enctype="multipart/form-data">
            @csrf @method('PUT')
            @include('products._form')
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Enregistrer</button>
                <a href="{{ route('products.index') }}" class="btn btn-ghost">Annuler</a>
            </div>
        </form>
    </div>
</x-layout>
