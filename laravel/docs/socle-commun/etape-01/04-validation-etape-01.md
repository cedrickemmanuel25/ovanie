# 04 — Validation de l'Étape 1

## Ce que vous devez obtenir après copie du patch

**Aucun écran ne doit changer. Aucun APK ne doit changer de comportement.**

Cette Étape 1 ajoute uniquement le dossier :

`web/docs/socle-commun/etape-01/`

Si une fonctionnalité ou un écran change après copie de ce patch, ce n'est pas le résultat attendu.

## Vérification dans VS Code

1. Ouvrir `web/docs/socle-commun/etape-01/README.md`.
2. Vérifier que les 4 documents principaux et le dossier `generated` existent.
3. Ouvrir `generated/surface-contract-matrix.csv`.
4. Vérifier que la matrice contient les domaines Client, Adresse, Checkout, Boutique,
   Produit, Commande, Livreur, Retour et Paiement/Commercial.
5. Ouvrir `02-ecarts-confirmes.md`.

## Captures fonctionnelles à préparer pour confirmer la cartographie

Ces captures ne servent pas à constater une correction visuelle : elles servent à confirmer
que le rapport décrit bien le comportement réel avant l'Étape 2.

### Capture A — Produit : `sale_type`

Faire deux captures :

- **Web Vendeur** → Ajouter/Modifier produit → étape Prix & unité → champ **Type de vente**.
- **App Vendeur** → Ajouter/Modifier produit → même notion **Type de vente**.

Résultat attendu pour l'Étape 1 :
- Web montre des choix du type Vente normale / Vente flash / Black Friday / Promo.
- App montre unité / lot / poids / mètre / m² / m³.

Si c'est ce que vous voyez, **E01 est confirmé**.

### Capture B — État produit

- Web Vendeur → champ État du produit : Neuf / Reconditionné / Occasion.
- App Vendeur → formulaire produit.

Résultat attendu :
- le Web possède le choix ;
- l'app ne possède pas encore l'équivalent complet et envoie `new`.

Cela confirme **E02**.

### Capture C — Ouverture boutique Entreprise

Faire deux captures au même stade :

- Web → Ouverture boutique → choisir **Entreprise**.
- App Vendeur → Ouverture boutique → choisir **Entreprise**.

Résultat attendu :
- le Web demande raison sociale, forme juridique, RCCM, numéro contribuable et documents société ;
- l'app ne demande pas encore la totalité de ces champs.

Cela confirme **E03**.

### Capture D — Profil Client

- Web Client → Paramètres / profil.
- App Client → Mon compte / Profil.

Résultat attendu :
- le Web possède davantage de champs de profil (date naissance, genre, préférences, etc.) ;
- l'app affiche le profil réduit.

Cela confirme **E05**.

### Capture E — Checkout localisation

- App Client → Panier → Checkout → choix commune/quartier.
- Facultatif : capture de la page/outil Web où les territoires officiels sont gérés si disponible.

Le rapport indique que la liste du checkout mobile provient encore du fichier Flutter local.
La capture permet surtout de vérifier que les communes/quartiers affichés correspondent à ce
que vous observez réellement.

### Capture F — Inscription Livreur

Faire les captures des étapes :
- Informations personnelles ;
- Véhicule ;
- Récapitulatif.

Puis, si vous avez un livreur test :
- Espace Logistique → fiche du même livreur.

Résultat attendu :
- la Logistique/backend possède des emplacements pour davantage d'informations d'identité /
  véhicule / documents que le formulaire mobile actuel.

Cela confirme **E08/E09/E10**.

## Critère GO / NO-GO

### GO vers Étape 2

On passe à l'Étape 2 si :
- A, B, C, D et F correspondent au rapport ;
- les fichiers de documentation sont présents ;
- aucun écran/fonctionnement n'a changé ;
- vous ne constatez pas une divergence importante oubliée.

### NO-GO

On reste en Étape 1 si une capture montre que le rapport est faux ou incomplet.
Dans ce cas, on met à jour la cartographie avant de définir le contrat cible.
