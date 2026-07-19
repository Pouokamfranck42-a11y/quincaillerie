<x-shop-layout :title="$product->name">
    <a href="{{ route('shop.catalog.index') }}" class="btn btn-ghost btn-sm" style="margin-bottom:16px"><i class="bi bi-arrow-left"></i> Retour au catalogue</a>

    @php $available = $product->availableStock(); @endphp

    <div class="shop-product">
        <div class="shop-product-media">
            @if ($product->photo_path)
                <img src="{{ asset('storage/'.$product->photo_path) }}" alt="{{ $product->name }}">
            @else
                <i class="bi bi-box-seam"></i>
            @endif
        </div>

        <div>
            @if ($product->brand)
                <p class="muted" style="margin-bottom:2px">{{ $product->brand }}</p>
            @endif
            <h1 style="margin-top:0">{{ $product->name }}</h1>

            <p>
                @if ($available <= 0)
                    <span class="badge badge-crit">rupture de stock</span>
                @elseif ($available <= $product->low_stock_threshold)
                    <span class="badge badge-warn">stock faible — {{ rtrim(rtrim(number_format($available, 2, ',', ' '), '0'), ',') }} {{ $product->unit }}</span>
                @else
                    <span class="badge badge-good">en stock</span>
                @endif
            </p>

            @if ($variants->isNotEmpty())
                <div>
                    <strong style="font-size:13px">Autres déclinaisons :</strong>
                    <div class="shop-variant-pills">
                        <span class="shop-filter-pill active">
                            {{ collect($product->variant_attributes)->map(fn ($v, $k) => "$k: $v")->implode(' · ') ?: $product->name }}
                        </span>
                        @foreach ($variants as $variant)
                            <a href="{{ route('shop.catalog.show', $variant) }}" class="shop-filter-pill">
                                {{ collect($variant->variant_attributes)->map(fn ($v, $k) => "$k: $v")->implode(' · ') ?: $variant->name }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            <p style="font-size:28px; font-weight:700; color:var(--primary-dark); margin:16px 0">{{ number_format($product->sale_price, 0, ',', ' ') }} FCFA</p>

            @if ($product->description)
                <p>{{ $product->description }}</p>
            @endif

            @if ($available > 0)
                <form method="POST" action="{{ route('shop.cart.store') }}" class="flex" style="align-items:flex-end; gap:12px">
                    @csrf
                    <input type="hidden" name="product_id" value="{{ $product->id }}">
                    @php $step = $product->sold_by_cut ? (float) $product->cut_step : 0.01; @endphp
                    <div class="field" style="max-width:160px">
                        <label for="quantity">Quantité ({{ $product->unit }})</label>
                        <input type="number" id="quantity" name="quantity" value="{{ $step }}" min="{{ $step }}" max="{{ $available }}" step="{{ $step }}" required>
                        @if ($product->sold_by_cut)
                            <div class="hint">Vendu par pas de {{ rtrim(rtrim(number_format($step, 3, ',', ' '), '0'), ',') }} {{ $product->unit }}.</div>
                        @endif
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-cart-plus"></i> Ajouter au panier</button>
                </form>
            @else
                <button class="btn" disabled><i class="bi bi-x-circle"></i> Indisponible</button>
            @endif
        </div>
    </div>

    @if ($crossSells->isNotEmpty())
        <div class="page-head" style="margin-top:32px">
            <h2 style="margin:0"><i class="bi bi-plus-slash-minus text-primary"></i> Souvent achetés ensemble</h2>
        </div>
        <div class="shop-grid">
            @foreach ($crossSells as $suggestion)
                <a href="{{ route('shop.catalog.show', $suggestion) }}" class="shop-card" style="text-decoration:none">
                    <div class="shop-card-media">
                        @if ($suggestion->photo_path)
                            <img src="{{ asset('storage/'.$suggestion->photo_path) }}" alt="{{ $suggestion->name }}">
                        @else
                            <i class="bi bi-box-seam"></i>
                        @endif
                    </div>
                    <div class="shop-card-body">
                        <span class="shop-card-name">{{ $suggestion->name }}</span>
                        <span class="shop-card-price">{{ number_format($suggestion->sale_price, 0, ',', ' ') }} FCFA</span>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</x-shop-layout>
