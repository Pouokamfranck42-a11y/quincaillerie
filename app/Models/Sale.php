<?php

namespace App\Models;

use App\Services\Ai\AnomalyDetector;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Sale extends Model
{
    protected $fillable = [
        'cash_register_session_id', 'user_id', 'customer_id',
        'subtotal', 'tax_rate', 'tax_amount', 'total', 'payment_method', 'status',
        'payment_status', 'due_date', 'paid_amount',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'due_date' => 'datetime',
    ];

    public function session()
    {
        return $this->belongsTo(CashRegisterSession::class, 'cash_register_session_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function lines()
    {
        return $this->hasMany(SaleLine::class);
    }

    /**
     * Encaisse un panier : crée la vente, ses lignes, et les mouvements de stock de sortie
     * associés — le tout dans une seule transaction pour ne jamais désynchroniser stock et caisse.
     * Le prix appliqué tient compte du tarif professionnel du client s'il y a lieu.
     *
     * @param  array<int, array{product: Product, quantity: float}>  $cartItems
     */
    public static function checkout(
        array $cartItems,
        CashRegisterSession $session,
        int $userId,
        ?int $customerId,
        string $paymentMethod,
        float $taxRate,
    ): self {
        return DB::transaction(function () use ($cartItems, $session, $userId, $customerId, $paymentMethod, $taxRate) {
            $customer = $customerId ? Customer::find($customerId) : null;

            $subtotal = array_reduce(
                $cartItems,
                fn (float $carry, array $item) => $carry + ($item['product']->priceFor($customer) * $item['quantity']),
                0.0
            );
            $taxAmount = round($subtotal * ($taxRate / 100), 2);
            $total = $subtotal + $taxAmount;

            $isCredit = $paymentMethod === 'credit';

            if ($isCredit) {
                if (! $customer) {
                    throw ValidationException::withMessages(['credit' => 'Sélectionnez un client pour une vente à crédit.']);
                }
                if ($total > $customer->availableCredit()) {
                    throw ValidationException::withMessages(['credit' => 'Plafond de crédit dépassé pour ce client (encours disponible : '.number_format($customer->availableCredit(), 0, ',', ' ').' FCFA).']);
                }
            }

            $sale = self::create([
                'cash_register_session_id' => $session->id,
                'user_id' => $userId,
                'customer_id' => $customerId,
                'subtotal' => $subtotal,
                'tax_rate' => $taxRate,
                'tax_amount' => $taxAmount,
                'total' => $total,
                'payment_method' => $paymentMethod,
                'status' => 'completed',
                'payment_status' => $isCredit ? 'due' : 'paid',
                'due_date' => $isCredit ? now()->addDays($customer->payment_terms_days) : null,
                'paid_amount' => $isCredit ? 0 : $total,
            ]);

            foreach ($cartItems as $item) {
                $product = $item['product'];
                $lotId = $item['lot_id'] ?? ($product->tracks_lots ? $product->nextFefoLot()?->id : null);

                $line = $sale->lines()->create([
                    'product_id' => $product->id,
                    'lot_id' => $lotId,
                    'quantity' => $item['quantity'],
                    'unit_price' => $product->priceFor($customer),
                ]);

                AnomalyDetector::checkSaleLine($line, $product);

                StockMovement::create([
                    'product_id' => $product->id,
                    'lot_id' => $lotId,
                    'type' => StockMovement::TYPE_SORTIE,
                    'quantity' => -$item['quantity'],
                    'reason' => 'Vente #'.$sale->id,
                    'reference_type' => $line::class,
                    'reference_id' => $line->id,
                    'user_id' => $userId,
                ]);
            }

            return $sale;
        });
    }

    /** Enregistre un paiement (partiel ou total) sur une vente à crédit. */
    public function recordPayment(float $amount): void
    {
        $newPaidAmount = min((float) $this->total, (float) $this->paid_amount + $amount);

        $this->update([
            'paid_amount' => $newPaidAmount,
            'payment_status' => $newPaidAmount >= (float) $this->total ? 'paid' : 'due',
        ]);
    }
}
