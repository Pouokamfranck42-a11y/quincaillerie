<x-layout title="Profils">
    <div class="page-head">
        <div>
            <h1><i class="bi bi-shield-lock text-primary"></i> Profils de permissions</h1>
            <p>Paquets de permissions réutilisables, entièrement définis par vous — aucun nom de profil n'est imposé par l'application.</p>
        </div>
        <a href="{{ route('roles.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nouveau profil</a>
    </div>

    <div class="tbl-wrap">
        <table>
            <thead><tr><th>Profil</th><th class="num">Permissions</th><th class="num">Comptes</th><th></th></tr></thead>
            <tbody>
                @forelse ($roles as $role)
                    <tr>
                        <td>{{ $role->name }}</td>
                        <td class="num">{{ $role->permissions_count }}</td>
                        <td class="num">{{ $role->users_count }}</td>
                        <td>
                            <div class="table-actions">
                                <a href="{{ route('roles.edit', $role) }}" class="btn btn-sm"><i class="bi bi-pencil-square"></i> Modifier</a>
                                <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#delete-role-{{ $role->id }}"><i class="bi bi-trash3"></i></button>
                                <x-confirm-modal id="delete-role-{{ $role->id }}" title="Supprimer ce profil ?" body="Les {{ $role->users_count }} compte(s) qui l'utilisent perdront les permissions qu'il leur donnait (sauf celles accordées individuellement).">
                                    <form method="POST" action="{{ route('roles.destroy', $role) }}">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-danger"><i class="bi bi-trash3"></i> Supprimer</button>
                                    </form>
                                </x-confirm-modal>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr class="empty-row"><td colspan="4"><i class="bi bi-inbox"></i> Aucun profil — les comptes n'ont que leurs permissions individuelles.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layout>
