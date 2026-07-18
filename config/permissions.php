<?php

/**
 * Catalogue source de vérité des permissions granulaires de l'application — utilisé à la fois
 * par le seeder (création des lignes en base) et par le composant de formulaire (grille de
 * cases à cocher groupées par module). Attribuer une permission à un compte reste 100% une
 * opération sur les DONNÉES (table `permissions`/`model_has_permissions`/`role_has_permissions`)
 * — ce fichier ne fait que déclarer les actions que le code sait vérifier, jamais qui y a droit.
 */
return [

    'produits' => [
        'label' => 'Produits',
        'permissions' => [
            'produits.voir' => 'Voir les produits',
            'produits.creer' => 'Créer un produit',
            'produits.modifier' => 'Modifier un produit',
            'produits.supprimer' => 'Supprimer un produit',
            'produits.importer' => 'Importer un catalogue (CSV / photo / OCR)',
        ],
    ],

    'prix' => [
        'label' => 'Prix',
        'permissions' => [
            'prix.modifier' => 'Modifier les prix (achat, vente, tarif pro)',
        ],
    ],

    'ecommerce' => [
        'label' => 'E-commerce',
        'permissions' => [
            'ecommerce.publier' => 'Publier/dépublier un produit sur la boutique',
            'ecommerce.commandes' => 'Gérer les commandes en ligne',
        ],
    ],

    'catalogue' => [
        'label' => 'Catalogue (catégories, familles)',
        'permissions' => [
            'catalogue.voir' => 'Voir les catégories et familles',
            'catalogue.gerer' => 'Gérer les catégories et familles',
        ],
    ],

    'fournisseurs' => [
        'label' => 'Fournisseurs',
        'permissions' => [
            'fournisseurs.voir' => 'Voir les fournisseurs',
            'fournisseurs.gerer' => 'Gérer les fournisseurs',
        ],
    ],

    'stock' => [
        'label' => 'Stock',
        'permissions' => [
            'stock.voir' => 'Voir les mouvements de stock',
            'stock.ajuster' => 'Ajuster le stock manuellement',
            'stock.inventaire' => "Faire un inventaire",
            'stock.transferer' => 'Transférer entre entrepôts',
        ],
    ],

    'achats' => [
        'label' => 'Achats fournisseurs',
        'permissions' => [
            'achats.voir' => 'Voir les commandes fournisseurs',
            'achats.gerer' => 'Créer/réceptionner/gérer les commandes fournisseurs',
        ],
    ],

    'ventes' => [
        'label' => 'Ventes comptoir',
        'permissions' => [
            'ventes.creer' => 'Créer une vente / un devis',
            'ventes.annuler' => 'Annuler une vente ou une ligne',
            'ventes.historique' => "Voir l'historique de ses propres ventes",
            'ventes.historique_tous' => "Voir l'historique de toutes les ventes",
        ],
    ],

    'caisse' => [
        'label' => 'Caisse',
        'permissions' => [
            'caisse.encaisser' => 'Encaisser au comptoir (POS)',
            'caisse.cloturer' => 'Ouvrir / clôturer une session de caisse',
            'caisse.journal' => 'Voir le journal de caisse',
        ],
    ],

    'clients' => [
        'label' => 'Clients',
        'permissions' => [
            'clients.voir' => 'Voir les clients',
            'clients.gerer' => 'Créer/modifier les clients, encaisser un paiement',
        ],
    ],

    'ia' => [
        'label' => 'Intelligence artificielle',
        'permissions' => [
            'ia.chatbot' => "Utiliser l'assistant IA",
            'ia.previsions' => 'Voir les prévisions de trésorerie',
        ],
    ],

    'rapports' => [
        'label' => 'Rapports',
        'permissions' => [
            'rapports.voir' => 'Voir les rapports (ventes, stock)',
            'rapports.exporter' => "Exporter les données comptables",
        ],
    ],

    'utilisateurs' => [
        'label' => 'Utilisateurs',
        'permissions' => [
            'utilisateurs.creer' => 'Créer et gérer les comptes utilisateurs',
            'utilisateurs.permissions' => 'Gérer les profils et attribuer des permissions',
        ],
    ],

    'configuration' => [
        'label' => 'Configuration système',
        'permissions' => [
            'configuration.systeme' => 'Entrepôts, journal d\'audit, corbeille',
        ],
    ],

];
