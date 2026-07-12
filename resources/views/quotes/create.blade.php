<x-layout title="Nouveau devis">
    <div class="page-head"><h1>Nouveau devis</h1></div>

    <div
        class="card"
        style="max-width:820px"
        x-data="{
            products: @json($products),
            customers: @json($customers),
            customerId: '',
            lines: [{ product_id: '', quantity: 1, unit_price: 0 }],
            isPro() { const c = this.customers.find(c => c.id == this.customerId); return c && c.type === 'professionnel'; },
            priceFor(product) { return (this.isPro() && product.pro_price) ? parseFloat(product.pro_price) : parseFloat(product.sale_price); },
            addLine() { this.lines.push({ product_id: '', quantity: 1, unit_price: 0 }); },
            removeLine(i) { if (this.lines.length > 1) this.lines.splice(i, 1); },
            onProductChange(line) {
                const p = this.products.find(p => p.id == line.product_id);
                if (p) { line.unit_price = this.priceFor(p); }
            },
            onCustomerChange() {
                this.lines.forEach(line => {
                    const p = this.products.find(p => p.id == line.product_id);
                    if (p) { line.unit_price = this.priceFor(p); }
                });
            },
            subtotal() { return this.lines.reduce((sum, l) => sum + ((parseFloat(l.quantity) || 0) * (parseFloat(l.unit_price) || 0)), 0); },
            tax() { return Math.round(this.subtotal() * 0.18 * 100) / 100; },
            total() { return this.subtotal() + this.tax(); }
        }"
    >
        <form method="POST" action="{{ route('quotes.store') }}">
            @csrf
            <div class="field">
                <label for="customer_id">Client (optionnel)</label>
                <select id="customer_id" name="customer_id" x-model="customerId" @change="onCustomerChange()">
                    <option value="">Client de passage</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->id }}">{{ $customer->name }} @if($customer->type === 'professionnel') (pro) @endif</option>
                    @endforeach
                </select>
            </div>

            <h3>Lignes du devis</h3>
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
                        <label>Quantité</label>
                        <input type="number" step="0.01" min="0.01" :name="'lines['+index+'][quantity]'" x-model="line.quantity" required>
                    </div>
                    <div class="field">
                        <label>Prix unitaire</label>
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
                <div class="row"><span>Sous-total</span><span x-text="subtotal().toLocaleString('fr-FR')"></span></div>
                <div class="row"><span>TVA (18%)</span><span x-text="tax().toLocaleString('fr-FR')"></span></div>
                <div class="row total"><span>Total</span><span x-text="total().toLocaleString('fr-FR') + ' FCFA'"></span></div>
            </div>

            <div class="field-row" style="margin-top:16px">
                <div class="field">
                    <label for="valid_until">Valable jusqu'au (optionnel)</label>
                    <input type="date" id="valid_until" name="valid_until">
                </div>
            </div>
            <div class="field">
                <label for="notes">Notes (optionnel)</label>
                <textarea id="notes" name="notes" rows="2"></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Créer le devis</button>
                <a href="{{ route('quotes.index') }}" class="btn btn-ghost">Annuler</a>
            </div>
        </form>
    </div>
</x-layout>
