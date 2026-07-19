<?php

namespace Tests\Feature;

use App\Models\CashRegisterSession;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Phase 2 — vente à la découpe (câble, tuyau, chaîne...) : quantité décimale contrainte à
 * un pas explicite, en caisse comme en boutique en ligne (published_online est désormais
 * publiable normalement pour ces articles, plus de restriction "articles simples uniquement").
 */
class CutSellingTest extends TestCase
{
    use RefreshDatabase;

    private function cutProduct(float $step = 0.5, float $stock = 50): Product
    {
        $product = Product::create([
            'reference' => 'CABLE-1', 'name' => 'Câble souple', 'purchase_price' => 100, 'sale_price' => 200,
            'unit' => 'mètre', 'low_stock_threshold' => 5, 'sold_by_cut' => true, 'cut_step' => $step,
            'published_online' => true, 'active' => true,
        ]);
        StockMovement::create(['product_id' => $product->id, 'type' => 'entree', 'quantity' => $stock]);

        return $product;
    }

    public function test_a_quantity_that_is_a_multiple_of_the_step_is_accepted(): void
    {
        $product = $this->cutProduct(step: 0.5);

        $product->assertValidSaleQuantity(1.5); // ne doit rien lever
        $this->addToAssertionCount(1);
    }

    public function test_a_quantity_that_is_not_a_multiple_of_the_step_is_rejected(): void
    {
        $product = $this->cutProduct(step: 0.5);

        $this->expectException(ValidationException::class);
        $product->assertValidSaleQuantity(1.3);
    }

    public function test_non_cut_products_are_unaffected_by_the_step_check(): void
    {
        $product = Product::create([
            'reference' => 'MARTEAU-1', 'name' => 'Marteau', 'purchase_price' => 100, 'sale_price' => 200,
            'unit' => 'unité', 'low_stock_threshold' => 5,
        ]);

        // sold_by_cut par défaut à false : aucune contrainte, comportement inchangé.
        $product->assertValidSaleQuantity(2.37);
        $this->addToAssertionCount(1);
    }

    public function test_pos_checkout_rejects_a_quantity_off_the_cutting_step(): void
    {
        $product = $this->cutProduct(step: 0.5);
        $session = CashRegisterSession::create([
            'user_id' => User::factory()->create()->id, 'opened_at' => now(), 'opening_amount' => 0, 'status' => 'open',
        ]);

        $this->expectException(ValidationException::class);
        \App\Models\Sale::checkout(
            [['product' => $product, 'quantity' => 1.3]],
            $session, $session->user_id, null, 'especes', 0,
        );
    }

    public function test_online_order_rejects_a_quantity_off_the_cutting_step(): void
    {
        $product = $this->cutProduct(step: 0.5);
        $customer = Customer::create(['name' => 'Client web', 'email' => 'cable@example.com']);

        $this->expectException(ValidationException::class);
        Order::place([['product' => $product, 'quantity' => 1.3]], $customer->id, 'mobile_money_mtn');
    }

    public function test_online_order_accepts_a_valid_cutting_step_quantity(): void
    {
        $product = $this->cutProduct(step: 0.5);
        $customer = Customer::create(['name' => 'Client web', 'email' => 'cable2@example.com']);

        $order = Order::place([['product' => $product, 'quantity' => 2.5]], $customer->id, 'mobile_money_mtn');

        $this->assertSame(1, $order->lines->count());
        $this->assertSame(2.5, (float) $order->lines->first()->quantity);
    }

    public function test_shop_cart_rejects_a_quantity_off_the_cutting_step(): void
    {
        $product = $this->cutProduct(step: 0.5);
        $customer = Customer::create(['name' => 'Client web', 'email' => 'cable3@example.com', 'password' => 'motdepasse123']);

        $response = $this->actingAs($customer, 'customer')->post(route('shop.cart.store'), [
            'product_id' => $product->id,
            'quantity' => 1.3,
        ]);

        $response->assertSessionHasErrors('quantity');
    }

    public function test_shop_cart_accepts_a_valid_cutting_step_quantity(): void
    {
        $product = $this->cutProduct(step: 0.5);
        $customer = Customer::create(['name' => 'Client web', 'email' => 'cable4@example.com', 'password' => 'motdepasse123']);

        $response = $this->actingAs($customer, 'customer')->post(route('shop.cart.store'), [
            'product_id' => $product->id,
            'quantity' => 1.5,
        ]);

        $response->assertSessionDoesntHaveErrors();
        $response->assertRedirect(route('shop.cart.index'));
    }
}
