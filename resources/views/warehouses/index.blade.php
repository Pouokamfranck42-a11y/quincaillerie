<x-layout title="Entrepôts">
    <div class="page-head">
        <div>
            <h1><i class="bi bi-building text-primary"></i> Entrepôts &amp; magasins</h1>
            <p>Sites physiques de stockage. Un seul suffit pour un magasin unique ; en créer d'autres active les transferts inter-sites.</p>
        </div>
        <a href="{{ route('warehouses.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nouvel entrepôt</a>
    </div>

    <div class="tbl-wrap">
        <table>
            <thead><tr><th>Nom</th><th>Adresse</th><th></th><th></th></tr></thead>
            <tbody>
                @foreach ($warehouses as $warehouse)
                    <tr>
                        <td>{{ $warehouse->name }}</td>
                        <td class="muted">{{ $warehouse->address ?? '—' }}</td>
                        <td>@if ($warehouse->is_default) <span class="badge badge-good">par défaut</span> @endif</td>
                        <td><a href="{{ route('warehouses.edit', $warehouse) }}" class="btn btn-sm"><i class="bi bi-pencil-square"></i> Modifier</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-layout>
