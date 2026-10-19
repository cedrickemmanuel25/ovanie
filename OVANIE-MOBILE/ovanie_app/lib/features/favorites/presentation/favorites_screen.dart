import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../app/theme.dart';
import '../../../core/utils/formatters.dart';
import '../../../shared/widgets/auth_required_view.dart';
import '../../auth/domain/session_store.dart';
import '../../auth/presentation/login_screen.dart';
import '../../cart/domain/cart_store.dart';
import '../../checkout/presentation/checkout_screen.dart' show shortSaleUnit;
import '../../home/data/marketplace_repository.dart';
import '../../payments/presentation/payment_ui.dart';
import '../../products/domain/product_model.dart';
import '../../products/presentation/product_detail_screen.dart';
import '../../search/presentation/search_screen.dart';
import '../../wishlists/domain/wishlist_model.dart';
import '../../wishlists/domain/wishlists_store.dart';
import '../../wishlists/presentation/wishlists_screen.dart';
import '../domain/favorites_store.dart';

class FavoritesScreen extends StatefulWidget {
  final VoidCallback? onOpenAccount;
  final VoidCallback? onOpenCart;
  final VoidCallback? onStartShopping;
  final VoidCallback? onOpenCategories;

  const FavoritesScreen({super.key, this.onOpenAccount, this.onOpenCart, this.onStartShopping, this.onOpenCategories});

  @override
  State<FavoritesScreen> createState() => _FavoritesScreenState();
}

class _FavoritesScreenState extends State<FavoritesScreen> {
  final MarketplaceRepository _marketplace = const MarketplaceRepository();
  // Produits retirés pendant la session, avec leur date de retrait.
  final List<ProductModel> _recentlyRemoved = <ProductModel>[];
  final Map<int, DateTime> _removedAt = <int, DateTime>{};
  List<ProductModel> _recommendations = const <ProductModel>[];
  bool _wishlistMode = false;
  bool _loadingRecommendations = false;

  @override
  void initState() {
    super.initState();
    _prepareData();
  }

  Future<void> _prepareData() async {
    if (SessionStore.instance.isAuthenticated) {
      unawaited(FavoritesStore.instance.refreshFromServer().catchError((_) {}));
      unawaited(WishlistsStore.instance.refresh().catchError((_) {}));
    }
    await _loadRecommendations();
  }

  Future<void> _loadRecommendations() async {
    if (_loadingRecommendations) return;
    _loadingRecommendations = true;
    try {
      var products = await _marketplace.safeProducts(() => _marketplace.getRecommendations(limit: 12));
      if (products.isEmpty) {
        products = await _marketplace.safeProducts(() => _marketplace.getLatestProducts(limit: 12));
      }
      if (!mounted) return;
      setState(() => _recommendations = products);
    } finally {
      _loadingRecommendations = false;
    }
  }

  void _startShopping() {
    if (widget.onStartShopping != null) {
      widget.onStartShopping!();
      return;
    }
    Navigator.of(context).maybePop();
  }

  void _openCategories() {
    if (widget.onOpenCategories != null) {
      widget.onOpenCategories!();
      return;
    }
    _startShopping();
  }

  void _rememberRemoved(Iterable<ProductModel> products) {
    final now = DateTime.now();
    for (final product in products) {
      _recentlyRemoved.removeWhere((item) => item.id == product.id);
      _recentlyRemoved.insert(0, product);
      _removedAt[product.id] = now;
    }
    if (_recentlyRemoved.length > 10) _recentlyRemoved.removeRange(10, _recentlyRemoved.length);
  }

  Future<void> _removeFavorite(ProductModel product) async {
    if (!FavoritesStore.instance.contains(product)) return;
    setState(() => _rememberRemoved([product]));
    try {
      await FavoritesStore.instance.remove(product);
      if (mounted) showOvanieToast(context, 'Produit retiré de vos favoris.');
    } catch (_) {
      if (!mounted) return;
      setState(() => _recentlyRemoved.removeWhere((item) => item.id == product.id));
      showOvanieToast(context, 'Impossible de retirer ce produit des favoris.', error: true);
    }
  }

