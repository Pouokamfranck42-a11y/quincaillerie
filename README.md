# Quincaillerie — système de gestion commerciale

Application Laravel de gestion pour une quincaillerie : vente au comptoir (caisse), boutique en ligne, gestion de stock multi-entrepôts, achats fournisseurs, devis, service après-vente, rapports de pilotage, et un ensemble de fonctions assistées par IA (Google Gemini). Une seule base de données PostgreSQL est partagée entre le comptoir et la boutique en ligne.

## Documentation

- 📘 [Fiche technique](docs/fiche-technique.pdf) — architecture, modèle de données, noyau de stock unifié, intégrations (IA, paiement, facturation), installation et configuration. Public : équipe technique.
- 📗 [Manuel d'utilisation](docs/manuel-utilisation.pdf) — guide pas à pas de toutes les fonctionnalités, par profil (caisse, stock, achats, pilotage, administration, boutique en ligne). Public : personnel du magasin et clients de la boutique.
- [docs/sauvegardes.md](docs/sauvegardes.md) — procédure de sauvegarde et de restauration de la base de données.
- [docs/planification-taches.md](docs/planification-taches.md) — tâches planifiées de l'application.

## Prérequis

- PHP ^8.3
- PostgreSQL avec l'extension `pg_trgm` activée
- Composer

## Installation

```bash
composer install
cp .env.example .env
php artisan key:generate
# renseigner la connexion PostgreSQL dans .env (DB_CONNECTION=pgsql, DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD)
php artisan migrate --seed
php artisan serve
```

Le détail complet (variables d'environnement, clé Gemini, sauvegardes, déploiement) est dans la [fiche technique](docs/fiche-technique.pdf).

## Stack technique

Laravel ^13.8 · PHP ^8.3 · Livewire ^4.3 · spatie/laravel-permission ^8.3 · PostgreSQL · Google Gemini API.

## License

Logiciel propriétaire — usage interne.
