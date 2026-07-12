<x-layout title="Nouveau transfert de stock">
    <div class="page-head"><h1>Nouveau transfert de stock</h1></div>

    @if ($warehouses->count() < 2)
        <div class="card">
            <p class="mt-0">Il faut au moins deux entrepôts pour créer un transfert. <a href="{{ route('warehouses.create') }}">Créer un entrepôt</a>.</p>
        </div>
    @else
        <div
            class="card"
            style="max-width:820px"
            x-data="{
                products: @json($products),
                lines: [{ product_id: '', quantity: 1 }],
                addLine() { this.lines.push({ product_id: '', quantity: 1 }); },
                removeLine(i) { if (this.lines.length > 1) this.lines.splice(i, 1); }
            }"
        >
            <form method="POST" action="{{ route('stock-transfers.store') }}">
                @csrf
                <div class="field-row">
                    <div class="field">
                        <label for="from_warehouse_id">Depuis</label>
                        <select id="from_warehouse_id" name="from_warehouse_id" required>
                            <option value="">— Choisir —</option>
                            @foreach ($warehouses as $warehouse)
                                <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                            @endforeach
                        </select>
                        @error('from_warehouse_id') <div class="error">{{ $message }}</div> @enderror
                    </div>
                    <div class="field">
                        <label for="to_warehouse_id">Vers</label>
                        <select id="to_warehouse_id" name="to_warehouse_id" required>
                            <option value="">— Choisir —</option>
                            @foreach ($warehouses as $warehouse)
                                <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                            @endforeach
                        </select>
                        @error('to_warehouse_id') <div class="error">{{ $message }}</div> @enderror
                    </div>
                </div>

                <h3>Produits à transférer</h3>
                <template x-for="(line, index) in lines" :key="index">
                    <div class="field-row" style="align-items:flex-end">
                        <div class="field" style="flex:2">
                            <label>Produit</label>
                            <select :name="'lines['+index+'][product_id]'" x-model="line.product_id" required>
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
                        <div class="field" style="flex:0">
                            <button type="button" class="btn btn-sm btn-danger" @click="removeLine(index)">✕</button>
                        </div>
                    </div>
                </template>
                <button type="button" class="btn btn-sm" @click="addLine()">+ Ajouter un produit</button>
                @error('lines') <div class="error" style="margin-top:8px">{{ $message }}</div> @enderror

                <div class="field" style="margin-top:16px">
                    <label for="notes">Notes (optionnel)</label>
                    <textarea id="notes" name="notes" rows="2"></textarea>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Créer le transfert</button>
                    <a href="{{ route('stock-transfers.index') }}" class="btn btn-ghost">Annuler</a>
                </div>
            </form>
        </div>
    @endif
</x-layout>
