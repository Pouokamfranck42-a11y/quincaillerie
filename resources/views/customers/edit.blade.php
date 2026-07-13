<x-layout title="Modifier le client">
    <div class="page-head"><h1><i class="bi bi-pencil-square text-primary"></i> Modifier « {{ $customer->name }} »</h1></div>
    <div class="card" style="max-width:640px">
        <form method="POST" action="{{ route('customers.update', $customer) }}">
            @csrf @method('PUT')
            @include('customers._form')
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Enregistrer</button>
                <a href="{{ route('customers.index') }}" class="btn btn-ghost">Annuler</a>
            </div>
        </form>
    </div>
</x-layout>
