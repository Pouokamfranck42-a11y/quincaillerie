<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuoteLine extends Model
{
    protected $fillable = ['quote_id', 'product_id', 'quantity', 'unit_price'];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
    ];

    public function quote()
    {
        return $this->belongsTo(Quote::class);
    }

    /** withTrashed() : une ligne de devis historique doit rester lisible même si le produit a été archivé depuis. */
    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
