# Registre central Laravel

Le registre est défini dans `config/ovanie_reference_data.php`.

Il relie des noms stables utilisés par les applications aux enums du contrat officiel, par exemple :

- `product_states` → `product_state`
- `commercial_sale_types` → `commercial_sale_type`
- `selling_modes` → `selling_mode`
- `product_units` → `product_unit`
- `seller_types` → `seller_type`
- `address_types` → `address_type`
- `vehicle_types` → `vehicle_type`

Le service `OvanieReferenceDataService` fournit ensuite trois formes principales :

- `codes(...)` : liste simple de codes, pour compatibilité avec les endpoints actuels ;
- `options(...)` : `{code, label}` ;
- `valueOptions(...)` : `{value, label}` pour les endpoints qui utilisent encore ce format.

Cette couche permet à l'étape 4 de créer une API unifiée sans recopier les listes dans un nouveau contrôleur.
