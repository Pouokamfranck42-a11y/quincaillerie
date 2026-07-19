<?php

namespace App\Http\Controllers;

use App\Services\Ai\DormantStockAdvisor;

/**
 * Écran dédié "Articles dormants — IA" (Phase 3) — distinct de reports.stock (qui garde sa
 * valorisation/rotation/ABC complète) : celui-ci se concentre sur l'argent immobilisé avec une
 * action suggérée par article, résumé exécutif IA en tête (dégrade proprement si indisponible).
 */
class DormantStockController extends Controller
{
    public function index(DormantStockAdvisor $advisor)
    {
        $opportunities = $advisor->rankedOpportunities();
        $summary = $advisor->narrativeSummary($opportunities);
        $totalTiedUp = $opportunities->sum('capital_tied_up');

        return view('dormant-stock.index', compact('opportunities', 'summary', 'totalTiedUp'));
    }
}
