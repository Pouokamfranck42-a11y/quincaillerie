<x-layout title="Nouvel utilisateur">
    <div class="page-head"><h1><i class="bi bi-person-badge text-primary"></i> Nouvel utilisateur</h1></div>

    <div class="card" style="max-width:480px">
        <form method="POST" action="{{ route('users.store') }}">
            @csrf
            <div class="field">
                <label for="name">Nom</label>
                <input type="text" id="name" name="name" value="{{ old('name') }}" class="@error('name') is-invalid @enderror" required autofocus>
                @error('name') <div class="error">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label for="email">E-mail</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" class="@error('email') is-invalid @enderror" required>
                @error('email') <div class="error">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label for="password">Mot de passe</label>
                <input type="password" id="password" name="password" class="@error('password') is-invalid @enderror" required>
                @error('password') <div class="error">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label for="role">Rôle</label>
                <select id="role" name="role" required>
                    <option value="caissier">Caissier</option>
                    <option value="magasinier">Magasinier</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Créer</button>
                <a href="{{ route('users.index') }}" class="btn btn-ghost">Annuler</a>
            </div>
        </form>
    </div>
</x-layout>
