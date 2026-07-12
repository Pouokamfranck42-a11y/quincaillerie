<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'phone', 'email', 'address', 'type', 'credit_limit', 'payment_terms_days',
        'ai_segment', 'ai_segment_rationale', 'ai_segment_updated_at',
    ];

    protected $casts = [
        'credit_limit' => 'decimal:2',
        'ai_segment_updated_at' => 'datetime',
    ];

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    public function quotes()
    {
        return $this->hasMany(Quote::class);
    }

    /** Somme des ventes à crédit non soldées. */
    public function outstandingBalance(): float
    {
        return (float) $this->sales()
            ->where('payment_status', 'due')
            ->get()
            ->sum(fn (Sale $sale) => (float) $sale->total - (float) $sale->paid_amount);
    }

    public function availableCredit(): float
    {
        return max(0, (float) $this->credit_limit - $this->outstandingBalance());
    }

    /** Métriques RFM simplifiées, utilisées pour la segmentation IA. */
    public function rfmSummary(): array
    {
        $sales = $this->sales()->where('status', 'completed')->get();

        return [
            'sales_count' => $sales->count(),
            'total_spent' => (float) $sales->sum('total'),
            'last_sale_days_ago' => $sales->max('created_at')?->diffInDays(now()),
            'outstanding_balance' => $this->outstandingBalance(),
        ];
    }
}
