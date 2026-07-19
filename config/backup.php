<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Binaires pg_dump / pg_restore
    |--------------------------------------------------------------------------
    |
    | Par défaut on suppose pg_dump/pg_restore sur le PATH (cas Linux typique en
    | production). Sur ce poste de développement Windows, PostgreSQL n'ajoute pas
    | ses binaires au PATH — PG_DUMP_BINARY/PG_RESTORE_BINARY pointent vers le
    | chemin complet dans .env local.
    |
    */

    'pg_dump_binary' => env('PG_DUMP_BINARY', 'pg_dump'),
    'pg_restore_binary' => env('PG_RESTORE_BINARY', 'pg_restore'),
    'createdb_binary' => env('PG_CREATEDB_BINARY', 'createdb'),
    'dropdb_binary' => env('PG_DROPDB_BINARY', 'dropdb'),
    'psql_binary' => env('PG_PSQL_BINARY', 'psql'),

    /** Répertoire de stockage des sauvegardes (hors webroot public). */
    'path' => storage_path('app/backups'),

    /** Nombre de sauvegardes conservées par défaut avant purge des plus anciennes. */
    'keep' => env('BACKUP_KEEP', 14),

    /** Timeout (secondes) sur l'appel pg_dump/pg_restore — ne doit jamais bloquer indéfiniment. */
    'timeout' => env('BACKUP_TIMEOUT', 300),

];
