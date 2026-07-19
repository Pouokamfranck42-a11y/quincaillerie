<?php

use App\Models\CashRegisterSession;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductAssociation;
use App\Models\Sale;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public int $sessionId;

    public string $search = '';

    /** @var array<int, float> quantité par product_id */
    public array $cart = [];

    public ?int $customerId = null;

    public string $paymentMethod = 'especes';

    public float $taxRate = 18;

    public ?float $amountTendered = null;

    public ?int $lastAddedProductId = null;

    public int $loyaltyPointsToRedeem = 0;

    /** Les points appartiennent à un client précis — en changer annule tout rachat en cours. */
    public function updatedCustomerId(): void
    {
        $this->loyaltyPointsToRedeem = 0;
    }

    public function mount(CashRegisterSession $session): void
    {
        $this->sessionId = $session->id;
        // TVA alignée sur la config de facturation (Phase 6) plutôt qu'un taux figé —
        // 0% tant que l'entreprise n'est pas confirmée assujettie par un comptable.
        $this->taxRate = config('company.vat_subject') ? (float) config('company.vat_rate') : 0.0;
    }

    #[Computed]
    public function results()
    {
        if (mb_strlen(trim($this->search)) < 2) {
            return collect();
        }

        return Product::query()
            ->where('active', true)
            ->where(function ($q) {
                $q->where('name', 'ilike', "%{$this->search}%")
                    ->orWhere('reference', 'ilike', "%{$this->search}%")
                    ->orWhere('barcode', $this->search);
            })
            ->withSum('stockMovements as stock_quantity', 'quantity')
            ->limit(12)
            ->get();
    }

    #[Computed]
    public function customers()
    {
        return Customer::orderBy('name')->get();
    }

    #[Computed]
    public function selectedCustomer(): ?Customer
    {
        return $this->customerId ? $this->customers->firstWhere('id', $this->customerId) : null;
    }

    /** Lignes du panier enrichies (nom, prix appliqué selon le client, sous-total) — recalculées à chaque rendu. */
    #[Computed]
    public function cartLines()
    {
        if (empty($this->cart)) {
            return collect();
        }

        $products = Product::whereIn('id', array_keys($this->cart))->get()->keyBy('id');
        $customer = $this->selectedCustomer();

        return collect($this->cart)->map(function ($quantity, $productId) use ($products, $customer) {
            $product = $products->get($productId);
            $lot = $product->tracks_lots ? $product->nextFefoLot() : null;

            return [
                'product_id' => $productId,
                'name' => $product->name,
                'unit' => $product->unit,
                'price' => $product->priceFor($customer),
                'quantity' => $quantity,
                'lot' => $lot,
                'available' => $product->availableStock(),
                'sold_by_cut' => $product->sold_by_cut,
                'cut_step' => (float) $product->cut_step,
            ];
        });
    }

    /** true si au moins une ligne du panier dépasse le stock disponible — vérification affichée en plus du verrou serveur autoritaire. */
    #[Computed]
    public function hasOverstockedLine(): bool
    {
        return $this->cartLines()->contains(fn ($line) => (float) $line['quantity'] > (float) $line['available']);
    }

    public function addToCart(int $productId): void
    {
        $this->cart[$productId] = ($this->cart[$productId] ?? 0) + 1;
        $this->search = '';
        $this->lastAddedProductId = $productId;
    }

    /** Suggestions de ventes croisées pour le dernier produit ajouté, hors produits déjà dans le panier. */
    #[Computed]
    public function crossSellSuggestions()
    {
        if (! $this->lastAddedProductId) {
            return collect();
        }

        return ProductAssociation::where('product_id', $this->lastAddedProductId)
            ->whereNotIn('associated_product_id', array_keys($this->cart))
            ->orderByDesc('co_occurrence_count')
            ->with('associatedProduct')
            ->limit(4)
            ->get()
            ->pluck('associatedProduct')
            ->filter(fn (?Product $p) => $p !== null && $p->active);
    }

    public function updateQuantity(int $productId, float $quantity): void
    {
        if ($quantity <= 0) {
            $this->removeFromCart($productId);

            return;
        }

        if (isset($this->cart[$productId])) {
            $this->cart[$productId] = $quantity;
        }
    }

    public function removeFromCart(int $productId): void
    {
        unset($this->cart[$productId]);
    }

    #[Computed]
    public function subtotal(): float
    {
        return (float) $this->cartLines()->sum(fn ($line) => $line['price'] * $line['quantity']);
    }

    #[Computed]
    public function taxAmount(): float
    {
        return round($this->subtotal() * ($this->taxRate / 100), 2);
    }

    #[Computed]
    public function total(): float
    {
        return $this->subtotal() + $this->taxAmount();
    }

    #[Computed]
    public function loyaltyPointsAvailable(): int
    {
        return $this->selectedCustomer()?->loyaltyPoints() ?? 0;
    }

    #[Computed]
    public function loyaltyDiscount(): float
    {
        $points = min($this->loyaltyPointsToRedeem, $this->loyaltyPointsAvailable());

        return round($points * (float) config('company.loyalty.redeem_value'), 2);
    }

    /** Ce que le client paie réellement — total brut moins la réduction fidélité, jamais négatif. */
    #[Computed]
    public function finalTotal(): float
    {
        return max(0.0, $this->total() - $this->loyaltyDiscount());
    }

    /** Monnaie à rendre — pertinent uniquement en espèces, une fois un montant reçu saisi. */
    #[Computed]
    public function changeDue(): float
    {
        if ($this->paymentMethod !== 'especes' || $this->amountTendered === null) {
            return 0.0;
        }

        return max(0.0, round($this->amountTendered - $this->finalTotal(), 2));
    }

    public function checkout(): void
    {
        if (empty($this->cart)) {
            $this->addError('cart', 'Le panier est vide.');

            return;
        }

        if ($this->hasOverstockedLine()) {
            $this->addError('cart', 'Une ou plusieurs lignes dépassent le stock disponible — ajustez les quantités.');

            return;
        }

        $session = CashRegisterSession::findOrFail($this->sessionId);

        $cartItems = collect($this->cart)->map(fn ($quantity, $productId) => [
            'product' => Product::findOrFail($productId),
            'quantity' => $quantity,
        ])->values()->all();

        // Vérification amont pour un message inline cohérent avec le reste du composant
        // (addError + return) — Sale::checkout() revérifie de toute façon en autorité.
        foreach ($cartItems as $item) {
            try {
                $item['product']->assertValidSaleQuantity((float) $item['quantity']);
            } catch (\Illuminate\Validation\ValidationException $e) {
                $this->addError('cart', $e->validator->errors()->first('quantity'));

                return;
            }
        }

        $sale = Sale::checkout(
            $cartItems,
            $session,
            auth()->id(),
            $this->customerId,
            $this->paymentMethod,
            $this->taxRate,
            $this->paymentMethod === 'especes' ? $this->amountTendered : null,
            $this->loyaltyPointsToRedeem,
        );

        $invoice = Invoice::generateFor($sale);

        $this->cart = [];
        $this->customerId = null;
        $this->paymentMethod = 'especes';
        $this->amountTendered = null;
        $this->loyaltyPointsToRedeem = 0;
        session()->flash('success', 'Vente #'.$sale->id.' encaissée : '.number_format($sale->total, 0, ',', ' ').' FCFA.');
        session()->flash('auto_print', true);
        $this->redirectRoute('invoices.show', $invoice);
    }
};
?>

