<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Une vente issue de la confirmation de paiement d'une commande web (Order::confirmPayment,
     * Phase 4) n'a ni session de caisse ni caissier — les deux étaient obligatoires jusqu'ici
     * car `sales` ne modélisait que le comptoir. Pas de doctrine/dbal disponible pour
     * `->change()` : DDL brut, plus portable.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE sales ALTER COLUMN cash_register_session_id DROP NOT NULL');
        DB::statement('ALTER TABLE sales ALTER COLUMN user_id DROP NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE sales ALTER COLUMN cash_register_session_id SET NOT NULL');
        DB::statement('ALTER TABLE sales ALTER COLUMN user_id SET NOT NULL');
    }
};
