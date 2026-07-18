<x-layout title="Ventes">
    <div class="page-head">
        <div>
            <h1><i class="bi bi-receipt text-primary"></i> Ventes</h1>
            <p>Historique des tickets de caisse.</p>
        </div>
    </div>

    <div class="tbl-wrap">
        <table>
            <thead><tr><th>N°</th><th>Date</th><th>Caissier</th><th>Client</th><th class="num">Articles</th><th class="num">Total</th><th>Paiement</th><th></th></tr></thead>
            <tbody>
                @forelse ($sales as $sale)
                    <tr>
                        <td class="mono">#{{ $sale->id }}</td>
                        <td class="muted">{{ $sale->created_at->format('d/m/Y H:i') }}</td>
                        <td>{{ $sale->user?->name ?? 'Vente en ligne' }}</td>
                        <td class="muted">{{ $sale->customer?->name ?? 'Client de passage' }}</td>
                        <td class="num">{{ $sale->lines_count }}</td>
                        <td class="num">{{ number_format($sale->total, 0, ',', ' ') }}</td>
                        <td>
                            @if ($sale->payment_status === 'due') <span class="badge badge-warn">à crédit</span>
                            @else <span class="badge badge-good">{{ $sale->payment_method }}</span>
                            @endif
                        </td>
                        <td><a href="{{ route('sales.show', $sale) }}" class="btn btn-sm"><i class="bi bi-eye"></i> Voir</a></td>
                    </tr>
                @empty
                    <tr class="empty-row"><td colspan="8"><i class="bi bi-inbox"></i> Aucune vente enregistrée.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:16px">{{ $sales->links() }}</div>
</x-layout>
