<x-layout title="Fournisseurs">
    <div class="page-head">
        <div>
            <h1><i class="bi bi-truck text-primary"></i> Fournisseurs</h1>
            <p>Coordonnées et délai de livraison moyen par fournisseur.</p>
        </div>
        <a href="{{ route('suppliers.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nouveau fournisseur</a>
    </div>

    <div class="tbl-wrap">
        <table>
            <thead>
                <tr><th>Nom</th><th>Contact</th><th>Téléphone</th><th class="num">Délai (j)</th><th class="num">Produits</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($suppliers as $supplier)
                    <tr>
                        <td>{{ $supplier->name }}</td>
                        <td class="muted">{{ $supplier->contact_name ?? '—' }}</td>
                        <td class="muted">{{ $supplier->phone ?? '—' }}</td>
                        <td class="num">{{ $supplier->lead_time_days }}</td>
                        <td class="num">{{ $supplier->products_count }}</td>
                        <td>
                            <div class="table-actions">
                                <a href="{{ route('suppliers.edit', $supplier) }}" class="btn btn-sm"><i class="bi bi-pencil-square"></i> Modifier</a>
                                <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#delete-supplier-{{ $supplier->id }}"><i class="bi bi-trash3"></i></button>
                                <x-confirm-modal id="delete-supplier-{{ $supplier->id }}" title="Supprimer ce fournisseur ?" body="Il sera déplacé vers la corbeille.">
                                    <form method="POST" action="{{ route('suppliers.destroy', $supplier) }}">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-danger"><i class="bi bi-trash3"></i> Supprimer</button>
                                    </form>
                                </x-confirm-modal>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr class="empty-row"><td colspan="6"><i class="bi bi-inbox"></i> Aucun fournisseur pour l'instant.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:16px">{{ $suppliers->links() }}</div>
</x-layout>