  Future<void> _clearFavorites() async {
    final items = FavoritesStore.instance.items;
    if (items.isEmpty) return;
    final confirmed = await showOvanieConfirmDialog(
      context,
      visual: const DangerCircle(icon: Icons.delete_outline_rounded),
      title: 'Supprimer tous les favoris ?',
      message: 'Tous les produits enregistrés dans vos favoris seront retirés.',
      confirmLabel: 'Supprimer',
      confirmColor: const Color(0xFFDC2626),
      cancelLabel: 'Annuler',
    );
    if (!confirmed || !mounted) return;
    setState(() => _rememberRemoved(items.reversed));
    try {
      await FavoritesStore.instance.clear();
      if (mounted) showOvanieToast(context, 'Vos favoris ont été supprimés.');
    } catch (_) {
      if (mounted) showOvanieToast(context, 'Impossible de supprimer vos favoris pour le moment.', error: true);
    }
  }

  void _restoreRemoved(ProductModel product) {
    if (!SessionStore.instance.isAuthenticated) return;
    if (!FavoritesStore.instance.contains(product)) FavoritesStore.instance.toggle(product);
    setState(() {
      _recentlyRemoved.removeWhere((item) => item.id == product.id);
      _removedAt.remove(product.id);
    });
    showOvanieToast(context, 'Produit restauré dans vos favoris.');
  }

