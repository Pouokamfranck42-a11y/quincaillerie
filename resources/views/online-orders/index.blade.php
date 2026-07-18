<x-layout title="Commandes en ligne">
    <div class="page-head">
        <div>
            <h1><i class="bi bi-globe2 text-primary"></i> Commandes en ligne</h1>
            <p>Commandes passées depuis la boutique — statuts et paiements.</p>
        </div>
    </div>

    <div class="tbl-wrap">
        <table>
            <thead><tr><th>Commande</th><th>Client</th><th>Date</th><th>Statut</th><th>Paiement</th><th class="num">Total</th><th></th></tr></thead>
            <tbody>
                @forelse ($orders as $order)
                    <tr>
                        <td class="mono">#{{ $order->id }}</td>
                        <td>{{ $order->customer->name }}</td>
                        <td class="muted">{{ $order->created_at->format('d/m/Y H:i') }}</td>
                        <td><x-shop-order-status :status="$order->status" /></td>
                        <td class="muted">{{ $order->payment_method }}</td>
                        <td class="num">{{ number_format($order->total, 0, ',', ' ') }}</td>
                        <td><a href="{{ route('online-orders.show', $order) }}" class="btn btn-sm">Détails</a></td>
                    </tr>
                @empty
                    <tr class="empty-row"><td colspan="7"><i class="bi bi-inbox"></i> Aucune commande en ligne pour l'instant.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:16px">{{ $orders->links() }}</div>
</x-layout>
