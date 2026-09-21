# 02 — Contrat officiel Produit

## Décision principale : séparation de `sale_type`

Les captures de validation montrent :

- Web Vendeur : « Type de vente » = Vente normale / Vente flash / Black Friday / Promo spéciale ;
- App Vendeur : « Type de vente » = Vente à l'unité / lot / poids / mètre / m² / m³.

Ces deux notions deviennent officiellement deux champs distincts.

### `commercial_sale_type`

| Code | Libellé |
|---|---|
| `normal` | Vente normale |
| `flash_sale` | Vente flash |
| `black_friday` | Black Friday |
| `promotion` | Promo spéciale |

### `selling_mode`

| Code | Libellé |
|---|---|
| `unit` | Vente à l'unité |
| `lot` | Vente par lot |
| `weight` | Vente au poids |
| `linear_meter` | Vente au mètre |
| `surface` | Vente au m² |
| `volume` | Vente au m³ |

Le champ legacy `products.sale_type` ne doit plus être alimenté par deux sémantiques après migration.

## État du produit

`product_state` est officiellement obligatoire à la publication :

- `new` — Neuf ;
- `reconditioned` — Reconditionné ;
- `used` — Occasion.

L'App Vendeur ne devra plus envoyer systématiquement `new` sans choix utilisateur.

## Unité de vente

`unit` reste distinct de `selling_mode`.

Exemples :

- `selling_mode=weight`, `unit=kg` ;
- `selling_mode=surface`, `unit=m2` ;
- `selling_mode=unit`, `unit=sac` ;
- `selling_mode=lot`, `unit=palette`.

Le contrat reprend les 15 unités déjà utilisées par le contrôleur Web : sac, tonne, m3, m2, ml, piece, palette, rouleau, seau, carton, paquet, barre, bidon, kg, litre.

## Type de produit

Le champ officiel reste `type`. Les codes initiaux retenus suivent l'App Vendeur existante : `materiau`, `outillage`, `equipement`, `consommable`, `autre`.

`material_grade` reste réservé à la qualité/grade matière et ne doit pas servir de substitut au « type de produit ».

## Publication

Le contrat cible normalise le statut de publication en :

- `draft` ;
- `pending_logistics` ;
- `active` ;
- `inactive` ;
- `archived`.

Les anciennes valeurs `actif`, `approved`, `inactif`, `brouillon` sont des alias legacy à migrer.

## Champs logistiques Produit

Sont communs Web/Mobile pour toute publication :

`weight_kg`, `length_cm`, `width_cm`, `height_cm`, `volume_m3`, `fragile`, `requires_unloading`, `unloading_instructions`.

## Règle writer unique

Web Vendeur, App Vendeur et App Commercial doivent à terme appeler les mêmes règles de validation/publication pour créer ou modifier un Produit.