<div class="pos-grid">
    <div>
        <div class="pos-search">
            <div class="input-group">
                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                <input
                    type="search"
                    id="pos-search-input"
                    class="border-start-0 ps-0"
                    wire:model.live.debounce.250ms="search"
                    placeholder="Rechercher un produit par nom, référence ou code-barres…"
                    autofocus
                >
            </div>
            <div wire:ignore>
                <x-barcode-scan target="pos-search-input" />
            </div>
        </div>

        <div class="pos-results">
            @forelse ($this->results as $product)
                @php $stock = (float) ($product->stock_quantity ?? 0); @endphp
                <div class="pos-result" wire:click="addToCart({{ $product->id }})">
                    <div>
                        <div class="name">{{ $product->name }}</div>
                        <div class="meta">
                            {{ $product->reference }} · {{ number_format($product->priceFor($this->selectedCustomer()), 0, ',', ' ') }} FCFA / {{ $product->unit }}
                            @if ($product->location) · <i class="bi bi-geo-alt"></i> {{ $product->location }} @endif
                            @if ($product->tracks_lots)
                                @php $fefo = $product->nextFefoLot(); @endphp
                                · <span class="{{ $fefo?->expiresWithin(30) ? 'badge badge-warn' : 'muted' }}">{{ $fefo ? 'lot '.$fefo->lot_number.' — '.$fefo->expiry_date?->format('d/m/Y') : 'aucun lot disponible' }}</span>
                            @endif
                        </div>
                    </div>
                    <div>
                        @if ($stock <= (float) $product->low_stock_threshold)
                            <span class="badge badge-crit">stock {{ rtrim(rtrim(number_format($stock, 2, ',', ' '), '0'), ',') }}</span>
                        @else
                            <span class="badge badge-good">stock {{ rtrim(rtrim(number_format($stock, 2, ',', ' '), '0'), ',') }}</span>
                        @endif
                    </div>
                </div>
            @empty
                @if (mb_strlen(trim($search)) >= 2)
                    <p class="muted"><i class="bi bi-search"></i> Aucun produit trouvé.</p>
                @endif
            @endforelse
        </div>
    </div>

    <div class="pos-cart">
        <h3><i class="bi bi-cart-check"></i> Panier</h3>

        @error('cart') <div class="alert alert-crit"><i class="bi bi-exclamation-triangle-fill"></i> <span>{{ $message }}</span></div> @enderror
        @error('credit') <div class="alert alert-crit"><i class="bi bi-exclamation-triangle-fill"></i> <span>{{ $message }}</span></div> @enderror

        @forelse ($this->cartLines() as $line)
            @php $overstocked = (float) $line['quantity'] > (float) $line['available']; @endphp
            <div class="cart-line" @if($overstocked) style="background:var(--crit-soft, #FEE2E2); border-radius:6px" @endif>
                <span class="name">
                    {{ $line['name'] }}
                    @if ($line['lot'])
                        <br><span class="muted" style="font-size:11px">lot {{ $line['lot']->lot_number }}{{ $line['lot']->expiry_date ? ' · péremption ' . $line['lot']->expiry_date->format('d/m/Y') : '' }}</span>
                    @endif
                    @if ($overstocked)
                        <br><span style="font-size:11px; color:var(--crit, #DC2626)"><i class="bi bi-exclamation-triangle-fill"></i> disponible : {{ rtrim(rtrim(number_format($line['available'], 2, ',', ' '), '0'), ',') }}</span>
                    @endif
                    @if ($line['sold_by_cut'])
                        <br><span class="muted" style="font-size:11px">pas de {{ rtrim(rtrim(number_format($line['cut_step'], 3, ',', ' '), '0'), ',') }} {{ $line['unit'] }}</span>
                    @endif
                </span>
                <input
                    type="number"
                    step="{{ $line['sold_by_cut'] ? $line['cut_step'] : 0.01 }}"
                    min="0"
                    value="{{ $line['quantity'] }}"
                    wire:change="updateQuantity({{ $line['product_id'] }}, $event.target.value)"
                >
                <span class="mono">{{ number_format($line['price'] * $line['quantity'], 0, ',', ' ') }}</span>
                <button type="button" class="btn btn-sm btn-ghost" wire:click="removeFromCart({{ $line['product_id'] }})"><i class="bi bi-x-lg"></i></button>
            </div>
        @empty
            <p class="muted"><i class="bi bi-cart"></i> Panier vide — recherchez un article à gauche.</p>
        @endforelse

        @if ($this->crossSellSuggestions()->isNotEmpty())
            <div style="margin-top:10px">
                <div class="muted" style="font-size:12px; margin-bottom:6px"><i class="bi bi-lightbulb"></i> Souvent acheté avec :</div>
                <div class="flex" style="flex-wrap:wrap; gap:6px">
                    @foreach ($this->crossSellSuggestions() as $suggestion)
                        <button type="button" class="btn btn-sm btn-ghost" style="border:1px solid var(--steel-200)" wire:click="addToCart({{ $suggestion->id }})"><i class="bi bi-plus-circle"></i> {{ $suggestion->name }}</button>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="field" style="margin-top:14px">
            <label for="customerId"><i class="bi bi-person me-1"></i>Client (optionnel)</label>
            <select id="customerId" wire:model.live="customerId">
                <option value="">Client de passage</option>
                @foreach ($this->customers as $customer)
                    <option value="{{ $customer->id }}">{{ $customer->name }} @if($customer->type === 'professionnel') (pro) @endif</option>
                @endforeach
            </select>
            @if ($this->selectedCustomer()?->type === 'professionnel')
                <div class="hint"><i class="bi bi-info-circle"></i> Tarif pro appliqué · crédit disponible : {{ number_format($this->selectedCustomer()->availableCredit(), 0, ',', ' ') }} FCFA</div>
            @endif
            @if ($this->selectedCustomer() && config('company.loyalty.enabled') && $this->loyaltyPointsAvailable() > 0)
                <div class="hint"><i class="bi bi-star"></i> {{ $this->loyaltyPointsAvailable() }} point(s) de fidélité disponible(s).</div>
            @endif
        </div>

        @if ($this->selectedCustomer() && config('company.loyalty.enabled') && $this->loyaltyPointsAvailable() > 0)
            <div class="field">
                <label for="loyaltyPointsToRedeem"><i class="bi bi-star me-1"></i>Points fidélité à utiliser</label>
                <input type="number" id="loyaltyPointsToRedeem" step="1" min="0" max="{{ $this->loyaltyPointsAvailable() }}" wire:model.live="loyaltyPointsToRedeem">
                @if ($this->loyaltyDiscount() > 0)
                    <div class="hint"><i class="bi bi-tag"></i> Réduction : {{ number_format($this->loyaltyDiscount(), 0, ',', ' ') }} FCFA</div>
                @endif
                @error('loyalty') <div class="error">{{ $message }}</div> @enderror
            </div>
        @endif

        <div class="field">
            <label for="paymentMethod"><i class="bi bi-credit-card-2-front me-1"></i>Paiement</label>
            <select id="paymentMethod" wire:model.live="paymentMethod">
                <option value="especes">Espèces</option>
                <option value="carte">Carte</option>
                <option value="mobile">Mobile money</option>
                @if ($this->selectedCustomer()?->type === 'professionnel' && $this->selectedCustomer()->credit_limit > 0)
                    <option value="credit">À crédit</option>
                @endif
            </select>
        </div>

        @error('amount_tendered') <div class="alert alert-crit"><i class="bi bi-exclamation-triangle-fill"></i> <span>{{ $message }}</span></div> @enderror

        @if ($paymentMethod === 'especes')
            <div class="field">
                <label for="amountTendered"><i class="bi bi-cash me-1"></i>Montant reçu</label>
                <input type="number" id="amountTendered" step="1" min="0" wire:model.live="amountTendered" placeholder="{{ number_format($this->finalTotal(), 0, ',', ' ') }}">
                @if ($amountTendered !== null && $amountTendered >= $this->finalTotal())
                    <div class="hint"><i class="bi bi-arrow-return-left"></i> Monnaie à rendre : <strong>{{ number_format($this->changeDue(), 0, ',', ' ') }} FCFA</strong></div>
                @elseif ($amountTendered !== null)
                    <div class="hint" style="color:var(--crit, #DC2626)"><i class="bi bi-exclamation-triangle"></i> Montant insuffisant.</div>
                @endif
            </div>
        @endif

        <div class="cart-totals">
            <div class="row"><span>Sous-total</span><span>{{ number_format($this->subtotal(), 0, ',', ' ') }}</span></div>
            <div class="row"><span>TVA ({{ rtrim(rtrim(number_format($taxRate, 2), '0'), '.') }}%)</span><span>{{ number_format($this->taxAmount(), 0, ',', ' ') }}</span></div>
            @if ($this->loyaltyDiscount() > 0)
                <div class="row"><span>Réduction fidélité</span><span>-{{ number_format($this->loyaltyDiscount(), 0, ',', ' ') }}</span></div>
            @endif
            <div class="row total"><span>Total</span><span>{{ number_format($this->finalTotal(), 0, ',', ' ') }} FCFA</span></div>
        </div>

        <button
            type="button"
            class="btn btn-primary"
            style="width:100%; margin-top:14px"
            wire:click="checkout"
            @disabled(empty($cart) || $this->hasOverstockedLine() || ($paymentMethod === 'especes' && $amountTendered !== null && $amountTendered < $this->finalTotal()))
        >
            <i class="bi bi-cash-coin"></i> Marquer comme vendu
        </button>
    </div>
</div>
