# OVANIE — Rapport de préparation à la production

Date : 18 juillet 2026

## Verdict final

**NON PRÊT POUR LA PRODUCTION**

Le code applicatif, les dépendances et les parcours métier validés sont prêts pour une recette de préproduction. Le dernier blocage réel est l'absence d'une base MySQL de préproduction saine : l'instance XAMPP locale a un `performance_schema` incomplet et les deux migrations en attente n'ont volontairement pas été appliquées sans confirmation.

## A. Blocages techniques réels

1. Créer une base MySQL de préproduction propre, restaurer une copie contrôlée des données puis exécuter et valider les migrations en attente. L'instance locale actuelle ne doit pas servir de référence de production (`performance_schema.session_status` absent).
2. Exécuter `documents:migrate-private --dry-run` sur une copie récente de la base de production. La base locale auditée contient 0 référence historique ; ce résultat ne prouve pas que la production n'en contient aucune.
3. Après sauvegarde et confirmation explicite seulement, appliquer `php artisan migrate --force` puis `php artisan documents:migrate-private` sur la préproduction.

## B. Actions manuelles cPanel

- pointer la racine web exclusivement vers `public/` ;
- conserver l'`APP_KEY`, poser `APP_ENV=production` et `APP_DEBUG=false` ;
- injecter les secrets uniquement dans `.env`, jamais dans le dépôt ;
- sauvegarder la base et `storage/app/private` avant migration ;
- configurer cron, worker de queue, HTTPS et permissions de `storage`/`bootstrap/cache` ;
- effectuer la recette client, vendeur, administration et logistique avant ouverture publique.

## C. Services volontairement désactivés

- OpenAI, WhatsApp/Meta, Twilio/téléphonie et SMS restent désactivés et ne génèrent aucune donnée fictive ;
- GPS temps réel/simulation locale reste désactivé ;
- PayDunya reste désactivé jusqu'à la recette de ses clés et signatures webhook ;
- paiement à la livraison reste disponible selon les règles métier.

## D. Migrations prêtes mais non exécutées

- `2026_07_17_000001_create_whatsapp_support_integration_tables.php` : additive, deux tables, clés étrangères valides, aucun appel externe ni donnée fictive ;
- `2026_07_18_000001_create_missing_laravel_infrastructure_tables.php` : additive et idempotente, crée seulement les tables Laravel manquantes (`sessions`, `cache`, `cache_locks`, `job_batches`, `failed_jobs`).

`php artisan migrate --pretend` réussit. Aucune migration réelle n'a été exécutée.

## E. Documents privés et résultat du dry-run

La commande `documents:migrate-private` couvre les KYC vendeurs, justificatifs de commande/paiement, preuves de livraison/retour/incident, identités coursier et reçus de reversement. Elle copie vers `storage/app/private`, vérifie taille et SHA-256, met à jour la base sous transaction puis supprime la source seulement après succès. Elle est relançable et ne journalise ni chemin personnel ni contenu.

Résultat local de `php artisan documents:migrate-private --dry-run` : **0 référence, 0 éligible, 0 manquant, 0 erreur** dans les neuf catégories. Aucune donnée ni aucun fichier n'a été modifié. Les nouveaux envois concernés utilisent le disque `local`, et les vues passent par neuf routes authentifiées avec contrôle de rôle/propriété.

Retour arrière documentaire : conserver avant exécution une sauvegarde cohérente de la base et des deux racines `storage/app/public` et `storage/app/private`. En cas d'échec, arrêter l'application, restaurer ces trois sauvegardes ensemble dans une base séparée, vérifier les empreintes puis basculer. Ne pas recopier les justificatifs vers le public sur une application ouverte.

## F. Résultat Composer

Avant correction : 25 avis sur 12 paquets. Mise à jour ciblée avec `--with-all-dependencies --minimal-changes`, sans changement de branche majeure final :

- Guzzle `7.10.0 → 7.15.1`, PSR-7 `2.8.0 → 2.13.0` ;
- Laravel `12.52.0 → 12.64.0` ;
- CommonMark `2.8.0 → 2.8.3`, phpseclib `3.0.49 → 3.0.55` ;
- composants Symfony corrigés dans leur branche 7.4 ; `polyfill-intl-idn 1.38.1` ;
- mises à jour transitives nécessaires de Promises et polyfills PHP.

Résultat : **0 avis Composer**. `composer.json` reste valide avec un avertissement non bloquant préexistant sur la contrainte non bornée de `twilio/sdk`.

## G. Tests

- validation post-Composer : **118 tests, 591 assertions, 0 échec** ;
- tests documents privés ajoutés : **3 tests, 11 assertions, 0 échec** (dry-run répété, propriétaire autorisé, autre vendeur interdit, aucune route `/storage`) ;
- suite finale complète après toutes les modifications : **121 tests, 602 assertions, 0 échec**.

## H. Build et caches

- `npm audit` : 0 vulnérabilité ;
- build Vite : réussi ;
- `config:cache`, `route:cache`, `view:cache`, `event:cache` : réussis après mise à jour Composer ;
- registre Laravel : 578 routes ; planning : 3 tâches ;
- `APP_DEBUG=false` dans `.env.example` et `.env.cpanel`.

## I. État Git

La racine exacte est `C:/Users/yaoce`, ajoutée seule à `safe.directory`. Le dossier `Downloads/ovanie/laravel/` apparaît entièrement non suivi dans le dépôt parent : aucun historique Git de ce sous-projet n'existe donc pour produire un diff fiable. Aucun fichier n'a été réinitialisé ou supprimé pour contourner ce constat.

## Fichiers principaux ajoutés ou modifiés dans cette phase

- `composer.lock`, `config/private_documents.php` ;
- `app/Console/Commands/MigrateSensitiveDocumentsToPrivate.php` ;
- `app/Http/Controllers/SensitiveDocumentController.php` et contrôleurs d'envoi de justificatifs ;
- `routes/web.php`, vues admin/logistique/vendeur concernées ;
- `database/migrations/2026_07_18_000001_create_missing_laravel_infrastructure_tables.php` ;
- `tests/Feature/PrivateDocumentSecurityTest.php` ;
- `.env.cpanel`, `PRODUCTION_READINESS_REPORT.md`, `DEPLOYMENT_CPANEL.md`.

Les corrections antérieures restent en place : suppression des scripts publics dangereux, inscription admin fermée, preuves de paiement/KYC privées, contrôles inter-vendeurs, intégrations externes désactivées et workflow financier corrigé.

## Commandes de validation exécutées

`git rev-parse --show-toplevel`, `git status`, `git diff --stat`, `git diff`, `composer validate --no-check-publish`, `composer audit --locked`, mise à jour Composer ciblée, `composer dump-autoload --optimize`, `npm audit`, `npm run build`, `php artisan optimize:clear`, `php artisan test`, `php artisan documents:migrate-private --dry-run`, `php artisan migrate:status`, `php artisan migrate --pretend`, `php artisan route:list`, `php artisan schedule:list`, `php artisan config:cache`, `route:cache`, `view:cache` et `event:cache`.

Tests en échec : **aucun**. Une répétition redondante de `php artisan test --compact` a été arrêtée après blocage de sa sortie ; la suite complète immédiatement précédente avait terminé avec code 0 et le test ciblé a été relancé séparément avec succès.
