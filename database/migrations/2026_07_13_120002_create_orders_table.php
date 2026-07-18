<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Commande e-commerce (et, plus tard éventuellement, comptoir avec mise de côté) —
     * distincte de `sales` volontairement : `Sale::checkout()` reste le flux comptoir
     * "payer et emporter" instantané, inchangé. Une commande suit un cycle de vie
     * pré-paiement (réservée -> payée -> ...) que `Sale` n'a jamais eu besoin de modéliser.
     * Une fois payée, une commande génère une `Sale` (même principe que
     * Quote::convert() -> Sale::checkout(), déjà en place) pour que rapports, factures
     * et retours continuent à ne raisonner que sur `sales`, sans double logique.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained('sales')->nullOnDelete();
            $table->string('channel')->default('web'); // web | comptoir
            $table->string('status')->default('reservee');
            // reservee | payee | preparation | prete | livree | retiree | annulee | retournee
            $table->string('fulfillment_type')->default('livraison'); // livraison | retrait
            $table->text('delivery_address')->nullable();
            $table->string('delivery_phone')->nullable();
            $table->text('delivery_notes')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->string('payment_method')->nullable();
            $table->string('payment_status')->default('pending'); // pending | paid | failed | refunded
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
