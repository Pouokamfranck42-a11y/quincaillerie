<div class="sidebar-brand"><span class="dot"></span> Quincaillerie</div>

<nav class="sidebar-nav">
    <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}"><i class="bi bi-speedometer2"></i> Tableau de bord</a>
    @can('ia.chatbot')
        <a href="{{ route('chatbot.index') }}" class="{{ request()->routeIs('chatbot.*') ? 'active' : '' }}"><i class="bi bi-robot"></i> Assistant IA</a>
    @endcan

    @if (auth()->user()->canAny(['caisse.encaisser', 'ventes.creer', 'ventes.historique', 'clients.voir', 'ecommerce.commandes']))
        <div class="sidebar-section">Vente</div>
        @can('caisse.encaisser')
            <a href="{{ route('pos.index') }}" class="{{ request()->routeIs('pos.*') ? 'active' : '' }}"><i class="bi bi-cash-coin"></i> Caisse (POS)</a>
        @endcan
        @can('ventes.historique')
            <a href="{{ route('sales.index') }}" class="{{ request()->routeIs('sales.*') ? 'active' : '' }}"><i class="bi bi-receipt"></i> Ventes</a>
        @endcan
        @can('ventes.creer')
            <a href="{{ route('quotes.index') }}" class="{{ request()->routeIs('quotes.*') ? 'active' : '' }}"><i class="bi bi-file-earmark-text"></i> Devis</a>
        @endcan
        @can('clients.voir')
            <a href="{{ route('customers.index') }}" class="{{ request()->routeIs('customers.*') ? 'active' : '' }}"><i class="bi bi-people"></i> Clients</a>
        @endcan
        @can('ecommerce.commandes')
            <a href="{{ route('online-orders.index') }}" class="{{ request()->routeIs('online-orders.*') ? 'active' : '' }}"><i class="bi bi-globe2"></i> Commandes en ligne</a>
        @endcan
    @endif

    @if (auth()->user()->canAny(['produits.voir', 'catalogue.voir', 'fournisseurs.voir', 'stock.voir', 'achats.voir', 'stock.transferer', 'stock.inventaire']))
        <div class="sidebar-section">Catalogue</div>
        @can('produits.voir')
            <a href="{{ route('products.index') }}" class="{{ request()->routeIs('products.*') && ! request()->routeIs('products.import*') ? 'active' : '' }}"><i class="bi bi-box-seam"></i> Produits</a>
        @endcan
        @can('produits.importer')
            <a href="{{ route('products.import') }}" class="{{ request()->routeIs('products.import*') ? 'active' : '' }}"><i class="bi bi-file-earmark-arrow-up"></i> Importer le catalogue</a>
        @endcan
        @can('catalogue.voir')
            <a href="{{ route('product-families.index') }}" class="{{ request()->routeIs('product-families.*') ? 'active' : '' }}"><i class="bi bi-diagram-3"></i> Familles &amp; variantes</a>
            <a href="{{ route('categories.index') }}" class="{{ request()->routeIs('categories.*') ? 'active' : '' }}"><i class="bi bi-tags"></i> Catégories</a>
        @endcan
        @can('fournisseurs.voir')
            <a href="{{ route('suppliers.index') }}" class="{{ request()->routeIs('suppliers.*') ? 'active' : '' }}"><i class="bi bi-truck"></i> Fournisseurs</a>
        @endcan

        @if (auth()->user()->canAny(['stock.voir', 'achats.voir', 'stock.transferer', 'stock.inventaire']))
            <div class="sidebar-section">Stock &amp; achats</div>
            @can('stock.voir')
                <a href="{{ route('stock-movements.index') }}" class="{{ request()->routeIs('stock-movements.*') ? 'active' : '' }}"><i class="bi bi-arrow-left-right"></i> Mouvements de stock</a>
            @endcan
            @can('achats.voir')
                <a href="{{ route('purchase-orders.index') }}" class="{{ request()->routeIs('purchase-orders.*') ? 'active' : '' }}"><i class="bi bi-cart3"></i> Commandes fournisseur</a>
            @endcan
            @can('stock.transferer')
                <a href="{{ route('stock-transfers.index') }}" class="{{ request()->routeIs('stock-transfers.*') ? 'active' : '' }}"><i class="bi bi-arrow-repeat"></i> Transferts</a>
            @endcan
            @can('stock.inventaire')
                <a href="{{ route('inventory-counts.index') }}" class="{{ request()->routeIs('inventory-counts.*') ? 'active' : '' }}"><i class="bi bi-clipboard-check"></i> Inventaires</a>
            @endcan
        @endif
    @endif

    @if (auth()->user()->canAny(['rapports.voir', 'ia.previsions', 'rapports.exporter', 'utilisateurs.creer', 'utilisateurs.permissions', 'configuration.systeme']))
        <div class="sidebar-section">Pilotage</div>
        @can('rapports.voir')
            <a href="{{ route('reports.index') }}" class="{{ request()->routeIs('reports.index') ? 'active' : '' }}"><i class="bi bi-graph-up"></i> Rapports ventes</a>
            <a href="{{ route('reports.stock') }}" class="{{ request()->routeIs('reports.stock') ? 'active' : '' }}"><i class="bi bi-bar-chart"></i> Rapports stock</a>
            <a href="{{ route('reports.customer-credit') }}" class="{{ request()->routeIs('reports.customer-credit') ? 'active' : '' }}"><i class="bi bi-cash-coin"></i> Encours clients</a>
        @endcan
        @can('ia.previsions')
            <a href="{{ route('reports.cash-flow') }}" class="{{ request()->routeIs('reports.cash-flow') ? 'active' : '' }}"><i class="bi bi-cash-stack"></i> Prévision de trésorerie</a>
        @endcan
        @can('rapports.exporter')
            <a href="{{ route('accounting-export.index') }}" class="{{ request()->routeIs('accounting-export.*') ? 'active' : '' }}"><i class="bi bi-file-earmark-spreadsheet"></i> Export comptable</a>
        @endcan
        @can('utilisateurs.creer')
            <a href="{{ route('users.index') }}" class="{{ request()->routeIs('users.*') ? 'active' : '' }}"><i class="bi bi-person-gear"></i> Utilisateurs</a>
        @endcan
        @can('utilisateurs.permissions')
            <a href="{{ route('roles.index') }}" class="{{ request()->routeIs('roles.*') ? 'active' : '' }}"><i class="bi bi-shield-lock"></i> Profils</a>
        @endcan
        @can('configuration.systeme')
            <a href="{{ route('warehouses.index') }}" class="{{ request()->routeIs('warehouses.*') ? 'active' : '' }}"><i class="bi bi-building"></i> Entrepôts</a>
            <a href="{{ route('audit-logs.index') }}" class="{{ request()->routeIs('audit-logs.*') ? 'active' : '' }}"><i class="bi bi-journal-text"></i> Journal d'audit</a>
            <a href="{{ route('error-logs.index') }}" class="{{ request()->routeIs('error-logs.*') ? 'active' : '' }}"><i class="bi bi-bug"></i> Journal des erreurs</a>
            <a href="{{ route('trash.index') }}" class="{{ request()->routeIs('trash.*') ? 'active' : '' }}"><i class="bi bi-trash3"></i> Corbeille</a>
        @endcan
    @endif
</nav>

<div class="sidebar-foot">
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit"><i class="bi bi-box-arrow-right"></i> Se déconnecter</button>
    </form>
</div>
