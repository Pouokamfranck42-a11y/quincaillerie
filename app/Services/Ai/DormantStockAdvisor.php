<?php

namespace App\Services\Ai;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Détection dormants/surstock (Phase 3) : le calcul (Product::isDormant(), capitalTiedUp(),
 * isOverstock()) est statistique et déjà éprouvé (voir StockReportController). Ce qui manquait
 * réellement : une ACTION suggérée par article, pas seulement un constat. Repli rule-based si
 * l'IA est indisponible — l'écran reste utilisable sans aucun appel réseau.
 */
class DormantStockAdvisor
{
    public function __construct(private readonly GeminiService $gemini = new GeminiService())
    {
    }

    /** @return Collection<int, array{product: Product, dormant: bool, overstock: bool, capital_tied_up: float, days_since_last_sale: ?int, suggested_action: string}> */
    public function rankedOpportunities(int $limit = 30): Collection
    {
        return Product::query()
            ->where('active', true)
            ->get()
            ->filter(fn (Product $p) => $p->isDormant() || $p->isOverstock())
            ->map(fn (Product $p) => [
                'product' => $p,
                'dormant' => $p->isDormant(),
                'overstock' => $p->isOverstock(),
                'capital_tied_up' => $p->capitalTiedUp(),
                'days_since_last_sale' => $p->daysSinceLastSale(),
                'suggested_action' => $this->ruleBasedAction($p),
            ])
            ->sortByDesc('capital_tied_up')
            ->take($limit)
            ->values();
    }

    /** Repli sans IA — toujours disponible, ne dépend d'aucun appel réseau. */
    private function ruleBasedAction(Product $product): string
    {
        if ($product->isDormant() && $product->isOverstock()) {
            return 'Dormant ET en surstock — promotion ou déstockage prioritaire, argent immobilisé sans rotation.';
        }
        if ($product->isDormant()) {
            return 'Aucune vente récente — envisager une promotion ciblée, un retour fournisseur si possible, ou arrêter le réapprovisionnement.';
        }

        return 'Stock au-delà du maximum défini — ralentir ou suspendre les prochaines commandes sur cet article.';
    }

    /**
     * Résumé + priorisation en langage naturel — dégrade silencieusement à null si Gemini
     * échoue, la liste + les actions rule-based restent utilisables seules. Mis en cache 6h.
     *
     * @param  Collection<int, array<string, mixed>>  $opportunities
     */
    public function narrativeSummary(Collection $opportunities): ?string
    {
        if ($opportunities->isEmpty()) {
            return null;
        }

        $cacheKey = 'ai.dormant-summary.'.md5($opportunities->pluck('product.id')->implode(','));

        return Cache::remember($cacheKey, now()->addHours(6), function () use ($opportunities) {
            $totalTiedUp = $opportunities->sum('capital_tied_up');

            $lines = $opportunities->take(15)->map(fn (array $row) => sprintf(
                '- %s : %s FCFA immobilisés%s%s',
                $row['product']->name,
                number_format($row['capital_tied_up'], 0, ',', ' '),
                $row['days_since_last_sale'] !== null ? ", aucune vente depuis {$row['days_since_last_sale']} jours" : ', jamais vendu',
                $row['overstock'] ? ' — surstock' : '',
            ))->implode("\n");

            $system = "Tu es un conseiller retail pour une quincaillerie camerounaise. On te donne une liste "
                ."d'articles dormants ou en surstock avec l'argent immobilisé (total : ".number_format($totalTiedUp, 0, ',', ' ')." FCFA). "
                .'Rédige un résumé actionnable de 3 à 5 lignes en français, DESTINÉ AU GÉRANT (pas un simple constat '
                .'du total) : nomme explicitement les 2 ou 3 articles les plus coûteux par leur nom, et pour CHACUN '
                .'propose une action concrète et réaliste pour un petit commerce (ex. "-20% sur X pendant 2 semaines", '
                .'"arrêter de réapprovisionner Y", "regrouper Z en lot avec un produit qui se vend bien"). '
                .'N\'invente aucun chiffre au-delà de ceux fournis dans la liste.';

            // maxTokens généreux : les modèles Gemini récents consomment une partie du budget en
            // "réflexion" interne avant le texte visible — un budget trop juste coupe la réponse
            // en plein milieu de phrase (constaté en vérification live avec 500 — même piège déjà
            // documenté dans ProductDescriptionController).
            $result = $this->gemini->generateText($system, $lines, 2048);

            return trim($result) !== '' ? trim($result) : null;
        });
    }
}
