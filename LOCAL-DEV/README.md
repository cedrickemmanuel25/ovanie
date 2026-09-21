# OVANIE — développement mobile en local

Les quatre applications sont configurées avec trois environnements :

- `local` : Laravel lancé sur le PC.
- `test` : `https://test.ovanie.com/api`.
- `production` : `https://www.ovanie.com/api`.

`OVANIE_API_BASE` reste prioritaire et permet de forcer une URL précise.

## 1. Démarrer Laravel local

Depuis la racine `ovanie` :

```powershell
.\LOCAL-DEV\start-laravel.ps1
```

Laravel écoute sur `0.0.0.0:8000`, donc le PC et un téléphone sur le même réseau peuvent l'atteindre.

## 2. Vérifier l'API locale

Dans un autre terminal :

```powershell
.\LOCAL-DEV\test-local-api.ps1
```

Le test doit afficher deux lignes `OK`.

## 3. Lancer une app sur téléphone physique

```powershell
.\LOCAL-DEV\run-app.ps1 -App client
.\LOCAL-DEV\run-app.ps1 -App vendor
.\LOCAL-DEV\run-app.ps1 -App livreur
.\LOCAL-DEV\run-app.ps1 -App commercial
```

Le script détecte automatiquement l'IP LAN du PC et l'injecte dans l'application.

Si Windows choisit la mauvaise carte réseau (VPN, VirtualBox, etc.), forcez l'adresse :

```powershell
.\LOCAL-DEV\run-app.ps1 -App vendor -HostIp 192.168.1.25
```

Pour cibler un appareil Flutter précis :

```powershell
flutter devices
.\LOCAL-DEV\run-app.ps1 -App vendor -DeviceId <ID>
```

## 4. Android Emulator

```powershell
.\LOCAL-DEV\run-app.ps1 -App vendor -Emulator
```

L'API utilisée sera `http://10.0.2.2:8000/api`.

## 5. Construire un APK local

```powershell
.\LOCAL-DEV\build-apk.ps1 -App client -Environment local
```

Par défaut un APK local est construit en `debug`. L'adresse IP actuelle du PC est embarquée dans l'APK. Si cette IP change, il faut reconstruire l'APK.

## 6. Revenir plus tard sur test.ovanie.com

```powershell
.\LOCAL-DEV\run-app.ps1 -App vendor -Environment test
```

ou construire l'APK de test :

```powershell
.\LOCAL-DEV\build-apk.ps1 -App vendor -Environment test
```

## 7. Revenir en production

Aucun fichier Dart n'a besoin d'être modifié :

```powershell
.\LOCAL-DEV\build-apk.ps1 -App vendor -Environment production -Mode release
```

Le script injecte `https://www.ovanie.com/api`.

## Important

Le backend local utilise la base configurée dans `laravel/.env`. Les données de `test.ovanie.com` ne sont pas automatiquement copiées en local.

Pour un téléphone physique :

- PC et téléphone doivent être sur le même Wi-Fi/LAN ;
- le port TCP 8000 doit être accessible dans le pare-feu Windows ;
- Laravel doit rester lancé avec `start-laravel.ps1`.
