<x-layout title="Nouveau produit">
    <div class="page-head"><h1><i class="bi bi-plus-lg text-primary"></i> Nouveau produit</h1></div>

    <div class="card" style="max-width:680px">
        <form method="POST" action="{{ route('products.store') }}" enctype="multipart/form-data">
            @csrf
            @include('products._form')
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Créer</button>
                <a href="{{ route('products.index') }}" class="btn btn-ghost">Annuler</a>
            </div>
        </form>
    </div>
</x-layout>
