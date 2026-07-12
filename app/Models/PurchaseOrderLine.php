<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PurchaseOrderLine extends Model
{
    protected $fillable = ['purchase_order_id', 'product_id', 'quantity', 'unit_price', 'received_quantity', 'returned_quantity'];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'received_quantity' => 'decimal:2',
        'returned_quantity' => 'decimal:2',
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /** Reliquat : quantité encore à recevoir (en unité d'achat). */
    public function remaining(): float
    {
        return max((float) $this->quantity - (float) $this->received_quantity, 0);
    }

    public function isFullyReceived(): bool
    {
        return $this->remaining() <= 0.0;
    }

    /** Ce qui a été reçu et n'a pas encore été retourné au fournisseur. */
    public function returnableQuantity(): float
    {
        return max((float) $this->received_quantity - (float) $this->returned_quantity, 0);
    }

    /** Retour fournisseur : sort le stock (en unité de stock) et trace le retour. */
    public function returnQuantity(float $quantity, int $byUserId, ?string $reason = null): void
    {
        $quantity = min($quantity, $this->returnableQuantity());

        if ($quantity <= 0) {
            return;
        }

        DB::transaction(function () use ($quantity, $byUserId, $reason) {
            $stockQuantity = $this->product->toStockQuantity($quantity);

            StockMovement::create([
                'product_id' => $this->product_id,
                'type' => StockMovement::TYPE_SORTIE,
                'subtype' => StockMovement::SUBTYPE_RETOUR_FOURNISSEUR,
                'quantity' => -$stockQuantity,
                'reason' => $reason ?? 'Retour fournisseur — commande #'.$this->purchase_order_id,
                'reference_type' => PurchaseOrder::class,
                'reference_id' => $this->purchase_order_id,
                'user_id' => $byUserId,
            ]);

            $this->update(['returned_quantity' => (float) $this->returned_quantity + $quantity]);
        });
    }
}
