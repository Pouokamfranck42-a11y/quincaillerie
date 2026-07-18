<?php

namespace App\Services\Ai;

use App\Models\InventoryCountLine;
use App\Models\Product;
use App\Models\SaleLine;
use App\Models\User;
use App\Notifications\AnomalyDetected;
use Illuminate\Support\Facades\Notification;

/**
 * Détection d'anomalies purement statistique (comparaisons PHP) — aucun appel IA,
 * exécutée en synchrone au moment de la vente, du changement de prix ou de la clôture
 * d'un inventaire.
 */
class AnomalyDetector
{
    private const PRICE_JUMP_THRESHOLD = 0.5; // 50 %

    private const INVENTORY_DISCREPANCY_THRESHOLD = 0.5; // 50 % d'écart relatif

    private const INVENTORY_DISCREPANCY_MIN_EXPECTED = 5; // évite le bruit sur du stock quasi nul

    public static function checkSaleLine(SaleLine $line, Product $product): void
    {
        if ((float) $line->unit_price >= (float) $product->purchase_price) {
            return;
        }

        self::notify(
            'sale_below_cost',
            sprintf(
                'Vente à perte : %s vendu à %s FCFA pour un coût de %s FCFA (vente #%d).',
                $product->name,
                number_format((float) $line->unit_price, 0, ',', ' '),
                number_format((float) $product->purchase_price, 0, ',', ' '),
                $line->sale_id,
            ),
            ['product_id' => $product->id, 'sale_id' => $line->sale_id, 'sale_line_id' => $line->id],
        );
    }

    public static function checkPriceChange(Product $product, float $oldPrice, float $newPrice): void
    {
        if ($oldPrice <= 0 || $oldPrice == $newPrice) {
            return;
        }

        $change = abs($newPrice - $oldPrice) / $oldPrice;

        if ($change <= self::PRICE_JUMP_THRESHOLD) {
            return;
        }

        self::notify(
            'price_jump',
            sprintf(
                'Changement de prix inhabituel : %s passé de %s à %s FCFA (%d%%).',
                $product->name,
                number_format($oldPrice, 0, ',', ' '),
                number_format($newPrice, 0, ',', ' '),
                round($change * 100),
            ),
            ['product_id' => $product->id],
        );
    }

    /**
     * Écart de comptage suspicieusement important (> 50 % du stock attendu, sur un stock
     * attendu non négligeable) — peut signaler une erreur de saisie ou un vol, à vérifier.
     */
    public static function checkInventoryDiscrepancy(InventoryCountLine $line, Product $product): void
    {
        $expected = (float) $line->expected_quantity;
        $counted = (float) $line->counted_quantity;
        $delta = abs($counted - $expected);

        if ($expected < self::INVENTORY_DISCREPANCY_MIN_EXPECTED || $delta == 0.0) {
            return;
        }

        $relativeChange = $delta / $expected;

        if ($relativeChange <= self::INVENTORY_DISCREPANCY_THRESHOLD) {
            return;
        }

        self::notify(
            'inventory_discrepancy',
            sprintf(
                "Écart d'inventaire important : %s — attendu %s, compté %s (%d%%).",
                $product->name,
                rtrim(rtrim(number_format($expected, 2, ',', ' '), '0'), ','),
                rtrim(rtrim(number_format($counted, 2, ',', ' '), '0'), ','),
                round($relativeChange * 100),
            ),
            ['product_id' => $product->id, 'inventory_count_id' => $line->inventory_count_id],
        );
    }

    /** @param array<string, mixed> $data */
    private static function notify(string $type, string $message, array $data): void
    {
        $admins = User::whereHas('roles', fn ($q) => $q->where('name', 'admin'))->get();

        if ($admins->isEmpty()) {
            return;
        }

        Notification::send($admins, new AnomalyDetected($type, $message, $data));
    }
}
