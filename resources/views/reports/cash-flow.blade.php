<x-layout title="Prévision de trésorerie">
    <div class="page-head">
        <div>
            <h1>Prévision de trésorerie</h1>
            <p>Projection à 30 / 60 / 90 jours — encaissements attendus moins décaissements attendus.</p>
        </div>
    </div>

    <div class="alert alert-good" style="background:var(--steel-100); color:var(--ink-soft); border:1px solid var(--steel-200)">
        Approximation : les ventes cash sont extrapolées sur la moyenne des 30 derniers jours, les encaissements clients sur les échéances de crédit connues,
        et les décaissements fournisseurs sur une hypothèse de règlement à 30 jours après commande (aucun suivi de paiement fournisseur n'existe dans l'application).
    </div>

    <div class="stat-grid">
        @foreach ($projections as $p)
            <div class="stat-tile @if($p['net'] < 0) crit @endif">
                <div class="lbl">À {{ $p['days'] }} jours</div>
                <div class="val">{{ number_format($p['net'], 0, ',', ' ') }}</div>
                <div class="sub">solde net projeté (FCFA)</div>
            </div>
        @endforeach
    </div>

    <div class="card">
        <div class="card-head"><h2>Détail par période</h2></div>
        @foreach ($projections as $p)
            @php $max = max($p['inflow'], $p['outflow'], 1); @endphp
            <div class="cashflow-row">
                <div class="lbl"><strong>À {{ $p['days'] }} jours</strong><span>encaissements {{ number_format($p['inflow'], 0, ',', ' ') }} · décaissements {{ number_format($p['outflow'], 0, ',', ' ') }}</span></div>
                <div class="cashflow-bar-track">
                    <div class="cashflow-bar-in" style="width:{{ round($p['inflow'] / $max * 100) }}%"></div>
                </div>
                <div class="cashflow-bar-track" style="margin-top:4px">
                    <div class="cashflow-bar-out" style="width:{{ round($p['outflow'] / $max * 100) }}%"></div>
                </div>
            </div>
        @endforeach

        <div class="tbl-wrap" style="margin-top:10px">
            <table>
                <thead><tr><th>Période</th><th class="num">Ventes cash</th><th class="num">Recouvrements crédit</th><th class="num">Décaissements fournisseurs</th><th class="num">Solde net</th></tr></thead>
                <tbody>
                    @foreach ($projections as $p)
                        <tr>
                            <td>{{ $p['days'] }} jours</td>
                            <td class="num">{{ number_format($p['cash_inflow'], 0, ',', ' ') }}</td>
                            <td class="num">{{ number_format($p['credit_collections'], 0, ',', ' ') }}</td>
                            <td class="num">{{ number_format($p['outflow'], 0, ',', ' ') }}</td>
                            <td class="num @if($p['net'] < 0) text-crit @endif"><strong>{{ number_format($p['net'], 0, ',', ' ') }}</strong></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-layout>
