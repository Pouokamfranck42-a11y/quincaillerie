<x-layout title="Export comptable">
    <div class="page-head">
        <h1><i class="bi bi-file-earmark-spreadsheet text-primary"></i> Export comptable (SYSCOHADA)</h1>
    </div>

    <div class="alert alert-warn">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <span>
            <strong>Mapping de comptes simplifié, non validé par un comptable.</strong>
            Cet export génère un journal des ventes en partie double (701 Ventes de marchandises,
            4431 TVA collectée, 411/571 Clients/Caisse) à titre de point de départ — à faire vérifier
            par un comptable ou expert-comptable avant tout usage réel dans votre plan comptable.
        </span>
    </div>

    <div class="card" style="max-width:480px">
        <form method="GET" action="{{ route('accounting-export.export') }}">
            <div class="field-row">
                <div class="field">
                    <label for="from">Du</label>
                    <input type="date" id="from" name="from" value="{{ now()->startOfMonth()->format('Y-m-d') }}" required>
                </div>
                <div class="field">
                    <label for="to">Au</label>
                    <input type="date" id="to" name="to" value="{{ now()->format('Y-m-d') }}" required>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><i class="bi bi-download"></i> Télécharger le CSV</button>
            </div>
        </form>
    </div>
</x-layout>
