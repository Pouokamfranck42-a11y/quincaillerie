<?php

namespace App\Models;

use App\Services\Stock\StockService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class StockTransfer extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_COMPLETED = 'completed';

    protected $fillable = ['from_warehouse_id', 'to_warehouse_id', 'user_id', 'status', 'completed_at', 'notes'];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    public function fromWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function toWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    /** withTrashed() : un transfert historique reste attribuable même si l'utilisateur a quitté depuis. */
    public function user()
    {
        return $this->belongsTo(\App\Models\User::class)->withTrashed();
    }

    public function lines()
    {
        return $this->hasMany(StockTransferLine::class);
    }

    /**
     * Exécute le transfert : une sortie au site d'origine, une entrée au site de destination,
     * pour chaque ligne — le stock ne bouge jamais tant que le transfert n'est pas exécuté.
     * Passe par StockService (noyau unifié) : la sortie vérifie la disponibilité et verrouille
     * le produit avant d'agir, contrairement à un StockMovement::create() direct qui pourrait
     * faire passer le stock en négatif sans jamais être bloqué par une vente concurrente.
     */
    public function execute(int $byUserId): void
    {
        if ($this->status === self::STATUS_COMPLETED) {
            return;
        }

        DB::transaction(function () use ($byUserId) {
            $stockService = app(StockService::class);

            foreach ($this->lines as $line) {
                $stockService->withdraw(
                    product: $line->product,
                    quantity: (float) $line->quantity,
                    reference: $this,
                    userId: $byUserId,
                    reason: 'Transfert #'.$this->id.' vers '.$this->toWarehouse->name,
                    subtype: StockMovement::SUBTYPE_TRANSFERT,
                    warehouseId: $this->from_warehouse_id,
                );

                $stockService->reintegrate(
                    product: $line->product,
                    quantity: (float) $line->quantity,
                    reference: $this,
                    userId: $byUserId,
                    reason: 'Transfert #'.$this->id.' depuis '.$this->fromWarehouse->name,
                    subtype: StockMovement::SUBTYPE_TRANSFERT,
                    warehouseId: $this->to_warehouse_id,
                );
            }

            $this->update(['status' => self::STATUS_COMPLETED, 'completed_at' => now()]);
        });
    }
}
