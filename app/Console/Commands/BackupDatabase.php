<?php

namespace App\Console\Commands;

use App\Support\ProcessOutput;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Sauvegarde quotidienne de la base (planifiée dans bootstrap/app.php, 02h00) — format
 * "custom" pg_dump (-Fc), compressé et compatible pg_restore avec sélection de tables/
 * parallélisation si besoin un jour. Purge les sauvegardes au-delà de --keep pour ne
 * jamais remplir le disque indéfiniment. Voir app:restore-database-backup pour la
 * restauration, et docs/sauvegardes.md pour la preuve que la restauration fonctionne.
 */
class BackupDatabase extends Command
{
    protected $signature = 'app:backup-database {--keep= : Nombre de sauvegardes à conserver (par défaut config(backup.keep))}';

    protected $description = 'Sauvegarde la base PostgreSQL (pg_dump) et purge les sauvegardes les plus anciennes.';

    public function handle(): int
    {
        $connection = config('database.connections.'.config('database.default'));

        if (($connection['driver'] ?? null) !== 'pgsql') {
            $this->error('app:backup-database ne gère que PostgreSQL (connexion par défaut : '.($connection['driver'] ?? 'inconnue').').');

            return self::FAILURE;
        }

        $dir = config('backup.path');
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            $this->error("Impossible de créer le répertoire de sauvegarde : {$dir}");

            return self::FAILURE;
        }

        $filename = 'quincaillerie-'.now()->format('Y-m-d_His').'.dump';
        $path = $dir.DIRECTORY_SEPARATOR.$filename;

        $result = Process::timeout((int) config('backup.timeout'))
            ->env(['PGPASSWORD' => (string) $connection['password']])
            ->run([
                config('backup.pg_dump_binary'),
                '-h', (string) $connection['host'],
                '-p', (string) $connection['port'],
                '-U', (string) $connection['username'],
                '-Fc',
                '-f', $path,
                (string) $connection['database'],
            ]);

        if (! $result->successful()) {
            $error = ProcessOutput::toUtf8($result->errorOutput());
            Log::error('app:backup-database a échoué', ['error' => $error]);
            $this->error('Échec de la sauvegarde : '.$error);

            return self::FAILURE;
        }

        $sizeMb = round(filesize($path) / 1024 / 1024, 2);
        $this->info("Sauvegarde créée : {$filename} ({$sizeMb} Mo)");
        Log::info('app:backup-database réussie', ['file' => $filename, 'size_mb' => $sizeMb]);

        $this->prune($dir, (int) ($this->option('keep') ?? config('backup.keep')));

        return self::SUCCESS;
    }

    private function prune(string $dir, int $keep): void
    {
        $files = collect(glob($dir.DIRECTORY_SEPARATOR.'quincaillerie-*.dump'))
            ->sortByDesc(fn (string $f) => filemtime($f))
            ->values();

        $files->slice($keep)->each(function (string $f) {
            @unlink($f);
            $this->info('Sauvegarde ancienne purgée : '.basename($f));
            Log::info('app:backup-database : purge', ['file' => basename($f)]);
        });
    }
}