  void _showRemovedProducts() {
    if (_recentlyRemoved.isEmpty) return;
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(26))),
      builder: (sheetContext) => StatefulBuilder(
        builder: (sheetContext, setSheetState) {
          final items = _recentlyRemoved.toList(growable: false);
          return SafeArea(
            child: ConstrainedBox(
              constraints: BoxConstraints(maxHeight: MediaQuery.sizeOf(context).height * .75),
              child: Padding(
                padding: const EdgeInsets.fromLTRB(20, 10, 12, 12),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const WishlistSheetHandle(),
                    const SizedBox(height: 16),
                    Row(
                      children: [
                        const Expanded(
                          child: Text(
                            'Produits supprimés récemment',
                            style: TextStyle(color: payNavy, fontSize: 22, fontWeight: FontWeight.w800),
                          ),
                        ),
                        IconButton(
                          onPressed: () => Navigator.of(sheetContext).pop(),
                          tooltip: 'Fermer',
                          icon: const Icon(Icons.close_rounded, color: payNavy, size: 30),
                        ),
                      ],
                    ),
                    Text(
                      'Vous avez supprimé ${items.length} produit${items.length > 1 ? 's' : ''} de vos favoris.',
                      style: const TextStyle(color: payMuted, fontSize: 15),
                    ),
                    const SizedBox(height: 10),
                    Flexible(
                      child: ListView.separated(
                        shrinkWrap: true,
                        padding: const EdgeInsets.only(right: 8),
                        itemCount: items.length,
                        separatorBuilder: (_, __) => const Divider(height: 22, color: payBorder),
                        itemBuilder: (_, index) {
                          final product = items[index];
                          return Row(
                            children: [
                              OrderItemThumbnail(url: product.imageUrl, width: 96, height: 76),
                              const SizedBox(width: 14),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      product.name,
                                      maxLines: 2,
                                      overflow: TextOverflow.ellipsis,
                                      style: const TextStyle(color: payNavy, fontSize: 15),
                                    ),
                                    const SizedBox(height: 3),
                                    _PriceText(product: product, size: 16),
                                    const SizedBox(height: 3),
                                    Text(
                                      'Supprimé le ${paymentShortDate(_removedAt[product.id])}',
                                      style: const TextStyle(color: payMuted, fontSize: 13),
                                    ),
                                  ],
                                ),
                              ),
                              const SizedBox(width: 8),
                              SizedBox(
                                width: 112,
                                height: 46,
                                child: OutlinedButton(
                                  onPressed: () {
                                    _restoreRemoved(product);
                                    if (_recentlyRemoved.isEmpty) {
                                      Navigator.of(sheetContext).pop();
                                    } else {
                                      setSheetState(() {});
                                    }
                                  },
                                  style: OutlinedButton.styleFrom(
                                    foregroundColor: payNavy,
                                    side: const BorderSide(color: payNavy, width: 1.4),
                                    padding: EdgeInsets.zero,
                                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                                  ),
                                  child: const Text(
                                    'Restaurer',
                                    style: TextStyle(fontSize: 15.5, fontWeight: FontWeight.w700),
                                  ),
                                ),
                              ),
                            ],
                          );
                        },
                      ),
                    ),
                  ],
                ),
              ),
            ),
          );
        },
      ),
    );
  }

  Future<void> _openProductMenu(ProductModel product) async {
    final action = await showModalBottomSheet<String>(
      context: context,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(26))),
      builder: (sheetContext) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(20, 10, 20, 10),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const WishlistSheetHandle(),
              const SizedBox(height: 18),
              Row(
                children: [
                  OrderItemThumbnail(url: product.imageUrl, width: 128, height: 76),
                  const SizedBox(width: 18),
                  Expanded(
                    child: Text(
                      product.name,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(color: payNavy, fontSize: 19, fontWeight: FontWeight.w800),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 10),
              const Divider(height: 1, color: payBorder),
              WishlistSheetAction(
                icon: Icons.playlist_add_rounded,
                label: 'Ajouter à une liste d’envies',
                chevron: true,
                onTap: () => Navigator.of(sheetContext).pop('wishlist'),
              ),
              const Divider(height: 1, color: payBorder),
              WishlistSheetAction(
                icon: Icons.heart_broken_outlined,
                label: 'Retirer des favoris',
                danger: true,
                chevron: true,
                onTap: () => Navigator.of(sheetContext).pop('remove'),
              ),
            ],
          ),
        ),
      ),
    );
    if (!mounted || action == null) return;
    if (action == 'remove') await _removeFavorite(product);
    if (action == 'wishlist') await _addFavoriteToWishlist(product);
  }

  Future<void> _addFavoriteToWishlist(ProductModel product) async {
    if (!SessionStore.instance.isAuthenticated) {
      showOvanieToast(context, 'Connectez-vous pour utiliser les listes d’envies.', error: true);
      return;
    }
    if (WishlistsStore.instance.items.isEmpty && !WishlistsStore.instance.loading) {
      try {
        await WishlistsStore.instance.refresh();
      } catch (_) {}
    }
    if (!mounted) return;

    final selected = await showModalBottomSheet<Object>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(26))),
      builder: (sheetContext) {
        final lists = WishlistsStore.instance.items;
        return SafeArea(
          child: ConstrainedBox(
            constraints: BoxConstraints(maxHeight: MediaQuery.sizeOf(context).height * .8),
            child: Padding(
              padding: const EdgeInsets.fromLTRB(18, 10, 18, 14),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const WishlistSheetHandle(),
                  const SizedBox(height: 16),
                  Row(
                    children: [
                      const Expanded(
                        child: Text(
                          'Ajouter à une liste d’envies',
                          style: TextStyle(color: payNavy, fontSize: 22, fontWeight: FontWeight.w800),
                        ),
                      ),
                      Material(
                        color: paySoft,
                        shape: const CircleBorder(),
                        child: InkWell(
                          customBorder: const CircleBorder(),
                          onTap: () => Navigator.of(sheetContext).pop(),
                          child: const SizedBox(
                            width: 52,
                            height: 52,
                            child: Icon(Icons.close_rounded, color: payNavy, size: 28),
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 14),
                  Flexible(
                    child: ListView(
                      shrinkWrap: true,
                      children: [
                        for (final wishlist in lists)
                          Padding(
                            padding: const EdgeInsets.only(bottom: 12),
                            child: _WishlistChoice(
                              wishlist: wishlist,
                              alreadyIn: wishlist.products.any((item) => item.id == product.id),
                              onTap: () => Navigator.of(sheetContext).pop(wishlist),
                            ),
                          ),
                        InkWell(
                          onTap: () => Navigator.of(sheetContext).pop('create'),
                          borderRadius: BorderRadius.circular(12),
                          child: Container(
                            height: 62,
                            decoration: BoxDecoration(
                              borderRadius: BorderRadius.circular(12),
                              border: Border.all(color: payBorder),
                            ),
                            child: const Row(
                              mainAxisAlignment: MainAxisAlignment.center,
                              children: [
                                Icon(Icons.add_rounded, color: OvanieColors.orange, size: 28),
                                SizedBox(width: 10),
                                Text(
                                  'Créer une nouvelle liste',
                                  style: TextStyle(
                                    color: OvanieColors.orange,
                                    fontSize: 18,
                                    fontWeight: FontWeight.w600,
                                  ),
                                ),
                              ],
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
      },
    );

    if (selected == null || !mounted) return;
    try {
      WishlistModel target;
      if (selected == 'create') {
        final name = await showCreateWishlistDialog(context);
        if (name == null || !mounted) return;
        target = await WishlistsStore.instance.create(name);
      } else {
        target = selected as WishlistModel;
        if (target.products.any((item) => item.id == product.id)) {
          showOvanieToast(context, 'Ce produit est déjà dans « ${target.name} ».');
          return;
        }
      }
      await WishlistsStore.instance.addProduct(target, product);
      if (mounted) showOvanieToast(context, 'Produit ajouté à la liste d’envies.');
    } catch (_) {
      if (mounted) showOvanieToast(context, 'Impossible d’ajouter ce produit à la liste.', error: true);
    }
  }

  void _addToCart(ProductModel product) {
    final result = CartStore.instance.add(product);
    showOvanieToast(context, result.message, error: !result.success);
  }

  void _toggleRecommendation(ProductModel product) {
    if (!SessionStore.instance.isAuthenticated) return;
    FavoritesStore.instance.toggle(product);
  }

  void _openProduct(ProductModel product) {
    Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => ProductDetailScreen(product: product)));
  }

  /// Onglet Compte quand l'écran vit dans la barre d'onglets, sinon connexion directe.
  void _openAccountOrLogin() {
    if (widget.onOpenAccount != null) {
      widget.onOpenAccount!();
      return;
    }
    Navigator.of(context).push(MaterialPageRoute<bool>(builder: (_) => const LoginScreen()));
  }

  void _openSearch() {
    Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => const SearchScreen()));
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: Listenable.merge([SessionStore.instance, FavoritesStore.instance, WishlistsStore.instance]),
      builder: (context, _) {
        if (_wishlistMode) {
          return WishlistsScreen(
            favoritesCount: FavoritesStore.instance.count,
            onBackToFavorites: () => setState(() => _wishlistMode = false),
            onOpenSearch: _openSearch,
          );
        }

        // Les favoris sont liés au compte : sans connexion, on propose d'y accéder.
        if (!SessionStore.instance.isAuthenticated) {
          return AuthRequiredPage(onOpenAccount: _openAccountOrLogin);
        }

        final favorites = FavoritesStore.instance.items;
        final seen = <int>{};
        final suggestions = _recommendations
            .where((product) => !FavoritesStore.instance.contains(product) && seen.add(product.id))
            .take(10)
            .toList(growable: false);
        final empty = favorites.isEmpty && !FavoritesStore.instance.loading;
        return AnnotatedRegion<SystemUiOverlayStyle>(
          value: SystemUiOverlayStyle.dark.copyWith(statusBarColor: Colors.transparent),
          child: Scaffold(
            backgroundColor: Colors.white,
            body: SafeArea(
              bottom: false,
              child: RefreshIndicator(
                color: OvanieColors.orange,
                onRefresh: () async {
                  await FavoritesStore.instance.refreshFromServer();
                  await _loadRecommendations();
                },
                child: CustomScrollView(
                  physics: const AlwaysScrollableScrollPhysics(parent: BouncingScrollPhysics()),
                  slivers: [
                    SliverToBoxAdapter(
                      child: FavoritesPageTitle(
                        title: 'Favoris',
                        trailing: favorites.isEmpty
                            ? null
                            : IconButton(
                                onPressed: _clearFavorites,
                                tooltip: 'Supprimer tous les favoris',
                                icon: const Icon(Icons.delete_outline_rounded, color: payNavy, size: 32),
                              ),
                      ),
                    ),
                    SliverToBoxAdapter(
                      child: FavoritesSegmentedTabs(
                        favoritesCount: favorites.length,
                        wishlistsSelected: false,
                        onFavorites: () {},
                        onWishlists: () => setState(() => _wishlistMode = true),
                      ),
                    ),
                    if (FavoritesStore.instance.loading && favorites.isEmpty)
                      const SliverFillRemaining(
                        hasScrollBody: false,
                        child: Center(child: CircularProgressIndicator(color: OvanieColors.orange)),
                      )
                    else if (empty) ...[
                      SliverToBoxAdapter(
                        child: _EmptyFavorites(onDiscover: _startShopping, onCategories: _openCategories),
                      ),
                      if (_recentlyRemoved.isNotEmpty)
                        SliverToBoxAdapter(child: _RemovedBanner(onTap: _showRemovedProducts)),
                      if (suggestions.isNotEmpty)
                        SliverToBoxAdapter(
                          child: Container(
                            color: const Color(0xFFF6F7F9),
                            padding: const EdgeInsets.only(bottom: 18),
                            child: _Recommendations(
                              products: suggestions,
                              onOpenProduct: _openProduct,
                              onToggleFavorite: _toggleRecommendation,
                            ),
                          ),
                        ),
                    ] else ...[
                      SliverToBoxAdapter(
                        child: Padding(
                          padding: const EdgeInsets.fromLTRB(18, 14, 18, 12),
                          child: Text(
                            'Vous avez ${favorites.length} produit${favorites.length > 1 ? 's' : ''} dans vos favoris',
                            style: const TextStyle(color: payMuted, fontSize: 15.5),
                          ),
                        ),
                      ),
                      SliverPadding(
                        padding: const EdgeInsets.symmetric(horizontal: 14),
                        sliver: SliverGrid(
                          gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                            crossAxisCount: 2,
                            mainAxisSpacing: 12,
                            crossAxisSpacing: 12,
                            mainAxisExtent: 352,
                          ),
                          delegate: SliverChildBuilderDelegate(
                            (context, index) => _FavoriteProductCard(
                              product: favorites[index],
                              onOpen: () => _openProduct(favorites[index]),
                              onRemove: () => _removeFavorite(favorites[index]),
                              onMenu: () => _openProductMenu(favorites[index]),
                              onAddToCart: () => _addToCart(favorites[index]),
                            ),
                            childCount: favorites.length,
                          ),
                        ),
                      ),
                      if (_recentlyRemoved.isNotEmpty)
                        SliverToBoxAdapter(child: _RemovedBanner(onTap: _showRemovedProducts)),
                      if (suggestions.isNotEmpty)
                        SliverToBoxAdapter(
                          child: _Recommendations(
                            products: suggestions,
                            onOpenProduct: _openProduct,
                            onToggleFavorite: _toggleRecommendation,
                          ),
                        ),
                      const SliverToBoxAdapter(child: SizedBox(height: 22)),
                    ],
                  ],
                ),
              ),
            ),
          ),
        );
      },
    );
  }
}

/// Libellé et couleur de la disponibilité, d'après le stock réel.
(String, Color, Color) _stockOf(ProductModel product) {
  final status = product.availabilityStatus.toLowerCase();
  if (status == 'on_order' || status == 'preorder') {
    return ('Sur commande', const Color(0xFF0B5ED7), const Color(0xFFE6F0FF));
  }
  if (product.stock <= 0 || status == 'out_of_stock' || !product.canAddToCart) {
    return ('Indisponible', const Color(0xFF6B7591), const Color(0xFFEFF2F6));
  }
  if (product.stock <= 5) return ('Stock limité', const Color(0xFFC2410C), const Color(0xFFFFF1E6));
  return ('En stock', const Color(0xFF15803D), const Color(0xFFE6F6EC));
}

class _PriceText extends StatelessWidget {
  final ProductModel product;
  final double size;

  const _PriceText({required this.product, this.size = 18});

  @override
  Widget build(BuildContext context) => FittedBox(
    fit: BoxFit.scaleDown,
    alignment: Alignment.centerLeft,
    child: Text.rich(
      TextSpan(
        text: formatFcfa(product.finalPrice),
        style: TextStyle(color: payNavy, fontSize: size, fontWeight: FontWeight.w800),
        children: [
          TextSpan(
            text: ' / ${shortSaleUnit(product.unitLabel)}',
            style: TextStyle(color: payMuted, fontSize: size * .8, fontWeight: FontWeight.w400),
          ),
        ],
      ),
      maxLines: 1,
    ),
  );
}

class _FavoriteProductCard extends StatelessWidget {
  final ProductModel product;
  final VoidCallback onOpen;
  final VoidCallback onRemove;
  final VoidCallback onMenu;
  final VoidCallback onAddToCart;

  const _FavoriteProductCard({
    required this.product,
    required this.onOpen,
    required this.onRemove,
    required this.onMenu,
    required this.onAddToCart,
  });

  @override
  Widget build(BuildContext context) {
    final (label, color, background) = _stockOf(product);
    final available = product.canAddToCart && label != 'Indisponible';
    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(14),
      child: InkWell(
        onTap: onOpen,
        borderRadius: BorderRadius.circular(14),
        child: Container(
          padding: const EdgeInsets.all(10),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: payBorder),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Stack(
                children: [
                  Container(
                    height: 162,
                    width: double.infinity,
                    padding: const EdgeInsets.all(8),
                    decoration: BoxDecoration(color: const Color(0xFFF4F5F7), borderRadius: BorderRadius.circular(10)),
                    child: product.imageUrl.trim().isEmpty
                        ? const Icon(Icons.inventory_2_outlined, color: Color(0xFFB5BFCE), size: 44)
                        : Image.network(
                            product.imageUrl,
                            fit: BoxFit.contain,
                            cacheWidth: 400,
                            errorBuilder: (_, __, ___) =>
                                const Icon(Icons.inventory_2_outlined, color: Color(0xFFB5BFCE), size: 44),
                          ),
                  ),
                  Positioned(
                    top: 2,
                    right: 2,
                    child: IconButton(
                      onPressed: onRemove,
                      tooltip: 'Retirer des favoris',
                      icon: const Icon(Icons.favorite_rounded, color: OvanieColors.orange, size: 28),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 10),
              Text(
                product.brand.trim().isEmpty ? 'OVANIE' : product.brand.trim().toUpperCase(),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(color: payMuted, fontSize: 12.5, letterSpacing: .3),
              ),
              const SizedBox(height: 2),
              Text(
                product.name,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(color: payNavy, fontSize: 15.5, height: 1.2),
              ),
              const Spacer(),
              _PriceText(product: product),
              const SizedBox(height: 8),
              Row(
                children: [
                  Expanded(
                    child: Align(
                      alignment: Alignment.centerLeft,
                      child: FittedBox(
                        fit: BoxFit.scaleDown,
                        alignment: Alignment.centerLeft,
                        child: Container(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
                          decoration: BoxDecoration(color: background, borderRadius: BorderRadius.circular(16)),
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Container(
                                width: 8,
                                height: 8,
                                decoration: BoxDecoration(color: color, shape: BoxShape.circle),
                              ),
                              const SizedBox(width: 5),
                              Text(label, style: TextStyle(color: color, fontSize: 13)),
                            ],
                          ),
                        ),
                      ),
                    ),
                  ),
                  Tooltip(
                    message: 'Options',
                    child: InkWell(
                      onTap: onMenu,
                      customBorder: const CircleBorder(),
                      child: const Padding(
                        padding: EdgeInsets.symmetric(horizontal: 2, vertical: 6),
                        child: Icon(Icons.more_vert_rounded, color: payNavy, size: 24),
                      ),
                    ),
                  ),
                  const SizedBox(width: 2),
                  SizedBox(
                    width: 42,
                    height: 42,
                    child: FilledButton(
                      onPressed: available ? onAddToCart : null,
                      style: FilledButton.styleFrom(
                        padding: EdgeInsets.zero,
                        elevation: 0,
                        backgroundColor: OvanieColors.orange,
                        disabledBackgroundColor: const Color(0xFFE5E8EE),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                      ),
                      child: const Icon(Icons.shopping_cart_outlined, color: Colors.white, size: 22),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _WishlistChoice extends StatelessWidget {
  final WishlistModel wishlist;
  final bool alreadyIn;
  final VoidCallback onTap;

  const _WishlistChoice({required this.wishlist, required this.alreadyIn, required this.onTap});

  @override
  Widget build(BuildContext context) => InkWell(
    onTap: onTap,
    borderRadius: BorderRadius.circular(12),
    child: Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: payBorder),
      ),
      child: Row(
        children: [
          if (wishlist.products.isEmpty)
            Container(
              width: 82,
              height: 82,
              decoration: BoxDecoration(color: const Color(0xFFF4F6F9), borderRadius: BorderRadius.circular(8)),
              child: const Icon(Icons.inventory_2_outlined, color: Color(0xFF8A93A8), size: 32),
            )
          else
            WishlistThumbGrid(products: wishlist.products, size: 82),
          const SizedBox(width: 16),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  wishlist.name,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(color: payNavy, fontSize: 18, fontWeight: FontWeight.w700),
                ),
                const SizedBox(height: 3),
                Text(
                  alreadyIn
                      ? 'Déjà dans cette liste'
                      : '${wishlist.productsCount} produit${wishlist.productsCount > 1 ? 's' : ''}',
                  style: TextStyle(color: alreadyIn ? payGreenDark : payMuted, fontSize: 15),
                ),
              ],
            ),
          ),
          const Icon(Icons.chevron_right_rounded, color: payNavy, size: 28),
        ],
      ),
    ),
  );
}

class _RemovedBanner extends StatelessWidget {
  final VoidCallback onTap;

  const _RemovedBanner({required this.onTap});

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.fromLTRB(16, 16, 16, 0),
    child: Material(
      color: const Color(0xFFF2F4F7),
      borderRadius: BorderRadius.circular(12),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(12),
        child: const Padding(
          padding: EdgeInsets.fromLTRB(16, 14, 12, 14),
          child: Row(
            children: [
              Icon(Icons.history_rounded, color: payNavy, size: 34),
              SizedBox(width: 16),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Vous avez supprimé un produit de vos favoris ?',
                      style: TextStyle(color: payNavy, fontSize: 14.5),
                    ),
                    SizedBox(height: 2),
                    Text(
                      'Voir les produits supprimés',
                      style: TextStyle(
                        color: OvanieColors.orange,
                        fontSize: 15,
                        decoration: TextDecoration.underline,
                        decorationColor: OvanieColors.orange,
                      ),
                    ),
                  ],
                ),
              ),
              Icon(Icons.chevron_right_rounded, color: payMuted, size: 26),
            ],
          ),
        ),
      ),
    ),
  );
}

