<x-layout title="Modifier l'utilisateur">
    <div class="page-head"><h1><i class="bi bi-pencil-square text-primary"></i> Modifier « {{ $user->name }} »</h1></div>

    @error('permissions') <div class="alert alert-crit" style="max-width:640px"><i class="bi bi-exclamation-triangle-fill"></i> <span>{{ $message }}</span></div> @enderror

    <div class="card" style="max-width:640px">
        <form method="POST" action="{{ route('users.update', $user) }}">
            @csrf @method('PUT')
            <div class="field">
                <label for="name">Nom</label>
                <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}" class="@error('name') is-invalid @enderror" required autofocus>
                @error('name') <div class="error">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label for="email">E-mail</label>
                <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" class="@error('email') is-invalid @enderror" required>
                @error('email') <div class="error">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label for="password">Nouveau mot de passe (laisser vide pour ne pas changer)</label>
                <input type="password" id="password" name="password" class="@error('password') is-invalid @enderror">
                @error('password') <div class="error">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label for="role">Profil (optionnel)</label>
                <select id="role" name="role">
                    <option value="">Aucun profil — permissions individuelles uniquement</option>
                    @foreach ($roles as $r)
                        <option value="{{ $r->name }}" @selected(old('role', $user->roles->pluck('name')->first()) === $r->name)>{{ $r->name }}</option>
                    @endforeach
                </select>
                <div class="hint">Les permissions ci-dessous s'ajoutent à celles du profil choisi.</div>
            </div>

            <div class="field">
                <label>Permissions individuelles supplémentaires</label>
                <x-permission-checklist :selected="$user->permissions->pluck('name')->all()" />
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Enregistrer</button>
                <a href="{{ route('users.index') }}" class="btn btn-ghost">Annuler</a>
            </div>
        </form>
    </div>
</x-layout>
