<x-shop-layout title="Catalogue">
    <div class="page-head">
        <div>
            <h1><i class="bi bi-shop text-primary"></i> Notre catalogue</h1>
            <p>{{ $products->total() }} article(s) disponible(s) en ligne.</p>
        </div>
    </div>

    @if ($categories->isNotEmpty())
        <div class="shop-filters">
            <a href="{{ route('shop.catalog.index', ['q' => request('q')]) }}" class="shop-filter-pill @if(!request('categorie')) active @endif">Toutes catégories</a>
            @foreach ($categories as $category)
                <a href="{{ route('shop.catalog.index', ['q' => request('q'), 'categorie' => $category->id]) }}" class="shop-filter-pill @if(request('categorie') == $category->id) active @endif">{{ $category->name }}</a>
            @endforeach
        </div>
    @endif

    @if ($products->isEmpty())
        <div class="card"><p class="mt-0"><i class="bi bi-inbox"></i> Aucun article ne correspond à votre recherche.</p></div>
    @else
        <div class="shop-grid">
            @foreach ($products as $product)
                @php $available = $product->availableStock(); @endphp
                <a href="{{ route('shop.catalog.show', $product) }}" class="shop-card" style="text-decoration:none">
                    <div class="shop-card-media">
                        @if ($product->photo_path)
                            <img src="{{ asset('storage/'.$product->photo_path) }}" alt="{{ $product->name }}">
                        @else
                            <i class="bi bi-box-seam"></i>
                        @endif
                    </div>
                    <div class="shop-card-body">
                        <span class="shop-card-name">{{ $product->name }}</span>
                        @if ($product->brand)
                            <span class="muted" style="font-size:12px">{{ $product->brand }}</span>
                        @endif
                        <span>
                            @if ($available <= 0)
                                <span class="badge badge-crit">rupture</span>
                            @elseif ($available <= $product->low_stock_threshold)
                                <span class="badge badge-warn">stock faible</span>
                            @else
                                <span class="badge badge-good">en stock</span>
                            @endif
                        </span>
                        <span class="shop-card-price">{{ number_format($product->sale_price, 0, ',', ' ') }} FCFA</span>
                    </div>
                </a>
            @endforeach
        </div>

        <div style="margin-top:24px">{{ $products->links() }}</div>
    @endif
</x-shop-layout>
