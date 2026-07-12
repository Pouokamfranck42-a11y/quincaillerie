<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Étiquette — {{ $product->name }}</title>
    <style>
        body{ font-family: -apple-system, "Segoe UI", Arial, sans-serif; margin:24px; color:#1B2027; }
        .label{
            width:280px; border:1px solid #1B2027; border-radius:4px; padding:12px 14px;
            display:inline-block; margin:0 12px 12px 0;
        }
        .label .name{ font-weight:700; font-size:14px; margin:0 0 2px; }
        .label .ref{ font-family:ui-monospace, Consolas, monospace; font-size:11px; color:#6B7280; margin:0 0 8px; }
        .label .price{ font-size:20px; font-weight:700; margin:6px 0; }
        .label svg{ width:100%; height:auto; }
        .no-print{ margin-bottom:16px; }
        @media print { .no-print{ display:none; } }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()">Imprimer</button>
        <label>Nombre d'étiquettes : <input type="number" id="copies" value="1" min="1" max="20" style="width:60px"></label>
        <button onclick="renderCopies()">Générer</button>
    </div>

    <div id="labels">
        <div class="label">
            <p class="name">{{ $product->name }}</p>
            <p class="ref">{{ $product->reference }}{{ $product->brand ? ' · '.$product->brand : '' }}</p>
            @if ($product->barcode)
                <svg class="barcode" data-code="{{ $product->barcode }}"></svg>
            @endif
            <p class="price">{{ number_format($product->sale_price, 0, ',', ' ') }} FCFA</p>
        </div>
    </div>

    <script src="{{ asset('js/vendor/jsbarcode.min.js') }}"></script>
    <script>
        function renderBarcodes() {
            document.querySelectorAll('svg.barcode').forEach(function (svg) {
                JsBarcode(svg, svg.dataset.code, { format: 'CODE128', height: 40, fontSize: 12, margin: 4 });
            });
        }
        function renderCopies() {
            const n = parseInt(document.getElementById('copies').value, 10) || 1;
            const first = document.querySelector('.label');
            const container = document.getElementById('labels');
            container.innerHTML = '';
            for (let i = 0; i < n; i++) {
                container.appendChild(first.cloneNode(true));
            }
            renderBarcodes();
        }
        renderBarcodes();
    </script>
</body>
</html>
