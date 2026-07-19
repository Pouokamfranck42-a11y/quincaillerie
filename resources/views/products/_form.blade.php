<div class="field-row">
    <div class="field">
        <label for="reference">Référence</label>
        <input type="text" id="reference" name="reference" value="{{ old('reference', $product->reference ?? '') }}" required autofocus>
        @error('reference') <div class="error">{{ $message }}</div> @enderror
    </div>
    <div class="field">
        <label for="barcode">Code-barres</label>
        <input type="text" id="barcode" name="barcode" value="{{ old('barcode', $product->barcode ?? '') }}">
        @error('barcode') <div class="error">{{ $message }}</div> @enderror
        <x-barcode-scan target="barcode" />
    </div>
</div>
<div class="field-row">
    <div class="field">
        <label for="name">Nom</label>
        <input type="text" id="name" name="name" value="{{ old('name', $product->name ?? request('name', '')) }}" required>
        @error('name') <div class="error">{{ $message }}</div> @enderror
    </div>
    <div class="field">
        <label for="brand">Marque</label>
        <input type="text" id="brand" name="brand" value="{{ old('brand', $product->brand ?? '') }}">
    </div>
</div>
<div class="field">
    <label for="description">Description</label>
    <textarea id="description" name="description" rows="2">{{ old('description', $product->description ?? request('description', '')) }}</textarea>
    <button type="button" class="btn btn-sm" id="generate-description-btn" style="margin-top:6px">✨ Générer une description avec l'IA</button>
</div>
<div class="field">
    <label for="photo">Photo</label>
    @if (! empty($product) && $product->photo_path)
        <div style="margin-bottom:8px"><img src="{{ asset('storage/'.$product->photo_path) }}" alt="" style="max-width:120px; border-radius:var(--radius); border:1px solid var(--steel-200)"></div>
    @endif
    <input type="file" id="photo" name="photo" accept="image/*">
    @error('photo') <div class="error">{{ $message }}</div> @enderror
</div>

