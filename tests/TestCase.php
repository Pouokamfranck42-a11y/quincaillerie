<?php

namespace Tests;

use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Utilisé par RefreshDatabase (voir CanConfigureMigrationCommands::seeder()) : seedé après
     * chaque migration fraîche pour que les rôles "admin"/"magasinier"/"caissier" portent
     * réellement leurs permissions dans les tests — sans ça, assignRole('admin') donnerait un
     * rôle vide (les permissions sont des données, pas codées en dur sur le rôle). Volontairement
     * PAS le DatabaseSeeder complet (trop lourd : catalogue de démo, comptes admin fixes) —
     * seulement permissions + profils.
     */
    protected $seeder = RoleSeeder::class;
}
