<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    public const CHANNEL_WEB = 'web';

    public const CHANNEL_COMPTOIR = 'comptoir';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_CONSUMED = 'consumed';

    public const STATUS_RELEASED = 'released';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'product_id', 'warehouse_id', 'quantity', 'channel', 'status',
        'reservable_type', 'reservable_id', 'user_id', 'expires_at', 'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'expires_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $reservation) {
            if (! $reservation->warehouse_id) {
                $reservation->warehouse_id = Warehouse::where('is_default', true)->value('id');
            }
            if (! $reservation->status) {
                $reservation->status = self::STATUS_ACTIVE;
            }
        });
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reservable()
    {
        return $this->morphTo();
    }
}
