# 07 — Mapping existant → contrat officiel

## Produit — conflit `sale_type`

Le mapping dépend de **l'origine historique** de la valeur.

### Valeurs Web historiques → `commercial_sale_type`

| Legacy | Cible |
|---|---|
| `Vente normale`, `normal` | `normal` |
| `Vente flash`, `vente flash`, `flash`, `flash_sale` | `flash_sale` |
| `Black Friday`, `black friday` | `black_friday` |
| `Promo spéciale`, `promo`, `promotion` | `promotion` |

### Valeurs App Vendeur historiques → `selling_mode`

| Legacy | Cible |
|---|---|
| `standard` | `unit` |
| `lot` | `lot` |
| `weight` | `weight` |
| `meter` | `linear_meter` |
| `surface` | `surface` |
| `volume` | `volume` |

**Conséquence importante :** une future migration de `products.sale_type` ne pourra pas deviner avec certitude l'origine d'une valeur ambiguë sans règles d'audit. L'Étape de migration devra produire un rapport avant écriture.

## Publication Produit

`actif`, `active`, `approved` → `active`.

`inactif`, `inactive` → `inactive`.

`brouillon`, `draft` → `draft`.

`pending_logistics` et `archived` restent identiques.

## Disponibilité Livreur

`Disponible` → `available`.

`Indisponible` → `unavailable`.

`En mission`, `En livraison` → `on_mission`.

## Paiement

Exemples d'alias :

- `waiting`, `initiated` → `pending` ;
- `success`, `successful`, `completed`, `validated`, `confirmed` → `paid` ;
- `error` → `failed` ;
- `canceled` → `cancelled`.

La liste machine complète est dans `generated/legacy-mapping.csv`.

## Champs de formulaire Web

Les noms camelCase du formulaire ouverture boutique (`sellerType`, `companyName`, `legalForm`, `taxpayerNumber`, etc.) sont des noms d'interface legacy. Le contrat API cible utilise les noms snake_case correspondant aux champs métier (`seller_type`, `company_name`, `legal_form`, `taxpayer_number`, etc.).
