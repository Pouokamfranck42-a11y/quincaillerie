<?php

namespace Tests\Feature;

use App\Models\CashRegisterSession;
use App\Models\Product;
use App\Models\ProductAssociation;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComputeCrossSellTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_computes_frequently_bought_together_pairs(): void
    {
        $cashier = User::factory()->create();
        $session = CashRegisterSession::create(['user_id' => $cashier->id, 'opening_amount' => 0, 'opened_at' => now()]);

        $nails = Product::create(['reference' => 'CLOU-1', 'name' => 'Clous', 'purchase_price' => 500, 'sale_price' => 800, 'unit' => 'boîte', 'low_stock_threshold' => 5]);
        $hammer = Product::create(['reference' => 'MART-1', 'name' => 'Marteau', 'purchase_price' => 3000, 'sale_price' => 4500, 'unit' => 'unité', 'low_stock_threshold' => 2]);
        $paint = Product::create(['reference' => 'PEINT-1', 'name' => 'Peinture', 'purchase_price' => 2000, 'sale_price' => 3000, 'unit' => 'pot', 'low_stock_threshold' => 5]);

        foreach ([1, 2] as $i) {
            Sale::checkout([
                ['product' => $nails, 'quantity' => 1],
                ['product' => $hammer, 'quantity' => 1],
            ], $session, $cashier->id, null, 'especes', 0);
        }
        Sale::checkout([
            ['product' => $paint, 'quantity' => 1],
        ], $session, $cashier->id, null, 'especes', 0);

        $this->artisan('app:compute-cross-sell')->assertExitCode(0);

        $association = ProductAssociation::where('product_id', $nails->id)->where('associated_product_id', $hammer->id)->first();
        $this->assertNotNull($association);
        $this->assertSame(2, $association->co_occurrence_count);

        $this->assertDatabaseMissing('product_associations', ['product_id' => $paint->id]);
    }
}
