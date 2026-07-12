<x-layout title="Devis">
    <div class="page-head">
        <div>
            <h1>Devis</h1>
            <p>Devis / proforma, convertibles en vente d'un clic.</p>
        </div>
        <a href="{{ route('quotes.create') }}" class="btn btn-primary">+ Nouveau devis</a>
    </div>

    <div class="tbl-wrap">
        <table>
            <thead><tr><th>N°</th><th>Client</th><th class="num">Lignes</th><th class="num">Total</th><th>Statut</th><th>Date</th><th></th></tr></thead>
            <tbody>
                @forelse ($quotes as $quote)
                    <tr>
                        <td class="mono">#{{ $quote->id }}</td>
                        <td>{{ $quote->customer?->name ?? 'Client de passage' }}</td>
                        <td class="num">{{ $quote->lines_count }}</td>
                        <td class="num">{{ number_format($quote->total, 0, ',', ' ') }}</td>
                        <td>
                            @if ($quote->status === 'converti') <span class="badge badge-good">converti</span>
                            @elseif ($quote->status === 'accepte') <span class="badge badge-good">accepté</span>
                            @elseif ($quote->status === 'envoye') <span class="badge badge-warn">envoyé</span>
                            @else <span class="badge badge-neutral">brouillon</span>
                            @endif
                        </td>
                        <td class="muted">{{ $quote->created_at->format('d/m/Y') }}</td>
                        <td><a href="{{ route('quotes.show', $quote) }}" class="btn btn-sm">Voir</a></td>
                    </tr>
                @empty
                    <tr class="empty-row"><td colspan="7">Aucun devis pour l'instant.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:16px">{{ $quotes->links() }}</div>
</x-layout>
