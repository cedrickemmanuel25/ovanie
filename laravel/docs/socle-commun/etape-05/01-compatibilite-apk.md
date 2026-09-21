# Compatibilité APK ↔ contrat OVANIE

Chaque APK embarque la version du schéma qu'elle sait interpréter. Au démarrage, elle interroge Laravel :

```text
GET /api/mobile/v1/reference-data/compatibility
    ?app=client
    &schema_version=1.0.0
```

Réponse compatible :

```json
{
  "ok": true,
  "compatible": true,
  "compatibility": {
    "status": "compatible",
    "app": "client",
    "client_schema_version": "1.0.0",
    "server_schema_version": "1.0.0"
  }
}
```

Si Laravel passe ultérieurement à un contrat incompatible, par exemple `2.0.0`, une ancienne APK compilée pour `1.0.0` doit recevoir `compatible=false` et afficher l'écran technique de mise à jour requise.

Cette étape ne change pas encore les listes de catégories, unités, communes, véhicules ou paiements utilisées par Flutter. Leur migration vers `reference-data` vient après la protection de compatibilité.
