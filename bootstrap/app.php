<?php

use App\Models\AuditLog;
use App\Models\ErrorLog;
use App\Models\User;
use App\Notifications\CriticalErrorOccurred;
use App\Support\ExceptionClassifier;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Exceptions\UnauthorizedException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
        ]);

        // Un invité bloqué sur /boutique/* doit atterrir sur la connexion boutique,
        // pas sur la connexion staff (par défaut, redirectGuestsTo ignore le guard demandé).
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('boutique/*')
            ? route('shop.login')
            : route('login'));

        // Le webhook de paiement est appelé par le serveur de l'agrégateur, jamais
        // par un navigateur — il n'a pas de jeton CSRF et n'en aura jamais.
        $middleware->validateCsrfTokens(except: ['webhooks/*']);
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('app:check-expiring-lots')->daily();
        $schedule->command('app:segment-customers')->weekly();
        $schedule->command('app:compute-cross-sell')->daily();
        $schedule->command('app:release-expired-reservations')
            ->everyFiveMinutes()
            ->appendOutputTo(storage_path('logs/schedule.log'));
        $schedule->command('app:backup-database')
            ->dailyAt('02:00')
            ->appendOutputTo(storage_path('logs/backup.log'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // Toute tentative d'accès sans la permission requise est journalisée ici — un seul
        // endroit, jamais oublié sur une route individuelle (couvre le middleware `permission:`
        // partout où il est appliqué).
        $exceptions->render(function (UnauthorizedException $e, Request $request) {
            if ($request->user()) {
                AuditLog::record('access.denied', $request->user(), [], [
                    'route' => $request->route()?->getName(),
                    'path' => $request->path(),
                ], $request->user()->id);
            }

            return response()->view('errors.403', [], 403);
        });

        // Log serveur systématique (Phase 1 — gestion d'erreurs uniforme) : toute exception
        // qui n'est pas un rejet métier attendu est enregistrée dans error_logs — table
        // consultable par le staff (AdministrationController) et source de l'alerte email
        // (CriticalErrorOccurred). Ne doit jamais elle-même faire échouer la requête : une
        // panne d'écriture du log ne doit pas transformer une erreur gérable en page blanche.
        $exceptions->reportable(function (\Throwable $e) {
            if (ExceptionClassifier::isRoutine($e)) {
                return;
            }

            try {
                $errorLog = ErrorLog::recordFrom($e, request());

                $admins = User::role('admin')->get();
                if ($admins->isNotEmpty()) {
                    Notification::send($admins, new CriticalErrorOccurred($errorLog));
                }
            } catch (\Throwable) {
                // Le report() original de Laravel a déjà écrit dans storage/logs/laravel.log
                // avant d'arriver ici — rien de plus à faire si CETTE couche échoue à son tour.
            }
        });
    })->create();
