<?php

namespace App\Support;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Distingue un rejet métier attendu (l'utilisateur a fait quelque chose d'invalide, ce
 * n'est pas un bug) d'une exception inattendue. Utilisé par bootstrap/app.php pour décider
 * quoi enregistrer dans error_logs/alerter — les rejets attendus continuent d'atterrir dans
 * storage/logs/laravel.log comme d'habitude, mais n'ont pas besoin d'une alerte : ce serait
 * du bruit qui noierait les vraies anomalies.
 *
 * Classe plutôt que fonction globale dans bootstrap/app.php : ce fichier est ré-exécuté
 * plusieurs fois par processus PHP pendant les tests (une déclaration `function` au premier
 * niveau y provoquerait une erreur de redéclaration au second test).
 */
class ExceptionClassifier
{
    public static function isRoutine(\Throwable $e): bool
    {
        if ($e instanceof ValidationException
            || $e instanceof AuthenticationException
            || $e instanceof AuthorizationException
            || $e instanceof UnauthorizedException
            || $e instanceof ModelNotFoundException
            || $e instanceof TokenMismatchException
            || $e instanceof ThrottleRequestsException) {
            return true;
        }

        // HttpExceptionInterface couvre 404/405/... : seules les erreurs serveur (5xx) sont
        // de vraies anomalies à journaliser/alerter, un 404 "normal" ne l'est pas.
        if ($e instanceof HttpExceptionInterface && $e->getStatusCode() < 500) {
            return true;
        }

        return false;
    }
}
