<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Connexion — Quincaillerie</title>
        <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    </head>
    <body>
        <div class="auth-wrap">
            <div class="auth-card">
                <div class="sidebar-brand"><span class="dot"></span> Quincaillerie</div>
                <p class="mt-0">Connectez-vous pour accéder à la caisse et à la gestion du magasin.</p>

                @if ($errors->any())
                    <div class="alert alert-crit">{{ $errors->first() }}</div>
                @endif

                <form method="POST" action="{{ route('login') }}">
                    @csrf
                    <div class="field">
                        <label for="email">Adresse e-mail</label>
                        <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus>
                    </div>
                    <div class="field">
                        <label for="password">Mot de passe</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary" style="width:100%">Se connecter</button>
                    </div>
                </form>
            </div>
        </div>
    </body>
</html>
