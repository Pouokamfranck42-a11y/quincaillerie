<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Quote extends Model
{
    public const STATUS_BROUILLON = 'brouillon';
    public const STATUS_ENVOYE = 'envoye';
    public const STATUS_ACCEPTE = 'accepte';
    public const STATUS_CONVERTI = 'converti';

    protected $fillable = [
        'customer_id', 'user_id', 'subtotal', 'tax_rate', 'tax_amount', 'total',
        'status', 'sale_id', 'valid_until', 'notes',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'valid_until' => 'date',
    ];

    /** withTrashed() : un devis historique doit rester lisible même si le compte client a été désactivé/supprimé depuis. */
    public function customer()
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    /** withTrashed() : un devis historique reste attribuable même si l'utilisateur a quitté depuis (voir quotes/show.blade.php qui accède à $quote->user->name sans opérateur null-safe). */
    public function user()
    {
        return $this->belongsTo(\App\Models\User::class)->withTrashed();
    }

    public function lines()
    {
        return $this->hasMany(QuoteLine::class);
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * Convertit le devis en vente en réutilisant Sale::checkout(), avec la session
     * de caisse actuellement ouverte de l'utilisateur qui effectue la conversion.
     */
    public function convertToSale(CashRegisterSession $session, int $userId, string $paymentMethod): Sale
    {
        $cartItems = $this->lines->map(fn (QuoteLine $line) => [
            'product' => $line->product,
            'quantity' => $line->quantity,
        ])->all();

        $sale = Sale::checkout(
            $cartItems,
            $session,
            $userId,
            $this->customer_id,
            $paymentMethod,
            (float) $this->tax_rate,
        );

        $this->update(['status' => self::STATUS_CONVERTI, 'sale_id' => $sale->id]);

        return $sale;
    }
}
