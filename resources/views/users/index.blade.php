<x-layout title="Utilisateurs">
    <div class="page-head">
        <div>
            <h1><i class="bi bi-people text-primary"></i> Utilisateurs</h1>
            <p>Comptes d'accès et rôles (admin / magasinier / caissier).</p>
        </div>
        <a href="{{ route('users.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nouvel utilisateur</a>
    </div>

    <div class="tbl-wrap">
        <table>
            <thead><tr><th>Nom</th><th>E-mail</th><th>Rôle</th><th></th></tr></thead>
            <tbody>
                @forelse ($users as $user)
                    <tr>
                        <td>{{ $user->name }}</td>
                        <td class="muted">{{ $user->email }}</td>
                        <td><span class="role-pill"><i class="bi bi-shield-check"></i> {{ $user->roles->first()?->name ?? '—' }}</span></td>
                        <td>
                            <div class="table-actions">
                                <a href="{{ route('users.edit', $user) }}" class="btn btn-sm"><i class="bi bi-pencil-square"></i> Modifier</a>
                                @if ($user->id !== auth()->id())
                                    <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#delete-user-{{ $user->id }}"><i class="bi bi-trash3"></i></button>
                                    <x-confirm-modal id="delete-user-{{ $user->id }}" title="Supprimer cet utilisateur ?" body="Cette action est irréversible.">
                                        <form method="POST" action="{{ route('users.destroy', $user) }}">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-danger"><i class="bi bi-trash3"></i> Supprimer</button>
                                        </form>
                                    </x-confirm-modal>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr class="empty-row"><td colspan="4"><i class="bi bi-inbox"></i> Aucun utilisateur.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:16px">{{ $users->links() }}</div>
</x-layout>
