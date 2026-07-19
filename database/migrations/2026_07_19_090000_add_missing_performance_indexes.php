<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PostgreSQL, contrairement à MySQL, ne crée pas automatiquement d'index sur une colonne
 * de clé étrangère — foreignId()->constrained() seul ne suffit pas. Ajoute les index
 * manquants sur les colonnes réellement filtrées/triées en usage courant (repérés lors de
 * l'audit Phase 1) : product_id sur stock_movements (Product::currentStock() fait un
 * sum('quantity') filtré dessus à chaque affichage produit), et status/customer_id/
 * created_at sur les tables de ventes/commandes/audit qui grossissent indéfiniment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->index('product_id');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->index('status');
            $table->index('customer_id');
            $table->index('created_at');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->index('customer_id');
            $table->index('created_at');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropIndex(['product_id']);
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['customer_id']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['customer_id']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });
    }
};
