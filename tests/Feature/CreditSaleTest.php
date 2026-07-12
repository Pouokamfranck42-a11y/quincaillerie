<?php

namespace Tests\Feature;

use App\Models\CashRegisterSession;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CreditSaleTest extends TestCase
{
    use RefreshDatabase;

    private function makeSession(User $user): CashRegisterSession
    {
        return CashRegisterSession::create([
            'user_id' => $user->id,
            'opened_at' => now(),
            'opening_amount' => 0,
            'status' => 'open',
        ]);
    }

    public function test_credit_sale_within_limit_succeeds_and_marks_sale_as_due(): void
    {
        $user = User::factory()->create();
        $customer = Customer::create([
            'name' => 'Client Pro', 'type' => 'professionnel', 'credit_limit' => 100000, 'payment_terms_days' => 30,
        ]);
        $product = Product::create([
            'reference' => 'CRED-1', 'name' => 'Produit', 'purchase_price' => 100, 'sale_price' => 200,
            'unit' => 'unité', 'low_stock_threshold' => 5,
        ]);
        StockMovement::create(['product_id' => $product->id, 'type' => 'entree', 'quantity' => 50]);
        $session = $this->makeSession($user);

        $sale = Sale::checkout(
            [['product' => $product, 'quantity' => 10]],
            $session, $user->id, $customer->id, 'credit', 18,
        );

        $this->assertEquals('due', $sale->payment_status);
        $this->assertEquals(0, (float) $sale->paid_amount);
        $this->assertNotNull($sale->due_date);
        $this->assertEquals((float) $sale->total, $customer->outstandingBalance());
    }

    public function test_credit_sale_beyond_limit_is_blocked(): void
    {
        $user = User::factory()->create();
        $customer = Customer::create([
            'name' => 'Client Pro Limité', 'type' => 'professionnel', 'credit_limit' => 1000, 'payment_terms_days' => 30,
        ]);
        $product = Product::create([
            'reference' => 'CRED-2', 'name' => 'Produit cher', 'purchase_price' => 100, 'sale_price' => 5000,
            'unit' => 'unité', 'low_stock_threshold' => 5,
        ]);
        StockMovement::create(['product_id' => $product->id, 'type' => 'entree', 'quantity' => 50]);
        $session = $this->makeSession($user);

        $this->expectException(ValidationException::class);

        Sale::checkout(
            [['product' => $product, 'quantity' => 1]],
            $session, $user->id, $customer->id, 'credit', 18,
        );
    }

    public function test_cash_payment_ignores_credit_limit(): void
    {
        $user = User::factory()->create();
        $customer = Customer::create([
            'name' => 'Client Pro Sans Credit', 'type' => 'professionnel', 'credit_limit' => 0, 'payment_terms_days' => 30,
        ]);
        $product = Product::create([
            'reference' => 'CRED-3', 'name' => 'Produit', 'purchase_price' => 100, 'sale_price' => 5000,
            'unit' => 'unité', 'low_stock_threshold' => 5,
        ]);
        StockMovement::create(['product_id' => $product->id, 'type' => 'entree', 'quantity' => 50]);
        $session = $this->makeSession($user);

        $sale = Sale::checkout(
            [['product' => $product, 'quantity' => 1]],
            $session, $user->id, $customer->id, 'especes', 18,
        );

        $this->assertEquals('paid', $sale->payment_status);
    }

    public function test_pro_price_is_applied_when_customer_is_professionnel(): void
    {
        $user = User::factory()->create();
        $customer = Customer::create(['name' => 'Client Pro', 'type' => 'professionnel', 'credit_limit' => 0]);
        $product = Product::create([
            'reference' => 'CRED-4', 'name' => 'Produit', 'purchase_price' => 100, 'sale_price' => 200,
            'pro_price' => 150, 'unit' => 'unité', 'low_stock_threshold' => 5,
        ]);
        StockMovement::create(['product_id' => $product->id, 'type' => 'entree', 'quantity' => 50]);
        $session = $this->makeSession($user);

        $sale = Sale::checkout(
            [['product' => $product, 'quantity' => 2]],
            $session, $user->id, $customer->id, 'especes', 0,
        );

        $this->assertEquals(300, (float) $sale->subtotal);
    }
}
