# Vérification des écrans Opérations

Les écrans reconstruits utilisent `layouts/logistics-operations.blade.php` et
les composants `components/operations`. Les deux layouts logistiques partagent
la même navigation : `logistics/operations/navigation.blade.php`,
`logistics-navigation.css` et `logistics-navigation.js`.
Les styles des six écrans de supervision sont isolés dans
`logistics-supervision.css` pour préserver les pages de tournées.

Depuis le dossier `laravel` :

```powershell
php artisan test --filter=LogisticsOperationsViewsTest
php tests/visual/render-operations.php
php tests/visual/render-supervision.php
php -S 127.0.0.1:8017 -t public tests/visual/serve-operations.php
```

Les aperçus locaux sont disponibles à `/preview/operations.html`, `tours.html`,
`create.html`, `detail.html` et `assignment.html`. Ajouter `?qa=1` exécute les
contrôles des interactions et écrit leurs résultats dans `#ops-qa-results`.
Le serveur de vérification ne modifie aucune donnée et ne fait pas partie des
routes Laravel. Les fichiers rendus et les captures sont dans
`storage/app/operations-visual`.

Les données des maquettes sont exclusivement dans `OperationsReferenceData`.
Elles ne remplacent jamais une liste vide en production. Certaines valeurs
de référence sont indépendantes des lignes : les poids de la maquette totalisent
410 kg alors que son indicateur affiche 420 kg. Après une modification de la
sélection, l’interface recalcule les totaux à partir des missions.

Les cartes utilisent le jeton Mapbox public du projet, ou OpenStreetMap sans
jeton. Les tracés proviennent des géométries enregistrées des expéditions.
Les brouillons de sélection et d’affectation restent dans le navigateur ;
les validations utilisent les contrôleurs Laravel existants.

Dimensions de référence : tournées 1536×1024, création et affectation 1448×1086,
détail 1672×941. Les filtres et formulaires sont également vérifiés à 390×844.

Les nouveaux aperçus sont `dashboard.html`, `mission.html`, `assign.html`,
`deliveries.html` et `map.html`. Ils utilisent des modèles en mémoire de
`SupervisionReferenceData`, sans écriture en base. Les contrôles de navigation,
recherche de livreur et formulaire sont dans `check-supervision.js`.
`capture-supervision.cjs` se connecte uniquement à un Chrome de test local
sur le port 9338 et fixe les dimensions via CDP : le simple argument
`--window-size=390,844` peut recadrer une fenêtre plus large sous Windows.
Les captures et résultats JSON sont enregistrés dans `storage/app/operations-visual`.

Les coordonnées des livreurs ne sont affichées que si elles sont récentes.
Les heures et trajets inconnus restent indiqués comme non renseignés ; les
maquettes ne servent pas à inventer des données opérationnelles.
