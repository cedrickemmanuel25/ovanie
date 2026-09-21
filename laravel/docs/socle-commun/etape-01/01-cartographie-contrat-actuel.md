# 01 — Cartographie du contrat actuel

> Ce document décrit **ce qui existe aujourd'hui**. Il ne décide pas encore ce que le contrat final doit devenir.

## 1. Architecture observée

```text
Web OVANIE ──────────────┐
App Client ──────────────┤
App Vendeur ─────────────┤
App Livreur ─────────────┼── API Laravel ── Modèles / base OVANIE
App Commercial ──────────┘
```

Le point d'entrée API principal est `web/routes/api.php`, chargé par `web/bootstrap/app.php`.

## 2. Client / User

### Backend

Le modèle `User` possède notamment les champs de profil :
`first_name`, `last_name`, `name`, `email`, `phone`, `whatsapp_phone`,
`avatar`, `secondary_phone`, `birth_date`, `gender`, `city`, `account_type`,
`locale`, `currency`, `timezone`, `date_format`, ainsi que des champs de fidélité et préférences.

### Web Client

Le profil Web permet de gérer un contrat plus large : téléphone secondaire,
date de naissance, genre, ville, type de compte, langue, devise, fuseau horaire,
format de date et avatar, en plus des données d'identité de base.

### App Client

Le modèle Flutter `ClientProfile` ne lit actuellement que :
`id`, `first_name`, `last_name`, `name`, `email`, `phone`, `whatsapp_phone`, `status`.

L'endpoint mobile de mise à jour du profil ne valide qu'un sous-ensemble équivalent
(prénom, nom, email, téléphone, WhatsApp).

**Conclusion actuelle : contrat Client partiellement commun.**

## 3. Adresses Client

Dans ce ZIP, les types d'adresse sont déjà fournis par Laravel :
`home/Domicile`, `office/Bureau`, `site/Chantier`, `warehouse/Dépôt`, `other/Autre`.

L'App Client consomme la liste `types` renvoyée par l'API.

**Conclusion actuelle : ce point est déjà mieux aligné et ne doit pas être reclassé comme bug P0.**

## 4. Checkout / localisation Client

Laravel expose `/mobile/delivery-territory`.

Cependant le sélecteur du checkout Flutter importe encore
`abidjan_localities.dart` et utilise `kAbidjanCommunes` /
`kAbidjanQuartiersByCommune`.

**Conclusion actuelle : deux sources de territoire coexistent.**

## 5. Catalogue Client

Le fichier Flutter `official_categories.dart` existe encore, mais aucune référence/import
actif n'a été trouvé dans l'App Client. Les écrans catalogue actifs passent par les API
marketplace/catégories.

**Conclusion actuelle : le vieux fichier est un reliquat, pas la source active du catalogue Client.**

## 6. Boutique / Vendeur

### Contrat Laravel/Web

Le modèle `Shop` contient 61 champs fillable. L'ouverture de boutique Web prend notamment en charge :

- identité vendeur ;
- type vendeur : particulier, entreprise, artisan, grossiste ;
- informations société pour Entreprise ;
- KYC / identité ;
- boutique et catégorie ;
- localisation structurée et GPS ;
- logistique ;
- coordonnées professionnelles ;
- calendrier de reversement / Mobile Money ;
- états de validation.

Pour `sellerType=entreprise`, Laravel exige actuellement :
`companyName`, `legalForm`, `rccm`, `taxpayerNumber`, `rccmFile`, `taxFile`.

### App Vendeur

L'écran d'onboarding envoie le noyau vendeur/boutique/localisation/KYC/paiement,
mais n'envoie pas les champs société ci-dessus.

Le repository sait accepter certains fichiers société en paramètres, mais l'écran courant
ne les fournit pas.

**Conclusion actuelle : l'ouverture Entreprise Web et App Vendeur n'a pas le même contrat d'entrée.**

### Profil boutique mobile

L'édition du profil boutique contient encore une table Flutter locale de catégories
(`ciment-beton`, `fer-metaux`, `carrelage`, etc.), alors que Laravel possède son propre
référentiel Category.

