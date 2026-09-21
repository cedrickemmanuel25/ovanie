# Correctif filtre catalogue OVANIE

Ce correctif rend la navigation des catégories indépendante du JavaScript mis en cache.

## Comportement

- Cliquer sur une catégorie ouvre `/catalog?category=<slug>`.
- Cliquer sur une autre catégorie remplace la précédente.
- Cliquer sur une sous-catégorie ouvre `/catalog?category=<slug-sous-categorie>`.
- Une seule catégorie/sous-catégorie est active à la fois.
- Une catégorie racine sans sous-catégorie reste filtrable normalement.
- Une catégorie racine avec sous-catégories affiche son univers complet côté API.
- Une sous-catégorie affiche uniquement ses produits.

## Validation

```bash
php docs/catalogue/validate_catalog_click_filter.php
```

## Important pour cPanel

Si le DocumentRoot public de production est séparé de `~/laravel/public`, recopier également le fichier JavaScript :

```bash
cp ~/laravel/public/js/catalog.js ~/public_html/public/js/catalog.js
```

Le filtre catégorie reste néanmoins fonctionnel même si l’ancien JS est encore en cache, car les catégories sont désormais de vrais liens HTTP.
