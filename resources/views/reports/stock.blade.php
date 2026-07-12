<x-layout title="Rapports stock">
    <div class="page-head">
        <div>
            <h1>Rapports stock</h1>
            <p>Valorisation, rotation, analyse ABC — basé sur les 90 derniers jours de ventes.</p>
        </div>
    </div>

    <div class="stat-grid">
        <div class="stat-tile">
            <div class="lbl">Valeur immobilisée</div>
            <div class="val">{{ number_format($totalValue, 0, ',', ' ') }}</div>
            <div class="sub">stock courant × CUMP</div>
        </div>
        <div class="stat-tile @if($stockoutRate > 5) crit @endif">
            <div class="lbl">Taux de rupture (approx.)</div>
            <div class="val">{{ $stockoutRate }}%</div>
            <div class="sub">{{ $stockoutCount }} produit(s) actifs à stock ≤ 0</div>
        </div>
        <div class="stat-tile @if($overstock->count() > 0) warn @endif">
            <div class="lbl">Produits en surstock</div>
            <div class="val">{{ $overstock->count() }}</div>
        </div>
        <div class="stat-tile @if($dormant->count() > 0) warn @endif">
            <div class="lbl">Produits dormants</div>
            <div class="val">{{ $dormant->count() }}</div>
            <div class="sub">aucune vente depuis 90 jours</div>
        </div>
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
            <div class="card-head"><h2>Valeur du stock par entrepôt</h2></div>
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
        <div class="card-head"><h2>Analyse ABC (chiffre d'affaires 90 jours)</h2></div>
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
        <div class="card-head"><h2>Produits dormants (top 20 par valeur immobilisée)</h2></div>
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
            <div class="card-head"><h2>Produits en surstock</h2></div>
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
</x-layout>
