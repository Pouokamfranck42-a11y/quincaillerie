<x-layout :title="'Commande en ligne #'.$order->id">
    <a href="{{ route('online-orders.index') }}" class="btn btn-ghost btn-sm" style="margin-bottom:16px"><i class="bi bi-arrow-left"></i> Commandes en ligne</a>

    <div class="page-head">
        <div>
            <h1><i class="bi bi-globe2 text-primary"></i> Commande #{{ $order->id }}</h1>
            <p>{{ $order->customer->name }} · {{ $order->created_at->format('d/m/Y H:i') }}</p>
        </div>
        <x-shop-order-status :status="$order->status" />
    </div>

    @if ($order->status === 'reservee' && $order->payment_method === 'a_la_livraison')
        <div class="alert alert-warn">
            <i class="bi bi-cash-coin"></i>
            <span>Paiement à la livraison — à confirmer une fois la marchandise remise et l'argent encaissé.</span>
        </div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#confirm-cod-{{ $order->id }}">
            <i class="bi bi-check-circle"></i> Confirmer le paiement à la livraison
        </button>
        <x-confirm-modal id="confirm-cod-{{ $order->id }}" title="Confirmer le paiement à la livraison ?" body="Le stock sera déduit et une vente sera enregistrée. À faire uniquement une fois l'argent réellement encaissé.">
            <form method="POST" action="{{ route('online-orders.confirm-cod', $order) }}">
                @csrf
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle"></i> Confirmer</button>
            </form>
        </x-confirm-modal>
    @endif

    @php
        $cancellable = in_array($order->status, ['reservee', 'payee', 'preparation', 'prete'], true);
    @endphp

    <div class="card">
        <div class="card-head"><h2><i class="bi bi-clipboard-check"></i> Suivi de la préparation</h2></div>
        <div class="table-actions">
            @if ($order->status === 'payee')
                <form method="POST" action="{{ route('online-orders.start-preparation', $order) }}">
                    @csrf
                    <button type="submit" class="btn btn-primary"><i class="bi bi-box-seam"></i> Passer en préparation</button>
                </form>
            @elseif ($order->status === 'preparation')
                <form method="POST" action="{{ route('online-orders.mark-ready', $order) }}">
                    @csrf
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check2-square"></i> Marquer prête</button>
                </form>
            @elseif ($order->status === 'prete' && $order->fulfillment_type === 'livraison')
                <form method="POST" action="{{ route('online-orders.deliver', $order) }}">
                    @csrf
                    <button type="submit" class="btn btn-primary"><i class="bi bi-truck"></i> Marquer livrée</button>
                </form>
            @elseif ($order->status === 'prete' && $order->fulfillment_type === 'retrait')
                <form method="POST" action="{{ route('online-orders.pick-up', $order) }}">
                    @csrf
                    <button type="submit" class="btn btn-primary"><i class="bi bi-bag-check"></i> Confirmer le retrait client</button>
                </form>
            @elseif (in_array($order->status, ['livree', 'retiree'], true))
                <span class="muted"><i class="bi bi-check-circle-fill text-success"></i> Commande terminée.</span>
            @elseif (in_array($order->status, ['annulee', 'retournee'], true))
                <span class="muted">Aucune action possible sur cette commande.</span>
            @else
                <span class="muted">En attente du paiement avant de pouvoir préparer la commande.</span>
            @endif

            @if ($cancellable)
                <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#cancel-order-{{ $order->id }}">
                    <i class="bi bi-x-circle"></i> Annuler la commande
                </button>
                <x-confirm-modal id="cancel-order-{{ $order->id }}" title="Annuler cette commande ?" body="Le stock sera réintégré si déjà déduit. Cette action est irréversible.">
                    <form method="POST" action="{{ route('online-orders.cancel', $order) }}">
                        @csrf
                        <button type="submit" class="btn btn-danger"><i class="bi bi-x-circle"></i> Annuler la commande</button>
                    </form>
                </x-confirm-modal>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2><i class="bi bi-truck"></i> {{ $order->fulfillment_type === 'livraison' ? 'Livraison' : 'Retrait en magasin' }}</h2></div>
        @if ($order->fulfillment_type === 'livraison')
            <p class="mt-0"><i class="bi bi-geo-alt"></i> {{ $order->delivery_address ?: '—' }}</p>
        @endif
        <p><i class="bi bi-telephone"></i> {{ $order->delivery_phone }}</p>
    </div>

    <div class="card">
        <div class="card-head"><h2><i class="bi bi-wallet2"></i> Paiements</h2></div>
        @if ($order->payments->isEmpty())
            <p class="mt-0 muted">Aucune tentative de paiement en ligne (paiement à la livraison).</p>
        @else
            <div class="tbl-wrap">
                <table>
                    <thead><tr><th>Fournisseur</th><th>Référence</th><th>Statut</th><th class="num">Montant</th></tr></thead>
                    <tbody>
                        @foreach ($order->payments as $payment)
                            <tr>
                                <td>{{ $payment->provider }}</td>
                                <td class="mono">{{ $payment->provider_reference }}</td>
                                <td>
                                    @if ($payment->status === 'success') <span class="badge badge-good">réussi</span>
                                    @elseif ($payment->status === 'failed') <span class="badge badge-crit">échoué</span>
                                    @else <span class="badge badge-warn">en attente</span>
                                    @endif
                                </td>
                                <td class="num">{{ number_format($payment->amount, 0, ',', ' ') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
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
            <div class="row total"><span>Total</span><span>{{ number_format($order->total, 0, ',', ' ') }} FCFA</span></div>
        </div>
    </div>
</x-layout>
