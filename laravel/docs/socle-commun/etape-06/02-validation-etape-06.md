# Validation de l'étape 6

## 1. Validation statique du branchement

Depuis Laravel :

```bash
php docs/socle-commun/etape-06/validate_reference_consumption.php
```

Résultat attendu :

```text
OK — Étape 6 : applications branchées sur les référentiels Laravel
Laravel reference-data : OK
App Client : OK
App Vendeur : OK
App Livreur : OK
App Commercial : OK
Mode : Laravel prioritaire, fallback local conservé
Suppression des fallbacks : non
```

Si les projets mobiles se trouvent ailleurs que dans le workspace attendu, le script indique les applications qu'il n'a pas pu inspecter au lieu d'inventer une validation.

## 2. Analyse Flutter

Dans chacun des quatre projets :

```bash
flutter analyze
```

Les warnings/info déjà présents ne bloquent pas cette étape. Il ne doit pas y avoir de nouvelle ligne `error - ...` liée aux fichiers de l'étape 6.

## 3. Tests fonctionnels à faire après compilation des APK de test

### Client

- ouvrir Ajouter/Modifier une adresse : `Domicile`, `Bureau`, `Chantier`, `Dépôt`, `Autre` doivent être disponibles selon Laravel ;
- ouvrir le checkout : commune et quartier doivent se charger normalement ;
- ouvrir les moyens de paiement : les opérateurs actifs doivent correspondre au référentiel Laravel.

### Vendeur

- Ajouter un produit > Informations : **État du produit** doit apparaître ;
- les valeurs doivent être `Neuf / Reconditionné / Occasion` ;
- Prix & unité : le libellé doit être **Mode de vente**, avec les modes fournis par Laravel ;
- catégories et unités doivent continuer à fonctionner ;
- ouverture/modification boutique : catégories et principaux référentiels doivent se charger.

### Livreur

- onboarding : types de véhicule, jours et zones doivent s'afficher normalement ;
- les valeurs sélectionnées doivent continuer à être envoyées avec les codes backend attendus.

### Commercial

- ouverture boutique : catégories, zones, types de pièce et moyens de paiement doivent s'afficher ;
- les types vendeur restent volontairement `Particulier / Entreprise` tant que l'API Commercial n'a pas été harmonisée ;
- ajout produit : les catégories doivent venir du référentiel commun.

## Critère de passage

On ne supprime pas les anciens fallbacks tant que ces quatre groupes de tests ne sont pas validés sur les APK connectées à `test.ovanie.com`.
