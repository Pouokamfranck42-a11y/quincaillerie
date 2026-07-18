@props(['status'])
@php
    $map = [
        'reservee' => ['label' => 'Réservée — en attente de paiement', 'class' => 'badge-warn'],
        'payee' => ['label' => 'Payée', 'class' => 'badge-good'],
        'preparation' => ['label' => 'En préparation', 'class' => 'badge-neutral'],
        'prete' => ['label' => 'Prête', 'class' => 'badge-good'],
        'livree' => ['label' => 'Livrée', 'class' => 'badge-good'],
        'retiree' => ['label' => 'Retirée', 'class' => 'badge-good'],
        'annulee' => ['label' => 'Annulée', 'class' => 'badge-crit'],
        'retournee' => ['label' => 'Retournée', 'class' => 'badge-crit'],
    ];
    $info = $map[$status] ?? ['label' => $status, 'class' => 'badge-neutral'];
@endphp
<span class="badge {{ $info['class'] }}">{{ $info['label'] }}</span>
