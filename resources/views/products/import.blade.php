<x-layout title="Importer le catalogue">
    <div class="page-head">
        <div>
            <h1><i class="bi bi-file-earmark-arrow-up text-primary"></i> Importer le catalogue</h1>
            <p>Créez ou mettez à jour plusieurs produits en une fois depuis un fichier CSV (export Excel « CSV » ou « CSV UTF-8 »).</p>
        </div>
        <a href="{{ route('products.import.template') }}" class="btn"><i class="bi bi-download"></i> Télécharger le modèle CSV</a>
    </div>

    <div class="card" style="max-width:640px">
        <p class="muted mt-0">Le fichier est d'abord analysé et affiché pour vérification — rien n'est enregistré tant que tu n'as pas confirmé.</p>

        @error('catalogue') <div class="alert alert-crit"><i class="bi bi-exclamation-triangle-fill"></i> <span>{{ $message }}</span></div> @enderror

        <form method="POST" action="{{ route('products.import.analyze') }}" enctype="multipart/form-data">
            @csrf
            <div class="field">
                <label for="catalogue"><i class="bi bi-paperclip me-1"></i>Fichier CSV du catalogue</label>
                <input type="file" id="catalogue" name="catalogue" accept=".csv,text/csv" required class="@error('catalogue') is-invalid @enderror">
                <div class="hint">{{ $maxRows }} lignes maximum par import. Encodage UTF-8, séparateur virgule ou point-virgule.</div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><i class="bi bi-magic"></i> Analyser le fichier</button>
                <a href="{{ route('products.index') }}" class="btn btn-ghost">Annuler</a>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="card-head"><h2><i class="bi bi-table"></i> Colonnes attendues</h2></div>
        <p class="muted">Seul l'ordre des en-têtes n'a pas d'importance — les colonnes absentes du fichier prennent leur valeur par défaut.</p>
        <div class="tbl-wrap">
            <table>
                <thead><tr><th>Colonne</th><th>Obligatoire</th><th>Description</th></tr></thead>
                <tbody>
                    <tr><td class="mono">reference</td><td><span class="badge badge-crit">oui</span></td><td>Code interne unique du produit (clé utilisée pour détecter les mises à jour).</td></tr>
                    <tr><td class="mono">nom</td><td><span class="badge badge-crit">oui</span></td><td>Désignation du produit.</td></tr>
                    <tr><td class="mono">prix_achat</td><td><span class="badge badge-crit">oui</span></td><td>Prix d'achat par unité de stock.</td></tr>
                    <tr><td class="mono">prix_vente</td><td><span class="badge badge-crit">oui</span></td><td>Prix de vente particulier par unité de stock.</td></tr>
                    <tr><td class="mono">marque</td><td><span class="badge badge-neutral">non</span></td><td>Marque du produit.</td></tr>
                    <tr><td class="mono">description</td><td><span class="badge badge-neutral">non</span></td><td>Description libre.</td></tr>
                    <tr><td class="mono">categorie</td><td><span class="badge badge-neutral">non</span></td><td>Nom de catégorie — créée automatiquement si elle n'existe pas encore.</td></tr>
                    <tr><td class="mono">fournisseur</td><td><span class="badge badge-neutral">non</span></td><td>Nom du fournisseur principal — doit déjà exister (créé au préalable dans Fournisseurs).</td></tr>
                    <tr><td class="mono">reference_fournisseur</td><td><span class="badge badge-neutral">non</span></td><td>Référence de l'article chez ce fournisseur.</td></tr>
                    <tr><td class="mono">famille</td><td><span class="badge badge-neutral">non</span></td><td>Famille de produit (pour regrouper des variantes) — créée automatiquement si absente.</td></tr>
                    <tr><td class="mono">variante</td><td><span class="badge badge-neutral">non</span></td><td>Attributs de variante au format <code>cle=valeur|cle=valeur</code>, ex. <code>taille=M6x20|materiau=inox</code> (séparateur <code>|</code>, pas <code>;</code>, pour ne pas entrer en conflit avec le séparateur de colonnes).</td></tr>
                    <tr><td class="mono">prix_pro</td><td><span class="badge badge-neutral">non</span></td><td>Prix de vente professionnel (= prix particulier si vide).</td></tr>
                    <tr><td class="mono">code_barre</td><td><span class="badge badge-neutral">non</span></td><td>Code-barres — doit être unique dans le catalogue.</td></tr>
                    <tr><td class="mono">emplacement</td><td><span class="badge badge-neutral">non</span></td><td>Localisation en magasin (allée, rayon, casier…).</td></tr>
                    <tr><td class="mono">unite</td><td><span class="badge badge-neutral">non</span></td><td>Unité de suivi du stock (défaut : « unité »).</td></tr>
                    <tr><td class="mono">unite_vente / facteur_unite_vente</td><td><span class="badge badge-neutral">non</span></td><td>Unité de vente et son équivalence en unités de stock (défaut : 1).</td></tr>
                    <tr><td class="mono">unite_achat / facteur_unite_achat</td><td><span class="badge badge-neutral">non</span></td><td>Unité d'achat et son équivalence en unités de stock (défaut : 1).</td></tr>
                    <tr><td class="mono">seuil_alerte / stock_securite / point_commande / stock_max</td><td><span class="badge badge-neutral">non</span></td><td>Paramètres de réapprovisionnement.</td></tr>
                    <tr><td class="mono">suit_lots</td><td><span class="badge badge-neutral">non</span></td><td><code>oui</code>/<code>non</code> — gestion par lots avec péremption (défaut : non).</td></tr>
                    <tr><td class="mono">actif</td><td><span class="badge badge-neutral">non</span></td><td><code>oui</code>/<code>non</code> — visible en caisse et catalogue (défaut : oui).</td></tr>
                    <tr><td class="mono">stock_initial</td><td><span class="badge badge-neutral">non</span></td><td>Quantité physique de départ, pour un <strong>nouveau</strong> produit uniquement — pré-remplit un inventaire à valider après import.</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</x-layout>
