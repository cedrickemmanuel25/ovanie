# 06 — Contrat officiel Commande / Livraison / Retour / Paiement

## Commande : séparer les dimensions

Le contrat interdit de confondre :

- statut commercial de la commande ;
- paiement ;
- préparation vendeur ;
- livraison ;
- réception client ;
- reversement vendeur.

### `order_status`

Cible : `pending`, `confirmed`, `processing`, `completed`, `cancelled`.

`paid` doit être exprimé par `payment_status`, pas par `order_status`.

`shipped` doit être exprimé par `delivery_status`, pas par `order_status`.

## Préparation vendeur

Le statut vendeur canonique est :

`pending` → `accepted` → `preparing` → `ready`.

`cancelled` est terminal.

Pour **OVANIE Logistics**, le vendeur s'arrête fonctionnellement à `ready`; ensuite la livraison est pilotée par `delivery_status`.

Les anciennes valeurs `shipped` et `delivered` dans `vendor_status` restent uniquement de la compatibilité legacy jusqu'à migration.

## Livraison

Providers : `ovanie`, `seller`, `pickup`, `partner`.

Le workflow central utilise : `pending`, `preparing`, `ready_for_pickup`, `assigned`, `picked_up`, `in_transit`, `delivered`, `late`, `failed`, `returned`, `cancelled`, `delivery_failed`, `not_required`.

Les actions autorisées devront venir du backend dans une étape ultérieure.

## Réception et reversement

Réception : `waiting`, `confirmed`.

Reversement de ligne : `not_ready`, `waiting_reception`, `ready`, `blocked`, `adjusted`, `cancelled`.

## Retours

Statut métier : `pending`, `accepted`, `rejected`, `closed`, `refunded`.

Le statut logistique reste séparé et reprend les valeurs du modèle `ReturnModel`.

Les motifs de retour deviennent des **codes de référentiel serveur** (`reason_code`) accompagnés du texte `reason` lorsque nécessaire.

## Paiement

Statuts canoniques : `pending`, `processing`, `paid`, `escrow_held`, `released_to_vendor`, `failed`, `cancelled`, `expired`, `refunded`, `chargeback`, `disputed`.

Les alias historiques (`success`, `completed`, `validated`, `confirmed`, etc.) sont normalisés vers ces valeurs.

Les méthodes officielles sont définies dans `ovanie_contract.php`; les applications ne doivent pas maintenir une liste indépendante des opérateurs/méthodes.
