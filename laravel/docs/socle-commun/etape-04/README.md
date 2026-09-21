# Socle commun Web ↔ Mobile — Étape 4

## Objectif

Exposer la source Laravel centralisée de l'étape 3 via une API mobile commune, en lecture seule, sans encore modifier les écrans Flutter.

## Endpoints ajoutés

- `GET /api/mobile/v1/reference-data` : retourne l'ensemble des référentiels officiels OVANIE, les catégories/sous-catégories réelles, les communes/quartiers et le territoire de livraison.
- `GET /api/mobile/v1/reference-data/meta` : endpoint léger qui retourne les versions et les clés disponibles sans charger tout le référentiel.

## Sécurité

Les deux endpoints sont publics en lecture seule, car certains formulaires doivent charger leurs référentiels avant authentification. Aucune donnée utilisateur, commande ou information financière personnelle n'est exposée. Les routes sont limitées par throttle.

## Structure de la réponse principale

```json
{
  "ok": true,
  "meta": {
    "service": "OVANIE Reference Data",
    "endpoint_version": "v1",
    "schema_version": "1.0.0",
    "registry_version": "1.0.0",
    "source": "laravel",
    "read_only": true,
    "generated_at": "..."
  },
  "data": {
    "product_states": [],
    "commercial_sale_types": [],
    "selling_modes": [],
    "product_units": [],
    "seller_types": [],
    "address_types": [],
    "identity_types": [],
    "vehicle_types": [],
    "payment_methods": [],
    "categories": [],
    "subcategories": [],
    "communes": [],
    "quarters": [],
    "delivery_territory": []
  }
}
```

## Important

Les applications Flutter ne sont pas encore modifiées à cette étape. `schema_version` est exposé, mais les règles de compatibilité APK seront traitées à l'étape 5.
