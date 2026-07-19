<?php

namespace App\Services\Ai;

use App\Models\Customer;
use App\Models\Product;

class ChatbotTools
{
    /**
     * Outils exposés à l'assistant : chacun peut interroger les données réelles du magasin
     * (jamais de stock/prix inventé par le modèle).
     *
     * @return array<int, array{name: string, description: string, inputSchema: array<string, mixed>, handler: callable}>
     */
    /**
     * Sous-ensemble sûr pour un visiteur de la boutique en ligne (pas de compte requis) :
     * exclut get_low_stock_products, qui expose des signaux de réapprovisionnement internes
     * (concurrentiellement sensibles) sans intérêt pour un client.
     *
     * @return array<int, array{name: string, description: string, inputSchema: array<string, mixed>, handler: callable}>
     */
    public static function publicDefinitions(): array
    {
        $publicNames = ['search_products', 'check_stock', 'get_product_price'];

        return array_values(array_filter(
            self::definitions(),
            fn (array $tool) => in_array($tool['name'], $publicNames, true),
        ));
    }

    public static function definitions(): array
    {
        return [
            [
                'name' => 'search_products',
                'description' => "Recherche des produits du catalogue par nom, référence ou mot-clé d'usage (ex. \"peinture extérieur\", \"vis à bois\"). Retourne jusqu'à 8 produits avec leur stock et leur prix.",
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => ['type' => 'string', 'description' => 'Terme de recherche'],
                    ],
                    'required' => ['query'],
                ],
                'handler' => fn (array $input) => self::searchProducts((string) ($input['query'] ?? '')),
            ],
            [
                'name' => 'check_stock',
                'description' => "Donne le stock disponible actuel d'un produit précis identifié par sa référence exacte.",
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'reference' => ['type' => 'string', 'description' => 'Référence exacte du produit'],
                    ],
                    'required' => ['reference'],
                ],
                'handler' => fn (array $input) => self::checkStock((string) ($input['reference'] ?? '')),
            ],
            [
                'name' => 'get_low_stock_products',
                'description' => 'Liste les produits actuellement en stock bas (sous leur seuil d\'alerte), utile pour répondre aux questions de réapprovisionnement.',
                'inputSchema' => ['type' => 'object'],
                'handler' => fn (array $input) => self::lowStockProducts(),
            ],
            [
                'name' => 'get_product_price',
                'description' => "Donne le prix de vente (normal ou professionnel) d'un produit précis identifié par sa référence exacte.",
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'reference' => ['type' => 'string', 'description' => 'Référence exacte du produit'],
                        'customer_id' => ['type' => 'integer', 'description' => 'Identifiant du client, pour appliquer le tarif pro si applicable (optionnel)'],
                    ],
                    'required' => ['reference'],
                ],
                'handler' => fn (array $input) => self::productPrice((string) ($input['reference'] ?? ''), isset($input['customer_id']) ? (int) $input['customer_id'] : null),
            ],
        ];
    }

    private static function searchProducts(string $query): string
    {
        if (trim($query) === '') {
            return 'Aucun terme de recherche fourni.';
        }

        // Même logique que Product::scopeSearch() (pg_trgm, rattrape les fautes de frappe) —
        // dupliquée plutôt qu'appelée ici, avec description en plus : imbriquer un scope qui
        // pose son propre orderByRaw() dans un where(Closure) ne s'appliquerait pas à la requête
        // externe (le grouping de where(Closure) ne porte que sur les conditions, pas l'ordre).
        $products = Product::where('active', true)
            ->where(function ($q) use ($query) {
                $q->where('name', 'ilike', "%{$query}%")
                    ->orWhere('reference', 'ilike', "%{$query}%")
                    ->orWhere('description', 'ilike', "%{$query}%")
                    ->orWhereRaw('similarity(name, ?) > 0.15', [$query])
                    ->orWhereRaw('similarity(reference, ?) > 0.15', [$query]);
            })
            ->orderByRaw('GREATEST(similarity(name, ?), similarity(reference, ?)) DESC', [$query, $query])
            ->limit(8)
            ->get();

        if ($products->isEmpty()) {
            return "Aucun produit trouvé pour « {$query} ».";
        }

        return $products->map(fn (Product $p) => sprintf(
            '- %s (réf. %s) : stock %s %s, prix %s FCFA',
            $p->name,
            $p->reference,
            rtrim(rtrim(number_format($p->currentStock(), 2, ',', ' '), '0'), ','),
            $p->unit,
            number_format((float) $p->sale_price, 0, ',', ' '),
        ))->implode("\n");
    }

    private static function checkStock(string $reference): string
    {
        $product = Product::where('reference', $reference)->where('active', true)->first();

        if (! $product) {
            return "Aucun produit actif avec la référence « {$reference} ».";
        }

        return sprintf(
            '%s : stock disponible %s %s (seuil d\'alerte %s).',
            $product->name,
            rtrim(rtrim(number_format($product->availableStock(), 2, ',', ' '), '0'), ','),
            $product->unit,
            rtrim(rtrim(number_format((float) $product->low_stock_threshold, 2, ',', ' '), '0'), ','),
        );
    }

    private static function lowStockProducts(): string
    {
        $lowStock = Product::where('active', true)->get()->filter(fn (Product $p) => $p->isLowStock());

        if ($lowStock->isEmpty()) {
            return 'Aucun produit en stock bas actuellement.';
        }

        return $lowStock->take(15)->map(fn (Product $p) => sprintf(
            '- %s (réf. %s) : stock %s / seuil %s',
            $p->name,
            $p->reference,
            rtrim(rtrim(number_format($p->currentStock(), 2, ',', ' '), '0'), ','),
            rtrim(rtrim(number_format((float) $p->low_stock_threshold, 2, ',', ' '), '0'), ','),
        ))->implode("\n");
    }

    private static function productPrice(string $reference, ?int $customerId): string
    {
        $product = Product::where('reference', $reference)->where('active', true)->first();

        if (! $product) {
            return "Aucun produit actif avec la référence « {$reference} ».";
        }

        $customer = $customerId ? Customer::find($customerId) : null;

        return sprintf('%s : %s FCFA / %s.', $product->name, number_format($product->priceFor($customer), 0, ',', ' '), $product->unit);
    }
}
