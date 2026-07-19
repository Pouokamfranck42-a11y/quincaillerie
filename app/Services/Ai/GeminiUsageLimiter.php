<?php

namespace App\Services\Ai;

use App\Models\GeminiUsage;
use App\Support\DatabaseLock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Quota/coût sur les appels Gemini (Phase 7) — un plafond quotidien configurable
 * (GEMINI_DAILY_CALL_LIMIT, 0 ou vide = illimité), persisté en base pour survivre à
 * un redémarrage et garder une vraie trace d'usage. Vérifié AVANT chaque appel réseau
 * (pas de coût engagé si le quota est déjà atteint).
 */
class GeminiUsageLimiter
{
    public function dailyLimit(): int
    {
        return (int) config('services.gemini.daily_call_limit', 0);
    }

    public function usedToday(): int
    {
        return (int) (GeminiUsage::whereDate('date', today())->value('calls') ?? 0);
    }

    public function remaining(): ?int
    {
        $limit = $this->dailyLimit();

        return $limit > 0 ? max(0, $limit - $this->usedToday()) : null;
    }

    public function hasQuotaRemaining(): bool
    {
        $limit = $this->dailyLimit();

        return $limit <= 0 || $this->usedToday() < $limit;
    }

    /**
     * Incrémente le compteur du jour de façon atomique (verrouillée, même principe que les
     * autres compteurs de l'app). L'appel Gemini a déjà eu lieu à ce stade : un verrou
     * contesté ne doit jamais faire échouer la réponse déjà obtenue par l'utilisateur —
     * on journalise et on continue plutôt que de propager l'erreur (léger sous-comptage
     * du quota, largement préférable à casser le chatbot).
     */
    public function recordCall(): void
    {
        try {
            DB::transaction(function () {
                $usage = DatabaseLock::guard(
                    fn () => GeminiUsage::where('date', today()->toDateString())->lockForUpdate()->first(),
                    'gemini',
                    'Quota Gemini en cours de mise à jour par une autre opération.',
                );

                if (! $usage) {
                    $usage = GeminiUsage::create(['date' => today()->toDateString(), 'calls' => 0]);
                }

                $usage->increment('calls');
            });
        } catch (ValidationException $e) {
            Log::warning('GeminiUsageLimiter::recordCall() : verrou contesté, appel non comptabilisé', [
                'date' => today()->toDateString(),
            ]);
        }
    }
}
