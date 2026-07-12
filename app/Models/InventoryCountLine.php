<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryCountLine extends Model
{
    protected $fillable = ['inventory_count_id', 'product_id', 'expected_quantity', 'counted_quantity'];

    protected $casts = [
        'expected_quantity' => 'decimal:2',
        'counted_quantity' => 'decimal:2',
    ];

    public function inventoryCount()
    {
        return $this->belongsTo(InventoryCount::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function discrepancy(): float
    {
        return $this->counted_quantity === null ? 0.0 : round((float) $this->counted_quantity - (float) $this->expected_quantity, 2);
    }

    public function hasDiscrepancy(): bool
    {
        return $this->counted_quantity !== null && $this->discrepancy() != 0.0;
    }
}
