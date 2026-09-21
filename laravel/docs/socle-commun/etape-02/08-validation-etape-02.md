# 08 — Validation de l'Étape 2

## Ce qui doit changer visuellement

**Rien.** Cette étape ne branche pas encore le contrat sur l'application.

## Test technique

Depuis la racine du projet, exécuter :

```bash
php web/docs/socle-commun/etape-02/validate_contract.php
```

Résultat attendu :

```text
OK — Contrat OVANIE Étape 2 valide
Version de définition : 1.0.0
Entités : 10
Enums : 29
Champs canoniques : 154
Runtime : désactivé (définition uniquement)
```

## Contrôles manuels

Ouvrir `web/config/ovanie_contract.php` dans VS Code et vérifier au minimum :

- `product.commercial_sale_type` et `product.selling_mode` sont distincts ;
- `product.product_state` contient Neuf/Reconditionné/Occasion ;
- `shop.seller_type` contient 4 types ;
- les champs Entreprise sont marqués `required_if seller_type=entreprise` ;
- le Client contient `secondary_phone`, `whatsapp_phone`, `birth_date`, `gender`, `city`, `account_type` ;
- le Livreur contient identité, véhicule, zones, disponibilités et documents ;
- le workflow vendeur s'arrête à `ready` pour OVANIE Logistics ;
- `runtime_enabled=false`.

## Critère de passage à l'Étape 3

L'Étape 2 est validée si le validateur retourne `OK` et si les décisions métier ci-dessus correspondent à ce que OVANIE veut conserver comme contrat officiel.

Une fois validée, l'Étape 3 pourra commencer : **centraliser les données de référence dans Laravel**, sans encore tout refaire dans Flutter en une seule fois.
