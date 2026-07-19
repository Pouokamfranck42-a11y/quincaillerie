<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Identité légale de l'entreprise (Phase 6 — facturation)
    |--------------------------------------------------------------------------
    |
    | Tant que 'niu' ou 'rccm' sont vides, les factures générées par l'application
    | affichent un bandeau "document non conforme" — voir invoices/show.blade.php.
    | Ces valeurs n'ont AUCUNE valeur légale par défaut : elles doivent être
    | renseignées avec les vraies informations de l'entreprise avant tout usage réel.
    |
    */

    'name' => env('COMPANY_NAME', env('APP_NAME', 'Quincaillerie')),
    'niu' => env('COMPANY_NIU'),
    'rccm' => env('COMPANY_RCCM'),
    'address' => env('COMPANY_ADDRESS'),
    'phone' => env('COMPANY_PHONE'),

    /** Si false (défaut), la TVA n'est pas appliquée sur les factures générées — à confirmer avec un comptable. */
    'vat_subject' => (bool) env('COMPANY_VAT_SUBJECT', false),

    /** Taux TVA camerounais standard cité dans le brief — appliqué seulement si vat_subject est vrai. */
    'vat_rate' => (float) env('COMPANY_VAT_RATE', 19.25),

    /*
    |--------------------------------------------------------------------------
    | Programme de fidélité (Phase 2)
    |--------------------------------------------------------------------------
    |
    | earn_per_fcfa : FCFA dépensés pour gagner 1 point (1000 = 1 point tous les 1000 FCFA).
    | redeem_value : valeur en FCFA d'1 point utilisé en réduction à l'encaissement.
    | Uniquement sur les ventes réglées (pas à crédit tant que la créance n'est pas soldée,
    | pas sur un client de passage sans fiche).
    |
    */
    'loyalty' => [
        'enabled' => (bool) env('LOYALTY_ENABLED', true),
        'earn_per_fcfa' => (int) env('LOYALTY_EARN_PER_FCFA', 1000),
        'redeem_value' => (float) env('LOYALTY_REDEEM_VALUE', 10),
    ],
];
