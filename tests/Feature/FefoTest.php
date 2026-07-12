<?php

namespace Tests\Feature;

use App\Models\CashRegisterSession;
use App\Models\Product;
use App\Models\ProductLot;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FefoTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_consumes_the_soonest_expiring_lot_first(): void
    {
        $user = User::factory()->create();
        $product = Product::create([
            'reference' => 'FEFO-1', 'name' => 'Peinture', 'purchase_price' => 5000, 'sale_price' => 8000,
            'unit' => 'bidon', 'low_stock_threshold' => 2, 'tracks_lots' => true,
        ]);

        $lotSoon = ProductLot::create(['product_id' => $product->id, 'lot_number' => 'A', 'expiry_date' => now()->addDays(10)]);
        $lotLater = ProductLot::create(['product_id' => $product->id, 'lot_number' => 'B', 'expiry_date' => now()->addDays(90)]);

        StockMovement::create(['product_id' => $product->id, 'lot_id' => $lotSoon->id, 'type' => 'entree', 'quantity' => 5]);
        StockMovement::create(['product_id' => $product->id, 'lot_id' => $lotLater->id, 'type' => 'entree', 'quantity' => 5]);

        $this->assertEquals($lotSoon->id, $product->nextFefoLot()->id);

        $session = CashRegisterSession::create(['user_id' => $user->id, 'opened_at' => now(), 'opening_amount' => 0, 'status' => 'open']);
        $sale = Sale::checkout([['product' => $product, 'quantity' => 2]], $session, $user->id, null, 'especes', 0);

        $this->assertEquals($lotSoon->id, $sale->lines->first()->lot_id);
        $this->assertEquals(3, $lotSoon->fresh()->currentQuantity());
        $this->assertEquals(5, $lotLater->fresh()->currentQuantity());
    }
}
