<?php

namespace Tests\Feature;

use App\Models\ErrorLog;
use App\Models\User;
use App\Notifications\CriticalErrorOccurred;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Phase 1 — gestion d'erreurs uniforme : aucune page blanche/figée, message clair,
 * journalisation systématique des erreurs inattendues, et alerte des admins.
 */
class ErrorHandlingTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_generic_404_renders_the_custom_error_page_not_a_blank_page(): void
    {
        config(['app.debug' => false]);

        $response = $this->get('/route-qui-nexiste-pas-du-tout');

        $response->assertStatus(404);
        $response->assertSee('Page introuvable');
    }

    public function test_an_unexpected_exception_is_recorded_and_admins_are_notified(): void
    {
        Notification::fake();

        \Spatie\Permission\Models\Role::findOrCreate('admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        config(['app.debug' => false]);
        Route::get('/test-route-qui-plante', function () {
            throw new \RuntimeException('Panne simulée pour le test.');
        })->middleware('web');

        $response = $this->actingAs($admin)->get('/test-route-qui-plante');

        $response->assertStatus(500);
        $response->assertSee('Erreur technique');

        $this->assertDatabaseCount('error_logs', 1);
        $log = ErrorLog::first();
        $this->assertSame(\RuntimeException::class, $log->exception_class);
        $this->assertSame('Panne simulée pour le test.', $log->message);

        Notification::assertSentTo($admin, CriticalErrorOccurred::class);
    }

    public function test_routine_exceptions_are_not_recorded_as_critical_errors(): void
    {
        Notification::fake();

        \Spatie\Permission\Models\Role::findOrCreate('admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        // 404 "normal" (ressource inexistante) : pas un bug, ne doit générer ni entrée
        // error_logs, ni alerte — ce serait du bruit qui noierait les vraies anomalies.
        $response = $this->actingAs($admin)->get('/products/999999/edit');

        $response->assertStatus(404);
        $this->assertDatabaseCount('error_logs', 0);
        Notification::assertNothingSent();
    }

    public function test_staff_with_permission_can_view_the_error_log(): void
    {
        \Spatie\Permission\Models\Role::findOrCreate('admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        ErrorLog::create([
            'exception_class' => \RuntimeException::class,
            'message' => 'Erreur test',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('error-logs.index'));

        $response->assertOk();
        $response->assertSee('Erreur test');
    }
}
