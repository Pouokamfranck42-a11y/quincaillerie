<x-layout title="Modifier le fournisseur">
    <div class="page-head"><h1>Modifier « {{ $supplier->name }} »</h1></div>

    <div class="card" style="max-width:640px">
        <form method="POST" action="{{ route('suppliers.update', $supplier) }}">
            @csrf @method('PUT')
            @include('suppliers._form')
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Enregistrer</button>
                <a href="{{ route('suppliers.index') }}" class="btn btn-ghost">Annuler</a>
            </div>
        </form>
    </div>
</x-layout>
