<x-layout title="Fournisseurs">
    <div class="page-head">
        <div>
            <h1>Fournisseurs</h1>
            <p>Coordonnées et délai de livraison moyen par fournisseur.</p>
        </div>
        <a href="{{ route('suppliers.create') }}" class="btn btn-primary">+ Nouveau fournisseur</a>
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
                                <a href="{{ route('suppliers.edit', $supplier) }}" class="btn btn-sm">Modifier</a>
                                <form method="POST" action="{{ route('suppliers.destroy', $supplier) }}" onsubmit="return confirm('Supprimer ce fournisseur ?');">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger">Supprimer</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr class="empty-row"><td colspan="6">Aucun fournisseur pour l'instant.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:16px">{{ $suppliers->links() }}</div>
</x-layout>
