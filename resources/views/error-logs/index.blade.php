<x-layout title="Journal des erreurs">
    <div class="page-head">
        <div>
            <h1><i class="bi bi-bug text-primary"></i> Journal des erreurs</h1>
            <p>Exceptions inattendues survenues sur l'application — les rejets normaux (validation, permissions, page introuvable) n'apparaissent pas ici. Chacune déclenche aussi une alerte aux administrateurs.</p>
        </div>
    </div>

    <div class="tbl-wrap">
        <table>
            <thead><tr><th>Date</th><th>Exception</th><th>Message</th><th>Où</th><th>Utilisateur</th></tr></thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td class="mono">{{ $log->created_at->format('d/m/Y H:i') }}</td>
                        <td class="mono" style="font-size:12px">{{ class_basename($log->exception_class) }}</td>
                        <td style="max-width:420px">{{ $log->message }}</td>
                        <td class="mono" style="font-size:12px">
                            @if ($log->method) {{ $log->method }} @endif
                            {{ $log->url }}
                            @if ($log->file)
                                <br><span class="muted">{{ basename($log->file) }}:{{ $log->line }}</span>
                            @endif
                        </td>
                        <td>{{ $log->user?->name ?? '—' }}</td>
                    </tr>
                @empty
                    <tr class="empty-row"><td colspan="5"><i class="bi bi-check-circle"></i> Aucune erreur inattendue enregistrée pour l'instant.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:16px">{{ $logs->links() }}</div>
</x-layout>
