<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('purchase_order_lines', function (Blueprint $table) {
            $table->decimal('received_quantity', 10, 2)->default(0)->after('quantity');
        });

        // Les commandes déjà réceptionnées dans l'ancien flux (tout ou rien) sont considérées soldées.
        DB::statement('
            UPDATE purchase_order_lines
            SET received_quantity = quantity
            FROM purchase_orders
            WHERE purchase_order_lines.purchase_order_id = purchase_orders.id
            AND purchase_orders.status = \'received\'
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_order_lines', function (Blueprint $table) {
            $table->dropColumn('received_quantity');
        });
    }
};
