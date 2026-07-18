<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Notifications\OrderReady;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Phase 8 — périphérie & exploitation : workflow de préparation staff, notification
 * "commande prête", journal d'audit pour l'annulation.
 */
class OrderFulfillmentTest extends TestCase
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

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    private function paidOrder(string $fulfillmentType = Order::FULFILLMENT_RETRAIT): Order
    {
        $product = Product::create([
            'reference' => 'FUL-1', 'name' => 'Produit commande', 'purchase_price' => 100, 'sale_price' => 200,
            'unit' => 'unité', 'low_stock_threshold' => 5,
        ]);
        StockMovement::create(['product_id' => $product->id, 'type' => StockMovement::TYPE_ENTREE, 'quantity' => 10]);
        $customer = Customer::create(['name' => 'Jean', 'email' => 'jean@example.com', 'password' => 'x']);

        $order = Order::place([['product' => $product, 'quantity' => 2]], $customer->id, 'mobile_money_mtn', $fulfillmentType);

        return $order->confirmPayment();
    }

    public function test_staff_can_progress_a_pickup_order_through_the_full_workflow(): void
    {
        $admin = $this->admin();
        $order = $this->paidOrder(Order::FULFILLMENT_RETRAIT);

        $this->actingAs($admin)->post(route('online-orders.start-preparation', $order))
            ->assertRedirect(route('online-orders.show', $order));
        $this->assertSame(Order::STATUS_PREPARATION, $order->fresh()->status);

        $this->actingAs($admin)->post(route('online-orders.mark-ready', $order));
        $this->assertSame(Order::STATUS_PRETE, $order->fresh()->status);

        $this->actingAs($admin)->post(route('online-orders.pick-up', $order));
        $this->assertSame(Order::STATUS_RETIREE, $order->fresh()->status);
        $this->assertNotNull($order->fresh()->delivered_at);
    }

    public function test_staff_can_progress_a_delivery_order_through_the_full_workflow(): void
    {
        $admin = $this->admin();
        $order = $this->paidOrder(Order::FULFILLMENT_LIVRAISON);

        $this->actingAs($admin)->post(route('online-orders.start-preparation', $order));
        $this->actingAs($admin)->post(route('online-orders.mark-ready', $order));
        $this->actingAs($admin)->post(route('online-orders.deliver', $order));

        $this->assertSame(Order::STATUS_LIVREE, $order->fresh()->status);
    }

    public function test_pickup_action_is_rejected_for_a_delivery_order(): void
    {
        $admin = $this->admin();
        $order = $this->paidOrder(Order::FULFILLMENT_LIVRAISON);
        $order->startPreparation();
        $order->markReady();

        $response = $this->actingAs($admin)->post(route('online-orders.pick-up', $order));

        $response->assertSessionHasErrors('status');
        $this->assertSame(Order::STATUS_PRETE, $order->fresh()->status);
    }

    public function test_invalid_transition_is_rejected_cleanly_without_a_crash(): void
    {
        $admin = $this->admin();
        $order = $this->paidOrder(); // status = payee

        // deliver() exige status prete, pas payee : doit échouer proprement, pas planter.
        $response = $this->actingAs($admin)->post(route('online-orders.deliver', $order));

        $response->assertSessionHasErrors('status');
        $this->assertSame(Order::STATUS_PAYEE, $order->fresh()->status);
    }

    public function test_marking_ready_notifies_the_customer(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $order = $this->paidOrder();
        $order->startPreparation();

        $this->actingAs($admin)->post(route('online-orders.mark-ready', $order));

        Notification::assertSentTo($order->customer, OrderReady::class);
    }

    public function test_staff_can_cancel_an_order_and_it_is_audited(): void
    {
        $admin = $this->admin();
        $order = $this->paidOrder(); // payee : stock déjà déduit

        $productId = $order->lines->first()->product_id;
        $stockBefore = Product::find($productId)->currentStock();

        $response = $this->actingAs($admin)->post(route('online-orders.cancel', $order), ['reason' => 'Rupture logistique']);

        $response->assertRedirect(route('online-orders.show', $order));
        $this->assertSame(Order::STATUS_ANNULEE, $order->fresh()->status);
        $this->assertGreaterThan($stockBefore, Product::find($productId)->currentStock());

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'order.cancelled',
            'auditable_type' => Order::class,
            'auditable_id' => $order->id,
            'user_id' => $admin->id,
        ]);
    }

    public function test_terminal_order_cannot_be_cancelled(): void
    {
        $admin = $this->admin();
        $order = $this->paidOrder();
        $order->startPreparation()->markReady()->pickUp();

        $response = $this->actingAs($admin)->post(route('online-orders.cancel', $order));

        $response->assertSessionHasErrors('status');
        $this->assertSame(Order::STATUS_RETIREE, $order->fresh()->status);
    }

    public function test_warehouse_staff_cannot_manage_order_fulfillment(): void
    {
        $magasinier = User::factory()->create();
        $magasinier->assignRole('magasinier');
        $order = $this->paidOrder();

        $this->actingAs($magasinier)->post(route('online-orders.start-preparation', $order))->assertForbidden();
    }

    public function test_customer_sees_order_ready_notification_on_their_account(): void
    {
        $admin = $this->admin();
        $order = $this->paidOrder();
        $order->startPreparation();
        $this->actingAs($admin)->post(route('online-orders.mark-ready', $order));

        $customer = $order->fresh()->customer;

        $response = $this->actingAs($customer, 'customer')->get(route('shop.notifications.index'));

        $response->assertOk();
        $response->assertSee('est prête');
    }
}
