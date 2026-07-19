<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Crée 3 profils de départ (Admin/Magasinier/Caissier) reproduisant exactement l'accès des
 * anciens rôles codés en dur — pour que les comptes existants gardent leurs accès immédiatement
 * après la bascule vers les permissions dynamiques. Ce ne sont plus que des données : l'admin
 * peut ensuite librement renommer, modifier ou supprimer ces profils depuis /roles.
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // Auto-suffisant : les permissions doivent exister avant qu'un profil puisse les recevoir
        // (idempotent — sans effet si déjà seedées par DatabaseSeeder).
        $this->call(PermissionSeeder::class);

        $admin = Role::findOrCreate('admin', 'web');
        $admin->syncPermissions(Permission::where('guard_name', 'web')->pluck('name'));

        $magasinier = Role::findOrCreate('magasinier', 'web');
        $magasinier->syncPermissions([
            'produits.voir', 'produits.creer', 'produits.modifier', 'produits.supprimer', 'produits.importer',
            'prix.modifier',
            'catalogue.voir', 'catalogue.gerer',
            'fournisseurs.voir', 'fournisseurs.gerer',
            'stock.voir', 'stock.ajuster', 'stock.inventaire', 'stock.transferer',
            'achats.voir', 'achats.gerer',
            'ia.chatbot',
        ]);

        $caissier = Role::findOrCreate('caissier', 'web');
        $caissier->syncPermissions([
            'ventes.creer', 'ventes.annuler', 'ventes.historique',
            'caisse.encaisser', 'caisse.cloturer', 'caisse.journal',
            'clients.voir', 'clients.gerer',
            'ecommerce.commandes',
            'sav.gerer',
            'ia.chatbot',
        ]);
    }
}
