<x-layout title="Réapprovisionnement intelligent">
    <div class="page-head">
        <div>
            <h1><i class="bi bi-robot text-primary"></i> Réapprovisionnement intelligent</h1>
            <p>Priorisé par urgence réelle — date de rupture prévue et date limite de commande, calculée à partir du délai de livraison de chaque fournisseur.</p>
        </div>
        <a href="{{ route('purchase-orders.suggestions') }}" class="btn btn-primary"><i class="bi bi-cart-plus"></i> Créer les commandes</a>
    </div>

    @if ($summary)
        <div class="card" style="background:var(--primary-soft, #EEF2FF); border:1px solid var(--primary-light, #C7D2FE)">
            <div class="card-head"><h2 style="font-size:15px"><i class="bi bi-stars"></i> Résumé</h2></div>
            <p class="mt-0" style="white-space:pre-line">{{ $summary }}</p>
        </div>
    @elseif ($suggestions->isNotEmpty())
        <div class="alert alert-warn"><i class="bi bi-info-circle"></i> <span>Résumé IA momentanément indisponible — la liste ci-dessous reste à jour et fiable.</span></div>
    @endif

    <div class="tbl-wrap">
        <table>
            <thead>
                <tr>
                    <th></th><th>Produit</th><th>Fournisseur</th>
                    <th class="num">Disponible</th>
                    <th>Rupture prévue</th>
                    <th>Commander avant le</th>
                    <th>Signal</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($suggestions as $row)
                    <tr @if($row['urgent']) style="background:var(--crit-soft, #FEE2E2)" @endif>
                        <td>{{ $row['urgent'] ? '🔴' : '🟡' }}</td>
                        <td><a href="{{ route('products.show', $row['product']) }}">{{ $row['product']->name }}</a></td>
                        <td class="muted">{{ $row['product']->supplier->name }}</td>
                        <td class="num">{{ rtrim(rtrim(number_format($row['product']->availableStock(), 2, ',', ' '), '0'), ',') }} {{ $row['product']->unit }}</td>
                        <td class="muted">{{ $row['stockout_date']?->format('d/m/Y') ?? '—' }}</td>
                        <td>
                            @if ($row['order_by_date'])
                                <strong @if($row['urgent']) style="color:var(--crit, #DC2626)" @endif>{{ $row['order_by_date']->format('d/m/Y') }}</strong>
                            @else
                                <span class="muted">dès que possible</span>
                            @endif
                        </td>
                        <td class="muted" style="font-size:12px">{{ $row['seasonality'] }}</td>
                    </tr>
                @empty
                    <tr class="empty-row"><td colspan="7"><i class="bi bi-check-circle"></i> Aucun réapprovisionnement nécessaire pour l'instant.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layout>
