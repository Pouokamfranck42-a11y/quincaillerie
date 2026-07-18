<x-shop-layout title="Mes commandes">
    <div class="page-head">
        <h1><i class="bi bi-box-seam text-primary"></i> Mes commandes</h1>
    </div>

    <div class="tbl-wrap">
        <table>
            <thead><tr><th>Commande</th><th>Date</th><th>Statut</th><th>Réception</th><th class="num">Total</th><th></th></tr></thead>
            <tbody>
                @forelse ($orders as $order)
                    <tr>
                        <td class="mono">#{{ $order->id }}</td>
                        <td class="muted">{{ $order->created_at->format('d/m/Y H:i') }}</td>
                        <td><x-shop-order-status :status="$order->status" /></td>
                        <td class="muted">{{ $order->fulfillment_type === 'livraison' ? 'Livraison' : 'Retrait en magasin' }}</td>
                        <td class="num">{{ number_format($order->total, 0, ',', ' ') }} FCFA</td>
                        <td><a href="{{ route('shop.account.orders.show', $order) }}" class="btn btn-sm">Détails</a></td>
                    </tr>
                @empty
                    <tr class="empty-row"><td colspan="6"><i class="bi bi-inbox"></i> Aucune commande.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:16px">{{ $orders->links() }}</div>
</x-shop-layout>
