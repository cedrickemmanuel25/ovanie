# OVANIE — Socle commun — Étape 6

## Objectif

Faire consommer **en priorité** les référentiels Laravel exposés par :

```text
GET /api/mobile/v1/reference-data
```

aux applications Client, Vendeur, Livreur et Commercial, sans supprimer immédiatement les anciens fallbacks locaux.

## Règle de migration

À cette étape :

- Laravel devient la source prioritaire des référentiels ;
- les listes locales existantes restent comme secours en cas d'indisponibilité temporaire ;
- aucune panne réseau ne doit empêcher l'ouverture d'une application ;
- aucune liste locale n'est encore supprimée définitivement ;
- les suppressions seront faites seulement après validation du comportement réel sur `test.ovanie.com` et les APK.

## Branches migrées

### App Client

- types d'adresse ;
- communes et quartiers du checkout ;
- opérateurs de paiement ;
- chargement du référentiel commun au démarrage.

### App Vendeur

- catégories boutique et produit ;
- unités produit ;
- types produit ;
- états produit ;
- modes de vente ;
- types vendeur ;
- formes juridiques ;
- types de pièce ;
- zones et modes logistiques ;
- moyens de reversement ;
- régions/villes/communes ;
- chargement du référentiel commun au démarrage.

Le formulaire Produit affiche désormais explicitement **État du produit** et utilise **Mode de vente** pour `unit / lot / weight / linear_meter / surface / volume`. Le champ historique `sale_type` est encore envoyé en compatibilité tant que la migration métier Produit n'est pas terminée.

### App Livreur

- types de véhicules ;
- jours de disponibilité ;
- communes/zones d'intervention ;
- correspondances libellé ↔ code backend ;
- chargement du référentiel commun au démarrage.

### App Commercial

- catégories boutique ;
- catégories produit ;
- communes ;
- régions/villes ;
- types de pièce ;
- pays de délivrance ;
- zones de livraison ;
- modes de paiement/reversement ;
- types vendeur **dans la limite actuellement acceptée par l'API Commercial** (`particulier`, `entreprise`).

## Validation

Depuis la racine Laravel :

```bash
php docs/socle-commun/etape-06/validate_reference_consumption.php
```

Puis exécuter `flutter analyze` dans les quatre projets mobiles.
