<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PurchaseOrder extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_ORDERED = 'ordered';
    public const STATUS_PARTIALLY_RECEIVED = 'partiellement_recu';
    public const STATUS_RECEIVED = 'received';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = ['supplier_id', 'warehouse_id', 'user_id', 'status', 'ordered_at', 'received_at', 'notes', 'extra_costs'];

    protected $casts = [
        'ordered_at' => 'datetime',
        'received_at' => 'datetime',
        'extra_costs' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $order) {
            if (! $order->warehouse_id) {
                $order->warehouse_id = Warehouse::where('is_default', true)->value('id');
            }
        });
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function lines()
    {
        return $this->hasMany(PurchaseOrderLine::class);
    }

    public function total(): float
    {
        return (float) $this->lines->sum(fn (PurchaseOrderLine $line) => $line->quantity * $line->unit_price);
    }

    public function isFullyReceived(): bool
    {
        return $this->lines->every(fn (PurchaseOrderLine $line) => $line->isFullyReceived());
    }

    /**
     * Prix unitaire « atterri » (coût de revient) d'une ligne : prix d'achat + quote-part des
     * frais annexes de la commande (transport, douane, manutention), répartie au prorata de la
     * valeur de la ligne dans le total de la commande. Exprimé en unité d'achat.
     */
    public function landedUnitPrice(PurchaseOrderLine $line): float
    {
        $orderTotal = $this->total();

        if ((float) $this->extra_costs <= 0 || $orderTotal <= 0 || (float) $line->quantity <= 0) {
            return (float) $line->unit_price;
        }

        $lineShare = ((float) $line->quantity * (float) $line->unit_price) / $orderTotal;
        $extraPerUnit = ($lineShare * (float) $this->extra_costs) / (float) $line->quantity;

        return (float) $line->unit_price + $extraPerUnit;
    }

    /**
     * Réceptionne la commande, en totalité ou partiellement : génère les mouvements de stock
     * d'entrée pour les quantités reçues, recalcule le CUMP de chaque produit, et met à jour
     * le reliquat. $receivedQuantities est [ligne_id => quantité reçue maintenant, en unité
     * d'achat] ; si omis, chaque ligne est réceptionnée pour la totalité de son reliquat.
     *
     * @param  array<int, float>  $receivedQuantities
     */
    public function receive(int $byUserId, array $receivedQuantities = []): void
    {
        if ($this->status === self::STATUS_RECEIVED) {
            return;
        }

        DB::transaction(function () use ($byUserId, $receivedQuantities) {
            foreach ($this->lines as $line) {
                $requested = array_key_exists($line->id, $receivedQuantities)
                    ? (float) $receivedQuantities[$line->id]
                    : $line->remaining();

                $qtyToReceive = min(max($requested, 0), $line->remaining());

                if ($qtyToReceive <= 0) {
                    continue;
                }

                $product = $line->product;
                $factor = (float) $product->purchase_unit_factor ?: 1;
                $stockQuantity = $product->toStockQuantity($qtyToReceive);
                $stockUnitCost = round($this->landedUnitPrice($line) / $factor, 4);

                // Le CUMP doit être recalculé AVANT que le mouvement ne modifie le stock courant.
                $product->applyCump($stockQuantity, $stockUnitCost);

                StockMovement::create([
                    'product_id' => $line->product_id,
                    'warehouse_id' => $this->warehouse_id,
                    'type' => StockMovement::TYPE_ENTREE,
                    'quantity' => $stockQuantity,
                    'unit_cost' => $stockUnitCost,
                    'reason' => 'Réception commande fournisseur #'.$this->id,
                    'reference_type' => self::class,
                    'reference_id' => $this->id,
                    'user_id' => $byUserId,
                ]);

                $line->update(['received_quantity' => (float) $line->received_quantity + $qtyToReceive]);
            }

            $this->refresh();
            $this->update([
                'status' => $this->isFullyReceived() ? self::STATUS_RECEIVED : self::STATUS_PARTIALLY_RECEIVED,
                'received_at' => $this->isFullyReceived() ? now() : $this->received_at,
            ]);
        });
    }
}
