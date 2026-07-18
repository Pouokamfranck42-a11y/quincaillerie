<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Champ structuré (nullable = non renseigné) en complément de `payment_terms` (texte
     * libre, conservé pour les nuances — "50% à la commande", etc.). La prévision de
     * trésorerie utilise ce délai réel par fournisseur quand il est renseigné, et ne
     * retombe sur une hypothèse par défaut (30 jours) qu'en dernier recours.
     */
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->unsignedSmallInteger('payment_terms_days')->nullable()->after('payment_terms');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn('payment_terms_days');
        });
    }
};
