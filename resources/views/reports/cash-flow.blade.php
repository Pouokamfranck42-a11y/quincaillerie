<x-layout title="Prévision de trésorerie">
    <div class="page-head">
        <div>
            <h1><i class="bi bi-cash-coin text-primary"></i> Prévision de trésorerie</h1>
            <p>Projection à 30 / 60 / 90 jours — encaissements attendus moins décaissements attendus.</p>
        </div>
    </div>

    <div class="alert" style="background:var(--secondary-soft); color:var(--secondary-dark); border-color:#C3D4EF; border-left-color:var(--secondary)">
        <i class="bi bi-info-circle-fill"></i>
        <span>Approximation : les ventes cash sont extrapolées sur la moyenne des 30 derniers jours, les encaissements clients sur les échéances de crédit connues,
        et les décaissements fournisseurs sur une hypothèse de règlement à 30 jours après commande (aucun suivi de paiement fournisseur n'existe dans l'application).</span>
    </div>

    <div class="stat-grid">
        @foreach ($projections as $p)
            <div class="stat-tile @if($p['net'] < 0) crit @else good @endif">
                <div class="lbl"><i class="bi bi-calendar-range"></i> À {{ $p['days'] }} jours</div>
                <div class="val">{{ number_format($p['net'], 0, ',', ' ') }}</div>
                <div class="sub">solde net projeté (FCFA)</div>
            </div>
        @endforeach
    </div>

    <div class="card">
        <div class="card-head"><h2><i class="bi bi-bar-chart-steps"></i> Détail par période</h2></div>
        <div style="height:280px"><canvas id="cashflowChart"></canvas></div>

        <div class="tbl-wrap" style="margin-top:18px">
            <table>
                <thead><tr><th>Période</th><th class="num">Ventes cash</th><th class="num">Recouvrements crédit</th><th class="num">Décaissements fournisseurs</th><th class="num">Solde net</th></tr></thead>
                <tbody>
                    @foreach ($projections as $p)
                        <tr>
                            <td>{{ $p['days'] }} jours</td>
                            <td class="num">{{ number_format($p['cash_inflow'], 0, ',', ' ') }}</td>
                            <td class="num">{{ number_format($p['credit_collections'], 0, ',', ' ') }}</td>
                            <td class="num">{{ number_format($p['outflow'], 0, ',', ' ') }}</td>
                            <td class="num" style="{{ $p['net'] < 0 ? 'color:var(--crit-dark)' : 'color:var(--good-dark)' }}"><strong>{{ number_format($p['net'], 0, ',', ' ') }}</strong></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <script src="{{ asset('js/vendor/chart.umd.min.js') }}"></script>
    <script>
        new Chart(document.getElementById('cashflowChart'), {
            type: 'bar',
            data: {
                labels: @json($projections->map(fn ($p) => 'À '.$p['days'].' jours')),
                datasets: [
                    { label: 'Encaissements', data: @json($projections->pluck('inflow')), backgroundColor: '#16A34A', borderRadius: 6 },
                    { label: 'Décaissements', data: @json($projections->pluck('outflow')), backgroundColor: '#DC2626', borderRadius: 6 },
                ],
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { position: 'top' } },
                scales: { y: { beginAtZero: true, ticks: { callback: (v) => v.toLocaleString('fr-FR') } } },
            },
        });
    </script>
</x-layout>
