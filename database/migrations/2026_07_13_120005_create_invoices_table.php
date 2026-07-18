<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Squelette minimal — non branché à un contrôleur, non utilisé par aucun code métier.
     * La numérotation séquentielle sans saut (obligation légale camerounaise), les
     * mentions NIU/RCCM/TVA et l'intégration à une solution homologuée DGI sont du
     * ressort de la Phase 6 ; ce n'est PAS conforme à ce stade, uniquement la table.
     */
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->morphs('invoiceable'); // Sale, Order...
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('number')->unique();
            $table->decimal('subtotal', 12, 2);
            $table->decimal('tax_amount', 12, 2);
            $table->decimal('total', 12, 2);
            $table->timestamp('issued_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
