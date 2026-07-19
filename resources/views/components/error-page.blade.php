@props(['title', 'message', 'icon' => 'bi-exclamation-triangle'])
<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>{{ $title }} — Quincaillerie</title>
        <link rel="stylesheet" href="{{ asset('css/vendor/bootstrap.min.css') }}">
        <link rel="stylesheet" href="{{ asset('css/vendor/bootstrap-icons.min.css') }}">
        <link rel="stylesheet" href="{{ asset('css/theme.css') }}">
    </head>
    <body>
        <div class="auth-wrap">
            <div class="auth-card" style="text-align:center">
                <div class="sidebar-brand" style="justify-content:center"><span class="dot"></span> Quincaillerie</div>
                <p style="font-size:44px; margin:12px 0 4px"><i class="bi {{ $icon }} text-danger"></i></p>
                <h1 style="font-size:20px; margin-bottom:8px">{{ $title }}</h1>
                <p class="muted">{{ $message }}</p>
                <a href="{{ url('/') }}" class="btn btn-primary" style="width:100%"><i class="bi bi-house"></i> Retour à l'accueil</a>
            </div>
        </div>
    </body>
</html>
