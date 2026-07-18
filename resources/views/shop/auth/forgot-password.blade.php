<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Mot de passe oublié — Boutique Quincaillerie</title>
        <link rel="stylesheet" href="{{ asset('css/vendor/bootstrap.min.css') }}">
        <link rel="stylesheet" href="{{ asset('css/vendor/bootstrap-icons.min.css') }}">
        <link rel="stylesheet" href="{{ asset('css/theme.css') }}">
    </head>
    <body>
        <div class="auth-wrap">
            <div class="auth-card">
                <a href="{{ route('shop.catalog.index') }}" class="sidebar-brand" style="text-decoration:none"><span class="dot"></span> Quincaillerie</a>
                <p class="mt-0">Indiquez votre adresse e-mail pour recevoir un lien de réinitialisation.</p>

                @if (session('status'))
                    <div class="alert alert-good"><i class="bi bi-check-circle-fill"></i> <span>{{ session('status') }}</span></div>
                @endif

                @if ($errors->any())
                    <div class="alert alert-crit"><i class="bi bi-exclamation-triangle-fill"></i> <span>{{ $errors->first() }}</span></div>
                @endif

                <form method="POST" action="{{ route('shop.password.email') }}">
                    @csrf
                    <div class="field">
                        <label for="email"><i class="bi bi-envelope me-1"></i> Adresse e-mail</label>
                        <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary" style="width:100%"><i class="bi bi-send"></i> Envoyer le lien de réinitialisation</button>
                    </div>
                </form>

                <p class="muted" style="text-align:center; margin-top:16px"><a href="{{ route('shop.login') }}">Retour à la connexion</a></p>
            </div>
        </div>
    </body>
</html>
