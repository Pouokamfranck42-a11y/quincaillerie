<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Phase 6 — paiement (webhook sécurisé + idempotent, paiement à la livraison) et
 * facturation (numérotation séquentielle, export comptable simplifié).
 */
class PaymentAndInvoicingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'magasinier', 'caissier'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        Warehouse::create(['name' => 'Magasin principal', 'is_default' => true]);
    }

    private function product(float $stock = 10): Product
    {
        $product = Product::create([
            'reference' => 'PAY-1', 'name' => 'Produit paiement', 'purchase_price' => 100, 'sale_price' => 200,
            'unit' => 'unité', 'low_stock_threshold' => 5,
        ]);
        StockMovement::create(['product_id' => $product->id, 'type' => StockMovement::TYPE_ENTREE, 'quantity' => $stock]);

        return $product;
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    private function signedWebhookRequest(string $reference, string $status, float $amount): array
    {
        $body = json_encode(['reference' => $reference, 'status' => $status, 'amount' => $amount]);
        $signature = hash_hmac('sha256', $body, config('services.payment.simulation_secret'));

        return [$body, $signature];
    }

    // --- Webhook : sécurité et idempotence ---

    public function test_webhook_rejects_an_invalid_signature(): void
    {
        [$body] = $this->signedWebhookRequest('REF-1', 'success', 100);

        $response = $this->call('POST', route('webhooks.payment', ['provider' => 'simulation']), [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_SIGNATURE' => 'signature-invalide',
        ], $body);

        $response->assertStatus(401);
    }

    public function test_webhook_confirms_payment_and_generates_a_sale(): void
    {
        $product = $this->product(10);
        $customer = Customer::create(['name' => 'Jean', 'email' => 'jean@example.com', 'password' => 'x']);
        $order = Order::place([['product' => $product, 'quantity' => 2]], $customer->id, 'mobile_money_mtn');
        $payment = Payment::create([
            'payable_type' => Order::class, 'payable_id' => $order->id, 'amount' => $order->total,
            'method' => 'mobile_money_mtn', 'status' => Payment::STATUS_PENDING,
            'provider' => 'simulation', 'provider_reference' => 'REF-CONFIRM-1',
        ]);

        [$body, $signature] = $this->signedWebhookRequest('REF-CONFIRM-1', 'success', (float) $order->total);

        $response = $this->call('POST', route('webhooks.payment', ['provider' => 'simulation']), [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_SIGNATURE' => $signature,
        ], $body);

        $response->assertOk();
        $this->assertSame(Order::STATUS_PAYEE, $order->fresh()->status);
        $this->assertSame(Payment::STATUS_SUCCESS, $payment->fresh()->status);
        $this->assertNotNull($order->fresh()->sale_id);
        $this->assertSame(8.0, $product->fresh()->currentStock());
    }

    public function test_webhook_is_idempotent_when_the_same_reference_is_replayed(): void
    {
        $product = $this->product(10);
        $customer = Customer::create(['name' => 'Jean', 'email' => 'jean@example.com', 'password' => 'x']);
        $order = Order::place([['product' => $product, 'quantity' => 2]], $customer->id, 'mobile_money_mtn');
        Payment::create([
            'payable_type' => Order::class, 'payable_id' => $order->id, 'amount' => $order->total,
            'method' => 'mobile_money_mtn', 'status' => Payment::STATUS_PENDING,
            'provider' => 'simulation', 'provider_reference' => 'REF-REPLAY-1',
        ]);

        [$body, $signature] = $this->signedWebhookRequest('REF-REPLAY-1', 'success', (float) $order->total);
        $headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_X_SIGNATURE' => $signature];

        $this->call('POST', route('webhooks.payment', ['provider' => 'simulation']), [], [], [], $headers, $body)->assertOk();
        $this->call('POST', route('webhooks.payment', ['provider' => 'simulation']), [], [], [], $headers, $body)->assertOk();

        // Une seule déduction malgré les deux appels.
        $this->assertSame(8.0, $product->fresh()->currentStock());
        $this->assertSame(1, StockMovement::where('type', StockMovement::TYPE_SORTIE)->count());
        $this->assertDatabaseCount('sales', 1);
    }

    public function test_webhook_with_unknown_reference_is_ignored_without_error(): void
    {
        [$body, $signature] = $this->signedWebhookRequest('REF-INCONNUE', 'success', 100);

        $response = $this->call('POST', route('webhooks.payment', ['provider' => 'simulation']), [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_SIGNATURE' => $signature,
        ], $body);

        $response->assertOk();
    }

    // --- Paiement à la livraison ---

    public function test_staff_can_confirm_cash_on_delivery_payment(): void
    {
        $product = $this->product(10);
        $customer = Customer::create(['name' => 'Jean', 'email' => 'jean@example.com', 'password' => 'x']);
        $order = Order::place([['product' => $product, 'quantity' => 1]], $customer->id, 'a_la_livraison');

        $response = $this->actingAs($this->admin())->post(route('online-orders.confirm-cod', $order));

        $response->assertRedirect(route('online-orders.show', $order));
        $this->assertSame(Order::STATUS_PAYEE, $order->fresh()->status);
        $this->assertSame(9.0, $product->fresh()->currentStock());
    }

    public function test_warehouse_staff_cannot_manage_online_orders(): void
    {
        $cashier = User::factory()->create();
        $cashier->assignRole('magasinier');

        $this->actingAs($cashier)->get(route('online-orders.index'))->assertForbidden();
    }

    // --- Facturation ---

    private function completedSale(): Sale
    {
        $admin = $this->admin();
        $session = \App\Models\CashRegisterSession::create(['user_id' => $admin->id, 'opened_at' => now(), 'opening_amount' => 0, 'status' => 'open']);

        return Sale::create([
            'cash_register_session_id' => $session->id, 'user_id' => $admin->id,
            'subtotal' => 1000, 'tax_rate' => 18, 'tax_amount' => 180, 'total' => 1180,
            'status' => 'completed', 'payment_status' => 'paid', 'paid_amount' => 1180,
        ]);
    }

    public function test_invoice_numbers_are_sequential_without_gaps(): void
    {
        $sale1 = $this->completedSale();
        $sale2 = $this->completedSale();

        $invoice1 = Invoice::generateFor($sale1);
        $invoice2 = Invoice::generateFor($sale2);

        $year = now()->year;
        $this->assertSame("FAC-{$year}-000001", $invoice1->number);
        $this->assertSame("FAC-{$year}-000002", $invoice2->number);
    }

    public function test_generating_an_invoice_twice_for_the_same_sale_does_not_consume_a_second_number(): void
    {
        $sale = $this->completedSale();

        $first = Invoice::generateFor($sale);
        $second = Invoice::generateFor($sale);

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('invoices', 1);
    }

    public function test_invoice_view_shows_non_compliance_banner_when_niu_is_missing(): void
    {
        config(['company.niu' => null, 'company.rccm' => null]);
        $sale = $this->completedSale();
        $invoice = Invoice::generateFor($sale);

        $response = $this->actingAs($this->admin())->get(route('invoices.show', $invoice));

        $response->assertOk();
        $response->assertSee('non conforme');
    }

    public function test_invoice_generation_route_is_admin_or_cashier_only(): void
    {
        $magasinier = User::factory()->create();
        $magasinier->assignRole('magasinier');
        $sale = $this->completedSale();

        $this->actingAs($magasinier)->post(route('invoices.store', $sale))->assertForbidden();
    }

    // --- Export SYSCOHADA ---

    public function test_accounting_export_produces_balanced_debit_credit_csv(): void
    {
        $this->completedSale();

        $response = $this->actingAs($this->admin())->get(route('accounting-export.export', [
            'from' => now()->subDay()->format('Y-m-d'), 'to' => now()->addDay()->format('Y-m-d'),
        ]));

        $response->assertOk();
        $csv = $response->getContent();
        $this->assertStringContainsString('701000', $csv);
        $this->assertStringContainsString('443100', $csv);
        $this->assertStringContainsString('571000', $csv);
    }

    public function test_accounting_export_is_admin_only(): void
    {
        $cashier = User::factory()->create();
        $cashier->assignRole('caissier');

        $this->actingAs($cashier)->get(route('accounting-export.index'))->assertForbidden();
    }
}
