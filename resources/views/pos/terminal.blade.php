<x-layout title="Caisse">
    @php $expected = $session->opening_amount + $session->salesTotal(); @endphp
    <div class="page-head" x-data="{ closing: false }">
        <div>
            <h1>Caisse</h1>
            <p>Session ouverte le {{ $session->opened_at->format('d/m/Y à H:i') }} · fond de caisse {{ number_format($session->opening_amount, 0, ',', ' ') }} FCFA</p>
        </div>
        <div>
            <button type="button" class="btn" @click="closing = true" x-show="!closing">Fermer la caisse</button>
            <form method="POST" action="{{ route('cash-sessions.close') }}" class="flex" x-show="closing" x-cloak>
                @csrf
                <span class="muted">Attendu : {{ number_format($expected, 0, ',', ' ') }}</span>
                <input type="number" step="1" min="0" name="closing_amount" placeholder="Montant compté" required style="width:160px">
                <button type="submit" class="btn btn-primary btn-sm">Confirmer</button>
                <button type="button" class="btn btn-ghost btn-sm" @click="closing = false">Annuler</button>
            </form>
        </div>
    </div>

    <livewire:pos.terminal :session="$session" />
</x-layout>
