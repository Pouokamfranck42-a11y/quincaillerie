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
];
