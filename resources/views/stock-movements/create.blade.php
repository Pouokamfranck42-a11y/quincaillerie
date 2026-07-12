<x-layout title="Nouveau mouvement de stock">
    <div class="page-head"><h1>Nouveau mouvement de stock</h1></div>

    <div class="card" style="max-width:560px" x-data="{ type: 'entree', productId: '', lotTrackedIds: @json($products->where('tracks_lots', true)->pluck('id')) }">
        <form method="POST" action="{{ route('stock-movements.store') }}">
            @csrf
            <div class="field">
                <label for="product_id">Produit</label>
                <select id="product_id" name="product_id" x-model="productId" required autofocus>
                    <option value="">— Choisir —</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}" @selected(old('product_id') == $product->id)>{{ $product->name }} ({{ $product->reference }})</option>
                    @endforeach
                </select>
                @error('product_id') <div class="error">{{ $message }}</div> @enderror
            </div>

            <div class="field">
                <label for="type">Type de mouvement</label>
                <select id="type" name="type" x-model="type" required>
                    <option value="entree">Entrée (réception, retour…)</option>
                    <option value="sortie">Sortie (casse, perte, usage interne…)</option>
                    <option value="ajustement">Ajustement d'inventaire</option>
                </select>
            </div>

            <div class="field" x-show="type === 'ajustement'" x-cloak>
                <label for="direction">Sens de l'ajustement</label>
                <select id="direction" name="direction">
                    <option value="augmente">Augmente le stock</option>
                    <option value="diminue">Diminue le stock</option>
                </select>
            </div>

            <div class="field" x-show="type === 'sortie'" x-cloak>
                <label for="subtype_sortie">Motif de sortie</label>
                <select name="subtype" id="subtype_sortie">
                    <option value="">— Non précisé —</option>
                    <option value="casse">Casse</option>
                    <option value="vol">Vol</option>
                    <option value="perime">Périmé</option>
                    <option value="consommation_interne">Consommation interne</option>
                </select>
            </div>

            <div class="field">
                <label for="quantity">Quantité</label>
                <input type="number" step="0.01" min="0.01" id="quantity" name="quantity" value="{{ old('quantity') }}" required>
                @error('quantity') <div class="error">{{ $message }}</div> @enderror
            </div>

            <div class="field" x-show="type === 'entree'" x-cloak>
                <label for="unit_cost">Coût unitaire (optionnel)</label>
                <input type="number" step="0.01" min="0" id="unit_cost" name="unit_cost" value="{{ old('unit_cost') }}">
                <div class="hint">Si renseigné, recalcule le prix d'achat moyen pondéré (CUMP) du produit.</div>
            </div>

            <template x-if="type === 'entree' && lotTrackedIds.includes(Number(productId))">
                <div class="field-row" x-cloak>
                    <div class="field">
                        <label for="lot_number">N° de lot</label>
                        <input type="text" id="lot_number" name="lot_number" value="{{ old('lot_number') }}">
                    </div>
                    <div class="field">
                        <label for="expiry_date">Date de péremption</label>
                        <input type="date" id="expiry_date" name="expiry_date" value="{{ old('expiry_date') }}">
                    </div>
                </div>
            </template>

            <div class="field">
                <label for="reason">Motif (optionnel)</label>
                <input type="text" id="reason" name="reason" value="{{ old('reason') }}" placeholder="Ex : casse en rayon, inventaire du 12/07…">
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Enregistrer</button>
                <a href="{{ route('stock-movements.index') }}" class="btn btn-ghost">Annuler</a>
            </div>
        </form>
    </div>
</x-layout>
