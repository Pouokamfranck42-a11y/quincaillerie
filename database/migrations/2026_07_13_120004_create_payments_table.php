<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Journal des paiements — distinct de orders/sales pour permettre l'IDEMPOTENCE des
     * webhooks (Phase 6) : `provider_reference` est unique, un même événement rejoué par
     * l'agrégateur Mobile Money ne peut donc pas créer une deuxième confirmation.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->morphs('payable'); // Order, Sale...
            $table->decimal('amount', 12, 2);
            $table->string('method'); // especes | carte | mobile_money_mtn | mobile_money_orange | credit
            $table->string('status')->default('pending'); // pending | success | failed | refunded
            $table->string('provider')->nullable(); // mtn_momo | orange_money | null (especes/carte/credit)
            $table->string('provider_reference')->nullable()->unique();
            $table->jsonb('raw_payload')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
