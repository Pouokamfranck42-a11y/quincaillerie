<x-layout title="Produits">
    <div class="page-head">
        <div>
            <h1>Produits</h1>
            <p>Catalogue, prix et stock courant.</p>
        </div>
        <div class="flex">
            <button type="button" class="btn" id="recognize-photo-btn">📷 Reconnaître via photo</button>
            <a href="{{ route('products.create') }}" class="btn btn-primary">+ Nouveau produit</a>
        </div>
    </div>

    <input type="file" id="recognize-photo-input" accept="image/*" style="display:none">
    <div id="recognize-results" class="card" style="display:none; margin-bottom:16px"></div>

    <form method="GET" class="field" style="max-width:360px">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Rechercher par nom, référence, code-barres…">
    </form>

    <div class="tbl-wrap">
        <table>
            <thead>
                <tr>
                    <th>Référence</th><th>Nom</th><th>Catégorie</th><th>Localisation</th>
                    <th class="num">Stock</th><th class="num">Prix vente</th><th>Statut</th><th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($products as $product)
                    @php $stock = (float) ($product->stock_quantity ?? 0); @endphp
                    <tr>
                        <td class="mono">{{ $product->reference }}</td>
                        <td><a href="{{ route('products.show', $product) }}">{{ $product->name }}</a></td>
                        <td class="muted">{{ $product->category?->name ?? '—' }}</td>
                        <td class="muted">{{ $product->location ?? '—' }}</td>
                        <td class="num">
                            {{ rtrim(rtrim(number_format($stock, 2, ',', ' '), '0'), ',') }} {{ $product->unit }}
                            @if ($stock <= (float) $product->low_stock_threshold)
                                <span class="badge badge-crit">bas</span>
                            @endif
                        </td>
                        <td class="num">{{ number_format($product->sale_price, 0, ',', ' ') }}</td>
                        <td>
                            @if ($product->active)
                                <span class="badge badge-good">actif</span>
                            @else
                                <span class="badge badge-neutral">inactif</span>
                            @endif
                        </td>
                        <td>
                            <div class="table-actions">
                                <a href="{{ route('products.edit', $product) }}" class="btn btn-sm">Modifier</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr class="empty-row"><td colspan="8">Aucun produit trouvé.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:16px">{{ $products->links() }}</div>
</x-layout>
