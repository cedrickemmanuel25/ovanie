import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../core/config/app_config.dart';
import '../../../core/network/api_client.dart';
import '../../../core/utils/formatters.dart';
import '../../../shared/widgets/api_error_card.dart';
import '../../../shared/widgets/ovanie_bottom_navigation.dart';
import '../../auth/domain/session_store.dart';
import '../../auth/presentation/auth_branding.dart';
import '../../cart/domain/cart_store.dart';
import '../../categories/domain/category_model.dart';
import '../../categories/presentation/catalog_widgets.dart';
import '../../favorites/domain/favorites_store.dart';
import '../../home/data/marketplace_repository.dart';
import '../../products/domain/product_model.dart';
import '../../products/presentation/product_detail_screen.dart';
import '../../search/presentation/search_screen.dart';
import '../../shell/domain/main_navigation_store.dart';
import '../../shell/presentation/main_navigation_controller.dart';
import '../domain/catalog_navigation_store.dart';

/// Catalogue produits OVANIE et résultats de recherche (même écran).
///
/// - sans recherche : « Catalogue produits », avec la bannière ;
/// - avec [initialQuery] : « Résultats pour « … » », sans bannière ;
/// - les pastilles du haut choisissent une catégorie principale, la pastille
///   « Catégorie » une de ses sous-catégories.
///
/// Source de vérité : Web OVANIE <-> API Laravel <-> Application Flutter.
class CatalogScreen extends StatefulWidget {
  final int? categoryId;
  final String? categorySlug;
  final String? categoryName;
  final String initialQuery;
  final String? initialOffer;
  final bool showBackButton;
  final CatalogNavigationStore? navigationStore;

  const CatalogScreen({
    super.key,
    this.categoryId,
    this.categorySlug,
    this.categoryName,
    this.initialQuery = '',
    this.initialOffer,
    this.showBackButton = false,
    this.navigationStore,
  });

  @override
  State<CatalogScreen> createState() => _CatalogScreenState();
}

class _CatalogScreenState extends State<CatalogScreen> {
  final MarketplaceRepository _repository = const MarketplaceRepository();
  final ScrollController _contentScrollController = ScrollController();

  List<CategoryModel> _roots = const [];
  List<ProductModel> _products = const [];
  CatalogFilters? _filters;
  CategoryModel? _selectedRoot;
  CategoryModel? _selectedSubcategory;

  late String _query;
  late String? _offer;
  String _sort = 'popular';
  String? _brand;
  String? _stock;
  double? _minPrice;
  double? _maxPrice;

  int _page = 1;
  int _lastPage = 1;
  int _total = 0;
  bool _loading = true;
  bool _loadingMore = false;
  Object? _error;
  int _lastNavigationSerial = 0;
  String? _externalCategoryName;

  @override
  void initState() {
    super.initState();
    _query = widget.initialQuery.trim();
    _offer = widget.initialOffer;
    _contentScrollController.addListener(_onScroll);
    widget.navigationStore?.addListener(_onExternalNavigation);
    _loadInitial();
  }

  @override
  void dispose() {
    widget.navigationStore?.removeListener(_onExternalNavigation);
    _contentScrollController
      ..removeListener(_onScroll)
      ..dispose();
    super.dispose();
  }

  String? get _activeCategorySlug => _selectedSubcategory?.slug ?? _selectedRoot?.slug;

  bool get _hasActiveFilters =>
      _brand != null || _stock != null || _minPrice != null || _maxPrice != null || _offer != null;

  CatalogFilterSelection get _selection =>
      CatalogFilterSelection(stock: _stock, brand: _brand, minPrice: _minPrice, maxPrice: _maxPrice);

  /// Libellé en gras à gauche du nombre de produits.
  String get _scopeLabel =>
      _selectedSubcategory?.name ??
      _selectedRoot?.name ??
      _externalCategoryName ??
      widget.categoryName ??
      'Tous les produits';

