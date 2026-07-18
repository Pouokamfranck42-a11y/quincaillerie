<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Bon de livraison — Vente #{{ $sale->id }}</title>
    <style>
        body{ font-family: -apple-system, "Segoe UI", Arial, sans-serif; color:#1B2027; max-width:640px; margin:32px auto; padding:0 20px; }
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
        @media print { .no-print{ display:none; } }
    </style>
</head>
<body>
    <button class="no-print" onclick="window.print()" style="float:right">🖨 Imprimer</button>
    <h1>Bon de livraison</h1>
    <p class="muted">Vente #{{ $sale->id }} · {{ $sale->created_at->format('d/m/Y H:i') }}</p>
    <p class="muted">Client : {{ $sale->customer?->name ?? 'Client de passage' }}<br>Vendu par : {{ $sale->user?->name ?? 'Vente en ligne' }}</p>

    <table>
        <thead><tr><th>Produit</th><th class="num">Qté</th><th class="num">Prix</th><th class="num">Total</th></tr></thead>
        <tbody>
            @foreach ($sale->lines as $line)
                <tr>
                    <td>{{ $line->product->name }} <span class="muted">({{ $line->product->reference }})</span></td>
                    <td class="num">{{ rtrim(rtrim(number_format($line->quantity, 2, ',', ' '), '0'), ',') }}</td>
                    <td class="num">{{ number_format($line->unit_price, 0, ',', ' ') }}</td>
                    <td class="num">{{ number_format($line->lineTotal(), 0, ',', ' ') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        <div class="row"><span>Sous-total</span><span>{{ number_format($sale->subtotal, 0, ',', ' ') }}</span></div>
        <div class="row"><span>TVA</span><span>{{ number_format($sale->tax_amount, 0, ',', ' ') }}</span></div>
        <div class="row total"><span>Total</span><span>{{ number_format($sale->total, 0, ',', ' ') }} FCFA</span></div>
    </div>

    <p class="foot">Paiement : {{ $sale->payment_status === 'due' ? 'à crédit (échéance ' . $sale->due_date?->format('d/m/Y') . ')' : $sale->payment_method }}</p>
</body>
</html>
