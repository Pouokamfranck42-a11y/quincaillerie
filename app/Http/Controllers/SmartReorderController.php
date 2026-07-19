<?php

namespace App\Http\Controllers;

use App\Services\Ai\ReorderAdvisor;

/**
 * Écran dédié "Réapprovisionnement intelligent" (Phase 3) — distinct de
 * purchase-orders.suggestions (qui reste l'écran d'ACTION pour créer les commandes) : celui-ci
 * est l'écran d'ANALYSE, priorisé par urgence réelle (délai fournisseur inclus) plutôt que par
 * un simple seuil bas/pas bas. Le bouton "créer les commandes" renvoie vers l'écran existant.
 */
class SmartReorderController extends Controller
{
    public function index(ReorderAdvisor $advisor)
    {
        $suggestions = $advisor->rankedSuggestions();
        $summary = $advisor->narrativeSummary($suggestions);

        return view('reorder.index', compact('suggestions', 'summary'));
    }
}
