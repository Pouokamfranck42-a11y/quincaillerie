<x-layout title="Mouvements de stock">
    <div class="page-head">
        <div>
            <h1><i class="bi bi-arrow-left-right text-primary"></i> Stock</h1>
            <p>Historique des mouvements et alertes de stock bas.</p>
        </div>
        <a href="{{ route('stock-movements.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nouveau mouvement</a>
    </div>

    @if ($lowStockProducts->isNotEmpty())
        <div class="card">
            <div class="card-head"><h2><i class="bi bi-exclamation-triangle"></i> Produits en stock bas ({{ $lowStockProducts->count() }})</h2></div>
            <div class="tbl-wrap">
                <table>
                    <thead><tr><th>Produit</th><th class="num">Stock</th><th class="num">Seuil</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($lowStockProducts as $product)
                            <tr>
                                <td>{{ $product->name }}</td>
                                <td class="num">{{ rtrim(rtrim(number_format($product->stock_quantity ?? 0, 2, ',', ' '), '0'), ',') }} {{ $product->unit }}</td>
                                <td class="num">{{ $product->low_stock_threshold }}</td>
                                <td><a href="{{ route('products.show', $product) }}" class="btn btn-sm"><i class="bi bi-eye"></i> Voir</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="card">
        <div class="card-head"><h2><i class="bi bi-clock-history"></i> Derniers mouvements</h2></div>
        <div class="tbl-wrap">
            <table>
                <thead><tr><th>Date</th><th>Produit</th><th>Type</th><th class="num">Quantité</th><th>Motif</th><th>Par</th></tr></thead>
                <tbody>
                    @forelse ($movements as $m)
                        <tr>
                            <td class="mono">{{ $m->created_at->format('d/m/Y H:i') }}</td>
                            <td>{{ $m->product->name }}</td>
                            <td>
                                @if ($m->type === 'entree') <span class="badge badge-good">entrée</span>
                                @elseif ($m->type === 'sortie') <span class="badge badge-crit">sortie</span>
                                @else <span class="badge badge-warn">ajustement</span>
                                @endif
                                @if ($m->subtype) <span class="badge badge-neutral">{{ str_replace('_', ' ', $m->subtype) }}</span> @endif
                            </td>
                            <td class="num">{{ $m->quantity > 0 ? '+' : '' }}{{ rtrim(rtrim(number_format($m->quantity, 2, ',', ' '), '0'), ',') }}</td>
                            <td class="muted">{{ $m->reason ?? '—' }}</td>
                            <td class="muted">{{ $m->user?->name ?? 'système' }}</td>
                        </tr>
                    @empty
                        <tr class="empty-row"><td colspan="6">Aucun mouvement enregistré.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:16px">{{ $movements->links() }}</div>
    </div>
</x-layout>
