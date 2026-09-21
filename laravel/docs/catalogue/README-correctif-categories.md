# Correctif catalogue — catégories et sous-catégories

## Ce qui est corrigé

1. Une seule catégorie ou sous-catégorie peut être sélectionnée à la fois.
2. Cliquer sur une autre catégorie remplace la précédente au lieu de l'ajouter.
3. Cliquer sur une autre sous-catégorie remplace la précédente.
4. Une catégorie principale affiche ses produits historiques encore rattachés au parent **et** les produits déjà classés dans ses sous-catégories.
5. Une sous-catégorie affiche uniquement les produits qui lui sont réellement rattachés.
6. Les compteurs du menu public tiennent compte des produits publics des sous-catégories.
7. Une commande de reclassement permet de déplacer automatiquement les anciens produits vers une sous-catégorie lorsqu'une correspondance est suffisamment sûre.

## Vérification technique

```bash
php docs/catalogue/validate_catalog_category_fix.php
```

## Reclassement des anciens produits

Toujours commencer par une simulation :

```bash
php artisan ovanie:reclassify-product-subcategories
```

La commande n'écrit rien en base par défaut.

Pour voir également les produits ambigus :

```bash
php artisan ovanie:reclassify-product-subcategories --show-all
```

Après contrôle des propositions, appliquer uniquement les classements sûrs :

```bash
php artisan ovanie:reclassify-product-subcategories --apply
```

Pour traiter une seule catégorie principale :

```bash
php artisan ovanie:reclassify-product-subcategories --parent=outillage-equipement
php artisan ovanie:reclassify-product-subcategories --parent=outillage-equipement --apply
```

Les produits ambigus restent dans leur catégorie principale. Aucun produit n'est dupliqué.
