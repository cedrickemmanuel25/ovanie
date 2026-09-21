# API commune `reference-data`

Le contrôleur `MobileReferenceDataController` ne définit aucune liste métier : il délègue à `OvanieReferenceDataService`.

Les référentiels contractuels sont exposés au format `[{code, label}]` afin que Flutter n'ait plus à reconstruire les libellés. Les catégories, sous-catégories, communes, quartiers et le territoire de livraison sont lus dans la base Laravel au moment de la requête.

Les données de compatibilité déjà centralisées (pays de pièce, délais de préparation, moyens de reversement, opérateurs checkout) restent également exposées par la même couche pendant la transition.
