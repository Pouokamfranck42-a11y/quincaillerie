<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Phase 1 — sauvegardes automatiques : preuve que app:backup-database produit un
 * fichier pg_dump valide et purge les sauvegardes au-delà de --keep. La restauration
 * elle-même est vérifiée manuellement (voir docs/sauvegardes.md) — un test automatisé
 * qui recrée une base à chaque exécution serait lent et dupliquerait cette preuve.
 */
class DatabaseBackupTest extends TestCase
{
    use RefreshDatabase;

    private string $backupDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->backupDir = storage_path('framework/testing/backups-'.uniqid());
        config(['backup.path' => $this->backupDir]);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->backupDir)) {
            array_map('unlink', glob($this->backupDir.'/*'));
            rmdir($this->backupDir);
        }

        parent::tearDown();
    }

    public function test_backup_command_produces_a_valid_non_empty_dump_file(): void
    {
        $exitCode = Artisan::call('app:backup-database');

        $this->assertSame(0, $exitCode);

        $files = glob($this->backupDir.'/quincaillerie-*.dump');
        $this->assertCount(1, $files);
        $this->assertGreaterThan(0, filesize($files[0]));
    }

    public function test_backup_command_prunes_backups_beyond_the_keep_count(): void
    {
        mkdir($this->backupDir, 0755, true);

        // Simule 3 sauvegardes précédentes avec des dates de modification échelonnées.
        foreach (range(1, 3) as $i) {
            $file = $this->backupDir."/quincaillerie-2026-01-0{$i}_000000.dump";
            file_put_contents($file, 'contenu factice');
            touch($file, now()->subDays(10 - $i)->timestamp);
        }

        $exitCode = Artisan::call('app:backup-database', ['--keep' => 2]);

        $this->assertSame(0, $exitCode);

        $files = glob($this->backupDir.'/quincaillerie-*.dump');
        $this->assertCount(2, $files, 'Seules les 2 sauvegardes les plus récentes (dont celle qui vient d\'être créée) doivent rester.');
    }
}
