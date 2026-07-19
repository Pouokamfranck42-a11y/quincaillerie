<?php

namespace Tests\Feature;

use App\Models\CashRegisterSession;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Phase 2 — programme de fidélité : points gagnés à l'achat (config('company.loyalty')),
 * rachetables en réduction. Ledger de mouvements (LoyaltyPointMovement), jamais un solde brut.
 */
class LoyaltyProgramTest extends TestCase
{
    use RefreshDatabase;

    private function checkout(Customer $customer, float $price, string $paymentMethod = 'especes', int $redeem = 0): Sale
    {
        $user = User::factory()->create();
        $product = Product::create([
            'reference' => 'FIDEL-'.uniqid(), 'name' => 'Produit fidélité', 'purchase_price' => 100, 'sale_price' => $price,
            'unit' => 'unité', 'low_stock_threshold' => 1,
        ]);
        StockMovement::create(['product_id' => $product->id, 'type' => 'entree', 'quantity' => 10]);

        $session = CashRegisterSession::create(['user_id' => $user->id, 'opened_at' => now(), 'opening_amount' => 0, 'status' => 'open']);

        return Sale::checkout([['product' => $product, 'quantity' => 1]], $session, $user->id, $customer->id, $paymentMethod, 0, null, $redeem);
    }

    public function test_a_cash_sale_earns_loyalty_points_for_the_customer(): void
    {
        $customer = Customer::create(['name' => 'Client fidèle', 'credit_limit' => 100000, 'payment_terms_days' => 30]);

        // earn_per_fcfa par défaut = 1000 : 5000 FCFA -> 5 points.
        $this->checkout($customer, 5000);

        $this->assertSame(5, $customer->fresh()->loyaltyPoints());
    }

    public function test_a_credit_sale_does_not_earn_points_until_paid(): void
    {
        $customer = Customer::create(['name' => 'Client crédit', 'credit_limit' => 100000, 'payment_terms_days' => 30]);

        $this->checkout($customer, 5000, 'credit');

        $this->assertSame(0, $customer->fresh()->loyaltyPoints());
    }

    public function test_redeeming_points_reduces_the_sale_total(): void
    {
        $customer = Customer::create(['name' => 'Client points', 'credit_limit' => 0, 'payment_terms_days' => 0]);
        \App\Models\LoyaltyPointMovement::create(['customer_id' => $customer->id, 'points' => 20, 'reason' => 'Test seed']);

        // redeem_value par défaut = 10 FCFA/point : 5 points = 50 FCFA de réduction.
        $sale = $this->checkout($customer, 1000, 'especes', redeem: 5);

        $this->assertEquals(950, $sale->total); // 1000 - 50
        $this->assertEquals(50, $sale->loyalty_discount);
        $this->assertSame(5, $sale->loyalty_points_redeemed);
        // solde : +20 initial, -5 rachetés, +0 gagnés (950/1000 arrondi à 0 point) = 15.
        $this->assertSame(15, $customer->fresh()->loyaltyPoints());
    }

    public function test_redeeming_more_points_than_available_is_rejected(): void
    {
        $customer = Customer::create(['name' => 'Client points insuffisants', 'credit_limit' => 0, 'payment_terms_days' => 0]);
        \App\Models\LoyaltyPointMovement::create(['customer_id' => $customer->id, 'points' => 3, 'reason' => 'Test seed']);

        $this->expectException(ValidationException::class);
        $this->checkout($customer, 1000, 'especes', redeem: 10);
    }

    public function test_walk_in_sale_without_a_customer_earns_nothing(): void
    {
        $user = User::factory()->create();
        $product = Product::create([
            'reference' => 'FIDEL-PASSAGE', 'name' => 'Produit', 'purchase_price' => 100, 'sale_price' => 5000,
            'unit' => 'unité', 'low_stock_threshold' => 1,
        ]);
        StockMovement::create(['product_id' => $product->id, 'type' => 'entree', 'quantity' => 10]);
        $session = CashRegisterSession::create(['user_id' => $user->id, 'opened_at' => now(), 'opening_amount' => 0, 'status' => 'open']);

        // Ne doit pas planter même sans client identifié — juste rien à créditer.
        Sale::checkout([['product' => $product, 'quantity' => 1]], $session, $user->id, null, 'especes', 0);
        $this->addToAssertionCount(1);
    }

    public function test_confirming_payment_of_an_online_order_earns_loyalty_points_too(): void
    {
        $customer = Customer::create(['name' => 'Client web fidèle', 'email' => 'fidele@example.com']);
        $product = Product::create([
            'reference' => 'FIDEL-WEB', 'name' => 'Produit web', 'purchase_price' => 100, 'sale_price' => 2000,
            'unit' => 'unité', 'low_stock_threshold' => 1,
        ]);
        StockMovement::create(['product_id' => $product->id, 'type' => 'entree', 'quantity' => 10]);

        $order = Order::place([['product' => $product, 'quantity' => 1]], $customer->id, 'mobile_money_mtn');
        $order->confirmPayment();

        // 2000 FCFA -> 2 points, même règle que le comptoir.
        $this->assertSame(2, $customer->fresh()->loyaltyPoints());
    }
}
