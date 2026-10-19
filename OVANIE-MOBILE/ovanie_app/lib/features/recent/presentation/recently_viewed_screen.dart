import 'package:flutter/material.dart';

import '../../../core/utils/formatters.dart';
import '../../../shared/widgets/ovanie_bottom_navigation.dart';
import '../../auth/domain/session_store.dart';
import '../../auth/presentation/auth_branding.dart';
import '../../cart/domain/cart_store.dart';
import '../../catalog/presentation/catalog_screen.dart';
import '../../categories/presentation/catalog_widgets.dart';
import '../../favorites/domain/favorites_store.dart';
import '../../home/data/marketplace_repository.dart';
import '../../products/domain/product_model.dart';
import '../../products/presentation/product_detail_screen.dart';
import '../../shell/domain/main_navigation_store.dart';
import '../domain/recently_viewed_store.dart';

/// « Vu récemment » : les produits consultés, puis des recommandations.
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
  bool _clearing = false;

  @override
  void initState() {
    super.initState();
    _loadRecommendations();
    _syncAccountHistory();
  }

  void _toast(String message) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(message), duration: const Duration(milliseconds: 1800)));
  }

  Future<void> _syncAccountHistory() async {
    try {
      await RecentlyViewedStore.instance.syncFromServer();
    } catch (error) {
      if (!mounted) return;
      _toast('Historique en ligne momentanément indisponible.');
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

  Future<void> _clearAll() async {
    if (_clearing) return;
    setState(() => _clearing = true);
    try {
      await RecentlyViewedStore.instance.clear();
    } catch (_) {
      if (mounted) _toast('L’historique n’a pas pu être effacé en ligne. Réessayez.');
    } finally {
      if (mounted) setState(() => _clearing = false);
    }
  }

  void _startShopping() {
    final openCategories = widget.onOpenCategories;
    if (openCategories != null) {
      if (Navigator.of(context).canPop()) Navigator.of(context).pop();
      openCategories();
      return;
    }
    _openCatalog();
  }

  void _openCatalog() {
    Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => const CatalogScreen(showBackButton: true)));
  }

  void _openProduct(ProductModel product) {
    Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => ProductDetailScreen(product: product)));
  }

  void _toggleFavorite(ProductModel product) {
    if (!SessionStore.instance.isAuthenticated) {
      _toast('Connectez-vous pour ajouter ce produit à vos favoris.');
      return;
    }
    FavoritesStore.instance.toggle(product);
  }

  void _addToCart(ProductModel product) {
    final result = CartStore.instance.add(product);
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          content: Text(result.message),
          duration: const Duration(milliseconds: 1200),
          backgroundColor: result.success ? authNavy : authDanger,
        ),
      );
  }

  @override
  Widget build(BuildContext context) {
    return OvanieAuthTheme(
      child: Scaffold(
        backgroundColor: Colors.white,
        bottomNavigationBar: const OvanieBottomNavigation(selectedTab: OvanieMainTab.account),
        body: SafeArea(
          bottom: false,
          child: AnimatedBuilder(
            animation: RecentlyViewedStore.instance,
            builder: (context, _) {
              final items = RecentlyViewedStore.instance.items;
              return Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  _Header(
                    canClear: items.isNotEmpty && !_clearing,
                    onBack: () => Navigator.of(context).maybePop(),
                    onClear: _clearAll,
                  ),
                  const Divider(height: 1, thickness: 1, color: Color(0xFFEDEFF3)),
                  Expanded(
                    child: items.isEmpty
                        ? (RecentlyViewedStore.instance.syncing
                              ? const Center(child: CircularProgressIndicator(color: authOrange))
                              : _Empty(onStartShopping: _startShopping))
                        : _buildList(items),
                  ),
                ],
              );
            },
          ),
        ),
      ),
    );
  }

  Widget _buildList(List<ProductModel> items) {
    return LayoutBuilder(
      builder: (context, box) {
        final width = box.maxWidth;
        final columns = width >= 900 ? 4 : (width >= 600 ? 3 : 2);
        const side = 9.0;
        const gap = 7.0;
        final tileWidth = (width - side * 2 - gap * (columns - 1)) / columns;

        return ListView(
          physics: const BouncingScrollPhysics(),
          padding: const EdgeInsets.only(top: 8, bottom: 14),
          children: [
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: side),
              child: GridView.builder(
                shrinkWrap: true,
                padding: EdgeInsets.zero,
                physics: const NeverScrollableScrollPhysics(),
                itemCount: items.length,
                gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
                  crossAxisCount: columns,
                  crossAxisSpacing: gap,
                  mainAxisSpacing: gap,
                  mainAxisExtent: tileWidth * .7 + 86,
                ),
                itemBuilder: (context, index) => _ProductTile(
                  product: items[index],
                  onTap: () => _openProduct(items[index]),
                  onFavorite: () => _toggleFavorite(items[index]),
                  onCart: () => _addToCart(items[index]),
                ),
              ),
            ),
            if (_recommendations.isNotEmpty) ...[
              Padding(
                padding: const EdgeInsets.fromLTRB(11, 14, 4, 6),
                child: Row(
                  children: [
                    const Expanded(
                      child: Text(
                        'Recommandé pour vous',
                        style: TextStyle(color: authNavy, fontSize: 16.5, fontWeight: FontWeight.w800),
                      ),
                    ),
                    InkWell(
                      onTap: _openCatalog,
                      borderRadius: BorderRadius.circular(6),
                      child: const Padding(
                        padding: EdgeInsets.symmetric(horizontal: 6, vertical: 6),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Text(
                              'Voir tout',
                              style: TextStyle(color: Color(0xFF0B5ED7), fontSize: 12.5, fontWeight: FontWeight.w600),
                            ),
                            Icon(Icons.chevron_right_rounded, size: 18, color: Color(0xFF0B5ED7)),
                          ],
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              SizedBox(
                height: 142,
                child: ListView.separated(
                  scrollDirection: Axis.horizontal,
                  physics: const BouncingScrollPhysics(),
                  padding: const EdgeInsets.symmetric(horizontal: side),
                  itemCount: _recommendations.length,
                  separatorBuilder: (_, __) => const SizedBox(width: 6),
                  itemBuilder: (context, index) => SizedBox(
                    width: 104,
                    child: _ProductTile(
                      product: _recommendations[index],
                      compact: true,
                      onTap: () => _openProduct(_recommendations[index]),
                      onFavorite: () => _toggleFavorite(_recommendations[index]),
                      onCart: () => _addToCart(_recommendations[index]),
                    ),
                  ),
                ),
              ),
            ],
          ],
        );
      },
    );
  }
}

