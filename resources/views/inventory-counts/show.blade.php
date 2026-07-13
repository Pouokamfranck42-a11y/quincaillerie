<x-layout :title="'Inventaire #'.$inventoryCount->id">
    <div class="page-head">
        <div>
            <h1><i class="bi bi-clipboard-check text-primary"></i> Inventaire #{{ $inventoryCount->id }}</h1>
            <p>{{ $inventoryCount->warehouse->name }} · {{ $inventoryCount->category?->name ?? 'Tout le catalogue' }} · créé le {{ $inventoryCount->created_at->format('d/m/Y') }} par {{ $inventoryCount->user->name }}</p>
        </div>
        @if ($inventoryCount->status === 'completed')
            <span class="badge badge-good">clôturé le {{ $inventoryCount->completed_at->format('d/m/Y H:i') }}</span>
        @else
            <span class="badge badge-warn">en cours</span>
        @endif
    </div>

    <div class="card">
        <form method="POST" action="{{ route('inventory-counts.update-lines', $inventoryCount) }}">
            @csrf
            <div class="tbl-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Produit</th><th class="num">Stock théorique</th><th class="num">Quantité comptée</th><th class="num">Écart</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($inventoryCount->lines as $line)
                            <tr>
                                <td>{{ $line->product->name }} <span class="muted mono">({{ $line->product->reference }})</span></td>
                                <td class="num">{{ rtrim(rtrim(number_format($line->expected_quantity, 2, ',', ' '), '0'), ',') }}</td>
                                <td class="num">
                                    @if ($inventoryCount->status === 'in_progress')
                                        <input type="number" step="0.01" min="0" name="counted[{{ $line->id }}]" value="{{ $line->counted_quantity }}" style="width:110px; text-align:right">
                                    @else
                                        {{ $line->counted_quantity !== null ? rtrim(rtrim(number_format($line->counted_quantity, 2, ',', ' '), '0'), ',') : '—' }}
                                    @endif
                                </td>
                                <td class="num">
                                    @if ($line->counted_quantity !== null)
                                        <span class="{{ $line->hasDiscrepancy() ? 'badge badge-warn' : 'badge badge-good' }}">
                                            {{ $line->discrepancy() > 0 ? '+' : '' }}{{ rtrim(rtrim(number_format($line->discrepancy(), 2, ',', ' '), '0'), ',') }}
                                        </span>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($inventoryCount->status === 'in_progress')
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Enregistrer les quantités saisies</button>
                </div>
            @endif
        </form>

        @if ($inventoryCount->status === 'in_progress')
            <form method="POST" action="{{ route('inventory-counts.complete', $inventoryCount) }}" id="complete-count-form" style="margin-top:20px; border-top:1px solid var(--steel-200); padding-top:16px">
                @csrf
                <p class="mt-0">La clôture génère un mouvement d'ajustement pour chaque écart constaté, puis verrouille le comptage.</p>
                <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#confirm-complete"><i class="bi bi-lock-fill"></i> Clôturer l'inventaire</button>
            </form>
            <x-confirm-modal id="confirm-complete" title="Clôturer l'inventaire ?" body="Les écarts saisis génèreront des mouvements d'ajustement de stock — cette action est irréversible.">
                <button type="submit" form="complete-count-form" class="btn btn-danger"><i class="bi bi-check-lg"></i> Clôturer définitivement</button>
            </x-confirm-modal>
        @endif

        @if ($inventoryCount->notes)
            <p style="margin-top:16px"><strong>Notes :</strong> {{ $inventoryCount->notes }}</p>
        @endif
    </div>
</x-layout>
