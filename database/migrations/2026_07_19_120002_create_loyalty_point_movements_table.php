<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 — fidélité : ledger de points (jamais un solde brut modifié directement), même
 * principe que stock_movements — le solde se calcule toujours en sommant, jamais stocké.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_point_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->integer('points'); // signé : positif = gagné, négatif = utilisé
            $table->string('reason');
            $table->nullableMorphs('reference'); // Sale (gagné à l'achat, utilisé en réduction), ou null (ajustement manuel)
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_point_movements');
    }
};
