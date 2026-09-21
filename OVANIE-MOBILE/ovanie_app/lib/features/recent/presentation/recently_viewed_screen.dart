import 'package:flutter/material.dart';

import '../../../app/theme.dart';
import '../../../core/layout/responsive.dart';
import '../../../shared/widgets/ovanie_top_bar.dart';
import '../../../shared/widgets/product_card.dart';
import '../../../shared/widgets/product_horizontal_section.dart';
import '../../cart/presentation/cart_screen.dart';
import '../../home/data/marketplace_repository.dart';
import '../../products/domain/product_model.dart';
import '../domain/recently_viewed_store.dart';

class RecentlyViewedScreen extends StatefulWidget {
  final VoidCallback? onOpenHome;
  final VoidCallback? onOpenCategories;
  final VoidCallback? onOpenCart;
  final VoidCallback? onOpenFavorites;
  final VoidCallback? onOpenAccount;

  const RecentlyViewedScreen({
    super.key,
    this.onOpenHome,
    this.onOpenCategories,
    this.onOpenCart,
    this.onOpenFavorites,
    this.onOpenAccount,
  });

  @override
  State<RecentlyViewedScreen> createState() => _RecentlyViewedScreenState();
}

class _RecentlyViewedScreenState extends State<RecentlyViewedScreen> {
  final MarketplaceRepository _repository = const MarketplaceRepository();
  List<ProductModel> _recommendations = const [];

  @override
  void initState() {
    super.initState();
    _loadRecommendations();
    _syncAccountHistory();
  }

