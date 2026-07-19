<x-layout title="Articles dormants — IA">
    <div class="page-head">
        <div>
            <h1><i class="bi bi-robot text-primary"></i> Articles dormants &amp; surstock</h1>
            <p>Argent immobilisé dans du stock qui ne tourne plus, avec une action suggérée par article.</p>
        </div>
    </div>

    <div class="stat-grid">
        <div class="stat-tile @if($totalTiedUp > 0) warn @endif">
            <div class="lbl"><i class="bi bi-cash-stack"></i> Argent immobilisé</div>
            <div class="val">{{ number_format($totalTiedUp, 0, ',', ' ') }}</div>
            <div class="sub">{{ $opportunities->count() }} article(s)</div>
        </div>
    </div>

    @if ($summary)
        <div class="card" style="background:var(--primary-soft, #EEF2FF); border:1px solid var(--primary-light, #C7D2FE)">
            <div class="card-head"><h2 style="font-size:15px"><i class="bi bi-stars"></i> Résumé</h2></div>
            <p class="mt-0" style="white-space:pre-line">{{ $summary }}</p>
        </div>
    @elseif ($opportunities->isNotEmpty())
        <div class="alert alert-warn"><i class="bi bi-info-circle"></i> <span>Résumé IA momentanément indisponible — la liste ci-dessous et les actions suggérées restent fiables (calculées sans IA).</span></div>
    @endif

    <div class="tbl-wrap">
        <table>
            <thead><tr><th>Produit</th><th>Statut</th><th class="num">Stock</th><th class="num">Immobilisé</th><th>Dernière vente</th><th>Action suggérée</th></tr></thead>
            <tbody>
                @forelse ($opportunities as $row)
                    <tr>
                        <td><a href="{{ route('products.show', $row['product']) }}">{{ $row['product']->name }}</a></td>
                        <td>
                            @if ($row['dormant'] && $row['overstock'])
                                <span class="badge badge-crit">dormant + surstock</span>
                            @elseif ($row['dormant'])
                                <span class="badge badge-warn">dormant</span>
                            @else
                                <span class="badge badge-warn">surstock</span>
                            @endif
                        </td>
                        <td class="num">{{ rtrim(rtrim(number_format($row['product']->currentStock(), 2, ',', ' '), '0'), ',') }} {{ $row['product']->unit }}</td>
                        <td class="num">{{ number_format($row['capital_tied_up'], 0, ',', ' ') }}</td>
                        <td class="muted">{{ $row['days_since_last_sale'] !== null ? $row['days_since_last_sale'].' j' : 'jamais vendu' }}</td>
                        <td style="font-size:13px">{{ $row['suggested_action'] }}</td>
                    </tr>
                @empty
                    <tr class="empty-row"><td colspan="6"><i class="bi bi-check-circle"></i> Aucun article dormant ou en surstock détecté.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layout>
