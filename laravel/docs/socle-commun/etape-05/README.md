# OVANIE — Socle commun — Étape 5

## Objectif

Mettre en place la compatibilité explicite entre le contrat de données Laravel et les APK OVANIE sans encore remplacer les référentiels locaux Flutter.

Cette étape ajoute :

- une route publique de vérification `GET /api/mobile/v1/reference-data/compatibility` ;
- une matrice Laravel pour les quatre applications : Client, Vendeur, Livreur, Commercial ;
- un numéro de schéma compilé dans chaque APK ;
- un contrôle au démarrage de chaque application ;
- un blocage uniquement lorsqu'une incompatibilité est explicitement confirmée par Laravel ;
- les en-têtes `X-Ovanie-App` et `X-Ovanie-Schema-Version` sur les appels API mobiles.

## Règle de sécurité opérationnelle

Une panne réseau ne doit pas bloquer le démarrage. Une incompatibilité de contrat confirmée par Laravel doit bloquer l'APK afin d'éviter une mauvaise interprétation des données.

## Contrat actuel

- Schéma Laravel : `1.0.0`
- Client : `1.0.0`
- Vendeur : `1.0.0`
- Livreur : `1.0.0`
- Commercial : `1.0.0`

## Validation

Depuis la racine Laravel :

```bash
php docs/socle-commun/etape-05/validate_schema_compatibility.php
```

L'étape est validée uniquement si le script termine par `OK`.
