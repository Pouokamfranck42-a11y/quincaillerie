<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('brand')->nullable()->after('name');
            $table->string('photo_path')->nullable()->after('description');
            $table->string('supplier_sku')->nullable()->after('supplier_id');
            $table->string('sale_unit')->nullable()->after('unit');
            $table->decimal('sale_unit_factor', 10, 3)->default(1)->after('sale_unit');
            $table->decimal('security_stock', 10, 2)->default(0)->after('low_stock_threshold');
            $table->decimal('max_stock', 10, 2)->nullable()->after('security_stock');
            $table->decimal('reorder_point', 10, 2)->nullable()->after('max_stock');
            $table->boolean('tracks_lots')->default(false)->after('reorder_point');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'brand', 'photo_path', 'supplier_sku', 'sale_unit', 'sale_unit_factor',
                'security_stock', 'max_stock', 'reorder_point', 'tracks_lots',
            ]);
        });
    }
};
