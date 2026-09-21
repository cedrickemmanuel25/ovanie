# 01 — Principes du contrat officiel OVANIE

## 1. Source de vérité

Laravel et la base OVANIE constituent la source métier unique. Le Web et les applications mobiles peuvent présenter les données différemment, mais ne doivent pas créer des significations différentes.

## 2. Convention de nommage

Les clés API et les champs cibles utilisent `snake_case`. Les libellés français affichés sont des présentations et ne doivent jamais être utilisés comme codes persistés.

Exemple :

```text
code : reconditioned
label : Reconditionné
```

## 3. Un champ = un concept

Un champ officiel ne peut représenter qu'un concept. Le conflit `sale_type` de l'Étape 1 est donc interdit par le contrat.

## 4. Référentiels

Les valeurs métier stables sont définies sous forme de codes. À l'Étape 3, Laravel deviendra la source de ces référentiels pour toutes les interfaces.

## 5. Statuts

Les statuts sont séparés par dimension :

- préparation vendeur ;
- livraison ;
- mission livreur ;
- paiement ;
- retour ;
- onboarding livreur ;
- publication produit.

Un statut de livraison ne doit pas être stocké comme statut de préparation vendeur uniquement pour faciliter un affichage.

## 6. Compatibilité legacy

Le contrat ne supprime pas immédiatement les anciennes valeurs. Elles sont recensées dans `legacy_mappings` afin de permettre une migration sûre et progressive.

## 7. Étape 2 = définition, pas migration

`runtime_enabled=false` est volontaire. Aucun écran n'est censé changer après copie de cette étape.
