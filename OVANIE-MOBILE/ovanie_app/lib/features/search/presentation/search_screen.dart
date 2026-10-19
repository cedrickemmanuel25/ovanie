import 'dart:async';

import 'package:flutter/material.dart';

import '../../../core/network/api_client.dart';
import '../../../core/utils/formatters.dart';
import '../../../shared/widgets/api_error_card.dart';
import '../../auth/domain/session_store.dart';
import '../../auth/presentation/auth_branding.dart';
import '../../cart/domain/cart_store.dart';
import '../../categories/domain/category_model.dart';
import '../../categories/presentation/catalog_widgets.dart';
import '../../categories/presentation/categories_screen.dart';
import '../../favorites/domain/favorites_store.dart';
import '../../home/data/marketplace_repository.dart';
import '../../products/domain/product_model.dart';
import '../../products/presentation/product_detail_screen.dart';

/// Écran de recherche OVANIE reconstruit selon les maquettes mobiles.
///
/// - champ vide : « Que recherchez-vous ? », recherches populaires et
///   catégories ;
/// - deux caractères ou plus : liste des produits trouvés, tri et
///   disponibilité ;
/// - aucun produit : « Aucun résultat trouvé ».
///
/// Les recherches populaires, les catégories et les résultats proviennent
/// exclusivement de l'API Laravel.
class SearchScreen extends StatefulWidget {
  final String initialQuery;

  /// Quand il est vrai, valider la recherche referme l'écran et renvoie le
  /// texte saisi à l'écran appelant (le catalogue), qui affiche les résultats.
  final bool returnQuery;

  const SearchScreen({super.key, this.initialQuery = '', this.returnQuery = false});

  @override
  State<SearchScreen> createState() => _SearchScreenState();
}

class _SearchScreenState extends State<SearchScreen> {
  final MarketplaceRepository _repository = const MarketplaceRepository();
  late final TextEditingController _controller;
  final FocusNode _focusNode = FocusNode();
  final ScrollController _scrollController = ScrollController();
  Timer? _debounce;

  List<String> _popular = const <String>[];
  List<CategoryModel> _categories = const <CategoryModel>[];

  List<ProductModel> _products = const <ProductModel>[];
  int? _total;
  int _pageIndex = 1;
  int _lastPage = 1;
  bool _loading = false;
  bool _loadingMore = false;
  Object? _error;
  String _sort = 'popular';
  bool _inStockOnly = false;

