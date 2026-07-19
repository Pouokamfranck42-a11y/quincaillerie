<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
     * Verrouille le devis AVANT de relire son statut : sans ça, un double-clic sur
     * "convertir" peut faire passer deux requêtes concurrentes le contrôle de statut
     * avant que l'une des deux ait écrit — deux Sale créées, stock déduit deux fois pour
     * un seul devis (même défaut déjà corrigé pour Order::cancel()/Sale::cancel()).
     */
    public function convertToSale(CashRegisterSession $session, int $userId, string $paymentMethod): Sale
    {
        return DB::transaction(function () use ($session, $userId, $paymentMethod) {
            $quote = self::where('id', $this->id)->lockForUpdate()->firstOrFail();

            if ($quote->status === self::STATUS_CONVERTI) {
                throw ValidationException::withMessages([
                    'quote' => 'Ce devis a déjà été converti en vente.',
                ]);
            }

            $cartItems = $quote->lines->map(fn (QuoteLine $line) => [
                'product' => $line->product,
                'quantity' => $line->quantity,
            ])->all();

            $sale = Sale::checkout(
                $cartItems,
                $session,
                $userId,
                $quote->customer_id,
                $paymentMethod,
                (float) $quote->tax_rate,
            );

            $quote->update(['status' => self::STATUS_CONVERTI, 'sale_id' => $sale->id]);

            return $sale;
        });
    }
}
