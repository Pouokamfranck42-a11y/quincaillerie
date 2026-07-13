<x-layout title="Nouvel entrepôt">
    <div class="page-head"><h1><i class="bi bi-plus-lg text-primary"></i> Nouvel entrepôt</h1></div>
    <div class="card" style="max-width:520px">
        <form method="POST" action="{{ route('warehouses.store') }}">
            @csrf
            @include('warehouses._form')
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Créer</button>
                <a href="{{ route('warehouses.index') }}" class="btn btn-ghost">Annuler</a>
            </div>
        </form>
    </div>
</x-layout>
