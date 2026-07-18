<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Phase 4 — la machine à états de la commande. Chaque transition doit appeler le
 * service central de la Phase 3 (jamais d'écriture directe dans stock_movements ici).
 */
class OrderStateMachineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Warehouse::create(['name' => 'Magasin principal', 'is_default' => true]);
    }

    private function product(float $stock = 10): Product
    {
        $product = Product::create([
            'reference' => 'CMD-1', 'name' => 'Produit commande', 'purchase_price' => 100, 'sale_price' => 200,
            'unit' => 'unité', 'low_stock_threshold' => 5,
        ]);
        StockMovement::create(['product_id' => $product->id, 'type' => StockMovement::TYPE_ENTREE, 'quantity' => $stock]);

        return $product;
    }

    private function customer(): Customer
    {
        return Customer::create(['name' => 'Client web', 'email' => 'client@example.com']);
    }

    public function test_place_reserves_stock_without_deducting_it(): void
    {
        $product = $this->product(10);
        $customer = $this->customer();

        $order = Order::place([['product' => $product, 'quantity' => 3]], $customer->id, 'mobile_money_mtn');

        $this->assertSame(Order::STATUS_RESERVEE, $order->status);
        $this->assertCount(1, $order->lines);
        $this->assertSame(10.0, $product->fresh()->currentStock());
        $this->assertSame(7.0, $product->fresh()->availableStock());
        $this->assertSame(1, Reservation::where('reservable_type', Order::class)->where('reservable_id', $order->id)->count());
    }

    public function test_place_fails_and_persists_nothing_when_stock_is_insufficient(): void
    {
        $product = $this->product(1);
        $customer = $this->customer();

        try {
            Order::place([['product' => $product, 'quantity' => 5]], $customer->id, 'mobile_money_mtn');
            $this->fail('ValidationException attendue.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('stock', $e->errors());
        }

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_confirm_payment_deducts_stock_and_generates_a_sale(): void
    {
        $product = $this->product(10);
        $customer = $this->customer();
        $order = Order::place([['product' => $product, 'quantity' => 3]], $customer->id, 'mobile_money_mtn', taxRate: 18);

        $order->confirmPayment();
        $order->refresh();

        $this->assertSame(Order::STATUS_PAYEE, $order->status);
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame(7.0, $product->fresh()->currentStock());
        $this->assertSame(7.0, $product->fresh()->availableStock());

        $sale = Sale::find($order->sale_id);
        $this->assertNotNull($sale);
        $this->assertNull($sale->cash_register_session_id);
        $this->assertNull($sale->user_id);
        $this->assertEquals($order->total, $sale->total);
        $this->assertCount(1, $sale->lines);
    }

    public function test_confirm_payment_twice_is_rejected_by_the_transition_guard(): void
    {
        $product = $this->product(10);
        $customer = $this->customer();
        $order = Order::place([['product' => $product, 'quantity' => 1]], $customer->id, 'mobile_money_mtn');
        $order->confirmPayment();

        $this->expectException(ValidationException::class);
        $order->fresh()->confirmPayment();
    }

    public function test_full_happy_path_delivery(): void
    {
        $product = $this->product(10);
        $customer = $this->customer();
        $order = Order::place([['product' => $product, 'quantity' => 1]], $customer->id, 'mobile_money_mtn', fulfillmentType: Order::FULFILLMENT_LIVRAISON);

        $order = $order->confirmPayment();
        $order = $order->startPreparation();
        $this->assertSame(Order::STATUS_PREPARATION, $order->status);

        $order = $order->markReady();
        $this->assertSame(Order::STATUS_PRETE, $order->status);

        $order = $order->deliver();
        $this->assertSame(Order::STATUS_LIVREE, $order->status);
        $this->assertNotNull($order->delivered_at);
    }

    public function test_pickup_is_rejected_for_a_delivery_order(): void
    {
        $product = $this->product(10);
        $customer = $this->customer();
        $order = Order::place([['product' => $product, 'quantity' => 1]], $customer->id, 'mobile_money_mtn', fulfillmentType: Order::FULFILLMENT_LIVRAISON);
        $order = $order->confirmPayment()->startPreparation()->markReady();

        $this->expectException(ValidationException::class);
        $order->pickUp();
    }

    public function test_cancelling_before_payment_releases_the_reservation_without_any_stock_movement(): void
    {
        $product = $this->product(10);
        $customer = $this->customer();
        $order = Order::place([['product' => $product, 'quantity' => 4]], $customer->id, 'mobile_money_mtn');

        $order->cancel(reason: 'Client a annulé');
        $order->refresh();

        $this->assertSame(Order::STATUS_ANNULEE, $order->status);
        $this->assertNotNull($order->cancelled_at);
        $this->assertSame(Reservation::STATUS_RELEASED, $order->reservations()->first()->status);
        $this->assertSame(10.0, $product->fresh()->availableStock());
        $this->assertDatabaseCount('stock_movements', 1); // uniquement l'entrée initiale du test
    }

    public function test_cancelling_after_payment_reintegrates_physical_stock(): void
    {
        $product = $this->product(10);
        $customer = $this->customer();
        $order = Order::place([['product' => $product, 'quantity' => 4]], $customer->id, 'mobile_money_mtn');
        $order = $order->confirmPayment();

        $this->assertSame(6.0, $product->fresh()->currentStock());

        $order->cancel(reason: 'Rupture logistique');

        $this->assertSame(Order::STATUS_ANNULEE, $order->fresh()->status);
        $this->assertSame(10.0, $product->fresh()->currentStock());

        // La Sale née de confirmPayment() doit être verrouillée, sinon le bouton "Retourner"
        // de la fiche Vente permet de réintégrer une seconde fois ce même stock (double crédit).
        $sale = $order->fresh()->sale;
        $this->assertSame(Sale::STATUS_CANCELLED, $sale->status);
        $this->assertSame(4.0, (float) $sale->lines->first()->returned_quantity);
        $this->assertSame(0.0, $sale->lines->first()->fresh()->returnableQuantity());
    }

    public function test_cancelling_after_payment_does_not_allow_a_double_stock_return_via_the_linked_sale(): void
    {
        $product = $this->product(10);
        $customer = $this->customer();
        $order = Order::place([['product' => $product, 'quantity' => 4]], $customer->id, 'mobile_money_mtn');
        $order = $order->confirmPayment();
        $order->cancel(reason: 'Rupture logistique');

        $sale = $order->fresh()->sale;
        $saleLine = $sale->lines->first();

        $saleLine->returnQuantity($saleLine->returnableQuantity(), $order->customer_id ?? 1);

        // returnQuantity() plafonne sur returnableQuantity() (0 ici) : aucun mouvement de stock
        // supplémentaire ne doit être créé, le produit doit rester à son stock initial.
        $this->assertSame(10.0, $product->fresh()->currentStock());
    }

    public function test_cancelling_a_terminal_order_is_rejected(): void
    {
        $product = $this->product(10);
        $customer = $this->customer();
        $order = Order::place([['product' => $product, 'quantity' => 1]], $customer->id, 'mobile_money_mtn');
        $order = $order->confirmPayment()->startPreparation()->markReady()->deliver();

        $this->expectException(ValidationException::class);
        $order->cancel();
    }

    public function test_return_after_delivery_reintegrates_stock(): void
    {
        $product = $this->product(10);
        $customer = $this->customer();
        $order = Order::place([['product' => $product, 'quantity' => 2]], $customer->id, 'mobile_money_mtn');
        $order = $order->confirmPayment()->startPreparation()->markReady()->deliver();

        $this->assertSame(8.0, $product->fresh()->currentStock());

        $order->returnOrder(reason: 'Produit défectueux');

        $this->assertSame(Order::STATUS_RETOURNEE, $order->fresh()->status);
        $this->assertSame(10.0, $product->fresh()->currentStock());
        $this->assertSame(2.0, (float) $order->lines->first()->fresh()->returned_quantity);

        // Même garde-fou qu'à l'annulation : la SaleLine correspondante doit refléter le retour
        // pour qu'un second retour ne soit pas possible depuis la fiche Vente.
        $sale = $order->fresh()->sale;
        $this->assertSame(Sale::STATUS_COMPLETED, $sale->status); // livraison honorée, seule la ligne est retournée
        $this->assertSame(2.0, (float) $sale->lines->first()->returned_quantity);
    }

    public function test_return_before_delivery_is_rejected(): void
    {
        $product = $this->product(10);
        $customer = $this->customer();
        $order = Order::place([['product' => $product, 'quantity' => 1]], $customer->id, 'mobile_money_mtn');
        $order = $order->confirmPayment();

        $this->expectException(ValidationException::class);
        $order->returnOrder();
    }
}
