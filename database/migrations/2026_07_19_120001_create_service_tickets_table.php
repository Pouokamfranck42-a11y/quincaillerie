<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase 2 — SAV : dossier de retour/réparation/échange rattaché à une ligne de vente précise. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_line_id')->constrained('sale_lines')->cascadeOnDelete();
            $table->foreignId('opened_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('ouvert'); // ouvert | en_cours | resolu | refuse
            $table->string('resolution_type')->nullable(); // reparation | echange | remboursement | refuse
            $table->text('issue_description');
            $table->text('resolution_notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_tickets');
    }
};
