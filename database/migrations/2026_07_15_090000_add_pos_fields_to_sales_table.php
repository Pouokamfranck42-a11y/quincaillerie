<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('amount_tendered', 12, 2)->nullable()->after('paid_amount');
            $table->decimal('change_due', 12, 2)->nullable()->after('amount_tendered');
            $table->timestamp('cancelled_at')->nullable()->after('change_due');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['amount_tendered', 'change_due', 'cancelled_at']);
        });
    }
};