<div class="field-row">
    <div class="field">
        <label for="category_id">Catégorie</label>
        <select id="category_id" name="category_id">
            <option value="">— Aucune —</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected(old('category_id', $product->category_id ?? '') == $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="field">
        <label for="supplier_id">Fournisseur principal</label>
        <select id="supplier_id" name="supplier_id">
            <option value="">— Aucun —</option>
            @foreach ($suppliers as $supplier)
                <option value="{{ $supplier->id }}" @selected(old('supplier_id', $product->supplier_id ?? '') == $supplier->id)>{{ $supplier->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="field">
        <label for="supplier_sku">Référence chez ce fournisseur</label>
        <input type="text" id="supplier_sku" name="supplier_sku" value="{{ old('supplier_sku', $product->supplier_sku ?? '') }}">
    </div>
</div>

@php
    $initialAltSuppliers = old('alt_suppliers') ?? ($product->alternateSuppliers ?? collect())->map(fn ($s) => [
        'supplier_id' => $s->supplier_id, 'supplier_sku' => $s->supplier_sku, 'purchase_price' => $s->purchase_price,
    ])->values()->all();
@endphp
<div class="field" x-data="{ alts: @js($initialAltSuppliers) }">
    <label>Fournisseurs secondaires (optionnel)</label>
    <div class="hint" style="margin-bottom:8px">Solutions de repli si le fournisseur principal est en rupture.</div>
    <template x-for="(alt, index) in alts" :key="index">
        <div class="field-row" style="margin-bottom:8px; align-items:center;">
            <select :name="'alt_suppliers['+index+'][supplier_id]'" x-model="alt.supplier_id" style="flex:2">
                <option value="">— Fournisseur —</option>
                @foreach ($suppliers as $supplier)
                    <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                @endforeach
            </select>
            <input type="text" :name="'alt_suppliers['+index+'][supplier_sku]'" x-model="alt.supplier_sku" placeholder="Réf. fournisseur" style="flex:1">
            <input type="number" step="0.01" min="0" :name="'alt_suppliers['+index+'][purchase_price]'" x-model="alt.purchase_price" placeholder="Prix d'achat" style="flex:1">
            <button type="button" class="btn btn-sm btn-ghost" @click="alts.splice(index, 1)">✕</button>
        </div>
    </template>
    <button type="button" class="btn btn-sm" @click="alts.push({ supplier_id: '', supplier_sku: '', purchase_price: '' })">+ Ajouter un fournisseur secondaire</button>
</div>

<div class="field">
    <label for="location">Localisation en magasin</label>
    <input type="text" id="location" name="location" value="{{ old('location', $product->location ?? '') }}" placeholder="Ex : Allée 3 · Rayon B · Casier 12">
</div>

<h3>Variante / déclinaison</h3>
<div class="field">
    <label for="product_family_id">Famille de produit (optionnel)</label>
    <select id="product_family_id" name="product_family_id">
        <option value="">— Aucune (produit indépendant) —</option>
        @foreach ($families as $family)
            <option value="{{ $family->id }}" @selected(old('product_family_id', $product->product_family_id ?? '') == $family->id)>{{ $family->name }}</option>
        @endforeach
    </select>
    <div class="hint">Regroupe plusieurs variantes (taille, diamètre…) d'un même article sous une fiche commune.</div>
</div>

@php
    $initialAttrs = old('variant_attrs') ?? collect($product->variant_attributes ?? [])->map(fn ($v, $k) => ['key' => $k, 'value' => $v])->values()->all();
@endphp
<div class="field" x-data="{ attrs: @js(count($initialAttrs) ? $initialAttrs : [['key' => '', 'value' => '']]) }">
    <label>Attributs de la variante</label>
    <template x-for="(attr, index) in attrs" :key="index">
        <div class="field-row" style="margin-bottom:8px; align-items:center;">
            <input type="text" :name="'variant_attrs['+index+'][key]'" x-model="attr.key" placeholder="Ex : taille, diamètre, matériau" style="flex:1">
            <input type="text" :name="'variant_attrs['+index+'][value]'" x-model="attr.value" placeholder="Ex : M, 10mm, acier" style="flex:1">
            <button type="button" class="btn btn-sm btn-ghost" @click="attrs.splice(index, 1)">✕</button>
        </div>
    </template>
    <button type="button" class="btn btn-sm" @click="attrs.push({ key: '', value: '' })">+ Ajouter un attribut</button>
</div>

@php
    // La permission 'prix.modifier' ne restreint que la MODIFICATION d'un prix existant — à la
    // création, le prix reste requis pour obtenir une fiche produit valide (voir ProductController).
    $priceLocked = ! empty($product) && ! auth()->user()->can('prix.modifier');
@endphp
<h3>Prix</h3>
@if ($priceLocked)
    <div class="hint"><i class="bi bi-lock"></i> Vous n'avez pas la permission de modifier les prix — valeurs actuelles affichées, non modifiables.</div>
@endif
<div class="field-row">
    <div class="field">
        <label for="purchase_price">Prix d'achat (par unité de stock)</label>
        <input type="number" step="0.01" min="0" id="purchase_price" name="purchase_price" value="{{ old('purchase_price', $product->purchase_price ?? 0) }}" @disabled($priceLocked) required>
        <div class="hint">Recalculé automatiquement (CUMP) à chaque réception fournisseur.</div>
        @error('purchase_price') <div class="error">{{ $message }}</div> @enderror
    </div>
    <div class="field">
        <label for="sale_price">Prix de vente particulier</label>
        <input type="number" step="0.01" min="0" id="sale_price" name="sale_price" value="{{ old('sale_price', $product->sale_price ?? 0) }}" @disabled($priceLocked) required>
        @error('sale_price') <div class="error">{{ $message }}</div> @enderror
    </div>
    <div class="field">
        <label for="pro_price">Prix de vente professionnel</label>
        <input type="number" step="0.01" min="0" id="pro_price" name="pro_price" value="{{ old('pro_price', $product->pro_price ?? '') }}" @disabled($priceLocked) placeholder="= prix particulier si vide">
        @error('pro_price') <div class="error">{{ $message }}</div> @enderror
    </div>
</div>

<h3>Unités</h3>
<div class="field-row">
    <div class="field">
        <label for="unit">Unité de stock</label>
        <input type="text" id="unit" name="unit" value="{{ old('unit', $product->unit ?? 'unité') }}" required>
        <div class="hint">Unité dans laquelle le stock est suivi (mètre, kg, unité…).</div>
    </div>
</div>

@php $initialSoldByCut = (bool) old('sold_by_cut', $product->sold_by_cut ?? false); @endphp
<div class="field" x-data="{ soldByCut: {{ $initialSoldByCut ? 'true' : 'false' }} }">
    <label style="display:flex; align-items:center; gap:8px; font-weight:400;">
        <input type="checkbox" name="sold_by_cut" value="1" style="width:auto" x-model="soldByCut" @checked($initialSoldByCut)>
        Vendu à la découpe (câble, tuyau, chaîne…)
    </label>
    <div class="hint">La quantité vendue doit alors être un multiple exact du pas ci-dessous — en caisse comme en boutique en ligne.</div>
    <div class="field" x-show="soldByCut" style="margin-top:8px; max-width:220px">
        <label for="cut_step">Pas de découpe (en {{ old('unit', $product->unit ?? 'mètre') }})</label>
        <input type="number" step="0.001" min="0.001" id="cut_step" name="cut_step" value="{{ old('cut_step', $product->cut_step ?? 0.5) }}">
        <div class="hint">Ex : 0.5 → vendable par 0.5 m, 1 m, 1.5 m…</div>
        @error('cut_step') <div class="error">{{ $message }}</div> @enderror
    </div>
</div>
<div class="field-row">
    <div class="field">
        <label for="sale_unit">Unité de vente (optionnel)</label>
        <input type="text" id="sale_unit" name="sale_unit" value="{{ old('sale_unit', $product->sale_unit ?? '') }}" placeholder="Ex : boîte de 100…">
    </div>
    <div class="field">
        <label for="sale_unit_factor">Équivalence (unités de stock par unité de vente)</label>
        <input type="number" step="0.001" min="0.001" id="sale_unit_factor" name="sale_unit_factor" value="{{ old('sale_unit_factor', $product->sale_unit_factor ?? 1) }}" required>
    </div>
</div>
<div class="field-row">
    <div class="field">
        <label for="purchase_unit">Unité d'achat (optionnel)</label>
        <input type="text" id="purchase_unit" name="purchase_unit" value="{{ old('purchase_unit', $product->purchase_unit ?? '') }}" placeholder="Ex : rouleau, boîte de 100…">
    </div>
    <div class="field">
        <label for="purchase_unit_factor">Équivalence (unités de stock par unité d'achat)</label>
        <input type="number" step="0.001" min="0.001" id="purchase_unit_factor" name="purchase_unit_factor" value="{{ old('purchase_unit_factor', $product->purchase_unit_factor ?? 1) }}" required>
        <div class="hint">Ex : 1 rouleau = 50 mètres → mettre 50.</div>
    </div>
</div>

<h3>Paramètres de stock</h3>
<div class="field-row">
    <div class="field">
        <label for="low_stock_threshold">Seuil d'alerte</label>
        <input type="number" step="0.01" min="0" id="low_stock_threshold" name="low_stock_threshold" value="{{ old('low_stock_threshold', $product->low_stock_threshold ?? 5) }}" required>
    </div>
    <div class="field">
        <label for="security_stock">Stock de sécurité</label>
        <input type="number" step="0.01" min="0" id="security_stock" name="security_stock" value="{{ old('security_stock', $product->security_stock ?? 0) }}" required>
    </div>
</div>
<div class="field-row">
    <div class="field">
        <label for="reorder_point">Point de commande (optionnel)</label>
        <input type="number" step="0.01" min="0" id="reorder_point" name="reorder_point" value="{{ old('reorder_point', $product->reorder_point ?? '') }}" placeholder="= seuil d'alerte si vide">
    </div>
    <div class="field">
        <label for="max_stock">Stock maximum (optionnel)</label>
        <input type="number" step="0.01" min="0" id="max_stock" name="max_stock" value="{{ old('max_stock', $product->max_stock ?? '') }}">
    </div>
</div>

<div class="field">
    <label style="display:flex; align-items:center; gap:8px; font-weight:400;">
        <input type="checkbox" name="tracks_lots" value="1" style="width:auto" @checked(old('tracks_lots', $product->tracks_lots ?? false))>
        Gérer par lots avec date de péremption (peinture, colle, produits chimiques…)
    </label>
</div>
<div class="field">
    <label style="display:flex; align-items:center; gap:8px; font-weight:400;">
        <input type="checkbox" name="active" value="1" style="width:auto" @checked(old('active', $product->active ?? true))>
        Produit actif (visible en caisse et dans le catalogue)
    </label>
</div>
@can('ecommerce.publier')
    <div class="field">
        <label style="display:flex; align-items:center; gap:8px; font-weight:400;">
            <input type="checkbox" name="published_online" value="1" style="width:auto" @checked(old('published_online', $product->published_online ?? false))>
            Publier sur la boutique en ligne
        </label>
        <div class="hint">Les articles vendus à la découpe sont publiables normalement — la boutique respecte automatiquement le pas défini ci-dessus. Réfléchissez en revanche pour le très lourd/encombrant à livrer, pas encore géré par le tunnel de commande.</div>
    </div>
@endcan
