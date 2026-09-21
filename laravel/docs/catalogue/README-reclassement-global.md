# OVANIE — Reclassement global des anciens produits

Cette correction traite les anciens produits encore enregistrés directement sur une catégorie principale.

Elle peut corriger en une seule opération :

- la catégorie principale ;
- la sous-catégorie.

Exemple : `Carrelage ... / Outillage & Équipement` peut devenir `Matériaux de finition / Carrelage` si les informations du produit donnent un score suffisamment sûr.

## Sécurité

La commande est en simulation par défaut. Elle ne touche jamais aux produits qui sont déjà rattachés à une sous-catégorie.

Les noms/références trop ambigus restent `A VERIFIER`.

## Validation installation

```bash
php docs/catalogue/validate_global_product_reclassification.php
```

## Simulation recommandée

```bash
php artisan optimize:clear
php artisan ovanie:reclassify-products-global --show-all
```

Pour comprendre une proposition :

```bash
php artisan ovanie:reclassify-products-global --product=106 --show-all --show-reasons
```

## Application

Après sauvegarde de la base et contrôle de la simulation :

```bash
php artisan ovanie:reclassify-products-global --apply
```

Puis relancer la simulation pour voir ce qu'il reste :

```bash
php artisan ovanie:reclassify-products-global --show-all
```

Aucune migration SQL n'est nécessaire pour ce correctif.
