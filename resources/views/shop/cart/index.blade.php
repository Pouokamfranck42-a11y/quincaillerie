<x-shop-layout title="Mon panier">
    <div class="page-head">
        <h1><i class="bi bi-cart3 text-primary"></i> Mon panier</h1>
    </div>

    @if (empty($lines))
        <div class="card">
            <p class="mt-0"><i class="bi bi-inbox"></i> Votre panier est vide.</p>
            <a href="{{ route('shop.catalog.index') }}" class="btn btn-primary"><i class="bi bi-shop"></i> Voir le catalogue</a>
        </div>
    @else
        <form method="POST" action="{{ route('shop.cart.update') }}">
            @csrf
            <div class="card">
                @foreach ($lines as $line)
                    <div class="shop-cart-row">
                        <div class="shop-cart-thumb">
                            @if ($line['product']->photo_path)
                                <img src="{{ asset('storage/'.$line['product']->photo_path) }}" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:var(--radius)">
                            @else
                                <i class="bi bi-box-seam"></i>
                            @endif
                        </div>
                        <div style="flex:1">
                            <a href="{{ route('shop.catalog.show', $line['product']) }}" style="font-weight:600; text-decoration:none; color:var(--ink)">{{ $line['product']->name }}</a>
                            <div class="muted" style="font-size:13px">{{ number_format($line['price'], 0, ',', ' ') }} FCFA / {{ $line['product']->unit }}</div>
                        </div>
                        <input type="number" name="quantities[{{ $line['product']->id }}]" value="{{ $line['quantity'] }}" min="0" max="{{ $line['available'] }}" step="0.01" style="width:90px">
                        <div class="num" style="width:110px; text-align:right; font-weight:600">{{ number_format($line['line_total'], 0, ',', ' ') }} FCFA</div>
                        <button type="submit" form="remove-{{ $line['product']->id }}" class="btn btn-sm btn-ghost" title="Retirer"><i class="bi bi-trash3"></i></button>
                    </div>
                @endforeach
            </div>

            <div class="form-actions" style="justify-content:space-between; align-items:center">
                <button type="submit" class="btn"><i class="bi bi-arrow-repeat"></i> Mettre à jour les quantités</button>
                <div style="font-size:20px; font-weight:700">Total : {{ number_format($total, 0, ',', ' ') }} FCFA</div>
            </div>
        </form>

        @foreach ($lines as $line)
            <form id="remove-{{ $line['product']->id }}" method="POST" action="{{ route('shop.cart.destroy', $line['product']->id) }}" style="display:none">
                @csrf @method('DELETE')
            </form>
        @endforeach

        <div class="form-actions">
            <a href="{{ route('shop.catalog.index') }}" class="btn btn-ghost">Continuer mes achats</a>
            <a href="{{ route('shop.checkout.create') }}" class="btn btn-primary"><i class="bi bi-bag-check"></i> Passer commande</a>
        </div>
    @endif
</x-shop-layout>
