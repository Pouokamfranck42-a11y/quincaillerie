<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

/**
 * Crée en base les permissions granulaires déclarées dans config/permissions.php — le seul
 * catalogue est celui-là ; ce seeder ne fait que le refléter en base (idempotent).
 */
class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('permissions') as $module) {
            foreach (array_keys($module['permissions']) as $name) {
                Permission::findOrCreate($name, 'web');
            }
        }
    }
}
