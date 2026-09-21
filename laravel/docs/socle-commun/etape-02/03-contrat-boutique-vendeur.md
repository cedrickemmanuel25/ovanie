# 03 — Contrat officiel Boutique / Vendeur

## Types vendeur

Les quatre codes officiels sont :

| Code | Libellé |
|---|---|
| `particulier` | Particulier |
| `entreprise` | Entreprise |
| `artisan` | Artisan |
| `grossiste` | Grossiste |

L'App Commercial ne doit donc plus limiter le contrat à `particulier` et `entreprise`.

## Compte propriétaire

Les données du propriétaire restent celles de `users` : prénom, nom, email, téléphone et mot de passe lors de la création initiale.

## Entreprise

Lorsque `seller_type=entreprise`, les données suivantes sont obligatoires dans tous les parcours :

- `company_name` ;
- `legal_form` ;
- `rccm` ;
- `taxpayer_number` ;
- `rccm_file` ;
- `tax_file`.

Les formes juridiques officielles sont : `sarl`, `sarlu`, `sa`, `sas`, `ei`, `cooperative`, `autre`.

## Boutique

Les champs communs incluent au minimum : nom, description, logo public, selfie KYC, catégorie principale, localisation, coordonnées professionnelles et paramètres logistiques.

`main_category` reste pour l'instant un **slug de catégorie Laravel**, conformément au Web actuel. L'Étape 3 centralisera la fourniture du référentiel.

## Localisation

La structure officielle conserve : région, ville, commune, `commune_id`, district/quartier, `quarter_id`, repère, adresse, latitude et longitude.

Lorsque `logistics_type=ovanie`, la position GPS doit être disponible conformément aux validations existantes.

## Logistique

Codes officiels :

- `ovanie` — OVANIE Logistics ;
- `seller` — Logistique vendeur.

Les écrans peuvent adapter les champs visibles selon le choix, mais doivent persister le même contrat.

## KYC identité

Types officiels : `cni`, `passport`, `permis`, `resident`.

Les fichiers PDF/scan restent des supports du même dossier d'identité ; ils ne créent pas des identités différentes selon l'interface.

## Reversement vendeur

Le calendrier officiel est :

- `post_delivery` ;
- `weekly`.

Les opérateurs Mobile Money officiels sont : `orange`, `mtn`, `wave`, `moov`.

## Règle commune

Le Web, l'App Vendeur et l'App Commercial doivent produire à terme la **même boutique** pour les mêmes réponses utilisateur.
