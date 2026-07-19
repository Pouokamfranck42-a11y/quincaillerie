<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase 4 — recherche floue (tolérante aux fautes de frappe) : jusqu'ici, toute recherche
 * produit (catalogue, POS, boutique, chatbot) était un ILIKE '%terme%' pur — une seule lettre
 * de travers ("percuteur" au lieu de "perforateur") et le résultat est vide. pg_trgm (trigrammes)
 * est l'extension standard PostgreSQL pour ça : similarity() donne un score de ressemblance,
 * les index GIN trigram le rendent rapide même sur un grand catalogue. SQL brut plutôt que le
 * Schema Builder : la syntaxe "USING GIN (colonne gin_trgm_ops)" avec classe d'opérateur n'a pas
 * d'équivalent fluent fiable dans Laravel.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        DB::statement('CREATE INDEX products_name_trgm_idx ON products USING GIN (name gin_trgm_ops)');
        DB::statement('CREATE INDEX products_reference_trgm_idx ON products USING GIN (reference gin_trgm_ops)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS products_name_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS products_reference_trgm_idx');
        // L'extension n'est volontairement pas désinstallée : d'autres objets pourraient en dépendre.
    }
};
