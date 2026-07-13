<x-layout title="Corbeille">
    <div class="page-head">
        <div>
            <h1><i class="bi bi-trash3 text-primary"></i> Corbeille</h1>
            <p>Éléments supprimés — restaurables à tout moment, ou supprimables définitivement.</p>
        </div>
    </div>

    @if ($groups->isEmpty())
        <div class="card"><p class="mt-0"><i class="bi bi-inbox"></i> La corbeille est vide.</p></div>
    @else
        @foreach ($groups as $group)
            <div class="card">
                <div class="card-head"><h2><i class="bi bi-folder2"></i> {{ $group['label'] }} ({{ $group['items']->count() }})</h2></div>
                <div class="tbl-wrap">
                    <table>
                        <thead><tr><th>Nom</th><th>Supprimé le</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($group['items'] as $item)
                                <tr>
                                    <td>{{ $item->name }}</td>
                                    <td class="muted">{{ $item->deleted_at->format('d/m/Y H:i') }}</td>
                                    <td>
                                        <div class="table-actions">
                                            <form method="POST" action="{{ route('trash.restore', [$group['type'], $item->id]) }}">
                                                @csrf
                                                <button type="submit" class="btn btn-sm"><i class="bi bi-arrow-counterclockwise"></i> Restaurer</button>
                                            </form>
                                            <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#force-delete-{{ $group['type'] }}-{{ $item->id }}"><i class="bi bi-x-octagon"></i> Supprimer définitivement</button>
                                            <x-confirm-modal id="force-delete-{{ $group['type'] }}-{{ $item->id }}" title="Supprimer définitivement ?" body="Cette action est irréversible.">
                                                <form method="POST" action="{{ route('trash.force-delete', [$group['type'], $item->id]) }}">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="btn btn-danger"><i class="bi bi-x-octagon"></i> Supprimer définitivement</button>
                                                </form>
                                            </x-confirm-modal>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach
    @endif
</x-layout>
