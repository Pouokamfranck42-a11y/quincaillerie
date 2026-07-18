<?php

namespace App\Models;

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
     */
    public function execute(int $byUserId): void
    {
        if ($this->status === self::STATUS_COMPLETED) {
            return;
        }

        DB::transaction(function () use ($byUserId) {
            foreach ($this->lines as $line) {
                StockMovement::create([
                    'product_id' => $line->product_id,
                    'warehouse_id' => $this->from_warehouse_id,
                    'type' => StockMovement::TYPE_SORTIE,
                    'subtype' => StockMovement::SUBTYPE_TRANSFERT,
                    'quantity' => -$line->quantity,
                    'reason' => 'Transfert #'.$this->id.' vers '.$this->toWarehouse->name,
                    'reference_type' => self::class,
                    'reference_id' => $this->id,
                    'user_id' => $byUserId,
                ]);

                StockMovement::create([
                    'product_id' => $line->product_id,
                    'warehouse_id' => $this->to_warehouse_id,
                    'type' => StockMovement::TYPE_ENTREE,
                    'subtype' => StockMovement::SUBTYPE_TRANSFERT,
                    'quantity' => $line->quantity,
                    'reason' => 'Transfert #'.$this->id.' depuis '.$this->fromWarehouse->name,
                    'reference_type' => self::class,
                    'reference_id' => $this->id,
                    'user_id' => $byUserId,
                ]);
            }

            $this->update(['status' => self::STATUS_COMPLETED, 'completed_at' => now()]);
        });
    }
}
