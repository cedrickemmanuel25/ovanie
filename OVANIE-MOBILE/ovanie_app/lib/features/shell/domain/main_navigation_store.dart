import 'package:flutter/foundation.dart';

/// Onglets principaux de l'application OVANIE.
///
/// L'ordre est unique dans toute l'application :
/// Accueil → Catégories → Panier → Favoris → Compte.
enum OvanieMainTab {
  home,
  categories,
  cart,
  favorites,
  account,
}

class MainNavigationStore extends ChangeNotifier {
  MainNavigationStore._();

  static final MainNavigationStore instance = MainNavigationStore._();

  OvanieMainTab _currentTab = OvanieMainTab.home;

  OvanieMainTab get currentTab => _currentTab;
  int get currentIndex => _currentTab.index;

  void select(OvanieMainTab tab) {
    if (_currentTab == tab) return;
    _currentTab = tab;
    notifyListeners();
  }

  void selectIndex(int index) {
    if (index < 0 || index >= OvanieMainTab.values.length) return;
    select(OvanieMainTab.values[index]);
  }
}
