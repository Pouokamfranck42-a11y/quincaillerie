<?php

namespace App\Support;

/**
 * Les binaires PostgreSQL en ligne de commande (createdb/pg_restore...) écrivent leurs
 * messages d'erreur dans l'encodage de la console Windows (CP1252 en locale française),
 * pas en UTF-8 — un message contenant un accent devient une séquence d'octets invalide en
 * UTF-8, que Symfony Console/le terminal affichent alors comme une chaîne vide au lieu du
 * message (silencieux, repéré en testant app:restore-database-backup). Reconvertit
 * uniquement si le texte n'est PAS déjà de l'UTF-8 valide, pour ne rien casser sous Linux
 * en production (sortie déjà en UTF-8 là-bas).
 */
class ProcessOutput
{
    public static function toUtf8(string $output): string
    {
        if ($output === '' || mb_check_encoding($output, 'UTF-8')) {
            return $output;
        }

        return mb_convert_encoding($output, 'UTF-8', 'Windows-1252');
    }
}
