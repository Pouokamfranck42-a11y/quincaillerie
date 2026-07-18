<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Créer un compte — Boutique Quincaillerie</title>
        <link rel="stylesheet" href="{{ asset('css/vendor/bootstrap.min.css') }}">
        <link rel="stylesheet" href="{{ asset('css/vendor/bootstrap-icons.min.css') }}">
        <link rel="stylesheet" href="{{ asset('css/theme.css') }}">
    </head>
    <body>
        <div class="auth-wrap">
            <div class="auth-card">
                <a href="{{ route('shop.catalog.index') }}" class="sidebar-brand" style="text-decoration:none"><span class="dot"></span> Quincaillerie</a>
                <p class="mt-0">Créez votre compte pour commander en ligne.</p>

                @if ($errors->any())
                    <div class="alert alert-crit"><i class="bi bi-exclamation-triangle-fill"></i> <span>{{ $errors->first() }}</span></div>
                @endif

                <form method="POST" action="{{ route('shop.register') }}">
                    @csrf
                    <div class="field">
                        <label for="name"><i class="bi bi-person me-1"></i> Nom complet</label>
                        <input type="text" id="name" name="name" value="{{ old('name') }}" required autofocus>
                    </div>
                    <div class="field">
                        <label for="email"><i class="bi bi-envelope me-1"></i> Adresse e-mail</label>
                        <input type="email" id="email" name="email" value="{{ old('email') }}" required>
                        <div class="hint">Si vous avez déjà acheté en magasin avec cette adresse, votre fiche client sera reliée automatiquement.</div>
                    </div>
                    <div class="field">
                        <label for="phone"><i class="bi bi-telephone me-1"></i> Téléphone (optionnel)</label>
                        <input type="text" id="phone" name="phone" value="{{ old('phone') }}">
                    </div>
                    <div class="field">
                        <label for="password"><i class="bi bi-lock me-1"></i> Mot de passe</label>
                        <input type="password" id="password" name="password" minlength="8" required>
                    </div>
                    <div class="field">
                        <label for="password_confirmation"><i class="bi bi-lock me-1"></i> Confirmer le mot de passe</label>
                        <input type="password" id="password_confirmation" name="password_confirmation" minlength="8" required>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary" style="width:100%"><i class="bi bi-person-plus"></i> Créer mon compte</button>
                    </div>
                </form>

                <p class="muted" style="text-align:center; margin-top:16px">Déjà un compte ? <a href="{{ route('shop.login') }}">Se connecter</a></p>
            </div>
        </div>
    </body>
</html>
