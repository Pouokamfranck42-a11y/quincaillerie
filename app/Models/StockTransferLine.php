<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockTransferLine extends Model
{
    protected $fillable = ['stock_transfer_id', 'product_id', 'quantity'];

    protected $casts = [
        'quantity' => 'decimal:2',
    ];

    public function stockTransfer()
    {
        return $this->belongsTo(StockTransfer::class);
    }

    /** withTrashed() : une ligne de transfert historique doit rester lisible même si le produit a été archivé depuis. */
    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
