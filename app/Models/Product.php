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
        'sale_unit', 'sale_unit_factor', 'purchase_unit', 'purchase_unit_factor', 'warranty_months',
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

    /**
     * Recherche tolérante aux fautes de frappe (Phase 4) : combine la correspondance exacte/
     * substring habituelle (toujours incluse, jamais régressée) avec un repli par similarité de
     * trigrammes (pg_trgm — voir migration 2026_07_19_130000) qui rattrape une lettre manquante,
     * un accent oublié ou une lettre substituée ("perceuze" trouve "Perceuse..."). Seuil à 0.15,
     * calibré empiriquement (0.25 ratait déjà des typos à une seule lettre sur des noms courts).
     * Utilisé partout où un client/employé tape une recherche produit — un seul endroit définit
     * la règle, pour qu'elle ne diverge jamais entre le catalogue, le POS, la boutique et le chatbot.
     */
    public function scopeSearch($query, string $term)
    {
        $term = trim($term);
        if ($term === '') {
            return $query;
        }

        return $query->where(function ($q) use ($term) {
            $q->where('name', 'ilike', "%{$term}%")
                ->orWhere('reference', 'ilike', "%{$term}%")
                ->orWhere('barcode', $term)
                ->orWhereRaw('similarity(name, ?) > 0.15', [$term])
                ->orWhereRaw('similarity(reference, ?) > 0.15', [$term]);
        })->orderByRaw('GREATEST(similarity(name, ?), similarity(reference, ?)) DESC', [$term, $term]);
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

    public function dailySalesVelocity(): float
    {
        return $this->forecastedMonthlyDemand() / 30;
    }

    /** Jours avant rupture au rythme de vente actuel — null si aucun signal de vente récent (rien à projeter). */
    public function daysOfStockRemaining(): ?float
    {
        $velocity = $this->dailySalesVelocity();

        return $velocity > 0 ? round($this->availableStock() / $velocity, 1) : null;
    }

    public function projectedStockoutDate(): ?\Illuminate\Support\Carbon
    {
        $days = $this->daysOfStockRemaining();

        return $days !== null ? now()->addDays((int) ceil(max($days, 0))) : null;
    }

    /**
     * Date limite pour passer la commande sans risquer la rupture, compte tenu du délai de
     * livraison du fournisseur (Supplier::lead_time_days — jusqu'ici jamais exploité, le point
     * de commande était une valeur statique déconnectée du délai réel). Null si pas de
     * fournisseur renseigné ou pas de signal de vente exploitable.
     */
    public function recommendedOrderByDate(): ?\Illuminate\Support\Carbon
    {
        $stockout = $this->projectedStockoutDate();

        if ($stockout === null || ! $this->supplier) {
            return null;
        }

        return $stockout->copy()->subDays($this->supplier->lead_time_days);
    }

    /** La date limite de commande est déjà dépassée ou tombe dans les 3 prochains jours. */
    public function isUrgentReorder(): bool
    {
        $orderBy = $this->recommendedOrderByDate();

        return $orderBy !== null && $orderBy->lte(now()->addDays(3));
    }

    /**
     * Signal léger de saisonnalité : compare les 30 derniers jours de sorties à la même fenêtre
     * calendaire un an plus tôt. Null si l'historique ne remonte pas encore jusque-là (rien à
     * comparer) ou si le volume de l'an dernier est trop faible pour un ratio fiable.
     */
    public function seasonalityNote(): ?string
    {
        $lastYearWindowStart = now()->subYear()->subDays(15);
        $lastYearWindowEnd = now()->subYear()->addDays(15);

        if (! $this->stockMovements()->where('created_at', '<=', $lastYearWindowEnd)->exists()) {
            return null;
        }

        $recent = abs((float) $this->stockMovements()
            ->where('type', StockMovement::TYPE_SORTIE)
            ->where('created_at', '>=', now()->subDays(30))
            ->sum('quantity'));

        $lastYear = abs((float) $this->stockMovements()
            ->where('type', StockMovement::TYPE_SORTIE)
            ->whereBetween('created_at', [$lastYearWindowStart, $lastYearWindowEnd])
            ->sum('quantity'));

        if ($lastYear < 1) {
            return null;
        }

        $ratio = $recent / $lastYear;

        if ($ratio >= 1.5) {
            return 'Ventes en hausse (×'.round($ratio, 1).') par rapport à la même période l\'an dernier — probable effet saisonnier.';
        }
        if ($ratio <= 0.5) {
            return 'Ventes ralenties par rapport à la même période l\'an dernier.';
        }

        return null;
    }

    /** Dernière sortie de stock enregistrée pour ce produit (vente, quel que soit le canal) — null si jamais vendu. */
    public function daysSinceLastSale(): ?int
    {
        $last = $this->stockMovements()->where('type', StockMovement::TYPE_SORTIE)->max('created_at');

        // diffInDays() est signé et de précision flottante depuis Carbon 2 — abs()+floor() avant
        // la coercition vers ?int (une conversion float->int implicite est dépréciée en PHP 8.1+).
        return $last ? (int) floor(abs(now()->diffInDays(\Illuminate\Support\Carbon::parse($last)))) : null;
    }

    /** Dormant : du stock, mais aucune sortie depuis au moins $days jours (ou jamais vendu du tout). */
    public function isDormant(int $days = 90): bool
    {
        if ($this->currentStock() <= 0) {
            return false;
        }

        $sinceLastSale = $this->daysSinceLastSale();

        return $sinceLastSale === null || $sinceLastSale >= $days;
    }

    /** Argent immobilisé dans le stock actuel de ce produit, au coût d'achat. */
    public function capitalTiedUp(): float
    {
        return round($this->currentStock() * (float) $this->purchase_price, 2);
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