  @override
  void initState() {
    super.initState();
    _controller = TextEditingController(text: widget.initialQuery);
    _scrollController.addListener(_onScroll);
    unawaited(_loadPrompt());
    final initial = widget.initialQuery.trim();
    if (initial.length >= 2 && !widget.returnQuery) {
      WidgetsBinding.instance.addPostFrameCallback((_) => _runSearch(initial));
    } else {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) _focusNode.requestFocus();
      });
    }
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _controller.dispose();
    _focusNode.dispose();
    _scrollController
      ..removeListener(_onScroll)
      ..dispose();
    super.dispose();
  }

  /// Recherches populaires et catégories de l'écran d'accueil de la
  /// recherche. Chacune s'affiche dès qu'elle arrive ; un échec la masque.
  Future<void> _loadPrompt() async {
    unawaited(
      _repository
          .getPopularSearches()
          .then((terms) {
            if (mounted) setState(() => _popular = terms);
          })
          .catchError((_) {}),
    );
    unawaited(
      _repository
          .getRootCategories(limit: 50)
          .then((roots) {
            if (mounted) setState(() => _categories = categoriesInDisplayOrder(roots));
          })
          .catchError((_) {}),
    );
  }

  void _resetResults() {
    _products = const <ProductModel>[];
    _total = null;
    _pageIndex = 1;
    _lastPage = 1;
    _loading = false;
    _loadingMore = false;
    _error = null;
  }

  void _onChanged(String value) {
    _debounce?.cancel();
    final query = value.trim();
    if (query.length < 2) {
      setState(_resetResults);
      return;
    }
    setState(() {});
    if (widget.returnQuery) return;
    _debounce = Timer(const Duration(milliseconds: 320), () => _runSearch(query));
  }

  Future<void> _runSearch(String rawQuery) async {
    final query = rawQuery.trim();
    if (query.length < 2) return;

    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final page = await _repository.getCatalogPage(
        query: query,
        sort: _sort,
        stock: _inStockOnly ? 'in-stock' : null,
        page: 1,
        perPage: 24,
      );
      if (!mounted || _controller.text.trim() != query) return;
      setState(() {
        _products = page.products;
        _total = page.total;
        _pageIndex = page.currentPage;
        _lastPage = page.lastPage;
      });
      if (_scrollController.hasClients) _scrollController.jumpTo(0);
    } catch (error) {
      if (!mounted || _controller.text.trim() != query) return;
      setState(() => _error = error);
    } finally {
      if (mounted && _controller.text.trim() == query) {
        setState(() => _loading = false);
      }
    }
  }

  Future<void> _loadMore() async {
    final query = _controller.text.trim();
    if (_loading || _loadingMore || _pageIndex >= _lastPage || query.length < 2) return;
    setState(() => _loadingMore = true);
    try {
      final page = await _repository.getCatalogPage(
        query: query,
        sort: _sort,
        stock: _inStockOnly ? 'in-stock' : null,
        page: _pageIndex + 1,
        perPage: 24,
      );
      if (!mounted || _controller.text.trim() != query) return;
      setState(() {
        _products = <ProductModel>[..._products, ...page.products];
        _pageIndex = page.currentPage;
        _lastPage = page.lastPage;
        _total = page.total;
      });
    } catch (_) {
      // La suite sera retentée au prochain défilement.
    } finally {
      if (mounted) setState(() => _loadingMore = false);
    }
  }

  void _onScroll() {
    if (!_scrollController.hasClients) return;
    final position = _scrollController.position;
    if (position.pixels >= position.maxScrollExtent - 420) unawaited(_loadMore());
  }

  /// Bouton « Rechercher » et touche de validation du clavier.
  void _submit([String? value]) {
    final query = (value ?? _controller.text).trim();
    if (widget.returnQuery && query.isEmpty) {
      // Champ vidé depuis le catalogue : on revient à tous les produits.
      Navigator.of(context).pop('');
      return;
    }
    if (query.length < 2) {
      _focusNode.requestFocus();
      return;
    }
    _debounce?.cancel();
    _focusNode.unfocus();
    if (widget.returnQuery) {
      Navigator.of(context).pop(query);
      return;
    }
    unawaited(_runSearch(query));
  }

  void _usePopular(String term) {
    _controller
      ..text = term
      ..selection = TextSelection.collapsed(offset: term.length);
    _submit(term);
  }

  void _clear() {
    _debounce?.cancel();
    _controller.clear();
    setState(_resetResults);
    _focusNode.requestFocus();
  }

  void _openProduct(ProductModel product) {
    _focusNode.unfocus();
    Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => ProductDetailScreen(product: product)));
  }

  void _openCategory(CategoryModel category) {
    _focusNode.unfocus();
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => CategoriesScreen(initialCategorySlug: category.slug, showBackButton: true),
      ),
    );
  }

  void _openAllCategories() {
    _focusNode.unfocus();
    Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => const CategoriesScreen(showBackButton: true)));
  }

  void _toggleFavorite(ProductModel product) {
    if (!SessionStore.instance.isAuthenticated) {
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(const SnackBar(content: Text('Connectez-vous pour ajouter ce produit à vos favoris.')));
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

  Future<void> _setSort(String sort) async {
    if (sort == _sort) return;
    setState(() => _sort = sort);
    await _runSearch(_controller.text);
  }

  /// Fenêtre « Trier les résultats » : tri et disponibilité.
  Future<void> _openSortSheet() async {
    _focusNode.unfocus();
    final choice = await showCatalogSheet<(String, bool)>(
      context,
      title: 'Trier les résultats',
      builder: (context) => Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          for (final (value, label) in _sortChoices) ...[
            const Divider(height: 1, thickness: 1, color: catalogLine),
            _SortRow(
              label: label,
              selected: value == _sort,
              onTap: () => Navigator.of(context).pop((value, _inStockOnly)),
            ),
          ],
          const Divider(height: 1, thickness: 1, color: catalogLine),
          const SizedBox(height: 16),
          const Text(
            'Disponibilité',
            style: TextStyle(color: authNavy, fontSize: 15, fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 10),
          Row(
            children: [
              Expanded(
                child: _WidePill(
                  label: 'Tous les produits',
                  selected: !_inStockOnly,
                  onTap: () => Navigator.of(context).pop((_sort, false)),
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: _WidePill(
                  label: 'En stock',
                  selected: _inStockOnly,
                  onTap: () => Navigator.of(context).pop((_sort, true)),
                ),
              ),
            ],
          ),
          const SizedBox(height: 4),
        ],
      ),
    );

    if (choice == null || !mounted) return;
    final (sort, inStock) = choice;
    if (sort == _sort && inStock == _inStockOnly) return;
    setState(() {
      _sort = sort;
      _inStockOnly = inStock;
    });
    await _runSearch(_controller.text);
  }

  static const List<(String, String)> _sortChoices = <(String, String)>[
    ('popular', 'Pertinence'),
    ('recent', 'Plus récents'),
    ('price_asc', 'Prix croissant'),
    ('price_desc', 'Prix décroissant'),
  ];

  @override
  Widget build(BuildContext context) {
    return OvanieAuthTheme(
      child: Scaffold(
        backgroundColor: Colors.white,
        body: SafeArea(
          bottom: false,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              _SearchBarRow(
                controller: _controller,
                focusNode: _focusNode,
                onBack: () => Navigator.of(context).maybePop(),
                onChanged: _onChanged,
                onSubmitted: _submit,
                onClear: _clear,
                onSearch: _submit,
              ),
              Expanded(child: _buildBody()),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildBody() {
    final query = _controller.text.trim();
    final total = _total;

    if (query.length < 2 || (widget.returnQuery && total == null && !_loading && _error == null)) {
      return _SearchPrompt(
        popular: _popular,
        categories: _categories,
        onPopular: _usePopular,
        onCategory: _openCategory,
        onAllCategories: _openAllCategories,
      );
    }

    if (_error != null && total == null) {
      return ListView(
        padding: const EdgeInsets.fromLTRB(16, 40, 16, 20),
        children: [ApiErrorCard(message: ApiClient.friendlyError(_error!), onRetry: () => _runSearch(query))],
      );
    }

    if (total == null) {
      return const Center(child: CircularProgressIndicator(color: authOrange));
    }

    if (total == 0 && !_loading) {
      return _SearchNoResult(
        query: query,
        inStockOnly: _inStockOnly,
        onShowAll: () {
          setState(() => _inStockOnly = false);
          _runSearch(query);
        },
        onOpenCategories: _openAllCategories,
      );
    }

    return RefreshIndicator(
      color: authOrange,
      onRefresh: () => _runSearch(query),
      child: ListView(
        controller: _scrollController,
        keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.only(bottom: 24),
        children: [
          SizedBox(
            height: 2,
            child: _loading
                ? const LinearProgressIndicator(minHeight: 2, color: authOrange, backgroundColor: Colors.transparent)
                : null,
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 6, 4),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.center,
              children: [
                Expanded(
                  child: Text.rich(
                    TextSpan(
                      text: 'Produits',
                      children: [
                        TextSpan(
                          text: '  ($total résultat${total > 1 ? 's' : ''})',
                          style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w500),
                        ),
                      ],
                    ),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(color: authNavy, fontSize: 21, fontWeight: FontWeight.w800),
                  ),
                ),
                TextButton(
                  onPressed: _openSortSheet,
                  style: TextButton.styleFrom(
                    foregroundColor: authNavy,
                    padding: const EdgeInsets.symmetric(horizontal: 10),
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const Text(
                        'Trier par',
                        style: TextStyle(fontFamily: authFont, fontSize: 12.5, fontWeight: FontWeight.w500),
                      ),
                      const SizedBox(width: 3),
                      Icon(Icons.keyboard_arrow_down_rounded, size: 20, color: _inStockOnly ? authOrange : authNavy),
                    ],
                  ),
                ),
              ],
            ),
          ),
          SizedBox(
            height: 36,
            child: ListView(
              scrollDirection: Axis.horizontal,
              physics: const BouncingScrollPhysics(),
              padding: const EdgeInsets.symmetric(horizontal: 16),
              children: [
                CatalogPill(
                  label: 'Pertinence',
                  white: true,
                  selected: _sort == 'popular',
                  onTap: () => _setSort('popular'),
                ),
                const SizedBox(width: 6),
                CatalogPill(label: 'Récent', white: true, selected: _sort == 'recent', onTap: () => _setSort('recent')),
                const SizedBox(width: 6),
                CatalogPill(
                  label: 'Prix',
                  trailing: Icons.arrow_upward_rounded,
                  white: true,
                  selected: _sort == 'price_asc',
                  onTap: () => _setSort('price_asc'),
                ),
                const SizedBox(width: 6),
                CatalogPill(
                  label: 'Prix',
                  trailing: Icons.arrow_downward_rounded,
                  white: true,
                  selected: _sort == 'price_desc',
                  onTap: () => _setSort('price_desc'),
                ),
              ],
            ),
          ),
          const SizedBox(height: 6),
          for (var i = 0; i < _products.length; i++) ...[
            if (i > 0)
              const Padding(
                padding: EdgeInsets.symmetric(horizontal: 16),
                child: Divider(height: 1, thickness: 1, color: catalogLine),
              ),
            _SearchProductRow(
              product: _products[i],
              onTap: () => _openProduct(_products[i]),
              onFavorite: () => _toggleFavorite(_products[i]),
              onCart: () => _addToCart(_products[i]),
            ),
          ],
          if (_loadingMore)
            const Padding(
              padding: EdgeInsets.only(top: 14),
              child: Center(child: CircularProgressIndicator(strokeWidth: 2, color: authOrange)),
            ),
        ],
      ),
    );
  }
}

/// Ligne du haut : retour, champ de saisie et bouton « Rechercher ».
class _SearchBarRow extends StatelessWidget {
  const _SearchBarRow({
    required this.controller,
    required this.focusNode,
    required this.onBack,
    required this.onChanged,
    required this.onSubmitted,
    required this.onClear,
    required this.onSearch,
  });

  final TextEditingController controller;
  final FocusNode focusNode;
  final VoidCallback onBack;
  final ValueChanged<String> onChanged;
  final ValueChanged<String> onSubmitted;
  final VoidCallback onClear;
  final VoidCallback onSearch;

  @override
  Widget build(BuildContext context) {
    OutlineInputBorder border(Color color) => OutlineInputBorder(
      borderRadius: BorderRadius.circular(9),
      borderSide: BorderSide(color: color),
    );

    return Padding(
      padding: const EdgeInsets.fromLTRB(4, 8, 14, 8),
      child: Row(
        children: [
          IconButton(
            onPressed: onBack,
            tooltip: 'Retour',
            icon: const Icon(Icons.arrow_back_rounded, size: 23, color: authNavy),
          ),
          Expanded(
            child: SizedBox(
              height: 40,
              child: TextField(
                controller: controller,
                focusNode: focusNode,
                onChanged: onChanged,
                onSubmitted: onSubmitted,
                textInputAction: TextInputAction.search,
                textAlignVertical: TextAlignVertical.center,
                cursorColor: authNavy,
                style: const TextStyle(
                  color: authNavy,
                  fontSize: 13.5,
                  fontWeight: FontWeight.w500,
                  fontFamily: authFont,
                ),
                decoration: InputDecoration(
                  hintText: 'Rechercher un produit...',
                  hintStyle: const TextStyle(color: Color(0xFF7A849C), fontSize: 13, fontWeight: FontWeight.w400),
                  isDense: true,
                  filled: true,
                  fillColor: const Color(0xFFF7F8FB),
                  contentPadding: EdgeInsets.zero,
                  prefixIcon: const Icon(Icons.search_rounded, size: 20, color: Color(0xFF6B7591)),
                  prefixIconConstraints: const BoxConstraints(minWidth: 38),
                  suffixIcon: ListenableBuilder(
                    listenable: controller,
                    builder: (context, _) => controller.text.isEmpty
                        ? const SizedBox.shrink()
                        : IconButton(
                            onPressed: onClear,
                            tooltip: 'Effacer',
                            padding: EdgeInsets.zero,
                            icon: const Icon(Icons.cancel_rounded, size: 19, color: Color(0xFF8A93A8)),
                          ),
                  ),
                  suffixIconConstraints: const BoxConstraints(minWidth: 36, minHeight: 36),
                  enabledBorder: border(const Color(0xFFD9DEE8)),
                  focusedBorder: border(const Color(0xFFB9C1D1)),
                ),
              ),
            ),
          ),
          const SizedBox(width: 6),
          SizedBox(
            height: 40,
            child: FilledButton(
              onPressed: onSearch,
              style: FilledButton.styleFrom(
                backgroundColor: authOrange,
                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(horizontal: 12),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
              ),
              child: const Text(
                'Rechercher',
                style: TextStyle(fontFamily: authFont, fontSize: 13, fontWeight: FontWeight.w600),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// Champ vide : invitation, recherches populaires et catégories.
class _SearchPrompt extends StatelessWidget {
  const _SearchPrompt({
    required this.popular,
    required this.categories,
    required this.onPopular,
    required this.onCategory,
    required this.onAllCategories,
  });

  final List<String> popular;
  final List<CategoryModel> categories;
  final ValueChanged<String> onPopular;
  final ValueChanged<CategoryModel> onCategory;
  final VoidCallback onAllCategories;

  @override
  Widget build(BuildContext context) {
    const heading = TextStyle(color: authNavy, fontSize: 16, fontWeight: FontWeight.w800);
    final shown = categories.take(6).toList(growable: false);

    return ListView(
      keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
      padding: const EdgeInsets.fromLTRB(16, 14, 16, 24),
      children: [
        const Text(
          'Que recherchez-vous ?',
          style: TextStyle(color: authNavy, fontSize: 24, height: 1.15, fontWeight: FontWeight.w800),
        ),
        const SizedBox(height: 8),
        const Text(
          'Saisissez au moins deux caractères pour rechercher un produit OVANIE.',
          style: TextStyle(color: catalogMuted, fontSize: 13.5, height: 1.45, fontWeight: FontWeight.w400),
        ),
        if (popular.isNotEmpty) ...[
          const SizedBox(height: 24),
          const Text('Suggestions populaires', style: heading),
          const SizedBox(height: 12),
          Wrap(
            spacing: 7,
            runSpacing: 8,
            children: [for (final term in popular.take(6)) _PopularChip(term: term, onTap: () => onPopular(term))],
          ),
        ],
        if (shown.isNotEmpty) ...[
          const SizedBox(height: 26),
          Row(
            children: [
              const Expanded(child: Text('Catégories', style: heading)),
              InkWell(
                onTap: onAllCategories,
                borderRadius: BorderRadius.circular(6),
                child: const Padding(
                  padding: EdgeInsets.symmetric(horizontal: 4, vertical: 6),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        'Voir tout',
                        style: TextStyle(color: Color(0xFF0A3A9C), fontSize: 12.5, fontWeight: FontWeight.w700),
                      ),
                      SizedBox(width: 2),
                      Icon(Icons.chevron_right_rounded, size: 18, color: Color(0xFF0A3A9C)),
                    ],
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          LayoutBuilder(
            builder: (context, box) {
              // Trois petites cartes par ligne (quatre sur tablette).
              const gap = 8.0;
              final columns = box.maxWidth >= 560 ? 4 : 3;
              final width = (box.maxWidth - gap * (columns - 1)) / columns - .01;
              return Wrap(
                spacing: gap,
                runSpacing: gap,
                children: [
                  for (final category in shown)
                    SizedBox(
                      width: width,
                      child: _PromptCategoryCard(category: category, onTap: () => onCategory(category)),
                    ),
                ],
              );
            },
          ),
        ],
      ],
    );
  }
}

class _PopularChip extends StatelessWidget {
  const _PopularChip({required this.term, required this.onTap});

  final String term;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white,
      shape: const StadiumBorder(side: BorderSide(color: Color(0xFFD9DEE8))),
      child: InkWell(
        onTap: onTap,
        customBorder: const StadiumBorder(),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.trending_up_rounded, size: 17, color: authNavy),
              const SizedBox(width: 8),
              Flexible(
                child: Text(
                  term,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(color: authNavy, fontSize: 12.5, fontWeight: FontWeight.w500),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _PromptCategoryCard extends StatelessWidget {
  const _PromptCategoryCard({required this.category, required this.onTap});

  final CategoryModel category;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(8),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Container(
          foregroundDecoration: BoxDecoration(
            borderRadius: BorderRadius.circular(8),
            border: Border.all(color: const Color(0xFFE4E8EF)),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              AspectRatio(
                aspectRatio: 4 / 3,
                child: CatalogImage(
                  urls: rootCategoryImages(category),
                  placeholder: const ColoredBox(
                    color: catalogSoft,
                    child: Icon(Icons.category_outlined, size: 24, color: Color(0xFFB5BECA)),
                  ),
                ),
              ),
              Padding(
                padding: const EdgeInsets.fromLTRB(7, 6, 6, 7),
                child: SizedBox(
                  height: 28,
                  child: Align(
                    alignment: Alignment.centerLeft,
                    child: Text(
                      category.name,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: authNavy,
                        fontSize: 10.5,
                        height: 1.25,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Aucun produit trouvé : loupe, message et renvoi vers les catégories.
class _SearchNoResult extends StatelessWidget {
  const _SearchNoResult({
    required this.query,
    required this.inStockOnly,
    required this.onShowAll,
    required this.onOpenCategories,
  });

  final String query;
  final bool inStockOnly;
  final VoidCallback onShowAll;
  final VoidCallback onOpenCategories;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, box) => SingleChildScrollView(
        keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
        padding: const EdgeInsets.symmetric(horizontal: 16),
        child: ConstrainedBox(
          constraints: BoxConstraints(minHeight: box.maxHeight),
          child: Align(
            alignment: const Alignment(0, -.35),
            child: Padding(
              padding: const EdgeInsets.symmetric(vertical: 24),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const Icon(Icons.search_rounded, size: 66, color: Color(0xFF7F89A3)),
                  const SizedBox(height: 14),
                  const Text(
                    'Aucun résultat trouvé',
                    textAlign: TextAlign.center,
                    style: TextStyle(color: authNavy, fontSize: 21, fontWeight: FontWeight.w800),
                  ),
                  const SizedBox(height: 10),
                  Text(
                    inStockOnly
                        ? 'Aucun produit en stock ne correspond à « $query »'
                        : 'Nous n’avons trouvé aucun produit correspondant à « $query »',
                    textAlign: TextAlign.center,
                    style: const TextStyle(color: catalogMuted, fontSize: 13.5, height: 1.45),
                  ),
                  if (inStockOnly)
                    TextButton(
                      onPressed: onShowAll,
                      style: TextButton.styleFrom(foregroundColor: authNavy),
                      child: const Text(
                        'Voir tous les produits',
                        style: TextStyle(
                          fontFamily: authFont,
                          fontSize: 13,
                          fontWeight: FontWeight.w600,
                          decoration: TextDecoration.underline,
                        ),
                      ),
                    ),
                  const SizedBox(height: 24),
                  Container(
                    padding: const EdgeInsets.fromLTRB(14, 20, 14, 16),
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(10),
                      border: Border.all(color: const Color(0xFFE4E8EF)),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        const Text(
                          'Vous ne trouvez pas ce que vous cherchez ?',
                          textAlign: TextAlign.center,
                          style: TextStyle(color: authNavy, fontSize: 14, fontWeight: FontWeight.w700),
                        ),
                        const SizedBox(height: 6),
                        const Text(
                          'Parcourez nos catégories pour découvrir nos produits.',
                          textAlign: TextAlign.center,
                          style: TextStyle(color: catalogMuted, fontSize: 13, height: 1.45),
                        ),
                        const SizedBox(height: 14),
                        OvaniePrimaryButton(label: 'Voir les catégories', height: 44, onPressed: onOpenCategories),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

/// Ligne de la fenêtre de tri : bouton radio à gauche, libellé à droite.
class _SortRow extends StatelessWidget {
  const _SortRow({required this.label, required this.selected, required this.onTap});

  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      button: true,
      selected: selected,
      child: InkWell(
        onTap: onTap,
        child: SizedBox(
          height: 42,
          child: Row(
            children: [
              const SizedBox(width: 6),
              Container(
                width: 20,
                height: 20,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  border: Border.all(color: selected ? authNavy : const Color(0xFFC3C9D6), width: selected ? 2 : 1.4),
                ),
                child: selected
                    ? Container(
                        width: 10,
                        height: 10,
                        decoration: const BoxDecoration(color: authNavy, shape: BoxShape.circle),
                      )
                    : null,
              ),
              const SizedBox(width: 16),
              Expanded(
                child: Text(
                  label,
                  style: const TextStyle(color: authNavy, fontSize: 13.5, fontWeight: FontWeight.w500),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Pastille large de la disponibilité.
class _WidePill extends StatelessWidget {
  const _WidePill({required this.label, required this.selected, required this.onTap});

  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      button: true,
      selected: selected,
      child: Material(
        color: selected ? authNavy : Colors.white,
        shape: StadiumBorder(side: BorderSide(color: selected ? authNavy : const Color(0xFFD9DEE8))),
        child: InkWell(
          onTap: onTap,
          customBorder: const StadiumBorder(),
          child: SizedBox(
            height: 40,
            child: Center(
              child: Text(
                label,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  color: selected ? Colors.white : authNavy,
                  fontSize: 12.5,
                  fontWeight: FontWeight.w500,
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

bool _isNew(ProductModel product) {
  return product.tags.any((tag) {
    final value = tag.toLowerCase();
    return value == 'new' || value == 'nouveau' || value == 'new-arrival';
  });
}

/// Produit trouvé : photo à gauche, puis nom, étiquette, note, prix,
/// disponibilité, cœur et bouton « Ajouter ».
class _SearchProductRow extends StatelessWidget {
  const _SearchProductRow({required this.product, required this.onTap, required this.onFavorite, required this.onCart});

  final ProductModel product;
  final VoidCallback onTap;
  final VoidCallback onFavorite;
  final VoidCallback onCart;

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
    final promo = product.hasDiscount;
    final isNew = !promo && _isNew(product);

    return InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(16, 10, 16, 10),
        child: IntrinsicHeight(
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              ClipRRect(
                borderRadius: BorderRadius.circular(8),
                child: SizedBox(
                  width: 112,
                  height: 112,
                  child: ColoredBox(
                    color: const Color(0xFFF1F3F6),
                    child: _RowImage(url: product.imageUrl),
                  ),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Stack(
                  children: [
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Padding(
                          padding: const EdgeInsets.only(right: 30),
                          child: Text(
                            product.name,
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              color: authNavy,
                              fontSize: 14,
                              height: 1.25,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ),
                        if (promo || isNew) ...[
                          const SizedBox(height: 5),
                          _Tag(
                            text: promo ? 'PROMO' : 'NOUVEAU',
                            color: promo ? const Color(0xFFF05A00) : const Color(0xFF0B6BD7),
                            background: promo ? const Color(0xFFFFEBDD) : const Color(0xFFE3F0FF),
                          ),
                        ],
                        if (rating > 0 && product.reviewsCount > 0) ...[
                          const SizedBox(height: 5),
                          Row(
                            children: [
                              const Icon(Icons.star_rounded, size: 15, color: authOrange),
                              const SizedBox(width: 4),
                              Text(
                                rating.toStringAsFixed(1).replaceAll('.', ','),
                                style: const TextStyle(color: authNavy, fontSize: 11.5, fontWeight: FontWeight.w500),
                              ),
                              Text(
                                ' (${product.reviewsCount})',
                                style: const TextStyle(color: catalogMuted, fontSize: 11.5),
                              ),
                            ],
                          ),
                        ],
                        const SizedBox(height: 5),
                        FittedBox(
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
                                      fontSize: 11.5,
                                      fontWeight: FontWeight.w400,
                                    ),
                                  ),
                              ],
                            ),
                            maxLines: 1,
                            style: const TextStyle(color: authNavy, fontSize: 15, fontWeight: FontWeight.w800),
                          ),
                        ),
                        const Spacer(),
                        Padding(
                          padding: const EdgeInsets.only(right: 96),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              if (oldPrice > 0)
                                Text(
                                  formatFcfa(oldPrice),
                                  maxLines: 1,
                                  style: const TextStyle(
                                    color: Color(0xFF7B8296),
                                    fontSize: 11,
                                    decoration: TextDecoration.lineThrough,
                                    decorationColor: Color(0xFF7B8296),
                                  ),
                                ),
                              const SizedBox(height: 3),
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
                                      style: TextStyle(color: stockColor, fontSize: 11, fontWeight: FontWeight.w500),
                                    ),
                                  ),
                                ],
                              ),
                              const SizedBox(height: 2),
                            ],
                          ),
                        ),
                      ],
                    ),
                    Positioned(
                      right: -8,
                      top: -8,
                      child: AnimatedBuilder(
                        animation: Listenable.merge([SessionStore.instance, FavoritesStore.instance]),
                        builder: (context, _) {
                          final favorite =
                              SessionStore.instance.isAuthenticated && FavoritesStore.instance.contains(product);
                          return IconButton(
                            onPressed: onFavorite,
                            tooltip: favorite ? 'Retirer des favoris' : 'Ajouter aux favoris',
                            icon: Icon(
                              favorite ? Icons.favorite_rounded : Icons.favorite_border_rounded,
                              size: 22,
                              color: favorite ? authOrange : authNavy,
                            ),
                          );
                        },
                      ),
                    ),
                    Positioned(
                      right: 0,
                      bottom: 0,
                      child: Material(
                        color: canBuy ? authOrange : const Color(0xFFCBD2DC),
                        borderRadius: BorderRadius.circular(7),
                        child: InkWell(
                          onTap: canBuy ? onCart : null,
                          borderRadius: BorderRadius.circular(7),
                          child: Container(
                            height: 32,
                            padding: const EdgeInsets.symmetric(horizontal: 10),
                            child: Row(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                const Icon(Icons.shopping_cart_outlined, color: Colors.white, size: 16),
                                const SizedBox(width: 6),
                                Text(
                                  canBuy ? 'Ajouter' : 'Indisponible',
                                  style: const TextStyle(
                                    color: Colors.white,
                                    fontSize: 12,
                                    fontWeight: FontWeight.w600,
                                  ),
                                ),
                              ],
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

class _Tag extends StatelessWidget {
  const _Tag({required this.text, required this.color, required this.background});

  final String text;
  final Color color;
  final Color background;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
      decoration: BoxDecoration(color: background, borderRadius: BorderRadius.circular(4)),
      child: Text(
        text,
        style: TextStyle(color: color, fontSize: 9.5, letterSpacing: .2, fontWeight: FontWeight.w700),
      ),
    );
  }
}

class _RowImage extends StatelessWidget {
  const _RowImage({required this.url});

  final String url;

  @override
  Widget build(BuildContext context) {
    const placeholder = Center(child: Icon(Icons.inventory_2_outlined, size: 34, color: Color(0xFFB5BECA)));
    if (url.trim().isEmpty) return placeholder;

    return Padding(
      padding: const EdgeInsets.all(5),
      child: Image.network(
        url,
        fit: BoxFit.contain,
        excludeFromSemantics: true,
        errorBuilder: (_, __, ___) => placeholder,
      ),
    );
  }
}