class _Header extends StatelessWidget {
  const _Header({required this.canClear, required this.onBack, required this.onClear});

  final bool canClear;
  final VoidCallback onBack;
  final VoidCallback onClear;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 50,
      child: Stack(
        alignment: Alignment.center,
        children: [
          const Text(
            'Vu récemment',
            style: TextStyle(color: authNavy, fontSize: 16.5, fontWeight: FontWeight.w800),
          ),
          Align(
            alignment: Alignment.centerLeft,
            child: IconButton(
              onPressed: onBack,
              tooltip: 'Retour',
              icon: const Icon(Icons.arrow_back_ios_new_rounded, size: 20, color: authNavy),
            ),
          ),
          Align(
            alignment: Alignment.centerRight,
            child: TextButton(
              onPressed: canClear ? onClear : null,
              style: TextButton.styleFrom(
                foregroundColor: const Color(0xFFE5231B),
                disabledForegroundColor: const Color(0x80E5231B),
                padding: const EdgeInsets.symmetric(horizontal: 12),
              ),
              child: const Text(
                'Tout effacer',
                style: TextStyle(fontFamily: authFont, fontSize: 12.5, fontWeight: FontWeight.w600),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _Empty extends StatelessWidget {
  const _Empty({required this.onStartShopping});

  final VoidCallback onStartShopping;

  @override
  Widget build(BuildContext context) {
    return Align(
      alignment: const Alignment(0, -.25),
      child: SingleChildScrollView(
        padding: const EdgeInsets.symmetric(horizontal: 13, vertical: 20),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Center(
              child: Container(
                width: 80,
                height: 80,
                decoration: const BoxDecoration(color: Color(0xFFF0F1F4), shape: BoxShape.circle),
                child: const Icon(Icons.schedule_rounded, size: 46, color: Color(0xFF8C8F97)),
              ),
            ),
            const SizedBox(height: 14),
            const Text(
              'Aucun produit récemment vu',
              textAlign: TextAlign.center,
              style: TextStyle(color: authNavy, fontSize: 19, fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 6),
            const Text(
              'Vous n’avez pas de produits vus récemment.',
              textAlign: TextAlign.center,
              style: TextStyle(color: catalogMuted, fontSize: 13),
            ),
            const SizedBox(height: 20),
            OvaniePrimaryButton(label: 'Poursuivez vos achats', height: 44, onPressed: onStartShopping),
          ],
        ),
      ),
    );
  }
}

/// Carte produit : photo, cœur dans un rond blanc, nom, étoiles, prix par
/// unité, disponibilité et bouton panier. [compact] pour les recommandations.
class _ProductTile extends StatelessWidget {
  const _ProductTile({
    required this.product,
    required this.onTap,
    required this.onFavorite,
    required this.onCart,
    this.compact = false,
  });

  final ProductModel product;
  final VoidCallback onTap;
  final VoidCallback onFavorite;
  final VoidCallback onCart;
  final bool compact;

  (String, Color) get _stock {
    final status = product.availabilityStatus.toLowerCase();
    if (status == 'on_order' || status == 'preorder') {
      return ('Sur commande', const Color(0xFF0B5ED7));
    }
    if (product.stock <= 0 || status == 'out_of_stock') {
      return ('Rupture de stock', authDanger);
    }
    if (product.stock <= 5) return ('Stock limité', const Color(0xFFB4470F));
    return ('En stock', const Color(0xFF15934A));
  }

  @override
  Widget build(BuildContext context) {
    final canBuy = product.canAddToCart && product.stock > 0;
    final unit = product.unitLabel.trim();
    final rating = product.rating ?? 0;
    final (stockLabel, stockColor) = _stock;
    final scale = compact ? .78 : 1.0;
    final button = compact ? 24.0 : 32.0;

    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(9),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Container(
          foregroundDecoration: BoxDecoration(
            borderRadius: BorderRadius.circular(9),
            border: Border.all(color: const Color(0xFFE8EBF0)),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Expanded(
                child: Stack(
                  fit: StackFit.expand,
                  children: [
                    ColoredBox(
                      color: const Color(0xFFF1F3F6),
                      child: CatalogImage(
                        urls: product.imageUrl.trim().isEmpty ? const <String>[] : <String>[product.imageUrl],
                        fit: BoxFit.contain,
                        placeholder: const Icon(Icons.inventory_2_outlined, size: 30, color: Color(0xFFB5BECA)),
                      ),
                    ),
                    Positioned(
                      right: 5,
                      top: 5,
                      child: AnimatedBuilder(
                        animation: Listenable.merge([SessionStore.instance, FavoritesStore.instance]),
                        builder: (context, _) {
                          final favorite =
                              SessionStore.instance.isAuthenticated && FavoritesStore.instance.contains(product);
                          return Semantics(
                            button: true,
                            label: favorite ? 'Retirer des favoris' : 'Ajouter aux favoris',
                            child: Material(
                              color: Colors.white,
                              shape: const CircleBorder(),
                              child: InkWell(
                                customBorder: const CircleBorder(),
                                onTap: onFavorite,
                                child: SizedBox(
                                  width: compact ? 22 : 28,
                                  height: compact ? 22 : 28,
                                  child: Icon(
                                    favorite ? Icons.favorite_rounded : Icons.favorite_border_rounded,
                                    size: compact ? 13 : 17,
                                    color: favorite ? authOrange : authNavy,
                                  ),
                                ),
                              ),
                            ),
                          );
                        },
                      ),
                    ),
                  ],
                ),
              ),
              SizedBox(
                height: compact ? 70 : 86,
                child: Stack(
                  children: [
                    Padding(
                      padding: EdgeInsets.fromLTRB(compact ? 5 : 7, compact ? 4 : 6, compact ? 5 : 7, compact ? 5 : 7),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            product.name,
                            maxLines: compact ? 2 : 1,
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(
                              color: authNavy,
                              fontSize: 12.5 * scale,
                              height: 1.2,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                          if (rating > 0 && product.reviewsCount > 0) ...[
                            const SizedBox(height: 2),
                            Row(
                              children: [
                                for (var i = 1; i <= 5; i++)
                                  Icon(
                                    rating >= i - .25
                                        ? Icons.star_rounded
                                        : (rating >= i - .75 ? Icons.star_half_rounded : Icons.star_outline_rounded),
                                    size: 12 * scale,
                                    color: const Color(0xFFFFB400),
                                  ),
                                const SizedBox(width: 3),
                                Flexible(
                                  child: Text(
                                    '${rating.toStringAsFixed(1).replaceAll('.', ',')} (${product.reviewsCount})',
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                    style: TextStyle(color: catalogMuted, fontSize: 10 * scale),
                                  ),
                                ),
                              ],
                            ),
                          ],
                          const Spacer(),
                          Padding(
                            padding: EdgeInsets.only(right: button + 4),
                            child: FittedBox(
                              fit: BoxFit.scaleDown,
                              alignment: Alignment.centerLeft,
                              child: Text.rich(
                                TextSpan(
                                  text: formatFcfa(product.homeDisplayPrice),
                                  children: [
                                    if (unit.isNotEmpty)
                                      TextSpan(
                                        text: '  / $unit',
                                        style: TextStyle(
                                          color: catalogMuted,
                                          fontSize: 11 * scale,
                                          fontWeight: FontWeight.w400,
                                        ),
                                      ),
                                  ],
                                ),
                                maxLines: 1,
                                style: TextStyle(color: authNavy, fontSize: 14 * scale, fontWeight: FontWeight.w800),
                              ),
                            ),
                          ),
                          const SizedBox(height: 3),
                          Row(
                            children: [
                              Container(
                                width: 6.5 * scale,
                                height: 6.5 * scale,
                                decoration: BoxDecoration(color: stockColor, shape: BoxShape.circle),
                              ),
                              SizedBox(width: 5 * scale),
                              Flexible(
                                child: Text(
                                  stockLabel,
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: TextStyle(
                                    color: stockColor,
                                    fontSize: 10.5 * scale,
                                    fontWeight: FontWeight.w500,
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),
                    Positioned(
                      right: compact ? 5 : 7,
                      bottom: compact ? 6 : 9,
                      child: Material(
                        color: canBuy ? authOrange : const Color(0xFFCBD2DC),
                        borderRadius: BorderRadius.circular(compact ? 5 : 7),
                        child: InkWell(
                          onTap: canBuy ? onCart : null,
                          borderRadius: BorderRadius.circular(compact ? 5 : 7),
                          child: SizedBox(
                            width: button,
                            height: button,
                            child: Icon(
                              Icons.shopping_cart_outlined,
                              color: Colors.white,
                              size: compact ? 14 : 18,
                              semanticLabel: canBuy ? 'Ajouter au panier' : 'Indisponible',
                            ),
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
