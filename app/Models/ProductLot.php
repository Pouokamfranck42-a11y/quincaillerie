<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductLot extends Model
{
    protected $fillable = ['product_id', 'lot_number', 'expiry_date'];

    protected $casts = [
        'expiry_date' => 'date',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class, 'lot_id');
    }

    /** Quantité courante du lot = somme de ses mouvements (même principe que le stock produit). */
    public function currentQuantity(): float
    {
        return (float) $this->stockMovements()->sum('quantity');
    }

    public function isExpired(): bool
    {
        return $this->expiry_date !== null && $this->expiry_date->isPast();
    }

    public function expiresWithin(int $days): bool
    {
        return $this->expiry_date !== null
            && ! $this->isExpired()
            && $this->expiry_date->lte(now()->addDays($days));
    }
}
