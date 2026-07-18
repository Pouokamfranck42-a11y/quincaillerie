<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductLot;
use App\Models\Reservation;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\Stock\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Phase 3 — le noyau de stock unifié. Ces tests couvrent la logique séquentielle
 * (disponibilité correctement calculée et respectée) ; la preuve de verrouillage sous
 * VRAIE concurrence multi-connexions est explicitement le "test décisif" de la Phase 9.
 */
class StockServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Warehouse::create(['name' => 'Magasin principal', 'is_default' => true]);
    }

    private function product(float $initialStock = 10): Product
    {
        $product = Product::create([
            'reference' => 'STOCK-1', 'name' => 'Produit test stock', 'purchase_price' => 100, 'sale_price' => 200,
            'unit' => 'unité', 'low_stock_threshold' => 5,
        ]);

        if ($initialStock > 0) {
            StockMovement::create(['product_id' => $product->id, 'type' => StockMovement::TYPE_ENTREE, 'quantity' => $initialStock]);
        }

        return $product;
    }

    public function test_reserve_creates_an_active_reservation_and_reduces_available_stock(): void
    {
        $product = $this->product(10);
        $service = app(StockService::class);

        $reservation = $service->reserve($product, 4, Reservation::CHANNEL_WEB);

        $this->assertSame(Reservation::STATUS_ACTIVE, $reservation->status);
        $this->assertSame(10.0, $product->fresh()->currentStock());
        $this->assertSame(6.0, $product->fresh()->availableStock());
        // Rien n'est physiquement déduit tant que ce n'est qu'une réservation.
        $this->assertDatabaseCount('stock_movements', 1);
    }

    public function test_reserve_fails_when_requested_quantity_exceeds_available_stock(): void
    {
        $product = $this->product(1);
        $service = app(StockService::class);

        $this->expectException(ValidationException::class);
        $service->reserve($product, 2, Reservation::CHANNEL_WEB);
    }

    public function test_second_reservation_on_the_last_unit_fails_while_the_first_is_still_active(): void
    {
        $product = $this->product(1);
        $service = app(StockService::class);

        $service->reserve($product, 1, Reservation::CHANNEL_COMPTOIR);

        $this->expectException(ValidationException::class);
        $service->reserve($product, 1, Reservation::CHANNEL_WEB);
    }

    public function test_release_frees_up_the_reserved_quantity_without_touching_physical_stock(): void
    {
        $product = $this->product(5);
        $service = app(StockService::class);

        $reservation = $service->reserve($product, 5, Reservation::CHANNEL_WEB);
        $service->release($reservation);

        $this->assertSame(Reservation::STATUS_RELEASED, $reservation->fresh()->status);
        $this->assertSame(5.0, $product->fresh()->availableStock());
        $this->assertSame(5.0, $product->fresh()->currentStock());
    }

    public function test_release_is_idempotent_on_an_already_released_reservation(): void
    {
        $product = $this->product(5);
        $service = app(StockService::class);

        $reservation = $service->reserve($product, 5, Reservation::CHANNEL_WEB);
        $service->release($reservation);
        $service->release($reservation); // ne doit pas lever d'erreur ni double-libérer

        $this->assertSame(Reservation::STATUS_RELEASED, $reservation->fresh()->status);
    }

    public function test_deduct_writes_a_stock_movement_and_marks_the_reservation_consumed(): void
    {
        $product = $this->product(5);
        $service = app(StockService::class);

        $reservation = $service->reserve($product, 3, Reservation::CHANNEL_WEB);
        $movement = $service->deduct($reservation, reason: 'Paiement confirmé');

        $this->assertSame(-3.0, (float) $movement->quantity);
        $this->assertSame(StockMovement::TYPE_SORTIE, $movement->type);
        $this->assertSame(Reservation::STATUS_CONSUMED, $reservation->fresh()->status);
        $this->assertSame(2.0, $product->fresh()->currentStock());
        $this->assertSame(2.0, $product->fresh()->availableStock());
    }

    public function test_deduct_refuses_a_reservation_that_is_no_longer_active(): void
    {
        $product = $this->product(5);
        $service = app(StockService::class);

        $reservation = $service->reserve($product, 3, Reservation::CHANNEL_WEB);
        $service->release($reservation);

        $this->expectException(\RuntimeException::class);
        $service->deduct($reservation);
    }

    public function test_reserve_and_deduct_does_both_in_one_call_for_the_counter_flow(): void
    {
        $product = $this->product(5);
        $service = app(StockService::class);

        $movement = $service->reserveAndDeduct($product, 2, Reservation::CHANNEL_COMPTOIR, reason: 'Vente comptoir');

        $this->assertSame(-2.0, (float) $movement->quantity);
        $this->assertSame(3.0, $product->fresh()->currentStock());
        $this->assertDatabaseCount('reservations', 1);
        $this->assertSame(Reservation::STATUS_CONSUMED, Reservation::first()->status);
    }

    public function test_reserve_and_deduct_picks_the_fefo_lot_when_none_is_given(): void
    {
        $product = Product::create([
            'reference' => 'LOT-1', 'name' => 'Produit à lots', 'purchase_price' => 100, 'sale_price' => 200,
            'unit' => 'unité', 'low_stock_threshold' => 5, 'tracks_lots' => true,
        ]);
        $earlyLot = ProductLot::create(['product_id' => $product->id, 'lot_number' => 'L1', 'expiry_date' => now()->addDays(10)]);
        ProductLot::create(['product_id' => $product->id, 'lot_number' => 'L2', 'expiry_date' => now()->addDays(60)]);
        StockMovement::create(['product_id' => $product->id, 'lot_id' => $earlyLot->id, 'type' => StockMovement::TYPE_ENTREE, 'quantity' => 5]);

        $movement = app(StockService::class)->reserveAndDeduct($product, 1, Reservation::CHANNEL_COMPTOIR);

        $this->assertSame($earlyLot->id, $movement->lot_id);
    }

    public function test_reintegrate_adds_physical_stock_back(): void
    {
        $product = $this->product(2);

        $movement = app(StockService::class)->reintegrate(
            $product, 1, reason: 'Retour client', subtype: StockMovement::SUBTYPE_RETOUR_CLIENT,
        );

        $this->assertSame(1.0, (float) $movement->quantity);
        $this->assertSame(3.0, $product->fresh()->currentStock());
    }
}
