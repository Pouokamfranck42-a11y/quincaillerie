<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Facture {{ $invoice->number }}</title>
    <style>
        body{ font-family: -apple-system, "Segoe UI", Arial, sans-serif; color:#1B2027; max-width:680px; margin:32px auto; padding:0 20px; }
        h1{ font-size:20px; margin:0 0 2px; }
        .muted{ color:#6B7280; font-size:13px; }
        table{ width:100%; border-collapse:collapse; margin-top:20px; font-size:14px; }
        th, td{ text-align:left; padding:8px 6px; border-bottom:1px solid #DBDED6; }
        th{ font-size:11px; text-transform:uppercase; letter-spacing:.04em; color:#6B7280; }
        td.num, th.num{ text-align:right; font-variant-numeric:tabular-nums; }
        .totals{ margin-top:16px; margin-left:auto; width:260px; }
        .totals .row{ display:flex; justify-content:space-between; padding:3px 0; }
        .totals .row.total{ font-weight:700; font-size:16px; border-top:2px solid #1B2027; margin-top:6px; padding-top:6px; }
        .foot{ margin-top:40px; font-size:12px; color:#6B7280; }
        .parties{ display:flex; justify-content:space-between; gap:24px; margin-top:20px; font-size:13px; }
        .warning{ background:#FEF3C7; border:1px solid #F6C453; border-left:4px solid #D97706; border-radius:6px; padding:12px 14px; font-size:12.5px; margin-top:16px; color:#7A4A04; }
        @media print { .no-print{ display:none; } }
    </style>
</head>
<body>
    <button class="no-print" onclick="window.print()" style="float:right">🖨 Imprimer</button>

    @if (session('auto_print'))
        <script>window.addEventListener('load', () => window.print());</script>
    @endif

    @if (empty(config('company.niu')) || empty(config('company.rccm')))
        <div class="warning no-print">
            ⚠ <strong>Document non conforme en l'état.</strong> Le NIU et/ou le RCCM de l'entreprise ne sont pas renseignés
            (voir <code>config/company.php</code> / variables <code>COMPANY_NIU</code> et <code>COMPANY_RCCM</code> dans <code>.env</code>).
            Ne pas remettre ce document à un client tant que ces informations et la conformité générale n'ont pas été validées par un comptable.
        </div>
    @endif

    <h1>Facture {{ $invoice->number }}</h1>
    <p class="muted">Émise le {{ $invoice->issued_at->format('d/m/Y') }} — pièce référencée : vente #{{ $invoice->invoiceable_id }}</p>

    <div class="parties">
        <div>
            <strong>{{ config('company.name') }}</strong><br>
            @if (config('company.address')) {{ config('company.address') }}<br> @endif
            @if (config('company.phone')) Tél : {{ config('company.phone') }}<br> @endif
            NIU : {{ config('company.niu') ?: '— à compléter —' }}<br>
            RCCM : {{ config('company.rccm') ?: '— à compléter —' }}
        </div>
        <div>
            <strong>Client</strong><br>
            {{ $invoice->customer?->name ?? 'Client de passage' }}<br>
            @if ($invoice->customer?->address) {{ $invoice->customer->address }}<br> @endif
            @if ($invoice->customer?->niu) NIU : {{ $invoice->customer->niu }} @endif
        </div>
    </div>

    <table>
        <thead><tr><th>Produit</th><th class="num">Qté</th><th class="num">Prix</th><th class="num">Total</th></tr></thead>
        <tbody>
            @foreach ($invoice->invoiceable->lines as $line)
                <tr>
                    <td>{{ $line->product->name }} <span class="muted">({{ $line->product->reference }})</span></td>
                    <td class="num">{{ rtrim(rtrim(number_format($line->quantity, 2, ',', ' '), '0'), ',') }}</td>
                    <td class="num">{{ number_format($line->unit_price, 0, ',', ' ') }}</td>
                    <td class="num">{{ number_format($line->quantity * $line->unit_price, 0, ',', ' ') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        <div class="row"><span>Sous-total</span><span>{{ number_format($invoice->subtotal, 0, ',', ' ') }}</span></div>
        <div class="row"><span>TVA ({{ rtrim(rtrim(number_format($invoice->tax_rate, 2), '0'), '.') }}%)</span><span>{{ number_format($invoice->tax_amount, 0, ',', ' ') }}</span></div>
        <div class="row total"><span>Total TTC</span><span>{{ number_format($invoice->total, 0, ',', ' ') }} FCFA</span></div>
    </div>

    <p class="foot">
        Numérotation séquentielle sans saut — pièce {{ $invoice->number }}.
        @if (! config('company.vat_subject')) Entreprise non assujettie à la TVA (à confirmer). @endif
    </p>
</body>
</html>
