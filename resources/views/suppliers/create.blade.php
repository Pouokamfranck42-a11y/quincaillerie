<x-layout title="Nouveau fournisseur">
    <div class="page-head"><h1><i class="bi bi-plus-lg text-primary"></i> Nouveau fournisseur</h1></div>

    <div class="card" style="max-width:640px">
        <form method="POST" action="{{ route('suppliers.store') }}">
            @csrf
            @include('suppliers._form')
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Créer</button>
                <a href="{{ route('suppliers.index') }}" class="btn btn-ghost">Annuler</a>
            </div>
        </form>
    </div>
</x-layout>
