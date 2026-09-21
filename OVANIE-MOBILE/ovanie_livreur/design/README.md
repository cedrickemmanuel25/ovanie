# Refonte OVANIE Livreur

Les neuf vues utilisent la bannière camion/route, le fond menthe, le logo typographique à gauche, les cartes blanches arrondies et les actions vertes inspirés de la maquette fournie.

Les cinq étapes d’inscription apparaissent au-dessus des formulaires. Les communes sont disposées sur trois colonnes, ou deux sur les écrans étroits et avec un texte agrandi. Les formulaires défilent et les actions des étapes restent en bas. Sur Connexion, Vérification OTP et Vérification du numéro, la bannière reste visible en format compact à l’ouverture du clavier.

Les numéros sont regroupés par paires (`07 01 02 03 04`), avec normalisation sans espaces avant l’envoi. L’OTP affiche un seul compteur d’expiration. Les disponibilités offrent des raccourcis semaine/week-end/tous les jours et des cartes par jour. Les types de véhicules utilisent des cartes avec icône, description et état sélectionné.

Les appels API et les règles de préinscription sont conservés. Les captures de contrôle utilisent des données fictives et une police locale de substitution (Arial) ; la police de l’application reste Plus Jakarta Sans. Les fichiers dans `previews/` représentent les premières vues à 390 × 844 points, les longs formulaires étant défilants.

## Image générée

Outil : imagegen intégré, sans CLI ni clé API. Asset final : `assets/ovanie_truck_road.png` (1536 × 1024). L’ancienne image est conservée.

Prompt utilisé :

> Use case: ads-marketing. Create a photorealistic landscape hero image for the OVANIE Livreur mobile app, inspired by the supplied reference's truck and road scene. A white cab delivery box truck with emerald green cargo box, seen from behind in three-quarter view on the right half, driving away on a clean curving multilane road, lush green trees and modern glass city towers in the distance, bright blue sky, sunny natural daylight. Exact white lettering on back of truck: 'OVANIE' with smaller 'Livreur' underneath. Premium realistic commercial photography. Wide 3:2 composition designed to crop to a mobile banner; keep the whole truck inside central 80 percent, road visible on left. Only the scene, no phone frame, no UI, no buttons, no added titles, no watermark.

## Vérification

`flutter test` vérifie la navigation connexion/inscription, le comportement avec le clavier, les neuf vues à 320 × 568 et 390 × 844, les sélections et la soumission simulée du dossier. Les réponses serveur sont simulées uniquement dans les tests.

Pour régénérer les captures sous Windows :

```powershell
$env:OVANIE_CAPTURE='1'
$env:OVANIE_PREVIEW_FONT='C:\Windows\Fonts\arial.ttf'
flutter test test/onboarding_screens_test.dart
```

La connexion SMS réelle, les téléversements depuis un appareil et la validation par le serveur nécessitent une vérification avec le backend et un téléphone.
