<?php

namespace Tests\Feature;

use App\Models\CashRegisterSession;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Vente comptoir — complète la couverture de tests_Feature/SaleCheckoutTest.php sur les
 * points spécifiques au composant Livewire du terminal POS : taux de TVA aligné sur la
 * config de facturation (au lieu d'un taux figé), et redirection automatique vers la
 * facture générée juste après l'encaissement.
 */
class PosTerminalTest extends TestCase
{
    use RefreshDatabase;

    private function makeSession(User $user): CashRegisterSession
    {
        return CashRegisterSession::create([
            'user_id' => $user->id, 'opened_at' => now(), 'opening_amount' => 0, 'status' => 'open',
        ]);
    }

    public function test_tax_rate_defaults_to_zero_when_company_is_not_vat_subject(): void
    {
        config(['company.vat_subject' => false]);
        $user = User::factory()->create();
        $session = $this->makeSession($user);

        Livewire::actingAs($user)
            ->test('pos.terminal', ['session' => $session])
            ->assertSet('taxRate', 0.0);
    }

    public function test_tax_rate_follows_company_config_when_vat_subject(): void
    {
        config(['company.vat_subject' => true, 'company.vat_rate' => 19.25]);
        $user = User::factory()->create();
        $session = $this->makeSession($user);

        Livewire::actingAs($user)
            ->test('pos.terminal', ['session' => $session])
            ->assertSet('taxRate', 19.25);
    }

    public function test_checkout_generates_invoice_and_redirects_to_it(): void
    {
        $user = User::factory()->create();
        $product = Product::create([
            'reference' => 'POS-1', 'name' => 'Produit POS', 'purchase_price' => 100, 'sale_price' => 200,
            'unit' => 'unité', 'low_stock_threshold' => 5,
        ]);
        StockMovement::create(['product_id' => $product->id, 'type' => StockMovement::TYPE_ENTREE, 'quantity' => 10]);
        $session = $this->makeSession($user);

        $test = Livewire::actingAs($user)
            ->test('pos.terminal', ['session' => $session])
            ->call('addToCart', $product->id)
            ->set('paymentMethod', 'especes')
            ->set('amountTendered', 500)
            ->call('checkout');

        $test->assertHasNoErrors();

        $sale = Sale::latest()->firstOrFail();
        $invoice = Invoice::where('invoiceable_id', $sale->id)->where('invoiceable_type', Sale::class)->firstOrFail();

        $test->assertRedirect(route('invoices.show', $invoice));
        $this->assertEquals(300, $sale->change_due);
        $this->assertNotNull($invoice->number);
    }

    public function test_checkout_is_blocked_when_a_cart_line_exceeds_available_stock(): void
    {
        $user = User::factory()->create();
        $product = Product::create([
            'reference' => 'POS-2', 'name' => 'Produit rare', 'purchase_price' => 100, 'sale_price' => 200,
            'unit' => 'unité', 'low_stock_threshold' => 5,
        ]);
        StockMovement::create(['product_id' => $product->id, 'type' => StockMovement::TYPE_ENTREE, 'quantity' => 2]);
        $session = $this->makeSession($user);

        Livewire::actingAs($user)
            ->test('pos.terminal', ['session' => $session])
            ->call('addToCart', $product->id)
            ->call('updateQuantity', $product->id, 5)
            ->call('checkout')
            ->assertHasErrors('cart');

        $this->assertDatabaseCount('sales', 0);
    }

    /** Interface caisse rapide (Phase 4) : Entrée dans la recherche = ajout direct si un seul résultat correspond, sans avoir à cliquer. */
    public function test_pressing_enter_adds_the_single_matching_result_to_the_cart(): void
    {
        $user = User::factory()->create();
        $product = Product::create([
            'reference' => 'POS-RACCOURCI', 'name' => 'Marteau unique', 'purchase_price' => 100, 'sale_price' => 200,
            'unit' => 'unité', 'low_stock_threshold' => 5,
        ]);
        StockMovement::create(['product_id' => $product->id, 'type' => StockMovement::TYPE_ENTREE, 'quantity' => 10]);
        $session = $this->makeSession($user);

        Livewire::actingAs($user)
            ->test('pos.terminal', ['session' => $session])
            ->set('search', 'Marteau unique')
            ->call('addFirstResultIfSingle')
            ->assertSet('cart', [$product->id => 1]);
    }

    public function test_pressing_enter_does_nothing_when_search_matches_several_products(): void
    {
        $user = User::factory()->create();
        foreach (['POS-MULTI-1', 'POS-MULTI-2'] as $reference) {
            $product = Product::create([
                'reference' => $reference, 'name' => 'Vis multiple', 'purchase_price' => 100, 'sale_price' => 200,
                'unit' => 'unité', 'low_stock_threshold' => 5,
            ]);
            StockMovement::create(['product_id' => $product->id, 'type' => StockMovement::TYPE_ENTREE, 'quantity' => 10]);
        }
        $session = $this->makeSession($user);

        Livewire::actingAs($user)
            ->test('pos.terminal', ['session' => $session])
            ->set('search', 'Vis multiple')
            ->call('addFirstResultIfSingle')
            ->assertSet('cart', []);
    }
}
