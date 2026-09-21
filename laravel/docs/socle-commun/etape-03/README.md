# Socle commun Web ↔ Mobile — Étape 3

## Objectif

Centraliser dans Laravel les référentiels métier OVANIE avant de créer l'API unifiée de l'étape 4.

Cette étape **ne refait aucun écran Flutter** et **ne modifie pas encore le schéma de base de données**. Elle crée une seule couche Laravel pour les listes communes et branche les endpoints existants les plus concernés sur cette couche en conservant leurs formes de réponse actuelles.

## Source unique créée

- `config/ovanie_contract.php` : contrat officiel validé à l'étape 2.
- `config/ovanie_reference_data.php` : registre central des référentiels.
- `app/Services/OvanieReferenceDataService.php` : service Laravel unique de lecture des référentiels statiques et dynamiques.

## Référentiels statiques centralisés

Le service lit directement les enums du contrat officiel :

- états produit ;
- types commerciaux ;
- modes de vente ;
- unités produit ;
- types vendeur ;
- formes juridiques ;
- types de pièce ;
- types de logistique ;
- zones macro de livraison ;
- modes de paiement vendeur ;
- opérateurs Mobile Money ;
- types de compte client ;
- types d'adresse ;
- types de véhicule ;
- jours ;
- statuts déjà définis dans le contrat.

## Référentiels dynamiques centralisés

Ils restent stockés dans la base Laravel et sont lus par le même service :

- catégories / sous-catégories ;
- communes / quartiers ;
- régions et villes déjà utilisées par les boutiques ;
- territoire de livraison actif géré par OVANIE Logistics.

## Compatibilité conservée

Trois listes déjà utilisées par le projet mais qui ne sont pas encore des enums canoniques de l'étape 2 sont regroupées dans le registre central afin de supprimer leurs définitions répétées :

- pays de délivrance d'identité ;
- délais de préparation ;
- moyens de reversement vendeur.

Les opérateurs du checkout sont également construits depuis le référentiel central.

## Endpoints existants branchés sur le service

Sans changer leurs URL ni leur structure attendue :

- meta App Vendeur ;
- meta Boutique App Commercial ;
- meta Produit App Commercial ;
- types d'adresse Client ;
- territoire de livraison Client ;
- opérateurs du checkout.

## Important

L'endpoint API unifié `reference-data` **n'est volontairement pas créé ici**. Il appartient à l'étape 4.
