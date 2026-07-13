<x-layout title="Vérifier la facture importée">
    <div class="page-head"><h1><i class="bi bi-file-earmark-check text-primary"></i> Vérifier la facture importée</h1></div>

    @if (! $supplierGuessId && $supplierGuessName)
        <div class="alert alert-crit"><i class="bi bi-exclamation-triangle-fill"></i> <span>Fournisseur « {{ $supplierGuessName }} » non trouvé dans le catalogue — choisis-le manuellement ci-dessous.</span></div>
    @endif

    <div
        class="card"
        style="max-width:900px"
        x-data="{
            products: @json($products),
            lines: @json($initialLines),
            addLine() { this.lines.push({ description: '', product_id: '', quantity: 1, unit_price: 0, matched: false }); },
            removeLine(i) { if (this.lines.length > 1) this.lines.splice(i, 1); },
            total() { return this.lines.reduce((sum, l) => sum + ((parseFloat(l.quantity) || 0) * (parseFloat(l.unit_price) || 0)), 0); }
        }"
    >
        <p class="muted mt-0">Vérifie et corrige chaque ligne avant d'enregistrer le brouillon — rien n'est encore créé.</p>

        <form method="POST" action="{{ route('purchase-orders.import-invoice.store') }}">
            @csrf
            <div class="field">
                <label for="supplier_id">Fournisseur</label>
                <select id="supplier_id" name="supplier_id" required>
                    <option value="">— Choisir —</option>
                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" @selected($supplierGuessId === $supplier->id)>{{ $supplier->name }}</option>
                    @endforeach
                </select>
                @error('supplier_id') <div class="error">{{ $message }}</div> @enderror
            </div>

            <h3>Lignes extraites de la facture</h3>
            <template x-for="(line, index) in lines" :key="index">
                <div class="field-row" style="align-items:flex-end">
                    <div class="field" style="flex:2">
                        <label x-text="'Produit' + (line.description ? ' — lu : « ' + line.description + ' »' : '')"></label>
                        <select :name="'lines['+index+'][product_id]'" x-model="line.product_id" required>
                            <option value="">— Non rapproché : choisir —</option>
                            <template x-for="p in products" :key="p.id">
                                <option :value="p.id" x-text="p.name + ' (' + p.reference + ')'"></option>
                            </template>
                        </select>
                        <div class="hint" x-show="!line.matched" style="color:var(--warn)">Non rapproché automatiquement — vérifie le choix.</div>
                    </div>
                    <div class="field">
                        <label>Quantité</label>
                        <input type="number" step="0.01" min="0.01" :name="'lines['+index+'][quantity]'" x-model="line.quantity" required>
                    </div>
                    <div class="field">
                        <label>Prix unitaire</label>
                        <input type="number" step="0.01" min="0" :name="'lines['+index+'][unit_price]'" x-model="line.unit_price" required>
                    </div>
                    <div class="field" style="flex:0">
                        <button type="button" class="btn btn-sm btn-danger" @click="removeLine(index)"><i class="bi bi-x-lg"></i></button>
                    </div>
                </div>
            </template>

            <button type="button" class="btn btn-sm" @click="addLine()"><i class="bi bi-plus-lg"></i> Ajouter une ligne</button>
            @error('lines') <div class="error"><i class="bi bi-exclamation-circle"></i> {{ $message }}</div> @enderror

            <div class="cart-totals" style="margin-top:20px">
                <div class="row total"><span>Total commande</span><span x-text="total().toLocaleString('fr-FR') + ' FCFA'"></span></div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Enregistrer le brouillon</button>
                <a href="{{ route('purchase-orders.index') }}" class="btn btn-ghost">Annuler</a>
            </div>
        </form>
    </div>
</x-layout>
