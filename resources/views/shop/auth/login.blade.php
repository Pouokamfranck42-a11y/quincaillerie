<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Connexion — Boutique Quincaillerie</title>
        <link rel="stylesheet" href="{{ asset('css/vendor/bootstrap.min.css') }}">
        <link rel="stylesheet" href="{{ asset('css/vendor/bootstrap-icons.min.css') }}">
        <link rel="stylesheet" href="{{ asset('css/theme.css') }}">
    </head>
    <body>
        <div class="auth-wrap">
            <div class="auth-card">
                <a href="{{ route('shop.catalog.index') }}" class="sidebar-brand" style="text-decoration:none"><span class="dot"></span> Quincaillerie</a>
                <p class="mt-0">Connectez-vous pour passer commande et suivre vos livraisons.</p>

                @if ($errors->any())
                    <div class="alert alert-crit"><i class="bi bi-exclamation-triangle-fill"></i> <span>{{ $errors->first() }}</span></div>
                @endif

                <form method="POST" action="{{ route('shop.login') }}">
                    @csrf
                    <div class="field">
                        <label for="email"><i class="bi bi-envelope me-1"></i> Adresse e-mail</label>
                        <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus>
                    </div>
                    <div class="field">
                        <label for="password"><i class="bi bi-lock me-1"></i> Mot de passe</label>
                        <input type="password" id="password" name="password" required>
                        <div class="hint"><a href="{{ route('shop.password.request') }}">Mot de passe oublié ?</a></div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary" style="width:100%"><i class="bi bi-box-arrow-in-right"></i> Se connecter</button>
                    </div>
                </form>

                <p class="muted" style="text-align:center; margin-top:16px">Pas encore de compte ? <a href="{{ route('shop.register') }}">Créer un compte</a></p>
            </div>
        </div>
    </body>
</html>
