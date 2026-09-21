import 'dart:async';

import '../../auth/domain/session_store.dart';
import '../../cart/data/cart_api_repository.dart';
import '../../catalog/domain/catalog_navigation_store.dart';
import '../../favorites/domain/favorites_store.dart';
import '../domain/main_navigation_store.dart';

/// Point d'entrée unique pour les changements d'onglet principaux.
///
/// En plus de sélectionner l'onglet, ce contrôleur conserve les règles métier
/// de navigation : retour à la racine des catégories et resynchronisation du
/// panier / des favoris avec l'API Laravel.
class MainNavigationController {
  const MainNavigationController._();

  static void select(
    OvanieMainTab tab, {
    bool resetCatalog = false,
  }) {
    if (tab == OvanieMainTab.categories && resetCatalog) {
      CatalogNavigationStore.instance.openAll();
    }

    if (SessionStore.instance.isAuthenticated) {
      if (tab == OvanieMainTab.cart) {
        unawaited(const CartApiRepository().refreshLocalCartFromServer());
      } else if (tab == OvanieMainTab.favorites) {
        unawaited(
          FavoritesStore.instance.refreshFromServer().catchError((_) {}),
        );
      }
    }

    MainNavigationStore.instance.select(tab);
  }
}
