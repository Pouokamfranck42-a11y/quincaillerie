<x-layout title="Rapports stock">
    <div class="page-head">
        <div>
            <h1><i class="bi bi-bar-chart text-primary"></i> Rapports stock</h1>
            <p>Valorisation, rotation, analyse ABC — basé sur les 90 derniers jours de ventes.</p>
        </div>
        <a href="{{ route('reports.stock.export') }}" class="btn"><i class="bi bi-file-earmark-arrow-down"></i> Exporter l'état du stock (CSV)</a>
    </div>

    <div class="stat-grid">
        <div class="stat-tile good">
            <div class="lbl"><i class="bi bi-piggy-bank-fill"></i> Valeur immobilisée</div>
            <div class="val">{{ number_format($totalValue, 0, ',', ' ') }}</div>
            <div class="sub">stock courant × CUMP</div>
        </div>
        <div class="stat-tile @if($stockoutRate > 5) crit @endif">
            <div class="lbl"><i class="bi bi-exclamation-octagon"></i> Taux de rupture (approx.)</div>
            <div class="val">{{ $stockoutRate }}%</div>
            <div class="sub">{{ $stockoutCount }} produit(s) actifs à stock ≤ 0</div>
        </div>
        <div class="stat-tile @if($overstock->count() > 0) warn @endif">
            <div class="lbl"><i class="bi bi-boxes"></i> Produits en surstock</div>
            <div class="val">{{ $overstock->count() }}</div>
        </div>
        <div class="stat-tile @if($dormant->count() > 0) warn @endif">
            <div class="lbl"><i class="bi bi-hourglass-split"></i> Produits dormants</div>
            <div class="val">{{ $dormant->count() }}</div>
            <div class="sub">aucune vente depuis 90 jours</div>
        </div>
    </div>

    <div class="field-row" style="align-items:stretch">
        <div class="card" style="flex:1 1 320px">
            <div class="card-head"><h2><i class="bi bi-pie-chart-fill"></i> Valeur du stock par catégorie</h2></div>
            @if ($valueByCategory->isNotEmpty())
                <div style="height:260px"><canvas id="valueByCategoryChart"></canvas></div>
            @else
                <p class="muted mt-0">Aucune donnée.</p>
            @endif
        </div>
        @if ($abc->isNotEmpty())
            <div class="card" style="flex:1 1 320px">
                <div class="card-head"><h2><i class="bi bi-bar-chart-line"></i> Répartition ABC (CA 90j)</h2></div>
                <div style="height:260px"><canvas id="abcChart"></canvas></div>
            </div>
        @endif
    </div>

    <div class="card">
        <div class="card-head"><h2>Valeur du stock par catégorie</h2></div>
        <div class="tbl-wrap">
            <table>
                <thead><tr><th>Catégorie</th><th class="num">Valeur</th></tr></thead>
                <tbody>
                    @foreach ($valueByCategory as $category => $value)
                        <tr><td>{{ $category }}</td><td class="num">{{ number_format($value, 0, ',', ' ') }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if ($valueByWarehouse->count() > 1)
        <div class="card">
            <div class="card-head"><h2><i class="bi bi-building"></i> Valeur du stock par entrepôt</h2></div>
            <div class="tbl-wrap">
                <table>
                    <thead><tr><th>Entrepôt</th><th class="num">Valeur</th></tr></thead>
                    <tbody>
                        @foreach ($valueByWarehouse as $warehouse => $value)
                            <tr><td>{{ $warehouse }}</td><td class="num">{{ number_format($value, 0, ',', ' ') }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="card">
        <div class="card-head"><h2><i class="bi bi-diagram-3"></i> Analyse ABC (chiffre d'affaires 90 jours)</h2></div>
        <p>A = 80% du CA cumulé · B = 15% suivants · C = le reste. Priorisez la disponibilité des A.</p>
        <div class="tbl-wrap">
            <table>
                <thead><tr><th>Produit</th><th class="num">CA 90j</th><th>Classe</th></tr></thead>
                <tbody>
                    @forelse ($abc->take(30) as $row)
                        <tr>
                            <td>{{ $row['product']->name }}</td>
                            <td class="num">{{ number_format($row['revenue'], 0, ',', ' ') }}</td>
                            <td>
                                <span class="badge {{ $row['class'] === 'A' ? 'badge-good' : ($row['class'] === 'B' ? 'badge-warn' : 'badge-neutral') }}">{{ $row['class'] }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr class="empty-row"><td colspan="3">Aucune vente sur la période.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2><i class="bi bi-hourglass-split"></i> Produits dormants (top 20 par valeur immobilisée)</h2></div>
        <div class="tbl-wrap">
            <table>
                <thead><tr><th>Produit</th><th class="num">Stock</th><th class="num">Valeur</th></tr></thead>
                <tbody>
                    @forelse ($dormant as $row)
                        <tr>
                            <td><a href="{{ route('products.show', $row['product']) }}">{{ $row['product']->name }}</a></td>
                            <td class="num">{{ rtrim(rtrim(number_format($row['product']->currentStock(), 2, ',', ' '), '0'), ',') }}</td>
                            <td class="num">{{ number_format($row['product']->currentStock() * $row['product']->purchase_price, 0, ',', ' ') }}</td>
                        </tr>
                    @empty
                        <tr class="empty-row"><td colspan="3">Aucun produit dormant détecté.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($overstock->isNotEmpty())
        <div class="card">
            <div class="card-head"><h2><i class="bi bi-boxes"></i> Produits en surstock</h2></div>
            <div class="tbl-wrap">
                <table>
                    <thead><tr><th>Produit</th><th class="num">Stock</th><th class="num">Stock max</th></tr></thead>
                    <tbody>
                        @foreach ($overstock as $product)
                            <tr>
                                <td><a href="{{ route('products.show', $product) }}">{{ $product->name }}</a></td>
                                <td class="num">{{ rtrim(rtrim(number_format($product->currentStock(), 2, ',', ' '), '0'), ',') }}</td>
                                <td class="num">{{ $product->max_stock }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if ($valueByCategory->isNotEmpty() || $abc->isNotEmpty())
        @php
            $abcByClass = $abc->groupBy('class')->map(fn ($g) => $g->sum('revenue'));
        @endphp
        <script src="{{ asset('js/vendor/chart.umd.min.js') }}"></script>
        <script>
            const qkPalette = ['#E85D1F', '#2451A3', '#16A34A', '#D97706', '#DC2626', '#78716C', '#9333EA', '#0EA5E9'];

            @if ($valueByCategory->isNotEmpty())
                new Chart(document.getElementById('valueByCategoryChart'), {
                    type: 'doughnut',
                    data: {
                        labels: @json($valueByCategory->keys()),
                        datasets: [{ data: @json($valueByCategory->values()), backgroundColor: qkPalette, borderWidth: 2, borderColor: '#fff' }],
                    },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right', labels: { boxWidth: 12 } } } },
                });
            @endif

            @if ($abc->isNotEmpty())
                new Chart(document.getElementById('abcChart'), {
                    type: 'bar',
                    data: {
                        labels: ['A', 'B', 'C'],
                        datasets: [{
                            label: "Chiffre d'affaires 90j",
                            data: [@json($abcByClass->get('A', 0)), @json($abcByClass->get('B', 0)), @json($abcByClass->get('C', 0))],
                            backgroundColor: ['#16A34A', '#D97706', '#78716C'],
                            borderRadius: 6,
                        }],
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: { y: { beginAtZero: true, ticks: { callback: (v) => v.toLocaleString('fr-FR') } } },
                    },
                });
            @endif
        </script>
    @endif
</x-layout>
