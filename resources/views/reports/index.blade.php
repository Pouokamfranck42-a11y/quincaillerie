<x-layout title="Rapports">
    <div class="page-head"><h1>Rapports</h1></div>

    <form method="GET" class="field-row" style="max-width:420px; margin-bottom:22px">
        <div class="field">
            <label for="from">Du</label>
            <input type="date" id="from" name="from" value="{{ $from->format('Y-m-d') }}">
        </div>
        <div class="field">
            <label for="to">Au</label>
            <input type="date" id="to" name="to" value="{{ $to->format('Y-m-d') }}">
        </div>
        <div class="field" style="flex:0; align-self:flex-end">
            <button type="submit" class="btn">Filtrer</button>
        </div>
    </form>

    <div class="stat-grid">
        <div class="stat-tile">
            <div class="lbl">Chiffre d'affaires</div>
            <div class="val">{{ number_format($totalSales, 0, ',', ' ') }}</div>
            <div class="sub">{{ $salesCount }} vente(s)</div>
        </div>
        <div class="stat-tile">
            <div class="lbl">TVA collectée</div>
            <div class="val">{{ number_format($totalTax, 0, ',', ' ') }}</div>
        </div>
        <div class="stat-tile">
            <div class="lbl">Marge brute (estimée)</div>
            <div class="val">{{ number_format($grossMargin, 0, ',', ' ') }}</div>
            <div class="sub">basée sur le prix d'achat courant des produits</div>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2>Ventes par jour</h2></div>
        <div class="tbl-wrap">
            <table>
                <thead><tr><th>Date</th><th class="num">Total</th></tr></thead>
                <tbody>
                    @forelse ($salesByDay as $day => $total)
                        <tr>
                            <td>{{ \Illuminate\Support\Carbon::parse($day)->format('d/m/Y') }}</td>
                            <td class="num">{{ number_format($total, 0, ',', ' ') }}</td>
                        </tr>
                    @empty
                        <tr class="empty-row"><td colspan="2">Aucune vente sur cette période.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2>Meilleures ventes (top 10)</h2></div>
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
</x-layout>
