<x-layout title="Catégories">
    <div class="page-head">
        <div>
            <h1><i class="bi bi-tags text-primary"></i> Catégories</h1>
            <p>Organisation hiérarchique du catalogue.</p>
        </div>
        <a href="{{ route('categories.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nouvelle catégorie</a>
    </div>

    <div class="tbl-wrap">
        <table>
            <thead>
                <tr><th>Nom</th><th>Catégorie parente</th><th class="num">Produits</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($categories as $category)
                    <tr>
                        <td>{{ $category->name }}</td>
                        <td class="muted">{{ $category->parent?->name ?? '—' }}</td>
                        <td class="num">{{ $category->products_count }}</td>
                        <td>
                            <div class="table-actions">
                                <a href="{{ route('categories.edit', $category) }}" class="btn btn-sm"><i class="bi bi-pencil-square"></i> Modifier</a>
                                <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#delete-category-{{ $category->id }}"><i class="bi bi-trash3"></i></button>
                                <x-confirm-modal id="delete-category-{{ $category->id }}" title="Supprimer cette catégorie ?" body="Elle sera déplacée vers la corbeille.">
                                    <form method="POST" action="{{ route('categories.destroy', $category) }}">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-danger"><i class="bi bi-trash3"></i> Supprimer</button>
                                    </form>
                                </x-confirm-modal>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr class="empty-row"><td colspan="4"><i class="bi bi-inbox"></i> Aucune catégorie pour l'instant.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:16px">{{ $categories->links() }}</div>
</x-layout>
