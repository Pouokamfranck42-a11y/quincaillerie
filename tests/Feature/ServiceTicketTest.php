<?php

namespace Tests\Feature;

use App\Models\CashRegisterSession;
use App\Models\Product;
use App\Models\Sale;
use App\Models\ServiceTicket;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Phase 2 — SAV/garantie : dossiers de retour/réparation/échange, éligibilité basée sur la garantie du produit. */
class ServiceTicketTest extends TestCase
{
    use RefreshDatabase;

    private function staffWithSav(): User
    {
        \Spatie\Permission\Models\Role::findOrCreate('admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function saleWithWarrantyProduct(?int $warrantyMonths, User $user): \App\Models\SaleLine
    {
        $product = Product::create([
            'reference' => 'SAV-1', 'name' => 'Perceuse', 'purchase_price' => 5000, 'sale_price' => 10000,
            'unit' => 'unité', 'low_stock_threshold' => 1, 'warranty_months' => $warrantyMonths,
        ]);
        StockMovement::create(['product_id' => $product->id, 'type' => 'entree', 'quantity' => 5]);

        $session = CashRegisterSession::create(['user_id' => $user->id, 'opened_at' => now(), 'opening_amount' => 0, 'status' => 'open']);
        $sale = Sale::checkout([['product' => $product, 'quantity' => 1, 'serial_number' => 'SN-12345']], $session, $user->id, null, 'especes', 0);

        return $sale->lines->first();
    }

    public function test_a_sale_line_within_warranty_period_is_flagged_as_under_warranty(): void
    {
        $user = $this->staffWithSav();
        $line = $this->saleWithWarrantyProduct(12, $user);

        $this->assertTrue($line->isUnderWarranty());
        $this->assertSame('SN-12345', $line->serial_number);
    }

    public function test_a_product_without_a_declared_warranty_is_never_under_warranty(): void
    {
        $user = $this->staffWithSav();
        $line = $this->saleWithWarrantyProduct(null, $user);

        $this->assertFalse($line->isUnderWarranty());
    }

    public function test_staff_can_open_a_service_ticket_for_a_sale_line(): void
    {
        $user = $this->staffWithSav();
        $line = $this->saleWithWarrantyProduct(12, $user);

        $response = $this->actingAs($user)->post(route('service-tickets.store', [$line->sale, $line]), [
            'issue_description' => "Ne démarre plus après une semaine d'utilisation.",
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('service_tickets', [
            'sale_line_id' => $line->id,
            'status' => ServiceTicket::STATUS_OUVERT,
        ]);
    }

    public function test_resolving_a_ticket_as_exchange_reintegrates_stock_via_the_unified_kernel(): void
    {
        $user = $this->staffWithSav();
        $line = $this->saleWithWarrantyProduct(12, $user);
        $product = $line->product;
        $stockBefore = $product->fresh()->currentStock();

        $ticket = ServiceTicket::create([
            'sale_line_id' => $line->id, 'opened_by' => $user->id, 'status' => ServiceTicket::STATUS_OUVERT,
            'issue_description' => 'Défectueux',
        ]);

        $response = $this->actingAs($user)->post(route('service-tickets.resolve', $ticket), [
            'resolution_type' => 'echange',
            'return_quantity' => 1,
        ]);

        $response->assertRedirect();
        $ticket->refresh();
        $this->assertSame(ServiceTicket::STATUS_RESOLU, $ticket->status);
        $this->assertSame('echange', $ticket->resolution_type);
        $this->assertEquals($stockBefore + 1, $product->fresh()->currentStock());
        $this->assertEquals(1, $line->fresh()->returned_quantity);
    }

    public function test_resolving_a_ticket_as_repair_does_not_move_any_stock(): void
    {
        $user = $this->staffWithSav();
        $line = $this->saleWithWarrantyProduct(12, $user);
        $product = $line->product;
        $stockBefore = $product->fresh()->currentStock();

        $ticket = ServiceTicket::create([
            'sale_line_id' => $line->id, 'opened_by' => $user->id, 'status' => ServiceTicket::STATUS_OUVERT,
            'issue_description' => 'Bruit anormal',
        ]);

        $this->actingAs($user)->post(route('service-tickets.resolve', $ticket), [
            'resolution_type' => 'reparation',
        ]);

        $this->assertEquals($stockBefore, $product->fresh()->currentStock());
        $this->assertEquals(0, $line->fresh()->returned_quantity);
    }

    public function test_staff_without_sav_permission_cannot_access_service_tickets(): void
    {
        $user = User::factory()->create(); // aucun rôle, aucune permission

        $response = $this->actingAs($user)->get(route('service-tickets.index'));

        $response->assertForbidden();
    }
}
