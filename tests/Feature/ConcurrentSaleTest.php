<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Reservation;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\Stock\StockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Phase 9 — LE TEST DÉCISIF : deux ventes simultanées sur le dernier article (une
 * comptoir, une web) => jamais de survente.
 *
 * Volontairement SANS RefreshDatabase : ce test a besoin que les données soient
 * réellement commitées en base pour qu'un second processus PHP (connexion séparée,
 * vraie concurrence au niveau du moteur de base de données — pas un thread simulé)
 * puisse les voir et entrer en contention sur le même verrou de ligne. C'est le
 * scénario que RefreshDatabase rend impossible à tester correctement (chaque test
 * est isolé dans une transaction jamais commitée), d'où le report explicite à cette
 * phase depuis la Phase 3.
 */
class ConcurrentSaleTest extends TestCase
{
    private ?Product $product = null;

    private ?string $signalFile = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Warehouse::where('is_default', true)->exists()) {
            Warehouse::create(['name' => 'Magasin principal (test concurrence)', 'is_default' => true]);
        }

        $this->product = Product::create([
            'reference' => 'CONCUR-'.uniqid(),
            'name' => 'Produit test concurrence',
            'purchase_price' => 100,
            'sale_price' => 200,
            'unit' => 'unité',
            'low_stock_threshold' => 0,
        ]);
        StockMovement::create(['product_id' => $this->product->id, 'type' => StockMovement::TYPE_ENTREE, 'quantity' => 1]);

        $this->signalFile = sys_get_temp_dir().'/concur_signal_'.uniqid().'.txt';
    }

    protected function tearDown(): void
    {
        if ($this->signalFile && file_exists($this->signalFile)) {
            @unlink($this->signalFile);
        }

        if ($this->product) {
            Reservation::where('product_id', $this->product->id)->delete();
            StockMovement::where('product_id', $this->product->id)->delete();
            $this->product->forceDelete();
        }

        parent::tearDown();
    }

    public function test_two_simultaneous_sales_on_the_last_unit_never_both_succeed(): void
    {
        $scriptPath = __DIR__.'/../concurrency/reserve_and_hold.php';
        $holdMs = 800;

        $phpBinary = PHP_BINARY;
        $cmd = sprintf(
            '%s %s %d %s %d comptoir',
            escapeshellarg($phpBinary),
            escapeshellarg($scriptPath),
            $this->product->id,
            escapeshellarg($this->signalFile),
            $holdMs,
        );

        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open($cmd, $descriptors, $pipes, base_path());
        $this->assertIsResource($process, 'Impossible de démarrer le sous-processus de test de concurrence.');

        // Attend que le sous-processus ait réellement acquis le verrou (fichier signal),
        // preuve qu'il est "en vol" avant que le process principal ne tente sa propre vente.
        $waited = 0;
        while (! file_exists($this->signalFile) && $waited < 5000) {
            usleep(20000);
            $waited += 20;
        }
        $this->assertFileExists($this->signalFile, "Le sous-processus n'a jamais signalé avoir acquis le verrou — la contention n'a pas pu être testée.");

        // Le process principal tente SA vente sur le même produit pendant que le
        // sous-processus tient encore le verrou : doit bloquer jusqu'à sa libération.
        DB::statement('SET statement_timeout = 5000');
        $start = microtime(true);
        $mainAttemptFailed = false;
        $mainErrors = [];

        try {
            app(StockService::class)->reserveAndDeduct(
                product: $this->product->fresh(),
                quantity: 1,
                channel: 'web',
                reason: 'Process principal — tentative concurrente',
            );
        } catch (ValidationException $e) {
            $mainAttemptFailed = true;
            $mainErrors = $e->errors();
        }

        $elapsedMs = (microtime(true) - $start) * 1000;

        // Preuve que le verrou a réellement fait attendre le process principal (pas un échec instantané sans lien avec la contention).
        $this->assertGreaterThan($holdMs * 0.5, $elapsedMs, "La tentative concurrente n'a pas attendu le verrou comme attendu (a répondu en {$elapsedMs} ms).");

        // La vente concurrente doit avoir échoué : le sous-processus a pris la dernière unité en premier.
        $this->assertTrue($mainAttemptFailed, 'La vente concurrente du process principal aurait dû échouer (stock insuffisant) — sinon, survente.');
        $this->assertArrayHasKey('stock', $mainErrors);

        // Le sous-processus, lui, doit avoir réussi.
        $subprocessOutput = stream_get_contents($pipes[1]);
        $subprocessErrors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        $this->assertSame(0, $exitCode, "Le sous-processus a échoué : {$subprocessErrors}");
        $this->assertStringContainsString('OK', $subprocessOutput);

        // État final : une seule réservation (consommée), stock physique à zéro — jamais négatif, jamais deux ventes.
        $this->assertSame(1, Reservation::where('product_id', $this->product->id)->count());
        $this->assertSame(Reservation::STATUS_CONSUMED, Reservation::where('product_id', $this->product->id)->first()->status);
        $this->assertSame(0.0, $this->product->fresh()->currentStock());
        $this->assertSame(0.0, $this->product->fresh()->availableStock());
    }
}
