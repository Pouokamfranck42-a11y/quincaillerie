<x-layout title="Modifier l'entrepôt">
    <div class="page-head"><h1><i class="bi bi-pencil-square text-primary"></i> Modifier « {{ $warehouse->name }} »</h1></div>
    <div class="card" style="max-width:520px">
        <form method="POST" action="{{ route('warehouses.update', $warehouse) }}">
            @csrf @method('PUT')
            @include('warehouses._form')
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Enregistrer</button>
                <a href="{{ route('warehouses.index') }}" class="btn btn-ghost">Annuler</a>
            </div>
        </form>
    </div>
</x-layout>
