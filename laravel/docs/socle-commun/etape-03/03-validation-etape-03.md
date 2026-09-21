# Validation de l'étape 3

Depuis la racine Laravel :

```bash
php docs/socle-commun/etape-03/validate_reference_data.php
```

Le script vérifie :

1. que le contrat de l'étape 2 est présent ;
2. que les référentiels P0 obligatoires sont enregistrés ;
3. que chaque alias pointe vers un enum réel du contrat ;
4. que les valeurs principales ont les cardinalités attendues ;
5. que les contrôleurs/services ciblés sont branchés sur `OvanieReferenceDataService` ;
6. que Laravel peut lire les catégories et les communes de la base configurée.

Résultat attendu :

```text
OK — Référentiels Laravel OVANIE centralisés
Contrat : 1.0.0
Registre : 1.0.0
Référentiels contractuels : 29
Référentiels P0 : 12/12
Contrôleurs/services branchés : 6/6
Catégories actives : <nombre>
Communes actives : <nombre>
API unifiée : non exposée (prévue à l'étape 4)
```

Le nombre de catégories et de communes dépend de la base de données locale/test utilisée.
