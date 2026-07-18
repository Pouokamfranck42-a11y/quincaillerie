<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Taux TVA réellement appliqué, figé au moment de l'émission (indépendant d'un futur changement de config). */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('tax_rate', 5, 2)->default(0)->after('tax_amount');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('tax_rate');
        });
    }
};
