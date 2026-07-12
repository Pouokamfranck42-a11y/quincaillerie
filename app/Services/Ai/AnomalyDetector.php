<?php

namespace App\Services\Ai;

use App\Models\Product;
use App\Models\SaleLine;
use App\Models\User;
use App\Notifications\AnomalyDetected;
use Illuminate\Support\Facades\Notification;

/**
 * Détection d'anomalies purement statistique (comparaisons PHP) — aucun appel Claude,
 * exécutée en synchrone au moment de la vente ou du changement de prix.
 */
class AnomalyDetector
{
    private const PRICE_JUMP_THRESHOLD = 0.5; // 50 %

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
