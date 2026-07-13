<x-layout title="Commandes fournisseur">
    <div class="page-head">
        <div>
            <h1><i class="bi bi-cart3 text-primary"></i> Commandes fournisseur</h1>
            <p>Suivi des commandes passées et de leur réception.</p>
        </div>
        <div class="flex">
            <a href="{{ route('purchase-orders.import-invoice') }}" class="btn"><i class="bi bi-file-earmark-arrow-up"></i> Importer une facture</a>
            <a href="{{ route('purchase-orders.suggestions') }}" class="btn"><i class="bi bi-lightbulb"></i> Suggestions de réappro</a>
            <a href="{{ route('purchase-orders.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nouvelle commande</a>
        </div>
    </div>

    <div class="tbl-wrap">
        <table>
            <thead><tr><th>N°</th><th>Fournisseur</th><th class="num">Lignes</th><th>Statut</th><th>Date</th><th></th></tr></thead>
            <tbody>
                @forelse ($purchaseOrders as $po)
                    <tr>
                        <td class="mono">#{{ $po->id }}</td>
                        <td>{{ $po->supplier->name }}</td>
                        <td class="num">{{ $po->lines_count }}</td>
                        <td>
                            @if ($po->status === 'received') <span class="badge badge-good">réceptionnée</span>
                            @elseif ($po->status === 'cancelled') <span class="badge badge-neutral">annulée</span>
                            @elseif ($po->status === 'draft') <span class="badge badge-neutral">brouillon</span>
                            @elseif ($po->status === 'partiellement_recu') <span class="badge badge-warn">partielle</span>
                            @else <span class="badge badge-warn">en attente</span>
                            @endif
                        </td>
                        <td class="muted">{{ $po->created_at->format('d/m/Y') }}</td>
                        <td><a href="{{ route('purchase-orders.show', $po) }}" class="btn btn-sm"><i class="bi bi-eye"></i> Voir</a></td>
                    </tr>
                @empty
                    <tr class="empty-row"><td colspan="6"><i class="bi bi-inbox"></i> Aucune commande fournisseur pour l'instant.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:16px">{{ $purchaseOrders->links() }}</div>
</x-layout>
