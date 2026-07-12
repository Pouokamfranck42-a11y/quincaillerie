<x-layout title="Journal d'audit">
    <div class="page-head">
        <div>
            <h1>Journal d'audit</h1>
            <p>Traçabilité des actions sensibles : changements de prix, changements de rôle.</p>
        </div>
    </div>

    <div class="tbl-wrap">
        <table>
            <thead><tr><th>Date</th><th>Utilisateur</th><th>Action</th><th>Avant</th><th>Après</th></tr></thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td class="mono">{{ $log->created_at->format('d/m/Y H:i') }}</td>
                        <td>{{ $log->user?->name ?? 'système' }}</td>
                        <td>
                            @if ($log->action === 'product.price_changed') <span class="badge badge-warn">prix produit modifié</span>
                            @elseif ($log->action === 'user.role_changed') <span class="badge badge-warn">rôle modifié</span>
                            @else {{ $log->action }}
                            @endif
                            <span class="muted">#{{ $log->auditable_id }}</span>
                        </td>
                        <td class="mono" style="font-size:12px">{{ collect($log->old_values)->map(fn ($v, $k) => "$k: $v")->implode(' · ') }}</td>
                        <td class="mono" style="font-size:12px">{{ collect($log->new_values)->map(fn ($v, $k) => "$k: $v")->implode(' · ') }}</td>
                    </tr>
                @empty
                    <tr class="empty-row"><td colspan="5">Aucune action sensible enregistrée pour l'instant.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:16px">{{ $logs->links() }}</div>
</x-layout>
