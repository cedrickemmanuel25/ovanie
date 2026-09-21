# OVANIE — Socle commun Web ↔ Mobile — Étape 2

## Objet

Cette étape **définit le contrat métier officiel OVANIE** à partir de la cartographie validée à l'Étape 1 et des captures de test fournies.

Elle ne refait aucun écran et ne migre encore aucune donnée. Le contrat est volontairement **déclaratif** (`runtime_enabled=false`).

La source machine lisible du contrat est :

- `web/config/ovanie_contract.php`
- miroir JSON : `generated/ovanie-contract.v1.json`

## Décisions majeures figées

1. Un même champ ne peut plus avoir deux sens selon Web/Mobile.
2. `products.sale_type` est reconnu comme champ legacy ambigu et doit être séparé en :
   - `commercial_sale_type` : normal / flash_sale / black_friday / promotion ;
   - `selling_mode` : unit / lot / weight / linear_meter / surface / volume.
3. `product_state` est obligatoire dans le contrat Produit pour tous les writers : Web Vendeur, App Vendeur, Commercial.
4. Les quatre types vendeur officiels sont : `particulier`, `entreprise`, `artisan`, `grossiste`.
5. Les champs légaux Entreprise sont communs à tous les parcours de création boutique.
6. Le profil Client officiel comprend les champs Web aujourd'hui absents du payload mobile.
7. Les types d'adresse existants sont déjà retenus comme canoniques.
8. Le dossier Livreur officiel contient identité, véhicule, disponibilité, zones et documents ; le livreur reste un **partenaire**.
9. `vendor_status` est un statut de préparation vendeur. Pour OVANIE Logistics, sa responsabilité opérationnelle s'arrête à `ready`; la suite relève de `delivery_status`.
10. Les statuts de mission Livreur sont ceux du workflow moderne `DriverMissionService`.
11. Les statuts/méthodes de paiement sont normalisés côté backend et les alias historiques ne deviennent pas des valeurs officielles.

## Fichiers

- `01-principes-contrat-officiel.md` : règles communes.
- `02-contrat-produit.md` : Produit et résolution du conflit `sale_type`.
- `03-contrat-boutique-vendeur.md` : Vendeur/Boutique.
- `04-contrat-client-adresse.md` : Client et Adresse.
- `05-contrat-livreur-logistique.md` : Livreur/Véhicule/Mission.
- `06-contrat-commande-livraison-retour-paiement.md` : workflows transverses.
- `07-mapping-existant-vers-officiel.md` : compatibilité legacy → contrat cible.
- `08-validation-etape-02.md` : test de validation avant Étape 3.
- `validate_contract.php` : validateur autonome, sans dépendance Composer/Laravel.

## Important

Aucun contrôleur, écran Flutter, vue Blade, migration ou table n'est modifié dans cette étape. Les corrections fonctionnelles commenceront après validation du contrat.

> `definition_version` versionne uniquement ce document de contrat. Le `schema_version` exposé aux APK sera ajouté à une étape ultérieure du socle.
