# 03 — Référentiels et statuts : sources actuelles

## Référentiels

| Référentiel | Source Laravel/API actuelle | Source Flutter/Web parallèle | État Étape 1 |
|---|---|---|---|
| Catégories produit | `Category`, APIs marketplace, `/mobile/v1/vendor/meta` | Pas de source Client active parallèle identifiée | Plutôt aligné |
| Catégorie profil boutique Vendeur | `Category` / meta | `profile_screens.dart` contient une liste locale | **Double source** |
| Types vendeur | Vendor meta = 4 types | Commercial mobile store = 2 types | **Divergent** |
| États produit | Validation Vendor = new/reconditioned/used | App Vendeur force new | **Divergent** |
| Type commercial / `sale_type` | Web = normal/flash/Black Friday/promotion | App Vendeur = unité/lot/poids/mètre/m²/m³ | **Conflit P0** |
| Unités produit | Vendor meta `product_units` | Divers écrans peuvent reconstruire des labels | À normaliser |
| Communes/quartiers Client | `/mobile/delivery-territory` | `abidjan_localities.dart` dans checkout | **Double source** |
| Types d'adresse | `AddressController::addressTypes()` | App les lit depuis réponse API | **Aligné** |
| Opérateurs paiement | `mobile/client/capabilities` | `_operators` dans reprise paiement | **Double source** |
| Types identité boutique | Vendor meta | Écran utilise les valeurs API | Plutôt aligné |
| Types véhicule | Backend/meta | constantes onboarding Livreur | Dupliqué |
| Jours disponibilité | backend valide les valeurs | constantes onboarding Livreur | Dupliqué |
| Motifs/créneaux retour | pas de meta unique identifiée | listes formulaire Client | Local uniquement |
| Zones Livreur | `/driver/territory/communes` | écran Zones utilise l'API | **Aligné actif** |

## Statuts / workflows backend repérés

### Boutique

- `status`: approved / rejected
- `kyc_status`: pending / verified / rejected
- `logistics_status`: ready / incomplete / suspended
- `payment_mode`: post_delivery / weekly

### Livreur

`onboarding_status` :
- invited
- pending_review
- active
- suspended
- rejected

### Livraison commande

`OrderWorkflowService` gère notamment :
- pending
- preparing
- ready_for_pickup
- assigned
- picked_up
- in_transit
- delivered
- late
- failed
- returned
- cancelled
- delivery_failed
- not_required

Providers repérés :
- ovanie
- seller
- pickup
- partner

### Retour

Statut métier :
- pending
- accepted
- rejected
- closed
- refunded

Statut logistique :
- pending_pickup
- return_pickup_planned
- return_in_transit
- return_received
- refund_pending
- refunded
- claim_review
- refund_review

### Paiement

Les états principaux repérés comprennent :
- pending
- paid
- failed
- cancelled
- refunded
- escrow_held
- released_to_vendor

## Observation importante

Le fait qu'un statut existe côté Laravel ne garantit pas encore que chaque application
utilise uniquement son libellé et ses actions serveur. L'App Vendeur possède encore une
partie de la traduction/progression en local. Cela sera tranché à l'Étape 2.
