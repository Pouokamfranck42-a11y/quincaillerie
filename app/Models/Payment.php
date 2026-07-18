<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    public const METHOD_ESPECES = 'especes';

    public const METHOD_CARTE = 'carte';

    public const METHOD_MOBILE_MONEY_MTN = 'mobile_money_mtn';

    public const METHOD_MOBILE_MONEY_ORANGE = 'mobile_money_orange';

    public const METHOD_CREDIT = 'credit';

    public const STATUS_PENDING = 'pending';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REFUNDED = 'refunded';

    protected $fillable = [
        'payable_type', 'payable_id', 'amount', 'method', 'status',
        'provider', 'provider_reference', 'raw_payload', 'user_id', 'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'raw_payload' => 'array',
        'paid_at' => 'datetime',
    ];

    public function payable()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
