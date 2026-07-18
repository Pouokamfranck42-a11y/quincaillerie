<?php

namespace App\Console\Commands;

use App\Services\Ai\GeminiService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * À exécuter avant toute autre fonctionnalité IA (Phase 7) — fait un vrai appel minimal
 * à l'API Gemini et rapporte clairement si la clé est valide, invalide, en quota dépassé,
 * ou si le service est injoignable. Jamais de crash : toujours un message actionnable.
 */
#[Signature('app:validate-gemini-key')]
#[Description("Vérifie que GEMINI_API_KEY est valide en faisant un appel réel minimal à l'API")]
class ValidateGeminiKey extends Command
{
    public function handle(GeminiService $gemini): int
    {
        $this->info('Vérification de la clé Gemini en cours…');

        $result = $gemini->validateKey();

        if ($result['valid']) {
            $this->info('✔ '.$result['message']);

            return self::SUCCESS;
        }

        $this->error('✘ Clé Gemini invalide ou inutilisable.');
        $this->line('Raison : '.$result['reason']);
        $this->line('Détail : '.$result['message']);

        if ($result['reason'] === 'invalid_key') {
            $this->line('');
            $this->line('Obtiens une vraie clé (format AIzaSy...) sur https://aistudio.google.com/apikey');
            $this->line('puis renseigne GEMINI_API_KEY dans .env.');
        }

        return self::FAILURE;
    }
}
