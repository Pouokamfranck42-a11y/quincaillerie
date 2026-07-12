<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashRegisterSession extends Model
{
    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = ['user_id', 'opened_at', 'closed_at', 'opening_amount', 'closing_amount', 'status'];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'opening_amount' => 'decimal:2',
        'closing_amount' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    public function salesTotal(): float
    {
        return (float) $this->sales()->where('status', 'completed')->sum('total');
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public static function openFor(int $userId): ?self
    {
        return self::where('user_id', $userId)
            ->where('status', self::STATUS_OPEN)
            ->latest('opened_at')
            ->first();
    }
}
