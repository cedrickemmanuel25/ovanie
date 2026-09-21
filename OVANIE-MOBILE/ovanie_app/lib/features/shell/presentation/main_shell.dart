import 'package:flutter/material.dart';

import '../../../app/theme.dart';
import '../../../core/layout/responsive.dart';
import '../../account/presentation/account_screen.dart';
import '../../auth/domain/session_store.dart';
import '../../cart/domain/cart_store.dart';
import '../../cart/presentation/cart_screen.dart';
import '../../catalog/domain/catalog_navigation_store.dart';
import '../../categories/presentation/categories_screen.dart';
import '../../favorites/domain/favorites_store.dart';
import '../../favorites/presentation/favorites_screen.dart';
import '../../home/presentation/home_screen.dart';
import '../../../shared/widgets/ovanie_bottom_navigation.dart';
import '../domain/main_navigation_store.dart';
import 'main_navigation_controller.dart';

class MainShell extends StatefulWidget {
  const MainShell({super.key});

  @override
  State<MainShell> createState() => _MainShellState();
}

class _MainShellState extends State<MainShell> {
  MainNavigationStore get _navigation => MainNavigationStore.instance;

  void _selectTab(OvanieMainTab tab) {
    MainNavigationController.select(tab);
  }

  void _openCatalogTab() {
    MainNavigationController.select(
      OvanieMainTab.categories,
      resetCatalog: true,
    );
  }

  void _onDestinationSelected(OvanieMainTab tab) {
    if (tab == OvanieMainTab.categories) {
      _openCatalogTab();
      return;
    }
    _selectTab(tab);
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: Listenable.merge([
        CartStore.instance,
        FavoritesStore.instance,
        SessionStore.instance,
        MainNavigationStore.instance,
      ]),
      builder: (context, _) {
        final cartCount = CartStore.instance.itemsCount;
        final favoritesCount = SessionStore.instance.isAuthenticated
            ? FavoritesStore.instance.count
            : 0;

        final screens = <Widget>[
          HomeScreen(
            onOpenCart: () => _selectTab(OvanieMainTab.cart),
            onOpenCatalog: ({categorySlug, categoryName, offer}) {
              CatalogNavigationStore.instance.open(
                categorySlug: categorySlug,
                categoryName: categoryName,
                offer: offer,
              );
              _selectTab(OvanieMainTab.categories);
            },
          ),
          CategoriesScreen(navigationStore: CatalogNavigationStore.instance),
          CartScreen(
            onBack: () => _selectTab(OvanieMainTab.home),
            onStartShopping: _openCatalogTab,
          ),
          FavoritesScreen(
            onOpenAccount: () => _selectTab(OvanieMainTab.account),
            onOpenCart: () => _selectTab(OvanieMainTab.cart),
            onStartShopping: _openCatalogTab,
          ),
          AccountScreen(
            onOpenHome: () => _selectTab(OvanieMainTab.home),
            onOpenCategories: _openCatalogTab,
            onOpenFavorites: () => _selectTab(OvanieMainTab.favorites),
            onOpenCart: () => _selectTab(OvanieMainTab.cart),
          ),
        ];

        final body = IndexedStack(
          index: _navigation.currentIndex,
          children: screens,
        );

        if (OvanieResponsive.useNavigationRail(context)) {
          final viewportWidth = MediaQuery.sizeOf(context).width;
          final navigationWidth =
              OvanieResponsive.tabletNavigationWidthForWidth(viewportWidth);

          return Scaffold(
            body: SafeArea(
              child: Row(
                children: [
                  _OvanieTabletNavigation(
                    width: navigationWidth,
                    selectedIndex: _navigation.currentIndex,
                    onDestinationSelected: _onDestinationSelected,
                    favoritesCount: favoritesCount,
                    cartCount: cartCount,
                  ),
                  const VerticalDivider(
                    width: 1,
                    thickness: 1,
                    color: OvanieColors.border,
                  ),
                  Expanded(child: body),
                ],
              ),
            ),
          );
        }

        return Scaffold(
          body: body,
          bottomNavigationBar: OvanieBottomNavigation(
            selectedTab: _navigation.currentTab,
            onTabSelected: _onDestinationSelected,
          ),
        );
      },
    );
  }
}

/// Navigation principale dédiée aux tablettes.
///
/// Contrairement à un NavigationRail compact, cette version conserve toujours
/// les libellés visibles. Elle reste donc lisible sur une Galaxy Tab A8 en
/// portrait comme en paysage et utilise la largeur disponible sans comprimer
/// le contenu principal.
class _OvanieTabletNavigation extends StatelessWidget {
  final double width;
  final int selectedIndex;
  final ValueChanged<OvanieMainTab> onDestinationSelected;
  final int favoritesCount;
  final int cartCount;

  const _OvanieTabletNavigation({
    required this.width,
    required this.selectedIndex,
    required this.onDestinationSelected,
    required this.favoritesCount,
    required this.cartCount,
  });

