import 'package:flutter/material.dart';

import '../../../core/network/api_client.dart';
import '../../../core/utils/formatters.dart';
import '../../../shared/widgets/api_error_card.dart';
import '../../auth/domain/session_store.dart';
import '../../auth/presentation/auth_branding.dart';
import '../../cart/domain/cart_store.dart';
import '../../favorites/domain/favorites_store.dart';
import '../../home/data/marketplace_repository.dart';
import '../../products/domain/product_model.dart';
import '../../products/presentation/product_detail_screen.dart';
import '../../search/presentation/search_screen.dart';
import '../../shell/domain/main_navigation_store.dart';
import '../../shell/presentation/main_navigation_controller.dart';
import 'catalog_widgets.dart';

/// Écran Sous-catégorie OVANIE conforme à la maquette mobile.
///
/// Les produits sont lus depuis Laravel avec `/products?category=<slug>`.
/// Les filtres et tris utilisent `/marketplace/filters`.
class SubcategoryScreen extends StatefulWidget {
  final int? categoryId;
  final String categorySlug;
  final String? categoryName;
  final String? parentCategoryName;
  final bool embedded;
  final VoidCallback? onBackToCategory;

  const SubcategoryScreen({
    super.key,
    this.categoryId,
    required this.categorySlug,
    this.categoryName,
    this.parentCategoryName,
    this.embedded = false,
    this.onBackToCategory,
  });

  @override
  State<SubcategoryScreen> createState() => _SubcategoryScreenState();
}

class _SubcategoryScreenState extends State<SubcategoryScreen> {
  final MarketplaceRepository _repository = const MarketplaceRepository();
  final ScrollController _scrollController = ScrollController();

  List<ProductModel> _products = const <ProductModel>[];
  CatalogFilters? _filters;
  String _sort = 'popular';
  String? _stock;
  String? _brand;
  double? _minPrice;
  double? _maxPrice;
  int _page = 1;
  int _lastPage = 1;
  int _total = 0;
  bool _loading = true;
  bool _loadingMore = false;
  Object? _error;

  String get _title {
    final value = widget.categoryName?.trim();
    return value == null || value.isEmpty ? 'Sous-catégorie' : value;
  }

  @override
  void initState() {
    super.initState();
    _scrollController.addListener(_onScroll);
    _loadInitial();
  }

