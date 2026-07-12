<x-layout title="Nouveau client">
    <div class="page-head"><h1>Nouveau client</h1></div>
    <div class="card" style="max-width:640px">
        <form method="POST" action="{{ route('customers.store') }}">
            @csrf
            @include('customers._form')
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Créer</button>
                <a href="{{ route('customers.index') }}" class="btn btn-ghost">Annuler</a>
            </div>
        </form>
    </div>
</x-layout>
