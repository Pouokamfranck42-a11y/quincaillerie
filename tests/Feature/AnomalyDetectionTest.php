<?php

namespace Tests\Feature;

use App\Models\CashRegisterSession;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Notifications\AnomalyDetected;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AnomalyDetectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'caissier'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    public function test_selling_below_cost_notifies_admins(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $cashier = User::factory()->create();
        $cashier->assignRole('caissier');

        $product = Product::create([
            'reference' => 'PERTE-1', 'name' => 'Produit vendu à perte', 'purchase_price' => 1000, 'sale_price' => 500,
            'unit' => 'unité', 'low_stock_threshold' => 5,
        ]);
        $session = CashRegisterSession::create(['user_id' => $cashier->id, 'opening_amount' => 0, 'opened_at' => now()]);

        Sale::checkout([
            ['product' => $product, 'quantity' => 1],
        ], $session, $cashier->id, null, 'especes', 0);

        $this->assertCount(1, $admin->fresh()->notifications()->where('type', AnomalyDetected::class)->get());
    }

    public function test_normal_sale_does_not_notify(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $cashier = User::factory()->create();
        $cashier->assignRole('caissier');

        $product = Product::create([
            'reference' => 'OK-1', 'name' => 'Produit normal', 'purchase_price' => 1000, 'sale_price' => 1500,
            'unit' => 'unité', 'low_stock_threshold' => 5,
        ]);
        $session = CashRegisterSession::create(['user_id' => $cashier->id, 'opening_amount' => 0, 'opened_at' => now()]);

        Sale::checkout([
            ['product' => $product, 'quantity' => 1],
        ], $session, $cashier->id, null, 'especes', 0);

        $this->assertCount(0, $admin->fresh()->notifications()->where('type', AnomalyDetected::class)->get());
    }

    public function test_large_price_jump_notifies_admins(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $product = Product::create([
            'reference' => 'PRIX-1', 'name' => 'Produit repricé', 'purchase_price' => 1000, 'sale_price' => 1500,
            'unit' => 'unité', 'low_stock_threshold' => 5,
        ]);

        $this->actingAs($admin)->put(route('products.update', $product), [
            'reference' => 'PRIX-1', 'name' => 'Produit repricé', 'purchase_price' => 1000, 'sale_price' => 4000,
            'unit' => 'unité', 'low_stock_threshold' => 5, 'security_stock' => 0,
            'sale_unit_factor' => 1, 'purchase_unit_factor' => 1,
        ]);

        $this->assertCount(1, $admin->fresh()->notifications()->where('type', AnomalyDetected::class)->get());
    }
}
