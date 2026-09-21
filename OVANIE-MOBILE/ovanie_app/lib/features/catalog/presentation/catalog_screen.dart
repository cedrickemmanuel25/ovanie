import 'dart:async';

import 'package:flutter/material.dart';

import '../../../app/theme.dart';
import '../../../core/network/api_client.dart';
import '../../../core/utils/formatters.dart';
import '../../../shared/widgets/api_error_card.dart';
import '../../auth/domain/session_store.dart';
import '../../cart/domain/cart_store.dart';
import '../../categories/domain/category_model.dart';
import '../../favorites/domain/favorites_store.dart';
import '../../home/data/marketplace_repository.dart';
import '../../products/domain/product_model.dart';
import '../../products/presentation/product_detail_screen.dart';
import '../../search/presentation/search_screen.dart';
import '../domain/catalog_navigation_store.dart';

/// Catalogue / Catégorie / Sous-catégorie OVANIE.
///
/// Une seule vue consomme les données Laravel et change d'état selon la
/// sélection :
/// - aucune catégorie : catalogue complet ;
/// - catégorie racine : écran Catégorie ;
/// - enfant sélectionné : écran Sous-catégorie.
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

  String? get _activeCategorySlug =>
      _selectedSubcategory?.slug ?? _selectedRoot?.slug;

  String get _screenTitle {
    if (_query.isNotEmpty) return 'Résultats pour « $_query »';
    return _selectedSubcategory?.name ??
        _selectedRoot?.name ??
        _externalCategoryName ??
        widget.categoryName ??
        'Catalogue produits';
  }

  bool get _hasActiveFilters =>
      _brand != null ||
      _stock != null ||
      _minPrice != null ||
      _maxPrice != null ||
      _offer != null;

  bool get _isAllCatalog =>
      _selectedRoot == null &&
      _selectedSubcategory == null &&
      (_externalCategoryName?.trim().isEmpty ?? true);

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

      final roots = results[0] as List<CategoryModel>;
      final filters = results[1] as CatalogFilters;

      CategoryModel? selectedRoot;
      CategoryModel? selectedSubcategory;
      if (widget.categoryId != null ||
          (widget.categorySlug?.trim().isNotEmpty ?? false)) {
        final target = _repository.findCategoryInRoots(
          roots,
          id: widget.categoryId,
          slug: widget.categorySlug,
        );
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
      _query = '';
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
      _query = '';
    });
    _jumpTop();
    await _loadProducts(reset: true);
  }

  Future<void> _goBackInsideCatalog() async {
    if (_selectedSubcategory != null) {
      await _selectSubcategory(null);
      return;
    }
    if (_selectedRoot != null) {
      await _selectRoot(null);
      return;
    }
    if (widget.showBackButton && mounted) {
      Navigator.of(context).maybePop();
    }
  }

  void _openSearch() {
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => SearchScreen(initialQuery: _query),
      ),
    );
  }

  Future<void> _chooseSort() async {
    final options = _filters?.sorts ?? const <CatalogOption>[];
    if (options.isEmpty) return;

    final selected = await showModalBottomSheet<String>(
      context: context,
      showDragHandle: true,
      builder: (context) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 0, 16, 18),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Trier les produits',
                style: TextStyle(
                  fontSize: 19,
                  fontWeight: FontWeight.w900,
                  color: OvanieColors.text,
                ),
              ),
              const SizedBox(height: 10),
              for (final option in options)
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  leading: Icon(
                    _sort == option.value
                        ? Icons.radio_button_checked_rounded
                        : Icons.radio_button_off_rounded,
                    color: _sort == option.value
                        ? OvanieColors.orange
                        : OvanieColors.muted,
                  ),
                  title: Text(
                    option.label,
                    style: const TextStyle(fontWeight: FontWeight.w700),
                  ),
                  onTap: () => Navigator.pop(context, option.value),
                ),
            ],
          ),
        ),
      ),
    );

    if (selected == null || selected == _sort) return;
    setState(() => _sort = selected);
    _jumpTop();
    await _loadProducts(reset: true);
  }

  Future<void> _openFilters() async {
    final filters = _filters;
    if (filters == null) return;

    final result = await showModalBottomSheet<_FilterResult>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (context) => _CatalogFilterSheet(
        filters: filters,
        brand: _brand,
        stock: _stock,
        minPrice: _minPrice,
        maxPrice: _maxPrice,
      ),
    );

    if (result == null) return;
    setState(() {
      _brand = result.brand;
      _stock = result.stock;
      _minPrice = result.minPrice;
      _maxPrice = result.maxPrice;
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
    if (availability.isEmpty) return;
    setState(() {
      _stock = _stock == null ? availability.first.value : null;
    });
    await _loadProducts(reset: true);
  }

  void _shareCurrentCategory() {
    final label = _selectedSubcategory?.name ?? _selectedRoot?.name;
    if (label == null) return;
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          content: Text('Partage de « $label »'),
          duration: const Duration(milliseconds: 1100),
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
    return Scaffold(
      backgroundColor: Colors.white,
      body: SafeArea(
        bottom: false,
        child: Column(
          children: [
            _TopSearchBar(onTap: _openSearch, query: _query),
            Expanded(
              child: LayoutBuilder(
                builder: (context, constraints) {
                  final railWidth = (constraints.maxWidth * .285)
                      .clamp(108.0, 132.0)
                      .toDouble();
                  return Row(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      SizedBox(
                        width: railWidth,
                        child: _CategoryRail(
                          roots: _roots,
                          selectedRoot: _selectedRoot,
                          loading: _loading && _roots.isEmpty,
                          onSelect: _selectRoot,
                        ),
                      ),
                      const VerticalDivider(
                        width: 1,
                        thickness: 1,
                        color: Color(0xFFE9EDF3),
                      ),
                      Expanded(child: _buildCatalogPanel()),
                    ],
                  );
                },
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildCatalogPanel() {
    return RefreshIndicator(
      onRefresh: () => _loadProducts(reset: true),
      color: OvanieColors.orange,
      child: CustomScrollView(
        controller: _contentScrollController,
        physics: const AlwaysScrollableScrollPhysics(
          parent: BouncingScrollPhysics(),
        ),
        slivers: [
          SliverToBoxAdapter(child: _buildHeader()),
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
                padding: const EdgeInsets.all(14),
                child: ApiErrorCard(
                  message: ApiClient.friendlyError(_error!),
                  onRetry: () => _loadProducts(reset: true),
                ),
              ),
            )
          else if (_products.isEmpty)
            SliverFillRemaining(
              hasScrollBody: false,
              child: _EmptyCatalog(
                title: _screenTitle,
                onClear: _hasActiveFilters ? _clearFilters : null,
              ),
            )
          else
            SliverPadding(
              padding: const EdgeInsets.fromLTRB(9, 8, 9, 18),
              sliver: SliverLayoutBuilder(
                builder: (context, constraints) {
                  final width = constraints.crossAxisExtent;
                  final columns = width >= 620
                      ? 4
                      : width >= 450
                          ? 3
                          : 2;
                  final gap = 8.0;
                  final tileWidth =
                      (width - gap * (columns - 1)) / columns;
                  final tileExtent =
                      (tileWidth * 1.72).clamp(218.0, 282.0).toDouble();

                  return SliverGrid(
                    delegate: SliverChildBuilderDelegate(
                      (context, index) => _CatalogProductCard(
                        product: _products[index],
                      ),
                      childCount: _products.length,
                    ),
                    gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
                      crossAxisCount: columns,
                      crossAxisSpacing: gap,
                      mainAxisSpacing: 9,
                      mainAxisExtent: tileExtent,
                    ),
                  );
                },
              ),
            ),
          if (_products.isNotEmpty)
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.only(bottom: 24),
                child: Center(
                  child: _loadingMore
                      ? const CircularProgressIndicator(
                          strokeWidth: 2,
                          color: OvanieColors.orange,
                        )
                      : _page >= _lastPage
                          ? const SizedBox.shrink()
                          : const SizedBox(height: 16),
                ),
              ),
            ),
        ],
      ),
    );
  }

  Widget _buildHeader() {
    final selectedRoot = _selectedRoot;
    final selectedSubcategory = _selectedSubcategory;

    if (_isAllCatalog) {
      return Padding(
        padding: const EdgeInsets.fromLTRB(14, 11, 10, 0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Catalogue produits',
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                color: OvanieColors.navy,
                fontSize: 24,
                height: 1.05,
                fontWeight: FontWeight.w900,
              ),
            ),
            const SizedBox(height: 3),
            Text(
              'Tous les produits  •  $_total produits',
              style: const TextStyle(
                color: OvanieColors.muted,
                fontSize: 12.5,
                fontWeight: FontWeight.w500,
              ),
            ),
            const SizedBox(height: 14),
            _ActionToolbar(
              filterActive: _hasActiveFilters,
              onFilters: _openFilters,
              onSort: _chooseSort,
            ),
            const SizedBox(height: 10),
            Wrap(
              spacing: 7,
              runSpacing: 7,
              children: [
                _QuickChip(
                  label: 'Populaire',
                  selected: _sort == 'popular',
                  onTap: _quickPopular,
                ),
                _QuickChip(
                  label: 'Prix ${_sort == 'price_desc' ? '⌄' : '⌃'}',
                  selected: _sort == 'price_asc' || _sort == 'price_desc',
                  onTap: _quickPrice,
                ),
                _QuickChip(
                  label: 'Disponible',
                  selected: _stock != null,
                  onTap: _quickAvailability,
                ),
              ],
            ),
            const SizedBox(height: 10),
            const _TrustStrip(),
          ],
        ),
      );
    }

    final title = selectedSubcategory?.name ??
        selectedRoot?.name ??
        _externalCategoryName ??
        'Catégorie';

    return Padding(
      padding: const EdgeInsets.fromLTRB(10, 10, 10, 0),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              _SquareIconButton(
                icon: Icons.arrow_back_rounded,
                onTap: _goBackInsideCatalog,
              ),
              const SizedBox(width: 9),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: OvanieColors.navy,
                        fontSize: 22,
                        height: 1.02,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      '$_total produits',
                      style: const TextStyle(
                        color: OvanieColors.muted,
                        fontSize: 12,
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 7),
              _SquareIconButton(
                icon: Icons.share_outlined,
                onTap: _shareCurrentCategory,
              ),
            ],
          ),
          const SizedBox(height: 11),
          if (selectedRoot != null)
            _CategoryHeroBanner(
              category: selectedRoot,
              fallbackImageUrl:
                  _products.isNotEmpty ? _products.first.imageUrl : '',
            ),
          const SizedBox(height: 11),
          _ActionToolbar(
            filterActive: _hasActiveFilters,
            onFilters: _openFilters,
            onSort: _chooseSort,
          ),
          if (selectedRoot != null && selectedRoot.children.isNotEmpty) ...[
            const SizedBox(height: 10),
            SizedBox(
              height: 37,
              child: ListView(
                scrollDirection: Axis.horizontal,
                physics: const BouncingScrollPhysics(),
                children: [
                  _SubcategoryPill(
                    label: 'Tous',
                    selected: selectedSubcategory == null,
                    onTap: () => _selectSubcategory(null),
                  ),
                  for (final child in selectedRoot.children)
                    _SubcategoryPill(
                      label: child.name,
                      selected: selectedSubcategory?.slug == child.slug,
                      onTap: () => _selectSubcategory(child),
                    ),
                ],
              ),
            ),
          ],
          const SizedBox(height: 10),
          const _TrustStrip(),
        ],
      ),
    );
  }
}

