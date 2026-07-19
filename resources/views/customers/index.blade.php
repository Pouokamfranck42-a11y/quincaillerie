<x-layout title="Clients">
    <div class="page-head">
        <div>
            <h1><i class="bi bi-people text-primary"></i> Clients</h1>
            <p>Fiches clients particuliers et professionnels.</p>
        </div>
        <div class="flex">
            <a href="{{ route('customers.import') }}" class="btn"><i class="bi bi-file-earmark-arrow-up"></i> Importer</a>
            <a href="{{ route('customers.export') }}" class="btn"><i class="bi bi-file-earmark-arrow-down"></i> Exporter (CSV)</a>
            <a href="{{ route('customers.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nouveau client</a>
        </div>
    </div>

    <form method="GET" class="field">
        <div class="input-group" style="max-width:360px">
            <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
            <input type="search" name="q" class="border-start-0 ps-0" value="{{ request('q') }}" placeholder="Rechercher par nom ou téléphone…">
        </div>
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
                                    <a href="{{ route('customers.statement', $customer) }}" class="btn btn-sm"><i class="bi bi-file-earmark-text"></i> Relevé</a>
                                @endif
                                <a href="{{ route('customers.edit', $customer) }}" class="btn btn-sm"><i class="bi bi-pencil-square"></i> Modifier</a>
                                <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#delete-customer-{{ $customer->id }}"><i class="bi bi-trash3"></i></button>
                                <x-confirm-modal id="delete-customer-{{ $customer->id }}" title="Supprimer ce client ?" body="Il sera déplacé vers la corbeille.">
                                    <form method="POST" action="{{ route('customers.destroy', $customer) }}">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-danger"><i class="bi bi-trash3"></i> Supprimer</button>
                                    </form>
                                </x-confirm-modal>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr class="empty-row"><td colspan="6"><i class="bi bi-inbox"></i> Aucun client pour l'instant.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:16px">{{ $customers->links() }}</div>
</x-layout>
