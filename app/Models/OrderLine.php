<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderLine extends Model
{
    protected $fillable = ['order_id', 'product_id', 'lot_id', 'quantity', 'unit_price', 'returned_quantity'];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'returned_quantity' => 'decimal:2',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /** withTrashed() : une ligne de commande web historique doit rester lisible même si le produit a été archivé depuis. */
    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function lot()
    {
        return $this->belongsTo(ProductLot::class, 'lot_id');
    }
}
