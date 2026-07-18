<?php

namespace App\Services\Stock;

use App\Models\Product;
use App\Models\Reservation;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Service central unique du stock — comptoir et web passent tous les deux par ici.
 * Chaque opération verrouille la ligne du produit (SELECT ... FOR UPDATE) avant de lire
 * ou d'écrire quoi que ce soit, pour que deux ventes concurrentes sur la même unité ne
 * puissent jamais toutes les deux réussir : la seconde transaction attend que la
 * première ait validé (ou annulé) avant de lire un stock disponible à jour.
 *
 * Trois niveaux : physique (somme de stock_movements, jamais modifié directement),
 * réservé (réservations actives, table `reservations`), disponible = physique - réservé
 * (Product::availableStock()). La déduction physique n'a jamais lieu à la réservation,
 * seulement à deduct() — c'est-à-dire au paiement confirmé (ou à la remise, comptoir).
 */
class StockService
{
    /**
     * Réserve une quantité si elle est disponible, sinon lève une ValidationException
     * (même convention que le reste de l'app, ex. le plafond de crédit dans Sale::checkout()).
     */
    public function reserve(
        Product $product,
        float $quantity,
        string $channel,
        ?Model $reservable = null,
        ?int $userId = null,
        ?int $warehouseId = null,
        $expiresAt = null,
    ): Reservation {
        return DB::transaction(function () use ($product, $quantity, $channel, $reservable, $userId, $warehouseId, $expiresAt) {
            $locked = Product::where('id', $product->id)->lockForUpdate()->firstOrFail();

            $available = $locked->availableStock();
            if ($available < $quantity) {
                throw ValidationException::withMessages([
                    'stock' => "Stock insuffisant pour {$locked->name} (disponible : ".rtrim(rtrim(number_format($available, 2, '.', ''), '0'), '.').').',
                ]);
            }

            return Reservation::create([
                'product_id' => $locked->id,
                'warehouse_id' => $warehouseId,
                'quantity' => $quantity,
                'channel' => $channel,
                'status' => Reservation::STATUS_ACTIVE,
                'reservable_type' => $reservable ? $reservable::class : null,
                'reservable_id' => $reservable?->id,
                'user_id' => $userId,
                'expires_at' => $expiresAt,
            ]);
        });
    }

    /** Libère une réservation active sans toucher au stock physique (rien n'avait été déduit). Idempotent. */
    public function release(Reservation $reservation, string $status = Reservation::STATUS_RELEASED): Reservation
    {
        return DB::transaction(function () use ($reservation, $status) {
            Product::where('id', $reservation->product_id)->lockForUpdate()->first();

            if ($reservation->status !== Reservation::STATUS_ACTIVE) {
                return $reservation;
            }

            $reservation->update(['status' => $status]);

            return $reservation->fresh();
        });
    }

    /**
     * Transforme une réservation active en déduction physique réelle (écrit dans le
     * journal des mouvements). C'est le SEUL endroit qui décrémente le stock physique
     * pour une vente/commande.
     */
    public function deduct(Reservation $reservation, ?int $userId = null, ?string $reason = null, ?Model $reference = null, ?int $lotId = null): StockMovement
    {
        return DB::transaction(function () use ($reservation, $userId, $reason, $reference, $lotId) {
            $product = Product::where('id', $reservation->product_id)->lockForUpdate()->firstOrFail();

            if ($reservation->status !== Reservation::STATUS_ACTIVE) {
                throw new \RuntimeException("Réservation #{$reservation->id} n'est plus active — déduction impossible.");
            }

            $resolvedLotId = $lotId ?? ($product->tracks_lots ? $product->nextFefoLot()?->id : null);

            $movement = StockMovement::create([
                'product_id' => $product->id,
                'warehouse_id' => $reservation->warehouse_id,
                'lot_id' => $resolvedLotId,
                'type' => StockMovement::TYPE_SORTIE,
                'quantity' => -$reservation->quantity,
                'reason' => $reason ?? 'Déduction réservation #'.$reservation->id,
                'reference_type' => $reference ? $reference::class : Reservation::class,
                'reference_id' => $reference?->id ?? $reservation->id,
                'user_id' => $userId,
            ]);

            $reservation->update(['status' => Reservation::STATUS_CONSUMED]);

            return $movement;
        });
    }

    /**
     * Réserve puis déduit immédiatement dans la même transaction — le cas du comptoir,
     * qui paie et remet la marchandise dans le même geste (pas de délai à couvrir par
     * une réservation qui attend).
     */
    public function reserveAndDeduct(
        Product $product,
        float $quantity,
        string $channel,
        ?Model $reservable = null,
        ?int $userId = null,
        ?string $reason = null,
        ?Model $reference = null,
        ?int $lotId = null,
        ?int $warehouseId = null,
    ): StockMovement {
        return DB::transaction(function () use ($product, $quantity, $channel, $reservable, $userId, $reason, $reference, $lotId, $warehouseId) {
            $reservation = $this->reserve($product, $quantity, $channel, $reservable, $userId, $warehouseId);

            return $this->deduct($reservation, $userId, $reason, $reference, $lotId);
        });
    }

    /** Réintègre du stock physique (retour client, retour fournisseur, etc.). */
    public function reintegrate(
        Product $product,
        float $quantity,
        ?Model $reference = null,
        ?int $userId = null,
        ?string $reason = null,
        ?string $subtype = null,
        ?int $lotId = null,
        ?int $warehouseId = null,
    ): StockMovement {
        return DB::transaction(function () use ($product, $quantity, $reference, $userId, $reason, $subtype, $lotId, $warehouseId) {
            Product::where('id', $product->id)->lockForUpdate()->firstOrFail();

            return StockMovement::create([
                'product_id' => $product->id,
                'warehouse_id' => $warehouseId,
                'lot_id' => $lotId,
                'type' => StockMovement::TYPE_ENTREE,
                'subtype' => $subtype,
                'quantity' => $quantity,
                'reason' => $reason ?? 'Réintégration',
                'reference_type' => $reference ? $reference::class : null,
                'reference_id' => $reference?->id,
                'user_id' => $userId,
            ]);
        });
    }
}
