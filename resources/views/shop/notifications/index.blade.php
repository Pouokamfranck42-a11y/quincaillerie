<x-shop-layout title="Notifications">
    <div class="page-head">
        <div>
            <h1><i class="bi bi-bell text-primary"></i> Notifications</h1>
            <p>Suivi de vos commandes.</p>
        </div>
        <form method="POST" action="{{ route('shop.notifications.mark-all-read') }}">
            @csrf
            <button type="submit" class="btn"><i class="bi bi-check2-all"></i> Tout marquer comme lu</button>
        </form>
    </div>

    <div class="tbl-wrap">
        <table>
            <thead><tr><th></th><th>Message</th><th>Date</th><th></th></tr></thead>
            <tbody>
                @forelse ($notifications as $notification)
                    <tr>
                        <td>@if (! $notification->read_at) <span class="badge badge-warn">nouveau</span> @endif</td>
                        <td>
                            {{ $notification->data['message'] ?? '—' }}
                            @if (($notification->data['type'] ?? null) === 'order_ready')
                                <a href="{{ route('shop.account.orders.show', $notification->data['order_id']) }}"><i class="bi bi-box-seam"></i> voir la commande</a>
                            @endif
                        </td>
                        <td class="muted">{{ $notification->created_at->format('d/m/Y H:i') }}</td>
                        <td>
                            @if (! $notification->read_at)
                                <form method="POST" action="{{ route('shop.notifications.mark-read', $notification->id) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-sm"><i class="bi bi-check-lg"></i> Marquer comme lu</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr class="empty-row"><td colspan="4"><i class="bi bi-inbox"></i> Aucune notification.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:16px">{{ $notifications->links() }}</div>
</x-shop-layout>