  Future<void> _loadInitial() async {
    if (mounted) {
      setState(() {
        _loading = true;
        _error = null;
      });
    }

    try {
      final results = await Future.wait<dynamic>([
        _repository.getRootCategories(limit: 50),
        _repository.getCatalogFilters(),
      ]);

      final roots = categoriesInDisplayOrder(results[0] as List<CategoryModel>);
      final filters = results[1] as CatalogFilters;

      CategoryModel? selectedRoot;
      CategoryModel? selectedSubcategory;
      if (widget.categoryId != null || (widget.categorySlug?.trim().isNotEmpty ?? false)) {
        final target = _repository.findCategoryInRoots(roots, id: widget.categoryId, slug: widget.categorySlug);
        if (target != null) {
          if (target.parentId == null) {
            selectedRoot = target;
          } else {
            selectedSubcategory = target;
            selectedRoot = _repository.findRootForCategory(roots, target);
          }
        }
      }

      if (!mounted) return;
      setState(() {
        _roots = roots;
        _filters = filters;
        _selectedRoot = selectedRoot;
        _selectedSubcategory = selectedSubcategory;
      });

      final pending = widget.navigationStore?.request;
      if (pending != null && pending.serial > _lastNavigationSerial) {
        await _applyNavigationRequest(pending);
      } else {
        await _loadProducts(reset: true);
      }
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _error = error;
        _loading = false;
      });
    }
  }

  void _onExternalNavigation() {
    final request = widget.navigationStore?.request;
    if (request == null || request.serial <= _lastNavigationSerial) return;
    if (_roots.isEmpty) return;
    unawaited(_applyNavigationRequest(request));
  }

  Future<void> _applyNavigationRequest(CatalogNavigationRequest request) async {
    if (!mounted || request.serial <= _lastNavigationSerial || _roots.isEmpty) {
      return;
    }

    CategoryModel? selectedRoot;
    CategoryModel? selectedSubcategory;
    final slug = request.categorySlug?.trim();

    if (slug != null && slug.isNotEmpty) {
      final target = _repository.findCategoryInRoots(_roots, slug: slug);
      if (target != null) {
        if (target.parentId == null) {
          selectedRoot = target;
        } else {
          selectedSubcategory = target;
          selectedRoot = _repository.findRootForCategory(_roots, target);
        }
      }
    }

    _lastNavigationSerial = request.serial;
    setState(() {
      _selectedRoot = selectedRoot;
      _selectedSubcategory = selectedSubcategory;
      _externalCategoryName = request.categoryName;
      _query = request.query.trim();
      _offer = request.offer;
      _sort = 'popular';
      _brand = null;
      _stock = null;
      _minPrice = null;
      _maxPrice = null;
    });
    _jumpTop();
    await _loadProducts(reset: true);
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
      setState(() => _loadingMore = true);
    }

    try {
      final targetPage = reset ? 1 : _page + 1;
      final page = await _repository.getCatalogPage(
        categorySlug: _activeCategorySlug,
        query: _query,
        brand: _brand,
        stock: _stock,
        offer: _offer,
        minPrice: _minPrice,
        maxPrice: _maxPrice,
        sort: _sort,
        page: targetPage,
        perPage: 20,
      );

      if (!mounted) return;
      setState(() {
        _page = page.currentPage;
        _lastPage = page.lastPage;
        _total = page.total;
        _products = reset ? page.products : [..._products, ...page.products];
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

  void _onScroll() {
    if (!_contentScrollController.hasClients) return;
    final position = _contentScrollController.position;
    if (position.pixels >= position.maxScrollExtent - 420) {
      unawaited(_loadProducts(reset: false));
    }
  }

  void _jumpTop() {
    if (_contentScrollController.hasClients) {
      _contentScrollController.jumpTo(0);
    }
  }

  Future<void> _selectRoot(CategoryModel? category) async {
    if (_selectedRoot?.slug == category?.slug && _selectedSubcategory == null) {
      return;
    }
    setState(() {
      _selectedRoot = category;
      _selectedSubcategory = null;
      _externalCategoryName = null;
      _offer = null;
      _sort = 'popular';
      _brand = null;
      _stock = null;
      _minPrice = null;
      _maxPrice = null;
    });
    _jumpTop();
    await _loadProducts(reset: true);
  }

  Future<void> _selectSubcategory(CategoryModel? category) async {
    if (_selectedSubcategory?.slug == category?.slug) return;
    setState(() {
      _selectedSubcategory = category;
      _externalCategoryName = null;
      _offer = null;
    });
    _jumpTop();
    await _loadProducts(reset: true);
  }

  /// La recherche du catalogue renvoie le texte saisi : les résultats
  /// s'affichent ici, avec le tri, les filtres et les catégories.
  Future<void> _openSearch() async {
    final query = await Navigator.of(
      context,
    ).push<String>(MaterialPageRoute<String>(builder: (_) => SearchScreen(initialQuery: _query, returnQuery: true)));
    if (query == null || !mounted || query == _query) return;
    setState(() => _query = query);
    _jumpTop();
    await _loadProducts(reset: true);
  }

  void _openCart() {
    MainNavigationController.select(OvanieMainTab.cart);
    Navigator.of(context).popUntil((route) => route.isFirst);
  }

  Future<void> _chooseSort() async {
    final apiSorts = _filters?.sorts ?? const <CatalogOption>[];
    final options = apiSorts.isNotEmpty
        ? apiSorts
        : const <CatalogOption>[
            CatalogOption(value: 'popular', label: 'Populaires'),
            CatalogOption(value: 'recent', label: 'Plus récents'),
            CatalogOption(value: 'price_asc', label: 'Prix croissant'),
            CatalogOption(value: 'price_desc', label: 'Prix décroissant'),
          ];

    final selected = await showCatalogChoiceSheet<String>(
      context,
      title: 'Trier les produits',
      options: [for (final option in options) (option.value, option.label)],
      selected: _sort,
    );

    if (selected == null || selected == _sort || !mounted) return;
    setState(() => _sort = selected);
    _jumpTop();
    await _loadProducts(reset: true);
  }

  Future<void> _openFilters() async {
    final next = await showCatalogFilterSheet(context, filters: _filters, current: _selection);
    if (next == null || !mounted) return;
    setState(() {
      _brand = next.brand;
      _stock = next.stock;
      _minPrice = next.minPrice;
      _maxPrice = next.maxPrice;
    });
    _jumpTop();
    await _loadProducts(reset: true);
  }

  Future<void> _quickPopular() async {
    if (_sort == 'popular') return;
    setState(() => _sort = 'popular');
    await _loadProducts(reset: true);
  }

  Future<void> _quickPrice() async {
    setState(() {
      _sort = _sort == 'price_asc' ? 'price_desc' : 'price_asc';
    });
    await _loadProducts(reset: true);
  }

  Future<void> _quickAvailability() async {
    final availability = _filters?.availability ?? const <CatalogOption>[];
    final inStock = availability.isNotEmpty ? availability.first.value : 'in-stock';
    setState(() => _stock = _stock == null ? inStock : null);
    await _loadProducts(reset: true);
  }

  /// Pastille « Catégorie » : les sous-catégories de la catégorie ouverte,
  /// ou les catégories principales quand aucune n'est choisie.
  Future<void> _quickCategory() async {
    final root = _selectedRoot;
    if (root == null) {
      if (_roots.isEmpty) return;
      final slug = await showCatalogChoiceSheet<String>(
        context,
        title: 'Catégorie',
        options: [('', 'Toutes les catégories'), for (final item in _roots) (item.slug, item.name)],
        selected: '',
      );
      if (slug == null || slug.isEmpty || !mounted) return;
      await _selectRoot(_roots.firstWhere((item) => item.slug == slug));
      return;
    }

    final slug = await showCatalogChoiceSheet<String>(
      context,
      title: root.name,
      options: [('', 'Toute la catégorie'), for (final child in root.children) (child.slug, child.name)],
      selected: _selectedSubcategory?.slug ?? '',
    );
    if (slug == null || !mounted) return;
    await _selectSubcategory(slug.isEmpty ? null : root.children.firstWhere((child) => child.slug == slug));
  }

  /// Copie le lien de la page correspondante du site, à coller où l'on veut.
  Future<void> _shareLink() async {
    final origin = AppConfig.backendOrigin;
    final slug = _activeCategorySlug;
    final url = _query.isNotEmpty
        ? '$origin/search?search=${Uri.encodeQueryComponent(_query)}'
        : '$origin/catalog${slug == null ? '' : '/$slug'}';
    await Clipboard.setData(ClipboardData(text: url));
    if (!mounted) return;
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        const SnackBar(
          content: Text('Lien copié. Collez-le pour le partager.'),
          duration: Duration(milliseconds: 1800),
        ),
      );
  }

  Future<void> _clearFilters() async {
    setState(() {
      _brand = null;
      _stock = null;
      _minPrice = null;
      _maxPrice = null;
      _offer = null;
    });
    await _loadProducts(reset: true);
  }

  @override
  Widget build(BuildContext context) {
    return OvanieAuthTheme(
      child: Scaffold(
        backgroundColor: Colors.white,
        bottomNavigationBar: const OvanieBottomNavigation(selectedTab: OvanieMainTab.categories),
        body: SafeArea(
          bottom: false,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              _CatalogHeader(
                title: _screenTitle,
                canGoBack: Navigator.of(context).canPop(),
                onBack: () => Navigator.of(context).maybePop(),
                onShare: _shareLink,
                onOpenCart: _openCart,
              ),
              Padding(
                padding: const EdgeInsets.fromLTRB(13, 0, 13, 8),
                child: CatalogSearchField(onTap: _openSearch),
              ),
              Expanded(child: _buildContent()),
            ],
          ),
        ),
      ),
    );
  }

  String get _screenTitle => _query.isNotEmpty ? 'Résultats pour « $_query »' : 'Catalogue produits';

  Widget _buildContent() {
    return RefreshIndicator(
      onRefresh: () => _loadProducts(reset: true),
      color: authOrange,
      child: CustomScrollView(
        controller: _contentScrollController,
        physics: const AlwaysScrollableScrollPhysics(parent: BouncingScrollPhysics()),
        slivers: [
          SliverToBoxAdapter(child: _buildHeader()),
          if (_loading && _products.isEmpty)
            const SliverFillRemaining(
              hasScrollBody: false,
              child: Center(child: CircularProgressIndicator(color: authOrange)),
            )
          else if (_error != null && _products.isEmpty)
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.all(14),
                child: ApiErrorCard(
                  message: ApiClient.friendlyError(_error!),
                  onRetry: _roots.isEmpty ? _loadInitial : () => _loadProducts(reset: true),
                ),
              ),
            )
          else if (_products.isEmpty)
            SliverFillRemaining(
              hasScrollBody: false,
              child: Center(
                child: _hasActiveFilters || _query.isNotEmpty || _activeCategorySlug != null
                    ? CatalogNoResult(
                        message: _query.isNotEmpty
                            ? 'Aucun produit pour « $_query ».'
                            : (_activeCategorySlug == null
                                  ? 'Aucun produit ne correspond à ces filtres.'
                                  : 'Aucun produit dans « $_scopeLabel ».'),
                        onClear: _hasActiveFilters ? _clearFilters : null,
                      )
                    : const CatalogEmptyState(
                        icon: Icons.inventory_2_rounded,
                        message: 'Aucun produit disponible pour le moment.',
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
                  final tileWidth = (width - gap * (columns - 1)) / columns;

                  return SliverGrid(
                    delegate: SliverChildBuilderDelegate(
                      (context, index) => _CatalogProductCard(product: _products[index]),
                      childCount: _products.length,
                    ),
                    gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
                      crossAxisCount: columns,
                      crossAxisSpacing: gap,
                      mainAxisSpacing: 6,
                      mainAxisExtent: _CatalogProductCard.extentFor(tileWidth),
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

  Widget _buildHeader() {
    final root = _selectedRoot;
    final sub = _selectedSubcategory;
    final count = _loading && _products.isEmpty ? '' : (_total == 1 ? '1 produit' : '$_total produits');

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (_query.isEmpty)
          Padding(
            padding: const EdgeInsets.fromLTRB(8, 0, 8, 10),
            child: _CatalogBanner(category: root),
          ),
        if (_roots.isNotEmpty)
          SizedBox(
            height: 36,
            child: ListView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 13),
              physics: const BouncingScrollPhysics(),
              children: [
                CatalogPill(label: 'Tous', selected: root == null, onTap: () => _selectRoot(null)),
                for (final item in _roots) ...[
                  const SizedBox(width: 6),
                  CatalogPill(label: item.name, selected: root?.slug == item.slug, onTap: () => _selectRoot(item)),
                ],
              ],
            ),
          ),
        Padding(
          padding: const EdgeInsets.fromLTRB(13, 12, 13, 10),
          child: Row(
            children: [
              Expanded(
                child: FittedBox(
                  fit: BoxFit.scaleDown,
                  alignment: Alignment.centerLeft,
                  child: Text.rich(
                    TextSpan(
                      text: _scopeLabel,
                      children: [
                        if (count.isNotEmpty)
                          TextSpan(
                            text: '  •  $count',
                            style: const TextStyle(color: catalogMuted, fontWeight: FontWeight.w500),
                          ),
                      ],
                    ),
                    maxLines: 1,
                    style: const TextStyle(color: authNavy, fontSize: 12.5, fontWeight: FontWeight.w700),
                  ),
                ),
              ),
              const SizedBox(width: 8),
              CatalogToolbarButton(icon: Icons.swap_vert_rounded, label: 'Trier', onTap: _chooseSort),
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
        SizedBox(
          height: 36,
          child: ListView(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 13),
            physics: const BouncingScrollPhysics(),
            children: [
              CatalogPill(label: 'Populaire', selected: _sort == 'popular', onTap: _quickPopular),
              const SizedBox(width: 6),
              CatalogPill(
                label: 'Prix',
                trailing: _sort == 'price_desc' ? Icons.arrow_downward_rounded : Icons.arrow_upward_rounded,
                selected: _sort == 'price_asc' || _sort == 'price_desc',
                onTap: _quickPrice,
              ),
              const SizedBox(width: 6),
              CatalogPill(label: 'Disponible', selected: _stock != null, onTap: _quickAvailability),
              const SizedBox(width: 6),
              CatalogPill(label: sub?.name ?? 'Catégorie', selected: sub != null, onTap: _quickCategory),
            ],
          ),
        ),
        const SizedBox(height: 10),
      ],
    );
  }
}

/// En-tête : retour, titre centré, partage et panier.
class _CatalogHeader extends StatelessWidget {
  const _CatalogHeader({
    required this.title,
    required this.canGoBack,
    required this.onBack,
    required this.onShare,
    required this.onOpenCart,
  });

  final String title;
  final bool canGoBack;
  final VoidCallback onBack;
  final VoidCallback onShare;
  final VoidCallback onOpenCart;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 50,
      child: Row(
        children: [
          if (canGoBack)
            IconButton(
              onPressed: onBack,
              tooltip: 'Retour',
              icon: const Icon(Icons.arrow_back_rounded, size: 23, color: authNavy),
            )
          else
            const SizedBox(width: 14),
          Expanded(
            child: Text(
              title,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              textAlign: TextAlign.center,
              style: const TextStyle(color: authNavy, fontSize: 16.5, fontWeight: FontWeight.w800),
            ),
          ),
          IconButton(
            onPressed: onShare,
            tooltip: 'Copier le lien',
            visualDensity: VisualDensity.compact,
            icon: const Icon(Icons.ios_share_rounded, size: 22, color: authNavy),
          ),
          AnimatedBuilder(
            animation: CartStore.instance,
            builder: (context, _) {
              final count = CartStore.instance.itemsCount;
              return IconButton(
                onPressed: onOpenCart,
                tooltip: count > 0 ? 'Panier, $count article${count > 1 ? 's' : ''}' : 'Panier',
                icon: Badge.count(
                  count: count,
                  isLabelVisible: count > 0,
                  backgroundColor: authOrange,
                  textColor: Colors.white,
                  child: const Icon(Icons.shopping_cart_outlined, size: 23, color: authNavy),
                ),
              );
            },
          ),
        ],
      ),
    );
  }
}

/// Bannière du catalogue. Sans catégorie : le message de la maquette ; avec
/// une catégorie : son visuel officiel et son nom.
class _CatalogBanner extends StatelessWidget {
  const _CatalogBanner({required this.category});

  final CategoryModel? category;

  @override
  Widget build(BuildContext context) {
    final category = this.category;
    const pillStyle = TextStyle(color: authNavy, fontSize: 9.5, fontWeight: FontWeight.w500);

    Widget pill(String text) => Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(99)),
      child: Text(text, maxLines: 1, overflow: TextOverflow.ellipsis, style: pillStyle),
    );

    return ClipRRect(
      borderRadius: BorderRadius.circular(9),
      child: AspectRatio(
        aspectRatio: 814 / 226,
        child: Stack(
          fit: StackFit.expand,
          children: [
            if (category == null)
              Image.asset(
                'assets/images/auth/catalog_banner.webp',
                fit: BoxFit.cover,
                alignment: const Alignment(0, -.2),
                excludeFromSemantics: true,
              )
            else
              CatalogImage(
                urls: rootCategoryImages(category),
                placeholder: const ColoredBox(color: authNavy),
              ),
            const DecoratedBox(
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  colors: [Color(0xE6101A33), Color(0x99101A33), Color(0x1A101A33)],
                  stops: [0, .5, 1],
                ),
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(13, 8, 13, 9),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Text(
                    category?.name ?? 'Des ciments de qualité',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 16.5,
                      height: 1.2,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    category == null
                        ? 'Pour des constructions solides\nqui durent dans le temps.'
                        : 'Des produits vérifiés pour vos chantiers,\nlivrés par OVANIE.',
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 10.5,
                      height: 1.3,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                  const SizedBox(height: 6),
                  Row(
                    children: [
                      pill('Qualité professionnelle'),
                      const SizedBox(width: 5),
                      Flexible(child: pill('Livraison fiable partout en Côte d’Ivoire')),
                    ],
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Carte produit du catalogue : photo dans un cadre gris, nom, étoiles, prix
/// orange par unité, ancien prix barré, disponibilité et bouton panier.
class _CatalogProductCard extends StatelessWidget {
  final ProductModel product;

  const _CatalogProductCard({required this.product});

  static const double _inset = 5;
  static const double _infoHeight = 112;

  /// Hauteur de la carte pour une largeur donnée.
  static double extentFor(double width) => _inset + (width - _inset * 2) / (377 / 222) + _infoHeight;

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

  /// Libellé et couleur de la disponibilité, d'après le stock réel.
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
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(_inset, _inset, _inset, 0),
                  child: ClipRRect(
                    borderRadius: BorderRadius.circular(6),
                    child: Stack(
                      fit: StackFit.expand,
                      children: [
                        ColoredBox(
                          color: const Color(0xFFF1F3F6),
                          child: _RemoteImage(url: product.imageUrl),
                        ),
                        if (product.hasDiscount && product.discountPercent > 0)
                          Positioned(
                            left: 4,
                            top: 4,
                            child: Container(
                              padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2.5),
                              decoration: BoxDecoration(color: authOrange, borderRadius: BorderRadius.circular(4)),
                              child: Text(
                                '-${product.discountPercent} %',
                                style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.w700),
                              ),
                            ),
                          ),
                        Positioned(
                          right: -3,
                          top: -3,
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
                                  color: favorite ? authOrange : authNavy,
                                ),
                              );
                            },
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
              SizedBox(
                height: _infoHeight,
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
                          if (rating > 0 && product.reviewsCount > 0) ...[
                            const SizedBox(height: 3),
                            Row(
                              children: [
                                _Stars(rating: rating),
                                const SizedBox(width: 4),
                                Flexible(
                                  child: Text(
                                    '${rating.toStringAsFixed(1).replaceAll('.', ',')} (${product.reviewsCount})',
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                    style: const TextStyle(color: catalogMuted, fontSize: 10.5),
                                  ),
                                ),
                              ],
                            ),
                          ],
                          const Spacer(),
                          Padding(
                            padding: const EdgeInsets.only(right: 40),
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
                                        style: const TextStyle(
                                          color: catalogMuted,
                                          fontSize: 10.5,
                                          fontWeight: FontWeight.w500,
                                        ),
                                      ),
                                  ],
                                ),
                                maxLines: 1,
                                style: const TextStyle(color: authOrange, fontSize: 14.5, fontWeight: FontWeight.w800),
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
                                      color: Color(0xFF7B8296),
                                      fontSize: 10,
                                      height: 1.3,
                                      decoration: TextDecoration.lineThrough,
                                      decorationColor: Color(0xFF7B8296),
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
                              const SizedBox(width: 5),
                              Flexible(
                                child: Text(
                                  stockLabel,
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: TextStyle(color: stockColor, fontSize: 10.5, fontWeight: FontWeight.w500),
                                ),
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),
                    Positioned(
                      right: 7,
                      bottom: 9,
                      child: Material(
                        color: canBuy ? authOrange : const Color(0xFFCBD2DC),
                        borderRadius: BorderRadius.circular(7),
                        child: InkWell(
                          onTap: canBuy ? () => _addToCart(context) : null,
                          borderRadius: BorderRadius.circular(7),
                          child: SizedBox(
                            width: 34,
                            height: 34,
                            child: Icon(
                              Icons.shopping_cart_outlined,
                              color: Colors.white,
                              size: 19,
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

/// Cinq étoiles, avec demi-étoile, pour la note moyenne.
class _Stars extends StatelessWidget {
  const _Stars({required this.rating});

  final double rating;

  @override
  Widget build(BuildContext context) {
    return ExcludeSemantics(
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          for (var i = 1; i <= 5; i++)
            Icon(
              rating >= i - .25
                  ? Icons.star_rounded
                  : (rating >= i - .75 ? Icons.star_half_rounded : Icons.star_outline_rounded),
              size: 12.5,
              color: const Color(0xFFFFB400),
            ),
        ],
      ),
    );
  }
}

class _RemoteImage extends StatelessWidget {
  const _RemoteImage({required this.url});

  final String url;

  @override
  Widget build(BuildContext context) {
    const placeholder = Center(child: Icon(Icons.inventory_2_outlined, size: 34, color: Color(0xFFB5BECA)));
    if (url.trim().isEmpty) return placeholder;

    return Padding(
      padding: const EdgeInsets.all(4),
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
