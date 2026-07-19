<?php

namespace Tests\Feature;

use App\Models\CashRegisterSession;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 2 — comptes clients professionnels : vue d'ensemble des encours, jusqu'ici
 * seulement consultable client par client (customers.statement).
 */
class CustomerCreditReportTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        \Spatie\Permission\Models\Role::findOrCreate('admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function creditSale(Customer $customer, User $user, int $dueDaysAgo): Sale
    {
        $product = Product::create([
            'reference' => 'CREDIT-'.uniqid(), 'name' => 'Produit crédit', 'purchase_price' => 100, 'sale_price' => 1000,
            'unit' => 'unité', 'low_stock_threshold' => 1,
        ]);
        StockMovement::create(['product_id' => $product->id, 'type' => 'entree', 'quantity' => 10]);

        $session = CashRegisterSession::create([
            'user_id' => $user->id, 'opened_at' => now(), 'opening_amount' => 0, 'status' => 'open',
        ]);

        $sale = Sale::checkout([['product' => $product, 'quantity' => 1]], $session, $user->id, $customer->id, 'credit', 0);
        $sale->update(['due_date' => now()->subDays($dueDaysAgo)]);

        return $sale;
    }

    public function test_report_lists_customers_with_outstanding_balance_sorted_by_overdue_first(): void
    {
        $user = $this->staff();

        $overdueCustomer = Customer::create(['name' => 'Client en retard', 'type' => 'professionnel', 'credit_limit' => 100000, 'payment_terms_days' => 30]);
        $this->creditSale($overdueCustomer, $user, dueDaysAgo: 10);

        $onTimeCustomer = Customer::create(['name' => 'Client à jour', 'type' => 'professionnel', 'credit_limit' => 100000, 'payment_terms_days' => 30]);
        $this->creditSale($onTimeCustomer, $user, dueDaysAgo: -5); // échéance dans le futur

        $noBalanceCustomer = Customer::create(['name' => 'Client sans encours', 'type' => 'particulier', 'credit_limit' => 0, 'payment_terms_days' => 0]);

        $response = $this->actingAs($user)->get(route('reports.customer-credit'));

        $response->assertOk();
        $response->assertSeeInOrder(['Client en retard', 'Client à jour']);
        $response->assertDontSee('Client sans encours');
    }

    public function test_a_fully_paid_credit_sale_does_not_appear_as_outstanding(): void
    {
        $user = $this->staff();
        $customer = Customer::create(['name' => 'Client soldé', 'type' => 'professionnel', 'credit_limit' => 100000, 'payment_terms_days' => 30]);
        $sale = $this->creditSale($customer, $user, dueDaysAgo: 5);
        $sale->recordPayment((float) $sale->total);

        $response = $this->actingAs($user)->get(route('reports.customer-credit'));

        $response->assertOk();
        $response->assertDontSee('Client soldé');
    }
}
