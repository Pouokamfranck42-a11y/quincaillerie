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

    <div class="card" style="max-width:640px">
        <div class="card-head"><h2><i class="bi bi-globe2"></i> Compte boutique en ligne</h2></div>
        @if ($customer->hasWebAccount())
            <p class="mt-0"><i class="bi bi-check-circle-fill text-success"></i> Ce client a un compte boutique actif.</p>
        @else
            <p class="mt-0 muted">Ce client n'a pas encore de compte boutique (pas de mot de passe défini).</p>
        @endif
        @if ($customer->email)
            <form method="POST" action="{{ route('customers.send-password-reset', $customer) }}">
                @csrf
                <button type="submit" class="btn">
                    <i class="bi bi-envelope-arrow-up"></i>
                    {{ $customer->hasWebAccount() ? 'Envoyer un lien de réinitialisation du mot de passe' : 'Envoyer un lien d\'activation du compte' }}
                </button>
            </form>
        @else
            <p class="muted"><i class="bi bi-exclamation-triangle"></i> Renseignez une adresse e-mail ci-dessus pour pouvoir envoyer un lien.</p>
        @endif
    </div>
</x-layout>
