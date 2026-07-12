<x-layout title="Tableau de bord">
    <div class="page-head"><h1>Tableau de bord</h1></div>

    <div class="stat-grid">
        <div class="stat-tile">
            <div class="lbl">Ventes du jour</div>
            <div class="val">{{ number_format($todaySalesTotal, 0, ',', ' ') }}</div>
            <div class="sub">{{ $todaySalesCount }} vente(s)</div>
        </div>
        <div class="stat-tile @if($lowStockCount > 0) warn @endif">
            <div class="lbl">Produits en stock bas</div>
            <div class="val">{{ $lowStockCount }}</div>
            <div class="sub"><a href="{{ route('stock-movements.index') }}">voir le détail</a></div>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2>Dernières ventes</h2></div>
        <div class="tbl-wrap">
            <table>
                <thead><tr><th>Heure</th><th>Caissier</th><th class="num">Articles</th><th class="num">Total</th><th></th></tr></thead>
                <tbody>
                    @forelse ($recentSales as $sale)
                        <tr>
                            <td class="mono">{{ $sale->created_at->format('d/m H:i') }}</td>
                            <td>{{ $sale->user->name }}</td>
                            <td class="num">{{ $sale->lines->count() }}</td>
                            <td class="num">{{ number_format($sale->total, 0, ',', ' ') }}</td>
                            <td>
                                @hasanyrole('admin|caissier')
                                    <a href="{{ route('sales.show', $sale) }}" class="btn btn-sm">Voir</a>
                                @endhasanyrole
                            </td>
                        </tr>
                    @empty
                        <tr class="empty-row"><td colspan="5">Aucune vente enregistrée pour l'instant.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layout>
