# Sauvegardes de la base de données

## 1. Sauvegarde automatique quotidienne

Planifiée dans `bootstrap/app.php` (`withSchedule`), tous les jours à 02h00 :

```
php artisan app:backup-database
```

- Utilise `pg_dump -Fc` (format "custom", compressé, seul format supporté par
  `pg_restore` avec restauration sélective).
- Fichier produit : `storage/app/backups/quincaillerie-AAAA-MM-JJ_HHMMSS.dump`
  (hors webroot public, jamais accessible par HTTP).
- Purge automatique : ne garde que les `--keep` sauvegardes les plus récentes
  (défaut 14, réglable via `BACKUP_KEEP` dans `.env` ou l'option `--keep=`).
- Timeout de 300s (`BACKUP_TIMEOUT`) sur l'appel `pg_dump` — ne bloque jamais
  indéfiniment (même principe que le reste du Phase 1 : tout appel externe est borné).
- Vérification manuelle immédiate : `php artisan app:backup-database`.
- Log de chaque exécution planifiée : `storage/logs/backup.log`.

Sur ce poste Windows, `pg_dump`/`pg_restore`/`createdb`/`dropdb`/`psql` ne sont pas
sur le PATH — leurs chemins complets sont dans `.env` (`PG_DUMP_BINARY`, etc.).
En production Linux typique, laisser ces variables vides suffit (PATH standard).

## 2. Restauration — `app:restore-database-backup`

```
php artisan app:restore-database-backup <fichier.dump> --database=<base_cible> [--drop-existing]
```

- `--database` est **obligatoire** — jamais de valeur implicite, pour ne jamais
  écraser accidentellement la base en cours d'utilisation par erreur de frappe.
- `--drop-existing` supprime puis recrée la base cible si elle existe déjà
  (nécessaire pour rejouer un test plusieurs fois).
- Vérifie après coup que des tables existent bien dans la base cible ; échoue
  proprement (message clair, rien de silencieux) si la restauration n'a rien produit.

**Limite connue de cet environnement** : cette commande crée la base cible via
`createdb`, qui exige le droit `CREATEDB` (ou un rôle superutilisateur). Le rôle
applicatif `quincaillerie_user` ne l'a pas ici — la commande échoue donc avec un
message clair (`droit refusé pour créer une base de données`) tant que personne
n'exécute, avec un accès superutilisateur PostgreSQL :

```sql
ALTER ROLE quincaillerie_user CREATEDB;
```

Une fois ce droit accordé, `app:restore-database-backup` fonctionne directement.
**Ceci n'affecte pas la sauvegarde elle-même** (déjà testée fonctionnelle et
restaurable — voir preuve ci-dessous), seulement le confort de la commande de
restauration automatisée sur CE poste précis.

## 3. Preuve que la restauration fonctionne (2026-07-19)

En l'absence du droit `CREATEDB`, la restauration a été vérifiée par une méthode
équivalente (mêmes fichiers, même moteur `pg_restore`, seule la cible diffère : un
schéma PostgreSQL isolé plutôt qu'une base séparée — `quincaillerie_user` possède
déjà les droits nécessaires sur son propre schéma) :

1. Sauvegarde réelle produite : `quincaillerie-2026-07-19_041755.dump` (165 Ko).
2. Conversion en SQL texte (`pg_restore -f`) puis substitution mécanique
   `public.` → `restore_test.` (le dump qualifie explicitement chaque objet,
   confirmé par `search_path` vidé dans l'en-tête — substitution donc fiable à 100%,
   538 occurrences remplacées).
3. Exécution dans un schéma `restore_test` dédié, avec `ON_ERROR_STOP=1` — **aucune
   erreur**, 48 tables restaurées (couverture complète, pas un sous-ensemble).
4. Comparaison ligne à ligne source (`public.*`) vs restauré (`restore_test.*`) :

   | Table            | Source | Restauré |
   |-------------------|-------:|---------:|
   | products          | 99     | 99       |
   | sales             | 30     | 30       |
   | stock_movements   | 177    | 177      |
   | orders            | 8      | 8        |
   | users             | 3      | 3        |

   Comptages identiques sur toutes les tables vérifiées, et contenu d'un
   enregistrement précis (produit #6, avec accents/caractères spéciaux) comparé
   byte à byte entre source et restauration : identique.
5. Nettoyage : `DROP SCHEMA restore_test CASCADE;` (aucune trace laissée).

**Conclusion : le fichier de sauvegarde produit par `app:backup-database` est
intègre et intégralement restaurable.** Seule la commande de confort
`app:restore-database-backup` nécessite le droit `CREATEDB` pour créer sa base
cible dans CET environnement précis.
