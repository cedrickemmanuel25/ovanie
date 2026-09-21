# Branchements effectués à l'étape 3

## App Vendeur

`VendorMobileController::meta()` utilise désormais `OvanieReferenceDataService` pour :

- catégories ;
- catégories boutique ;
- unités produit ;
- types vendeur ;
- types de pièce ;
- modes de paiement vendeur ;
- opérateurs Mobile Money ;
- zones ;
- types de logistique ;
- délais de préparation ;
- véhicules ;
- communes.

## App Commercial — Boutique

`CommercialMobileShopController::meta()` utilise le même service pour les catégories, communes/quartiers, régions, villes, identité, paiement, reversement, logistique et zones.

## App Commercial — Produit

`CommercialMobileProductController::meta()` ne possède plus sa propre liste d'unités produit.

## App Client — Adresse

`AddressController` récupère les types d'adresse depuis le contrat officiel. La validation du champ `type` utilise également cette même liste de codes.

## Checkout

`CheckoutPaymentOptionsService` construit les opérateurs du paiement en ligne depuis le registre central.

## Territoire livraison

`DeliveryTerritoryController` lit les zones actives via `OvanieReferenceDataService`, qui délègue à la logique OVANIE Logistics existante.
