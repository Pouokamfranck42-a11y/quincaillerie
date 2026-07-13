<x-layout :title="'Transfert #'.$stockTransfer->id">
    <div class="page-head">
        <div>
            <h1><i class="bi bi-arrow-repeat text-primary"></i> Transfert #{{ $stockTransfer->id }}</h1>
            <p>{{ $stockTransfer->fromWarehouse->name }} → {{ $stockTransfer->toWarehouse->name }} · créé le {{ $stockTransfer->created_at->format('d/m/Y') }} par {{ $stockTransfer->user->name }}</p>
        </div>
        @if ($stockTransfer->status !== 'completed')
            <form method="POST" action="{{ route('stock-transfers.execute', $stockTransfer) }}" id="execute-transfer-form">
                @csrf
            </form>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#confirm-execute"><i class="bi bi-play-fill"></i> Exécuter le transfert</button>
            <x-confirm-modal id="confirm-execute" title="Exécuter ce transfert ?" body="Le stock sera déplacé immédiatement entre les deux entrepôts.">
                <button type="submit" form="execute-transfer-form" class="btn btn-primary"><i class="bi bi-check-lg"></i> Confirmer</button>
            </x-confirm-modal>
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
