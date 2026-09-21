import 'dart:async';

import 'package:flutter/material.dart';

import '../../../app/theme.dart';
import '../../../core/utils/formatters.dart';
import '../../auth/domain/session_store.dart';
import '../../cart/domain/cart_store.dart';
import '../../home/data/marketplace_repository.dart';
import '../../products/domain/product_model.dart';
import '../../products/presentation/product_detail_screen.dart';
import '../../search/presentation/search_screen.dart';
import '../../wishlists/domain/wishlists_store.dart';
import '../../wishlists/presentation/wishlists_screen.dart';
import '../domain/favorites_store.dart';

class FavoritesScreen extends StatefulWidget {
  final VoidCallback? onOpenAccount;
  final VoidCallback? onOpenCart;
  final VoidCallback? onStartShopping;

  const FavoritesScreen({
    super.key,
    this.onOpenAccount,
    this.onOpenCart,
    this.onStartShopping,
  });

  @override
  State<FavoritesScreen> createState() => _FavoritesScreenState();
}

class _FavoritesScreenState extends State<FavoritesScreen> {
  final MarketplaceRepository _marketplace = const MarketplaceRepository();
  final Map<int, int> _quantities = <int, int>{};
  final List<ProductModel> _recentlyRemoved = <ProductModel>[];
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
      var products = await _marketplace.safeProducts(
        () => _marketplace.getRecommendations(limit: 12),
      );
      if (products.isEmpty) {
        products = await _marketplace.safeProducts(
          () => _marketplace.getLatestProducts(limit: 12),
        );
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

  void _openCart() {
    if (widget.onOpenCart != null) {
      widget.onOpenCart!();
    }
  }

  Future<void> _removeFavorite(ProductModel product) async {
    final wasFavorite = FavoritesStore.instance.contains(product);
    if (!wasFavorite) return;
    setState(() {
      _recentlyRemoved.removeWhere((item) => item.id == product.id);
      _recentlyRemoved.insert(0, product);
      if (_recentlyRemoved.length > 8) {
        _recentlyRemoved.removeRange(8, _recentlyRemoved.length);
      }
    });
    try {
      await FavoritesStore.instance.remove(product);
    } catch (_) {
      if (!mounted) return;
      setState(() => _recentlyRemoved.removeWhere((item) => item.id == product.id));
      _showMessage('Impossible de retirer ce produit des favoris.');
    }
  }

  Future<void> _clearFavorites() async {
    final items = FavoritesStore.instance.items;
    if (items.isEmpty) return;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Supprimer tous les favoris ?'),
        content: const Text(
          'Tous les produits enregistrés dans vos favoris seront retirés.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(false),
            child: const Text('Annuler'),
          ),
          FilledButton(
            onPressed: () => Navigator.of(dialogContext).pop(true),
            style: FilledButton.styleFrom(backgroundColor: OvanieColors.orange),
            child: const Text('Supprimer'),
          ),
        ],
      ),
    );
    if (confirmed != true) return;

    setState(() {
      _recentlyRemoved
        ..clear()
        ..addAll(items.reversed.take(8));
    });
    try {
      await FavoritesStore.instance.clear();
    } catch (_) {
      if (!mounted) return;
      _showMessage('Impossible de supprimer vos favoris pour le moment.');
    }
  }

  void _restoreRemoved(ProductModel product) {
    if (!SessionStore.instance.isAuthenticated) return;
    FavoritesStore.instance.toggle(product);
    setState(() => _recentlyRemoved.removeWhere((item) => item.id == product.id));
  }

  void _showRemovedProducts() {
    if (_recentlyRemoved.isEmpty) return;
    showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      backgroundColor: Colors.white,
      builder: (sheetContext) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(20, 4, 20, 22),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Produits supprimés récemment',
                style: TextStyle(
                  color: OvanieColors.navy,
                  fontSize: 18,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 14),
              ..._recentlyRemoved.take(5).map(
                    (product) => ListTile(
                      contentPadding: EdgeInsets.zero,
                      leading: _ProductImage(
                        product: product,
                        width: 52,
                        height: 52,
                      ),
                      title: Text(
                        product.name,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                      trailing: TextButton(
                        onPressed: () {
                          _restoreRemoved(product);
                          Navigator.of(sheetContext).pop();
                        },
                        child: const Text('Restaurer'),
                      ),
                    ),
                  ),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _addFavoriteToWishlist(ProductModel product) async {
    if (!SessionStore.instance.isAuthenticated) {
      _showMessage('Connectez-vous pour utiliser les listes d’envies.');
      return;
    }

    if (WishlistsStore.instance.items.isEmpty && !WishlistsStore.instance.loading) {
      try {
        await WishlistsStore.instance.refresh();
      } catch (_) {}
    }

    if (!mounted) return;
    final selected = await showModalBottomSheet<dynamic>(
      context: context,
      showDragHandle: true,
      backgroundColor: Colors.white,
      builder: (sheetContext) {
        final lists = WishlistsStore.instance.items;
        return SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(18, 2, 18, 18),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Ajouter à une liste d’envies',
                  style: TextStyle(
                    color: OvanieColors.navy,
                    fontSize: 19,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 10),
                ...lists.take(8).map(
                      (wishlist) => ListTile(
                        contentPadding: EdgeInsets.zero,
                        leading: const CircleAvatar(
                          backgroundColor: Color(0xFFFFF2EA),
                          child: Icon(
                            Icons.favorite_border_rounded,
                            color: OvanieColors.orange,
                          ),
                        ),
                        title: Text(wishlist.name),
                        subtitle: Text(
                          '${wishlist.productsCount} produit${wishlist.productsCount > 1 ? 's' : ''}',
                        ),
                        onTap: () => Navigator.of(sheetContext).pop(wishlist),
                      ),
                    ),
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  leading: const CircleAvatar(
                    backgroundColor: Color(0xFFF3F6FD),
                    child: Icon(Icons.add_rounded, color: OvanieColors.blue),
                  ),
                  title: const Text('Créer une nouvelle liste'),
                  onTap: () => Navigator.of(sheetContext).pop('create'),
                ),
              ],
            ),
          ),
        );
      },
    );

    if (selected == null || !mounted) return;
    try {
      dynamic target = selected;
      if (selected == 'create') {
        final controller = TextEditingController();
        final name = await showDialog<String>(
          context: context,
          builder: (dialogContext) => AlertDialog(
            title: const Text('Créer une liste'),
            content: TextField(
              controller: controller,
              autofocus: true,
              decoration: const InputDecoration(hintText: 'Nom de la liste'),
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.of(dialogContext).pop(),
                child: const Text('Annuler'),
              ),
              FilledButton(
                onPressed: () => Navigator.of(dialogContext).pop(controller.text.trim()),
                style: FilledButton.styleFrom(backgroundColor: OvanieColors.orange),
                child: const Text('Créer'),
              ),
            ],
          ),
        );
        controller.dispose();
        if (name == null || name.trim().isEmpty) return;
        target = await WishlistsStore.instance.create(name.trim());
      }
      await WishlistsStore.instance.addProduct(target, product);
      _showMessage('Produit ajouté à la liste d’envies.', success: true);
    } catch (_) {
      _showMessage('Impossible d’ajouter ce produit à la liste.');
    }
  }

  void _addToCart(ProductModel product) {
    final quantity = _quantities[product.id] ?? 1;
    final result = CartStore.instance.addQuantity(product, quantity);
    _showMessage(result.message, success: result.success);
  }

  void _changeQuantity(ProductModel product, int delta) {
    final current = _quantities[product.id] ?? 1;
    final next = (current + delta)
        .clamp(1, product.stock > 0 ? product.stock : 99)
        .toInt();
    setState(() => _quantities[product.id] = next);
  }

  void _showMessage(String message, {bool success = false}) {
    if (!mounted) return;
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          content: Text(message),
          backgroundColor: success ? OvanieColors.success : OvanieColors.navy,
          behavior: SnackBarBehavior.floating,
        ),
      );
  }

  void _openProduct(ProductModel product) {
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => ProductDetailScreen(product: product),
      ),
    );
  }

  void _openSearch() {
    Navigator.of(context).push(
      MaterialPageRoute<void>(builder: (_) => const SearchScreen()),
    );
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: Listenable.merge([
        SessionStore.instance,
        FavoritesStore.instance,
        CartStore.instance,
        WishlistsStore.instance,
      ]),
      builder: (context, _) {
        if (_wishlistMode) {
          return WishlistsScreen(
            favoritesCount: FavoritesStore.instance.count,
            onBackToFavorites: () => setState(() => _wishlistMode = false),
            onOpenSearch: _openSearch,
          );
        }

        final favorites = FavoritesStore.instance.items;
        return Scaffold(
          backgroundColor: Colors.white,
          body: SafeArea(
            bottom: false,
            child: RefreshIndicator(
              color: OvanieColors.orange,
              onRefresh: () async {
                if (SessionStore.instance.isAuthenticated) {
                  await FavoritesStore.instance.refreshFromServer();
                }
                await _loadRecommendations();
              },
              child: CustomScrollView(
                physics: const AlwaysScrollableScrollPhysics(
                  parent: BouncingScrollPhysics(),
                ),
                slivers: [
                  SliverToBoxAdapter(
                    child: _Header(
                      onSearch: _openSearch,
                      onDelete: _clearFavorites,
                    ),
                  ),
                  SliverToBoxAdapter(
                    child: _Tabs(
                      favoritesCount: favorites.length,
                      onWishlists: () => setState(() => _wishlistMode = true),
                    ),
                  ),
                  if (FavoritesStore.instance.loading && favorites.isEmpty)
                    const SliverFillRemaining(
                      hasScrollBody: false,
                      child: Center(
                        child: CircularProgressIndicator(
                          color: OvanieColors.orange,
                        ),
                      ),
                    )
                  else if (favorites.isEmpty)
                    SliverFillRemaining(
                      hasScrollBody: false,
                      child: _EmptyFavorites(
                        onDiscover: _startShopping,
                        onCategories: _startShopping,
                      ),
                    )
                  else ...[
                    SliverToBoxAdapter(
                      child: _CountBanner(count: favorites.length),
                    ),
                    SliverPadding(
                      padding: const EdgeInsets.fromLTRB(18, 2, 18, 0),
                      sliver: SliverList(
                        delegate: SliverChildBuilderDelegate(
                          (context, rawIndex) {
                            if (rawIndex.isOdd) {
                              return const SizedBox(height: 10);
                            }
                            final index = rawIndex ~/ 2;
                            final product = favorites[index];
                            return _FavoriteProductCard(
                              product: product,
                              quantity: _quantities[product.id] ?? 1,
                              onOpen: () => _openProduct(product),
                              onRemove: () => _removeFavorite(product),
                              onAddToWishlist: () => _addFavoriteToWishlist(product),
                              onMinus: () => _changeQuantity(product, -1),
                              onPlus: () => _changeQuantity(product, 1),
                              onAddToCart: () => _addToCart(product),
                            );
                          },
                          childCount: favorites.length * 2 - 1,
                        ),
                      ),
                    ),
                    if (_recentlyRemoved.isNotEmpty)
                      SliverToBoxAdapter(
                        child: _RemovedBanner(onTap: _showRemovedProducts),
                      ),
                    if (_recommendations.isNotEmpty)
                      SliverToBoxAdapter(
                        child: _Recommendations(
                          products: _recommendations
                              .where(
                                (product) => !FavoritesStore.instance.contains(product),
                              )
                              .take(10)
                              .toList(growable: false),
                          onOpenProduct: _openProduct,
                          onAddToCart: (product) {
                            final result = CartStore.instance.add(product);
                            _showMessage(result.message, success: result.success);
                          },
                        ),
                      ),
                    const SliverToBoxAdapter(child: SizedBox(height: 22)),
                  ],
                ],
              ),
            ),
          ),
        );
      },
    );
  }
}

