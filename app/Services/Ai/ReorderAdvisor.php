<?php

namespace App\Services\Ai;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Réapprovisionnement intelligent (Phase 3) : classement par URGENCE réelle (date limite de
 * commande, compte tenu du délai fournisseur — jamais exploité jusqu'ici) plutôt qu'un simple
 * "sous le seuil oui/non". Le calcul lui-même est statistique (Product::recommendedOrderByDate(),
 * déjà basé sur la prévision de demande pondérée existante) — l'IA n'intervient qu'en surcouche
 * pour produire un résumé exécutif lisible, jamais pour le chiffre lui-même (une IA générative
 * n'est pas fiable pour faire des additions).
 */
class ReorderAdvisor
{
    public function __construct(private readonly GeminiService $gemini = new GeminiService())
    {
    }

    /** @return Collection<int, array{product: Product, stockout_date: ?\Illuminate\Support\Carbon, order_by_date: ?\Illuminate\Support\Carbon, urgent: bool, seasonality: ?string}> */
    public function rankedSuggestions(): Collection
    {
        return Product::query()
            ->where('active', true)
            ->whereNotNull('supplier_id')
            ->with('supplier')
            ->get()
            ->filter(fn (Product $p) => $p->needsReorder())
            ->map(fn (Product $p) => [
                'product' => $p,
                'stockout_date' => $p->projectedStockoutDate(),
                'order_by_date' => $p->recommendedOrderByDate(),
                'urgent' => $p->isUrgentReorder(),
                'seasonality' => $p->seasonalityNote(),
            ])
            ->sortBy(fn (array $row) => $row['order_by_date'] ?? now()->addYears(10))
            ->values();
    }

    /**
     * Résumé en langage naturel, priorisant les plus urgents — dégrade silencieusement à null
     * si Gemini échoue (quota atteint, clé invalide, etc.) : jamais bloquant, la liste
     * statistique reste utilisable seule. Mis en cache 6h pour ne pas consommer de quota à
     * chaque affichage de la page (les priorités de réappro ne changent pas minute par minute).
     *
     * @param  Collection<int, array<string, mixed>>  $suggestions
     */
    public function narrativeSummary(Collection $suggestions): ?string
    {
        if ($suggestions->isEmpty()) {
            return null;
        }

        $cacheKey = 'ai.reorder-summary.'.md5($suggestions->pluck('product.id')->implode(','));

        return Cache::remember($cacheKey, now()->addHours(6), function () use ($suggestions) {
            $lines = $suggestions->take(15)->map(fn (array $row) => sprintf(
                '- %s : %s %s disponible, rupture prévue vers le %s, commander avant le %s%s%s',
                $row['product']->name,
                rtrim(rtrim(number_format($row['product']->availableStock(), 2, '.', ''), '0'), '.'),
                $row['product']->unit,
                $row['stockout_date']?->format('d/m/Y') ?? 'indéterminée',
                $row['order_by_date']?->format('d/m/Y') ?? 'dès que possible',
                $row['urgent'] ? ' [URGENT]' : '',
                $row['seasonality'] ? ' — '.$row['seasonality'] : '',
            ))->implode("\n");

            $system = "Tu es un conseiller achats pour une quincaillerie camerounaise. On te donne une liste "
                ."d'articles à réapprovisionner avec leur date de rupture prévue et leur date limite de commande "
                ."(calculée à partir du délai de livraison du fournisseur). Rédige un résumé actionnable de 3 à 5 "
                .'lignes en français, DESTINÉ AU GÉRANT : nomme explicitement les articles marqués [URGENT] par leur '
                .'nom avec leur date limite de commande, regroupe les autres par fournisseur si pertinent pour '
                .'grouper les commandes. N\'invente aucun chiffre au-delà de ceux fournis — cite les noms de produits '
                .'et dates telles quelles.';

            // maxTokens généreux : voir DormantStockAdvisor / ProductDescriptionController pour
            // le même piège déjà rencontré (réflexion interne du modèle qui absorbe le budget).
            $result = $this->gemini->generateText($system, $lines, 2048);

            return trim($result) !== '' ? trim($result) : null;
        });
    }
}
