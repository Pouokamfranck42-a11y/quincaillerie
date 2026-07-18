<x-layout title="Produits">
    <div class="page-head">
        <div>
            <h1><i class="bi bi-box-seam text-primary"></i> Produits</h1>
            <p>Catalogue, prix et stock courant.</p>
        </div>
        <div class="flex">
            <a href="{{ route('products.import') }}" class="btn"><i class="bi bi-file-earmark-arrow-up"></i> Importer le catalogue</a>
            <button type="button" class="btn" id="recognize-photo-btn"><i class="bi bi-camera"></i> Reconnaître via photo</button>
            <a href="{{ route('products.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nouveau produit</a>
        </div>
    </div>

    <input type="file" id="recognize-photo-input" accept="image/*" style="display:none">
    <div id="recognize-results" class="card" style="display:none; margin-bottom:16px"></div>

    <form method="GET" class="field">
        <div class="input-group" style="max-width:360px">
            <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
            <input type="search" name="q" class="border-start-0 ps-0" value="{{ request('q') }}" placeholder="Rechercher par nom, référence, code-barres…">
        </div>
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
                                <a href="{{ route('products.edit', $product) }}" class="btn btn-sm"><i class="bi bi-pencil-square"></i> Modifier</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr class="empty-row"><td colspan="8"><i class="bi bi-inbox"></i> Aucun produit trouvé.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:16px">{{ $products->links() }}</div>
</x-layout>
