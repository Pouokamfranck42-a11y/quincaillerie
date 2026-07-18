@props(['title' => 'Boutique'])
<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $title }} — Quincaillerie</title>
        <link rel="stylesheet" href="{{ asset('css/vendor/bootstrap.min.css') }}">
        <link rel="stylesheet" href="{{ asset('css/vendor/bootstrap-icons.min.css') }}">
        <link rel="stylesheet" href="{{ asset('css/theme.css') }}">
        <link rel="stylesheet" href="{{ asset('css/shop.css') }}">
    </head>
    <body class="shop-body">
        @php $cartCount = collect(session('shop_cart', []))->count(); @endphp

        <header class="shop-navbar">
            <div class="shop-navbar-inner">
                <a href="{{ route('shop.catalog.index') }}" class="shop-brand"><span class="dot"></span> Quincaillerie</a>

                <form method="GET" action="{{ route('shop.catalog.index') }}" class="shop-search">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                        <input type="search" name="q" class="border-start-0 ps-0" value="{{ request('q') }}" placeholder="Rechercher un article…" aria-label="Rechercher un article">
                    </div>
                </form>

                <nav class="shop-nav-actions">
                    <a href="{{ route('shop.assistant') }}" class="shop-icon-link" title="Besoin d'un conseil ?">
                        <i class="bi bi-tools"></i>
                    </a>
                    <a href="{{ route('shop.cart.index') }}" class="shop-icon-link" title="Panier">
                        <i class="bi bi-cart3"></i>
                        @if ($cartCount > 0)
                            <span class="badge badge-crit shop-cart-badge">{{ $cartCount }}</span>
                        @endif
                    </a>

                    @auth('customer')
                        <a href="{{ route('shop.notifications.index') }}" class="shop-icon-link" title="Notifications">
                            <i class="bi bi-bell"></i>
                            @php $unreadCount = auth('customer')->user()->unreadNotifications()->count(); @endphp
                            @if ($unreadCount > 0)
                                <span class="badge badge-crit shop-cart-badge">{{ $unreadCount }}</span>
                            @endif
                        </a>

                        <div class="dropdown">
                            <button class="btn btn-ghost btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-person-circle"></i> <span class="d-none d-sm-inline">{{ auth('customer')->user()->name }}</span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                <li><a class="dropdown-item" href="{{ route('shop.account.index') }}"><i class="bi bi-speedometer2 me-2"></i>Mon compte</a></li>
                                <li><a class="dropdown-item" href="{{ route('shop.account.orders.index') }}"><i class="bi bi-box-seam me-2"></i>Mes commandes</a></li>
                                <li><a class="dropdown-item" href="{{ route('shop.notifications.index') }}"><i class="bi bi-bell me-2"></i>Notifications</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form method="POST" action="{{ route('shop.logout') }}">
                                        @csrf
                                        <button type="submit" class="dropdown-item"><i class="bi bi-box-arrow-right me-2"></i>Se déconnecter</button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    @else
                        <a href="{{ route('shop.login') }}" class="btn btn-ghost btn-sm"><i class="bi bi-person"></i> <span class="d-none d-sm-inline">Connexion</span></a>
                    @endauth
                </nav>
            </div>
        </header>

        <main class="shop-content">
            @if (session('success'))
                <div class="alert alert-good"><i class="bi bi-check-circle-fill"></i> <span>{{ session('success') }}</span></div>
            @endif
            @if (session('error'))
                <div class="alert alert-crit"><i class="bi bi-exclamation-triangle-fill"></i> <span>{{ session('error') }}</span></div>
            @endif
            @if ($errors->any())
                <div class="alert alert-crit">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span>{{ $errors->first() }}</span>
                </div>
            @endif

            {{ $slot }}
        </main>

        <footer class="shop-footer">
            <p>Quincaillerie — magasin physique et boutique en ligne. Retrait en magasin ou livraison.</p>
        </footer>

        <script src="{{ asset('js/vendor/bootstrap.bundle.min.js') }}"></script>
    </body>
</html>
