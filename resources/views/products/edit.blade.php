<x-layout title="Modifier le produit">
    <div class="page-head">
        <h1>Modifier « {{ $product->name }} »</h1>
        <form method="POST" action="{{ route('products.destroy', $product) }}" onsubmit="return confirm('Supprimer ce produit ?');">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-danger btn-sm">Supprimer</button>
        </form>
    </div>

    <div class="card" style="max-width:680px">
        <form method="POST" action="{{ route('products.update', $product) }}" enctype="multipart/form-data">
            @csrf @method('PUT')
            @include('products._form')
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Enregistrer</button>
                <a href="{{ route('products.index') }}" class="btn btn-ghost">Annuler</a>
            </div>
        </form>
    </div>
</x-layout>
