<x-shop-layout title="Mon compte">
    <div class="page-head">
        <h1><i class="bi bi-person-circle text-primary"></i> Bonjour {{ $customer->name }}</h1>
    </div>

    <div class="card">
        <div class="card-head"><h2><i class="bi bi-box-seam"></i> Commandes récentes</h2></div>
        @if ($recentOrders->isEmpty())
            <p class="mt-0"><i class="bi bi-inbox"></i> Aucune commande pour l'instant.</p>
            <a href="{{ route('shop.catalog.index') }}" class="btn btn-primary"><i class="bi bi-shop"></i> Découvrir le catalogue</a>
        @else
            <div class="tbl-wrap">
                <table>
                    <thead><tr><th>Commande</th><th>Date</th><th>Statut</th><th class="num">Total</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($recentOrders as $order)
                            <tr>
                                <td class="mono">#{{ $order->id }}</td>
                                <td class="muted">{{ $order->created_at->format('d/m/Y H:i') }}</td>
                                <td><x-shop-order-status :status="$order->status" /></td>
                                <td class="num">{{ number_format($order->total, 0, ',', ' ') }} FCFA</td>
                                <td><a href="{{ route('shop.account.orders.show', $order) }}" class="btn btn-sm">Détails</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <a href="{{ route('shop.account.orders.index') }}" class="btn" style="margin-top:12px">Voir toutes mes commandes</a>
        @endif
    </div>
</x-shop-layout>
