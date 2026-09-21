import 'package:flutter/material.dart';

import '../../../app/theme.dart';
import '../../../core/network/api_client.dart';
import '../../../core/utils/formatters.dart';
import '../../../shared/widgets/api_error_card.dart';
import '../../auth/domain/session_store.dart';
import '../../cart/domain/cart_store.dart';
import '../../favorites/domain/favorites_store.dart';
import '../../home/data/marketplace_repository.dart';
import '../../products/domain/product_model.dart';
import '../../products/presentation/product_detail_screen.dart';
import '../../search/presentation/search_screen.dart';

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
    if (oldWidget.categorySlug != widget.categorySlug ||
        oldWidget.categoryId != widget.categoryId) {
      _sort = 'popular';
      _stock = null;
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
    Navigator.of(context).push(
      MaterialPageRoute<void>(builder: (_) => const SearchScreen()),
    );
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

    final selected = await showModalBottomSheet<String>(
      context: context,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(22)),
      ),
      builder: (context) {
        return SafeArea(
          top: false,
          child: Padding(
            padding: const EdgeInsets.fromLTRB(18, 18, 18, 16),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Trier les produits',
                  style: TextStyle(
                    color: OvanieColors.navy,
                    fontSize: 19,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 10),
                ...options.map(
                  (option) => RadioListTile<String>(
                    value: option.value,
                    groupValue: _sort,
                    activeColor: OvanieColors.orange,
                    contentPadding: EdgeInsets.zero,
                    title: Text(
                      option.label,
                      style: const TextStyle(
                        color: OvanieColors.navy,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    onChanged: (value) => Navigator.of(context).pop(value),
                  ),
                ),
              ],
            ),
          ),
        );
      },
    );

    if (selected == null || selected == _sort) return;
    setState(() => _sort = selected);
    await _loadProducts(reset: true);
  }

  Future<void> _openFilters() async {
    final availability = _filters?.availability ?? const <CatalogOption>[];
    if (availability.isEmpty) {
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(
          const SnackBar(content: Text('Aucun filtre de disponibilité configuré.')),
        );
      return;
    }

    final selected = await showModalBottomSheet<String>(
      context: context,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(22)),
      ),
      builder: (context) {
        return SafeArea(
          top: false,
          child: Padding(
            padding: const EdgeInsets.fromLTRB(18, 18, 18, 18),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Filtres',
                  style: TextStyle(
                    color: OvanieColors.navy,
                    fontSize: 19,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 6),
                const Text(
                  'Disponibilité',
                  style: TextStyle(
                    color: OvanieColors.muted,
                    fontSize: 13,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 10),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    ChoiceChip(
                      label: const Text('Tous'),
                      selected: _stock == null,
                      selectedColor: const Color(0xFFFFEEE2),
                      side: BorderSide(
                        color: _stock == null
                            ? OvanieColors.orange
                            : const Color(0xFFDDE3EA),
                      ),
                      onSelected: (_) => Navigator.of(context).pop('__all__'),
                    ),
                    ...availability.map(
                      (option) => ChoiceChip(
                        label: Text(option.label),
                        selected: _stock == option.value,
                        selectedColor: const Color(0xFFFFEEE2),
                        side: BorderSide(
                          color: _stock == option.value
                              ? OvanieColors.orange
                              : const Color(0xFFDDE3EA),
                        ),
                        onSelected: (_) => Navigator.of(context).pop(option.value),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        );
      },
    );

    if (selected == null) return;
    final nextStock = selected == '__all__' ? null : selected;
    if (nextStock == _stock) return;
    setState(() => _stock = nextStock);
    await _loadProducts(reset: true);
  }

  @override
  Widget build(BuildContext context) {
    final content = ColoredBox(
      color: Colors.white,
      child: SafeArea(
        bottom: false,
        child: Column(
          children: [
            _SubcategorySearchBar(onTap: _openSearch),
            Expanded(child: _buildScrollableContent()),
          ],
        ),
      ),
    );

    return WillPopScope(
      onWillPop: () async {
        if (widget.embedded && widget.onBackToCategory != null) {
          widget.onBackToCategory!();
          return false;
        }
        return true;
      },
      child: widget.embedded
          ? content
          : Scaffold(
              backgroundColor: Colors.white,
              body: content,
            ),
    );
  }

  Widget _buildScrollableContent() {
    return RefreshIndicator(
      color: OvanieColors.orange,
      onRefresh: () => _loadProducts(reset: true),
      child: CustomScrollView(
        controller: _scrollController,
        physics: const AlwaysScrollableScrollPhysics(
          parent: BouncingScrollPhysics(),
        ),
        slivers: [
          SliverToBoxAdapter(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(18, 8, 18, 0),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    _title,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: OvanieColors.navy,
                      fontSize: 28,
                      height: 1.02,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 7),
                  Text(
                    '$_total produits',
                    style: const TextStyle(
                      color: Color(0xFF697791),
                      fontSize: 14,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                  const SizedBox(height: 17),
                  Row(
                    children: [
                      Expanded(
                        child: _LargeOutlineAction(
                          icon: Icons.tune_rounded,
                          label: 'Filtres',
                          active: _stock != null,
                          onTap: _openFilters,
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: _LargeOutlineAction(
                          icon: Icons.swap_vert_rounded,
                          label: 'Trier',
                          onTap: _openSort,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 14),
                ],
              ),
            ),
          ),
          if (_loading && _products.isEmpty)
            const SliverFillRemaining(
              hasScrollBody: false,
              child: Center(
                child: CircularProgressIndicator(color: OvanieColors.orange),
              ),
            )
          else if (_error != null && _products.isEmpty)
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(18, 22, 18, 0),
                child: ApiErrorCard(
                  message: ApiClient.friendlyError(_error!),
                  onRetry: _loadInitial,
                ),
              ),
            )
          else if (_products.isEmpty)
            const SliverFillRemaining(
              hasScrollBody: false,
              child: Center(
                child: Padding(
                  padding: EdgeInsets.all(24),
                  child: Text(
                    'Aucun produit disponible pour le moment.',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      color: OvanieColors.muted,
                      fontSize: 14,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
              ),
            )
          else
            SliverPadding(
              padding: const EdgeInsets.fromLTRB(18, 0, 18, 20),
              sliver: SliverLayoutBuilder(
                builder: (context, constraints) {
                  final width = constraints.crossAxisExtent;
                  final columns = width >= 720 ? 3 : 2;
                  final gap = 10.0;
                  final tileWidth = (width - (gap * (columns - 1))) / columns;
                  final tileHeight = (tileWidth * 1.62).clamp(250.0, 330.0).toDouble();

                  return SliverGrid(
                    delegate: SliverChildBuilderDelegate(
                      (context, index) => _SubcategoryProductCard(
                        product: _products[index],
                      ),
                      childCount: _products.length,
                    ),
                    gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
                      crossAxisCount: columns,
                      crossAxisSpacing: gap,
                      mainAxisSpacing: 10,
                      mainAxisExtent: tileHeight,
                    ),
                  );
                },
              ),
            ),
          if (_loadingMore)
            const SliverToBoxAdapter(
              child: Padding(
                padding: EdgeInsets.only(bottom: 22),
                child: Center(
                  child: CircularProgressIndicator(
                    strokeWidth: 2,
                    color: OvanieColors.orange,
                  ),
                ),
              ),
            )
          else
            const SliverToBoxAdapter(child: SizedBox(height: 14)),
        ],
      ),
    );
  }
}

class _SubcategorySearchBar extends StatelessWidget {
  final VoidCallback onTap;

  const _SubcategorySearchBar({required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
      child: Material(
        color: Colors.white,
        borderRadius: BorderRadius.circular(15),
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(15),
          child: Container(
            height: 54,
            padding: const EdgeInsets.symmetric(horizontal: 15),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(15),
              border: Border.all(color: const Color(0xFFDDE3EB)),
            ),
            child: const Row(
              children: [
                Icon(
                  Icons.search_rounded,
                  size: 27,
                  color: Color(0xFF52627D),
                ),
                SizedBox(width: 12),
                Expanded(
                  child: Text(
                    'Rechercher un produit, une marque...',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: Color(0xFF77839A),
                      fontSize: 14,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                ),
                SizedBox(width: 8),
                Icon(
                  Icons.center_focus_weak_rounded,
                  size: 28,
                  color: OvanieColors.navy,
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _LargeOutlineAction extends StatelessWidget {
  final IconData icon;
  final String label;
  final bool active;
  final VoidCallback onTap;

  const _LargeOutlineAction({
    required this.icon,
    required this.label,
    this.active = false,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(12),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(12),
        child: Container(
          height: 56,
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(12),
            border: Border.all(
              color: active ? OvanieColors.orange : const Color(0xFFDDE3EA),
            ),
          ),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(
                icon,
                size: 23,
                color: active ? OvanieColors.orange : OvanieColors.navy,
              ),
              const SizedBox(width: 10),
              Text(
                label,
                style: TextStyle(
                  color: active ? OvanieColors.orange : OvanieColors.navy,
                  fontSize: 16,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _SubcategoryProductCard extends StatelessWidget {
  final ProductModel product;

  const _SubcategoryProductCard({required this.product});

  void _openDetails(BuildContext context) {
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => ProductDetailScreen(product: product),
      ),
    );
  }

  void _addToCart(BuildContext context) {
    final result = CartStore.instance.add(product);
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          content: Text(result.message),
          duration: const Duration(milliseconds: 1200),
          backgroundColor:
              result.success ? OvanieColors.navy : OvanieColors.danger,
        ),
      );
  }

  String get _subtitle {
    final packaging = product.packaging.trim();
    if (packaging.isNotEmpty) return packaging;

    final unit = product.unitLabel.trim();
    if (product.weightKg > 0 && unit.toLowerCase().contains('sac')) {
      return 'Sac de ${ProductModel.compactNumber(product.weightKg)} kg';
    }
    return product.homeSubtitle;
  }

  @override
  Widget build(BuildContext context) {
    final canBuy = product.canAddToCart && product.stock > 0;

    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(12),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: () => _openDetails(context),
        child: Container(
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: const Color(0xFFE1E6ED)),
          ),
          child: LayoutBuilder(
            builder: (context, constraints) {
              final imageHeight =
                  (constraints.maxWidth * .88).clamp(118.0, 158.0).toDouble();

              return Stack(
                children: [
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      SizedBox(
                        width: double.infinity,
                        height: imageHeight,
                        child: Padding(
                          padding: const EdgeInsets.fromLTRB(10, 10, 10, 2),
                          child: _ProductRemoteImage(url: product.imageUrl),
                        ),
                      ),
                      Expanded(
                        child: Padding(
                          padding: const EdgeInsets.fromLTRB(10, 6, 10, 10),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                product.name,
                                maxLines: 2,
                                overflow: TextOverflow.ellipsis,
                                style: const TextStyle(
                                  color: OvanieColors.navy,
                                  fontSize: 13.4,
                                  height: 1.12,
                                  fontWeight: FontWeight.w800,
                                ),
                              ),
                              const SizedBox(height: 5),
                              Text(
                                _subtitle,
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: const TextStyle(
                                  color: Color(0xFF59677D),
                                  fontSize: 11.1,
                                  fontWeight: FontWeight.w500,
                                ),
                              ),
                              const Spacer(),
                              Padding(
                                padding: const EdgeInsets.only(right: 48),
                                child: Text(
                                  formatFcfa(product.homeDisplayPrice),
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: const TextStyle(
                                    color: OvanieColors.orange,
                                    fontSize: 15.5,
                                    height: 1,
                                    fontWeight: FontWeight.w900,
                                  ),
                                ),
                              ),
                              if (product.hasDiscount &&
                                  product.homeOriginalPrice > 0) ...[
                                const SizedBox(height: 4),
                                Padding(
                                  padding: const EdgeInsets.only(right: 48),
                                  child: Text(
                                    formatFcfa(product.homeOriginalPrice),
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                    style: const TextStyle(
                                      color: Color(0xFF7E8797),
                                      fontSize: 11,
                                      decoration: TextDecoration.lineThrough,
                                      decorationColor: Color(0xFF7E8797),
                                    ),
                                  ),
                                ),
                              ] else
                                const SizedBox(height: 15),
                            ],
                          ),
                        ),
                      ),
                    ],
                  ),
                  if (product.hasDiscount && product.discountPercent > 0)
                    Positioned(
                      left: 9,
                      top: 9,
                      child: Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 8,
                          vertical: 5,
                        ),
                        decoration: BoxDecoration(
                          color: const Color(0xFFE80B18),
                          borderRadius: BorderRadius.circular(8),
                          boxShadow: const [
                            BoxShadow(
                              color: Color(0x22000000),
                              blurRadius: 5,
                              offset: Offset(0, 2),
                            ),
                          ],
                        ),
                        child: Text(
                          '-${product.discountPercent}%',
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 11,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                      ),
                    ),
                  Positioned(
                    right: 10,
                    top: 10,
                    child: AnimatedBuilder(
                      animation: Listenable.merge([
                        FavoritesStore.instance,
                        SessionStore.instance,
                      ]),
                      builder: (context, _) {
                        final favorite = SessionStore.instance.isAuthenticated &&
                            FavoritesStore.instance.contains(product);
                        return InkResponse(
                          onTap: () {
                            if (!SessionStore.instance.isAuthenticated) {
                              ScaffoldMessenger.of(context)
                                ..hideCurrentSnackBar()
                                ..showSnackBar(
                                  const SnackBar(
                                    content: Text(
                                      'Connectez-vous pour ajouter ce produit à vos favoris.',
                                    ),
                                  ),
                                );
                              return;
                            }
                            FavoritesStore.instance.toggle(product);
                          },
                          radius: 22,
                          child: Icon(
                            favorite
                                ? Icons.favorite_rounded
                                : Icons.favorite_border_rounded,
                            size: 24,
                            color: favorite
                                ? OvanieColors.orange
                                : const Color(0xFF2E405F),
                          ),
                        );
                      },
                    ),
                  ),
                  Positioned(
                    right: 10,
                    bottom: 10,
                    child: Material(
                      color: canBuy
                          ? OvanieColors.orange
                          : const Color(0xFFCBD2DC),
                      borderRadius: BorderRadius.circular(10),
                      child: InkWell(
                        onTap: canBuy ? () => _addToCart(context) : null,
                        borderRadius: BorderRadius.circular(10),
                        child: const SizedBox(
                          width: 44,
                          height: 44,
                          child: Icon(
                            Icons.shopping_cart_outlined,
                            color: Colors.white,
                            size: 24,
                          ),
                        ),
                      ),
                    ),
                  ),
                ],
              );
            },
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
    if (url.trim().isEmpty) {
      return const Center(
        child: Icon(
          Icons.inventory_2_outlined,
          size: 44,
          color: Color(0xFFB5BECA),
        ),
      );
    }

    return Image.network(
      url,
      fit: BoxFit.contain,
      errorBuilder: (_, __, ___) => const Center(
        child: Icon(
          Icons.inventory_2_outlined,
          size: 44,
          color: Color(0xFFB5BECA),
        ),
      ),
      loadingBuilder: (context, child, progress) {
        if (progress == null) return child;
        return const Center(
          child: SizedBox(
            width: 18,
            height: 18,
            child: CircularProgressIndicator(
              strokeWidth: 1.5,
              color: Color(0xFFD6DCE5),
            ),
          ),
        );
      },
    );
  }
}
