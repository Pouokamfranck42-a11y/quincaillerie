<x-layout :title="'Commande #'.$purchaseOrder->id">
    <div class="page-head">
        <div>
            <h1><i class="bi bi-cart3 text-primary"></i> Commande fournisseur #{{ $purchaseOrder->id }}</h1>
            <p>{{ $purchaseOrder->supplier->name }} · créée le {{ $purchaseOrder->created_at->format('d/m/Y') }} par {{ $purchaseOrder->user?->name ?? '—' }}</p>
        </div>
        @if ($purchaseOrder->status === 'draft')
            <div class="flex">
                <a href="{{ route('purchase-orders.edit', $purchaseOrder) }}" class="btn"><i class="bi bi-pencil-square"></i> Modifier les lignes</a>
                <form method="POST" action="{{ route('purchase-orders.place', $purchaseOrder) }}">
                    @csrf
                    <button type="submit" class="btn btn-primary"><i class="bi bi-send-check"></i> Passer la commande</button>
                </form>
            </div>
        @elseif ($purchaseOrder->status === 'received')
            <span class="badge badge-good">réceptionnée le {{ $purchaseOrder->received_at->format('d/m/Y') }}</span>
        @elseif ($purchaseOrder->status === 'partiellement_recu')
            <span class="badge badge-warn">partiellement reçue</span>
        @endif
    </div>

    <div class="card">
        <div class="tbl-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Produit</th><th class="num">Commandé</th><th class="num">Reçu</th><th class="num">Reliquat</th>
                        <th class="num">Prix unitaire</th><th class="num">Total</th>
                        @if (in_array($purchaseOrder->status, ['ordered', 'partiellement_recu']))
                            <th class="num">À réceptionner</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($purchaseOrder->lines as $line)
                        <tr>
                            <td>{{ $line->product->name }}</td>
                            <td class="num">{{ rtrim(rtrim(number_format($line->quantity, 2, ',', ' '), '0'), ',') }}</td>
                            <td class="num">{{ rtrim(rtrim(number_format($line->received_quantity, 2, ',', ' '), '0'), ',') }}</td>
                            <td class="num">
                                {{ rtrim(rtrim(number_format($line->remaining(), 2, ',', ' '), '0'), ',') }}
                                @if ($line->remaining() > 0 && in_array($purchaseOrder->status, ['ordered', 'partiellement_recu']))
                                    <span class="badge badge-warn">reliquat</span>
                                @endif
                            </td>
                            <td class="num">{{ number_format($line->unit_price, 0, ',', ' ') }}</td>
                            <td class="num">{{ number_format($line->quantity * $line->unit_price, 0, ',', ' ') }}</td>
                            @if (in_array($purchaseOrder->status, ['ordered', 'partiellement_recu']))
                                <td class="num">
                                    @if ($line->remaining() > 0)
                                        <input type="number" form="receive-form" name="quantities[{{ $line->id }}]" step="0.01" min="0" max="{{ $line->remaining() }}" value="{{ $line->remaining() }}" style="width:100px; text-align:right">
                                    @else
                                        —
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="cart-totals">
            <div class="row"><span>Sous-total lignes</span><span>{{ number_format($purchaseOrder->total(), 0, ',', ' ') }}</span></div>
            @if ($purchaseOrder->extra_costs > 0)
                <div class="row"><span>Frais annexes</span><span>{{ number_format($purchaseOrder->extra_costs, 0, ',', ' ') }}</span></div>
            @endif
            <div class="row total"><span>Coût de revient total</span><span>{{ number_format($purchaseOrder->total() + $purchaseOrder->extra_costs, 0, ',', ' ') }} FCFA</span></div>
        </div>

        @if ($purchaseOrder->notes)
            <p style="margin-top:16px"><strong>Notes :</strong> {{ $purchaseOrder->notes }}</p>
        @endif

        @if (in_array($purchaseOrder->status, ['ordered', 'partiellement_recu']))
            <form id="receive-form" method="POST" action="{{ route('purchase-orders.receive', $purchaseOrder) }}">
                @csrf
                <div class="form-actions">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#confirm-receive"><i class="bi bi-box-arrow-in-down"></i> Réceptionner les quantités saisies</button>
                </div>
            </form>
            <x-confirm-modal id="confirm-receive" title="Confirmer la réception ?" body="Le stock sera mis à jour avec les quantités saisies.">
                <button type="submit" form="receive-form" class="btn btn-primary"><i class="bi bi-check-lg"></i> Confirmer</button>
            </x-confirm-modal>
        @endif
    </div>

    @if ($purchaseOrder->lines->contains(fn ($line) => $line->returnableQuantity() > 0))
        <div class="card">
            <div class="card-head"><h2><i class="bi bi-arrow-return-left"></i> Retour fournisseur</h2></div>
            @foreach ($purchaseOrder->lines as $line)
                @if ($line->returnableQuantity() > 0)
                    <form method="POST" action="{{ route('purchase-orders.return-line', [$purchaseOrder, $line]) }}" id="return-form-{{ $line->id }}" class="flex" style="margin-bottom:8px">
                        @csrf
                        <span style="flex:1">{{ $line->product->name }} <span class="muted">(reçu : {{ rtrim(rtrim(number_format($line->returnableQuantity(), 2, ',', ' '), '0'), ',') }})</span></span>
                        <input type="number" step="0.01" min="0.01" max="{{ $line->returnableQuantity() }}" name="quantity" placeholder="Qté à retourner" style="width:140px" required>
                        <button type="button" class="btn btn-sm" data-bs-toggle="modal" data-bs-target="#confirm-return-{{ $line->id }}"><i class="bi bi-arrow-return-left"></i> Retourner</button>
                    </form>
                    <x-confirm-modal id="confirm-return-{{ $line->id }}" title="Confirmer le retour fournisseur ?" body="Le stock sera sorti pour la quantité saisie.">
                        <button type="submit" form="return-form-{{ $line->id }}" class="btn btn-primary"><i class="bi bi-check-lg"></i> Confirmer</button>
                    </x-confirm-modal>
                @endif
            @endforeach
        </div>
    @endif
</x-layout>
