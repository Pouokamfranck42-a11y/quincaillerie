<?php

namespace Tests\Feature;

use App\Contracts\PaymentProviderContract;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\Payment\AggregatorPaymentProvider;
use App\Services\Payment\SimulatedPaymentProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Phase 9 — "Paiement en simulation puis réel" : prouve que le mode se bascule
 * proprement (config -> binding du bon fournisseur) et que le fournisseur réel
 * (agrégateur) construit une requête correctement formée, même sans agrégateur
 * choisi/identifiants réels (voir AggregatorPaymentProvider — squelette non vérifié
 * en conditions réelles, signalé explicitement en Phase 6).
 */
class PaymentModeSwitchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Warehouse::create(['name' => 'Magasin principal', 'is_default' => true]);
    }

    public function test_simulation_mode_binds_the_simulated_provider(): void
    {
        config(['services.payment.mode' => 'simulation']);

        $provider = app(PaymentProviderContract::class);

        $this->assertInstanceOf(SimulatedPaymentProvider::class, $provider);
        $this->assertSame('simulation', $provider->name());
    }

    public function test_aggregator_mode_binds_the_real_provider(): void
    {
        config(['services.payment.mode' => 'aggregator']);

        $provider = app(PaymentProviderContract::class);

        $this->assertInstanceOf(AggregatorPaymentProvider::class, $provider);
        $this->assertSame('aggregator', $provider->name());
    }

    public function test_switching_mode_at_runtime_changes_the_resolved_provider(): void
    {
        config(['services.payment.mode' => 'simulation']);
        $this->assertInstanceOf(SimulatedPaymentProvider::class, app(PaymentProviderContract::class));

        config(['services.payment.mode' => 'aggregator']);
        $this->assertInstanceOf(AggregatorPaymentProvider::class, app(PaymentProviderContract::class));

        config(['services.payment.mode' => 'simulation']);
        $this->assertInstanceOf(SimulatedPaymentProvider::class, app(PaymentProviderContract::class));
    }

    public function test_aggregator_provider_sends_a_well_formed_request_and_parses_the_response(): void
    {
        config([
            'services.payment.mode' => 'aggregator',
            'services.payment.aggregator.base_url' => 'https://aggregator.example.test',
            'services.payment.aggregator.api_key' => 'test-api-key',
        ]);

        Http::fake([
            'aggregator.example.test/*' => Http::response([
                'transaction_id' => 'AGG-TXN-123',
                'payment_url' => null,
            ], 200),
        ]);

        $customer = Customer::create(['name' => 'Jean', 'email' => 'jean@example.com', 'password' => 'x']);
        $product = Product::create([
            'reference' => 'AGG-1', 'name' => 'Produit agrégateur', 'purchase_price' => 100, 'sale_price' => 200,
            'unit' => 'unité', 'low_stock_threshold' => 5,
        ]);
        StockMovement::create(['product_id' => $product->id, 'type' => StockMovement::TYPE_ENTREE, 'quantity' => 10]);
        $order = Order::place([['product' => $product, 'quantity' => 1]], $customer->id, 'mobile_money_mtn');

        $provider = app(PaymentProviderContract::class);
        $result = $provider->initiate($order, 'mobile_money_mtn');

        $this->assertSame('AGG-TXN-123', $result->reference);

        Http::assertSent(function ($request) use ($order) {
            return $request->url() === 'https://aggregator.example.test/payments'
                && $request['network'] === 'MTN'
                && $request['currency'] === 'XAF'
                && (float) $request['amount'] === (float) $order->total
                && $request['external_reference'] === (string) $order->id
                && $request->hasHeader('Authorization', 'Bearer test-api-key');
        });
    }

    public function test_aggregator_webhook_signature_is_rejected_without_a_configured_secret(): void
    {
        config(['services.payment.aggregator.webhook_secret' => '']);

        $provider = new AggregatorPaymentProvider();
        $request = \Illuminate\Http\Request::create('/webhooks/paiement/aggregator', 'POST', content: '{}');

        $this->assertFalse($provider->verifyWebhookSignature($request));
    }
}
