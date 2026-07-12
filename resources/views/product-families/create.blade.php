<x-layout title="Nouvelle famille">
    <div class="page-head"><h1>Nouvelle famille de produits</h1></div>
    <div class="card" style="max-width:560px">
        <form method="POST" action="{{ route('product-families.store') }}">
            @csrf
            @include('product-families._form')
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Créer</button>
                <a href="{{ route('product-families.index') }}" class="btn btn-ghost">Annuler</a>
            </div>
        </form>
    </div>
</x-layout>
