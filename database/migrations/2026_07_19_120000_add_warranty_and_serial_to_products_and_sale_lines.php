<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase 2 — SAV/garantie : durée de garantie par produit, n° de série capturé à la vente. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('warranty_months')->nullable()->after('tracks_lots');
        });

        Schema::table('sale_lines', function (Blueprint $table) {
            $table->string('serial_number')->nullable()->after('lot_id');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('warranty_months');
        });

        Schema::table('sale_lines', function (Blueprint $table) {
            $table->dropColumn('serial_number');
        });
    }
};
