<x-layout title="Importer une facture">
    <div class="page-head"><h1>Importer une facture fournisseur</h1></div>

    <div class="card" style="max-width:560px">
        <p class="muted mt-0">Prends une photo ou choisis un PDF de la facture — l'IA extrait les lignes et te propose un brouillon de commande à vérifier avant enregistrement.</p>

        <form method="POST" action="{{ route('purchase-orders.import-invoice.analyze') }}" enctype="multipart/form-data">
            @csrf
            <div class="field">
                <label for="invoice">Facture (photo ou PDF)</label>
                <input type="file" id="invoice" name="invoice" accept="image/*,application/pdf" required>
                @error('invoice') <div class="error">{{ $message }}</div> @enderror
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Analyser la facture</button>
                <a href="{{ route('purchase-orders.index') }}" class="btn btn-ghost">Annuler</a>
            </div>
        </form>
    </div>
</x-layout>
