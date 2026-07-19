<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SaleLine extends Model
{
    protected $fillable = ['sale_id', 'product_id', 'lot_id', 'quantity', 'unit_price', 'returned_quantity'];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'returned_quantity' => 'decimal:2',
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    /** withTrashed() : une ligne de vente historique doit rester lisible même si le produit a été archivé depuis (suppression douce). */
    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function lot()
    {
        return $this->belongsTo(ProductLot::class, 'lot_id');
    }

    public function lineTotal(): float
    {
        return (float) $this->quantity * (float) $this->unit_price;
    }

    public function returnableQuantity(): float
    {
        return max((float) $this->quantity - (float) $this->returned_quantity, 0);
    }

    /** Retour client : réintègre le stock et trace le retour. Ne modifie pas le montant déjà encaissé. */
    public function returnQuantity(float $quantity, int $byUserId, ?string $reason = null): void
    {
        DB::transaction(function () use ($quantity, $byUserId, $reason) {
            // Verrouille la ligne AVANT de recalculer la quantité retournable : sans ça, deux
            // retours concurrents sur la même ligne (double-clic) liraient tous les deux le même
            // returned_quantity "avant" périmé, réintégreraient chacun leur part, et le second
            // update() écraserait le premier au lieu de s'additionner — double crédit silencieux.
            $line = self::where('id', $this->id)->lockForUpdate()->firstOrFail();
            $actualQuantity = min($quantity, $line->returnableQuantity());

            if ($actualQuantity <= 0) {
                return;
            }

            app(\App\Services\Stock\StockService::class)->reintegrate(
                product: $line->product,
                quantity: $actualQuantity,
                reference: $line->sale,
                userId: $byUserId,
                reason: $reason ?? 'Retour client — vente #'.$line->sale_id,
                subtype: StockMovement::SUBTYPE_RETOUR_CLIENT,
                lotId: $line->lot_id,
            );

            $line->update(['returned_quantity' => (float) $line->returned_quantity + $actualQuantity]);
        });

        $this->refresh();
    }
}
