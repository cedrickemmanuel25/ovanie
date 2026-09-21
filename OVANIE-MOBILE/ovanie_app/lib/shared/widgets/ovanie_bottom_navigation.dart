import 'package:flutter/material.dart';

import '../../app/theme.dart';
import '../../features/auth/domain/session_store.dart';
import '../../features/cart/domain/cart_store.dart';
import '../../features/favorites/domain/favorites_store.dart';
import '../../features/shell/domain/main_navigation_store.dart';
import '../../features/shell/presentation/main_navigation_controller.dart';

/// Barre de navigation principale unique de l'application mobile OVANIE.
///
/// Tous les écrans qui affichent la navigation principale doivent utiliser ce
/// widget. Cela garantit les mêmes dimensions, le même ordre d'onglets, les
/// mêmes badges et le même état actif partout dans l'application.
class OvanieBottomNavigation extends StatelessWidget {
  final OvanieMainTab selectedTab;
  final ValueChanged<OvanieMainTab>? onTabSelected;

  const OvanieBottomNavigation({
    super.key,
    required this.selectedTab,
    this.onTabSelected,
  });

  void _select(BuildContext context, OvanieMainTab tab) {
    if (onTabSelected != null) {
      onTabSelected!(tab);
      return;
    }

    // Les écrans secondaires sont ouverts au-dessus du MainShell. On met à
    // jour l'onglet cible puis on revient au Shell existant au lieu d'empiler
    // une nouvelle copie de l'application.
    MainNavigationController.select(
      tab,
      resetCatalog: tab == OvanieMainTab.categories,
    );
    Navigator.of(context).popUntil((route) => route.isFirst);
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: Listenable.merge([
        CartStore.instance,
        FavoritesStore.instance,
        SessionStore.instance,
      ]),
      builder: (context, _) {
        final favoriteCount = SessionStore.instance.isAuthenticated
            ? FavoritesStore.instance.count
            : 0;
        final cartCount = CartStore.instance.itemsCount;

        final items = <_BottomNavigationItemData>[
          const _BottomNavigationItemData(
            tab: OvanieMainTab.home,
            label: 'Accueil',
            icon: Icons.home_outlined,
          ),
          const _BottomNavigationItemData(
            tab: OvanieMainTab.categories,
            label: 'Catégories',
            icon: Icons.grid_view_rounded,
          ),
          _BottomNavigationItemData(
            tab: OvanieMainTab.cart,
            label: 'Panier',
            icon: Icons.shopping_cart_outlined,
            badgeCount: cartCount,
          ),
          _BottomNavigationItemData(
            tab: OvanieMainTab.favorites,
            label: 'Favoris',
            icon: Icons.favorite_border_rounded,
            badgeCount: favoriteCount,
          ),
          const _BottomNavigationItemData(
            tab: OvanieMainTab.account,
            label: 'Compte',
            icon: Icons.person_outline_rounded,
          ),
        ];

        return Material(
          color: Colors.white,
          child: Container(
            decoration: const BoxDecoration(
              color: Colors.white,
              border: Border(
                top: BorderSide(color: Color(0xFFE8EAF0), width: .7),
              ),
              boxShadow: [
                BoxShadow(
                  color: Color(0x0A000000),
                  blurRadius: 8,
                  offset: Offset(0, -2),
                ),
              ],
            ),
            child: SafeArea(
              top: false,
              child: SizedBox(
                height: 62,
                child: Row(
                  children: items.map((item) {
                    return Expanded(
                      child: _BottomNavigationButton(
                        data: item,
                        selected: selectedTab == item.tab,
                        onTap: () => _select(context, item.tab),
                      ),
                    );
                  }).toList(growable: false),
                ),
              ),
            ),
          ),
        );
      },
    );
  }
}

class _BottomNavigationItemData {
  final OvanieMainTab tab;
  final String label;
  final IconData icon;
  final int badgeCount;

  const _BottomNavigationItemData({
    required this.tab,
    required this.label,
    required this.icon,
    this.badgeCount = 0,
  });
}

class _BottomNavigationButton extends StatelessWidget {
  final _BottomNavigationItemData data;
  final bool selected;
  final VoidCallback onTap;

  const _BottomNavigationButton({
    required this.data,
    required this.selected,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final color = selected ? OvanieColors.orange : OvanieColors.navy;

    return InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(2, 7, 2, 4),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            SizedBox(
              width: 30,
              height: 25,
              child: Stack(
                clipBehavior: Clip.none,
                alignment: Alignment.center,
                children: [
                  Icon(data.icon, color: color, size: 23),
                  if (data.badgeCount > 0)
                    Positioned(
                      right: -2,
                      top: -4,
                      child: Container(
                        constraints: const BoxConstraints(
                          minWidth: 16,
                          minHeight: 16,
                        ),
                        padding: const EdgeInsets.symmetric(horizontal: 3),
                        alignment: Alignment.center,
                        decoration: const BoxDecoration(
                          color: OvanieColors.orange,
                          shape: BoxShape.circle,
                        ),
                        child: Text(
                          data.badgeCount > 99 ? '99+' : '${data.badgeCount}',
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 7.5,
                            height: 1,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                      ),
                    ),
                ],
              ),
            ),
            const SizedBox(height: 3),
            Text(
              data.label,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                color: color,
                fontSize: 10,
                height: 1,
                fontWeight: selected ? FontWeight.w800 : FontWeight.w500,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
