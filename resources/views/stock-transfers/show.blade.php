<x-layout :title="'Transfert #'.$stockTransfer->id">
    <div class="page-head">
        <div>
            <h1>Transfert #{{ $stockTransfer->id }}</h1>
            <p>{{ $stockTransfer->fromWarehouse->name }} → {{ $stockTransfer->toWarehouse->name }} · créé le {{ $stockTransfer->created_at->format('d/m/Y') }} par {{ $stockTransfer->user->name }}</p>
        </div>
        @if ($stockTransfer->status !== 'completed')
            <form method="POST" action="{{ route('stock-transfers.execute', $stockTransfer) }}" onsubmit="return confirm('Exécuter ce transfert ? Le stock sera déplacé immédiatement.');">
                @csrf
                <button type="submit" class="btn btn-primary">Exécuter le transfert</button>
            </form>
        @else
            <span class="badge badge-good">exécuté le {{ $stockTransfer->completed_at->format('d/m/Y H:i') }}</span>
        @endif
    </div>

    <div class="card">
        <div class="tbl-wrap">
            <table>
                <thead><tr><th>Produit</th><th class="num">Quantité</th></tr></thead>
                <tbody>
                    @foreach ($stockTransfer->lines as $line)
                        <tr>
                            <td>{{ $line->product->name }}</td>
                            <td class="num">{{ rtrim(rtrim(number_format($line->quantity, 2, ',', ' '), '0'), ',') }} {{ $line->product->unit }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($stockTransfer->notes)
            <p style="margin-top:16px"><strong>Notes :</strong> {{ $stockTransfer->notes }}</p>
        @endif
    </div>
</x-layout>
