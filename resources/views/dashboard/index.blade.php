<x-layout title="Tableau de bord">
    <div class="page-head">
        <div>
            <h1><i class="bi bi-speedometer2 text-primary"></i> Tableau de bord</h1>
            <p>Vue d'ensemble de l'activité du jour.</p>
        </div>
    </div>

    @if ($lowStockCount > 0)
        <div class="alert-banner">
            <i class="bi bi-exclamation-diamond-fill icon"></i>
            <div>
                <div class="title">{{ $lowStockCount }} produit(s) en stock bas</div>
                <div class="desc">Ces articles ont atteint ou dépassé leur seuil d'alerte.</div>
            </div>
            <a href="{{ route('stock-movements.index') }}" class="btn btn-sm" style="margin-left:auto"><i class="bi bi-box-arrow-up-right"></i> Voir le détail</a>
        </div>
    @endif

    <div class="stat-grid">
        <div class="stat-tile good">
            <div class="lbl"><i class="bi bi-cash-stack"></i> Ventes du jour</div>
            <div class="val">{{ number_format($todaySalesTotal, 0, ',', ' ') }} <small style="font-size:13px; font-weight:600;">FCFA</small></div>
            <div class="sub">{{ $todaySalesCount }} vente(s) enregistrée(s)</div>
        </div>
        <div class="stat-tile">
            <div class="lbl"><i class="bi bi-receipt-cutoff"></i> Nombre de ventes</div>
            <div class="val">{{ $todaySalesCount }}</div>
            <div class="sub">aujourd'hui</div>
        </div>
        <div class="stat-tile">
            <div class="lbl"><i class="bi bi-graph-up-arrow"></i> Panier moyen</div>
            <div class="val">{{ number_format($todaySalesCount > 0 ? $todaySalesTotal / $todaySalesCount : 0, 0, ',', ' ') }}</div>
            <div class="sub">FCFA par vente</div>
        </div>
        <div class="stat-tile @if($lowStockCount > 0) warn @endif">
            <div class="lbl"><i class="bi bi-exclamation-triangle"></i> Produits en stock bas</div>
            <div class="val">{{ $lowStockCount }}</div>
            <div class="sub"><a href="{{ route('stock-movements.index') }}">voir le détail <i class="bi bi-arrow-right"></i></a></div>
        </div>
    </div>

    <div class="stat-grid">
        <div class="stat-tile">
            <div class="lbl"><i class="bi bi-box-seam"></i> Valeur du stock</div>
            <div class="val">{{ number_format($stockValue, 0, ',', ' ') }}</div>
            <div class="sub">au coût d'achat</div>
        </div>
        <div class="stat-tile">
            <div class="lbl"><i class="bi bi-arrow-repeat"></i> Taux de rotation</div>
            <div class="val">{{ $turnoverRate }}×</div>
            <div class="sub">sur 90 jours</div>
        </div>
        <div class="stat-tile">
            <div class="lbl"><i class="bi bi-percent"></i> Marge</div>
            <div class="val">{{ $marginPercent }}%</div>
            <div class="sub">sur 90 jours</div>
        </div>
        <div class="stat-tile @if($stockoutCount > 0) crit @endif">
            <div class="lbl"><i class="bi bi-x-octagon"></i> Ruptures</div>
            <div class="val">{{ $stockoutCount }}</div>
            <div class="sub">stock à zéro</div>
        </div>
        <div class="stat-tile @if($dormantCount > 0) warn @endif">
            <div class="lbl"><i class="bi bi-hourglass-split"></i> Articles dormants</div>
            <div class="val">{{ $dormantCount }}</div>
            <div class="sub">
                @can('rapports.voir')
                    <a href="{{ route('dormant-stock.index') }}">voir le détail <i class="bi bi-arrow-right"></i></a>
                @else
                    argent immobilisé
                @endcan
            </div>
        </div>
        <div class="stat-tile @if($overstockCount > 0) warn @endif">
            <div class="lbl"><i class="bi bi-boxes"></i> Surstock</div>
            <div class="val">{{ $overstockCount }}</div>
            <div class="sub">au-delà du stock max</div>
        </div>
    </div>

    @if (auth()->user()->canAny(['rapports.voir', 'ia.previsions']))
        <div class="stat-grid" style="grid-template-columns:repeat(auto-fit, minmax(230px,1fr)); margin-bottom:26px;">
            @can('rapports.voir')
                <a href="{{ route('reports.index') }}" class="card nav-card">
                    <div class="nav-card-icon"><i class="bi bi-graph-up"></i></div>
                    <div>
                        <div class="nav-card-title">Rapports ventes</div>
                        <div class="nav-card-sub">Évolution &amp; top produits</div>
                    </div>
                    <i class="bi bi-arrow-right nav-card-arrow"></i>
                </a>
                <a href="{{ route('reports.stock') }}" class="card nav-card">
                    <div class="nav-card-icon"><i class="bi bi-bar-chart"></i></div>
                    <div>
                        <div class="nav-card-title">Rapports stock</div>
                        <div class="nav-card-sub">Rotation, ABC, dormants</div>
                    </div>
                    <i class="bi bi-arrow-right nav-card-arrow"></i>
                </a>
            @endcan
            @can('ia.previsions')
                <a href="{{ route('reports.cash-flow') }}" class="card nav-card">
                    <div class="nav-card-icon"><i class="bi bi-cash-coin"></i></div>
                    <div>
                        <div class="nav-card-title">Prévision de trésorerie</div>
                        <div class="nav-card-sub">Projection 30/60/90 jours</div>
                    </div>
                    <i class="bi bi-arrow-right nav-card-arrow"></i>
                </a>
            @endcan
        </div>
    @endif

    <div class="card">
        <div class="card-head"><h2><i class="bi bi-clock-history"></i> Dernières ventes</h2></div>
        <div class="tbl-wrap">
            <table>
                <thead><tr><th>Heure</th><th>Caissier</th><th class="num">Articles</th><th class="num">Total</th><th></th></tr></thead>
                <tbody>
                    @forelse ($recentSales as $sale)
                        <tr>
                            <td class="mono">{{ $sale->created_at->format('d/m H:i') }}</td>
                            <td>{{ $sale->user?->name ?? 'Vente en ligne' }}</td>
                            <td class="num">{{ $sale->lines->count() }}</td>
                            <td class="num">{{ number_format($sale->total, 0, ',', ' ') }}</td>
                            <td>
                                @can('ventes.historique')
                                    <a href="{{ route('sales.show', $sale) }}" class="btn btn-sm"><i class="bi bi-eye"></i> Voir</a>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr class="empty-row"><td colspan="5"><i class="bi bi-inbox"></i> Aucune vente enregistrée pour l'instant.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layout>
