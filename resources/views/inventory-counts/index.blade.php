<x-layout title="Inventaires">
    <div class="page-head">
        <div>
            <h1>Inventaires</h1>
            <p>Comptages complets ou tournants, écarts et régularisations.</p>
        </div>
        <a href="{{ route('inventory-counts.create') }}" class="btn btn-primary">+ Nouveau comptage</a>
    </div>

    <div class="tbl-wrap">
        <table>
            <thead><tr><th>N°</th><th>Entrepôt</th><th>Type</th><th>Périmètre</th><th class="num">Lignes</th><th>Statut</th><th>Date</th><th></th></tr></thead>
            <tbody>
                @forelse ($counts as $count)
                    <tr>
                        <td class="mono">#{{ $count->id }}</td>
                        <td>{{ $count->warehouse->name }}</td>
                        <td class="muted">{{ $count->type }}</td>
                        <td class="muted">{{ $count->category?->name ?? 'Tout le catalogue' }}</td>
                        <td class="num">{{ $count->lines_count }}</td>
                        <td>
                            @if ($count->status === 'completed') <span class="badge badge-good">clôturé</span>
                            @else <span class="badge badge-warn">en cours</span>
                            @endif
                        </td>
                        <td class="muted">{{ $count->created_at->format('d/m/Y') }}</td>
                        <td><a href="{{ route('inventory-counts.show', $count) }}" class="btn btn-sm">Voir</a></td>
                    </tr>
                @empty
                    <tr class="empty-row"><td colspan="8">Aucun comptage pour l'instant.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:16px">{{ $counts->links() }}</div>
</x-layout>
