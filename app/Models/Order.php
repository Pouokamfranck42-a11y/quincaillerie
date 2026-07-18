<?php

namespace App\Models;

use App\Notifications\OrderReady;
use App\Services\Stock\StockService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Machine à états : reservee -> payee -> preparation -> prete -> (livree | retiree) -> retournee,
 * avec annulee accessible depuis reservee/payee/preparation/prete. Toute transition hors de ce
 * graphe est refusée. Chaque méthode appelle StockService (Phase 3) — jamais d'écriture directe
 * dans stock_movements/reservations ici.
 */
class Order extends Model
{
    public const CHANNEL_WEB = 'web';

    public const CHANNEL_COMPTOIR = 'comptoir';

    public const STATUS_RESERVEE = 'reservee';

    public const STATUS_PAYEE = 'payee';

    public const STATUS_PREPARATION = 'preparation';

    public const STATUS_PRETE = 'prete';

    public const STATUS_LIVREE = 'livree';

    public const STATUS_RETIREE = 'retiree';

    public const STATUS_ANNULEE = 'annulee';

    public const STATUS_RETOURNEE = 'retournee';

    public const FULFILLMENT_LIVRAISON = 'livraison';

    public const FULFILLMENT_RETRAIT = 'retrait';

    /** Durée par défaut avant qu'une réservation web non payée devienne libérable (Phase 5 : tâche planifiée). */
    public const RESERVATION_MINUTES = 30;

    private const TRANSITIONS = [
        self::STATUS_RESERVEE => [self::STATUS_PAYEE, self::STATUS_ANNULEE],
        self::STATUS_PAYEE => [self::STATUS_PREPARATION, self::STATUS_ANNULEE],
        self::STATUS_PREPARATION => [self::STATUS_PRETE, self::STATUS_ANNULEE],
        self::STATUS_PRETE => [self::STATUS_LIVREE, self::STATUS_RETIREE, self::STATUS_ANNULEE],
        self::STATUS_LIVREE => [self::STATUS_RETOURNEE],
        self::STATUS_RETIREE => [self::STATUS_RETOURNEE],
        self::STATUS_ANNULEE => [],
        self::STATUS_RETOURNEE => [],
    ];

