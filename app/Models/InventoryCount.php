<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class InventoryCount extends Model
{
    public const TYPE_COMPLET = 'complet';
    public const TYPE_TOURNANT = 'tournant';

    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';

    protected $fillable = ['warehouse_id', 'user_id', 'type', 'status', 'category_id', 'completed_at', 'notes'];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function lines()
    {
        return $this->hasMany(InventoryCountLine::class);
    }

    public function discrepancyCount(): int
    {
        return $this->lines->filter(fn (InventoryCountLine $l) => $l->hasDiscrepancy())->count();
    }

    /**
     * Clôture l'inventaire : génère un mouvement d'ajustement pour chaque ligne comptée
     * dont l'écart est non nul, puis verrouille le comptage.
     */
    public function complete(int $byUserId): void
    {
        if ($this->status === self::STATUS_COMPLETED) {
            return;
        }

        DB::transaction(function () use ($byUserId) {
            foreach ($this->lines as $line) {
                if ($line->counted_quantity === null) {
                    continue;
                }

                $delta = round((float) $line->counted_quantity - (float) $line->expected_quantity, 2);

                if ($delta == 0.0) {
                    continue;
                }

                StockMovement::create([
                    'product_id' => $line->product_id,
                    'warehouse_id' => $this->warehouse_id,
                    'type' => StockMovement::TYPE_AJUSTEMENT,
                    'subtype' => StockMovement::SUBTYPE_INVENTAIRE,
                    'quantity' => $delta,
                    'reason' => 'Écart d\'inventaire #'.$this->id,
                    'reference_type' => self::class,
                    'reference_id' => $this->id,
                    'user_id' => $byUserId,
                ]);
            }

            $this->update(['status' => self::STATUS_COMPLETED, 'completed_at' => now()]);
        });
    }
}
