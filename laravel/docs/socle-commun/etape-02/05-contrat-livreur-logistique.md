# 05 — Contrat officiel Livreur / Logistique

## Nature du livreur

Le contrat parle de **livreur partenaire**, pas de salarié OVANIE.

## Onboarding

Statuts officiels :

- `invited` ;
- `pending_review` ;
- `active` ;
- `suspended` ;
- `rejected`.

## Identité

Le dossier commun doit pouvoir contenir : prénom, nom, téléphone, email, photo, date de naissance, type/numéro de pièce et document d'identité.

## Véhicule

Le même véhicule doit être affiché dans Profil Livreur, Accueil Livreur, Fiche Logistique et suivi lorsque le contexte le nécessite.

Types officiels : `moto`, `tricycle`, `pickup`, `camion_3t`, `camion_10t`.

Champs officiels : type, plaque, couleur, couleur hex éventuelle, année, capacité, carte grise, photo véhicule, photo plaque et documents complémentaires.

La capacité cible est `capacity_kg` numérique. Le champ legacy `profile.capacity` sous forme de texte devra être normalisé lors d'une étape de migration.

## Disponibilité

La disponibilité métier devient un code et non un texte français stocké :

- `available` ;
- `unavailable` ;
- `on_mission`.

Les valeurs legacy `Disponible`, `Indisponible`, `En mission`, `En livraison` seront mappées.

`is_online` reste **dérivé par le serveur** de la fraîcheur GPS ; ce n'est pas un simple bouton de présence.

## Zones et jours

`zone_ids` utilise les identifiants du territoire Laravel. Les jours officiels sont lundi à dimanche en codes stables.

## Documents complémentaires

`supporting_documents` fait partie du contrat officiel. Le fait que l'app les sélectionne sans les envoyer est un défaut d'implémentation à corriger plus tard, pas une raison de retirer ce champ.

## Missions

Statuts officiels issus du workflow moderne :

`planned`, `assigned`, `accepted`, `collecting`, `picked_up`, `in_transit`, `arrived`, `delivered`, `rejected`, `incident`.

Le serveur doit rester responsable des transitions et des actions autorisées.