**Conclusion actuelle : double source active sur ce sous-écran.**

## 7. Produit

Le modèle `Product` est l'un des contrats les plus larges du projet (95 champs fillable).

### État du produit

Le Web propose :
- `new` = Neuf ;
- `reconditioned` = Reconditionné ;
- `used` = Occasion.

L'App Vendeur envoie actuellement `product_state = new` de manière fixe.

### Champ `sale_type`

Le Web utilise `sale_type` pour une logique commerciale :
- Vente normale ;
- Vente flash ;
- Black Friday ;
- Promo spéciale.

L'App Vendeur utilise **le même champ** pour une logique de conditionnement/mode de vente :
- `standard` = Vente à l'unité ;
- `lot` = Vente par lot ;
- `weight` = Vente au poids ;
- `meter` = Vente au mètre ;
- `surface` = Vente au m² ;
- `volume` = Vente au m³.

**Conclusion actuelle : conflit sémantique P0. Le même champ représente deux concepts différents.**

## 8. Commande / Livraison

Laravel possède un workflow de livraison centralisé (`OrderWorkflowService`) et les
payloads Client mobile contiennent déjà des labels/actions calculés côté serveur.

L'App Vendeur conserve cependant des fonctions locales de traduction/progression des statuts
(`orderStatusLabel`, `orderWorkflowStep`) et certains écrans déduisent encore les actions à
partir de `vendor_status` / `delivery_status`.

**Conclusion actuelle : backend centralisé partiellement, interprétation Vendeur encore dupliquée.**

## 9. Livreur / Logistique

### Backend

L'onboarding backend accepte notamment :
- `birth_date` ;
- `identity_type`, `identity_number`, `identity_document` ;
- `vehicle`, `plate`, couleur ;
- `vehicle_year`, `capacity` ;
- documents véhicule ;
- `supporting_documents[]` ;
- `work_hours` ;
- jours et zones de disponibilité ;
- photo du profil.

### App Livreur

Le repository mobile envoie :
- véhicule, plaque ;
- couleur détectée ;
- jours et zones ;
- photo profil ;
- carte grise ;
- photo véhicule ;
- photo plaque.

Il n'envoie pas actuellement :
`birth_date`, `identity_type`, `identity_number`, `identity_document`,
`vehicle_year`, `capacity`, `work_hours`.

De plus, `supportingDocuments` est bien transporté jusqu'au `VehicleFiles` de confirmation,
mais n'est pas ajouté au `FormData` envoyé au backend.

Le modèle Flutter `DriverProfile` ne lit également qu'un sous-ensemble du profil renvoyé.

**Conclusion actuelle : contrat Livreur mobile incomplet par rapport au backend/Logistique.**

## 10. App Commercial

Le flux Commercial utilise les mêmes entités `User`, `Shop` et `Product`, mais via des
contrôleurs mobiles spécifiques.

Exemple confirmé : `CommercialMobileShopController` n'accepte actuellement que
`particulier` et `entreprise`, alors que le contrat boutique Web/Vendeur autorise aussi
`artisan` et `grossiste`.

Le produit Commercial possède également son propre contrôleur de saisie/publish, avec
ses propres exigences de publication.

**Conclusion actuelle : mêmes tables, contrats d'entrée parallèles.**

## 11. Retour

Le backend stocke un `reason` et plusieurs informations de préparation/retrait.
Le formulaire Client possède encore ses propres motifs et créneaux prédéfinis.

**Conclusion actuelle : liste UX locale sans référentiel backend unique identifié.**

## 12. Paiement

Le backend expose déjà les opérateurs pris en charge dans les capacités Client :
Orange, MTN, Wave, Moov.

L'écran mobile de reprise de paiement conserve aussi une liste statique locale.

**Conclusion actuelle : valeurs cohérentes aujourd'hui, mais double source pouvant diverger.**

## 13. Inventaire exhaustif des champs

Voir `generated/laravel-model-fields.csv` et `generated/contract-map.json`.
