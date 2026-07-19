<x-layout title="Ouvrir un dossier SAV">
    <div class="page-head">
        <div>
            <h1><i class="bi bi-tools text-primary"></i> Ouvrir un dossier SAV</h1>
            <p>Vente #{{ $sale->id }} · {{ $saleLine->product->name }}</p>
        </div>
    </div>

    <div class="card" style="max-width:640px">
        @if ($saleLine->isUnderWarranty())
            <div class="alert alert-good"><i class="bi bi-shield-check"></i> <span>Sous garantie jusqu'au {{ $saleLine->warrantyExpiresAt()->format('d/m/Y') }}.</span></div>
        @elseif ($saleLine->product->warranty_months)
            <div class="alert alert-crit"><i class="bi bi-exclamation-triangle-fill"></i> <span>Garantie expirée depuis le {{ $saleLine->warrantyExpiresAt()->format('d/m/Y') }} — une prise en charge reste possible au cas par cas.</span></div>
        @else
            <div class="alert alert-warn"><i class="bi bi-info-circle"></i> <span>Aucune durée de garantie déclarée pour ce produit.</span></div>
        @endif

        @if ($saleLine->serial_number)
            <p class="muted">N° de série : <span class="mono">{{ $saleLine->serial_number }}</span></p>
        @endif

        <form method="POST" action="{{ route('service-tickets.store', [$sale, $saleLine]) }}">
            @csrf
            <div class="field">
                <label for="issue_description">Description du problème</label>
                <textarea id="issue_description" name="issue_description" rows="4" required autofocus>{{ old('issue_description') }}</textarea>
                @error('issue_description') <div class="error">{{ $message }}</div> @enderror
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Ouvrir le dossier</button>
                <a href="{{ route('sales.show', $sale) }}" class="btn btn-ghost">Annuler</a>
            </div>
        </form>
    </div>
</x-layout>
