<?php

namespace Tests\Feature;

use App\Models\CashRegisterSession;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuoteConversionTest extends TestCase
{
    use RefreshDatabase;

    public function test_converting_a_quote_creates_a_sale_and_decrements_stock(): void
    {
        $user = User::factory()->create();
        $product = Product::create([
            'reference' => 'DEVIS-1',
            'name' => 'Produit devis',
            'purchase_price' => 100,
            'sale_price' => 200,
            'unit' => 'unité',
            'low_stock_threshold' => 5,
        ]);
        \App\Models\StockMovement::create([
            'product_id' => $product->id,
            'type' => 'entree',
            'quantity' => 10,
        ]);

        $quote = Quote::create([
            'user_id' => $user->id,
            'subtotal' => 400,
            'tax_rate' => 18,
            'tax_amount' => 72,
            'total' => 472,
            'status' => Quote::STATUS_BROUILLON,
        ]);
        $quote->lines()->create(['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 200]);
        $quote->load('lines.product');

        $session = CashRegisterSession::create([
            'user_id' => $user->id,
            'opened_at' => now(),
            'opening_amount' => 0,
            'status' => 'open',
        ]);

        $sale = $quote->convertToSale($session, $user->id, 'especes');

        $this->assertEquals(Quote::STATUS_CONVERTI, $quote->fresh()->status);
        $this->assertEquals($sale->id, $quote->fresh()->sale_id);
        $this->assertEquals(8, $product->fresh()->currentStock());
        $this->assertEquals(472, $sale->total);
    }

    public function test_cannot_convert_an_already_converted_quote_twice_via_controller(): void
    {
        $user = User::factory()->create();
        \Spatie\Permission\Models\Role::findOrCreate('admin', 'web');
        $user->assignRole('admin');

        $product = Product::create([
            'reference' => 'DEVIS-2',
            'name' => 'Produit',
            'purchase_price' => 100,
            'sale_price' => 200,
            'unit' => 'unité',
            'low_stock_threshold' => 5,
        ]);

        $quote = Quote::create([
            'user_id' => $user->id,
            'subtotal' => 200,
            'tax_rate' => 18,
            'tax_amount' => 36,
            'total' => 236,
            'status' => Quote::STATUS_CONVERTI,
            'sale_id' => null,
        ]);
        $quote->lines()->create(['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 200]);

        $response = $this->actingAs($user)->post(route('quotes.convert', $quote), ['payment_method' => 'especes']);

        $response->assertRedirect();
        $this->assertEquals(Quote::STATUS_CONVERTI, $quote->fresh()->status);
    }

    /**
     * Cousin du bug de double annulation déjà corrigé sur Order/Sale : sans verrou sur le
     * devis, deux conversions concurrentes (double-clic) passeraient toutes les deux le
     * contrôle de statut avant que l'une des deux n'écrive — deux Sale créées, stock déduit
     * deux fois pour un seul devis. convertToSale() verrouille désormais le devis avant de
     * relire son statut ; on simule la course en rappelant la méthode sur la même instance.
     */
    public function test_converting_the_same_quote_twice_in_a_row_does_not_double_deduct_stock(): void
    {
        $user = User::factory()->create();
        $product = Product::create([
            'reference' => 'DEVIS-3', 'name' => 'Produit devis course', 'purchase_price' => 100, 'sale_price' => 200,
            'unit' => 'unité', 'low_stock_threshold' => 5,
        ]);
        \App\Models\StockMovement::create(['product_id' => $product->id, 'type' => 'entree', 'quantity' => 10]);

        $quote = Quote::create([
            'user_id' => $user->id, 'subtotal' => 400, 'tax_rate' => 18, 'tax_amount' => 72, 'total' => 472,
            'status' => Quote::STATUS_BROUILLON,
        ]);
        $quote->lines()->create(['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 200]);
        $quote->load('lines.product');

        $session = CashRegisterSession::create([
            'user_id' => $user->id, 'opened_at' => now(), 'opening_amount' => 0, 'status' => 'open',
        ]);

        $quote->convertToSale($session, $user->id, 'especes');

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $quote->convertToSale($session, $user->id, 'especes');
    }

    public function test_converting_a_quote_to_an_order_reserves_stock_without_deducting_it(): void
    {
        $user = User::factory()->create();
        $customer = Customer::create(['name' => 'Client pro', 'type' => 'professionnel']);
        $product = Product::create([
            'reference' => 'DEVIS-4', 'name' => 'Produit devis commande', 'purchase_price' => 100, 'sale_price' => 200,
            'unit' => 'unité', 'low_stock_threshold' => 5,
        ]);
        \App\Models\StockMovement::create(['product_id' => $product->id, 'type' => 'entree', 'quantity' => 10]);

        $quote = Quote::create([
            'customer_id' => $customer->id, 'user_id' => $user->id, 'subtotal' => 400, 'tax_rate' => 18, 'tax_amount' => 72, 'total' => 472,
            'status' => Quote::STATUS_BROUILLON,
        ]);
        $quote->lines()->create(['product_id' => $product->id, 'quantity' => 3, 'unit_price' => 200]);
        $quote->load('lines.product');

        $order = $quote->convertToOrder('a_la_livraison', \App\Models\Order::FULFILLMENT_RETRAIT);

        $this->assertEquals(Quote::STATUS_CONVERTI, $quote->fresh()->status);
        $this->assertEquals($order->id, $quote->fresh()->order_id);
        $this->assertEquals(\App\Models\Order::STATUS_RESERVEE, $order->status);
        // stock physique inchangé (réservé, pas déduit) ; disponible réduit de 3.
        $this->assertEquals(10, $product->fresh()->currentStock());
        $this->assertEquals(7, $product->fresh()->availableStock());
    }

    public function test_converting_a_quote_without_a_customer_to_an_order_is_rejected(): void
    {
        $user = User::factory()->create();
        $product = Product::create([
            'reference' => 'DEVIS-5', 'name' => 'Produit', 'purchase_price' => 100, 'sale_price' => 200,
            'unit' => 'unité', 'low_stock_threshold' => 5,
        ]);

        $quote = Quote::create([
            'user_id' => $user->id, 'subtotal' => 200, 'tax_rate' => 18, 'tax_amount' => 36, 'total' => 236,
            'status' => Quote::STATUS_BROUILLON,
        ]);
        $quote->lines()->create(['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 200]);
        $quote->load('lines.product');

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $quote->convertToOrder('a_la_livraison');
    }

    public function test_staff_can_convert_a_quote_to_an_order_via_the_controller(): void
    {
        \Spatie\Permission\Models\Role::findOrCreate('admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('admin');
        $customer = Customer::create(['name' => 'Client pro', 'type' => 'professionnel']);
        $product = Product::create([
            'reference' => 'DEVIS-6', 'name' => 'Produit', 'purchase_price' => 100, 'sale_price' => 200,
            'unit' => 'unité', 'low_stock_threshold' => 5,
        ]);
        \App\Models\StockMovement::create(['product_id' => $product->id, 'type' => 'entree', 'quantity' => 10]);

        $quote = Quote::create([
            'customer_id' => $customer->id, 'user_id' => $user->id, 'subtotal' => 200, 'tax_rate' => 18, 'tax_amount' => 36, 'total' => 236,
            'status' => Quote::STATUS_BROUILLON,
        ]);
        $quote->lines()->create(['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 200]);

        $response = $this->actingAs($user)->post(route('quotes.convert-to-order', $quote), [
            'fulfillment_type' => 'retrait',
        ]);

        $response->assertRedirect(route('quotes.show', $quote));
        $this->assertEquals(Quote::STATUS_CONVERTI, $quote->fresh()->status);
        $this->assertNotNull($quote->fresh()->order_id);
    }

    public function test_quote_print_view_is_accessible(): void
    {
        \Spatie\Permission\Models\Role::findOrCreate('admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('admin');
        $product = Product::create([
            'reference' => 'DEVIS-7', 'name' => 'Produit imprimé', 'purchase_price' => 100, 'sale_price' => 200,
            'unit' => 'unité', 'low_stock_threshold' => 5,
        ]);

        $quote = Quote::create([
            'user_id' => $user->id, 'subtotal' => 200, 'tax_rate' => 18, 'tax_amount' => 36, 'total' => 236,
            'status' => Quote::STATUS_BROUILLON,
        ]);
        $quote->lines()->create(['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 200]);

        $response = $this->actingAs($user)->get(route('quotes.print', $quote));

        $response->assertOk();
        $response->assertSee('Produit imprimé');
        $response->assertSee('Devis #'.$quote->id);
    }
}
