<x-layout title="SAV">
    <div class="page-head">
        <div>
            <h1><i class="bi bi-tools text-primary"></i> SAV / garantie</h1>
            <p>Dossiers de retour, réparation et échange.</p>
        </div>
    </div>

    <form method="GET" class="field" style="max-width:260px">
        <select name="status" onchange="this.form.submit()">
            <option value="">Tous les statuts</option>
            <option value="ouvert" @selected(request('status') === 'ouvert')>Ouvert</option>
            <option value="en_cours" @selected(request('status') === 'en_cours')>En cours</option>
            <option value="resolu" @selected(request('status') === 'resolu')>Résolu</option>
            <option value="refuse" @selected(request('status') === 'refuse')>Refusé</option>
        </select>
    </form>

    <div class="tbl-wrap">
        <table>
            <thead><tr><th>Dossier</th><th>Produit</th><th>Client</th><th>Statut</th><th>Ouvert le</th><th></th></tr></thead>
            <tbody>
                @forelse ($tickets as $ticket)
                    <tr>
                        <td class="mono">#{{ $ticket->id }}</td>
                        <td>{{ $ticket->saleLine->product->name }}</td>
                        <td>{{ $ticket->saleLine->sale->customer?->name ?? 'Client de passage' }}</td>
                        <td>
                            @if ($ticket->status === 'ouvert') <span class="badge badge-warn">ouvert</span>
                            @elseif ($ticket->status === 'en_cours') <span class="badge badge-neutral">en cours</span>
                            @elseif ($ticket->status === 'resolu') <span class="badge badge-good">résolu</span>
                            @else <span class="badge badge-crit">refusé</span>
                            @endif
                        </td>
                        <td class="muted">{{ $ticket->created_at->format('d/m/Y') }}</td>
                        <td><a href="{{ route('service-tickets.show', $ticket) }}" class="btn btn-sm">Ouvrir</a></td>
                    </tr>
                @empty
                    <tr class="empty-row"><td colspan="6"><i class="bi bi-inbox"></i> Aucun dossier SAV.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:16px">{{ $tickets->links() }}</div>
</x-layout>
