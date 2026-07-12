<x-layout :title="$product->name">
    <div class="page-head">
        <div class="flex" style="align-items:flex-start">
            @if ($product->photo_path)
                <img src="{{ asset('storage/'.$product->photo_path) }}" alt="" style="width:64px; height:64px; object-fit:cover; border-radius:var(--radius); border:1px solid var(--steel-200)">
            @endif
            <div>
                <h1>{{ $product->name }}{{ $product->brand ? ' — '.$product->brand : '' }}</h1>
                <p class="mono">{{ $product->reference }} @if($product->barcode) · {{ $product->barcode }} @endif</p>
                <div class="flex" style="margin-top:8px; flex-wrap:wrap;">
                    @if ($product->location)
                        <span class="badge badge-neutral">📍 {{ $product->location }}</span>
                    @endif
                    @if ($product->family)
                        <span class="badge badge-neutral">{{ $product->family->name }}</span>
                    @endif
                    @if ($product->tracks_lots)
                        <span class="badge badge-warn">gestion par lots</span>
                    @endif
                    @foreach ($product->variant_attributes ?? [] as $key => $value)
                        <span class="badge badge-neutral">{{ $key }} : {{ $value }}</span>
                    @endforeach
                </div>
            </div>
        </div>
        <div class="flex">
            <a href="{{ route('products.label', $product) }}" class="btn" target="_blank">Étiquette</a>
            <a href="{{ route('products.edit', $product) }}" class="btn">Modifier</a>
        </div>
    </div>

    <div class="stat-grid">
        <div class="stat-tile @if($product->isLowStock()) crit @elseif($product->isOverstock()) warn @endif">
            <div class="lbl">Stock physique</div>
            <div class="val">{{ rtrim(rtrim(number_format($product->currentStock(), 2, ',', ' '), '0'), ',') }}</div>
            <div class="sub">{{ $product->unit }} · seuil {{ $product->low_stock_threshold }}</div>
        </div>
        <div class="stat-tile">
            <div class="lbl">Disponible / réservé</div>
            <div class="val">{{ rtrim(rtrim(number_format($product->availableStock(), 2, ',', ' '), '0'), ',') }}</div>
            <div class="sub">{{ rtrim(rtrim(number_format($product->reservedStock(), 2, ',', ' '), '0'), ',') }} réservé (devis acceptés)</div>
        </div>
        <div class="stat-tile">
            <div class="lbl">Attendu (en commande)</div>
            <div class="val">{{ rtrim(rtrim(number_format($product->incomingStock(), 2, ',', ' '), '0'), ',') }}</div>
            <div class="sub">point de commande {{ $product->effectiveReorderPoint() }}</div>
        </div>
        <div class="stat-tile">
            <div class="lbl">Prix d'achat (CUMP)</div>
            <div class="val">{{ number_format($product->purchase_price, 0, ',', ' ') }}</div>
            @if ($product->purchase_unit)
                <div class="sub">{{ number_format($product->purchase_price * $product->purchase_unit_factor, 0, ',', ' ') }} / {{ $product->purchase_unit }}</div>
            @endif
        </div>
        <div class="stat-tile">
            <div class="lbl">Prix de vente</div>
            <div class="val">{{ number_format($product->sale_price, 0, ',', ' ') }}</div>
            @if ($product->pro_price)
                <div class="sub">{{ number_format($product->pro_price, 0, ',', ' ') }} tarif pro</div>
            @endif
        </div>
        <div class="stat-tile">
            <div class="lbl">Marge</div>
            <div class="val">{{ $product->marginPercent() }}%</div>
            <div class="sub">
                {{ number_format($product->marginAmount(), 0, ',', ' ') }} / unité
                @if ($pricing['category_avg_margin'] !== null)
                    · moyenne catégorie {{ round($pricing['category_avg_margin'], 1) }}%
                @endif
            </div>
        </div>
    </div>

    @if ($pricing['suggestion'])
        <div class="alert alert-warn" style="background:var(--warn-soft); color:var(--warn); border:1px solid var(--warn)">
            💡 {{ $pricing['suggestion'] }}
        </div>
    @endif

    @if ($product->supplier || $product->alternateSuppliers->isNotEmpty())
        <div class="card">
            <div class="card-head"><h2>Fournisseurs</h2></div>
            <div class="tbl-wrap">
                <table>
                    <thead><tr><th>Fournisseur</th><th>Réf. fournisseur</th><th class="num">Prix d'achat</th><th></th></tr></thead>
                    <tbody>
                        @if ($product->supplier)
                            <tr>
                                <td>{{ $product->supplier->name }}</td>
                                <td class="muted">{{ $product->supplier_sku ?? '—' }}</td>
                                <td class="num">{{ number_format($product->purchase_price, 0, ',', ' ') }}</td>
                                <td><span class="badge badge-good">principal</span></td>
                            </tr>
                        @endif
                        @foreach ($product->alternateSuppliers as $alt)
                            <tr>
                                <td>{{ $alt->supplier->name }}</td>
                                <td class="muted">{{ $alt->supplier_sku ?? '—' }}</td>
                                <td class="num">{{ $alt->purchase_price ? number_format($alt->purchase_price, 0, ',', ' ') : '—' }}</td>
                                <td><span class="badge badge-neutral">secondaire</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if ($associations->isNotEmpty())
        <div class="card">
            <div class="card-head"><h2>Produits associés</h2></div>
            <p class="muted mt-0" style="margin-bottom:10px">Souvent achetés avec ce produit.</p>
            <div class="flex" style="flex-wrap:wrap; gap:8px">
                @foreach ($associations as $assoc)
                    <a href="{{ route('products.show', $assoc) }}" class="badge badge-neutral">{{ $assoc->name }}</a>
                @endforeach
            </div>
        </div>
    @endif

    @if ($product->tracks_lots)
        <div class="card">
            <div class="card-head"><h2>Lots</h2></div>
            <div class="tbl-wrap">
                <table>
                    <thead><tr><th>N° de lot</th><th class="num">Quantité</th><th>Péremption</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($lots as $lot)
                            <tr>
                                <td class="mono">{{ $lot->lot_number }}</td>
                                <td class="num">{{ rtrim(rtrim(number_format($lot->currentQuantity(), 2, ',', ' '), '0'), ',') }}</td>
                                <td>{{ $lot->expiry_date?->format('d/m/Y') ?? '—' }}</td>
                                <td>
                                    @if ($lot->isExpired()) <span class="badge badge-crit">périmé</span>
                                    @elseif ($lot->expiresWithin(30)) <span class="badge badge-warn">péremption proche</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr class="empty-row"><td colspan="4">Aucun lot enregistré — créez-en un via un mouvement d'entrée.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="card">
        <div class="card-head"><h2>Derniers mouvements de stock</h2></div>
        <div class="tbl-wrap">
            <table>
                <thead><tr><th>Date</th><th>Type</th><th class="num">Quantité</th><th>Motif</th></tr></thead>
                <tbody>
                    @forelse ($movements as $m)
                        <tr>
                            <td>{{ $m->created_at->format('d/m/Y H:i') }}</td>
                            <td>
                                @if ($m->type === 'entree') <span class="badge badge-good">entrée</span>
                                @elseif ($m->type === 'sortie') <span class="badge badge-crit">sortie</span>
                                @else <span class="badge badge-warn">ajustement</span>
                                @endif
                            </td>
                            <td class="num">{{ $m->quantity > 0 ? '+' : '' }}{{ rtrim(rtrim(number_format($m->quantity, 2, ',', ' '), '0'), ',') }}</td>
                            <td class="muted">{{ $m->reason ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr class="empty-row"><td colspan="4">Aucun mouvement enregistré.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layout>
