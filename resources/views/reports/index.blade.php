<x-layout title="Rapports ventes">
    <div class="page-head">
        <div>
            <h1><i class="bi bi-graph-up text-primary"></i> Rapports ventes</h1>
            <p>Chiffre d'affaires, marge et meilleures ventes sur la période choisie.</p>
        </div>
    </div>

    <form method="GET" class="field-row" style="max-width:460px; margin-bottom:22px; align-items:flex-end">
        <div class="field">
            <label for="from"><i class="bi bi-calendar3 me-1"></i>Du</label>
            <input type="date" id="from" name="from" value="{{ $from->format('Y-m-d') }}">
        </div>
        <div class="field">
            <label for="to"><i class="bi bi-calendar3 me-1"></i>Au</label>
            <input type="date" id="to" name="to" value="{{ $to->format('Y-m-d') }}">
        </div>
        <div class="field" style="flex:0">
            <button type="submit" class="btn btn-primary"><i class="bi bi-funnel"></i> Filtrer</button>
        </div>
    </form>

    <div class="stat-grid">
        <div class="stat-tile good">
            <div class="lbl"><i class="bi bi-cash-stack"></i> Chiffre d'affaires</div>
            <div class="val">{{ number_format($totalSales, 0, ',', ' ') }}</div>
            <div class="sub">{{ $salesCount }} vente(s)</div>
        </div>
        <div class="stat-tile">
            <div class="lbl"><i class="bi bi-receipt"></i> TVA collectée</div>
            <div class="val">{{ number_format($totalTax, 0, ',', ' ') }}</div>
        </div>
        <div class="stat-tile">
            <div class="lbl"><i class="bi bi-graph-up-arrow"></i> Marge brute (estimée)</div>
            <div class="val">{{ number_format($grossMargin, 0, ',', ' ') }}</div>
            <div class="sub">basée sur le prix d'achat courant des produits</div>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2><i class="bi bi-bar-chart-line"></i> Évolution des ventes</h2></div>
        @if ($salesByDay->isNotEmpty())
            <div style="height:280px"><canvas id="salesByDayChart"></canvas></div>
        @else
            <p class="muted mt-0">Aucune vente sur cette période.</p>
        @endif
    </div>

    <div class="card">
        <div class="card-head"><h2><i class="bi bi-trophy"></i> Meilleures ventes (top 10)</h2></div>
        <div class="tbl-wrap">
            <table>
                <thead><tr><th>Produit</th><th class="num">Quantité</th><th class="num">Chiffre d'affaires</th></tr></thead>
                <tbody>
                    @forelse ($topProducts as $row)
                        <tr>
                            <td>{{ $row['name'] }}</td>
                            <td class="num">{{ rtrim(rtrim(number_format($row['quantity'], 2, ',', ' '), '0'), ',') }}</td>
                            <td class="num">{{ number_format($row['revenue'], 0, ',', ' ') }}</td>
                        </tr>
                    @empty
                        <tr class="empty-row"><td colspan="3">Aucune vente sur cette période.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($salesByDay->isNotEmpty())
        <script src="{{ asset('js/vendor/chart.umd.min.js') }}"></script>
        <script>
            new Chart(document.getElementById('salesByDayChart'), {
                type: 'line',
                data: {
                    labels: @json($salesByDay->keys()->map(fn ($d) => \Illuminate\Support\Carbon::parse($d)->format('d/m'))),
                    datasets: [{
                        label: 'Ventes (FCFA)',
                        data: @json($salesByDay->values()),
                        borderColor: '#E85D1F',
                        backgroundColor: 'rgba(232,93,31,.12)',
                        fill: true,
                        tension: 0.3,
                        pointRadius: 3,
                        pointBackgroundColor: '#E85D1F',
                    }],
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true, ticks: { callback: (v) => v.toLocaleString('fr-FR') } } },
                },
            });
        </script>
    @endif
</x-layout>
