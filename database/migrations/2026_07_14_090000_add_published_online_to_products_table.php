<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Choix manuel de l'admin, produit par produit — au lancement, seuls les articles
     * simples vendus à l'unité doivent être cochés (pas la découpe au mètre, pas le
     * très lourd), mais c'est une politique de lancement, pas une règle technique
     * vérifiable automatiquement à partir des champs existants.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('published_online')->default(false)->after('active');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('published_online');
        });
    }
};
