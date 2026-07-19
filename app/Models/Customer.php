<?php

namespace App\Models;

use App\Notifications\CustomerResetPassword;
use Illuminate\Auth\Authenticatable;
use Illuminate\Auth\Passwords\CanResetPassword as CanResetPasswordTrait;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;

/** Authenticatable seulement pour porter le guard web "customer" (Phase 5) — reste un Model, pas un User. */
class Customer extends Model implements AuthenticatableContract, CanResetPasswordContract
{
    use Authenticatable, CanResetPasswordTrait, Notifiable, SoftDeletes;

    protected $fillable = [
        'name', 'phone', 'email', 'address', 'type', 'niu', 'credit_limit', 'payment_terms_days',
        'ai_segment', 'ai_segment_rationale', 'ai_segment_updated_at',
        'password', 'email_verified_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'credit_limit' => 'decimal:2',
        'ai_segment_updated_at' => 'datetime',
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    public function quotes()
    {
        return $this->hasMany(Quote::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /** Un mot de passe renseigné signifie que le client a un compte web actif (Phase 5). */
    public function hasWebAccount(): bool
    {
        return filled($this->password);
    }

    /** Route le lien vers la page de réinitialisation de la boutique (guard "customer"), pas celle du staff. */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new CustomerResetPassword($token));
    }

    /** Somme des ventes à crédit non soldées. */
    public function outstandingBalance(): float
    {
        return (float) $this->sales()
            ->where('payment_status', 'due')
            ->where('status', '!=', 'cancelled')
            ->get()
            ->sum(fn (Sale $sale) => (float) $sale->total - (float) $sale->paid_amount);
    }

    public function availableCredit(): float
    {
        return max(0, (float) $this->credit_limit - $this->outstandingBalance());
    }

    public function loyaltyPointMovements()
    {
        return $this->hasMany(LoyaltyPointMovement::class);
    }

    /** Solde de points — jamais stocké, toujours recalculé depuis le ledger (même principe que Product::currentStock()). */
    public function loyaltyPoints(): int
    {
        return (int) $this->loyaltyPointMovements()->sum('points');
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
