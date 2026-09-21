# Consommation des référentiels Laravel par les applications

## Principe

Avant cette étape, plusieurs écrans Flutter possédaient encore leurs propres listes métier. Depuis cette étape, les écrans migrés lisent d'abord `/api/mobile/v1/reference-data`.

Le comportement est :

```text
Application
    ↓
/api/mobile/v1/reference-data
    ↓ OK
Référentiel Laravel utilisé

ou

Application
    ↓
API indisponible temporairement
    ↓
Fallback local existant
```

Ce fallback est volontairement conservé pour une migration progressive et réversible.

## Produit Vendeur

Deux notions sont maintenant séparées dans l'interface mobile :

- **État du produit** : `new`, `reconditioned`, `used` ;
- **Mode de vente** : `unit`, `lot`, `weight`, `linear_meter`, `surface`, `volume`.

Le formulaire mobile conserve temporairement l'ancien champ `sale_type` en plus de `selling_mode` pour ne pas casser le backend historique avant la migration métier Produit.

## Limite volontaire App Commercial

Le référentiel Laravel expose les types vendeur canoniques. Le contrôleur Commercial actuel n'accepte cependant encore que `particulier` et `entreprise`. L'application Commercial consomme donc les **libellés Laravel** mais n'expose pas encore `artisan` et `grossiste` afin de ne pas permettre l'envoi d'une valeur refusée par l'API existante.

Cette limite doit être levée dans le chantier d'harmonisation Boutique/Commercial, pas silencieusement à cette étape.
