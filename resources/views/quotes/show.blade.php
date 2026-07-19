<x-layout :title="'Devis #'.$quote->id">
    <div class="page-head">
        <div>
            <h1><i class="bi bi-file-earmark-text text-primary"></i> Devis #{{ $quote->id }}</h1>
            <p>{{ $quote->customer?->name ?? 'Client de passage' }} · créé le {{ $quote->created_at->format('d/m/Y') }} par {{ $quote->user->name }}
                @if ($quote->valid_until) · valable jusqu'au {{ $quote->valid_until->format('d/m/Y') }} @endif
            </p>
        </div>
        <div class="flex">
            <a href="{{ route('quotes.print', $quote) }}" class="btn" target="_blank"><i class="bi bi-printer"></i> Imprimer</a>
            @if ($quote->status === 'converti')
                @if ($quote->sale_id)
                    <span class="badge badge-good">converti en vente #{{ $quote->sale_id }}</span>
                @elseif ($quote->order_id)
                    <span class="badge badge-good">
                        converti en commande #{{ $quote->order_id }}
                        @can('ecommerce.commandes')
                            · <a href="{{ route('online-orders.show', $quote->order_id) }}">voir</a>
                        @endcan
                    </span>
                @endif
            @endif
        </div>
    </div>

    @error('credit') <div class="alert alert-crit"><i class="bi bi-exclamation-triangle-fill"></i> <span>{{ $message }}</span></div> @enderror

    <div class="card">
        <div class="tbl-wrap">
            <table>
                <thead><tr><th>Produit</th><th class="num">Quantité</th><th class="num">Prix unitaire</th><th class="num">Total</th></tr></thead>
                <tbody>
                    @foreach ($quote->lines as $line)
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
            <div class="row"><span>Sous-total</span><span>{{ number_format($quote->subtotal, 0, ',', ' ') }}</span></div>
            <div class="row"><span>TVA ({{ rtrim(rtrim(number_format($quote->tax_rate, 2), '0'), '.') }}%)</span><span>{{ number_format($quote->tax_amount, 0, ',', ' ') }}</span></div>
            <div class="row total"><span>Total</span><span>{{ number_format($quote->total, 0, ',', ' ') }} FCFA</span></div>
        </div>

        @if ($quote->notes)
            <p style="margin-top:16px"><strong>Notes :</strong> {{ $quote->notes }}</p>
        @endif
    </div>

    @if ($quote->status !== 'converti')
        <div class="card">
            <div class="card-head"><h2><i class="bi bi-arrow-repeat"></i> Convertir en vente</h2></div>
            <p>Encaissement immédiat, rattaché à votre session de caisse actuellement ouverte — stock déduit tout de suite.</p>
            <form method="POST" action="{{ route('quotes.convert', $quote) }}" class="flex">
                @csrf
                <select name="payment_method" required>
                    <option value="especes">Espèces</option>
                    <option value="carte">Carte</option>
                    <option value="mobile">Mobile money</option>
                    @if ($quote->customer?->type === 'professionnel' && $quote->customer->credit_limit > 0)
                        <option value="credit">À crédit</option>
                    @endif
                </select>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Convertir en vente</button>
            </form>
        </div>

        <div class="card" x-data="{ fulfillment: 'retrait' }">
            <div class="card-head"><h2><i class="bi bi-box-seam"></i> Convertir en commande</h2></div>
            @if (! $quote->customer_id)
                <p class="muted"><i class="bi bi-exclamation-triangle"></i> Un client est requis sur ce devis pour cette option (contrairement à la vente comptoir).</p>
            @else
                <p>Réserve le stock sans l'encaisser ni le déduire tout de suite — le client paiera à la remise (retrait ou livraison), comme une commande boutique. Suivi ensuite depuis "Commandes en ligne".</p>
                @error('quote') <div class="alert alert-crit"><i class="bi bi-exclamation-triangle-fill"></i> <span>{{ $message }}</span></div> @enderror
                <form method="POST" action="{{ route('quotes.convert-to-order', $quote) }}">
                    @csrf
                    <div class="field-row">
                        <div class="field">
                            <label for="fulfillment_type">Remise</label>
                            <select id="fulfillment_type" name="fulfillment_type" x-model="fulfillment">
                                <option value="retrait">Retrait en magasin</option>
                                <option value="livraison">Livraison</option>
                            </select>
                        </div>
                        <div class="field">
                            <label for="delivery_phone">Téléphone (optionnel)</label>
                            <input type="text" id="delivery_phone" name="delivery_phone" value="{{ $quote->customer->phone }}">
                        </div>
                    </div>
                    <div class="field" x-show="fulfillment === 'livraison'">
                        <label for="delivery_address">Adresse de livraison</label>
                        <textarea id="delivery_address" name="delivery_address" rows="2">{{ $quote->customer->address }}</textarea>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-box-seam"></i> Convertir en commande</button>
                </form>
            @endif
        </div>
    @endif
</x-layout>
