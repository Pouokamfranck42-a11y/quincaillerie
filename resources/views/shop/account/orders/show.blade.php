<x-shop-layout :title="'Commande #'.$order->id">
    <a href="{{ route('shop.account.orders.index') }}" class="btn btn-ghost btn-sm" style="margin-bottom:16px"><i class="bi bi-arrow-left"></i> Mes commandes</a>

    <div class="page-head">
        <div>
            <h1><i class="bi bi-box-seam text-primary"></i> Commande #{{ $order->id }}</h1>
            <p>Passée le {{ $order->created_at->format('d/m/Y à H:i') }}</p>
        </div>
        <x-shop-order-status :status="$order->status" />
    </div>

    @if ($order->status === 'reservee')
        @php $pendingPayment = $order->payments->firstWhere('status', 'pending'); @endphp
        @if ($pendingPayment)
            <div class="alert alert-warn">
                <i class="bi bi-info-circle"></i>
                <span>Paiement Mobile Money en attente de confirmation. Vérifiez votre téléphone.</span>
            </div>
            @if (config('services.payment.mode') === 'simulation')
                <form method="POST" action="{{ route('shop.payment.simulate', $order) }}" style="margin-bottom:16px">
                    @csrf
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle"></i> Simuler le paiement (mode test)</button>
                </form>
            @endif
        @else
            <div class="alert alert-warn">
                <i class="bi bi-info-circle"></i>
                <span>Commande réservée — le paiement se fera à la livraison ou au retrait.</span>
            </div>
        @endif
    @endif

    <div class="card">
        <div class="card-head"><h2><i class="bi bi-truck"></i> {{ $order->fulfillment_type === 'livraison' ? 'Livraison' : 'Retrait en magasin' }}</h2></div>
        @if ($order->fulfillment_type === 'livraison')
            <p class="mt-0"><i class="bi bi-geo-alt"></i> {{ $order->delivery_address ?: '—' }}</p>
        @endif
        <p><i class="bi bi-telephone"></i> {{ $order->delivery_phone }}</p>
        @if ($order->delivery_notes)
            <p class="muted">{{ $order->delivery_notes }}</p>
        @endif
    </div>

    <div class="card">
        <div class="card-head"><h2><i class="bi bi-receipt"></i> Articles</h2></div>
        <div class="tbl-wrap">
            <table>
                <thead><tr><th>Produit</th><th class="num">Quantité</th><th class="num">Prix unitaire</th><th class="num">Total</th></tr></thead>
                <tbody>
                    @foreach ($order->lines as $line)
                        <tr>
                            <td>{{ $line->product->name }}</td>
                            <td class="num">{{ rtrim(rtrim(number_format($line->quantity, 2, ',', ' '), '0'), ',') }}</td>
                            <td class="num">{{ number_format($line->unit_price, 0, ',', ' ') }}</td>
                            <td class="num">{{ number_format($line->quantity * $line->unit_price, 0, ',', ' ') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="cart-totals">
            <div class="row"><span>Sous-total</span><span>{{ number_format($order->subtotal, 0, ',', ' ') }}</span></div>
            <div class="row"><span>TVA</span><span>{{ number_format($order->tax_amount, 0, ',', ' ') }}</span></div>
            <div class="row total"><span>Total</span><span>{{ number_format($order->total, 0, ',', ' ') }} FCFA</span></div>
        </div>
    </div>
</x-shop-layout>
