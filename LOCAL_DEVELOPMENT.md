# Développement local : Web Laravel et applications mobiles

Le backend commun est `laravel`. Il est configuré pour démarrer sur
`http://127.0.0.1:8000`; les deux applications Flutter utilisent cette API.

## Démarrer le backend

Dans un premier terminal :

```powershell
cd laravel
php artisan config:clear
php artisan serve --host=0.0.0.0 --port=8000
```

L'option `--host=0.0.0.0` rend le serveur accessible depuis l'émulateur et un
téléphone connecté au même réseau. Le site web reste disponible sur
`http://127.0.0.1:8000`.

## Lancer les applications Flutter

Par défaut, les applications utilisent :

- Android Emulator : `http://10.0.2.2:8000/api`
- Flutter Web : `http://127.0.0.1:8000/api`

```powershell
cd OVANIE-MOBILE\ovanie_app
flutter run

cd ..\ovanie_vendor
flutter run
```

Pour un téléphone physique, remplacez `ADRESSE_IP_DU_PC` par l'adresse IPv4
locale de l'ordinateur, puis lancez l'application avec :

```powershell
flutter run --dart-define=OVANIE_API_BASE=http://ADRESSE_IP_DU_PC:8000/api
```

Le même paramètre peut être utilisé pour un environnement de test ou de
production. Ne mettez pas cette adresse dans le code : `OVANIE_API_BASE` a
priorité sur la configuration locale par défaut.

## Vérification rapide

Quand Laravel est démarré, cette URL doit retourner du JSON :

`http://127.0.0.1:8000/api/mobile/v1/auth/status`

Le fichier `laravel/.env` autorise aussi les origines `localhost` et
`127.0.0.1` pour Flutter Web, y compris les ports de développement variables.
