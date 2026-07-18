<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Compteur verrouillable (SELECT ... FOR UPDATE, même principe que le stock en
     * Phase 3) qui garantit une numérotation de facture séquentielle SANS SAUT,
     * remise à zéro chaque année civile (format FAC-2026-000001).
     */
    public function up(): void
    {
        Schema::create('invoice_counters', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year')->unique();
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_counters');
    }
};
