<x-layout title="Corbeille">
    <div class="page-head">
        <div>
            <h1>Corbeille</h1>
            <p>Éléments supprimés — restaurables à tout moment, ou supprimables définitivement.</p>
        </div>
    </div>

    @if ($groups->isEmpty())
        <div class="card"><p class="mt-0">La corbeille est vide.</p></div>
    @else
        @foreach ($groups as $group)
            <div class="card">
                <div class="card-head"><h2>{{ $group['label'] }} ({{ $group['items']->count() }})</h2></div>
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
                                                <button type="submit" class="btn btn-sm">Restaurer</button>
                                            </form>
                                            <form method="POST" action="{{ route('trash.force-delete', [$group['type'], $item->id]) }}" onsubmit="return confirm('Supprimer définitivement ? Cette action est irréversible.');">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-danger">Supprimer définitivement</button>
                                            </form>
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
