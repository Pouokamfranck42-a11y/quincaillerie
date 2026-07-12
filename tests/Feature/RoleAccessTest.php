<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'magasinier', 'caissier'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    public function test_cashier_cannot_access_catalogue_management(): void
    {
        $cashier = User::factory()->create();
        $cashier->assignRole('caissier');

        $this->actingAs($cashier)->get('/products')->assertForbidden();
        $this->actingAs($cashier)->get('/reports')->assertForbidden();
        $this->actingAs($cashier)->get('/users')->assertForbidden();
    }

    public function test_cashier_can_access_pos_and_customers(): void
    {
        $cashier = User::factory()->create();
        $cashier->assignRole('caissier');

        $this->actingAs($cashier)->get('/pos')->assertOk();
        $this->actingAs($cashier)->get('/customers')->assertOk();
    }

    public function test_admin_can_access_everything(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)->get('/products')->assertOk();
        $this->actingAs($admin)->get('/reports')->assertOk();
        $this->actingAs($admin)->get('/users')->assertOk();
        $this->actingAs($admin)->get('/pos')->assertOk();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }
}
