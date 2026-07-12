<x-layout title="Nouveau fournisseur">
    <div class="page-head"><h1>Nouveau fournisseur</h1></div>

    <div class="card" style="max-width:640px">
        <form method="POST" action="{{ route('suppliers.store') }}">
            @csrf
            @include('suppliers._form')
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Créer</button>
                <a href="{{ route('suppliers.index') }}" class="btn btn-ghost">Annuler</a>
            </div>
        </form>
    </div>
</x-layout>
