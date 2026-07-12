<x-layout title="Transferts de stock">
    <div class="page-head">
        <div>
            <h1>Transferts de stock</h1>
            <p>Mouvements de stock entre entrepôts/magasins.</p>
        </div>
        <a href="{{ route('stock-transfers.create') }}" class="btn btn-primary">+ Nouveau transfert</a>
    </div>

    <div class="tbl-wrap">
        <table>
            <thead><tr><th>N°</th><th>De</th><th>Vers</th><th class="num">Lignes</th><th>Statut</th><th>Date</th><th></th></tr></thead>
            <tbody>
                @forelse ($transfers as $transfer)
                    <tr>
                        <td class="mono">#{{ $transfer->id }}</td>
                        <td>{{ $transfer->fromWarehouse->name }}</td>
                        <td>{{ $transfer->toWarehouse->name }}</td>
                        <td class="num">{{ $transfer->lines_count }}</td>
                        <td>
                            @if ($transfer->status === 'completed') <span class="badge badge-good">exécuté</span>
                            @else <span class="badge badge-warn">brouillon</span>
                            @endif
                        </td>
                        <td class="muted">{{ $transfer->created_at->format('d/m/Y') }}</td>
                        <td><a href="{{ route('stock-transfers.show', $transfer) }}" class="btn btn-sm">Voir</a></td>
                    </tr>
                @empty
                    <tr class="empty-row"><td colspan="7">Aucun transfert pour l'instant.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:16px">{{ $transfers->links() }}</div>
</x-layout>