class _Recommendations extends StatelessWidget {
  final List<ProductModel> products;
  final ValueChanged<ProductModel> onOpenProduct;
  final ValueChanged<ProductModel> onToggleFavorite;

  const _Recommendations({required this.products, required this.onOpenProduct, required this.onToggleFavorite});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 20, 0, 0),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Vous pourriez aussi aimer',
            style: TextStyle(color: payNavy, fontSize: 21, fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 12),
          SizedBox(
            height: 236,
            child: ListView.separated(
              padding: const EdgeInsets.only(right: 16),
              scrollDirection: Axis.horizontal,
              itemCount: products.length,
              separatorBuilder: (_, __) => const SizedBox(width: 10),
              itemBuilder: (context, index) {
                final product = products[index];
                final favorite = FavoritesStore.instance.contains(product);
                return InkWell(
                  onTap: () => onOpenProduct(product),
                  borderRadius: BorderRadius.circular(12),
                  child: Container(
                    width: 156,
                    padding: const EdgeInsets.all(8),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: payBorder),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Stack(
                          children: [
                            Container(
                              height: 116,
                              width: double.infinity,
                              padding: const EdgeInsets.all(6),
                              decoration: BoxDecoration(
                                color: const Color(0xFFF4F5F7),
                                borderRadius: BorderRadius.circular(8),
                              ),
                              child: product.imageUrl.trim().isEmpty
                                  ? const Icon(Icons.inventory_2_outlined, color: Color(0xFFB5BFCE))
                                  : Image.network(
                                      product.imageUrl,
                                      fit: BoxFit.contain,
                                      cacheWidth: 300,
                                      errorBuilder: (_, __, ___) =>
                                          const Icon(Icons.inventory_2_outlined, color: Color(0xFFB5BFCE)),
                                    ),
                            ),
                            Positioned(
                              top: 0,
                              right: 0,
                              child: IconButton(
                                onPressed: () => onToggleFavorite(product),
                                tooltip: favorite ? 'Retirer des favoris' : 'Ajouter aux favoris',
                                icon: Icon(
                                  favorite ? Icons.favorite_rounded : Icons.favorite_border_rounded,
                                  color: favorite ? OvanieColors.orange : payNavy,
                                  size: 24,
                                ),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 8),
                        Text(
                          product.brand.trim().isEmpty ? 'OVANIE' : product.brand.trim().toUpperCase(),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(color: payMuted, fontSize: 11.5),
                        ),
                        Text(
                          product.name,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(color: payNavy, fontSize: 13.5, height: 1.2),
                        ),
                        const Spacer(),
                        _PriceText(product: product, size: 15),
                      ],
                    ),
                  ),
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}

class _EmptyFavorites extends StatelessWidget {
  final VoidCallback onDiscover;
  final VoidCallback onCategories;

  const _EmptyFavorites({required this.onDiscover, required this.onCategories});

  @override
  Widget build(BuildContext context) {
    final width = MediaQuery.sizeOf(context).width;
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 34, 16, 26),
      child: Column(
        children: [
          Image.asset(
            'assets/images/checkout/favorites_empty.png',
            width: (width * .82).clamp(240.0, 360.0),
            fit: BoxFit.contain,
          ),
          const SizedBox(height: 26),
          const Text(
            'Aucun favori pour le moment',
            textAlign: TextAlign.center,
            style: TextStyle(color: payNavy, fontSize: 26, fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 12),
          const Text(
            'Vous n’avez encore ajouté aucun produit à vos favoris. Découvrez nos produits et ajoutez vos coups de cœur pour les retrouver ici.',
            textAlign: TextAlign.center,
            style: TextStyle(color: payMuted, fontSize: 16, height: 1.45),
          ),
          const SizedBox(height: 26),
          PaymentButton(label: 'Découvrir nos produits', height: 56, onPressed: onDiscover),
          const SizedBox(height: 12),
          PaymentButton(label: 'Voir les catégories', outlined: true, height: 56, onPressed: onCategories),
        ],
      ),
    );
  }
}
