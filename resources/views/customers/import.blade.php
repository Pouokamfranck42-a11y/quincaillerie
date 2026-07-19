<x-layout title="Importer des clients">
    <div class="page-head">
        <div>
            <h1><i class="bi bi-file-earmark-arrow-up text-primary"></i> Importer des clients</h1>
            <p>Créez ou mettez à jour plusieurs fiches client en une fois depuis un fichier CSV.</p>
        </div>
        <a href="{{ route('customers.import.template') }}" class="btn"><i class="bi bi-download"></i> Télécharger le modèle CSV</a>
    </div>

    <div class="card" style="max-width:640px">
        @error('clients') <div class="alert alert-crit"><i class="bi bi-exclamation-triangle-fill"></i> <span>{{ $message }}</span></div> @enderror

        <form method="POST" action="{{ route('customers.import.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="field">
                <label for="clients"><i class="bi bi-paperclip me-1"></i>Fichier CSV des clients</label>
                <input type="file" id="clients" name="clients" accept=".csv,text/csv" required class="@error('clients') is-invalid @enderror">
                <div class="hint">Une ligne dont l'e-mail correspond à un client existant met à jour sa fiche plutôt que d'en créer une nouvelle.</div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><i class="bi bi-upload"></i> Importer</button>
                <a href="{{ route('customers.index') }}" class="btn btn-ghost">Annuler</a>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="card-head"><h2><i class="bi bi-table"></i> Colonnes attendues</h2></div>
        <div class="tbl-wrap">
            <table>
                <thead><tr><th>Colonne</th><th>Obligatoire</th><th>Description</th></tr></thead>
                <tbody>
                    <tr><td class="mono">name</td><td><span class="badge badge-crit">oui</span></td><td>Nom du client.</td></tr>
                    <tr><td class="mono">type</td><td><span class="badge badge-neutral">non</span></td><td><code>particulier</code> ou <code>professionnel</code> (défaut : particulier).</td></tr>
                    <tr><td class="mono">phone</td><td><span class="badge badge-neutral">non</span></td><td>Téléphone.</td></tr>
                    <tr><td class="mono">email</td><td><span class="badge badge-neutral">non</span></td><td>Utilisé pour détecter les doublons — sans e-mail, une nouvelle fiche est toujours créée.</td></tr>
                    <tr><td class="mono">address</td><td><span class="badge badge-neutral">non</span></td><td>Adresse.</td></tr>
                    <tr><td class="mono">niu</td><td><span class="badge badge-neutral">non</span></td><td>Numéro d'identifiant unique (entreprise).</td></tr>
                    <tr><td class="mono">credit_limit</td><td><span class="badge badge-neutral">non</span></td><td>Plafond de crédit en FCFA (défaut : 0 — pas de vente à crédit).</td></tr>
                    <tr><td class="mono">payment_terms_days</td><td><span class="badge badge-neutral">non</span></td><td>Délai de paiement en jours (défaut : 30).</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</x-layout>
