<div class="field">
    <label for="name">Nom de la famille</label>
    <input type="text" id="name" name="name" value="{{ old('name', $family->name ?? '') }}" required autofocus placeholder="Ex : Vis à bois, Tuyau PVC évacuation…">
    @error('name') <div class="error">{{ $message }}</div> @enderror
</div>
<div class="field">
    <label for="category_id">Catégorie</label>
    <select id="category_id" name="category_id">
        <option value="">— Aucune —</option>
        @foreach ($categories as $category)
            <option value="{{ $category->id }}" @selected(old('category_id', $family->category_id ?? '') == $category->id)>{{ $category->name }}</option>
        @endforeach
    </select>
</div>
<div class="field">
    <label for="description">Description</label>
    <textarea id="description" name="description" rows="2">{{ old('description', $family->description ?? '') }}</textarea>
</div>
