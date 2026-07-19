<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 — vente à la découpe (câble, tuyau, chaîne...) : quantité décimale avec un pas
 * explicite plutôt que la simple tolérance "n'importe quel décimal" qui existait déjà.
 * sold_by_cut=false laisse le comportement actuel inchangé pour tous les produits existants.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('sold_by_cut')->default(false)->after('unit');
            $table->decimal('cut_step', 8, 3)->default(1)->after('sold_by_cut');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['sold_by_cut', 'cut_step']);
        });
    }
};
