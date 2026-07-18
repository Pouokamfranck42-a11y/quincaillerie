<x-layout title="Nouveau profil">
    <div class="page-head"><h1><i class="bi bi-shield-plus text-primary"></i> Nouveau profil</h1></div>

    <div class="card" style="max-width:900px">
        <form method="POST" action="{{ route('roles.store') }}">
            @csrf
            <div class="field">
                <label for="name">Nom du profil</label>
                <input type="text" id="name" name="name" value="{{ old('name') }}" class="@error('name') is-invalid @enderror" required autofocus placeholder="ex. Vendeur">
                @error('name') <div class="error">{{ $message }}</div> @enderror
            </div>

            <div class="field">
                <label>Permissions</label>
                <x-permission-checklist :selected="[]" />
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Créer</button>
                <a href="{{ route('roles.index') }}" class="btn btn-ghost">Annuler</a>
            </div>
        </form>
    </div>
</x-layout>
