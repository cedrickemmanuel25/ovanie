# 02 — Écarts confirmés dans `web(3).zip`

Les éléments ci-dessous sont **reproduits dans le code actuel**. Cette étape ne les corrige pas encore.

| ID | Gravité | Domaine | Écart confirmé | Preuve principale |
|---|---|---|---|---|
| E01 | **P0** | Produit | `sale_type` n'a pas le même sens sur Web et App Vendeur | Web wizard vs `product_form_screen.dart` |
| E02 | **P0** | Produit | App Vendeur force `product_state = new` | `product_form_screen.dart` |
| E03 | **P0** | Boutique Entreprise | Laravel exige données/documents société que l'écran App Vendeur n'envoie pas | `ShopController.php` vs onboarding Flutter |
| E04 | P1 | Profil Boutique | Catégories codées localement dans l'édition profil App Vendeur | `profile_screens.dart` |
| E05 | **P0** | Profil Client | Profil Web/DB plus riche que l'API + modèle Flutter Client | `ClientAccountController` / `MobileClientAccountController` |
| E06 | **P0** | Checkout | Communes/quartiers du checkout proviennent encore d'un fichier Flutter local malgré `/mobile/delivery-territory` | `checkout_screen.dart` |
| E07 | P1 | Paiement | Opérateurs présents dans l'API et aussi dans une liste Flutter statique | capabilities + `resume_payment_screen.dart` |
| E08 | **P0** | Livreur | App n'envoie pas plusieurs champs acceptés/gérés par le backend : identité, année/capacité véhicule, horaires | repository vs `DriverOnboardingController` |
| E09 | **P0** | Livreur | Documents complémentaires sélectionnés mais non ajoutés au `FormData` | confirmation + `driver_repository.dart` |
| E10 | P1 | Profil Livreur | `DriverProfile` Flutter ignore une partie importante du dossier backend | `driver_profile.dart` |
| E11 | P1 | Commandes Vendeur | Labels/étapes/actions encore partiellement calculés localement dans Flutter | `order_ui.dart`, écrans commandes |
| E12 | **P0** | Commercial/Boutique | Commercial mobile limite `seller_type` à particulier/entreprise, Web/Vendeur autorisent 4 types | `CommercialMobileShopController.php` |
| E13 | P1/P0 à trancher Étape 2 | Commercial/Produit | Produit Commercial possède un writer/publish parallèle avec ses propres conditions | `CommercialMobileProductController.php` |
| E14 | P1 | Retours | Motifs/créneaux de retour définis localement dans l'App Client | `return_form_screen.dart` |
| E15 | P1 technique | Routes | Plusieurs anciens fichiers de routes coexistent ; l'entrée API principale reste `routes/api.php` | `bootstrap/app.php` |

## E01 — `sale_type` : conflit sémantique

### Web

Dans le wizard vendeur Web, `sale_type` représente une opération commerciale :
Vente normale, Vente flash, Black Friday, Promo spéciale.

### App Vendeur

Dans Flutter, `_saleTypes` représente :
unité, lot, poids, mètre, m², m³ et le payload écrit ces valeurs dans `sale_type`.

### Risque

Une ligne `products.sale_type = weight` peut être comprise comme un mode de vente par
l'application, alors que le Web attend un type de campagne commerciale.

**Ce point doit être tranché avant toute refonte du formulaire Produit.**

## E02 — état produit forcé

L'App Vendeur envoie `product_state: new` sans choix équivalent à Neuf /
Reconditionné / Occasion.

## E03 — Entreprise App Vendeur

Le backend Web exige pour une entreprise :
raison sociale, forme juridique, RCCM, numéro contribuable, document RCCM, document fiscal.

Le payload actuel de l'écran App Vendeur ne les contient pas.

## E05 — Profil Client

Le Web gère notamment :
`téléphone secondaire`, `date de naissance`, `genre`, `ville`, `type de compte`,
`langue`, `devise`, `fuseau horaire`, `format de date`, `avatar`.

Le modèle Flutter Client et l'endpoint mobile de mise à jour n'en couvrent qu'une partie.

## E06 — Checkout localités

`checkout_screen.dart` importe explicitement `abidjan_localities.dart`.
La route Laravel `/mobile/delivery-territory` existe mais n'est pas la source principale
du sélecteur de communes/quartiers du checkout.

## E08/E09 — Dossier Livreur

Le backend est plus riche que le formulaire envoyé par l'app.
Le cas `supportingDocuments` est un bug de transmission : la collection est présente dans
les données de confirmation, mais aucune boucle ne l'ajoute au `FormData`.

## E12/E13 — Commercial

Le Commercial écrit dans les mêmes tables mais via des contrats distincts.
Cela doit être inclus dans le contrat officiel de l'Étape 2 pour éviter qu'une boutique
ou un produit créé par le Commercial ait une structure différente.

# Points déjà alignés dans ce ZIP

Il est aussi important de ne pas corriger ce qui fonctionne déjà :

- **Types d'adresse Client** : récupérés dynamiquement depuis l'API, avec `Autre`.
- **Catalogue Client actif** : les catégories sont récupérées par les APIs marketplace ;
  `official_categories.dart` semble être un reliquat non référencé.
- **Zones Livreur** : l'écran actif charge les communes via l'API territoire.
- **Route API principale** : clairement `routes/api.php`.
