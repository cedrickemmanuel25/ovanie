# Étape 4 — Correctif relation catégories

## Problème observé

Le validateur échouait lors de la construction du payload avec une erreur de type :
`Argument #1 ($query) must be of type Illuminate\\Database\\Eloquent\\Builder, Illuminate\\Database\\Eloquent\\Relations\\HasMany given`.

## Cause

Dans `OvanieReferenceDataService::productCategories()`, le callback passé à l'eager-loading de la relation `children` imposait le type `Builder`. Selon le chemin d'exécution Laravel utilisé par le projet, ce callback reçoit la relation `HasMany`, qui relaie ensuite les appels de scopes vers son builder interne.

## Correction

Le type du paramètre du callback a été retiré :

```php
->with(['children' => fn ($query) => $query->active()->ordered()])
```

Aucun contrat, champ, référentiel, route, donnée ou écran n'est modifié par ce correctif.

## Validation

Depuis la racine Laravel :

```bash
php docs/socle-commun/etape-04/validate_reference_api.php
```

Le résultat attendu commence par :

```text
OK — API commune OVANIE reference-data exposée
```
