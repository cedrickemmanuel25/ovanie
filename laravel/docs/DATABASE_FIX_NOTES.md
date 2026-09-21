# Database Fix Notes

## Bloc 2 - Structure User / Shop / Vendor

Objectif retenu :

- `User` reste le compte utilisateur.
- `Shop` devient la boutique vendeuse de référence.
- `Product` appartient à `Shop`.
- `Order` appartient au client `User` via `client_id` et peut appartenir à `Shop`.
- `OrderItem` appartient à `Product` et `Shop`.
- `Commission` appartient à `Shop` et `Order`.
- `VendorPayout` appartient à `Shop`.
- `Vendor` est conservé comme modèle legacy tant que le projet l'utilise.

## Incohérences identifiées

- `products.vendor_id` a été ajouté par d'anciennes migrations puis supprimé ensuite, mais le modèle `Product` exposait encore `vendor_id`.
- `orders.vendor_id` a été ajouté puis remplacé par `shop_id`, mais le modèle `Order` exposait encore `vendor_id`.
- La migration initiale `create_order_items_table` crée `order_items.shop_id` avec une contrainte vers `users`, alors que la logique métier attend une contrainte vers `shops`.
- La migration `modify_shop_id_in_order_items_table` ne corrige pas cette contrainte si la colonne existe déjà.
- Le modèle `Vendor` pointait encore ses produits et commandes via `vendor_id`, alors que la relation stable doit passer par `shops`.

## Changements appliqués

- `Product` ne rend plus `vendor_id` mass assignable et ne le caste plus.
- `Order` ne rend plus `vendor_id` mass assignable.
- `Order` expose `user()` comme alias de relation client via `client_id`.
- `Shop` expose les relations `orders`, `orderItems`, `commissions` et `vendorPayouts`.
- `Vendor` est conservé et redirige ses relations `shop`, `products` et `orders` via `user_id -> shops.user_id`.
- Une migration corrective répare la contrainte `order_items.shop_id` vers `shops.id`.

## Migration corrective

Fichier :

- `database/migrations/2026_06_23_000002_fix_order_items_shop_foreign_key.php`

Comportement :

- supprime la contrainte existante sur `order_items.shop_id` si elle existe ;
- remappe sous MySQL les anciens `shop_id` qui contiennent en réalité un `users.id` vers le `shops.id` correspondant ;
- recrée la contrainte vers `shops.id`.

## Risques restants

- Si une base contient des `order_items.shop_id` qui ne correspondent ni à `shops.id` ni à `shops.user_id`, la nouvelle contrainte échouera à la migration.
- Le modèle `Vendor` reste legacy : il ne doit plus être utilisé comme source principale pour les nouveaux développements vendeur.
- `vendor_payouts` contient encore `vendor_id` pour compatibilité, mais la relation stable doit être `shop_id`.
