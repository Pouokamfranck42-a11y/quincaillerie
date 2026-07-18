<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Journal des réservations de stock — le niveau "réservé" du modèle à 3 niveaux
     * (physique / réservé / disponible). Web et comptoir créent des réservations de la
     * même façon via le futur service central (Phase 3) : le comptoir les consomme
     * immédiatement dans la même transaction, le web les laisse "active" jusqu'au
     * paiement ou à l'expiration.
     */
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->decimal('quantity', 12, 2);
            $table->string('channel'); // web | comptoir
            $table->string('status')->default('active'); // active | consumed | released | expired
            $table->nullableMorphs('reservable'); // Order, Sale...
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'status']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
