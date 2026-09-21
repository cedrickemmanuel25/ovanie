# Environnement LOCAL des quatre applications OVANIE

Ce patch ne change pas les règles métier de l'Étape 6. Il configure seulement les quatre apps pour travailler proprement contre le backend Laravel local.

## Principe

Chaque application accepte désormais :

- `OVANIE_ENV=local`
- `OVANIE_ENV=test`
- `OVANIE_ENV=production`
- `OVANIE_API_BASE=...` pour forcer une URL précise.

Sur téléphone physique, le script `LOCAL-DEV/run-app.ps1` détecte l'IP LAN du PC et passe l'URL locale à Flutter. Cela évite de mettre une adresse IP personnelle en dur dans les sources.

## Validation statique

Depuis `laravel` :

```powershell
php docs/socle-commun/environnement-local/validate_local_environment.php
```

Puis, depuis la racine du workspace :

```powershell
.\LOCAL-DEV\start-laravel.ps1
```

Dans un second terminal :

```powershell
.\LOCAL-DEV\test-local-api.ps1
```
