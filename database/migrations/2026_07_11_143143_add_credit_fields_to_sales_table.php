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
        Schema::table('sales', function (Blueprint $table) {
            $table->string('payment_status')->default('paid')->after('payment_method'); // paid | due
            $table->timestamp('due_date')->nullable()->after('payment_status');
            $table->decimal('paid_amount', 12, 2)->default(0)->after('due_date');
        });

        // Les ventes déjà enregistrées ont toutes été payées comptant au moment de l'encaissement.
        DB::table('sales')->update(['paid_amount' => DB::raw('total'), 'payment_status' => 'paid']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['payment_status', 'due_date', 'paid_amount']);
        });
    }
};
