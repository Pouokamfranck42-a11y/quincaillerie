<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * ATTENTION — voir invoices/show.blade.php et le rapport de Phase 6 : la numérotation
 * séquentielle est bien garantie sans saut, mais les mentions légales (NIU/RCCM de
 * l'entreprise), la conformité TVA et l'intégration à une solution homologuée DGI
 * NE SONT PAS validées. Ne jamais présenter ces factures comme conformes sans
 * validation externe (comptable / DGI).
 */
class Invoice extends Model
{
    protected $fillable = ['invoiceable_type', 'invoiceable_id', 'customer_id', 'number', 'subtotal', 'tax_amount', 'tax_rate', 'total', 'issued_at'];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'total' => 'decimal:2',
        'issued_at' => 'datetime',
    ];

    public function invoiceable()
    {
        return $this->morphTo();
    }

    /** withTrashed() : une facture historique doit rester lisible même si le compte client a été désactivé/supprimé depuis. */
    public function customer()
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    /**
     * Génère (ou retrouve, si déjà fait) la facture d'une vente — numérotation
     * séquentielle sans saut via un compteur verrouillé (même principe que le
     * verrou de stock, Phase 3), remis à zéro chaque année.
     */
    public static function generateFor(Sale $sale): self
    {
        return DB::transaction(function () use ($sale) {
            $existing = self::where('invoiceable_type', Sale::class)->where('invoiceable_id', $sale->id)->first();
            if ($existing) {
                return $existing;
            }

            $year = now()->year;
            $counter = InvoiceCounter::where('year', $year)->lockForUpdate()->first();

            if (! $counter) {
                try {
                    $counter = InvoiceCounter::create(['year' => $year, 'last_number' => 0]);
                } catch (QueryException $e) {
                    $counter = InvoiceCounter::where('year', $year)->lockForUpdate()->firstOrFail();
                }
            }

            $counter->increment('last_number');

            return self::create([
                'invoiceable_type' => Sale::class,
                'invoiceable_id' => $sale->id,
                'customer_id' => $sale->customer_id,
                'number' => sprintf('FAC-%d-%06d', $year, $counter->last_number),
                'subtotal' => $sale->subtotal,
                'tax_amount' => $sale->tax_amount,
                'tax_rate' => $sale->tax_rate,
                'total' => $sale->total,
                'issued_at' => now(),
            ]);
        });
    }
}
