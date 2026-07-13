<x-layout title="Nouvelle catégorie">
    <div class="page-head"><h1><i class="bi bi-plus-lg text-primary"></i> Nouvelle catégorie</h1></div>

    <div class="card" style="max-width:520px">
        <form method="POST" action="{{ route('categories.store') }}">
            @csrf
            <div class="field">
                <label for="name">Nom</label>
                <input type="text" id="name" name="name" value="{{ old('name') }}" required autofocus>
                @error('name') <div class="error">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label for="parent_id">Catégorie parente (optionnel)</label>
                <select id="parent_id" name="parent_id">
                    <option value="">— Aucune —</option>
                    @foreach ($parents as $parent)
                        <option value="{{ $parent->id }}" @selected(old('parent_id') == $parent->id)>{{ $parent->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Créer</button>
                <a href="{{ route('categories.index') }}" class="btn btn-ghost">Annuler</a>
            </div>
        </form>
    </div>
</x-layout>
