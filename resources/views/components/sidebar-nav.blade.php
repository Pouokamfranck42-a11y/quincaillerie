<div class="sidebar-brand"><span class="dot"></span> Quincaillerie</div>

<nav class="sidebar-nav">
    <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}"><i class="bi bi-speedometer2"></i> Tableau de bord</a>
    <a href="{{ route('chatbot.index') }}" class="{{ request()->routeIs('chatbot.*') ? 'active' : '' }}"><i class="bi bi-robot"></i> Assistant IA</a>

    @hasanyrole('admin|caissier')
        <div class="sidebar-section">Vente</div>
        <a href="{{ route('pos.index') }}" class="{{ request()->routeIs('pos.*') ? 'active' : '' }}"><i class="bi bi-cash-coin"></i> Caisse (POS)</a>
        <a href="{{ route('sales.index') }}" class="{{ request()->routeIs('sales.*') ? 'active' : '' }}"><i class="bi bi-receipt"></i> Ventes</a>
        <a href="{{ route('quotes.index') }}" class="{{ request()->routeIs('quotes.*') ? 'active' : '' }}"><i class="bi bi-file-earmark-text"></i> Devis</a>
        <a href="{{ route('customers.index') }}" class="{{ request()->routeIs('customers.*') ? 'active' : '' }}"><i class="bi bi-people"></i> Clients</a>
    @endhasanyrole

    @hasanyrole('admin|magasinier')
        <div class="sidebar-section">Catalogue</div>
        <a href="{{ route('products.index') }}" class="{{ request()->routeIs('products.*') ? 'active' : '' }}"><i class="bi bi-box-seam"></i> Produits</a>
        <a href="{{ route('product-families.index') }}" class="{{ request()->routeIs('product-families.*') ? 'active' : '' }}"><i class="bi bi-diagram-3"></i> Familles &amp; variantes</a>
        <a href="{{ route('categories.index') }}" class="{{ request()->routeIs('categories.*') ? 'active' : '' }}"><i class="bi bi-tags"></i> Catégories</a>
        <a href="{{ route('suppliers.index') }}" class="{{ request()->routeIs('suppliers.*') ? 'active' : '' }}"><i class="bi bi-truck"></i> Fournisseurs</a>

        <div class="sidebar-section">Stock &amp; achats</div>
        <a href="{{ route('stock-movements.index') }}" class="{{ request()->routeIs('stock-movements.*') ? 'active' : '' }}"><i class="bi bi-arrow-left-right"></i> Mouvements de stock</a>
        <a href="{{ route('purchase-orders.index') }}" class="{{ request()->routeIs('purchase-orders.*') ? 'active' : '' }}"><i class="bi bi-cart3"></i> Commandes fournisseur</a>
        <a href="{{ route('stock-transfers.index') }}" class="{{ request()->routeIs('stock-transfers.*') ? 'active' : '' }}"><i class="bi bi-arrow-repeat"></i> Transferts</a>
        <a href="{{ route('inventory-counts.index') }}" class="{{ request()->routeIs('inventory-counts.*') ? 'active' : '' }}"><i class="bi bi-clipboard-check"></i> Inventaires</a>
    @endhasanyrole

    @hasrole('admin')
        <div class="sidebar-section">Pilotage</div>
        <a href="{{ route('reports.index') }}" class="{{ request()->routeIs('reports.index') ? 'active' : '' }}"><i class="bi bi-graph-up"></i> Rapports ventes</a>
        <a href="{{ route('reports.stock') }}" class="{{ request()->routeIs('reports.stock') ? 'active' : '' }}"><i class="bi bi-bar-chart"></i> Rapports stock</a>
        <a href="{{ route('reports.cash-flow') }}" class="{{ request()->routeIs('reports.cash-flow') ? 'active' : '' }}"><i class="bi bi-cash-stack"></i> Prévision de trésorerie</a>
        <a href="{{ route('users.index') }}" class="{{ request()->routeIs('users.*') ? 'active' : '' }}"><i class="bi bi-person-gear"></i> Utilisateurs</a>
        <a href="{{ route('warehouses.index') }}" class="{{ request()->routeIs('warehouses.*') ? 'active' : '' }}"><i class="bi bi-building"></i> Entrepôts</a>
        <a href="{{ route('audit-logs.index') }}" class="{{ request()->routeIs('audit-logs.*') ? 'active' : '' }}"><i class="bi bi-journal-text"></i> Journal d'audit</a>
        <a href="{{ route('trash.index') }}" class="{{ request()->routeIs('trash.*') ? 'active' : '' }}"><i class="bi bi-trash3"></i> Corbeille</a>
    @endhasrole
</nav>

<div class="sidebar-foot">
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit"><i class="bi bi-box-arrow-right"></i> Se déconnecter</button>
    </form>
</div>
