<div class="field">
    <label for="name">Nom</label>
    <input type="text" id="name" name="name" value="{{ old('name', $warehouse->name ?? '') }}" required autofocus>
    @error('name') <div class="error">{{ $message }}</div> @enderror
</div>
<div class="field">
    <label for="address">Adresse</label>
    <textarea id="address" name="address" rows="2">{{ old('address', $warehouse->address ?? '') }}</textarea>
</div>
