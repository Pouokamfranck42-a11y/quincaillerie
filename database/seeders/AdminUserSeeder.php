<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds. Comptes de démonstration — mots de passe à changer avant tout usage réel.
     */
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@quincaillerie.local'],
            ['name' => 'Administrateur', 'password' => 'admin1234']
        );
        $admin->syncRoles(['admin']);

        $magasinier = User::firstOrCreate(
            ['email' => 'magasinier@quincaillerie.local'],
            ['name' => 'Magasinier Démo', 'password' => 'magasinier1234']
        );
        $magasinier->syncRoles(['magasinier']);

        $caissier = User::firstOrCreate(
            ['email' => 'caissier@quincaillerie.local'],
            ['name' => 'Caissier Démo', 'password' => 'caissier1234']
        );
        $caissier->syncRoles(['caissier']);
    }
}
