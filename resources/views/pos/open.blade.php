<x-layout title="Ouvrir la caisse">
    <div class="page-head"><h1><i class="bi bi-cash-coin text-primary"></i> Ouvrir la caisse</h1></div>

    <div class="card" style="max-width:420px">
        <p class="mt-0">Renseignez le fond de caisse de départ pour démarrer votre session de vente.</p>
        <form method="POST" action="{{ route('cash-sessions.open') }}">
            @csrf
            <div class="field">
                <label for="opening_amount"><i class="bi bi-wallet2 me-1"></i>Fond de caisse (FCFA)</label>
                <input type="number" step="1" min="0" id="opening_amount" name="opening_amount" value="0" required autofocus class="@error('opening_amount') is-invalid @enderror">
                @error('opening_amount') <div class="error"><i class="bi bi-exclamation-circle"></i> {{ $message }}</div> @enderror
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary" style="width:100%"><i class="bi bi-door-open"></i> Ouvrir la caisse</button>
            </div>
        </form>
    </div>
</x-layout>
