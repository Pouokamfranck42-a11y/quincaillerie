<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Ledger de points de fidélité — jamais un solde brut modifié directement, même principe
 * que stock_movements : Customer::loyaltyPoints() somme toujours ces mouvements.
 */
class LoyaltyPointMovement extends Model
{
    protected $fillable = ['customer_id', 'points', 'reason', 'reference_type', 'reference_id', 'user_id'];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function reference()
    {
        return $this->morphTo();
    }

    /**
     * Points gagnés à l'achat — jamais sur une vente à crédit non encore soldée (on ne
     * récompense pas de l'argent qui n'a pas encore été payé), ni sans client identifié.
     * Appelé depuis Sale::checkout() (comptoir) et Order::confirmPayment() (commande web) —
     * un seul endroit calcule la règle, pour qu'elle ne diverge jamais entre les deux canaux.
     */
    public static function earnForSale(Sale $sale, ?Customer $customer, ?int $userId = null): void
    {
        if (! $customer || ! config('company.loyalty.enabled') || $sale->payment_status === 'due') {
            return;
        }

        $earnPerFcfa = (int) config('company.loyalty.earn_per_fcfa');
        if ($earnPerFcfa <= 0) {
            return;
        }

        $points = intdiv((int) $sale->total, $earnPerFcfa);
        if ($points <= 0) {
            return;
        }

        self::create([
            'customer_id' => $customer->id,
            'points' => $points,
            'reason' => 'Achat — vente #'.$sale->id,
            'reference_type' => Sale::class,
            'reference_id' => $sale->id,
            'user_id' => $userId,
        ]);
    }
}