  @override
  Widget build(BuildContext context) {
    final destinations = <_TabletDestination>[
      const _TabletDestination(
        label: 'Accueil',
        icon: Icons.home_outlined,
        selectedIcon: Icons.home_rounded,
      ),
      const _TabletDestination(
        label: 'Catégories',
        icon: Icons.grid_view_outlined,
        selectedIcon: Icons.grid_view_rounded,
      ),
      _TabletDestination(
        label: 'Panier',
        icon: Icons.shopping_cart_outlined,
        selectedIcon: Icons.shopping_cart_rounded,
        badgeCount: cartCount,
      ),
      _TabletDestination(
        label: 'Favoris',
        icon: Icons.favorite_border_rounded,
        selectedIcon: Icons.favorite_rounded,
        badgeCount: favoritesCount,
      ),
      const _TabletDestination(
        label: 'Compte',
        icon: Icons.person_outline_rounded,
        selectedIcon: Icons.person_rounded,
      ),
    ];

    return Material(
      color: Colors.white,
      child: SizedBox(
        width: width,
        height: double.infinity,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const _OvanieTabletBrand(),
            const Padding(
              padding: EdgeInsets.symmetric(horizontal: 18),
              child: Divider(height: 1, color: OvanieColors.border),
            ),
            const SizedBox(height: 14),
            Expanded(
              child: ListView.separated(
                padding: const EdgeInsets.fromLTRB(12, 0, 12, 18),
                itemCount: destinations.length,
                separatorBuilder: (_, __) => const SizedBox(height: 8),
                itemBuilder: (context, index) {
                  final destination = destinations[index];
                  return _OvanieTabletNavigationItem(
                    label: destination.label,
                    icon: destination.icon,
                    selectedIcon: destination.selectedIcon,
                    badgeCount: destination.badgeCount,
                    selected: selectedIndex == index,
                    onTap: () => onDestinationSelected(OvanieMainTab.values[index]),
                  );
                },
              ),
            ),
            const _OvanieTabletFooter(),
          ],
        ),
      ),
    );
  }
}

class _TabletDestination {
  final String label;
  final IconData icon;
  final IconData selectedIcon;
  final int badgeCount;

  const _TabletDestination({
    required this.label,
    required this.icon,
    required this.selectedIcon,
    this.badgeCount = 0,
  });
}

class _OvanieTabletBrand extends StatelessWidget {
  const _OvanieTabletBrand();

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(18, 20, 16, 18),
      child: Row(
        children: [
          Container(
            width: 40,
            height: 40,
            decoration: BoxDecoration(
              color: OvanieColors.navy,
              borderRadius: BorderRadius.circular(12),
            ),
            alignment: Alignment.center,
            child: const Icon(
              Icons.home_work_outlined,
              color: Colors.white,
              size: 22,
            ),
          ),
          const SizedBox(width: 11),
          const Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  'OVANIE',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: OvanieColors.navy,
                    fontSize: 18,
                    fontWeight: FontWeight.w900,
                    letterSpacing: .5,
                  ),
                ),
                SizedBox(height: 1),
                Text(
                  'Espace client',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: OvanieColors.muted,
                    fontSize: 11,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _OvanieTabletNavigationItem extends StatelessWidget {
  final String label;
  final IconData icon;
  final IconData selectedIcon;
  final int badgeCount;
  final bool selected;
  final VoidCallback onTap;

  const _OvanieTabletNavigationItem({
    required this.label,
    required this.icon,
    required this.selectedIcon,
    required this.badgeCount,
    required this.selected,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final foreground = selected ? OvanieColors.blue : OvanieColors.text;
    final background = selected
        ? OvanieColors.blue.withValues(alpha: .10)
        : Colors.transparent;

    Widget iconWidget = Icon(
      selected ? selectedIcon : icon,
      color: foreground,
      size: 23,
    );

    if (badgeCount > 0) {
      iconWidget = Badge.count(
        count: badgeCount,
        backgroundColor: OvanieColors.orange,
        textColor: Colors.white,
        child: iconWidget,
      );
    }

    return Semantics(
      button: true,
      selected: selected,
      label: label,
      child: Material(
        color: background,
        borderRadius: BorderRadius.circular(14),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(14),
          child: AnimatedContainer(
            duration: const Duration(milliseconds: 180),
            curve: Curves.easeOut,
            constraints: const BoxConstraints(minHeight: 56),
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 11),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(14),
              border: Border.all(
                color: selected
                    ? OvanieColors.blue.withValues(alpha: .18)
                    : Colors.transparent,
              ),
            ),
            child: Row(
              children: [
                AnimatedContainer(
                  duration: const Duration(milliseconds: 180),
                  width: 4,
                  height: selected ? 28 : 12,
                  decoration: BoxDecoration(
                    color: selected ? OvanieColors.orange : Colors.transparent,
                    borderRadius: BorderRadius.circular(999),
                  ),
                ),
                const SizedBox(width: 10),
                SizedBox(
                  width: 30,
                  child: Center(child: iconWidget),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Text(
                    label,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: foreground,
                      fontSize: 14,
                      fontWeight: selected ? FontWeight.w800 : FontWeight.w600,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _OvanieTabletFooter extends StatelessWidget {
  const _OvanieTabletFooter();

  @override
  Widget build(BuildContext context) {
    return const Padding(
      padding: EdgeInsets.fromLTRB(18, 12, 18, 18),
      child: Row(
        children: [
          Icon(Icons.verified_user_outlined, size: 16, color: OvanieColors.muted),
          SizedBox(width: 8),
          Expanded(
            child: Text(
              'Compte client sécurisé',
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                color: OvanieColors.muted,
                fontSize: 10.5,
                height: 1.2,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
