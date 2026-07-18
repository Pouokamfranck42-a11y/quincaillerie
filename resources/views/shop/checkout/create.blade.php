<x-shop-layout title="Passer commande">
    <div class="page-head">
        <h1><i class="bi bi-bag-check text-primary"></i> Finaliser ma commande</h1>
    </div>

    <div class="shop-checkout-steps">
        <span class="step">Panier</span> → <span class="step active">Livraison &amp; paiement</span> → <span class="step">Confirmation</span>
    </div>

    <div class="shop-product">
        <div class="card">
            <div class="card-head"><h2><i class="bi bi-truck"></i> Livraison</h2></div>

            <form method="POST" action="{{ route('shop.checkout.store') }}">
                @csrf
                <div class="field">
                    <label>Mode de réception</label>
                    <div class="flex" style="gap:16px; margin-top:6px">
                        <label style="display:flex; align-items:center; gap:6px; font-weight:400;">
                            <input type="radio" name="fulfillment_type" value="livraison" style="width:auto" checked> <i class="bi bi-truck-front"></i> Livraison
                        </label>
                        <label style="display:flex; align-items:center; gap:6px; font-weight:400;">
                            <input type="radio" name="fulfillment_type" value="retrait" style="width:auto"> <i class="bi bi-shop"></i> Retrait en magasin
                        </label>
                    </div>
                </div>

                <div class="field">
                    <label for="delivery_address"><i class="bi bi-geo-alt me-1"></i> Adresse de livraison</label>
                    <textarea id="delivery_address" name="delivery_address" rows="2" placeholder="Quartier, points de repère…">{{ old('delivery_address') }}</textarea>
                    <div class="hint">Requis uniquement pour une livraison — inutile pour un retrait en magasin.</div>
                </div>

                <div class="field">
                    <label for="delivery_phone"><i class="bi bi-telephone me-1"></i> Téléphone de contact</label>
                    <input type="text" id="delivery_phone" name="delivery_phone" value="{{ old('delivery_phone', auth('customer')->user()->phone) }}" required>
                </div>

                <div class="field">
                    <label for="delivery_notes">Notes (optionnel)</label>
                    <input type="text" id="delivery_notes" name="delivery_notes" value="{{ old('delivery_notes') }}">
                </div>

                <div class="field">
                    <label for="payment_method"><i class="bi bi-wallet2 me-1"></i> Mode de paiement</label>
                    <select id="payment_method" name="payment_method" required>
                        <option value="mobile_money_mtn">MTN Mobile Money</option>
                        <option value="mobile_money_orange">Orange Money</option>
                        <option value="a_la_livraison">Paiement à la livraison</option>
                    </select>
                    <div class="hint"><i class="bi bi-info-circle"></i> Le paiement en ligne réel n'est pas encore actif — votre commande sera enregistrée « en attente de paiement ».</div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" style="width:100%"><i class="bi bi-check-lg"></i> Confirmer la commande</button>
                </div>
            </form>
        </div>

        <div class="card">
            <div class="card-head"><h2><i class="bi bi-receipt"></i> Récapitulatif</h2></div>
            @foreach ($lines as $line)
                <div class="shop-cart-row">
                    <div style="flex:1">{{ $line['product']->name }} <span class="muted">× {{ rtrim(rtrim(number_format($line['quantity'], 2, ',', ' '), '0'), ',') }}</span></div>
                    <div class="num" style="font-weight:600">{{ number_format($line['line_total'], 0, ',', ' ') }} FCFA</div>
                </div>
            @endforeach
            <div class="cart-totals">
                <div class="row total"><span>Total</span><span>{{ number_format($total, 0, ',', ' ') }} FCFA</span></div>
            </div>
        </div>
    </div>
</x-shop-layout>
