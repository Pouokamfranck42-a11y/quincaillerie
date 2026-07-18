<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\Sale;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 2 — vérifie uniquement que le nouveau modèle de données (migrations + relations
 * Eloquent) est correct. Aucune logique métier ici : réservation/déduction/état de
 * commande sont l'objet des Phases 3 et 4.
 */
class EcommerceDataModelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Warehouse::create(['name' => 'Magasin principal', 'is_default' => true]);
    }

    private function product(): Product
    {
        return Product::create([
            'reference' => 'VIS-1', 'name' => 'Vis à bois', 'purchase_price' => 100, 'sale_price' => 150,
            'unit' => 'unité', 'low_stock_threshold' => 5,
        ]);
    }

    public function test_customer_can_hold_a_web_account_alongside_counter_history(): void
    {
        $walkIn = Customer::create(['name' => 'Client comptoir']);
        $webCustomer = Customer::create([
            'name' => 'Client web', 'email' => 'web@example.com', 'password' => 'secret1234',
        ]);

        $this->assertFalse($walkIn->hasWebAccount());
        $this->assertTrue($webCustomer->hasWebAccount());
        $this->assertNotSame('secret1234', $webCustomer->password);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('secret1234', $webCustomer->fresh()->password));
    }

    public function test_customer_email_must_be_unique(): void
    {
        Customer::create(['name' => 'A', 'email' => 'dup@example.com']);

        $this->expectException(QueryException::class);
        Customer::create(['name' => 'B', 'email' => 'dup@example.com']);
    }

    public function test_reservation_defaults_to_the_default_warehouse_like_stock_movements(): void
    {
        $product = $this->product();

        $reservation = Reservation::create([
            'product_id' => $product->id, 'quantity' => 5, 'channel' => Reservation::CHANNEL_WEB,
        ]);

        $this->assertSame(Warehouse::default()->id, $reservation->warehouse_id);
        $this->assertSame(Reservation::STATUS_ACTIVE, $reservation->status);
    }

    public function test_order_links_customer_lines_and_optional_sale(): void
    {
        $customer = Customer::create(['name' => 'Client web', 'email' => 'client@example.com']);
        $product = $this->product();

        $order = Order::create([
            'customer_id' => $customer->id, 'channel' => Order::CHANNEL_WEB,
            'subtotal' => 150, 'total' => 150,
        ]);
        $order->lines()->create(['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 150]);

        $this->assertSame(Order::STATUS_RESERVEE, $order->fresh()->status);
        $this->assertCount(1, $order->lines);
        $this->assertTrue($order->customer->is($customer));
        $this->assertNull($order->sale);

        $session = \App\Models\CashRegisterSession::create([
            'user_id' => User::factory()->create()->id, 'opened_at' => now(), 'opening_amount' => 0, 'status' => 'open',
        ]);
        $sale = Sale::create([
            'cash_register_session_id' => $session->id, 'user_id' => $session->user_id,
            'subtotal' => 150, 'total' => 150, 'status' => 'completed', 'payment_status' => 'paid', 'paid_amount' => 150,
        ]);
        $order->update(['sale_id' => $sale->id]);

        $this->assertTrue($order->fresh()->sale->is($sale));
        $this->assertTrue($sale->order->is($order));
    }

    public function test_payment_provider_reference_is_unique_for_webhook_idempotency(): void
    {
        $customer = Customer::create(['name' => 'Client web']);
        $order = Order::create(['customer_id' => $customer->id, 'subtotal' => 100, 'total' => 100]);

        Payment::create([
            'payable_type' => Order::class, 'payable_id' => $order->id,
            'amount' => 100, 'method' => Payment::METHOD_MOBILE_MONEY_MTN,
            'status' => Payment::STATUS_SUCCESS, 'provider_reference' => 'MTN-TXN-123',
        ]);

        $this->expectException(QueryException::class);
        Payment::create([
            'payable_type' => Order::class, 'payable_id' => $order->id,
            'amount' => 100, 'method' => Payment::METHOD_MOBILE_MONEY_MTN,
            'status' => Payment::STATUS_SUCCESS, 'provider_reference' => 'MTN-TXN-123',
        ]);
    }

    public function test_invoice_number_is_unique_and_polymorphic_to_sale(): void
    {
        $session = \App\Models\CashRegisterSession::create([
            'user_id' => User::factory()->create()->id, 'opened_at' => now(), 'opening_amount' => 0, 'status' => 'open',
        ]);
        $sale = Sale::create([
            'cash_register_session_id' => $session->id, 'user_id' => $session->user_id,
            'subtotal' => 150, 'total' => 150, 'status' => 'completed', 'payment_status' => 'paid', 'paid_amount' => 150,
        ]);

        $invoice = Invoice::create([
            'invoiceable_type' => Sale::class, 'invoiceable_id' => $sale->id,
            'number' => 'FAC-2026-0001', 'subtotal' => 150, 'tax_amount' => 0, 'total' => 150, 'issued_at' => now(),
        ]);

        $this->assertTrue($invoice->invoiceable->is($sale));
        $this->assertCount(1, $sale->invoices);

        $this->expectException(QueryException::class);
        Invoice::create([
            'invoiceable_type' => Sale::class, 'invoiceable_id' => $sale->id,
            'number' => 'FAC-2026-0001', 'subtotal' => 150, 'tax_amount' => 0, 'total' => 150, 'issued_at' => now(),
        ]);
    }

    /** Garde-fou : la Phase 2 n'a pas dû toucher au calcul existant du stock réservé (devis). */
    public function test_existing_reserved_stock_calculation_is_unaffected_by_the_new_reservations_table(): void
    {
        $product = $this->product();

        Reservation::create(['product_id' => $product->id, 'quantity' => 999, 'channel' => Reservation::CHANNEL_WEB]);

        $this->assertSame(0.0, $product->reservedStock());
    }
}
