<x-layout :title="'Dossier SAV #'.$ticket->id">
    <a href="{{ route('service-tickets.index') }}" class="btn btn-ghost btn-sm" style="margin-bottom:16px"><i class="bi bi-arrow-left"></i> SAV</a>

    <div class="page-head">
        <div>
            <h1><i class="bi bi-tools text-primary"></i> Dossier SAV #{{ $ticket->id }}</h1>
            <p>
                <a href="{{ route('sales.show', $ticket->saleLine->sale_id) }}">Vente #{{ $ticket->saleLine->sale_id }}</a>
                · {{ $ticket->saleLine->product->name }}
                · {{ $ticket->saleLine->sale->customer?->name ?? 'Client de passage' }}
            </p>
        </div>
        @if ($ticket->status === 'ouvert') <span class="badge badge-warn">ouvert</span>
        @elseif ($ticket->status === 'en_cours') <span class="badge badge-neutral">en cours</span>
        @elseif ($ticket->status === 'resolu') <span class="badge badge-good">résolu</span>
        @else <span class="badge badge-crit">refusé</span>
        @endif
    </div>

    <div class="card">
        <p><strong>Problème signalé :</strong><br>{{ $ticket->issue_description }}</p>
        <p class="muted">Ouvert le {{ $ticket->created_at->format('d/m/Y H:i') }} par {{ $ticket->openedBy->name }}</p>

        @if ($ticket->saleLine->isUnderWarranty())
            <p><span class="badge badge-good"><i class="bi bi-shield-check"></i> Sous garantie</span></p>
        @endif

        @if ($ticket->resolved_at)
            <hr>
            <p><strong>Résolution :</strong>
                @if ($ticket->resolution_type === 'reparation') Réparé
                @elseif ($ticket->resolution_type === 'echange') Échangé
                @elseif ($ticket->resolution_type === 'remboursement') Remboursé (retour stock)
                @else Refusé
                @endif
            </p>
            @if ($ticket->resolution_notes)
                <p>{{ $ticket->resolution_notes }}</p>
            @endif
            <p class="muted">Résolu le {{ $ticket->resolved_at->format('d/m/Y H:i') }} par {{ $ticket->resolvedBy?->name }}</p>
        @endif
    </div>

    @if (! $ticket->resolved_at)
        <div class="card">
            <div class="card-head"><h2><i class="bi bi-check2-square"></i> Résoudre le dossier</h2></div>
            <form method="POST" action="{{ route('service-tickets.resolve', $ticket) }}" x-data="{ type: '' }">
                @csrf
                <div class="field">
                    <label for="resolution_type">Résolution</label>
                    <select id="resolution_type" name="resolution_type" x-model="type" required>
                        <option value="">— Choisir —</option>
                        <option value="reparation">Réparation (le produit reste chez le client, pas de mouvement de stock)</option>
                        <option value="echange">Échange (retour stock du produit défectueux)</option>
                        <option value="remboursement">Remboursement (retour stock)</option>
                        <option value="refuse">Refuser la prise en charge</option>
                    </select>
                </div>
                <div class="field" x-show="type === 'echange' || type === 'remboursement'">
                    <label for="return_quantity">Quantité à réintégrer en stock</label>
                    <input type="number" id="return_quantity" name="return_quantity" step="0.01" min="0.01" max="{{ $ticket->saleLine->returnableQuantity() }}" value="{{ $ticket->saleLine->returnableQuantity() }}">
                    <div class="hint">Passe par le même circuit que le retour ligne par ligne — disponible : {{ rtrim(rtrim(number_format($ticket->saleLine->returnableQuantity(), 2, ',', ' '), '0'), ',') }}.</div>
                </div>
                <div class="field">
                    <label for="resolution_notes">Notes (optionnel)</label>
                    <textarea id="resolution_notes" name="resolution_notes" rows="3"></textarea>
                </div>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Enregistrer la résolution</button>
            </form>
        </div>
    @endif
</x-layout>
