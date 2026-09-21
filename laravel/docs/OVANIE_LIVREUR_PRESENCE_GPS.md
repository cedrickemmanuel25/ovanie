# OVANIE Livreur — présence GPS

La connexion OTP ne suffit plus à déclarer un livreur « En ligne ».

## Présence GPS

L'application mobile Livreur doit appeler, avec le jeton Sanctum du livreur :

`POST /api/driver/presence`

Corps JSON :

```json
{
  "latitude": 5.3484,
  "longitude": -4.0267,
  "accuracy": 15,
  "speed": 0,
  "heading": 120,
  "battery_level": 80
}
```

Fréquence recommandée : utiliser la valeur `presence_heartbeat_seconds` renvoyée par `/api/driver/me` (45 secondes par défaut).

Un livreur est considéré en ligne uniquement si :
- son compte est actif et validé ;
- OVANIE a reçu un heartbeat GPS ;
- la dernière position est plus récente que `presence_offline_after_seconds` (120 secondes par défaut).

## Déconnexion / GPS désactivé

Appeler immédiatement :

`POST /api/driver/presence/offline`

Le logout `/api/driver/auth/logout` passe également le livreur hors ligne.

Si l'application est tuée sans pouvoir appeler l'endpoint offline, le back-office le considérera automatiquement hors ligne dès que la dernière position GPS dépasse le délai de présence.

## Disponibilité

La présence GPS et la disponibilité sont séparées.

`POST /api/driver/availability`

```json
{"status":"Disponible"}
```

ou

```json
{"status":"Indisponible"}
```

Le livreur peut donc être :
- En ligne + Disponible ;
- En ligne + Indisponible ;
- En ligne + En mission ;
- Hors ligne.
