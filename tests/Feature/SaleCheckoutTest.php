<?php

namespace Tests\Feature;

use App\Models\CashRegisterSession;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
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

    /**
     * Depuis le noyau de stock unifié (Phase 3), une vente qui dépasse le disponible est
     * bloquée — plus jamais de stock négatif silencieux. Remplace l'ancien comportement
     * assumé (et testé comme tel) où la vente passait quand même.
     */
    public function test_checkout_blocks_the_sale_when_stock_is_insufficient(): void
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

        try {
            Sale::checkout(
                [['product' => $product, 'quantity' => 2]],
                $session,
                $user->id,
                null,
                'especes',
                18,
            );
            $this->fail('Une ValidationException était attendue pour stock insuffisant.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('stock', $e->errors());
        }

        $this->assertEquals(0, $product->fresh()->currentStock());
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_checkout_computes_change_due_when_amount_tendered_is_sufficient(): void
    {
        $user = User::factory()->create();
        $product = Product::create([
            'reference' => 'TEST-3', 'name' => 'Produit test', 'purchase_price' => 100, 'sale_price' => 200,
            'unit' => 'unité', 'low_stock_threshold' => 5,
        ]);
        StockMovement::create(['product_id' => $product->id, 'type' => StockMovement::TYPE_ENTREE, 'quantity' => 10]);
        $session = CashRegisterSession::create(['user_id' => $user->id, 'opened_at' => now(), 'opening_amount' => 0, 'status' => 'open']);

        $sale = Sale::checkout([['product' => $product, 'quantity' => 1]], $session, $user->id, null, 'especes', 0, 5000);

        $this->assertEquals(5000, $sale->amount_tendered);
        $this->assertEquals(4800, $sale->change_due);
    }

    public function test_checkout_rejects_insufficient_amount_tendered(): void
    {
        $user = User::factory()->create();
        $product = Product::create([
            'reference' => 'TEST-4', 'name' => 'Produit test', 'purchase_price' => 100, 'sale_price' => 200,
            'unit' => 'unité', 'low_stock_threshold' => 5,
        ]);
        StockMovement::create(['product_id' => $product->id, 'type' => StockMovement::TYPE_ENTREE, 'quantity' => 10]);
        $session = CashRegisterSession::create(['user_id' => $user->id, 'opened_at' => now(), 'opening_amount' => 0, 'status' => 'open']);

        try {
            Sale::checkout([['product' => $product, 'quantity' => 1]], $session, $user->id, null, 'especes', 0, 100);
            $this->fail('Une ValidationException était attendue pour un montant reçu insuffisant.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('amount_tendered', $e->errors());
        }

        $this->assertDatabaseCount('sales', 0);
    }

    public function test_cancel_reintegrates_stock_and_marks_sale_cancelled(): void
    {
        $user = User::factory()->create();
        $product = Product::create([
            'reference' => 'TEST-5', 'name' => 'Produit test', 'purchase_price' => 100, 'sale_price' => 200,
            'unit' => 'unité', 'low_stock_threshold' => 5,
        ]);
        StockMovement::create(['product_id' => $product->id, 'type' => StockMovement::TYPE_ENTREE, 'quantity' => 10]);
        $session = CashRegisterSession::create(['user_id' => $user->id, 'opened_at' => now(), 'opening_amount' => 0, 'status' => 'open']);
        $sale = Sale::checkout([['product' => $product, 'quantity' => 4]], $session, $user->id, null, 'especes', 0);

        $this->assertEquals(6, $product->fresh()->currentStock());

        $sale->cancel($user->id, 'Erreur de saisie');

        $this->assertEquals(10, $product->fresh()->currentStock());
        $this->assertEquals(Sale::STATUS_CANCELLED, $sale->fresh()->status);
        $this->assertNotNull($sale->fresh()->cancelled_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'sale.cancelled', 'auditable_id' => $sale->id]);

        // Idempotent : un second appel ne réintègre pas de stock supplémentaire.
        $sale->cancel($user->id);
        $this->assertEquals(10, $product->fresh()->currentStock());
    }

    public function test_cancel_is_rejected_once_the_cash_register_session_is_closed(): void
    {
        $user = User::factory()->create();
        $product = Product::create([
            'reference' => 'TEST-6', 'name' => 'Produit test', 'purchase_price' => 100, 'sale_price' => 200,
            'unit' => 'unité', 'low_stock_threshold' => 5,
        ]);
        StockMovement::create(['product_id' => $product->id, 'type' => StockMovement::TYPE_ENTREE, 'quantity' => 10]);
        $session = CashRegisterSession::create(['user_id' => $user->id, 'opened_at' => now(), 'opening_amount' => 0, 'status' => 'open']);
        $sale = Sale::checkout([['product' => $product, 'quantity' => 2]], $session, $user->id, null, 'especes', 0);

        $session->update(['status' => 'closed', 'closed_at' => now(), 'closing_amount' => 0]);

        try {
            $sale->cancel($user->id);
            $this->fail('Une ValidationException était attendue : session de caisse fermée.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('sale', $e->errors());
        }

        $this->assertEquals(Sale::STATUS_COMPLETED, $sale->fresh()->status);
    }
}
