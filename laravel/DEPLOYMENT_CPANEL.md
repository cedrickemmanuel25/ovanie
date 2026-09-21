# OVANIE — Déploiement cPanel

La racine web doit pointer vers `public/`. Ne jamais placer `.env`, `.env.cpanel`, `storage/app/private`, une sauvegarde ou un dump SQL dans `public_html`.

## Préparation et contrôle

Sur une base de préproduction propre et une copie des fichiers :

```bash
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
npm ci
npm audit
npm run build
php artisan optimize:clear
php artisan migrate:status
php artisan migrate --pretend
php artisan documents:migrate-private --dry-run
php artisan test
composer audit --locked
```

Vérifier sans afficher les secrets : `APP_ENV=production`, `APP_DEBUG=false`, HTTPS/cookies sécurisés, inscription admin fermée et fournisseurs OpenAI/WhatsApp/Twilio/SMS/GPS/PayDunya désactivés tant qu'ils ne sont pas recettés. Ne jamais régénérer `APP_KEY`.

## Déploiement exact

Après revue du `--pretend`, du dry-run documentaire et confirmation explicite :

```bash
php artisan down --retry=60
cp .env .env.rollback
mysqldump --single-transaction --routines --triggers -u "$DB_USERNAME" -p "$DB_DATABASE" > ovanie-before-deploy.sql
tar -czf ovanie-private-before-deploy.tar.gz storage/app/private storage/app/public
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
npm ci
npm run build
php artisan optimize:clear
php artisan migrate --force
php artisan documents:migrate-private --dry-run
php artisan documents:migrate-private
php artisan config:cache
php artisan event:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
php artisan up
```

La migration documentaire réelle ne doit être lancée qu'après examen du tableau dry-run sur la même copie de base.

## Permissions, cron et queue

```bash
chmod -R u+rwX,g+rwX storage bootstrap/cache
find storage bootstrap/cache -type d -exec chmod 775 {} \;
find storage bootstrap/cache -type f -exec chmod 664 {} \;
```

```cron
* * * * * cd /home/CPANEL_USER/ovanie && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
```

Worker recommandé :

```bash
php artisan queue:work database --sleep=3 --tries=3 --timeout=120 --max-time=3600
```

## Vérifications post-déploiement

```bash
composer audit --locked
npm audit
php artisan migrate:status
php artisan route:list
php artisan schedule:list
php artisan test
```

Tester manuellement authentification client/vendeur/admin, boutique/produit/image, panier, adresse checkout, paiement à la livraison, facture, droits inter-vendeurs et accès refusé aux documents privés. Confirmer l'absence de `public/run_setup.php` et `public/rename_logos.php`.

## Retour arrière applicatif

```bash
php artisan down --retry=60
git checkout DEPLOYMENT_PREVIOUS_TAG -- .
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
npm ci
npm run build
cp .env.rollback .env
php artisan optimize:clear
php artisan config:cache
php artisan event:cache
php artisan route:cache
php artisan view:cache
php artisan up
```

## Retour arrière base et documents

Les migrations OVANIE nouvelles ont volontairement un `down()` non destructif pour protéger les historiques. Ne pas utiliser un rollback Laravel aveugle. Procédure :

```bash
php artisan down --retry=60
mysql -u "$DB_USERNAME" -p -e "CREATE DATABASE ovanie_restore_check CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -u "$DB_USERNAME" -p ovanie_restore_check < ovanie-before-deploy.sql
mkdir -p restore-check
tar -xzf ovanie-private-before-deploy.tar.gz -C restore-check
```

Comparer la base restaurée et les empreintes des fichiers hors ligne. Après validation responsable, basculer vers la base restaurée et restaurer ensemble `storage/app/private` et `storage/app/public`. Ne jamais supprimer ou rendre publics les fichiers privés pendant l'incident.

## Activation ultérieure de PayDunya

Configurer les secrets en recette, laisser les webhooks non signés refusés, puis effectuer une transaction réelle autorisée de faible montant. Activer `PAYDUNYA_ENABLED=true` uniquement après validation du montant, de l'idempotence, de la facture, de la réception et du reversement.
