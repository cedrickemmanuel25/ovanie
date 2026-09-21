# OVANIE — Socle commun Web ↔ Mobile — Étape 1

## Statut

**Étape 1 terminée : cartographie du contrat de données actuel.**

Cette étape est volontairement **non fonctionnelle** : aucun contrôleur, modèle, route, écran Web ou fichier Flutter n'a été modifié.  
Le but est de figer l'état réel du ZIP `web(3).zip` avant l'Étape 2, où le contrat cible OVANIE sera défini.

## Ce que contient cette étape

- inventaire des entités centrales Laravel ;
- inventaire des champs `$fillable` des modèles principaux ;
- identification de la source de routage API réellement chargée ;
- matrice Web ↔ API ↔ App Client ↔ App Vendeur ↔ App Livreur ↔ App Commercial ;
- liste des divergences **confirmées dans ce ZIP** ;
- inventaire des listes/référentiels encore codés localement dans Flutter ;
- inventaire des statuts/workflows déjà centralisés et de ceux encore interprétés localement ;
- checklist de validation avant de passer à l'Étape 2.

## Source API active

`web/bootstrap/app.php` charge `web/routes/api.php` comme fichier API principal.

Les fichiers `routes/api_mobile_auth.php`, `routes/api_vendor_mobile.php`,
`routes/VENDOR_NOTIFICATION_ROUTES.php` et autres fichiers historiques restent présents dans le projet,
mais **ils ne sont pas considérés comme la source API principale par cette cartographie** tant qu'ils ne sont pas explicitement chargés ailleurs.

## Entités auditées

| Entité | Modèle Laravel | Nombre de champs fillable |
|---|---|---:|
| User | `web/app/Models/User.php` | 29 |
| Shop | `web/app/Models/Shop.php` | 61 |
| Product | `web/app/Models/Product.php` | 95 |
| Order | `web/app/Models/Order.php` | 67 |
| Shipment | `web/app/Models/Shipment.php` | 35 |
| DeliveryAssignment | `web/app/Models/DeliveryAssignment.php` | 24 |
| DeliveryDriver | `web/app/Models/DeliveryDriver.php` | 30 |
| ReturnModel | `web/app/Models/ReturnModel.php` | 23 |
| Payment | `web/app/Models/Payment.php` | 16 |
| Address | `web/app/Models/Address.php` | 13 |

## Fichiers à lire

1. `01-cartographie-contrat-actuel.md` — vue d'ensemble par domaine.
2. `02-ecarts-confirmes.md` — écarts actuels à traiter à partir de l'Étape 2.
3. `03-referentiels-et-statuts.md` — où se trouvent aujourd'hui listes et statuts.
4. `04-validation-etape-01.md` — test de validation et captures à préparer.
5. `generated/contract-map.json` — inventaire machine-readable des modèles/champs.
6. `generated/surface-contract-matrix.csv` — matrice synthétique Web/API/Mobile.
7. `generated/laravel-model-fields.csv` — liste exhaustive des champs fillable audités.
8. `generated/flutter-local-reference-inventory.csv` — listes Flutter locales repérées.
9. `generated/active-mobile-route-index.txt` — index des routes mobiles actives pertinentes.
10. `generated/evidence-index.csv` — fichiers/lignes servant de preuves aux constats.

## Règle de passage à l'Étape 2

On passe à l'Étape 2 uniquement si :

- le rapport correspond bien aux écrans/comportements que vous observez ;
- les quatre divergences P0 principales sont reproduites/confirmées ;
- aucune donnée ou écran important n'a été oublié dans la cartographie ;
- aucune correction fonctionnelle n'est attendue de cette Étape 1.

L'Étape 2 définira ensuite **le contrat officiel OVANIE** (noms de champs, sens, valeurs autorisées et compatibilités), sans encore refaire tous les écrans.
