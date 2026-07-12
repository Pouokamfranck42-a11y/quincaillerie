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

    public function product()
    {
        return $this->belongsTo(Product::class);
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
        $quantity = min($quantity, $this->returnableQuantity());

        if ($quantity <= 0) {
            return;
        }

        DB::transaction(function () use ($quantity, $byUserId, $reason) {
            StockMovement::create([
                'product_id' => $this->product_id,
                'lot_id' => $this->lot_id,
                'type' => StockMovement::TYPE_ENTREE,
                'subtype' => StockMovement::SUBTYPE_RETOUR_CLIENT,
                'quantity' => $quantity,
                'reason' => $reason ?? 'Retour client — vente #'.$this->sale_id,
                'reference_type' => Sale::class,
                'reference_id' => $this->sale_id,
                'user_id' => $byUserId,
            ]);

            $this->update(['returned_quantity' => (float) $this->returned_quantity + $quantity]);
        });
    }
}
