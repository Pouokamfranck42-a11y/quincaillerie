<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Notifications\CustomerResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Phase 5 — la boutique en ligne : catalogue public, comptes clients (guard "customer"
 * distinct du guard "web" staff), panier en session, tunnel de commande, suivi.
 */
class ShopTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Warehouse::create(['name' => 'Magasin principal', 'is_default' => true]);
    }

    private function product(array $overrides = [], float $stock = 10): Product
    {
        $product = Product::create(array_merge([
            'reference' => 'WEB-1', 'name' => 'Produit web', 'purchase_price' => 100, 'sale_price' => 200,
            'unit' => 'unité', 'low_stock_threshold' => 5, 'active' => true, 'published_online' => true,
        ], $overrides));

        StockMovement::create(['product_id' => $product->id, 'type' => StockMovement::TYPE_ENTREE, 'quantity' => $stock]);

        return $product;
    }

    // --- Catalogue ---

    public function test_catalog_shows_only_published_and_active_products(): void
    {
        $published = $this->product(['reference' => 'PUB-1', 'name' => 'Publié']);
        $this->product(['reference' => 'NOPUB-1', 'name' => 'Non publié', 'published_online' => false]);
        $this->product(['reference' => 'INACTIVE-1', 'name' => 'Inactif', 'active' => false, 'published_online' => true]);

        $response = $this->get(route('shop.catalog.index'));

        $response->assertOk();
        $response->assertSee('Publié');
        $response->assertDontSee('Non publié');
        $response->assertDontSee('Inactif');
    }

    public function test_catalog_search_filters_by_name(): void
    {
        $this->product(['reference' => 'VIS-1', 'name' => 'Vis à bois']);
        $this->product(['reference' => 'MART-1', 'name' => 'Marteau']);

        $response = $this->get(route('shop.catalog.index', ['q' => 'marteau']));

        $response->assertSee('Marteau');
        $response->assertDontSee('Vis à bois');
    }

    public function test_unpublished_product_page_returns_404(): void
    {
        $product = $this->product(['published_online' => false]);

        $this->get(route('shop.catalog.show', $product))->assertNotFound();
    }

    public function test_product_page_lists_sibling_variants_in_the_same_family(): void
    {
        $family = \App\Models\ProductFamily::create(['name' => 'Vis']);
        $a = $this->product(['reference' => 'VIS-M6', 'name' => 'Vis M6', 'product_family_id' => $family->id]);
        $b = $this->product(['reference' => 'VIS-M8', 'name' => 'Vis M8', 'product_family_id' => $family->id]);

        $response = $this->get(route('shop.catalog.show', $a));

        $response->assertOk();
        $response->assertSee('Vis M8');
    }

    // --- Comptes clients ---

    public function test_registration_creates_a_customer_and_logs_them_in(): void
    {
        $response = $this->post(route('shop.register'), [
            'name' => 'Jean Client', 'email' => 'jean@example.com', 'phone' => '699000000',
            'password' => 'motdepasse123', 'password_confirmation' => 'motdepasse123',
        ]);

        $response->assertRedirect(route('shop.account.index'));
        $this->assertAuthenticated('customer');
        $customer = Customer::where('email', 'jean@example.com')->firstOrFail();
        $this->assertTrue($customer->hasWebAccount());
    }

    public function test_registration_upgrades_an_existing_walk_in_customer_instead_of_duplicating(): void
    {
        $walkIn = Customer::create(['name' => 'Jean (comptoir)', 'email' => 'jean@example.com']);

        $this->post(route('shop.register'), [
            'name' => 'Jean Client', 'email' => 'jean@example.com',
            'password' => 'motdepasse123', 'password_confirmation' => 'motdepasse123',
        ]);

        $this->assertDatabaseCount('customers', 1);
        $this->assertTrue($walkIn->fresh()->hasWebAccount());
    }

    public function test_registration_is_rejected_if_a_web_account_already_exists_for_the_email(): void
    {
        Customer::create(['name' => 'Existant', 'email' => 'jean@example.com', 'password' => 'dejainscrit']);

        $response = $this->post(route('shop.register'), [
            'name' => 'Jean Client', 'email' => 'jean@example.com',
            'password' => 'motdepasse123', 'password_confirmation' => 'motdepasse123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest('customer');
    }

    public function test_walk_in_customer_without_a_password_cannot_log_in(): void
    {
        Customer::create(['name' => 'Jean (comptoir)', 'email' => 'jean@example.com']);

        $response = $this->post(route('shop.login'), ['email' => 'jean@example.com', 'password' => 'anything']);

        $response->assertSessionHasErrors('email');
        $this->assertGuest('customer');
    }

    public function test_login_with_correct_credentials_succeeds(): void
    {
        Customer::create(['name' => 'Jean', 'email' => 'jean@example.com', 'password' => 'motdepasse123']);

        $response = $this->post(route('shop.login'), ['email' => 'jean@example.com', 'password' => 'motdepasse123']);

        $response->assertRedirect(route('shop.account.index'));
        $this->assertAuthenticated('customer');
    }

    // --- Mot de passe oublié ---

    public function test_forgot_password_sends_a_reset_notification_for_a_known_web_account(): void
    {
        Notification::fake();
        $customer = Customer::create(['name' => 'Jean', 'email' => 'jean@example.com', 'password' => 'motdepasse123']);

        $response = $this->post(route('shop.password.email'), ['email' => 'jean@example.com']);

        $response->assertSessionHas('status');
        Notification::assertSentTo($customer, CustomerResetPassword::class);
    }

    public function test_forgot_password_does_not_leak_whether_the_email_exists(): void
    {
        Notification::fake();

        $response = $this->post(route('shop.password.email'), ['email' => 'inconnu@example.com']);

        $response->assertSessionHas('status');
        Notification::assertNothingSent();
    }

    public function test_customer_can_reset_password_with_a_valid_token_and_then_log_in(): void
    {
        $customer = Customer::create(['name' => 'Jean', 'email' => 'jean@example.com', 'password' => 'ancienmdp1']);

        $token = \Illuminate\Support\Facades\Password::broker('customers')->createToken($customer);

        $response = $this->post(route('shop.password.update'), [
            'token' => $token,
            'email' => 'jean@example.com',
            'password' => 'nouveaumdp1',
            'password_confirmation' => 'nouveaumdp1',
        ]);

        $response->assertRedirect(route('shop.login'));

        $this->post(route('shop.login'), ['email' => 'jean@example.com', 'password' => 'nouveaumdp1'])
            ->assertRedirect(route('shop.account.index'));
        $this->assertAuthenticated('customer');
    }

    public function test_reset_password_is_rejected_with_an_invalid_token(): void
    {
        Customer::create(['name' => 'Jean', 'email' => 'jean@example.com', 'password' => 'ancienmdp1']);

        $response = $this->post(route('shop.password.update'), [
            'token' => 'jeton-invalide',
            'email' => 'jean@example.com',
            'password' => 'nouveaumdp1',
            'password_confirmation' => 'nouveaumdp1',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest('customer');
    }

    public function test_customer_password_reset_tokens_are_stored_separately_from_staff_tokens(): void
    {
        $customer = Customer::create(['name' => 'Jean', 'email' => 'partage@example.com', 'password' => 'motdepasse1']);
        \App\Models\User::create(['name' => 'Staff', 'email' => 'partage@example.com', 'password' => bcrypt('motdepasse1')]);

        \Illuminate\Support\Facades\Password::broker('customers')->createToken($customer);

        $this->assertSame(1, DB::table('customer_password_reset_tokens')->count());
        $this->assertSame(0, DB::table('password_reset_tokens')->count());
    }

    // --- Panier ---

    public function test_adding_to_cart_respects_available_stock(): void
    {
        $product = $this->product(stock: 2);

        $this->post(route('shop.cart.store'), ['product_id' => $product->id, 'quantity' => 5])
            ->assertSessionHasErrors('quantity');

        $this->post(route('shop.cart.store'), ['product_id' => $product->id, 'quantity' => 2])
            ->assertRedirect(route('shop.cart.index'));

        $response = $this->get(route('shop.cart.index'));
        $response->assertSee($product->name);
    }

    public function test_cart_update_removes_a_line_when_quantity_is_zero(): void
    {
        $product = $this->product();
        $this->withSession(['shop_cart' => [$product->id => 3]]);

        $this->post(route('shop.cart.update'), ['quantities' => [$product->id => 0]]);

        $response = $this->get(route('shop.cart.index'));
        $response->assertSee('Votre panier est vide');
    }

    // --- Tunnel de commande ---

    public function test_checkout_requires_a_customer_account(): void
    {
        $product = $this->product();
        $this->withSession(['shop_cart' => [$product->id => 1]]);

        $this->get(route('shop.checkout.create'))->assertRedirect(route('shop.login'));
    }

    public function test_authenticated_customer_can_place_an_order_from_the_cart(): void
    {
        $product = $this->product(stock: 10);
        $customer = Customer::create(['name' => 'Jean', 'email' => 'jean@example.com', 'password' => 'motdepasse123']);

        $response = $this->actingAs($customer, 'customer')
            ->withSession(['shop_cart' => [$product->id => 3]])
            ->post(route('shop.checkout.store'), [
                'fulfillment_type' => 'retrait',
                'delivery_phone' => '699000000',
                'payment_method' => 'mobile_money_mtn',
            ]);

        $order = Order::where('customer_id', $customer->id)->firstOrFail();
        $response->assertRedirect(route('shop.account.orders.show', $order));
        $this->assertSame(Order::STATUS_RESERVEE, $order->status);
        $this->assertSame(7.0, $product->fresh()->availableStock());
        $this->assertEmpty(session('shop_cart', []));
    }

    public function test_customer_cannot_view_another_customers_order(): void
    {
        $product = $this->product(stock: 10);
        $owner = Customer::create(['name' => 'Propriétaire', 'email' => 'owner@example.com', 'password' => 'motdepasse123']);
        $intruder = Customer::create(['name' => 'Intrus', 'email' => 'intruder@example.com', 'password' => 'motdepasse123']);

        $order = Order::place([['product' => $product, 'quantity' => 1]], $owner->id, 'mobile_money_mtn');

        $this->actingAs($intruder, 'customer')
            ->get(route('shop.account.orders.show', $order))
            ->assertNotFound();
    }

    // --- Expiration des réservations ---

    public function test_release_expired_reservations_cancels_unpaid_orders_and_frees_stock(): void
    {
        $product = $this->product(stock: 10);
        $customer = Customer::create(['name' => 'Jean', 'email' => 'jean@example.com', 'password' => 'x']);
        $order = Order::place([['product' => $product, 'quantity' => 4]], $customer->id, 'mobile_money_mtn');

        Reservation::where('reservable_id', $order->id)->update(['expires_at' => now()->subMinute()]);

        $this->artisan('app:release-expired-reservations')->assertExitCode(0);

        $this->assertSame(Order::STATUS_ANNULEE, $order->fresh()->status);
        $this->assertSame(10.0, $product->fresh()->availableStock());
    }

    public function test_release_expired_reservations_leaves_paid_orders_untouched(): void
    {
        $product = $this->product(stock: 10);
        $customer = Customer::create(['name' => 'Jean', 'email' => 'jean@example.com', 'password' => 'x']);
        $order = Order::place([['product' => $product, 'quantity' => 4]], $customer->id, 'mobile_money_mtn');
        $order->confirmPayment();

        $this->artisan('app:release-expired-reservations')->assertExitCode(0);

        $this->assertSame(Order::STATUS_PAYEE, $order->fresh()->status);
    }
}
