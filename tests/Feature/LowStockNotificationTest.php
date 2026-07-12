<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Notifications\LowStockAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LowStockNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_fires_once_when_crossing_the_threshold(): void
    {
        Notification::fake();

        Role::findOrCreate('admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $product = Product::create([
            'reference' => 'ALERT-1', 'name' => 'Produit surveillé', 'purchase_price' => 100, 'sale_price' => 150,
            'unit' => 'unité', 'low_stock_threshold' => 5,
        ]);

        // Stock initial confortable : pas d'alerte.
        StockMovement::create(['product_id' => $product->id, 'type' => 'entree', 'quantity' => 20]);
        Notification::assertNothingSent();

        // Cette sortie fait passer le stock de 20 à 4 : franchissement du seuil (5) → alerte.
        StockMovement::create(['product_id' => $product->id, 'type' => 'sortie', 'quantity' => -16]);
        Notification::assertSentTo($admin, LowStockAlert::class);

        // Une nouvelle sortie alors qu'on est déjà sous le seuil ne doit pas re-notifier.
        Notification::fake();
        StockMovement::create(['product_id' => $product->id, 'type' => 'sortie', 'quantity' => -1]);
        Notification::assertNothingSent();
    }
}
