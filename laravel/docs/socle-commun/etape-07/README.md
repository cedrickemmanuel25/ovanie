# OVANIE — Socle commun — Étape 7

## Objectif

Retirer les anciens fallbacks métier locaux dans les blocs déjà validés à l'étape 6.
Laravel `GET /api/mobile/v1/reference-data` devient la source métier prioritaire et unique pour ces référentiels.

## Ce qui change

- App Client : communes/quartiers, types d'adresse et opérateurs de paiement ne retombent plus sur des listes Flutter codées en dur.
- App Vendeur : les meta boutique/produit ne retombent plus sur les anciens endpoints meta ; types produit, états produit et modes de vente viennent du référentiel Laravel.
- App Livreur : communes, jours et véhicules ne possèdent plus de tables locales de secours ; les conversions code/libellé utilisent Laravel.
- App Commercial : meta boutique/produit passent exclusivement par `reference-data`; les valeurs de secours Abidjan et les maps métier codées en dur sont retirées des blocs migrés.
- Le correctif du dropdown Commercial (`itemHeight: null`) est conservé.

## Comportement attendu en cas de panne API

Une ancienne liste locale ne doit plus apparaître silencieusement. L'écran doit rester vide ou afficher un message d'indisponibilité/actualisation selon le module.

## Nettoyage des deux fichiers historiques Client

Après copie du patch, exécuter depuis la racine `ovanie` :

```powershell
.\LOCAL-DEV\apply-step7-cleanup.ps1
```

Le script supprime uniquement, après vérification qu'ils ne sont plus importés :

- `ovanie_app/lib/features/checkout/data/abidjan_localities.dart`
- `ovanie_app/lib/features/categories/data/official_categories.dart`

## Validation

Depuis `laravel` :

```powershell
php docs/socle-commun/etape-07/validate_no_local_fallbacks.php
```

Puis exécuter `flutter analyze` dans les 4 applications.
