<x-layout title="Modifier l'utilisateur">
    <div class="page-head"><h1>Modifier « {{ $user->name }} »</h1></div>

    <div class="card" style="max-width:480px">
        <form method="POST" action="{{ route('users.update', $user) }}">
            @csrf @method('PUT')
            <div class="field">
                <label for="name">Nom</label>
                <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}" required autofocus>
                @error('name') <div class="error">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label for="email">E-mail</label>
                <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" required>
                @error('email') <div class="error">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label for="password">Nouveau mot de passe (laisser vide pour ne pas changer)</label>
                <input type="password" id="password" name="password">
                @error('password') <div class="error">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label for="role">Rôle</label>
                <select id="role" name="role" required>
                    @foreach (['caissier' => 'Caissier', 'magasinier' => 'Magasinier', 'admin' => 'Admin'] as $value => $label)
                        <option value="{{ $value }}" @selected($user->hasRole($value))>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Enregistrer</button>
                <a href="{{ route('users.index') }}" class="btn btn-ghost">Annuler</a>
            </div>
        </form>
    </div>
</x-layout>
