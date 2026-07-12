@props(['title' => 'Tableau de bord'])
<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $title }} — Quincaillerie</title>
        <link rel="stylesheet" href="{{ asset('css/app.css') }}">
        @livewireStyles
    </head>
    <body>
        <div class="app-shell">
            <aside class="sidebar">
                <div class="sidebar-brand"><span class="dot"></span> Quincaillerie</div>

                <nav class="sidebar-nav">
                    <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">Tableau de bord</a>
                    <a href="{{ route('chatbot.index') }}" class="{{ request()->routeIs('chatbot.*') ? 'active' : '' }}">🤖 Assistant IA</a>

                    @hasanyrole('admin|caissier')
                        <div class="sidebar-section">Vente</div>
                        <a href="{{ route('pos.index') }}" class="{{ request()->routeIs('pos.*') ? 'active' : '' }}">Caisse (POS)</a>
                        <a href="{{ route('sales.index') }}" class="{{ request()->routeIs('sales.*') ? 'active' : '' }}">Ventes</a>
                        <a href="{{ route('quotes.index') }}" class="{{ request()->routeIs('quotes.*') ? 'active' : '' }}">Devis</a>
                        <a href="{{ route('customers.index') }}" class="{{ request()->routeIs('customers.*') ? 'active' : '' }}">Clients</a>
                    @endhasanyrole

                    @hasanyrole('admin|magasinier')
                        <div class="sidebar-section">Catalogue</div>
                        <a href="{{ route('products.index') }}" class="{{ request()->routeIs('products.*') ? 'active' : '' }}">Produits</a>
                        <a href="{{ route('product-families.index') }}" class="{{ request()->routeIs('product-families.*') ? 'active' : '' }}">Familles &amp; variantes</a>
                        <a href="{{ route('categories.index') }}" class="{{ request()->routeIs('categories.*') ? 'active' : '' }}">Catégories</a>
                        <a href="{{ route('suppliers.index') }}" class="{{ request()->routeIs('suppliers.*') ? 'active' : '' }}">Fournisseurs</a>

                        <div class="sidebar-section">Stock &amp; achats</div>
                        <a href="{{ route('stock-movements.index') }}" class="{{ request()->routeIs('stock-movements.*') ? 'active' : '' }}">Mouvements de stock</a>
                        <a href="{{ route('purchase-orders.index') }}" class="{{ request()->routeIs('purchase-orders.*') ? 'active' : '' }}">Commandes fournisseur</a>
                        <a href="{{ route('stock-transfers.index') }}" class="{{ request()->routeIs('stock-transfers.*') ? 'active' : '' }}">Transferts</a>
                        <a href="{{ route('inventory-counts.index') }}" class="{{ request()->routeIs('inventory-counts.*') ? 'active' : '' }}">Inventaires</a>
                    @endhasanyrole

                    @hasrole('admin')
                        <div class="sidebar-section">Pilotage</div>
                        <a href="{{ route('reports.index') }}" class="{{ request()->routeIs('reports.index') ? 'active' : '' }}">Rapports ventes</a>
                        <a href="{{ route('reports.stock') }}" class="{{ request()->routeIs('reports.stock') ? 'active' : '' }}">Rapports stock</a>
                        <a href="{{ route('reports.cash-flow') }}" class="{{ request()->routeIs('reports.cash-flow') ? 'active' : '' }}">Prévision de trésorerie</a>
                        <a href="{{ route('users.index') }}" class="{{ request()->routeIs('users.*') ? 'active' : '' }}">Utilisateurs</a>
                        <a href="{{ route('warehouses.index') }}" class="{{ request()->routeIs('warehouses.*') ? 'active' : '' }}">Entrepôts</a>
                        <a href="{{ route('audit-logs.index') }}" class="{{ request()->routeIs('audit-logs.*') ? 'active' : '' }}">Journal d'audit</a>
                        <a href="{{ route('trash.index') }}" class="{{ request()->routeIs('trash.*') ? 'active' : '' }}">Corbeille</a>
                    @endhasrole
                </nav>

                <div class="sidebar-foot">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit">Se déconnecter</button>
                    </form>
                </div>
            </aside>

            <div class="main">
                <header class="topbar">
                    <a href="{{ route('notifications.index') }}" class="btn btn-ghost btn-sm" style="position:relative">
                        🔔
                        @php $unread = auth()->user()->unreadNotifications()->count(); @endphp
                        @if ($unread > 0)
                            <span class="badge badge-crit" style="position:absolute; top:-6px; right:-6px; min-width:18px; justify-content:center;">{{ $unread }}</span>
                        @endif
                    </a>
                    <div class="topbar-user">
                        {{ auth()->user()->name }}
                        <span class="role-pill">{{ auth()->user()->getRoleNames()->first() ?? '—' }}</span>
                    </div>
                </header>

                <main class="content">
                    @if (session('success'))
                        <div class="alert alert-good">{{ session('success') }}</div>
                    @endif
                    @if (session('error'))
                        <div class="alert alert-crit">{{ session('error') }}</div>
                    @endif

                    {{ $slot }}
                </main>
            </div>
        </div>

        <script src="{{ asset('js/barcode-scanner.js') }}"></script>
        <script src="{{ asset('js/product-recognition.js') }}"></script>
        <script src="{{ asset('js/product-description.js') }}"></script>
        @livewireScripts
    </body>
</html>
