<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Dossier SAV rattaché à une ligne de vente précise (produit + quantité + n° de série
 * éventuel). Le retour physique de marchandise (si la résolution l'implique) passe par
 * SaleLine::returnQuantity() — noyau de stock unifié — jamais une écriture séparée ici.
 */
class ServiceTicket extends Model
{
    public const STATUS_OUVERT = 'ouvert';
    public const STATUS_EN_COURS = 'en_cours';
    public const STATUS_RESOLU = 'resolu';
    public const STATUS_REFUSE = 'refuse';

    public const RESOLUTION_REPARATION = 'reparation';
    public const RESOLUTION_ECHANGE = 'echange';
    public const RESOLUTION_REMBOURSEMENT = 'remboursement';
    public const RESOLUTION_REFUSE = 'refuse';

    protected $fillable = [
        'sale_line_id', 'opened_by', 'resolved_by', 'status',
        'resolution_type', 'issue_description', 'resolution_notes', 'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function saleLine()
    {
        return $this->belongsTo(SaleLine::class);
    }

    /** withTrashed() : un dossier historique reste attribuable même si l'agent a quitté depuis. */
    public function openedBy()
    {
        return $this->belongsTo(User::class, 'opened_by')->withTrashed();
    }

    public function resolvedBy()
    {
        return $this->belongsTo(User::class, 'resolved_by')->withTrashed();
    }

    public function resolve(int $userId, string $resolutionType, ?string $notes = null): void
    {
        $this->update([
            'status' => $resolutionType === self::RESOLUTION_REFUSE ? self::STATUS_REFUSE : self::STATUS_RESOLU,
            'resolution_type' => $resolutionType,
            'resolution_notes' => $notes,
            'resolved_by' => $userId,
            'resolved_at' => now(),
        ]);
    }
}
