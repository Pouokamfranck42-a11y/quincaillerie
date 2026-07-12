<x-layout title="Clients">
    <div class="page-head">
        <div>
            <h1>Clients</h1>
            <p>Fiches clients particuliers et professionnels.</p>
        </div>
        <a href="{{ route('customers.create') }}" class="btn btn-primary">+ Nouveau client</a>
    </div>

    <form method="GET" class="field" style="max-width:360px">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Rechercher par nom ou téléphone…">
    </form>

    <div class="tbl-wrap">
        <table>
            <thead><tr><th>Nom</th><th>Type</th><th>Segment IA</th><th>Téléphone</th><th class="num">Encours</th><th></th></tr></thead>
            <tbody>
                @forelse ($customers as $customer)
                    @php $outstanding = (float) ($customer->due_total ?? 0) - (float) ($customer->due_paid ?? 0); @endphp
                    <tr>
                        <td>{{ $customer->name }}</td>
                        <td><span class="badge badge-neutral">{{ $customer->type }}</span></td>
                        <td>
                            @if ($customer->ai_segment)
                                <span class="badge badge-neutral" title="{{ $customer->ai_segment_rationale }}">{{ $customer->ai_segment }}</span>
                            @else
                                <span class="muted">—</span>
                            @endif
                        </td>
                        <td class="muted">{{ $customer->phone ?? '—' }}</td>
                        <td class="num">
                            @if ($customer->credit_limit > 0)
                                {{ number_format($outstanding, 0, ',', ' ') }} / {{ number_format($customer->credit_limit, 0, ',', ' ') }}
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            <div class="table-actions">
                                @if ($customer->credit_limit > 0)
                                    <a href="{{ route('customers.statement', $customer) }}" class="btn btn-sm">Relevé</a>
                                @endif
                                <a href="{{ route('customers.edit', $customer) }}" class="btn btn-sm">Modifier</a>
                                <form method="POST" action="{{ route('customers.destroy', $customer) }}" onsubmit="return confirm('Supprimer ce client ?');">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger">Supprimer</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr class="empty-row"><td colspan="6">Aucun client pour l'instant.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:16px">{{ $customers->links() }}</div>
</x-layout>
