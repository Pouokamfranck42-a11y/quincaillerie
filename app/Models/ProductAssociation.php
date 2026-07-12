<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductAssociation extends Model
{
    public $timestamps = false;

    protected $fillable = ['product_id', 'associated_product_id', 'co_occurrence_count', 'updated_at'];

    protected $casts = [
        'updated_at' => 'datetime',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function associatedProduct()
    {
        return $this->belongsTo(Product::class, 'associated_product_id');
    }
}
