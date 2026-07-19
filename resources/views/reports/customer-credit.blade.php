<x-layout title="Encours clients">
    <div class="page-head">
        <div>
            <h1><i class="bi bi-cash-coin text-primary"></i> Encours clients</h1>
            <p>Qui doit de l'argent à la quincaillerie, et depuis quand — pour la relance et le recouvrement.</p>
        </div>
    </div>

    <div class="stat-grid">
        <div class="stat-tile @if($totalOutstanding > 0) warn @endif">
            <div class="lbl"><i class="bi bi-cash-stack"></i> Encours total</div>
            <div class="val">{{ number_format($totalOutstanding, 0, ',', ' ') }}</div>
        </div>
        <div class="stat-tile @if($totalOverdue > 0) crit @endif">
            <div class="lbl"><i class="bi bi-exclamation-triangle"></i> Dont en retard</div>
            <div class="val">{{ number_format($totalOverdue, 0, ',', ' ') }}</div>
            <div class="sub">{{ $overdueCount }} client(s)</div>
        </div>
    </div>

    <div class="tbl-wrap">
        <table>
            <thead><tr><th>Client</th><th>Type</th><th>Échéance la plus ancienne</th><th class="num">Retard</th><th class="num">Encours</th><th class="num">Plafond</th><th></th></tr></thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>{{ $row['customer']->name }}</td>
                        <td><span class="badge badge-neutral">{{ $row['customer']->type }}</span></td>
                        <td class="muted">{{ $row['oldest_due_date']?->format('d/m/Y') ?? '—' }}</td>
                        <td class="num">
                            @if ($row['is_overdue'])
                                <span class="badge badge-crit">{{ $row['days_overdue'] }} j</span>
                            @else
                                <span class="muted">à jour</span>
                            @endif
                        </td>
                        <td class="num">{{ number_format($row['outstanding'], 0, ',', ' ') }}</td>
                        <td class="num muted">{{ number_format($row['customer']->credit_limit, 0, ',', ' ') }}</td>
                        <td><a href="{{ route('customers.statement', $row['customer']) }}" class="btn btn-sm"><i class="bi bi-file-earmark-text"></i> Relevé</a></td>
                    </tr>
                @empty
                    <tr class="empty-row"><td colspan="7"><i class="bi bi-check-circle"></i> Aucun encours en cours — tous les comptes clients sont soldés.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layout>