class _Header extends StatelessWidget {
  final VoidCallback onSearch;
  final VoidCallback onDelete;

  const _Header({required this.onSearch, required this.onDelete});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 14, 18, 12),
      child: Row(
        children: [
          const Expanded(
            child: Text(
              'Favoris',
              style: TextStyle(
                color: OvanieColors.navy,
                fontSize: 30,
                height: 1,
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
          IconButton(
            onPressed: onSearch,
            icon: const Icon(Icons.search_rounded),
            color: OvanieColors.navy,
            iconSize: 31,
          ),
          const SizedBox(width: 4),
          IconButton(
            onPressed: onDelete,
            icon: const Icon(Icons.delete_outline_rounded),
            color: OvanieColors.navy,
            iconSize: 28,
          ),
        ],
      ),
    );
  }
}

class _Tabs extends StatelessWidget {
  final int favoritesCount;
  final VoidCallback onWishlists;

  const _Tabs({required this.favoritesCount, required this.onWishlists});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(18, 4, 18, 0),
      child: Row(
        children: [
          Expanded(
            child: Container(
              height: 52,
              decoration: const BoxDecoration(
                border: Border(
                  bottom: BorderSide(color: OvanieColors.orange, width: 2.4),
                ),
              ),
              alignment: Alignment.center,
              child: Text(
                'Mes favoris ($favoritesCount)',
                style: const TextStyle(
                  color: OvanieColors.orange,
                  fontSize: 15.5,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ),
          ),
          Expanded(
            child: InkWell(
              onTap: onWishlists,
              child: Container(
                height: 52,
                decoration: const BoxDecoration(
                  border: Border(
                    bottom: BorderSide(color: OvanieColors.border, width: 1.2),
                  ),
                ),
                alignment: Alignment.center,
                child: const Text(
                  "Listes d'envies",
                  style: TextStyle(
                    color: OvanieColors.navy,
                    fontSize: 15.5,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _CountBanner extends StatelessWidget {
  final int count;

  const _CountBanner({required this.count});

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.fromLTRB(18, 14, 18, 14),
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 13),
      decoration: BoxDecoration(
        color: const Color(0xFFFFF4EC),
        borderRadius: BorderRadius.circular(10),
      ),
      child: Row(
        children: [
          const Icon(Icons.favorite_border_rounded, color: OvanieColors.orange),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              'Vous avez $count produit${count > 1 ? 's' : ''} dans vos favoris',
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
              color: Color(0xFF6C2210),
              fontSize: 14,
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _FavoriteProductCard extends StatelessWidget {
  final ProductModel product;
  final int quantity;
  final VoidCallback onOpen;
  final VoidCallback onRemove;
  final VoidCallback onAddToWishlist;
  final VoidCallback onMinus;
  final VoidCallback onPlus;
  final VoidCallback onAddToCart;

  const _FavoriteProductCard({
    required this.product,
    required this.quantity,
    required this.onOpen,
    required this.onRemove,
    required this.onAddToWishlist,
    required this.onMinus,
    required this.onPlus,
    required this.onAddToCart,
  });

  @override
  Widget build(BuildContext context) {
    final inStock = product.stock > 0 && product.canAddToCart;
    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(13),
      child: InkWell(
        onTap: onOpen,
        borderRadius: BorderRadius.circular(13),
        child: Container(
          height: 144,
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(
            border: Border.all(color: OvanieColors.border),
            borderRadius: BorderRadius.circular(13),
            boxShadow: const [
              BoxShadow(
                color: Color(0x0A001D4A),
                blurRadius: 10,
                offset: Offset(0, 3),
              ),
            ],
          ),
          child: Row(
            children: [
              _ProductImage(product: product, width: 82, height: 118),
              const SizedBox(width: 9),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      product.name,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: OvanieColors.navy,
                        fontSize: 15,
                        height: 1.1,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      product.brand.isNotEmpty
                          ? product.brand
                          : (product.categoryName.isNotEmpty
                              ? product.categoryName
                              : 'OVANIE'),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: OvanieColors.muted,
                        fontSize: 12.5,
                      ),
                    ),
                    const Spacer(),
                    Text(
                      formatFcfa(product.finalPrice),
                      style: const TextStyle(
                        color: OvanieColors.orange,
                        fontSize: 16,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      inStock ? 'En stock' : 'Stock limité',
                      style: TextStyle(
                        color: inStock ? const Color(0xFF0C9A43) : OvanieColors.orange,
                        fontSize: 12,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 6),
              SizedBox(
                width: 130,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.end,
                      children: [
                        IconButton(
                          tooltip: 'Retirer des favoris',
                          onPressed: onRemove,
                          padding: EdgeInsets.zero,
                          constraints: const BoxConstraints.tightFor(
                            width: 38,
                            height: 38,
                          ),
                          icon: const Icon(
                            Icons.favorite_rounded,
                            color: OvanieColors.orange,
                            size: 27,
                          ),
                        ),
                        PopupMenuButton<String>(
                          padding: EdgeInsets.zero,
                          icon: const Icon(
                            Icons.more_vert_rounded,
                            color: OvanieColors.navy,
                          ),
                          onSelected: (value) {
                            if (value == 'remove') onRemove();
                            if (value == 'wishlist') onAddToWishlist();
                          },
                          itemBuilder: (_) => const [
                            PopupMenuItem<String>(
                              value: 'wishlist',
                              child: Text('Ajouter à une liste d’envies'),
                            ),
                            PopupMenuItem<String>(
                              value: 'remove',
                              child: Text('Retirer des favoris'),
                            ),
                          ],
                        ),
                      ],
                    ),
                    const Spacer(),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.end,
                      children: [
                        _QuantityControl(
                          quantity: quantity,
                          onMinus: onMinus,
                          onPlus: onPlus,
                        ),
                        const SizedBox(width: 9),
                        SizedBox(
                          width: 44,
                          height: 44,
                          child: FilledButton(
                            onPressed: inStock ? onAddToCart : null,
                            style: FilledButton.styleFrom(
                              padding: EdgeInsets.zero,
                              backgroundColor: OvanieColors.orange,
                              disabledBackgroundColor: const Color(0xFFE8EBF0),
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(10),
                              ),
                            ),
                            child: const Icon(
                              Icons.shopping_cart_outlined,
                              color: Colors.white,
                            ),
                          ),
                        ),
                      ],
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

class _QuantityControl extends StatelessWidget {
  final int quantity;
  final VoidCallback onMinus;
  final VoidCallback onPlus;

  const _QuantityControl({
    required this.quantity,
    required this.onMinus,
    required this.onPlus,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 72,
      height: 44,
      decoration: BoxDecoration(
        color: Colors.white,
        border: Border.all(color: OvanieColors.border),
        borderRadius: BorderRadius.circular(9),
      ),
      child: Row(
        children: [
          Expanded(
            child: InkWell(
              onTap: onMinus,
              child: const Center(
                child: Icon(Icons.remove_rounded, size: 18),
              ),
            ),
          ),
          Expanded(
            child: Center(
              child: Text(
                '$quantity',
                style: const TextStyle(
                  color: OvanieColors.navy,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ),
          ),
          Expanded(
            child: InkWell(
              onTap: onPlus,
              child: const Center(
                child: Icon(Icons.add_rounded, size: 18),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _RemovedBanner extends StatelessWidget {
  final VoidCallback onTap;

  const _RemovedBanner({required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(18, 12, 18, 0),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(10),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
          decoration: BoxDecoration(
            color: const Color(0xFFF7FAFF),
            border: Border.all(color: const Color(0xFFDDE7F6)),
            borderRadius: BorderRadius.circular(10),
          ),
          child: const Row(
            children: [
              CircleAvatar(
                radius: 17,
                backgroundColor: Colors.white,
                child: Icon(
                  Icons.delete_outline_rounded,
                  color: OvanieColors.navy,
                  size: 20,
                ),
              ),
              SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Vous avez supprimé un produit de vos favoris ?',
                      style: TextStyle(
                        color: OvanieColors.navy,
                        fontSize: 11.5,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    SizedBox(height: 2),
                    Text(
                      'Voir les produits supprimés',
                      style: TextStyle(
                        color: Color(0xFF0759C7),
                        fontSize: 11.5,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ],
                ),
              ),
              Icon(Icons.chevron_right_rounded, color: OvanieColors.navy),
            ],
          ),
        ),
      ),
    );
  }
}

class _Recommendations extends StatelessWidget {
  final List<ProductModel> products;
  final ValueChanged<ProductModel> onOpenProduct;
  final ValueChanged<ProductModel> onAddToCart;

  const _Recommendations({
    required this.products,
    required this.onOpenProduct,
    required this.onAddToCart,
  });

  @override
  Widget build(BuildContext context) {
    if (products.isEmpty) return const SizedBox.shrink();
    return Padding(
      padding: const EdgeInsets.fromLTRB(18, 14, 0, 0),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Vous pourriez aussi aimer',
            style: TextStyle(
              color: OvanieColors.navy,
              fontSize: 18,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 9),
          SizedBox(
            height: 182,
            child: ListView.separated(
              padding: const EdgeInsets.only(right: 18),
              scrollDirection: Axis.horizontal,
              itemCount: products.length,
              separatorBuilder: (_, __) => const SizedBox(width: 9),
              itemBuilder: (context, index) {
                final product = products[index];
                return _RecommendationCard(
                  product: product,
                  onTap: () => onOpenProduct(product),
                  onCart: () => onAddToCart(product),
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}

class _RecommendationCard extends StatelessWidget {
  final ProductModel product;
  final VoidCallback onTap;
  final VoidCallback onCart;

  const _RecommendationCard({
    required this.product,
    required this.onTap,
    required this.onCart,
  });

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: Container(
        width: 146,
        padding: const EdgeInsets.all(9),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: OvanieColors.border),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Stack(
              children: [
                _ProductImage(product: product, width: 128, height: 92),
                const Positioned(
                  right: 0,
                  top: 0,
                  child: Icon(
                    Icons.favorite_border_rounded,
                    color: OvanieColors.navy,
                    size: 21,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 5),
            Text(
              product.name,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                color: OvanieColors.navy,
                fontSize: 11,
                fontWeight: FontWeight.w700,
              ),
            ),
            const Spacer(),
            Row(
              children: [
                Expanded(
                  child: Text(
                    formatFcfa(product.finalPrice),
                    maxLines: 1,
                    style: const TextStyle(
                      color: OvanieColors.orange,
                      fontSize: 11.5,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ),
                InkWell(
                  onTap: onCart,
                  borderRadius: BorderRadius.circular(6),
                  child: Container(
                    width: 30,
                    height: 30,
                    decoration: BoxDecoration(
                      border: Border.all(color: const Color(0xFF1F65FF)),
                      borderRadius: BorderRadius.circular(6),
                    ),
                    child: const Icon(
                      Icons.shopping_cart_outlined,
                      size: 17,
                      color: Color(0xFF1F65FF),
                    ),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _EmptyFavorites extends StatelessWidget {
  final VoidCallback onDiscover;
  final VoidCallback onCategories;

  const _EmptyFavorites({
    required this.onDiscover,
    required this.onCategories,
  });

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      physics: const AlwaysScrollableScrollPhysics(),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(24, 62, 24, 30),
        child: Column(
          children: [
            Image.asset(
              'assets/images/favorites_empty_illustration.png',
              width: 330,
              height: 280,
              fit: BoxFit.contain,
              filterQuality: FilterQuality.high,
            ),
            const SizedBox(height: 8),
            const Text(
              'Aucun favori pour le moment',
              textAlign: TextAlign.center,
              style: TextStyle(
                color: OvanieColors.navy,
                fontSize: 26,
                fontWeight: FontWeight.w900,
              ),
            ),
            const SizedBox(height: 18),
            const Text(
              "Vous n'avez encore ajouté aucun produit\n"
              'à vos favoris.\n'
              'Découvrez nos produits et ajoutez vos coups\n'
              'de cœur pour les retrouver ici.',
              textAlign: TextAlign.center,
              style: TextStyle(
                color: Color(0xFF576482),
                fontSize: 16,
                height: 1.55,
              ),
            ),
            const SizedBox(height: 34),
            Row(
              children: [
                Expanded(
                  child: SizedBox(
                    height: 58,
                    child: FilledButton.icon(
                      onPressed: onDiscover,
                      icon: const Icon(Icons.search_rounded),
                      label: const FittedBox(
                        fit: BoxFit.scaleDown,
                        child: Text(
                          'Découvrir nos produits',
                          maxLines: 1,
                          softWrap: false,
                        ),
                      ),
                      style: FilledButton.styleFrom(
                        backgroundColor: const Color(0xFFFFF2EA),
                        foregroundColor: OvanieColors.orange,
                        elevation: 0,
                        textStyle: const TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w900,
                        ),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
                      ),
                    ),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: SizedBox(
                    height: 58,
                    child: FilledButton.icon(
                      onPressed: onCategories,
                      icon: const Icon(Icons.grid_view_rounded),
                      label: const Text('Voir les catégories'),
                      style: FilledButton.styleFrom(
                        backgroundColor: const Color(0xFFF3F5F9),
                        foregroundColor: OvanieColors.navy,
                        elevation: 0,
                        textStyle: const TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.w900,
                        ),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _ProductImage extends StatelessWidget {
  final ProductModel product;
  final double width;
  final double height;

  const _ProductImage({
    required this.product,
    required this.width,
    required this.height,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      width: width,
      height: height,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(10),
      ),
      clipBehavior: Clip.antiAlias,
      child: product.imageUrl.trim().isEmpty
          ? const Icon(
              Icons.inventory_2_outlined,
              color: Color(0xFFB5BFCE),
              size: 40,
            )
          : Image.network(
              product.imageUrl,
              fit: BoxFit.contain,
              width: width,
              height: height,
              errorBuilder: (_, __, ___) => const Icon(
                Icons.inventory_2_outlined,
                color: Color(0xFFB5BFCE),
                size: 40,
              ),
            ),
    );
  }
}
