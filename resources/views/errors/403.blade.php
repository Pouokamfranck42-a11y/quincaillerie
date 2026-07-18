<x-layout title="Accès refusé">
    <div class="page-head"><h1><i class="bi bi-shield-lock text-danger"></i> Accès refusé</h1></div>
    <div class="card" style="max-width:560px">
        <p class="mt-0">Vous n'avez pas la permission nécessaire pour accéder à cette page ou effectuer cette action.</p>
        <p class="muted">Si vous pensez qu'il s'agit d'une erreur, contactez un administrateur — cette tentative a été journalisée.</p>
        <a href="{{ route('dashboard') }}" class="btn btn-primary"><i class="bi bi-house"></i> Retour au tableau de bord</a>
    </div>
</x-layout>
