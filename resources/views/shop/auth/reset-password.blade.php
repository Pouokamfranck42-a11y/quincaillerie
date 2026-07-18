<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Réinitialiser le mot de passe — Boutique Quincaillerie</title>
        <link rel="stylesheet" href="{{ asset('css/vendor/bootstrap.min.css') }}">
        <link rel="stylesheet" href="{{ asset('css/vendor/bootstrap-icons.min.css') }}">
        <link rel="stylesheet" href="{{ asset('css/theme.css') }}">
    </head>
    <body>
        <div class="auth-wrap">
            <div class="auth-card">
                <a href="{{ route('shop.catalog.index') }}" class="sidebar-brand" style="text-decoration:none"><span class="dot"></span> Quincaillerie</a>
                <p class="mt-0">Choisissez un nouveau mot de passe.</p>

                @if ($errors->any())
                    <div class="alert alert-crit"><i class="bi bi-exclamation-triangle-fill"></i> <span>{{ $errors->first() }}</span></div>
                @endif

                <form method="POST" action="{{ route('shop.password.update') }}">
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">
                    <div class="field">
                        <label for="email"><i class="bi bi-envelope me-1"></i> Adresse e-mail</label>
                        <input type="email" id="email" name="email" value="{{ old('email', $email) }}" required autofocus>
                    </div>
                    <div class="field">
                        <label for="password"><i class="bi bi-lock me-1"></i> Nouveau mot de passe</label>
                        <input type="password" id="password" name="password" minlength="8" required>
                    </div>
                    <div class="field">
                        <label for="password_confirmation"><i class="bi bi-lock me-1"></i> Confirmer le mot de passe</label>
                        <input type="password" id="password_confirmation" name="password_confirmation" minlength="8" required>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary" style="width:100%"><i class="bi bi-check-circle"></i> Réinitialiser le mot de passe</button>
                    </div>
                </form>
            </div>
        </div>
    </body>
</html>
