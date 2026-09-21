# 04 — Contrat officiel Client / Adresse

## Profil Client

Le contrat officiel reprend les champs métier déjà disponibles dans le modèle `User` et le profil Web :

- `first_name`, `last_name`, `name` dérivé ;
- `email`, `phone` ;
- `secondary_phone` ;
- `whatsapp_phone` ;
- `birth_date` ;
- `gender` ;
- `city` ;
- `account_type` ;
- `avatar` ;
- `locale`, `currency`, `timezone`, `date_format`.

### Décision téléphone secondaire / WhatsApp

Les deux champs sont conservés car ils ne représentent pas la même chose :

- `secondary_phone` = autre numéro de contact ;
- `whatsapp_phone` = numéro WhatsApp.

L'App Client devra pouvoir lire/écrire le même contrat que le Web à l'étape d'implémentation correspondante.

## Valeurs Client

Genre : `homme`, `femme`, `non_precise`.

Type de compte : `particulier`, `professionnel`, `artisan`.

Langue : `fr`, `en`.

Devise : `XOF`, `EUR`.

## Adresse

Les types d'adresse observés dans le nouveau ZIP sont déjà retenus comme officiels :

- `home` — Domicile ;
- `office` — Bureau ;
- `site` — Chantier ;
- `warehouse` — Dépôt ;
- `other` — Autre.

Champs persistés : type, label, destinataire, ville, commune, quartier, pays, adresse complète, téléphone, latitude, longitude, adresse par défaut.

`quartier_principal` et `sous_quartier` restent des informations dérivées par l'API tant qu'aucune migration dédiée n'est décidée.

## Checkout

Le contrat fixe que le checkout ne doit pas posséder son propre référentiel métier de communes/quartiers. Le territoire affiché doit venir du serveur. La suppression du fichier Flutter local sera faite à l'étape d'implémentation des référentiels.
