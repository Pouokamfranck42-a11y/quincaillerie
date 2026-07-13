<x-layout title="Suggestions de réapprovisionnement">
    <div class="page-head">
        <div>
            <h1><i class="bi bi-lightbulb text-primary"></i> Suggestions de réapprovisionnement</h1>
            <p>Produits sous leur point de commande (stock disponible + en cours de livraison), groupés par fournisseur. Quantité suggérée : reconstitue jusqu'au stock maximum (ou point de commande + stock de sécurité), avec la quantité économique de commande (EOQ) comme plancher.</p>
        </div>
    </div>

    @if ($lowStock->isEmpty())
        <div class="card"><p class="mt-0">Aucun produit en stock bas avec un fournisseur renseigné pour le moment.</p></div>
    @else
        <form method="POST" action="{{ route('purchase-orders.create-suggestions') }}">
            @csrf
            @foreach ($lowStock as $supplierId => $products)
                <div class="card">
                    <div class="card-head"><h2><i class="bi bi-truck"></i> {{ $products->first()->supplier->name }}</h2></div>
                    <div class="tbl-wrap">
                        <table>
                            <thead>
                                <tr><th></th><th>Produit</th><th class="num">Disponible</th><th class="num">En commande</th><th class="num">Point de cmde</th><th class="num">Qté suggérée</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($products as $product)
                                    <tr>
                                        <td><input type="checkbox" name="product_ids[]" value="{{ $product->id }}" checked style="width:auto"></td>
                                        <td>{{ $product->name }}</td>
                                        <td class="num">{{ rtrim(rtrim(number_format($product->availableStock(), 2, ',', ' '), '0'), ',') }} {{ $product->unit }}</td>
                                        <td class="num">{{ rtrim(rtrim(number_format($product->incomingStock(), 2, ',', ' '), '0'), ',') }}</td>
                                        <td class="num">{{ $product->effectiveReorderPoint() }}</td>
                                        <td class="num">{{ rtrim(rtrim(number_format($product->suggested_quantity, 2, ',', ' '), '0'), ',') }} {{ $product->unit }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach

            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Créer les commandes en brouillon</button>
                <a href="{{ route('purchase-orders.index') }}" class="btn btn-ghost">Annuler</a>
            </div>
        </form>
    @endif
</x-layout>
