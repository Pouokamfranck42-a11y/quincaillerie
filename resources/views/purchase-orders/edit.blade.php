<x-layout :title="'Modifier la commande #'.$purchaseOrder->id">
    <div class="page-head"><h1><i class="bi bi-pencil-square text-primary"></i> Modifier la commande #{{ $purchaseOrder->id }}</h1></div>

    <div
        class="card"
        style="max-width:900px"
        x-data="{
            products: @json($products),
            lines: @json($purchaseOrder->lines->map(fn ($l) => ['id' => $l->id, 'product_id' => $l->product_id, 'quantity' => (float) $l->quantity, 'unit_price' => (float) $l->unit_price])),
            total() { return this.lines.reduce((sum, l) => sum + ((parseFloat(l.quantity) || 0) * (parseFloat(l.unit_price) || 0)), 0); }
        }"
    >
        <p class="muted mt-0">{{ $purchaseOrder->supplier->name }} — brouillon, modifiable avant d'être passée.</p>

        <form method="POST" action="{{ route('purchase-orders.update', $purchaseOrder) }}">
            @csrf
            @method('PUT')

            <template x-for="(line, index) in lines" :key="line.id">
                <div class="field-row" style="align-items:flex-end">
                    <input type="hidden" :name="'lines['+index+'][id]'" x-model="line.id">
                    <div class="field" style="flex:2">
                        <label>Produit</label>
                        <select :name="'lines['+index+'][product_id]'" x-model="line.product_id" required>
                            <template x-for="p in products" :key="p.id">
                                <option :value="p.id" x-text="p.name + ' (' + p.reference + ')'"></option>
                            </template>
                        </select>
                    </div>
                    <div class="field">
                        <label>Quantité</label>
                        <input type="number" step="0.01" min="0.01" :name="'lines['+index+'][quantity]'" x-model="line.quantity" required>
                    </div>
                    <div class="field">
                        <label>Prix unitaire</label>
                        <input type="number" step="0.01" min="0" :name="'lines['+index+'][unit_price]'" x-model="line.unit_price" required>
                    </div>
                </div>
            </template>

            <div class="cart-totals" style="margin-top:20px">
                <div class="row total"><span>Total commande</span><span x-text="total().toLocaleString('fr-FR') + ' FCFA'"></span></div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Enregistrer les modifications</button>
                <a href="{{ route('purchase-orders.show', $purchaseOrder) }}" class="btn btn-ghost">Annuler</a>
            </div>
        </form>
    </div>
</x-layout>
