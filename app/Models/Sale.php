<?php

namespace App\Models;

use App\Services\Ai\AnomalyDetector;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Sale extends Model
{
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'cash_register_session_id', 'user_id', 'customer_id',
        'subtotal', 'tax_rate', 'tax_amount', 'total', 'payment_method', 'status',
        'payment_status', 'due_date', 'paid_amount', 'amount_tendered', 'change_due', 'cancelled_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'amount_tendered' => 'decimal:2',
        'change_due' => 'decimal:2',
        'due_date' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function session()
    {
        return $this->belongsTo(CashRegisterSession::class, 'cash_register_session_id');
    }

    /** withTrashed() : une vente historique reste attribuable même si l'utilisateur a quitté depuis. */
    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /** withTrashed() : une vente historique doit rester lisible même si le compte client a été désactivé/supprimé depuis. */
    public function customer()
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function lines()
    {
        return $this->hasMany(SaleLine::class);
    }

    /** Renseigné uniquement si cette vente provient de la confirmation d'une commande e-commerce. */
    public function order()
    {
        return $this->hasOne(Order::class);
    }

    public function payments()
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    public function invoices()
    {
        return $this->morphMany(Invoice::class, 'invoiceable');
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
        ?float $amountTendered = null,
    ): self {
        return DB::transaction(function () use ($cartItems, $session, $userId, $customerId, $paymentMethod, $taxRate, $amountTendered) {
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

            $changeDue = null;
            if ($paymentMethod === 'especes' && $amountTendered !== null) {
                if ($amountTendered < $total) {
                    throw ValidationException::withMessages(['amount_tendered' => 'Le montant reçu ('.number_format($amountTendered, 0, ',', ' ').' FCFA) est inférieur au total à payer ('.number_format($total, 0, ',', ' ').' FCFA).']);
                }
                $changeDue = round($amountTendered - $total, 2);
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
                'status' => self::STATUS_COMPLETED,
                'payment_status' => $isCredit ? 'due' : 'paid',
                'due_date' => $isCredit ? now()->addDays($customer->payment_terms_days) : null,
                'paid_amount' => $isCredit ? 0 : $total,
                'amount_tendered' => $amountTendered,
                'change_due' => $changeDue,
            ]);

            $stockService = app(\App\Services\Stock\StockService::class);

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

                // Comptoir : réservation et déduction physique dans le même geste (paiement immédiat).
                $stockService->reserveAndDeduct(
                    product: $product,
                    quantity: (float) $item['quantity'],
                    channel: Reservation::CHANNEL_COMPTOIR,
                    reservable: $sale,
                    userId: $userId,
                    reason: 'Vente #'.$sale->id,
                    reference: $line,
                    lotId: $lotId,
                );
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

    /**
     * Annule la vente entière : réintègre en une fois le stock encore non retourné de chaque
     * ligne via le noyau de stock unifié (même service que le retour ligne par ligne), puis
     * verrouille la vente. Réservée aux ventes de la session de caisse encore ouverte — une
     * fois la session clôturée, seul le retour ligne par ligne reste disponible (cohérent avec
     * la caisse déjà comptée).
     */
    public function cancel(int $userId, ?string $reason = null): self
    {
        if ($this->status === self::STATUS_CANCELLED) {
            return $this;
        }

        if ($this->session->status !== CashRegisterSession::STATUS_OPEN) {
            throw ValidationException::withMessages([
                'sale' => "Cette vente appartient à une session de caisse déjà clôturée — utilisez le retour ligne par ligne plutôt que l'annulation complète.",
            ]);
        }

        DB::transaction(function () use ($userId, $reason) {
            $stockService = app(\App\Services\Stock\StockService::class);

            foreach ($this->lines as $line) {
                $returnable = $line->returnableQuantity();

                if ($returnable > 0) {
                    $stockService->reintegrate(
                        product: $line->product,
                        quantity: $returnable,
                        reference: $line,
                        userId: $userId,
                        reason: $reason ?? 'Annulation vente #'.$this->id,
                        subtype: StockMovement::SUBTYPE_ANNULATION_VENTE,
                        lotId: $line->lot_id,
                    );
                    $line->update(['returned_quantity' => $line->quantity]);
                }
            }

            $oldValues = ['status' => $this->status];
            $this->update(['status' => self::STATUS_CANCELLED, 'cancelled_at' => now()]);

            AuditLog::record('sale.cancelled', $this, $oldValues, ['status' => self::STATUS_CANCELLED, 'reason' => $reason], $userId);
        });

        return $this->fresh();
    }
}
