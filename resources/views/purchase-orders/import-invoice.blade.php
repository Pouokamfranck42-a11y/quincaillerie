<x-layout title="Importer une facture">
    <div class="page-head"><h1><i class="bi bi-file-earmark-arrow-up text-primary"></i> Importer une facture fournisseur</h1></div>

    <div class="card" style="max-width:560px">
        <p class="muted mt-0"><i class="bi bi-stars"></i> Prends une photo ou choisis un PDF de la facture — l'IA extrait les lignes et te propose un brouillon de commande à vérifier avant enregistrement.</p>

        <form method="POST" action="{{ route('purchase-orders.import-invoice.analyze') }}" enctype="multipart/form-data">
            @csrf
            <div class="field">
                <label for="invoice"><i class="bi bi-paperclip me-1"></i>Facture (photo ou PDF)</label>
                <input type="file" id="invoice" name="invoice" accept="image/*,application/pdf" required class="@error('invoice') is-invalid @enderror">
                @error('invoice') <div class="error"><i class="bi bi-exclamation-circle"></i> {{ $message }}</div> @enderror
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><i class="bi bi-magic"></i> Analyser la facture</button>
                <a href="{{ route('purchase-orders.index') }}" class="btn btn-ghost">Annuler</a>
            </div>
        </form>
    </div>
</x-layout>
