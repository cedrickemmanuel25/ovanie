# Dossier `tools/`

Ce dossier contient des **scripts PHP de diagnostic et de développement** utilisés localement pour déboguer la plateforme OVANIE. Ces fichiers **ne sont pas exposés publiquement** et ne doivent pas être placés dans le dossier `public/`.

## Scripts disponibles

| Fichier | Description | Equivalent Artisan |
|---|---|---|
| `diag_delivery.php` | Diagnostic du moteur de livraison (poids, zones, tarifs) | `php artisan diag:delivery` |
| `sim_delivery.php` | Simulation de calcul de frais de livraison | — |
| `find_category_usage.php` | Recherche des usages de catégories dans le code | — |
| `find_controller_validation.php` | Recherche des validations dans les contrôleurs | — |
| `find_page_numbers_styles.php` | Recherche des styles de pagination CSS | — |
| `inspect_catalog_css.php` | Inspection des classes CSS du catalogue | — |
| `search_wizard.php` | Test du moteur de recherche | — |

## ⚠️ Important

- Ces scripts **bootstrap Laravel** manuellement et nécessitent `php scripts/nom_du_script.php` depuis la racine.
- Préférez les **commandes Artisan** équivalentes pour un usage en production.
- Ne jamais exposer ce dossier via un lien public.
