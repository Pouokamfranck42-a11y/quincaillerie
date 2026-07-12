<x-layout title="Utilisateurs">
    <div class="page-head">
        <div>
            <h1>Utilisateurs</h1>
            <p>Comptes d'accès et rôles (admin / magasinier / caissier).</p>
        </div>
        <a href="{{ route('users.create') }}" class="btn btn-primary">+ Nouvel utilisateur</a>
    </div>

    <div class="tbl-wrap">
        <table>
            <thead><tr><th>Nom</th><th>E-mail</th><th>Rôle</th><th></th></tr></thead>
            <tbody>
                @forelse ($users as $user)
                    <tr>
                        <td>{{ $user->name }}</td>
                        <td class="muted">{{ $user->email }}</td>
                        <td><span class="role-pill">{{ $user->roles->first()?->name ?? '—' }}</span></td>
                        <td>
                            <div class="table-actions">
                                <a href="{{ route('users.edit', $user) }}" class="btn btn-sm">Modifier</a>
                                @if ($user->id !== auth()->id())
                                    <form method="POST" action="{{ route('users.destroy', $user) }}" onsubmit="return confirm('Supprimer cet utilisateur ?');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger">Supprimer</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr class="empty-row"><td colspan="4">Aucun utilisateur.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:16px">{{ $users->links() }}</div>
</x-layout>
