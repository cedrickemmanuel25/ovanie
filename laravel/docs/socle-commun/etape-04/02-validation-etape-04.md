# Validation de l'étape 4

Depuis la racine Laravel :

```bash
php docs/socle-commun/etape-04/validate_reference_api.php
```

Le résultat attendu commence par :

```text
OK — API commune OVANIE reference-data exposée
```

Contrôle facultatif :

```bash
php artisan route:list --path=mobile/v1/reference-data
```

Après déploiement sur l'environnement de test, ouvrir :

```text
https://test.ovanie.com/api/mobile/v1/reference-data/meta
```

Le JSON doit contenir `ok: true`, `schema_version`, `registry_version` et `available_keys`.