  @override
  void didUpdateWidget(covariant SubcategoryScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.categorySlug != widget.categorySlug || oldWidget.categoryId != widget.categoryId) {
      _sort = 'popular';
      _stock = null;
      _brand = null;
      _minPrice = null;
      _maxPrice = null;
      _products = const <ProductModel>[];
      _page = 1;
      _lastPage = 1;
      _total = 0;
      _loadInitial();
    }
  }

  @override
  void dispose() {
    _scrollController
      ..removeListener(_onScroll)
      ..dispose();
    super.dispose();
  }

  void _onScroll() {
    if (!_scrollController.hasClients || _loading || _loadingMore) return;
    final position = _scrollController.position;
    if (position.pixels >= position.maxScrollExtent - 420) {
      _loadProducts(reset: false);
    }
  }

  Future<void> _loadInitial() async {
    if (mounted) {
      setState(() {
        _loading = true;
        _error = null;
      });
    }

    try {
      final results = await Future.wait<dynamic>([
        _repository.getCatalogFilters(),
        _repository.getCatalogPage(
          categorySlug: widget.categorySlug,
          sort: _sort,
          stock: _stock,
          brand: _brand,
          minPrice: _minPrice,
          maxPrice: _maxPrice,
          page: 1,
          perPage: 20,
        ),
      ]);

      if (!mounted) return;
      final page = results[1] as CatalogPage;
      setState(() {
        _filters = results[0] as CatalogFilters;
        _products = page.products;
        _page = page.currentPage;
        _lastPage = page.lastPage;
        _total = page.total;
        _loading = false;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _error = error;
        _loading = false;
      });
    }
  }

  Future<void> _loadProducts({required bool reset}) async {
    if (reset) {
      if (mounted) {
        setState(() {
          _loading = true;
          _error = null;
          _page = 1;
        });
      }
    } else {
      if (_loadingMore || _page >= _lastPage) return;
      if (mounted) setState(() => _loadingMore = true);
    }

    try {
      final targetPage = reset ? 1 : _page + 1;
      final page = await _repository.getCatalogPage(
        categorySlug: widget.categorySlug,
        sort: _sort,
        stock: _stock,
        brand: _brand,
        minPrice: _minPrice,
        maxPrice: _maxPrice,
        page: targetPage,
        perPage: 20,
      );

      if (!mounted) return;
      setState(() {
        _products = reset ? page.products : <ProductModel>[..._products, ...page.products];
        _page = page.currentPage;
        _lastPage = page.lastPage;
        _total = page.total;
        _loading = false;
        _loadingMore = false;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _error = error;
        _loading = false;
        _loadingMore = false;
      });
    }
  }

  void _openSearch() {
    Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => const SearchScreen()));
  }

  void _back() {
    if (widget.embedded && widget.onBackToCategory != null) {
      widget.onBackToCategory!();
      return;
    }
    Navigator.of(context).maybePop();
  }

  void _openCart() {
    MainNavigationController.select(OvanieMainTab.cart);
    Navigator.of(context).popUntil((route) => route.isFirst);
  }

  Future<void> _openSort() async {
    final apiSorts = _filters?.sorts ?? const <CatalogOption>[];
    final options = apiSorts.isNotEmpty
        ? apiSorts
        : const <CatalogOption>[
            CatalogOption(value: 'popular', label: 'Populaires'),
            CatalogOption(value: 'recent', label: 'Plus récents'),
            CatalogOption(value: 'price_asc', label: 'Prix croissant'),
            CatalogOption(value: 'price_desc', label: 'Prix décroissant'),
          ];

    final selected = await showCatalogSheet<String>(
      context,
      title: 'Trier les produits',
      builder: (context) => Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          for (var i = 0; i < options.length; i++) ...[
            if (i > 0) const Divider(height: 1, thickness: 1, color: catalogLine),
            CatalogRadioRow(
              label: options[i].label,
              selected: options[i].value == _sort,
              onTap: () => Navigator.of(context).pop(options[i].value),
            ),
          ],
        ],
      ),
    );

    if (selected == null || selected == _sort) return;
    setState(() => _sort = selected);
    await _loadProducts(reset: true);
  }

  CatalogFilterSelection get _selection =>
      CatalogFilterSelection(stock: _stock, brand: _brand, minPrice: _minPrice, maxPrice: _maxPrice);

  Future<void> _applyFilters(CatalogFilterSelection next) async {
    setState(() {
      _stock = next.stock;
      _brand = next.brand;
      _minPrice = next.minPrice;
      _maxPrice = next.maxPrice;
    });
    await _loadProducts(reset: true);
  }

  Future<void> _openFilters() async {
    final next = await showCatalogFilterSheet(context, filters: _filters, current: _selection);
    if (next == null || !mounted) return;
    await _applyFilters(next);
  }

  @override
  Widget build(BuildContext context) {
    final content = OvanieAuthTheme(
      child: Material(
        color: Colors.white,
        child: SafeArea(
          bottom: false,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              _ProductListHeader(title: _title, onBack: _back, onOpenCart: _openCart),
              Padding(
                padding: const EdgeInsets.fromLTRB(12, 2, 12, 8),
                child: CatalogSearchField(onTap: _openSearch, filled: true),
              ),
              Padding(
                padding: const EdgeInsets.fromLTRB(13, 0, 12, 8),
                child: Row(
                  children: [
                    Expanded(
                      child: Text(
                        _loading && _products.isEmpty ? '' : (_total == 1 ? '1 produit' : '$_total produits'),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(color: catalogMuted, fontSize: 13, fontWeight: FontWeight.w500),
                      ),
                    ),
                    CatalogToolbarButton(icon: Icons.swap_vert_rounded, label: 'Trier', onTap: _openSort),
                    const SizedBox(width: 6),
                    CatalogToolbarButton(
                      icon: Icons.tune_rounded,
                      label: 'Filtres',
                      badge: _selection.count,
                      onTap: _openFilters,
                    ),
                  ],
                ),
              ),
              Expanded(child: _buildScrollableContent()),
            ],
          ),
        ),
      ),
    );

    return PopScope(
      canPop: !(widget.embedded && widget.onBackToCategory != null),
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop) widget.onBackToCategory?.call();
      },
      child: widget.embedded ? content : Scaffold(backgroundColor: Colors.white, body: content),
    );
  }

  Widget _buildScrollableContent() {
    return RefreshIndicator(
      color: authOrange,
      onRefresh: () => _loadProducts(reset: true),
      child: CustomScrollView(
        controller: _scrollController,
        physics: const AlwaysScrollableScrollPhysics(parent: BouncingScrollPhysics()),
        slivers: [
          if (_loading && _products.isEmpty)
            const SliverFillRemaining(
              hasScrollBody: false,
              child: Center(child: CircularProgressIndicator(color: authOrange)),
            )
          else if (_error != null && _products.isEmpty)
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(14, 22, 14, 0),
                child: ApiErrorCard(message: ApiClient.friendlyError(_error!), onRetry: _loadInitial),
              ),
            )
          else if (_products.isEmpty)
            SliverFillRemaining(
              hasScrollBody: false,
              child: Center(
                child: _selection.isEmpty
                    ? const CatalogEmptyState(
                        icon: Icons.inventory_2_rounded,
                        message: 'Aucun produit disponible pour le moment.',
                      )
                    : CatalogNoResult(
                        message: 'Aucun produit dans « $_title ».',
                        onClear: () => _applyFilters(const CatalogFilterSelection()),
                      ),
              ),
            )
          else
            SliverPadding(
              padding: const EdgeInsets.fromLTRB(9, 0, 9, 14),
              sliver: SliverLayoutBuilder(
                builder: (context, constraints) {
                  final width = constraints.crossAxisExtent;
                  final columns = width >= 900 ? 4 : (width >= 600 ? 3 : 2);
                  const gap = 6.0;
                  final tileWidth = (width - (gap * (columns - 1))) / columns;

                  return SliverGrid(
                    delegate: SliverChildBuilderDelegate(
                      (context, index) => _SubcategoryProductCard(product: _products[index]),
                      childCount: _products.length,
                    ),
                    gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
                      crossAxisCount: columns,
                      crossAxisSpacing: gap,
                      mainAxisSpacing: 6,
                      mainAxisExtent: tileWidth * .68 + _SubcategoryProductCard.infoHeight,
                    ),
                  );
                },
              ),
            ),
          if (_loadingMore)
            const SliverToBoxAdapter(
              child: Padding(
                padding: EdgeInsets.only(bottom: 22),
                child: Center(child: CircularProgressIndicator(strokeWidth: 2, color: authOrange)),
              ),
            ),
        ],
      ),
    );
  }
}

