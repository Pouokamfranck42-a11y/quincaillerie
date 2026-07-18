# Planification des tâches automatiques (Windows Task Scheduler)

L'application a plusieurs tâches qui doivent s'exécuter automatiquement, sans qu'un humain n'ait à cliquer sur un bouton :

- **`app:release-expired-reservations`** (toutes les 5 min) — annule les commandes web réservées mais jamais payées après 30 minutes, et relibère le stock correspondant. C'est ce qui empêche un panier abandonné de bloquer du stock indéfiniment.
- `app:check-expiring-lots` (quotidienne) — alerte sur les lots proches de péremption.
- `app:segment-customers` (hebdomadaire) — recalcule la segmentation IA des clients.
- `app:compute-cross-sell` (quotidienne) — recalcule les associations de ventes croisées.

Sous Linux, on planifierait `* * * * * php artisan schedule:run` dans le crontab et Laravel se chargerait de répartir chaque tâche selon sa propre fréquence. **Windows n'a pas de cron** — il faut Task Scheduler, avec une seule tâche qui appelle `schedule:run` chaque minute ; c'est Laravel qui décide ensuite, à chaque appel, laquelle de ses tâches internes doit réellement s'exécuter.

## 1. La commande à planifier

```
C:\php83\php.exe artisan schedule:run
```

- **Programme/script** : `C:\php83\php.exe`
- **Arguments** : `artisan schedule:run`
- **Dossier de démarrage (obligatoire)** : `G:\quincaillerie-app`

Le dossier de démarrage est indispensable — sans lui, `artisan` n'est pas trouvé (le chemin `artisan` est relatif à la racine du projet).

## 2. Notice pas à pas — créer la tâche planifiée

1. Ouvrir **Planificateur de tâches** (touche Windows → taper « Planificateur de tâches » → Entrée).
2. Dans le panneau de droite, cliquer **Créer une tâche de base…** (ou **Créer une tâche…** pour accéder directement à toutes les options, recommandé ici).
3. **Onglet Général**
   - Nom : `Quincaillerie - Laravel Scheduler`
   - Cocher **Exécuter que l'utilisateur soit connecté ou non**
   - Cocher **Exécuter avec les autorisations maximales**
4. **Onglet Déclencheurs** → **Nouveau…**
   - Commencer la tâche : **Selon une planification**
   - **Quotidienne**, heure de départ = maintenant
   - Cocher **Répéter la tâche toutes les** → choisir **1 minute**
   - Durée : **Indéfiniment**
   - Valider.
5. **Onglet Actions** → **Nouvelle…**
   - Action : **Démarrer un programme**
   - Programme/script : `C:\php83\php.exe`
   - Ajouter des arguments : `artisan schedule:run`
   - Commencer dans (obligatoire) : `G:\quincaillerie-app`
   - Valider.
6. **Onglet Conditions**
   - Décocher **Ne démarrer la tâche que si l'ordinateur est branché sur secteur** si c'est un ordinateur portable (le magasin doit continuer de tourner sur batterie).
7. **Onglet Paramètres**
   - Cocher **Si la tâche est déjà en cours d'exécution, la règle suivante s'applique** → **Ne pas démarrer une nouvelle instance** (évite les exécutions qui se chevauchent).
8. Valider — Windows demandera le mot de passe du compte Windows utilisé, pour pouvoir exécuter la tâche même hors session.

## 3. Vérifier que ça tourne

Après quelques minutes, dans le Planificateur de tâches, sélectionner la tâche → onglet **Historique** : on doit voir des occurrences « Tâche terminée » toutes les minutes.

## 4. Le journal d'exécution

La tâche `app:release-expired-reservations` écrit sa sortie dans :

```
G:\quincaillerie-app\storage\logs\schedule.log
```

Chaque exécution y ajoute une ligne du type `N commande(s) annulée(s) pour réservation expirée.` — c'est le log d'exécution demandé. Pour vérifier manuellement que tout fonctionne sans attendre Task Scheduler :

```
cd G:\quincaillerie-app
C:\php83\php.exe artisan app:release-expired-reservations
```

Les erreurs applicatives plus larges (exceptions PHP) continuent, elles, d'atterrir dans `storage\logs\laravel.log` comme pour le reste de l'application — rien de spécifique à changer là-dessus.

## État actuel (créée le 2026-07-14, à ta demande)

La tâche `Quincaillerie - Laravel Scheduler` est créée et active. Commande utilisée :

```
schtasks /create /tn "Quincaillerie - Laravel Scheduler" /tr "C:\php83\php.exe G:\quincaillerie-app\artisan schedule:run" /sc minute /mo 1 /f
```

(le chemin complet vers `artisan` est passé directement en argument plutôt que de compter sur un dossier de démarrage — évite toute ambiguïté, fonctionne à l'identique.)

**Limite importante** : la création avec `/ru SYSTEM` (exécution même hors session, recommandée au § 2) a été refusée — *Accès refusé* — car la session dans laquelle j'opère n'a pas les droits administrateur nécessaires pour élever une tâche à ce niveau. La tâche a donc été créée en repli sur le compte Windows courant, mode **« Interactive uniquement »** : elle ne tourne que **pendant que ta session Windows est ouverte** (verrouillée, ça continue de tourner ; complètement déconnecté ou avant login, non).

Pour un vrai serveur de magasin qui doit tourner même hors session (redémarrage, avant login), il faut repasser en mode SYSTEM :
1. Ouvrir **Planificateur de tâches** en tant qu'administrateur (clic droit → Exécuter en tant qu'administrateur).
2. Trouver la tâche `Quincaillerie - Laravel Scheduler` → Propriétés → onglet Général.
3. Cocher **Exécuter que l'utilisateur soit connecté ou non**, changer l'utilisateur en `SYSTEM` (bouton *Modifier l'utilisateur ou le groupe…* → taper `SYSTEM`).
4. Valider — aucun mot de passe requis pour SYSTEM.

**Vérifié en direct le 2026-07-14** : la tâche s'est déclenchée automatiquement à 07:15:00 et 07:16:01 (résultat 0 = succès à chaque fois), et `storage\logs\schedule.log` contient bien les lignes d'exécution de `app:release-expired-reservations`. Le pipeline complet (Windows Task Scheduler → `schedule:run` → cron interne Laravel → commande → log) est confirmé opérationnel.
