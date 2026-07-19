<x-layout :title="'Relevé — '.$customer->name">
    <div class="page-head">
        <div>
            <h1><i class="bi bi-file-earmark-text text-primary"></i> Relevé de compte — {{ $customer->name }}</h1>
            <p>Plafond de crédit {{ number_format($customer->credit_limit, 0, ',', ' ') }} FCFA · délai de paiement {{ $customer->payment_terms_days }} jours</p>
        </div>
        <a href="{{ route('customers.edit', $customer) }}" class="btn"><i class="bi bi-pencil-square"></i> Modifier le client</a>
    </div>

    <div class="stat-grid">
        <div class="stat-tile @if($customer->outstandingBalance() > 0) warn @endif">
            <div class="lbl"><i class="bi bi-cash-stack"></i> Encours actuel</div>
            <div class="val">{{ number_format($customer->outstandingBalance(), 0, ',', ' ') }}</div>
        </div>
        <div class="stat-tile good">
            <div class="lbl"><i class="bi bi-wallet2"></i> Crédit disponible</div>
            <div class="val">{{ number_format($customer->availableCredit(), 0, ',', ' ') }}</div>
        </div>
        @if (config('company.loyalty.enabled'))
            <div class="stat-tile">
                <div class="lbl"><i class="bi bi-star"></i> Points fidélité</div>
                <div class="val">{{ $customer->loyaltyPoints() }}</div>
                <div class="sub">≈ {{ number_format($customer->loyaltyPoints() * (float) config('company.loyalty.redeem_value'), 0, ',', ' ') }} FCFA</div>
            </div>
        @endif
    </div>

    <div class="card">
        <div class="card-head"><h2><i class="bi bi-exclamation-circle"></i> Ventes à crédit non soldées</h2></div>
        <div class="tbl-wrap">
            <table>
                <thead><tr><th>Vente</th><th>Date</th><th>Échéance</th><th class="num">Total</th><th class="num">Payé</th><th class="num">Reste dû</th><th></th></tr></thead>
                <tbody>
                    @forelse ($dueSales as $sale)
                        @php $remaining = (float) $sale->total - (float) $sale->paid_amount; @endphp
                        <tr>
                            <td class="mono">#{{ $sale->id }}</td>
                            <td class="muted">{{ $sale->created_at->format('d/m/Y') }}</td>
                            <td class="muted">
                                {{ $sale->due_date?->format('d/m/Y') }}
                                @if ($sale->due_date && $sale->due_date->isPast())
                                    <span class="badge badge-crit">en retard</span>
                                @endif
                            </td>
                            <td class="num">{{ number_format($sale->total, 0, ',', ' ') }}</td>
                            <td class="num">{{ number_format($sale->paid_amount, 0, ',', ' ') }}</td>
                            <td class="num">{{ number_format($remaining, 0, ',', ' ') }}</td>
                            <td>
                                <form method="POST" action="{{ route('customers.record-payment', [$customer, $sale]) }}" class="flex">
                                    @csrf
                                    <input type="number" step="1" min="1" max="{{ $remaining }}" name="amount" placeholder="Montant" style="width:110px" required>
                                    <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-cash"></i> Encaisser</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr class="empty-row"><td colspan="7">Aucune vente à crédit en cours pour ce client.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layout>
