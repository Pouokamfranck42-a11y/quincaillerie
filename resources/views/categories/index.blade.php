<x-layout title="Catégories">
    <div class="page-head">
        <div>
            <h1>Catégories</h1>
            <p>Organisation hiérarchique du catalogue.</p>
        </div>
        <a href="{{ route('categories.create') }}" class="btn btn-primary">+ Nouvelle catégorie</a>
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
                                <a href="{{ route('categories.edit', $category) }}" class="btn btn-sm">Modifier</a>
                                <form method="POST" action="{{ route('categories.destroy', $category) }}" onsubmit="return confirm('Supprimer cette catégorie ?');">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger">Supprimer</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr class="empty-row"><td colspan="4">Aucune catégorie pour l'instant.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:16px">{{ $categories->links() }}</div>
</x-layout>
