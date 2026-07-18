<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    protected $fillable = ['name', 'address', 'is_default'];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }

    public static function default(): self
    {
        return self::where('is_default', true)->firstOrFail();
    }
}
