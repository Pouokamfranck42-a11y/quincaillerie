<x-layout :title="'Vente #'.$sale->id">
    <div class="page-head">
        <div>
            <h1><i class="bi bi-receipt text-primary"></i> Vente #{{ $sale->id }}</h1>
            <p>{{ $sale->created_at->format('d/m/Y H:i') }} · {{ $sale->user->name }} · {{ $sale->customer?->name ?? 'Client de passage' }}</p>
        </div>
        <a href="{{ route('sales.print', $sale) }}" class="btn" target="_blank"><i class="bi bi-printer"></i> Imprimer le bon de livraison</a>
    </div>

    <div class="card">
        <div class="tbl-wrap">
            <table>
                <thead><tr><th>Produit</th><th class="num">Quantité</th><th class="num">Prix</th><th class="num">Total</th><th class="num">Retourné</th><th></th></tr></thead>
                <tbody>
                    @foreach ($sale->lines as $line)
                        <tr>
                            <td>{{ $line->product->name }}</td>
                            <td class="num">{{ rtrim(rtrim(number_format($line->quantity, 2, ',', ' '), '0'), ',') }}</td>
                            <td class="num">{{ number_format($line->unit_price, 0, ',', ' ') }}</td>
                            <td class="num">{{ number_format($line->lineTotal(), 0, ',', ' ') }}</td>
                            <td class="num">{{ $line->returned_quantity > 0 ? rtrim(rtrim(number_format($line->returned_quantity, 2, ',', ' '), '0'), ',') : '—' }}</td>
                            <td>
                                @if ($line->returnableQuantity() > 0)
                                    <form method="POST" action="{{ route('sales.return-line', [$sale, $line]) }}" id="return-sale-line-{{ $line->id }}" class="flex">
                                        @csrf
                                        <input type="number" step="0.01" min="0.01" max="{{ $line->returnableQuantity() }}" name="quantity" placeholder="Qté" style="width:80px" required>
                                        <button type="button" class="btn btn-sm" data-bs-toggle="modal" data-bs-target="#confirm-return-sale-{{ $line->id }}"><i class="bi bi-arrow-return-left"></i> Retourner</button>
                                    </form>
                                    <x-confirm-modal id="confirm-return-sale-{{ $line->id }}" title="Confirmer le retour ?" body="Le stock sera réintégré pour la quantité saisie.">
                                        <button type="submit" form="return-sale-line-{{ $line->id }}" class="btn btn-primary"><i class="bi bi-check-lg"></i> Confirmer</button>
                                    </x-confirm-modal>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="cart-totals">
            <div class="row"><span>Sous-total</span><span>{{ number_format($sale->subtotal, 0, ',', ' ') }}</span></div>
            <div class="row"><span>TVA</span><span>{{ number_format($sale->tax_amount, 0, ',', ' ') }}</span></div>
            <div class="row total"><span>Total</span><span>{{ number_format($sale->total, 0, ',', ' ') }} FCFA</span></div>
        </div>
    </div>
</x-layout>
