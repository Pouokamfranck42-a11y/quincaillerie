<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'reference', 'name', 'brand', 'description', 'photo_path', 'category_id', 'supplier_id', 'supplier_sku',
        'purchase_price', 'sale_price', 'pro_price', 'barcode', 'location', 'unit', 'sold_by_cut', 'cut_step',
        'sale_unit', 'sale_unit_factor', 'purchase_unit', 'purchase_unit_factor',
        'low_stock_threshold', 'security_stock', 'max_stock', 'reorder_point', 'tracks_lots', 'active',
        'product_family_id', 'variant_attributes', 'published_online',
    ];

    protected $casts = [
        'purchase_price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'pro_price' => 'decimal:2',
        'sale_unit_factor' => 'decimal:3',
        'purchase_unit_factor' => 'decimal:3',
        'cut_step' => 'decimal:3',
        'low_stock_threshold' => 'decimal:2',
        'security_stock' => 'decimal:2',
        'max_stock' => 'decimal:2',
        'reorder_point' => 'decimal:2',
        'tracks_lots' => 'boolean',
        'sold_by_cut' => 'boolean',
        'active' => 'boolean',
        'published_online' => 'boolean',
        'variant_attributes' => 'array',
    ];

    /** Catalogue web (Phase 5) : actifs et explicitement publiés par l'admin. */
    public function scopePublished($query)
    {
        return $query->where('active', true)->where('published_online', true);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /** withTrashed() : le fournisseur principal reste affichable même s'il a été archivé depuis. */
    public function supplier()
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    /** Fournisseurs secondaires (le fournisseur principal reste supplier()). */
    public function alternateSuppliers()
    {
        return $this->hasMany(ProductSupplier::class);
    }

    public function lots()
    {
        return $this->hasMany(ProductLot::class);
    }

    /** Lots en stock positif, triés du plus proche de la péremption au plus lointain (FEFO). */
    public function fefoLots()
    {
        return $this->lots()
            ->get()
            ->filter(fn (ProductLot $lot) => $lot->currentQuantity() > 0)
            ->sortBy(fn (ProductLot $lot) => $lot->expiry_date?->timestamp ?? PHP_INT_MAX)
            ->values();
    }

    /** Prochain lot à consommer en priorité (péremption la plus proche), ou null si pas de lot en stock. */
    public function nextFefoLot(): ?ProductLot
    {
        return $this->fefoLots()->first();
    }

    public function family()
    {
        return $this->belongsTo(ProductFamily::class, 'product_family_id');
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function saleLines()
    {
        return $this->hasMany(SaleLine::class);
    }

    public function orderLines()
    {
        return $this->hasMany(OrderLine::class);
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }

    public function purchaseOrderLines()
    {
        return $this->hasMany(PurchaseOrderLine::class);
    }

    /** Stock courant = somme de tous les mouvements. Jamais stocké en dur. */
    public function currentStock(): float
    {
        return (float) $this->stockMovements()->sum('quantity');
    }

    public function isLowStock(): bool
    {
        return $this->currentStock() <= (float) $this->low_stock_threshold;
    }

    /** Quantité retenue pour des devis acceptés mais pas encore convertis en vente. */
    public function reservedStock(): float
    {
        return (float) QuoteLine::where('product_id', $this->id)
            ->whereHas('quote', fn ($q) => $q->where('status', Quote::STATUS_ACCEPTE))
            ->sum('quantity');
    }

    /** Quantité retenue par des réservations actives (StockService) — comptoir et web confondus. */
    public function activeReservationsStock(): float
    {
        return (float) $this->reservations()->where('status', Reservation::STATUS_ACTIVE)->sum('quantity');
    }

    /** Stock physique moins les devis acceptés et les réservations actives — c'est le "disponible" du modèle à 3 niveaux. */
    public function availableStock(): float
    {
        return $this->currentStock() - $this->reservedStock() - $this->activeReservationsStock();
    }

    /** Quantité en cours de commande fournisseur (en unité de stock), pas encore réceptionnée. */
    public function incomingStock(): float
    {
        return (float) $this->purchaseOrderLines()
            ->whereHas('purchaseOrder', fn ($q) => $q->where('status', PurchaseOrder::STATUS_ORDERED))
            ->get()
            ->sum(fn (PurchaseOrderLine $line) => $this->toStockQuantity((float) $line->quantity - (float) $line->received_quantity));
    }

    public function isOverstock(): bool
    {
        return $this->max_stock !== null && $this->currentStock() > (float) $this->max_stock;
    }

    public function effectiveReorderPoint(): float
    {
        return (float) ($this->reorder_point ?? $this->low_stock_threshold);
    }

    public function needsReorder(): bool
    {
        return $this->availableStock() + $this->incomingStock() <= $this->effectiveReorderPoint();
    }

    /**
     * Prévision de la demande mensuelle par moyenne mobile pondérée sur les 6 derniers mois de
     * sorties de stock (les mois les plus récents pèsent davantage) — plus réactive aux tendances
     * qu'une simple moyenne plate sur 90 jours.
     */
    public function forecastedMonthlyDemand(): float
    {
        $monthly = $this->stockMovements()
            ->where('type', StockMovement::TYPE_SORTIE)
            ->where('created_at', '>=', now()->subMonths(6)->startOfMonth())
            ->selectRaw("date_trunc('month', created_at) as month, SUM(ABS(quantity)) as qty")
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('qty');

        if ($monthly->isEmpty()) {
            return 0.0;
        }

        $weightedSum = 0.0;
        $weightTotal = 0;

        foreach ($monthly->values() as $i => $qty) {
            $weight = $i + 1; // le mois le plus ancien pèse 1, le plus récent pèse N
            $weightedSum += (float) $qty * $weight;
            $weightTotal += $weight;
        }

        return round($weightedSum / $weightTotal, 2);
    }

    /**
     * Quantité économique de commande (formule de Wilson) : sqrt(2 × D × S / H).
     * D = demande annuelle, extrapolée depuis la prévision mensuelle pondérée (forecastedMonthlyDemand).
     * S = coût de passation d'une commande (constante conventionnelle, faute de comptabilité analytique).
     * H = coût de possession annuel par unité, estimé à 20 % du prix d'achat.
     * Simplifié volontairement : donne un ordre de grandeur, pas un optimum comptable précis.
     */
    public function economicOrderQuantity(): float
    {
        $annualDemand = $this->forecastedMonthlyDemand() * 12;

        if ($annualDemand <= 0 || (float) $this->purchase_price <= 0) {
            return $this->effectiveReorderPoint();
        }

        $orderingCost = 2000; // FCFA, coût administratif conventionnel par commande
        $holdingCost = (float) $this->purchase_price * 0.20;

        return round(sqrt((2 * $annualDemand * $orderingCost) / $holdingCost), 0);
    }

    public function marginAmount(): float
    {
        return (float) $this->sale_price - (float) $this->purchase_price;
    }

    public function marginPercent(): float
    {
        if ((float) $this->purchase_price <= 0) {
            return 0;
        }

        return round(($this->marginAmount() / (float) $this->purchase_price) * 100, 1);
    }

    /** Prix pratiqué pour un client donné : tarif pro s'il est renseigné et que le client est professionnel. */
    public function priceFor(?Customer $customer): float
    {
        if ($customer && $customer->type === 'professionnel' && $this->pro_price !== null) {
            return (float) $this->pro_price;
        }

        return (float) $this->sale_price;
    }

    /** Convertit une quantité exprimée en unité d'achat (ex. rouleau) en unité de stock (ex. mètre). */
    public function toStockQuantity(float $purchaseQuantity): float
    {
        return round($purchaseQuantity * (float) $this->purchase_unit_factor, 2);
    }

    /**
     * Vente à la découpe (câble, tuyau, chaîne...) : la quantité doit être un multiple du
     * pas défini (ex. 0.5 m), pas n'importe quelle décimale arbitraire. Ne change rien pour
     * les produits non marqués sold_by_cut — comportement identique à avant cette fonction.
     * Vérifié au point de passage commun (Sale::checkout(), Order::place()) plutôt que
     * dans chaque contrôleur, pour ne jamais dépendre de l'endroit d'où la vente est déclenchée.
     */
    public function assertValidSaleQuantity(float $quantity): void
    {
        if (! $this->sold_by_cut) {
            return;
        }

        $step = (float) $this->cut_step ?: 1.0;
        $steps = round($quantity / $step, 4);

        if (abs($steps - round($steps)) > 0.001) {
            throw ValidationException::withMessages([
                'quantity' => "{$this->name} se vend par pas de ".rtrim(rtrim(number_format($step, 3, '.', ''), '0'), '.')." {$this->unit} (ex : ".rtrim(rtrim(number_format($step * 2, 3, '.', ''), '0'), '.').', '.rtrim(rtrim(number_format($step * 3, 3, '.', ''), '0'), '.').'...).',
            ]);
        }
    }

    /**
     * Recalcule le prix d'achat en coût unitaire moyen pondéré (CUMP) après une entrée de stock.
     * $quantityIn et $unitCost sont exprimés en unité de stock.
     */
    public function applyCump(float $quantityIn, float $unitCost): void
    {
        if ($quantityIn <= 0 || $unitCost <= 0) {
            return;
        }

        $stockBefore = $this->currentStock();

        $newAverage = $stockBefore > 0
            ? (($stockBefore * (float) $this->purchase_price) + ($quantityIn * $unitCost)) / ($stockBefore + $quantityIn)
            : $unitCost;

        $this->update(['purchase_price' => round($newAverage, 2)]);
    }
}
