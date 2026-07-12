<x-layout title="Nouveau comptage">
    <div class="page-head"><h1>Nouveau comptage d'inventaire</h1></div>

    <div class="card" style="max-width:560px" x-data="{ type: 'tournant' }">
        <form method="POST" action="{{ route('inventory-counts.store') }}">
            @csrf
            <div class="field">
                <label for="warehouse_id">Entrepôt</label>
                <select id="warehouse_id" name="warehouse_id" required>
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="type">Type de comptage</label>
                <select id="type" name="type" x-model="type" required>
                    <option value="tournant">Tournant (une catégorie)</option>
                    <option value="complet">Complet (tout le catalogue actif)</option>
                </select>
            </div>

            <div class="field" x-show="type === 'tournant'" x-cloak>
                <label for="category_id">Catégorie</label>
                <select id="category_id" name="category_id">
                    <option value="">— Toutes —</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="notes">Notes (optionnel)</label>
                <textarea id="notes" name="notes" rows="2"></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Créer le comptage</button>
                <a href="{{ route('inventory-counts.index') }}" class="btn btn-ghost">Annuler</a>
            </div>
        </form>
    </div>
</x-layout>
