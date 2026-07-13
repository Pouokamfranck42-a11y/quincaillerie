<x-layout title="Familles de produits">
    <div class="page-head">
        <div>
            <h1><i class="bi bi-diagram-3 text-primary"></i> Familles de produits</h1>
            <p>Regroupe les variantes/déclinaisons d'un même produit (taille, diamètre, matériau…).</p>
        </div>
        <a href="{{ route('product-families.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nouvelle famille</a>
    </div>

    <div class="tbl-wrap">
        <table>
            <thead><tr><th>Nom</th><th>Catégorie</th><th class="num">Variantes</th><th></th></tr></thead>
            <tbody>
                @forelse ($families as $family)
                    <tr>
                        <td>{{ $family->name }}</td>
                        <td class="muted">{{ $family->category?->name ?? '—' }}</td>
                        <td class="num">{{ $family->products_count }}</td>
                        <td>
                            <div class="table-actions">
                                <a href="{{ route('product-families.edit', $family) }}" class="btn btn-sm"><i class="bi bi-pencil-square"></i> Modifier</a>
                                <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#delete-family-{{ $family->id }}"><i class="bi bi-trash3"></i></button>
                                <x-confirm-modal id="delete-family-{{ $family->id }}" title="Supprimer cette famille ?" body="Elle sera déplacée vers la corbeille.">
                                    <form method="POST" action="{{ route('product-families.destroy', $family) }}">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-danger"><i class="bi bi-trash3"></i> Supprimer</button>
                                    </form>
                                </x-confirm-modal>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr class="empty-row"><td colspan="4"><i class="bi bi-inbox"></i> Aucune famille de produits pour l'instant.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:16px">{{ $families->links() }}</div>
</x-layout>
