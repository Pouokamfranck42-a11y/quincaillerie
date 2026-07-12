<x-layout title="Familles de produits">
    <div class="page-head">
        <div>
            <h1>Familles de produits</h1>
            <p>Regroupe les variantes/déclinaisons d'un même produit (taille, diamètre, matériau…).</p>
        </div>
        <a href="{{ route('product-families.create') }}" class="btn btn-primary">+ Nouvelle famille</a>
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
                                <a href="{{ route('product-families.edit', $family) }}" class="btn btn-sm">Modifier</a>
                                <form method="POST" action="{{ route('product-families.destroy', $family) }}" onsubmit="return confirm('Supprimer cette famille ?');">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger">Supprimer</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr class="empty-row"><td colspan="4">Aucune famille de produits pour l'instant.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:16px">{{ $families->links() }}</div>
</x-layout>