    protected $fillable = [
        'customer_id', 'sale_id', 'channel', 'status', 'fulfillment_type',
        'delivery_address', 'delivery_phone', 'delivery_notes',
        'subtotal', 'tax_rate', 'tax_amount', 'total',
        'payment_method', 'payment_status', 'paid_amount',
        'cancelled_at', 'delivered_at', 'notes',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'cancelled_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    /** withTrashed() : une commande historique doit rester lisible même si le compte client a été désactivé/supprimé depuis. */
    public function customer()
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function lines()
    {
        return $this->hasMany(OrderLine::class);
    }

    public function reservations()
    {
        return $this->morphMany(Reservation::class, 'reservable');
    }

    public function payments()
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    /**
     * Passe la commande : crée la commande, ses lignes, et réserve le stock de chaque
     * ligne (niveau "réservé", rien n'est déduit physiquement). C'est la transition
     * implicite Panier -> Commande passée (RÉSERVE) du cycle de vie.
     *
     * @param  array<int, array{product: Product, quantity: float}>  $cartItems
     */
    public static function place(
        array $cartItems,
        int $customerId,
        string $paymentMethod,
        string $fulfillmentType = self::FULFILLMENT_LIVRAISON,
        float $taxRate = 0,
        string $channel = self::CHANNEL_WEB,
        ?string $deliveryAddress = null,
        ?string $deliveryPhone = null,
        ?string $deliveryNotes = null,
    ): self {
        return DB::transaction(function () use ($cartItems, $customerId, $paymentMethod, $fulfillmentType, $taxRate, $channel, $deliveryAddress, $deliveryPhone, $deliveryNotes) {
            $customer = Customer::findOrFail($customerId);

            $subtotal = array_reduce(
                $cartItems,
                fn (float $carry, array $item) => $carry + ($item['product']->priceFor($customer) * $item['quantity']),
                0.0
            );
            $taxAmount = round($subtotal * ($taxRate / 100), 2);
            $total = $subtotal + $taxAmount;

            $order = self::create([
                'customer_id' => $customerId,
                'channel' => $channel,
                'status' => self::STATUS_RESERVEE,
                'fulfillment_type' => $fulfillmentType,
                'delivery_address' => $deliveryAddress,
                'delivery_phone' => $deliveryPhone,
                'delivery_notes' => $deliveryNotes,
                'subtotal' => $subtotal,
                'tax_rate' => $taxRate,
                'tax_amount' => $taxAmount,
                'total' => $total,
                'payment_method' => $paymentMethod,
                'payment_status' => 'pending',
            ]);

            $stockService = app(StockService::class);

            foreach ($cartItems as $item) {
                $product = $item['product'];

                $order->lines()->create([
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $product->priceFor($customer),
                ]);

                $stockService->reserve(
                    product: $product,
                    quantity: (float) $item['quantity'],
                    channel: $channel,
                    reservable: $order,
                    expiresAt: now()->addMinutes(self::RESERVATION_MINUTES),
                );
            }

            return $order->fresh('lines');
        });
    }

    /**
     * Paiement confirmé : déduit physiquement chaque réservation active et fait naître
     * la Sale correspondante (même principe que Quote::convertToSale — mais sans repasser
     * par Sale::checkout(), qui re-toucherait le stock déjà déduit ici).
     */
    public function confirmPayment(?int $userId = null): self
    {
        $this->assertCanTransitionTo(self::STATUS_PAYEE);

        return DB::transaction(function () use ($userId) {
            $stockService = app(StockService::class);

            foreach ($this->reservations()->where('status', Reservation::STATUS_ACTIVE)->get() as $reservation) {
                $stockService->deduct($reservation, $userId, 'Commande #'.$this->id.' — paiement confirmé');
            }

            $sale = $this->buildSaleRecord($userId);

            $this->update([
                'status' => self::STATUS_PAYEE,
                'payment_status' => 'paid',
                'paid_amount' => $this->total,
                'sale_id' => $sale->id,
            ]);

            return $this->fresh();
        });
    }

    public function startPreparation(): self
    {
        return $this->transitionTo(self::STATUS_PREPARATION);
    }

    public function markReady(): self
    {
        $order = $this->transitionTo(self::STATUS_PRETE);

        if ($order->customer) {
            Notification::send($order->customer, new OrderReady($order));
        }

        return $order;
    }

    public function deliver(): self
    {
        if ($this->fulfillment_type !== self::FULFILLMENT_LIVRAISON) {
            throw ValidationException::withMessages(['status' => 'Cette commande est en retrait, pas en livraison.']);
        }

        return $this->transitionTo(self::STATUS_LIVREE, ['delivered_at' => now()]);
    }

    public function pickUp(): self
    {
        if ($this->fulfillment_type !== self::FULFILLMENT_RETRAIT) {
            throw ValidationException::withMessages(['status' => 'Cette commande est en livraison, pas en retrait.']);
        }

        return $this->transitionTo(self::STATUS_RETIREE, ['delivered_at' => now()]);
    }

    /**
     * Annule la commande. Avant paiement : libère simplement les réservations actives
     * (rien n'a été déduit). Après paiement : le stock a déjà été déduit, donc on le
     * réintègre — c'est le [Annulée => RÉINTÈGRE] du cycle de vie.
     */
    public function cancel(?int $userId = null, ?string $reason = null): self
    {
        $this->assertCanTransitionTo(self::STATUS_ANNULEE);

        return DB::transaction(function () use ($userId, $reason) {
            $stockService = app(StockService::class);
            $previousStatus = $this->status;

            if ($this->status === self::STATUS_RESERVEE) {
                foreach ($this->reservations()->where('status', Reservation::STATUS_ACTIVE)->get() as $reservation) {
                    $stockService->release($reservation);
                }
            } else {
                foreach ($this->lines as $line) {
                    $stockService->reintegrate(
                        product: $line->product,
                        quantity: (float) $line->quantity,
                        reference: $this,
                        userId: $userId,
                        reason: $reason ?? 'Annulation commande #'.$this->id,
                        subtype: StockMovement::SUBTYPE_RETOUR_CLIENT,
                    );
                }
            }

            $this->update(['status' => self::STATUS_ANNULEE, 'cancelled_at' => now()]);

            AuditLog::record(
                'order.cancelled',
                $this,
                ['status' => $previousStatus],
                ['status' => self::STATUS_ANNULEE, 'reason' => $reason],
                $userId,
            );

            return $this->fresh();
        });
    }

    /** Retour après livraison/retrait — le stock est réintégré (marchandise physiquement rendue). */
    public function returnOrder(?int $userId = null, ?string $reason = null): self
    {
        $this->assertCanTransitionTo(self::STATUS_RETOURNEE);

        return DB::transaction(function () use ($userId, $reason) {
            $stockService = app(StockService::class);

            foreach ($this->lines as $line) {
                $remaining = (float) $line->quantity - (float) $line->returned_quantity;
                if ($remaining <= 0) {
                    continue;
                }

                $stockService->reintegrate(
                    product: $line->product,
                    quantity: $remaining,
                    reference: $this,
                    userId: $userId,
                    reason: $reason ?? 'Retour commande #'.$this->id,
                    subtype: StockMovement::SUBTYPE_RETOUR_CLIENT,
                );

                $line->update(['returned_quantity' => $line->quantity]);
            }

            $this->update(['status' => self::STATUS_RETOURNEE]);

            return $this->fresh();
        });
    }

    private function transitionTo(string $target, array $extra = []): self
    {
        $this->assertCanTransitionTo($target);
        $this->update(array_merge(['status' => $target], $extra));

        return $this->fresh();
    }

    private function assertCanTransitionTo(string $target): void
    {
        $allowed = self::TRANSITIONS[$this->status] ?? [];

        if (! in_array($target, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => "Transition impossible de « {$this->status} » vers « {$target} ».",
            ]);
        }
    }

    private function buildSaleRecord(?int $userId): Sale
    {
        $sale = Sale::create([
            'cash_register_session_id' => null,
            'user_id' => $userId,
            'customer_id' => $this->customer_id,
            'subtotal' => $this->subtotal,
            'tax_rate' => $this->tax_rate,
            'tax_amount' => $this->tax_amount,
            'total' => $this->total,
            'payment_method' => $this->payment_method,
            'status' => 'completed',
            'payment_status' => 'paid',
            'paid_amount' => $this->total,
        ]);

        foreach ($this->lines as $line) {
            $sale->lines()->create([
                'product_id' => $line->product_id,
                'lot_id' => $line->lot_id,
                'quantity' => $line->quantity,
                'unit_price' => $line->unit_price,
            ]);
        }

        return $sale;
    }
}
