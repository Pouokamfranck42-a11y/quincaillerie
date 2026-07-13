@props(['title' => 'Tableau de bord'])
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
        @livewireStyles
    </head>
    <body>
        <div class="app-shell">
            <aside class="sidebar d-none d-lg-flex">
                @include('components.sidebar-nav')
            </aside>

            <div class="offcanvas offcanvas-start d-lg-none p-0 border-0" tabindex="-1" id="sidebarOffcanvas" aria-labelledby="sidebarOffcanvasLabel">
                <div class="sidebar d-flex h-100" style="width:100%">
                    @include('components.sidebar-nav')
                </div>
            </div>

            <div class="main">
                <header class="topbar">
                    <div class="flex" style="gap:14px">
                        <button class="btn btn-ghost btn-sm d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas" aria-label="Ouvrir le menu">
                            <i class="bi bi-list" style="font-size:20px"></i>
                        </button>
                        <form method="GET" action="{{ route('products.index') }}" class="topbar-search d-none d-md-block">
                            <div class="input-group">
                                <span class="input-group-text bg-transparent border-end-0"><i class="bi bi-search text-body-secondary"></i></span>
                                <input type="search" name="q" class="form-control border-start-0 ps-0" placeholder="Rechercher un produit…" aria-label="Rechercher un produit">
                            </div>
                        </form>
                    </div>

                    <div class="flex" style="gap:6px">
                        <a href="{{ route('notifications.index') }}" class="topbar-icon-btn" title="Notifications">
                            <i class="bi bi-bell"></i>
                            @php $unread = auth()->user()->unreadNotifications()->count(); @endphp
                            @if ($unread > 0)
                                <span class="badge badge-crit" style="position:absolute; top:3px; right:3px; min-width:17px; height:17px; justify-content:center; padding:0; font-size:10px;">{{ $unread }}</span>
                            @endif
                        </a>

                        <div class="dropdown">
                            <button class="btn btn-ghost btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <span class="topbar-user">
                                    <span class="d-none d-sm-inline">{{ auth()->user()->name }}</span>
                                    <span class="role-pill">{{ auth()->user()->getRoleNames()->first() ?? '—' }}</span>
                                </span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                <li><h6 class="dropdown-header mb-0">{{ auth()->user()->email }}</h6></li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <button type="submit" class="dropdown-item"><i class="bi bi-box-arrow-right me-2"></i>Se déconnecter</button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    </div>
                </header>

                <main class="content">
                    @if (session('success'))
                        <div class="alert alert-good"><i class="bi bi-check-circle-fill"></i> <span>{{ session('success') }}</span></div>
                    @endif
                    @if (session('error'))
                        <div class="alert alert-crit"><i class="bi bi-exclamation-triangle-fill"></i> <span>{{ session('error') }}</span></div>
                    @endif

                    {{ $slot }}
                </main>
            </div>
        </div>

        <script src="{{ asset('js/vendor/bootstrap.bundle.min.js') }}"></script>
        <script src="{{ asset('js/barcode-scanner.js') }}"></script>
        <script src="{{ asset('js/product-recognition.js') }}"></script>
        <script src="{{ asset('js/product-description.js') }}"></script>
        @livewireScripts
    </body>
</html>
