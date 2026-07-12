@props(['target'])
<div data-barcode-scanner data-scan-target="#{{ $target }}" style="margin-top:6px">
    <button type="button" data-scan-button class="btn btn-sm">📷 Scanner un code-barres</button>
    <button type="button" data-scan-close class="btn btn-sm btn-ghost" style="display:none">Arrêter le scan</button>
    <video data-scan-video muted playsinline style="display:none; width:100%; max-width:320px; margin-top:8px; border-radius:var(--radius); border:1px solid var(--steel-200)"></video>
</div>
