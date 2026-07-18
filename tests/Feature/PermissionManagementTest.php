<?php

namespace Tests\Feature;

use App\Models\CashRegisterSession;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Permissions dynamiques (remplace les rôles figés) — chaque compte n'a que les permissions
 * qui lui sont explicitement attribuées (directement ou via un profil), 100% en base, aucun
 * nom de rôle codé en dur. Le seeder de test (voir Tests\TestCase::$seeder) fournit déjà les
 * 34 permissions + 3 profils de départ (Admin/Magasinier/Caissier).
 */
class PermissionManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_with_only_produits_voir_can_list_but_not_create_products(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('produits.voir');

        $this->actingAs($user)->get('/products')->assertOk();
        $this->actingAs($user)->get('/products/create')->assertForbidden();
        $this->actingAs($user)->post('/products', [])->assertForbidden();
    }

    public function test_updating_a_product_without_prix_modifier_leaves_the_price_unchanged(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['produits.voir', 'produits.modifier']);

        $category = Category::create(['name' => 'Test']);
        $product = Product::create([
            'reference' => 'PERM-1', 'name' => 'Produit test', 'category_id' => $category->id,
            'purchase_price' => 100, 'sale_price' => 200, 'unit' => 'unité', 'low_stock_threshold' => 5,
        ]);

        $this->actingAs($user)->put("/products/{$product->id}", [
            'reference' => 'PERM-1', 'name' => 'Produit renommé', 'category_id' => $category->id,
            'purchase_price' => 999, 'sale_price' => 999,
            'unit' => 'unité', 'sale_unit_factor' => 1, 'purchase_unit_factor' => 1,
            'low_stock_threshold' => 5, 'security_stock' => 0,
        ])->assertRedirect('/products');

        $product->refresh();
        $this->assertSame('Produit renommé', $product->name);
        $this->assertEquals(200, (float) $product->sale_price);

        $user->givePermissionTo('prix.modifier');

        $this->actingAs($user)->put("/products/{$product->id}", [
            'reference' => 'PERM-1', 'name' => 'Produit renommé', 'category_id' => $category->id,
            'purchase_price' => 999, 'sale_price' => 999,
            'unit' => 'unité', 'sale_unit_factor' => 1, 'purchase_unit_factor' => 1,
            'low_stock_threshold' => 5, 'security_stock' => 0,
        ]);

        $this->assertEquals(999, (float) $product->fresh()->sale_price);
    }

    public function test_admin_creates_a_custom_profile_and_assigns_it_to_a_user(): void
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo(['utilisateurs.permissions', 'utilisateurs.creer']);

        $this->actingAs($admin)->post('/roles', [
            'name' => 'Vendeur',
            'permissions' => ['ventes.creer', 'caisse.encaisser'],
        ])->assertRedirect('/roles');

        $role = Role::where('name', 'Vendeur')->firstOrFail();
        $this->assertEqualsCanonicalizing(['ventes.creer', 'caisse.encaisser'], $role->permissions->pluck('name')->all());

        $seller = User::factory()->create();
        $this->actingAs($admin)->put("/users/{$seller->id}", [
            'name' => $seller->name, 'email' => $seller->email, 'role' => 'Vendeur',
        ])->assertRedirect('/users');

        $seller->refresh();
        $this->assertTrue($seller->can('ventes.creer'));
        $this->assertTrue($seller->can('caisse.encaisser'));
        $this->assertFalse($seller->can('produits.voir'));
    }

    public function test_last_account_with_utilisateurs_permissions_cannot_be_stripped_of_it(): void
    {
        $sole = User::factory()->create();
        $sole->givePermissionTo(['utilisateurs.permissions', 'utilisateurs.creer']);

        $this->assertSame(1, User::countActiveUsersWithPermission('utilisateurs.permissions'));

        $response = $this->actingAs($sole)->put("/users/{$sole->id}", [
            'name' => $sole->name, 'email' => $sole->email, 'role' => '', 'permissions' => [],
        ]);

        $response->assertSessionHasErrors('permissions');
        $this->assertTrue($sole->fresh()->can('utilisateurs.permissions'));
    }

    public function test_last_account_with_utilisateurs_permissions_cannot_be_deleted(): void
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo(['utilisateurs.permissions', 'utilisateurs.creer']);
        $other = User::factory()->create();

        $response = $this->actingAs($admin)->delete("/users/{$admin->id}");

        // Bloqué soit par la garde "pas d'auto-suppression" soit par le garde-fou anti-verrouillage —
        // dans les deux cas, le compte doit rester présent et fonctionnel.
        $this->assertNotNull($admin->fresh());
        $this->assertTrue($admin->fresh()->can('utilisateurs.permissions'));
    }

    public function test_editing_a_role_that_would_zero_out_permission_managers_is_rejected(): void
    {
        $admin = User::factory()->create();
        $role = Role::create(['name' => 'SeulGestionnaire', 'guard_name' => 'web']);
        $role->givePermissionTo('utilisateurs.permissions');
        $admin->assignRole($role);

        $this->assertSame(1, User::countActiveUsersWithPermission('utilisateurs.permissions'));

        $response = $this->actingAs($admin)->put("/roles/{$role->id}", [
            'name' => 'SeulGestionnaire',
            'permissions' => [],
        ]);

        $response->assertSessionHasErrors('permissions');
        $this->assertTrue($role->fresh()->hasPermissionTo('utilisateurs.permissions'));
    }

    public function test_denied_access_attempt_is_logged_to_the_audit_trail(): void
    {
        $user = User::factory()->create(); // aucune permission

        $this->actingAs($user)->get('/products')->assertForbidden();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'access.denied',
            'auditable_id' => $user->id,
            'auditable_type' => User::class,
        ]);
    }

    public function test_changing_a_user_permissions_is_recorded_in_the_audit_trail(): void
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo('utilisateurs.creer');
        $target = User::factory()->create();

        $this->actingAs($admin)->put("/users/{$target->id}", [
            'name' => $target->name, 'email' => $target->email, 'permissions' => ['clients.voir'],
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.permissions_changed',
            'auditable_id' => $target->id,
        ]);
    }

    public function test_pos_route_requires_caisse_encaisser_permission(): void
    {
        $noPerm = User::factory()->create();
        $this->actingAs($noPerm)->get('/pos')->assertForbidden();

        $withPerm = User::factory()->create();
        $withPerm->givePermissionTo('caisse.encaisser');
        CashRegisterSession::create(['user_id' => $withPerm->id, 'opened_at' => now(), 'opening_amount' => 0, 'status' => 'open']);
        $this->actingAs($withPerm)->get('/pos')->assertOk();
    }
}
