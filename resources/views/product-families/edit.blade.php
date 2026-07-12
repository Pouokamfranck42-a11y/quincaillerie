<x-layout title="Modifier la famille">
    <div class="page-head"><h1>Modifier « {{ $family->name }} »</h1></div>
    <div class="card" style="max-width:560px">
        <form method="POST" action="{{ route('product-families.update', $family) }}">
            @csrf @method('PUT')
            @include('product-families._form')
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Enregistrer</button>
                <a href="{{ route('product-families.index') }}" class="btn btn-ghost">Annuler</a>
            </div>
        </form>
    </div>
</x-layout>
