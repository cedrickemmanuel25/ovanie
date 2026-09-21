# Validation de l'étape 5

## Test principal

```bash
php docs/socle-commun/etape-05/validate_schema_compatibility.php
```

## Résultat attendu

Le test vérifie :

1. la configuration Laravel de compatibilité ;
2. les quatre applications connues ;
3. `1.0.0` compatible avec le serveur `1.0.0` ;
4. une ancienne version `0.9.0` refusée ;
5. une future version `2.0.0` refusée tant que le serveur est `1.0.0` ;
6. la route API de compatibilité ;
7. la présence du contrôle de démarrage dans les quatre apps ;
8. la présence des en-têtes de schéma dans leurs clients HTTP.

Aucune migration SQL n'est nécessaire.