/// En-tête : retour, nom de la sous-catégorie centré, panier avec pastille.
class _ProductListHeader extends StatelessWidget {
  const _ProductListHeader({required this.title, required this.onBack, required this.onOpenCart});

  final String title;
  final VoidCallback onBack;
  final VoidCallback onOpenCart;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 48,
      child: Row(
        children: [
          IconButton(
            onPressed: onBack,
            tooltip: 'Retour',
            icon: const Icon(Icons.arrow_back_ios_new_rounded, size: 20, color: authNavy),
          ),
          Expanded(
            child: Text(
              title,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              textAlign: TextAlign.center,
              style: const TextStyle(color: authNavy, fontSize: 16, fontWeight: FontWeight.w800),
            ),
          ),
          AnimatedBuilder(
            animation: CartStore.instance,
            builder: (context, _) {
              final count = CartStore.instance.itemsCount;
              return IconButton(
                onPressed: onOpenCart,
                tooltip: count > 0 ? 'Panier, $count article${count > 1 ? 's' : ''}' : 'Panier',
                icon: Stack(
                  clipBehavior: Clip.none,
                  children: [
                    const Icon(Icons.shopping_cart_outlined, size: 24, color: authNavy),
                    if (count > 0)
                      Positioned(
                        right: -7,
                        top: -7,
                        child: Container(
                          constraints: const BoxConstraints(minWidth: 16, minHeight: 16),
                          padding: const EdgeInsets.symmetric(horizontal: 3),
                          alignment: Alignment.center,
                          decoration: BoxDecoration(color: authOrange, borderRadius: BorderRadius.circular(99)),
                          child: Text(
                            count > 99 ? '99+' : '$count',
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 9,
                              height: 1,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ),
                      ),
                  ],
                ),
              );
            },
          ),
        ],
      ),
    );
  }
}

