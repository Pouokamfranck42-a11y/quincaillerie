<x-layout title="Notifications">
    <div class="page-head">
        <div>
            <h1>Notifications</h1>
            <p>Alertes de seuil de stock et de péremption proche.</p>
        </div>
        <form method="POST" action="{{ route('notifications.mark-all-read') }}">
            @csrf
            <button type="submit" class="btn">Tout marquer comme lu</button>
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
                            @if (($notification->data['type'] ?? null) === 'low_stock')
                                <a href="{{ route('products.show', $notification->data['product_id']) }}">voir le produit</a>
                            @elseif (($notification->data['type'] ?? null) === 'lot_expiring')
                                <a href="{{ route('products.show', $notification->data['product_id']) }}">voir le produit</a>
                            @endif
                        </td>
                        <td class="muted">{{ $notification->created_at->format('d/m/Y H:i') }}</td>
                        <td>
                            @if (! $notification->read_at)
                                <form method="POST" action="{{ route('notifications.mark-read', $notification->id) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-sm">Marquer comme lu</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr class="empty-row"><td colspan="4">Aucune notification.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:16px">{{ $notifications->links() }}</div>
</x-layout>
