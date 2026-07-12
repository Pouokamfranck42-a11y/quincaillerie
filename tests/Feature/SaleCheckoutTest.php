<?php

namespace Tests\Feature;

use App\Models\CashRegisterSession;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_creates_sale_lines_and_decrements_stock(): void
    {
        $user = User::factory()->create();
        $product = Product::create([
            'reference' => 'TEST-1',
            'name' => 'Produit test',
            'purchase_price' => 100,
            'sale_price' => 200,
            'unit' => 'unité',
            'low_stock_threshold' => 5,
        ]);
        StockMovement::create([
            'product_id' => $product->id,
            'type' => StockMovement::TYPE_ENTREE,
            'quantity' => 10,
        ]);

        $session = CashRegisterSession::create([
            'user_id' => $user->id,
            'opened_at' => now(),
            'opening_amount' => 0,
            'status' => 'open',
        ]);

        $sale = Sale::checkout(
            [['product' => $product, 'quantity' => 3]],
            $session,
            $user->id,
            null,
            'especes',
            18,
        );

        $this->assertEquals(600, $sale->subtotal);
        $this->assertEquals(108, $sale->tax_amount);
        $this->assertEquals(708, $sale->total);
        $this->assertCount(1, $sale->lines);
        $this->assertEquals(7, $product->fresh()->currentStock());
    }

    public function test_checkout_allows_stock_to_go_negative_instead_of_blocking_the_sale(): void
    {
        $user = User::factory()->create();
        $product = Product::create([
            'reference' => 'TEST-2',
            'name' => 'Produit sans stock',
            'purchase_price' => 100,
            'sale_price' => 200,
            'unit' => 'unité',
            'low_stock_threshold' => 5,
        ]);
        // aucun mouvement d'entrée : stock courant = 0

        $session = CashRegisterSession::create([
            'user_id' => $user->id,
            'opened_at' => now(),
            'opening_amount' => 0,
            'status' => 'open',
        ]);

        $sale = Sale::checkout(
            [['product' => $product, 'quantity' => 2]],
            $session,
            $user->id,
            null,
            'especes',
            18,
        );

        $this->assertNotNull($sale->id);
        $this->assertEquals(-2, $product->fresh()->currentStock());
    }
}