class _TopSearchBar extends StatelessWidget {
  final VoidCallback onTap;
  final String query;

  const _TopSearchBar({required this.onTap, required this.query});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(13, 8, 13, 10),
      child: Material(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(14),
          child: Container(
            height: 52,
            padding: const EdgeInsets.symmetric(horizontal: 14),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: const Color(0xFFE0E5EC)),
            ),
            child: Row(
              children: [
                const Icon(
                  Icons.search_rounded,
                  color: Color(0xFF607087),
                  size: 24,
                ),
                const SizedBox(width: 11),
                Expanded(
                  child: Text(
                    query.isEmpty
                        ? 'Rechercher un produit, une marque...'
                        : query,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: query.isEmpty
                          ? const Color(0xFF758197)
                          : OvanieColors.text,
                      fontSize: 13,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                ),
                const Icon(
                  Icons.center_focus_weak,
                  color: OvanieColors.navy,
                  size: 24,
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _CategoryRail extends StatelessWidget {
  final List<CategoryModel> roots;
  final CategoryModel? selectedRoot;
  final bool loading;
  final ValueChanged<CategoryModel?> onSelect;

  const _CategoryRail({
    required this.roots,
    required this.selectedRoot,
    required this.loading,
    required this.onSelect,
  });

  @override
  Widget build(BuildContext context) {
    if (loading) {
      return const Center(
        child: CircularProgressIndicator(
          strokeWidth: 2,
          color: OvanieColors.orange,
        ),
      );
    }

    return ColoredBox(
      color: Colors.white,
      child: ListView.separated(
        padding: const EdgeInsets.fromLTRB(10, 1, 6, 12),
        physics: const BouncingScrollPhysics(),
        itemCount: roots.length + 1,
        separatorBuilder: (_, __) => const SizedBox(height: 5),
        itemBuilder: (context, index) {
          if (index == 0) {
            return _CategoryRailTile(
              title: 'Toutes',
              selected: selectedRoot == null,
              onTap: () => onSelect(null),
            );
          }

          final category = roots[index - 1];
          return _CategoryRailTile(
            title: category.name,
            selected: selectedRoot?.slug == category.slug,
            onTap: () => onSelect(category),
          );
        },
      ),
    );
  }
}

class _CategoryRailTile extends StatelessWidget {
  final String title;
  final bool selected;
  final VoidCallback onTap;

  const _CategoryRailTile({
    required this.title,
    required this.selected,
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
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 160),
          constraints: const BoxConstraints(minHeight: 70),
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 9),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(12),
            border: Border.all(
              color: selected ? OvanieColors.orange : const Color(0xFFE1E6ED),
              width: selected ? 1.5 : 1,
            ),
          ),
          child: Center(
            child: Text(
              title,
              maxLines: 3,
              overflow: TextOverflow.ellipsis,
              textAlign: TextAlign.center,
              style: TextStyle(
                color: selected ? OvanieColors.orange : OvanieColors.navy,
                fontSize: 11.4,
                height: 1.12,
                fontWeight: selected ? FontWeight.w900 : FontWeight.w800,
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _ActionToolbar extends StatelessWidget {
  final bool filterActive;
  final VoidCallback onFilters;
  final VoidCallback onSort;

  const _ActionToolbar({
    required this.filterActive,
    required this.onFilters,
    required this.onSort,
  });

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(
          child: _OutlinedActionButton(
            icon: Icons.tune_rounded,
            label: 'Filtres',
            active: filterActive,
            onTap: onFilters,
          ),
        ),
        const SizedBox(width: 8),
        Expanded(
          child: _OutlinedActionButton(
            icon: Icons.swap_vert_rounded,
            label: 'Trier',
            trailing: Icons.keyboard_arrow_down_rounded,
            onTap: onSort,
          ),
        ),
      ],
    );
  }
}

class _OutlinedActionButton extends StatelessWidget {
  final IconData icon;
  final IconData? trailing;
  final String label;
  final bool active;
  final VoidCallback onTap;

  const _OutlinedActionButton({
    required this.icon,
    this.trailing,
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
          height: 46,
          padding: const EdgeInsets.symmetric(horizontal: 10),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(12),
            border: Border.all(
              color: active
                  ? OvanieColors.orange
                  : const Color(0xFFDDE3EA),
            ),
          ),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(
                icon,
                size: 20,
                color: active ? OvanieColors.orange : OvanieColors.navy,
              ),
              const SizedBox(width: 8),
              Flexible(
                child: Text(
                  label,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: active ? OvanieColors.orange : OvanieColors.navy,
                    fontSize: 13,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
              if (trailing != null) ...[
                const SizedBox(width: 3),
                Icon(trailing, size: 18, color: OvanieColors.navy),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class _QuickChip extends StatelessWidget {
  final String label;
  final bool selected;
  final VoidCallback onTap;

  const _QuickChip({
    required this.label,
    required this.selected,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.transparent,
      borderRadius: BorderRadius.circular(11),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(11),
        child: Container(
          height: 36,
          padding: const EdgeInsets.symmetric(horizontal: 12),
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: selected ? const Color(0xFFFFF7F1) : Colors.white,
            borderRadius: BorderRadius.circular(11),
            border: Border.all(
              color: selected
                  ? OvanieColors.orange
                  : const Color(0xFFE1E6ED),
            ),
          ),
          child: Text(
            label,
            style: TextStyle(
              color: selected ? OvanieColors.orange : OvanieColors.navy,
              fontSize: 11.5,
              fontWeight: FontWeight.w700,
            ),
          ),
        ),
      ),
    );
  }
}

class _SubcategoryPill extends StatelessWidget {
  final String label;
  final bool selected;
  final VoidCallback onTap;

  const _SubcategoryPill({
    required this.label,
    required this.selected,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(right: 7),
      child: _QuickChip(label: label, selected: selected, onTap: onTap),
    );
  }
}

class _TrustStrip extends StatelessWidget {
  const _TrustStrip();

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      constraints: const BoxConstraints(minHeight: 42),
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
      decoration: BoxDecoration(
        color: const Color(0xFFFFF6F0),
        borderRadius: BorderRadius.circular(18),
      ),
      child: Row(
        children: [
          const Icon(
            Icons.verified_user_outlined,
            size: 18,
            color: OvanieColors.navy,
          ),
          const SizedBox(width: 6),
          const Flexible(
            child: Text(
              'Qualité professionnelle',
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                color: OvanieColors.navy,
                fontSize: 9.8,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
          const Padding(
            padding: EdgeInsets.symmetric(horizontal: 7),
            child: Text('•', style: TextStyle(color: OvanieColors.navy)),
          ),
          const Icon(
            Icons.local_shipping_outlined,
            size: 19,
            color: OvanieColors.navy,
          ),
          const SizedBox(width: 5),
          const Expanded(
            child: Text(
              "Livraison fiable partout en Côte d'Ivoire",
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                color: OvanieColors.navy,
                fontSize: 9.8,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _CategoryHeroBanner extends StatelessWidget {
  final CategoryModel category;
  final String fallbackImageUrl;

  const _CategoryHeroBanner({
    required this.category,
    required this.fallbackImageUrl,
  });

  @override
  Widget build(BuildContext context) {
    final isCement = _looksLikeCement(category);
    final imageUrl = _categoryImageUrl(category).isNotEmpty
        ? _categoryImageUrl(category)
        : fallbackImageUrl;

    final headline = isCement
        ? 'Des ciments de qualité'
        : 'Des ${category.name.toLowerCase()} de qualité';
    final subtitle = isCement
        ? 'Pour des constructions solides\nqui durent dans le temps.'
        : 'Des produits fiables pour vos\ntravaux et vos chantiers.';

    return Container(
      height: 122,
      width: double.infinity,
      decoration: BoxDecoration(
        color: const Color(0xFFF4F0EB),
        borderRadius: BorderRadius.circular(14),
        gradient: const LinearGradient(
          begin: Alignment.centerLeft,
          end: Alignment.centerRight,
          colors: [Color(0xFFF6F3F0), Color(0xFFEFEAE4)],
        ),
      ),
      clipBehavior: Clip.antiAlias,
      child: Stack(
        children: [
          Positioned(
            left: 13,
            top: 20,
            right: 112,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  headline,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: OvanieColors.navy,
                    fontSize: 16,
                    height: 1.08,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  subtitle,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: OvanieColors.navy,
                    fontSize: 10.5,
                    height: 1.25,
                    fontWeight: FontWeight.w500,
                  ),
                ),
              ],
            ),
          ),
          Positioned(
            right: -5,
            bottom: 1,
            width: 126,
            height: 112,
            child: imageUrl.isEmpty
                ? Icon(
                    _fallbackCategoryIcon(category.name),
                    size: 70,
                    color: const Color(0xFFC8B9A9),
                  )
                : _RemoteImage(url: imageUrl, fit: BoxFit.contain),
          ),
          const Positioned(
            bottom: 8,
            left: 0,
            right: 0,
            child: Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                _HeroDot(active: true),
                _HeroDot(),
                _HeroDot(),
                _HeroDot(),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _HeroDot extends StatelessWidget {
  final bool active;

  const _HeroDot({this.active = false});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: active ? 13 : 6,
      height: 6,
      margin: const EdgeInsets.symmetric(horizontal: 2),
      decoration: BoxDecoration(
        color: active ? OvanieColors.orange : Colors.white,
        borderRadius: BorderRadius.circular(10),
      ),
    );
  }
}

class _SquareIconButton extends StatelessWidget {
  final IconData icon;
  final VoidCallback onTap;

  const _SquareIconButton({required this.icon, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(12),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(12),
        child: Container(
          width: 44,
          height: 44,
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: const Color(0xFFE1E6ED)),
          ),
          child: Icon(icon, color: OvanieColors.navy, size: 23),
        ),
      ),
    );
  }
}

class _CatalogProductCard extends StatelessWidget {
  final ProductModel product;

  const _CatalogProductCard({required this.product});

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
            border: Border.all(color: const Color(0xFFE3E7ED)),
          ),
          child: LayoutBuilder(
            builder: (context, constraints) {
              final imageHeight =
                  (constraints.maxWidth * .86).clamp(92.0, 128.0).toDouble();
              return Stack(
                children: [
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      SizedBox(
                        height: imageHeight,
                        width: double.infinity,
                        child: Padding(
                          padding: const EdgeInsets.fromLTRB(7, 8, 7, 2),
                          child: _RemoteImage(
                            url: product.imageUrl,
                            fit: BoxFit.contain,
                            fallbackIcon: Icons.inventory_2_outlined,
                          ),
                        ),
                      ),
                      Expanded(
                        child: Padding(
                          padding: const EdgeInsets.fromLTRB(8, 4, 7, 8),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                product.name,
                                maxLines: 2,
                                overflow: TextOverflow.ellipsis,
                                style: const TextStyle(
                                  color: OvanieColors.navy,
                                  fontSize: 11.3,
                                  height: 1.12,
                                  fontWeight: FontWeight.w800,
                                ),
                              ),
                              const SizedBox(height: 4),
                              Text(
                                product.homeSubtitle,
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: const TextStyle(
                                  color: OvanieColors.muted,
                                  fontSize: 9.5,
                                  height: 1,
                                  fontWeight: FontWeight.w500,
                                ),
                              ),
                              const Spacer(),
                              Padding(
                                padding: const EdgeInsets.only(right: 42),
                                child: Text(
                                  formatFcfa(product.homeDisplayPrice),
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: const TextStyle(
                                    color: OvanieColors.orange,
                                    fontSize: 12.8,
                                    height: 1.05,
                                    fontWeight: FontWeight.w900,
                                  ),
                                ),
                              ),
                              if (product.hasDiscount &&
                                  product.homeOriginalPrice > 0) ...[
                                const SizedBox(height: 3),
                                Padding(
                                  padding: const EdgeInsets.only(right: 42),
                                  child: Text(
                                    formatFcfa(product.homeOriginalPrice),
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                    style: const TextStyle(
                                      color: OvanieColors.muted,
                                      fontSize: 9.4,
                                      decoration: TextDecoration.lineThrough,
                                      decorationColor: OvanieColors.muted,
                                    ),
                                  ),
                                ),
                              ] else
                                const SizedBox(height: 13),
                            ],
                          ),
                        ),
                      ),
                    ],
                  ),
                  if (product.hasDiscount && product.discountPercent > 0)
                    Positioned(
                      left: 7,
                      top: 7,
                      child: Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 7,
                          vertical: 4,
                        ),
                        decoration: BoxDecoration(
                          color: const Color(0xFFE60012),
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
                            fontSize: 9.5,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                      ),
                    ),
                  Positioned(
                    right: 7,
                    top: 7,
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
                          radius: 20,
                          child: Icon(
                            favorite
                                ? Icons.favorite_rounded
                                : Icons.favorite_border_rounded,
                            size: 20,
                            color: favorite
                                ? OvanieColors.orange
                                : OvanieColors.navy,
                          ),
                        );
                      },
                    ),
                  ),
                  Positioned(
                    right: 7,
                    bottom: 7,
                    child: Material(
                      color: canBuy
                          ? OvanieColors.orange
                          : const Color(0xFFCBD2DC),
                      borderRadius: BorderRadius.circular(10),
                      child: InkWell(
                        onTap: canBuy ? () => _addToCart(context) : null,
                        borderRadius: BorderRadius.circular(10),
                        child: const SizedBox(
                          width: 38,
                          height: 38,
                          child: Icon(
                            Icons.shopping_cart_outlined,
                            color: Colors.white,
                            size: 22,
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

class _RemoteImage extends StatelessWidget {
  final String url;
  final BoxFit fit;
  final IconData? fallbackIcon;

  const _RemoteImage({
    required this.url,
    this.fit = BoxFit.contain,
    this.fallbackIcon,
  });

  @override
  Widget build(BuildContext context) {
    if (url.trim().isEmpty) {
      return Center(
        child: Icon(
          fallbackIcon ?? Icons.inventory_2_outlined,
          size: 36,
          color: const Color(0xFFB8C0CC),
        ),
      );
    }

    return Image.network(
      url,
      fit: fit,
      errorBuilder: (_, __, ___) => Center(
        child: Icon(
          fallbackIcon ?? Icons.inventory_2_outlined,
          size: 36,
          color: const Color(0xFFB8C0CC),
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
              color: Color(0xFFD8DDE5),
            ),
          ),
        );
      },
    );
  }
}

class _CatalogFilterSheet extends StatefulWidget {
  final CatalogFilters filters;
  final String? brand;
  final String? stock;
  final double? minPrice;
  final double? maxPrice;

  const _CatalogFilterSheet({
    required this.filters,
    required this.brand,
    required this.stock,
    required this.minPrice,
    required this.maxPrice,
  });

  @override
  State<_CatalogFilterSheet> createState() => _CatalogFilterSheetState();
}

class _CatalogFilterSheetState extends State<_CatalogFilterSheet> {
  String? _brand;
  String? _stock;
  late final TextEditingController _minController;
  late final TextEditingController _maxController;

  @override
  void initState() {
    super.initState();
    _brand = widget.brand;
    _stock = widget.stock;
    _minController = TextEditingController(
      text: widget.minPrice?.round().toString() ?? '',
    );
    _maxController = TextEditingController(
      text: widget.maxPrice?.round().toString() ?? '',
    );
  }

  @override
  void dispose() {
    _minController.dispose();
    _maxController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.fromLTRB(
        18,
        0,
        18,
        MediaQuery.of(context).viewInsets.bottom + 22,
      ),
      child: SingleChildScrollView(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Filtrer le catalogue',
              style: TextStyle(
                fontSize: 20,
                fontWeight: FontWeight.w900,
                color: OvanieColors.text,
              ),
            ),
            const SizedBox(height: 18),
            const Text(
              'Disponibilité',
              style: TextStyle(fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 8),
            Wrap(
              spacing: 8,
              children: [
                ChoiceChip(
                  label: const Text('Toutes'),
                  selected: _stock == null,
                  onSelected: (_) => setState(() => _stock = null),
                ),
                for (final option in widget.filters.availability)
                  ChoiceChip(
                    label: Text(option.label),
                    selected: _stock == option.value,
                    onSelected: (_) => setState(() => _stock = option.value),
                  ),
              ],
            ),
            const SizedBox(height: 18),
            const Text(
              'Marque',
              style: TextStyle(fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 8),
            DropdownButtonFormField<String>(
              value: _brand ?? '',
              items: [
                const DropdownMenuItem<String>(
                  value: '',
                  child: Text('Toutes les marques'),
                ),
                for (final brand in widget.filters.brands)
                  DropdownMenuItem<String>(value: brand, child: Text(brand)),
              ],
              onChanged: (value) => setState(
                () => _brand =
                    (value == null || value.isEmpty) ? null : value,
              ),
            ),
            const SizedBox(height: 18),
            const Text(
              'Prix',
              style: TextStyle(fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 8),
            Row(
              children: [
                Expanded(
                  child: TextField(
                    controller: _minController,
                    keyboardType: TextInputType.number,
                    decoration: const InputDecoration(labelText: 'Minimum FCFA'),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: TextField(
                    controller: _maxController,
                    keyboardType: TextInputType.number,
                    decoration: const InputDecoration(labelText: 'Maximum FCFA'),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 22),
            SizedBox(
              width: double.infinity,
              height: 50,
              child: FilledButton(
                onPressed: () {
                  Navigator.pop(
                    context,
                    _FilterResult(
                      brand: _brand,
                      stock: _stock,
                      minPrice: double.tryParse(_minController.text.trim()),
                      maxPrice: double.tryParse(_maxController.text.trim()),
                    ),
                  );
                },
                style: FilledButton.styleFrom(
                  backgroundColor: OvanieColors.orange,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(14),
                  ),
                ),
                child: const Text(
                  'Afficher les résultats',
                  style: TextStyle(fontWeight: FontWeight.w900),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _FilterResult {
  final String? brand;
  final String? stock;
  final double? minPrice;
  final double? maxPrice;

  const _FilterResult({
    this.brand,
    this.stock,
    this.minPrice,
    this.maxPrice,
  });
}

class _EmptyCatalog extends StatelessWidget {
  final String title;
  final VoidCallback? onClear;

  const _EmptyCatalog({required this.title, this.onClear});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(
              Icons.inventory_2_outlined,
              size: 48,
              color: OvanieColors.muted,
            ),
            const SizedBox(height: 12),
            Text(
              'Aucun produit dans « $title ».',
              textAlign: TextAlign.center,
              style: const TextStyle(
                color: OvanieColors.text,
                fontWeight: FontWeight.w800,
              ),
            ),
            if (onClear != null) ...[
              const SizedBox(height: 12),
              TextButton(
                onPressed: onClear,
                child: const Text('Effacer les filtres'),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

String _categoryImageUrl(CategoryModel category) {
  final image = category.imageUrl.trim();
  if (image.isNotEmpty) return image;
  final icon = category.icon.trim();
  if (icon.startsWith('http://') || icon.startsWith('https://')) return icon;
  return '';
}

bool _looksLikeCement(CategoryModel category) {
  final value = '${category.slug} ${category.name}'.toLowerCase();
  return value.contains('ciment') || value.contains('liant');
}

IconData _fallbackCategoryIcon(String categoryName) {
  final value = categoryName.toLowerCase();
  if (value.contains('ciment') || value.contains('liant')) {
    return Icons.inventory_2_outlined;
  }
  if (value.contains('fer') || value.contains('métal')) {
    return Icons.view_week_outlined;
  }
  if (value.contains('gros') || value.contains('béton')) {
    return Icons.domain_outlined;
  }
  if (value.contains('bois')) return Icons.handyman_outlined;
  if (value.contains('élect')) return Icons.electrical_services_outlined;
  if (value.contains('plomb')) return Icons.plumbing_outlined;
  if (value.contains('peint')) return Icons.format_paint_outlined;
  if (value.contains('outil')) return Icons.handyman_outlined;
  if (value.contains('sécur')) return Icons.health_and_safety_outlined;
  if (value.contains('carrel')) return Icons.grid_on_outlined;
  if (value.contains('quinc')) return Icons.build_outlined;
  return Icons.category_outlined;
}