class _SubcategoryProductCard extends StatelessWidget {
  final ProductModel product;

  const _SubcategoryProductCard({required this.product});

  /// Hauteur de la partie texte, sous la photo.
  static const double infoHeight = 128;

  void _openDetails(BuildContext context) {
    Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => ProductDetailScreen(product: product)));
  }

  void _addToCart(BuildContext context) {
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

  void _toggleFavorite(BuildContext context) {
    if (!SessionStore.instance.isAuthenticated) {
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(const SnackBar(content: Text('Connectez-vous pour ajouter ce produit à vos favoris.')));
      return;
    }
    FavoritesStore.instance.toggle(product);
  }

  /// Conditionnement affiché dans la pastille grise (« Sac de 50 kg »).
  String get _packaging {
    String text = product.packaging.trim();
    if (text.isEmpty) {
      final unit = product.unitLabel.trim();
      text = product.weightKg > 0 && unit.toLowerCase().contains('sac')
          ? 'Sac de ${ProductModel.compactNumber(product.weightKg)} kg'
          : product.homeSubtitle;
    }
    return text.isEmpty ? text : text[0].toUpperCase() + text.substring(1);
  }

  /// Libellé et couleur de la disponibilité, d'après le stock réel.
  (String, Color) get _stock {
    final status = product.availabilityStatus.toLowerCase();
    if (status == 'on_order' || status == 'preorder') {
      return ('Sur commande', const Color(0xFF0B5ED7));
    }
    if (product.stock <= 0 || status == 'out_of_stock') {
      return ('Rupture de stock', authDanger);
    }
    if (product.stock <= 5) return ('Stock limité', const Color(0xFFC96F00));
    return ('En stock', const Color(0xFF15934A));
  }

  @override
  Widget build(BuildContext context) {
    final canBuy = product.canAddToCart && product.stock > 0;
    final unit = product.unitLabel.trim();
    final rating = product.rating ?? 0;
    final (stockLabel, stockColor) = _stock;
    final oldPrice = product.hasDiscount ? product.homeOriginalPrice : 0.0;

    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(9),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: () => _openDetails(context),
        child: Container(
          foregroundDecoration: BoxDecoration(
            borderRadius: BorderRadius.circular(9),
            border: Border.all(color: const Color(0xFFE4E8EF)),
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
                      child: _ProductRemoteImage(url: product.imageUrl),
                    ),
                    if (product.hasDiscount && product.discountPercent > 0)
                      Positioned(
                        left: 6,
                        top: 6,
                        child: Container(
                          padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),
                          decoration: BoxDecoration(color: authOrange, borderRadius: BorderRadius.circular(5)),
                          child: Text(
                            '-${product.discountPercent} %',
                            style: const TextStyle(color: Colors.white, fontSize: 10.5, fontWeight: FontWeight.w700),
                          ),
                        ),
                      ),
                    Positioned(
                      right: 2,
                      top: 2,
                      child: AnimatedBuilder(
                        animation: Listenable.merge([FavoritesStore.instance, SessionStore.instance]),
                        builder: (context, _) {
                          final favorite =
                              SessionStore.instance.isAuthenticated && FavoritesStore.instance.contains(product);
                          return IconButton(
                            onPressed: () => _toggleFavorite(context),
                            visualDensity: VisualDensity.compact,
                            tooltip: favorite ? 'Retirer des favoris' : 'Ajouter aux favoris',
                            icon: Icon(
                              favorite ? Icons.favorite_rounded : Icons.favorite_border_rounded,
                              size: 20,
                              color: favorite ? authOrange : const Color(0xFF3A4563),
                            ),
                          );
                        },
                      ),
                    ),
                  ],
                ),
              ),
              SizedBox(
                height: infoHeight,
                child: Stack(
                  children: [
                    Padding(
                      padding: const EdgeInsets.fromLTRB(8, 7, 8, 8),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            product.name,
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              color: authNavy,
                              fontSize: 12,
                              height: 1.22,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                          const SizedBox(height: 4),
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
                            decoration: BoxDecoration(
                              color: const Color(0xFFEEF1F5),
                              borderRadius: BorderRadius.circular(99),
                            ),
                            child: Text(
                              _packaging,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                color: Color(0xFF4A5674),
                                fontSize: 9.5,
                                fontWeight: FontWeight.w500,
                              ),
                            ),
                          ),
                          if (rating > 0 && product.reviewsCount > 0) ...[
                            const SizedBox(height: 4),
                            Row(
                              children: [
                                const Icon(Icons.star_rounded, size: 14, color: Color(0xFFFFB400)),
                                const SizedBox(width: 2),
                                Text(
                                  rating.toStringAsFixed(1).replaceAll('.', ','),
                                  style: const TextStyle(color: authNavy, fontSize: 10.5, fontWeight: FontWeight.w600),
                                ),
                                Text(
                                  ' (${product.reviewsCount})',
                                  style: const TextStyle(color: catalogMuted, fontSize: 10.5),
                                ),
                              ],
                            ),
                          ],
                          const Spacer(),
                          Padding(
                            padding: const EdgeInsets.only(right: 38),
                            child: FittedBox(
                              fit: BoxFit.scaleDown,
                              alignment: Alignment.centerLeft,
                              child: Text.rich(
                                TextSpan(
                                  text: formatFcfa(product.homeDisplayPrice),
                                  children: [
                                    if (unit.isNotEmpty)
                                      TextSpan(
                                        text: ' / $unit',
                                        style: const TextStyle(
                                          color: catalogMuted,
                                          fontSize: 11,
                                          fontWeight: FontWeight.w500,
                                        ),
                                      ),
                                  ],
                                ),
                                maxLines: 1,
                                style: const TextStyle(color: authNavy, fontSize: 13.5, fontWeight: FontWeight.w800),
                              ),
                            ),
                          ),
                          SizedBox(
                            height: 14,
                            child: oldPrice > 0
                                ? Text(
                                    formatFcfa(oldPrice),
                                    maxLines: 1,
                                    style: const TextStyle(
                                      color: Color(0xFFE5501B),
                                      fontSize: 10,
                                      height: 1.3,
                                      decoration: TextDecoration.lineThrough,
                                      decorationColor: Color(0xFFE5501B),
                                    ),
                                  )
                                : null,
                          ),
                          const SizedBox(height: 2),
                          Row(
                            children: [
                              Container(
                                width: 7,
                                height: 7,
                                decoration: BoxDecoration(color: stockColor, shape: BoxShape.circle),
                              ),
                              const SizedBox(width: 6),
                              Flexible(
                                child: Text(
                                  stockLabel,
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: TextStyle(color: stockColor, fontSize: 10, fontWeight: FontWeight.w600),
                                ),
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),
                    Positioned(
                      right: 7,
                      bottom: 10,
                      child: Material(
                        color: canBuy ? authOrange : const Color(0xFFCBD2DC),
                        borderRadius: BorderRadius.circular(7),
                        child: InkWell(
                          onTap: canBuy ? () => _addToCart(context) : null,
                          borderRadius: BorderRadius.circular(7),
                          child: SizedBox(
                            width: 32,
                            height: 32,
                            child: Icon(
                              Icons.shopping_cart_outlined,
                              color: Colors.white,
                              size: 18,
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

class _ProductRemoteImage extends StatelessWidget {
  final String url;

  const _ProductRemoteImage({required this.url});

  @override
  Widget build(BuildContext context) {
    const placeholder = Center(child: Icon(Icons.inventory_2_outlined, size: 38, color: Color(0xFFB5BECA)));
    if (url.trim().isEmpty) return placeholder;

    return Padding(
      padding: const EdgeInsets.all(6),
      child: Image.network(
        url,
        fit: BoxFit.contain,
        excludeFromSemantics: true,
        errorBuilder: (_, __, ___) => placeholder,
        loadingBuilder: (context, child, progress) {
          if (progress == null) return child;
          return const Center(
            child: SizedBox(
              width: 18,
              height: 18,
              child: CircularProgressIndicator(strokeWidth: 1.5, color: Color(0xFFD6DCE5)),
            ),
          );
        },
      ),
    );
  }
}
