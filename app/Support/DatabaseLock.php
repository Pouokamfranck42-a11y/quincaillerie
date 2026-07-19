<?php

namespace App\Support;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Borne l'attente de tout SELECT ... FOR UPDATE contesté — sans ça, PostgreSQL attend
 * indéfiniment (ni lock_timeout ni statement_timeout par défaut) et, comme une seule
 * requête est traitée à la fois en développement, un verrou bloqué gèle l'application
 * entière pour tout le monde (bug diagnostiqué et corrigé sur StockService — ce helper
 * généralise le même correctif à tout autre verrou de l'application : compteur de
 * factures, webhook de paiement, quota IA, devis, transferts de stock...).
 */
class DatabaseLock
{
    private const DEFAULT_TIMEOUT = '5s';

    /** set_config(..., true) = portée LOCAL (annulée au COMMIT/ROLLBACK) — SET n'accepte pas de paramètre lié pour sa valeur, set_config() est une fonction normale. */
    public static function timeout(string $timeout = self::DEFAULT_TIMEOUT): void
    {
        DB::statement("SELECT set_config('lock_timeout', ?, true)", [$timeout]);
    }

    /**
     * Exécute $callback sous un lock_timeout borné : un verrou contesté échoue
     * proprement avec le message donné au lieu de bloquer indéfiniment.
     */
    public static function guard(callable $callback, string $errorKey, string $friendlyMessage, string $timeout = self::DEFAULT_TIMEOUT)
    {
        try {
            self::timeout($timeout);

            return $callback();
        } catch (QueryException $e) {
            if (self::isLockTimeout($e)) {
                throw ValidationException::withMessages([$errorKey => $friendlyMessage]);
            }

            throw $e;
        }
    }

    /** SQLSTATE 55P03 = lock_not_available (PostgreSQL), déclenché par le lock_timeout ci-dessus. */
    public static function isLockTimeout(QueryException $e): bool
    {
        return $e->getCode() === '55P03' || str_contains($e->getMessage(), 'lock timeout');
    }
}
