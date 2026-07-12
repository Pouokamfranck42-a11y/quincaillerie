<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    public const TYPE_ENTREE = 'entree';
    public const TYPE_SORTIE = 'sortie';
    public const TYPE_AJUSTEMENT = 'ajustement';

    // Sous-types indicatifs (facultatifs), pour qualifier la raison d'un mouvement.
    public const SUBTYPE_CASSE = 'casse';
    public const SUBTYPE_VOL = 'vol';
    public const SUBTYPE_PERIME = 'perime';
    public const SUBTYPE_CONSOMMATION_INTERNE = 'consommation_interne';
    public const SUBTYPE_RETOUR_CLIENT = 'retour_client';
    public const SUBTYPE_RETOUR_FOURNISSEUR = 'retour_fournisseur';
    public const SUBTYPE_REINTEGRATION_SAV = 'reintegration_sav';
    public const SUBTYPE_TRANSFERT = 'transfert';
    public const SUBTYPE_INVENTAIRE = 'inventaire';

    protected $fillable = [
        'product_id', 'warehouse_id', 'lot_id', 'type', 'subtype', 'quantity', 'unit_cost',
        'reason', 'reference_type', 'reference_id', 'user_id',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_cost' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $movement) {
            if (! $movement->warehouse_id) {
                $movement->warehouse_id = Warehouse::where('is_default', true)->value('id');
            }
        });

        static::created(function (self $movement) {
            $movement->maybeNotifyLowStock();
        });
    }

    /**
     * Notifie les admins/magasiniers si ce mouvement fait passer le produit sous son seuil
     * d'alerte — uniquement au moment du franchissement, pas à chaque mouvement suivant.
     */
    private function maybeNotifyLowStock(): void
    {
        $product = $this->product;
        $stockAfter = $product->currentStock();
        $stockBefore = $stockAfter - (float) $this->quantity;
        $threshold = (float) $product->low_stock_threshold;

        if ($stockAfter <= $threshold && $stockBefore > $threshold) {
            // Requête directe plutôt que le scope role() de spatie : ne doit jamais lever
            // d'exception si un des rôles n'existe pas encore dans la base (installation fraîche, tests).
            $recipients = User::whereHas('roles', fn ($q) => $q->whereIn('name', ['admin', 'magasinier']))->get();
            \Illuminate\Support\Facades\Notification::send($recipients, new \App\Notifications\LowStockAlert($product));
        }
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function lot()
    {
        return $this->belongsTo(ProductLot::class, 'lot_id');
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function reference()
    {
        return $this->morphTo();
    }
}
