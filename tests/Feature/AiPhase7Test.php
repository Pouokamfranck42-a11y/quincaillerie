<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CashRegisterSession;
use App\Models\GeminiUsage;
use App\Models\InventoryCount;
use App\Models\Product;
use App\Models\ProductAssociation;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Notifications\AnomalyDetected;
use App\Services\Ai\GeminiService;
use App\Services\Ai\GeminiUsageLimiter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Phase 7 — couche IA : gestion d'erreurs propre, quota/coût, streaming (staff + boutique),
 * ventes croisées en ligne, trésorerie sur échéances réelles, anomalies d'inventaire.
 */
class AiPhase7Test extends TestCase
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

    // --- Gestion d'erreurs propre ---

    public function test_product_description_returns_a_clean_error_when_gemini_fails(): void
    {
        $admin = $this->admin();

        $this->mock(GeminiService::class, function ($mock) {
            $mock->shouldReceive('generateText')->once()->andReturn('');
            $mock->shouldReceive('lastErrorMessage')->andReturn("La clé API Gemini est invalide ou expirée — vérifie GEMINI_API_KEY dans .env.");
        });

        $response = $this->actingAs($admin)->postJson(route('products.generate-description'), ['name' => 'Marteau']);

        $response->assertStatus(422);
        $response->assertJson(['error' => "La clé API Gemini est invalide ou expirée — vérifie GEMINI_API_KEY dans .env."]);
    }

    public function test_photo_recognition_returns_a_clean_error_when_gemini_fails(): void
    {
        $admin = $this->admin();

        $this->mock(GeminiService::class, function ($mock) {
            $mock->shouldReceive('extractStructured')->once()->andReturn([]);
            $mock->shouldReceive('lastErrorMessage')->andReturn('Le quota Gemini (Google) est atteint pour le moment — réessaie plus tard.');
        });

        $response = $this->actingAs($admin)->postJson(route('products.recognize-photo'), [
            'photo' => UploadedFile::fake()->image('objet.jpg'),
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['error' => 'Le quota Gemini (Google) est atteint pour le moment — réessaie plus tard.']);
    }

    // --- Quota / coût ---

    public function test_usage_limiter_blocks_new_calls_once_the_daily_limit_is_reached(): void
    {
        config(['services.gemini.daily_call_limit' => 2]);
        $limiter = new GeminiUsageLimiter();

        $this->assertTrue($limiter->hasQuotaRemaining());
        $limiter->recordCall();
        $this->assertTrue($limiter->hasQuotaRemaining());
        $limiter->recordCall();
        $this->assertFalse($limiter->hasQuotaRemaining());
        $this->assertSame(0, $limiter->remaining());
    }

    public function test_usage_limiter_is_unlimited_when_configured_at_zero(): void
    {
        config(['services.gemini.daily_call_limit' => 0]);
        $limiter = new GeminiUsageLimiter();

        for ($i = 0; $i < 50; $i++) {
            $limiter->recordCall();
        }

        $this->assertTrue($limiter->hasQuotaRemaining());
        $this->assertNull($limiter->remaining());
    }

    public function test_gemini_service_blocks_the_call_once_local_quota_is_exhausted(): void
    {
        config(['services.gemini.key' => 'test-key', 'services.gemini.daily_call_limit' => 1]);
        GeminiUsage::create(['date' => today()->toDateString(), 'calls' => 1]);

        $gemini = new GeminiService();
        $text = $gemini->generateText('system', 'prompt');

        $this->assertSame('', $text);
        $this->assertSame('quota_exceeded', $gemini->lastErrorReason());
    }

    // --- Streaming (staff + boutique) ---

    public function test_staff_chatbot_streams_the_final_answer_progressively(): void
    {
        $user = User::factory()->create();
        $user->assignRole('caissier');

        $this->mock(GeminiService::class, function ($mock) {
            $mock->shouldReceive('chat')->once()->andReturn('Bonjour et bienvenue.');
        });

        Livewire::actingAs($user)
            ->test('chatbot.assistant')
            ->set('input', 'Bonjour')
            ->call('send')
            ->assertSet('input', '');

        $this->assertDatabaseHas('chat_messages', ['role' => 'assistant', 'content' => 'Bonjour et bienvenue.']);
    }

    public function test_shop_diagnostic_assistant_answers_without_requiring_an_account(): void
    {
        $this->mock(GeminiService::class, function ($mock) {
            $mock->shouldReceive('chat')->once()->andReturn('Essayez du ruban Téflon sur les raccords.');
        });

        Livewire::test('shop.diagnostic-assistant')
            ->set('input', 'Fuite sous mon évier')
            ->call('send')
            ->assertSet('input', '')
            ->assertSee('Essayez du ruban Téflon sur les raccords.');
    }

    public function test_shop_diagnostic_assistant_can_restart_the_conversation(): void
    {
        $this->mock(GeminiService::class, function ($mock) {
            $mock->shouldReceive('chat')->once()->andReturn('Réponse.');
        });

        Livewire::test('shop.diagnostic-assistant')
            ->set('input', 'Question')
            ->call('send')
            ->call('restart')
            ->assertSet('messages', []);
    }

    // --- Ventes croisées en ligne ---

    public function test_product_page_shows_cross_sell_suggestions(): void
    {
        $nails = Product::create([
            'reference' => 'CLOU-1', 'name' => 'Clous', 'purchase_price' => 500, 'sale_price' => 800,
            'unit' => 'boîte', 'low_stock_threshold' => 5, 'active' => true, 'published_online' => true,
        ]);
        $hammer = Product::create([
            'reference' => 'MART-1', 'name' => 'Marteau', 'purchase_price' => 3000, 'sale_price' => 4500,
            'unit' => 'unité', 'low_stock_threshold' => 2, 'active' => true, 'published_online' => true,
        ]);
        ProductAssociation::create(['product_id' => $nails->id, 'associated_product_id' => $hammer->id, 'co_occurrence_count' => 5, 'updated_at' => now()]);

        $response = $this->get(route('shop.catalog.show', $nails));

        $response->assertOk();
        $response->assertSee('Souvent achetés ensemble');
        $response->assertSee('Marteau');
    }

    public function test_cross_sell_suggestions_hide_unpublished_products(): void
    {
        $nails = Product::create([
            'reference' => 'CLOU-2', 'name' => 'Clous 2', 'purchase_price' => 500, 'sale_price' => 800,
            'unit' => 'boîte', 'low_stock_threshold' => 5, 'active' => true, 'published_online' => true,
        ]);
        $internalOnly = Product::create([
            'reference' => 'INT-1', 'name' => 'Produit interne', 'purchase_price' => 100, 'sale_price' => 200,
            'unit' => 'unité', 'low_stock_threshold' => 5, 'active' => true, 'published_online' => false,
        ]);
        ProductAssociation::create(['product_id' => $nails->id, 'associated_product_id' => $internalOnly->id, 'co_occurrence_count' => 5, 'updated_at' => now()]);

        $response = $this->get(route('shop.catalog.show', $nails));

        $response->assertOk();
        $response->assertDontSee('Produit interne');
    }

    // --- Trésorerie sur échéances réelles fournisseurs ---

    public function test_cash_flow_uses_real_supplier_payment_terms_when_set(): void
    {
        $admin = $this->admin();
        $supplier = Supplier::create(['name' => 'Fournisseur rapide', 'lead_time_days' => 5, 'payment_terms_days' => 10]);
        $product = Product::create([
            'reference' => 'PO-1', 'name' => 'Produit commandé', 'purchase_price' => 1000, 'sale_price' => 1500,
            'unit' => 'unité', 'low_stock_threshold' => 5,
        ]);

        $po = PurchaseOrder::create([
            'supplier_id' => $supplier->id, 'user_id' => $admin->id,
            'status' => PurchaseOrder::STATUS_ORDERED, 'ordered_at' => now()->subDays(5),
        ]);
        $po->lines()->create(['product_id' => $product->id, 'quantity' => 10, 'unit_price' => 1000]);

        $response = $this->actingAs($admin)->get(route('reports.cash-flow'));

        $response->assertOk();
        // Échéance réelle : commandé il y a 5 jours + 10 jours de délai = doit apparaître dès la fenêtre à 30 jours.
        $response->assertViewHas('payables', function ($payables) {
            $entry = $payables->firstWhere('supplier_name', 'Fournisseur rapide');

            return $entry !== null && $entry['is_real_term'] === true && $entry['term_days'] === 10;
        });
    }

    public function test_cash_flow_falls_back_to_default_term_when_supplier_has_none(): void
    {
        $admin = $this->admin();
        $supplier = Supplier::create(['name' => 'Fournisseur sans délai connu', 'lead_time_days' => 5]);
        $product = Product::create([
            'reference' => 'PO-2', 'name' => 'Produit commandé 2', 'purchase_price' => 1000, 'sale_price' => 1500,
            'unit' => 'unité', 'low_stock_threshold' => 5,
        ]);

        $po = PurchaseOrder::create([
            'supplier_id' => $supplier->id, 'user_id' => $admin->id,
            'status' => PurchaseOrder::STATUS_ORDERED, 'ordered_at' => now(),
        ]);
        $po->lines()->create(['product_id' => $product->id, 'quantity' => 5, 'unit_price' => 1000]);

        $response = $this->actingAs($admin)->get(route('reports.cash-flow'));

        $response->assertViewHas('payables', function ($payables) {
            $entry = $payables->firstWhere('supplier_name', 'Fournisseur sans délai connu');

            return $entry !== null && $entry['is_real_term'] === false && $entry['term_days'] === 30;
        });
    }

    // --- Détection d'anomalies : écarts d'inventaire ---

    public function test_large_inventory_discrepancy_notifies_admins(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $warehouse = Warehouse::where('is_default', true)->first();

        $product = Product::create([
            'reference' => 'ANOM-1', 'name' => 'Produit avec gros écart', 'purchase_price' => 100, 'sale_price' => 150,
            'unit' => 'unité', 'low_stock_threshold' => 5,
        ]);
        StockMovement::create(['product_id' => $product->id, 'type' => StockMovement::TYPE_ENTREE, 'quantity' => 20]);

        $count = InventoryCount::create([
            'warehouse_id' => $warehouse->id, 'user_id' => $admin->id,
            'type' => InventoryCount::TYPE_COMPLET, 'status' => InventoryCount::STATUS_IN_PROGRESS,
        ]);
        $count->lines()->create(['product_id' => $product->id, 'expected_quantity' => 20, 'counted_quantity' => 5]); // -75 %
        $count->load('lines');

        $count->complete($admin->id);

        Notification::assertSentTo($admin, AnomalyDetected::class, fn ($n) => $n->anomalyType === 'inventory_discrepancy');
    }

    public function test_small_inventory_discrepancy_does_not_notify(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $warehouse = Warehouse::where('is_default', true)->first();

        $product = Product::create([
            'reference' => 'ANOM-2', 'name' => 'Produit avec petit écart', 'purchase_price' => 100, 'sale_price' => 150,
            'unit' => 'unité', 'low_stock_threshold' => 5,
        ]);
        StockMovement::create(['product_id' => $product->id, 'type' => StockMovement::TYPE_ENTREE, 'quantity' => 20]);

        $count = InventoryCount::create([
            'warehouse_id' => $warehouse->id, 'user_id' => $admin->id,
            'type' => InventoryCount::TYPE_COMPLET, 'status' => InventoryCount::STATUS_IN_PROGRESS,
        ]);
        $count->lines()->create(['product_id' => $product->id, 'expected_quantity' => 20, 'counted_quantity' => 18]); // -10 %
        $count->load('lines');

        $count->complete($admin->id);

        Notification::assertNothingSent();
    }
}
