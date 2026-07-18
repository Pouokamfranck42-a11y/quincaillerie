<?php

/**
 * Sous-processus PHP réel (pas un thread simulé) utilisé par ConcurrentSaleTest pour
 * prouver que le verrou de ligne (SELECT ... FOR UPDATE) sérialise vraiment deux
 * connexions concurrentes — le "test décisif" de la Phase 9 : deux ventes simultanées
 * sur le dernier article, une seule doit jamais survendre.
 *
 * Usage : php reserve_and_hold.php <product_id> <signal_file> <hold_ms> <channel>
 * Verrouille la ligne produit, écrit le fichier signal une fois le verrou acquis,
 * tient le verrou pendant hold_ms, puis termine la réservation dans la même transaction.
 */

use App\Models\Product;
use App\Models\Reservation;
use App\Services\Stock\StockService;
use Illuminate\Support\Facades\DB;

putenv('APP_ENV=testing');
$_ENV['APP_ENV'] = 'testing';
$_SERVER['APP_ENV'] = 'testing';

require __DIR__.'/../../vendor/autoload.php';
/** @var \Illuminate\Foundation\Application $app */
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

[, $productId, $signalFile, $holdMs, $channel] = $argv;
$productId = (int) $productId;
$holdMs = (int) $holdMs;

try {
    DB::transaction(function () use ($productId, $signalFile, $holdMs, $channel) {
        $product = Product::where('id', $productId)->lockForUpdate()->firstOrFail();

        // Le verrou est acquis : signale au process principal qu'il peut tenter la sienne.
        file_put_contents($signalFile, 'locked');

        usleep($holdMs * 1000);

        app(StockService::class)->reserveAndDeduct(
            product: $product,
            quantity: 1,
            channel: $channel,
            reason: 'Sous-processus concurrence — test décisif',
        );
    });

    echo 'OK'.PHP_EOL;
} catch (\Throwable $e) {
    echo 'ERROR: '.$e->getMessage().PHP_EOL;
    exit(1);
}