  Future<void> _syncAccountHistory() async {
    try {
      await RecentlyViewedStore.instance.syncFromServer();
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Historique en ligne momentanément indisponible.')),
      );
    }
  }

  Future<void> _loadRecommendations() async {
    try {
      final items = await _repository.getRecommendations(limit: 8);
      if (!mounted) return;
      setState(() => _recommendations = items);
    } catch (_) {
      // Les recommandations sont complémentaires : l'écran reste utilisable
      // même si l'API de recommandations est momentanément indisponible.
    }
  }

  void _goBack() => Navigator.of(context).maybePop();

  void _goToRoot(VoidCallback? callback) {
    if (callback == null) return;
    if (Navigator.of(context).canPop()) {
      Navigator.of(context).pop();
    }
    callback();
  }

  void _openCart() {
    if (widget.onOpenCart != null) {
      _goToRoot(widget.onOpenCart);
      return;
    }
    Navigator.of(context).push(
      MaterialPageRoute<void>(builder: (_) => const CartScreen()),
    );
  }

  void _startShopping() {
    if (widget.onOpenCategories != null) {
      _goToRoot(widget.onOpenCategories);
      return;
    }
    Navigator.of(context).maybePop();
  }

  void _handleMenu(String value) {
    switch (value) {
      case 'home':
        _goToRoot(widget.onOpenHome);
        break;
      case 'categories':
        _goToRoot(widget.onOpenCategories);
        break;
      case 'cart':
        _openCart();
        break;
      case 'favorites':
        _goToRoot(widget.onOpenFavorites);
        break;
      case 'account':
        _goToRoot(widget.onOpenAccount);
        break;
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: OvanieColors.background,
      body: SafeArea(
        child: LayoutBuilder(
          builder: (context, viewport) {
            final availableWidth = viewport.maxWidth;
            final gridPadding =
                OvanieResponsive.horizontalPaddingForWidth(availableWidth);
            final gridColumns =
                OvanieResponsive.productGridColumnsForWidth(availableWidth);
            final gridTileWidth = OvanieResponsive.productTileWidthForWidth(
              availableWidth,
              gridColumns,
            );
            final gridExtent =
                ProductCard.extentForWidth(context, width: gridTileWidth);

            return AnimatedBuilder(
          animation: RecentlyViewedStore.instance,
          builder: (context, _) {
            final items = RecentlyViewedStore.instance.items;

            return CustomScrollView(
              slivers: [
                SliverToBoxAdapter(
                  child: OvanieTopBar(
                    showBack: true,
                    onBack: _goBack,
                    onOpenCart: _openCart,
                    menuActions: const [
                      OvanieMenuAction(
                        value: 'home',
                        label: 'Accueil',
                        icon: Icons.home_outlined,
                      ),
                      OvanieMenuAction(
                        value: 'categories',
                        label: 'Catégories',
                        icon: Icons.grid_view_outlined,
                      ),
                      OvanieMenuAction(
                        value: 'cart',
                        label: 'Panier',
                        icon: Icons.shopping_cart_outlined,
                      ),
                      OvanieMenuAction(
                        value: 'favorites',
                        label: 'Favoris',
                        icon: Icons.favorite_border_rounded,
                      ),
                      OvanieMenuAction(
                        value: 'account',
                        label: 'Compte',
                        icon: Icons.person_outline_rounded,
                      ),
                    ],
                    onMenuSelected: _handleMenu,
                  ),
                ),
                const SliverToBoxAdapter(
                  child: Padding(
                    padding: EdgeInsets.fromLTRB(16, 18, 16, 12),
                    child: Text(
                      'Vu récemment',
                      style: TextStyle(
                        color: OvanieColors.text,
                        fontSize: 21,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ),
                ),
                if (items.isEmpty)
                  SliverToBoxAdapter(
                    child: _EmptyRecentlyViewed(
                      onStartShopping: _startShopping,
                    ),
                  )
                else ...[
                  SliverPadding(
                    padding: EdgeInsets.fromLTRB(gridPadding, 0, gridPadding, 18),
                    sliver: SliverGrid(
                      delegate: SliverChildBuilderDelegate(
                        (context, index) => ProductCard(product: items[index]),
                        childCount: items.length,
                      ),
                      gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
                        crossAxisCount: gridColumns,
                        crossAxisSpacing: 12,
                        mainAxisSpacing: 12,
                        mainAxisExtent: gridExtent,
                      ),
                    ),
                  ),
                  SliverToBoxAdapter(
                    child: Padding(
                      padding: const EdgeInsets.fromLTRB(16, 0, 16, 26),
                      child: SizedBox(
                        height: 44,
                        child: OutlinedButton.icon(
                          onPressed: () async {
                            try {
                              await RecentlyViewedStore.instance.clear();
                            } catch (_) {
                              if (!mounted) return;
                              ScaffoldMessenger.of(context).showSnackBar(
                                const SnackBar(content: Text('Impossible d’effacer l’historique en ligne.')),
                              );
                            }
                          },
                          style: OutlinedButton.styleFrom(
                            foregroundColor: OvanieColors.danger,
                            side: const BorderSide(color: OvanieColors.border),
                            backgroundColor: Colors.white,
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(12),
                            ),
                          ),
                          icon: const Icon(Icons.delete_sweep_outlined, size: 19),
                          label: const Text(
                            'Tout effacer',
                            style: TextStyle(fontWeight: FontWeight.w900),
                          ),
                        ),
                      ),
                    ),
                  ),
                ],
                if (_recommendations.isNotEmpty)
                  SliverToBoxAdapter(
                    child: ProductHorizontalSection(
                      title: 'Recommandé pour vous',
                      products: _recommendations,
                    ),
                  ),
                const SliverToBoxAdapter(child: SizedBox(height: 24)),
              ],
            );
              },
            );
          },
        ),
      ),
    );
  }
}

class _EmptyRecentlyViewed extends StatelessWidget {
  final VoidCallback onStartShopping;

  const _EmptyRecentlyViewed({required this.onStartShopping});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(30, 48, 30, 42),
      child: Column(
        children: [
          Container(
            width: 76,
            height: 76,
            decoration: BoxDecoration(
              color: OvanieColors.blue.withValues(alpha: 0.08),
              shape: BoxShape.circle,
            ),
            child: const Icon(
              Icons.history_rounded,
              size: 38,
              color: OvanieColors.blue,
            ),
          ),
          const SizedBox(height: 18),
          const Text(
            'Aucun produit récemment vu',
            textAlign: TextAlign.center,
            style: TextStyle(
              color: OvanieColors.text,
              fontSize: 18,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 8),
          const Text(
            'Vous n’avez pas de produits vus récemment.',
            textAlign: TextAlign.center,
            style: TextStyle(
              color: OvanieColors.muted,
              fontSize: 12.5,
              height: 1.45,
            ),
          ),
          const SizedBox(height: 20),
          SizedBox(
            width: 220,
            height: 46,
            child: FilledButton(
              onPressed: onStartShopping,
              style: FilledButton.styleFrom(
                backgroundColor: OvanieColors.orange,
                foregroundColor: Colors.white,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
              ),
              child: const Text(
                'Poursuivez vos achats',
                style: TextStyle(fontWeight: FontWeight.w900),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
