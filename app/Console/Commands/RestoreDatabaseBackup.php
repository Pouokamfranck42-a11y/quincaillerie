<?php

namespace App\Console\Commands;

use App\Support\ProcessOutput;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

/**
 * Restaure une sauvegarde pg_dump (-Fc) vers une base CIBLE explicite — jamais implicite,
 * pour ne jamais écraser accidentellement la base en cours d'utilisation par erreur de
 * frappe. --database est obligatoire ; --drop-existing recrée la base cible si elle
 * existe déjà (nécessaire pour rejouer un test de restauration plusieurs fois).
 *
 * Usage typique de vérification (voir docs/sauvegardes.md) :
 *   php artisan app:restore-database-backup storage/app/backups/xxx.dump --database=quincaillerie_restore_test --drop-existing
 */
class RestoreDatabaseBackup extends Command
{
    protected $signature = 'app:restore-database-backup
        {file : Chemin du fichier .dump à restaurer}
        {--database= : Base de données CIBLE (obligatoire, jamais la base en cours d\'utilisation par défaut)}
        {--drop-existing : Supprime puis recrée la base cible si elle existe déjà}';

    protected $description = 'Restaure une sauvegarde pg_dump vers une base cible explicite (jamais la base en cours d\'utilisation par défaut).';

    public function handle(): int
    {
        $file = $this->argument('file');
        $database = $this->option('database');

        if (! $database) {
            $this->error('--database est obligatoire : précisez explicitement la base cible (jamais la base en cours d\'utilisation par défaut, pour éviter tout écrasement accidentel).');

            return self::FAILURE;
        }

        if (! is_file($file)) {
            $this->error("Fichier introuvable : {$file}");

            return self::FAILURE;
        }

        $connection = config('database.connections.'.config('database.default'));
        if (($connection['driver'] ?? null) !== 'pgsql') {
            $this->error('app:restore-database-backup ne gère que PostgreSQL.');

            return self::FAILURE;
        }

        $env = ['PGPASSWORD' => (string) $connection['password']];
        $timeout = (int) config('backup.timeout');

        if ($this->option('drop-existing')) {
            $this->info("Suppression de la base cible « {$database} » si elle existe...");
            Process::timeout($timeout)->env($env)->run([
                config('backup.dropdb_binary'),
                '-h', (string) $connection['host'], '-p', (string) $connection['port'], '-U', (string) $connection['username'],
                '--if-exists', $database,
            ]);
        }

        $this->info("Création de la base cible « {$database} »...");
        $create = Process::timeout($timeout)->env($env)->run([
            config('backup.createdb_binary'),
            '-h', (string) $connection['host'], '-p', (string) $connection['port'], '-U', (string) $connection['username'],
            $database,
        ]);

        if (! $create->successful()) {
            $this->error('Échec de la création de la base cible : '.ProcessOutput::toUtf8($create->errorOutput()));

            return self::FAILURE;
        }

        $this->info("Restauration de {$file} vers « {$database} »...");
        $restore = Process::timeout($timeout)->env($env)->run([
            config('backup.pg_restore_binary'),
            '-h', (string) $connection['host'], '-p', (string) $connection['port'], '-U', (string) $connection['username'],
            '-d', $database,
            '--no-owner', '--no-privileges',
            $file,
        ]);

        // pg_restore renvoie parfois un code non-nul pour de simples avertissements (ex. extension
        // déjà présente) — on affiche stderr mais on ne considère l'échec que si la base cible est
        // restée vide (aucune table restaurée), signal fiable d'un échec réel.
        $tableCount = Process::timeout(30)->env($env)->run([
            config('backup.psql_binary'), '-h', (string) $connection['host'], '-p', (string) $connection['port'], '-U', (string) $connection['username'],
            '-d', $database, '-t', '-A', '-c', "SELECT count(*) FROM information_schema.tables WHERE table_schema = 'public'",
        ]);

        $count = (int) trim((string) $tableCount->output());

        if ($count === 0) {
            $this->error('Restauration échouée : aucune table trouvée dans la base cible.');
            $this->error(ProcessOutput::toUtf8($restore->errorOutput()));

            return self::FAILURE;
        }

        $this->info("Restauration terminée : {$count} tables restaurées dans « {$database} ».");
        if ($restore->errorOutput()) {
            $this->warn('Avertissements pg_restore (généralement sans gravité — extensions déjà présentes, etc.) :');
            $this->line(ProcessOutput::toUtf8($restore->errorOutput()));
        }

        return self::SUCCESS;
    }
}
