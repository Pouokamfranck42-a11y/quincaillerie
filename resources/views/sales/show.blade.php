<x-layout :title="'Vente #'.$sale->id">
    <div class="page-head">
        <div>
            <h1><i class="bi bi-receipt text-primary"></i> Vente #{{ $sale->id }}</h1>
            <p>{{ $sale->created_at->format('d/m/Y H:i') }} · {{ $sale->user?->name ?? 'Vente en ligne' }} · {{ $sale->customer?->name ?? 'Client de passage' }}</p>
        </div>
        <div class="flex">
            <a href="{{ route('sales.print', $sale) }}" class="btn" target="_blank"><i class="bi bi-printer"></i> Bon de livraison</a>
            @if ($sale->invoices->isNotEmpty())
                <a href="{{ route('invoices.show', $sale->invoices->first()) }}" class="btn" target="_blank"><i class="bi bi-file-earmark-text"></i> Voir la facture {{ $sale->invoices->first()->number }}</a>
            @else
                <form method="POST" action="{{ route('invoices.store', $sale) }}" target="_blank">
                    @csrf
                    <button type="submit" class="btn btn-primary"><i class="bi bi-file-earmark-plus"></i> Générer la facture</button>
                </form>
            @endif
            @if ($sale->status === 'completed' && $sale->session?->status === 'open')
                <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#confirm-cancel-sale"><i class="bi bi-x-circle"></i> Annuler la vente</button>
                <form method="POST" action="{{ route('sales.cancel', $sale) }}" id="cancel-sale-{{ $sale->id }}">
                    @csrf
                </form>
                <x-confirm-modal id="confirm-cancel-sale" title="Annuler cette vente ?" body="Le stock de chaque ligne encore non retournée sera réintégré. Cette action est tracée dans le journal d'audit.">
                    <button type="submit" form="cancel-sale-{{ $sale->id }}" class="btn btn-danger"><i class="bi bi-x-circle"></i> Confirmer l'annulation</button>
                </x-confirm-modal>
            @endif
        </div>
    </div>

    @error('sale') <div class="alert alert-crit"><i class="bi bi-exclamation-triangle-fill"></i> <span>{{ $message }}</span></div> @enderror

    @if ($sale->status === 'cancelled')
        <div class="alert alert-crit"><i class="bi bi-x-circle-fill"></i> <span>Vente annulée le {{ $sale->cancelled_at?->format('d/m/Y H:i') }} — stock réintégré.</span></div>
    @endif

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
                                <div class="table-actions">
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
                                    @can('sav.gerer')
                                        <a href="{{ route('service-tickets.create', [$sale, $line]) }}" class="btn btn-sm btn-ghost"><i class="bi bi-tools"></i> SAV</a>
                                    @endcan
                                </div>
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
            @if ($sale->amount_tendered !== null)
                <div class="row"><span>Montant reçu</span><span>{{ number_format($sale->amount_tendered, 0, ',', ' ') }}</span></div>
                <div class="row"><span>Monnaie rendue</span><span>{{ number_format($sale->change_due, 0, ',', ' ') }}</span></div>
            @endif
        </div>
    </div>
</x-layout>
