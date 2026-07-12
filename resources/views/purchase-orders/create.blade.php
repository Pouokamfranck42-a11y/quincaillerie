<x-layout title="Nouvelle commande fournisseur">
    <div class="page-head"><h1>Nouvelle commande fournisseur</h1></div>

    <div
        class="card"
        style="max-width:820px"
        x-data="{
            products: @json($products),
            lines: [{ product_id: '', quantity: 1, unit_price: 0 }],
            addLine() { this.lines.push({ product_id: '', quantity: 1, unit_price: 0 }); },
            removeLine(i) { if (this.lines.length > 1) this.lines.splice(i, 1); },
            productOf(line) { return this.products.find(p => p.id == line.product_id); },
            purchaseUnitLabel(line) { const p = this.productOf(line); return p ? (p.purchase_unit || p.unit) : ''; },
            stockEquivalent(line) {
                const p = this.productOf(line);
                if (!p) return '';
                const factor = parseFloat(p.purchase_unit_factor) || 1;
                const qty = (parseFloat(line.quantity) || 0) * factor;
                return p.purchase_unit ? ('= ' + qty.toLocaleString('fr-FR') + ' ' + p.unit) : '';
            },
            onProductChange(line) {
                const p = this.productOf(line);
                if (p) {
                    const factor = parseFloat(p.purchase_unit_factor) || 1;
                    line.unit_price = Math.round(parseFloat(p.purchase_price) * factor * 100) / 100;
                }
            },
            total() { return this.lines.reduce((sum, l) => sum + ((parseFloat(l.quantity) || 0) * (parseFloat(l.unit_price) || 0)), 0); }
        }"
    >
        <form method="POST" action="{{ route('purchase-orders.store') }}">
            @csrf
            <div class="field">
                <label for="supplier_id">Fournisseur</label>
                <select id="supplier_id" name="supplier_id" required>
                    <option value="">— Choisir —</option>
                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                    @endforeach
                </select>
                @error('supplier_id') <div class="error">{{ $message }}</div> @enderror
            </div>

            <h3>Lignes de commande</h3>
            <template x-for="(line, index) in lines" :key="index">
                <div class="field-row" style="align-items:flex-end">
                    <div class="field" style="flex:2">
                        <label>Produit</label>
                        <select :name="'lines['+index+'][product_id]'" x-model="line.product_id" @change="onProductChange(line)" required>
                            <option value="">— Choisir —</option>
                            <template x-for="p in products" :key="p.id">
                                <option :value="p.id" x-text="p.name + ' (' + p.reference + ')'"></option>
                            </template>
                        </select>
                    </div>
                    <div class="field">
                        <label x-text="'Quantité' + (purchaseUnitLabel(line) ? (' (' + purchaseUnitLabel(line) + ')') : '')"></label>
                        <input type="number" step="0.01" min="0.01" :name="'lines['+index+'][quantity]'" x-model="line.quantity" required>
                        <div class="hint" x-text="stockEquivalent(line)"></div>
                    </div>
                    <div class="field">
                        <label x-text="'Prix / ' + (purchaseUnitLabel(line) || 'unité')"></label>
                        <input type="number" step="0.01" min="0" :name="'lines['+index+'][unit_price]'" x-model="line.unit_price" required>
                    </div>
                    <div class="field" style="flex:0">
                        <button type="button" class="btn btn-sm btn-danger" @click="removeLine(index)">✕</button>
                    </div>
                </div>
            </template>

            <button type="button" class="btn btn-sm" @click="addLine()">+ Ajouter une ligne</button>
            @error('lines') <div class="error" style="margin-top:8px">{{ $message }}</div> @enderror

            <div class="cart-totals" style="margin-top:20px">
                <div class="row total"><span>Total commande</span><span x-text="total().toLocaleString('fr-FR') + ' FCFA'"></span></div>
            </div>

            <div class="field" style="margin-top:16px">
                <label for="extra_costs">Frais annexes (transport, douane, manutention…)</label>
                <input type="number" step="0.01" min="0" id="extra_costs" name="extra_costs" value="0">
                <div class="hint">Réparti au prorata sur le coût de revient de chaque ligne à la réception.</div>
            </div>

            <div class="field">
                <label for="notes">Notes (optionnel)</label>
                <textarea id="notes" name="notes" rows="2"></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Créer la commande</button>
                <a href="{{ route('purchase-orders.index') }}" class="btn btn-ghost">Annuler</a>
            </div>
        </form>
    </div>
</x-layout>
