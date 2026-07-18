<x-layout title="Modifier le profil">
    <div class="page-head"><h1><i class="bi bi-shield-lock text-primary"></i> Modifier « {{ $role->name }} »</h1></div>

    @error('permissions') <div class="alert alert-crit" style="max-width:900px"><i class="bi bi-exclamation-triangle-fill"></i> <span>{{ $message }}</span></div> @enderror

    <div class="card" style="max-width:900px">
        <form method="POST" action="{{ route('roles.update', $role) }}">
            @csrf @method('PUT')
            <div class="field">
                <label for="name">Nom du profil</label>
                <input type="text" id="name" name="name" value="{{ old('name', $role->name) }}" class="@error('name') is-invalid @enderror" required autofocus>
                @error('name') <div class="error">{{ $message }}</div> @enderror
            </div>

            <div class="field">
                <label>Permissions</label>
                <x-permission-checklist :selected="$role->permissions->pluck('name')->all()" />
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Enregistrer</button>
                <a href="{{ route('roles.index') }}" class="btn btn-ghost">Annuler</a>
            </div>
        </form>
    </div>
</x-layout>
