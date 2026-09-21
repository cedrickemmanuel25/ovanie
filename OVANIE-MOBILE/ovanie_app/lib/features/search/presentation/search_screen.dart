import 'dart:async';

import 'package:flutter/material.dart';

import '../../../app/theme.dart';
import '../../../core/network/api_client.dart';
import '../../../core/utils/formatters.dart';
import '../../../shared/widgets/ovanie_bottom_navigation.dart';
import '../../shell/domain/main_navigation_store.dart';
import '../../account/presentation/account_screen.dart';
import '../../auth/domain/session_store.dart';
import '../../cart/domain/cart_store.dart';
import '../../cart/presentation/cart_screen.dart';
import '../../categories/presentation/categories_screen.dart';
import '../../favorites/domain/favorites_store.dart';
import '../../favorites/presentation/favorites_screen.dart';
import '../../home/data/marketplace_repository.dart';
import '../../products/domain/product_model.dart';
import '../../products/presentation/product_detail_screen.dart';

/// Écran de recherche OVANIE reconstruit selon les maquettes mobiles.
/// Les suggestions et résultats proviennent exclusivement de l'API Laravel.
class SearchScreen extends StatefulWidget {
  final String initialQuery;

  const SearchScreen({
    super.key,
    this.initialQuery = '',
  });

  @override
  State<SearchScreen> createState() => _SearchScreenState();
}

class _SearchScreenState extends State<SearchScreen> {
  final MarketplaceRepository _repository = const MarketplaceRepository();
  late final TextEditingController _controller;
  final FocusNode _focusNode = FocusNode();
  Timer? _debounce;

  SearchSuggestions _suggestions = const SearchSuggestions.empty();
  CatalogPage? _page;
  bool _loading = false;
  Object? _error;
  String _sort = 'popular';
  bool _inStockOnly = false;

  @override
  void initState() {
    super.initState();
    _controller = TextEditingController(text: widget.initialQuery);
    final initial = widget.initialQuery.trim();
    if (initial.length >= 2) {
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
    super.dispose();
  }

  void _onChanged(String value) {
    _debounce?.cancel();
    final query = value.trim();
    if (query.length < 2) {
      setState(() {
        _suggestions = const SearchSuggestions.empty();
        _page = null;
        _loading = false;
        _error = null;
      });
      return;
    }
    setState(() {});
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
      final responses = await Future.wait<dynamic>([
        _repository.getSearchSuggestions(query, limit: 8),
        _repository.getCatalogPage(
          query: query,
          sort: _sort,
          stock: _inStockOnly ? 'in-stock' : null,
          page: 1,
          perPage: 24,
        ),
      ]);
      if (!mounted || _controller.text.trim() != query) return;
      setState(() {
        _suggestions = responses[0] as SearchSuggestions;
        _page = responses[1] as CatalogPage;
      });
    } catch (error) {
      if (!mounted || _controller.text.trim() != query) return;
      setState(() => _error = error);
    } finally {
      if (mounted && _controller.text.trim() == query) {
        setState(() => _loading = false);
      }
    }
  }

  void _clear() {
    _debounce?.cancel();
    _controller.clear();
    setState(() {
      _suggestions = const SearchSuggestions.empty();
      _page = null;
      _loading = false;
      _error = null;
    });
    _focusNode.requestFocus();
  }

  Future<void> _openSuggestion(ProductModel product) async {
    _focusNode.unfocus();
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => ProductDetailScreen(product: product),
      ),
    );
  }

  void _openCategory(SearchCategorySuggestion category) {
    _focusNode.unfocus();
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => CategoriesScreen(
          initialCategorySlug: category.slug,
          showBackButton: true,
        ),
      ),
    );
  }

  void _openAllCategories() {
    _focusNode.unfocus();
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => const CategoriesScreen(showBackButton: true),
      ),
    );
  }

  void _toggleFavorite(ProductModel product) {
    if (!SessionStore.instance.isAuthenticated) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Connectez-vous pour enregistrer ce produit.')),
      );
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
          backgroundColor: result.success ? OvanieColors.navy : OvanieColors.danger,
        ),
      );
  }

  Future<void> _chooseSort() async {
    final selected = await showModalBottomSheet<String>(
      context: context,
      backgroundColor: Colors.white,
      showDragHandle: true,
      builder: (context) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(18, 4, 18, 20),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Trier les résultats',
                style: TextStyle(
                  color: OvanieColors.navy,
                  fontSize: 18,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 10),
              for (final entry in const <String, String>{
                'popular': 'Pertinence',
                'recent': 'Plus récents',
                'price_asc': 'Prix croissant',
                'price_desc': 'Prix décroissant',
              }.entries)
                RadioListTile<String>(
                  value: entry.key,
                  groupValue: _sort,
                  activeColor: OvanieColors.orange,
                  contentPadding: EdgeInsets.zero,
                  title: Text(entry.value),
                  onChanged: (value) => Navigator.of(context).pop(value),
                ),
            ],
          ),
        ),
      ),
    );
    if (selected == null || selected == _sort) return;
    setState(() => _sort = selected);
    await _runSearch(_controller.text);
  }

  Future<void> _chooseFilter() async {
    final selected = await showModalBottomSheet<bool>(
      context: context,
      backgroundColor: Colors.white,
      showDragHandle: true,
      builder: (context) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(18, 4, 18, 22),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Disponibilité',
                style: TextStyle(
                  color: OvanieColors.navy,
                  fontSize: 18,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 10),
              RadioListTile<bool>(
                value: false,
                groupValue: _inStockOnly,
                activeColor: OvanieColors.orange,
                contentPadding: EdgeInsets.zero,
                title: const Text('Tous les produits'),
                onChanged: (value) => Navigator.of(context).pop(value),
              ),
              RadioListTile<bool>(
                value: true,
                groupValue: _inStockOnly,
                activeColor: OvanieColors.orange,
                contentPadding: EdgeInsets.zero,
                title: const Text('En stock'),
                onChanged: (value) => Navigator.of(context).pop(value),
              ),
            ],
          ),
        ),
      ),
    );
    if (selected == null || selected == _inStockOnly) return;
    setState(() => _inStockOnly = selected);
    await _runSearch(_controller.text);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      body: SafeArea(
        bottom: false,
        child: Column(
          children: [
            _SearchHeader(
              onBack: () => Navigator.of(context).maybePop(),
              onFavorites: () => Navigator.of(context).push(
                MaterialPageRoute<void>(builder: (_) => const FavoritesScreen()),
              ),
              onCart: () => Navigator.of(context).push(
                MaterialPageRoute<void>(builder: (_) => const CartScreen()),
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 10),
              child: _SearchField(
                controller: _controller,
                focusNode: _focusNode,
                onChanged: _onChanged,
                onSubmitted: _runSearch,
                onClear: _clear,
              ),
            ),
            Expanded(child: _buildBody()),
          ],
        ),
      ),
      bottomNavigationBar: const OvanieBottomNavigation(
        selectedTab: OvanieMainTab.home,
      ),
    );
  }

  Widget _buildBody() {
    final query = _controller.text.trim();
    if (query.length < 2) {
      return _SearchPrompt(onOpenCategories: _openAllCategories);
    }

    if (_loading && _page == null) {
      return const Center(
        child: CircularProgressIndicator(color: OvanieColors.orange),
      );
    }

    if (_error != null && _page == null) {
      return _SearchError(
        message: ApiClient.friendlyError(_error!),
        onRetry: () => _runSearch(query),
      );
    }

    final page = _page;
    if (page != null && page.total == 0) {
      return _SearchEmptyState(
        query: query,
        onOpenCategories: _openAllCategories,
      );
    }

    final products = page?.products ?? const <ProductModel>[];
    return RefreshIndicator(
      color: OvanieColors.orange,
      onRefresh: () => _runSearch(query),
      child: ListView(
        keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 10, 16, 26),
        children: [
          if (_loading)
            const LinearProgressIndicator(
              minHeight: 2,
              color: OvanieColors.orange,
              backgroundColor: Colors.transparent,
            ),
          if (_error != null) ...[
            const SizedBox(height: 10),
            _InlineError(
              message: ApiClient.friendlyError(_error!),
              onRetry: () => _runSearch(query),
            ),
          ],
          if (products.isNotEmpty) ...[
            const _SearchSectionTitle('Suggestions populaires'),
            const SizedBox(height: 10),
            SizedBox(
              height: 76,
              child: ListView.separated(
                scrollDirection: Axis.horizontal,
                physics: const BouncingScrollPhysics(),
                itemCount: products.take(5).length,
                separatorBuilder: (_, __) => const SizedBox(width: 8),
                itemBuilder: (context, index) {
                  final product = products[index];
                  return _PopularSuggestionCard(
                    product: product,
                    onTap: () => _openSuggestion(product),
                  );
                },
              ),
            ),
            const SizedBox(height: 22),
          ],
          if (_suggestions.categories.isNotEmpty) ...[
            Row(
              children: [
                const Expanded(child: _SearchSectionTitle('Catégories')),
                TextButton(
                  onPressed: _openAllCategories,
                  child: const Text(
                    'Voir tout',
                    style: TextStyle(
                      color: OvanieColors.orange,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 8),
            SizedBox(
              height: 130,
              child: ListView.separated(
                scrollDirection: Axis.horizontal,
                physics: const BouncingScrollPhysics(),
                itemCount: _suggestions.categories.take(6).length,
                separatorBuilder: (_, __) => const SizedBox(width: 10),
                itemBuilder: (context, index) {
                  final category = _suggestions.categories[index];
                  return _SearchCategoryCard(
                    category: category,
                    onTap: () => _openCategory(category),
                  );
                },
              ),
            ),
            const SizedBox(height: 22),
          ],
          Row(
            children: [
              Expanded(
                child: Text.rich(
                  TextSpan(
                    text: 'Produits',
                    style: const TextStyle(
                      color: OvanieColors.navy,
                      fontSize: 17,
                      fontWeight: FontWeight.w900,
                    ),
                    children: [
                      TextSpan(
                        text: ' (${page?.total ?? products.length} résultats)',
                        style: const TextStyle(
                          color: OvanieColors.muted,
                          fontSize: 14,
                          fontWeight: FontWeight.w500,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              const Text(
                'Trier par',
                style: TextStyle(color: OvanieColors.muted, fontSize: 12),
              ),
              TextButton.icon(
                onPressed: _chooseSort,
                icon: const Icon(Icons.keyboard_arrow_down_rounded, size: 18),
                label: Text(
                  _sortLabel(_sort),
                  style: const TextStyle(
                    color: OvanieColors.navy,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
              const SizedBox(width: 2),
              InkWell(
                onTap: _chooseFilter,
                borderRadius: BorderRadius.circular(12),
                child: Container(
                  width: 44,
                  height: 44,
                  decoration: BoxDecoration(
                    color: _inStockOnly ? const Color(0xFFFFF1E8) : Colors.white,
                    border: Border.all(color: const Color(0xFFDDE3EB)),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: const Icon(Icons.tune_rounded, color: OvanieColors.navy),
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          ...products.map(
            (product) => Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: _SearchProductCard(
                product: product,
                onTap: () => _openSuggestion(product),
                onFavorite: () => _toggleFavorite(product),
                onCart: () => _addToCart(product),
              ),
            ),
          ),
          const SizedBox(height: 4),
          _SearchCategoriesCallout(onTap: _openAllCategories),
        ],
      ),
    );
  }
}

String _sortLabel(String value) {
  switch (value) {
    case 'recent':
      return 'Récent';
    case 'price_asc':
      return 'Prix ↑';
    case 'price_desc':
      return 'Prix ↓';
    default:
      return 'Pertinence';
  }
}

class _SearchHeader extends StatelessWidget {
  final VoidCallback onBack;
  final VoidCallback onFavorites;
  final VoidCallback onCart;

  const _SearchHeader({
    required this.onBack,
    required this.onFavorites,
    required this.onCart,
  });

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(8, 6, 10, 4),
      child: Row(
        children: [
          IconButton(
            onPressed: onBack,
            icon: const Icon(Icons.arrow_back_rounded, color: OvanieColors.navy, size: 27),
          ),
          const SizedBox(width: 2),
          const Expanded(
            child: Text(
              'Rechercher',
              style: TextStyle(
                color: OvanieColors.navy,
                fontSize: 20,
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
          IconButton(
            onPressed: onFavorites,
            icon: const Icon(Icons.favorite_border_rounded, color: OvanieColors.navy, size: 27),
          ),
          AnimatedBuilder(
            animation: CartStore.instance,
            builder: (context, _) => _HeaderBadgeButton(
              icon: Icons.shopping_cart_outlined,
              badge: CartStore.instance.itemsCount,
              onTap: onCart,
            ),
          ),
        ],
      ),
    );
  }
}

class _HeaderBadgeButton extends StatelessWidget {
  final IconData icon;
  final int badge;
  final VoidCallback onTap;

  const _HeaderBadgeButton({
    required this.icon,
    required this.badge,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return IconButton(
      onPressed: onTap,
      icon: Stack(
        clipBehavior: Clip.none,
        children: [
          Icon(icon, color: OvanieColors.navy, size: 27),
          if (badge > 0)
            Positioned(
              right: -7,
              top: -7,
              child: Container(
                constraints: const BoxConstraints(minWidth: 18, minHeight: 18),
                padding: const EdgeInsets.symmetric(horizontal: 4),
                alignment: Alignment.center,
                decoration: const BoxDecoration(
                  color: OvanieColors.orange,
                  shape: BoxShape.circle,
                ),
                child: Text(
                  badge > 99 ? '99+' : '$badge',
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 9,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

class _SearchField extends StatelessWidget {
  final TextEditingController controller;
  final FocusNode focusNode;
  final ValueChanged<String> onChanged;
  final ValueChanged<String> onSubmitted;
  final VoidCallback onClear;

  const _SearchField({
    required this.controller,
    required this.focusNode,
    required this.onChanged,
    required this.onSubmitted,
    required this.onClear,
  });

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: controller,
      builder: (context, _) => TextField(
        controller: controller,
        focusNode: focusNode,
        autofocus: false,
        textInputAction: TextInputAction.search,
        onChanged: onChanged,
        onSubmitted: onSubmitted,
        style: const TextStyle(
          color: OvanieColors.navy,
          fontSize: 15,
          fontWeight: FontWeight.w700,
        ),
        decoration: InputDecoration(
          hintText: 'Rechercher un produit...',
          prefixIcon: const Icon(Icons.search_rounded, color: Color(0xFF65748C), size: 25),
          suffixIcon: controller.text.trim().isEmpty
              ? null
              : IconButton(
                  onPressed: onClear,
                  icon: const Icon(Icons.cancel_rounded, color: Color(0xFF7E899C), size: 20),
                ),
          filled: true,
          fillColor: Colors.white,
          contentPadding: const EdgeInsets.symmetric(vertical: 17),
          border: OutlineInputBorder(
            borderRadius: BorderRadius.circular(12),
            borderSide: const BorderSide(color: Color(0xFFD8DEE8)),
          ),
          enabledBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(12),
            borderSide: const BorderSide(color: Color(0xFFD8DEE8)),
          ),
          focusedBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(12),
            borderSide: const BorderSide(color: Color(0xFFAAB6C7), width: 1.2),
          ),
        ),
      ),
    );
  }
}

class _SearchPrompt extends StatelessWidget {
  final VoidCallback onOpenCategories;

  const _SearchPrompt({required this.onOpenCategories});

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.fromLTRB(26, 60, 26, 24),
      children: [
        const _EmptyIllustration(),
        const SizedBox(height: 28),
        const Text(
          'Que recherchez-vous ?',
          textAlign: TextAlign.center,
          style: TextStyle(
            color: OvanieColors.navy,
            fontSize: 24,
            fontWeight: FontWeight.w900,
          ),
        ),
        const SizedBox(height: 10),
        const Text(
          'Saisissez au moins deux caractères pour rechercher un produit OVANIE.',
          textAlign: TextAlign.center,
          style: TextStyle(
            color: OvanieColors.muted,
            fontSize: 14,
            height: 1.45,
          ),
        ),
        const SizedBox(height: 60),
        _SearchCategoriesCallout(onTap: onOpenCategories),
      ],
    );
  }
}

class _SearchEmptyState extends StatelessWidget {
  final String query;
  final VoidCallback onOpenCategories;

  const _SearchEmptyState({
    required this.query,
    required this.onOpenCategories,
  });

  @override
  Widget build(BuildContext context) {
    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(22, 54, 22, 24),
      children: [
        const _EmptyIllustration(),
        const SizedBox(height: 28),
        const Text(
          'Aucun résultat trouvé',
          textAlign: TextAlign.center,
          style: TextStyle(
            color: OvanieColors.navy,
            fontSize: 25,
            fontWeight: FontWeight.w900,
          ),
        ),
        const SizedBox(height: 12),
        Text.rich(
          TextSpan(
            text: 'Nous n’avons trouvé aucun produit\ncorrespondant à « ',
            style: const TextStyle(
              color: OvanieColors.muted,
              fontSize: 15,
              height: 1.5,
              fontWeight: FontWeight.w500,
            ),
            children: [
              TextSpan(
                text: query,
                style: const TextStyle(
                  color: OvanieColors.orange,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const TextSpan(text: ' ».')
            ],
          ),
          textAlign: TextAlign.center,
        ),
        const SizedBox(height: 150),
        _SearchCategoriesCallout(onTap: onOpenCategories),
      ],
    );
  }
}

class _EmptyIllustration extends StatelessWidget {
  const _EmptyIllustration();

  @override
  Widget build(BuildContext context) {
    return Center(
      child: SizedBox(
        width: 245,
        height: 205,
        child: Stack(
          alignment: Alignment.center,
          children: [
            Container(
              width: 210,
              height: 160,
              decoration: BoxDecoration(
                color: const Color(0xFFF2F5FB),
                borderRadius: BorderRadius.circular(80),
              ),
            ),
            Positioned(
              left: 58,
              bottom: 36,
              child: Icon(
                Icons.view_in_ar_outlined,
                size: 92,
                color: const Color(0xFFD9E1EE),
              ),
            ),
            Transform.rotate(
              angle: -.55,
              child: const Icon(
                Icons.search_rounded,
                size: 155,
                color: OvanieColors.navy,
              ),
            ),
            const Positioned(
              right: 19,
              top: 35,
              child: Icon(Icons.auto_awesome_rounded, color: Color(0xFF7A88A4), size: 28),
            ),
            const Positioned(
              left: 22,
              top: 24,
              child: Icon(Icons.close_rounded, color: OvanieColors.navy, size: 18),
            ),
          ],
        ),
      ),
    );
  }
}

class _SearchSectionTitle extends StatelessWidget {
  final String text;

  const _SearchSectionTitle(this.text);

  @override
  Widget build(BuildContext context) {
    return Text(
      text,
      style: const TextStyle(
        color: OvanieColors.navy,
        fontSize: 17,
        fontWeight: FontWeight.w900,
      ),
    );
  }
}

class _PopularSuggestionCard extends StatelessWidget {
  final ProductModel product;
  final VoidCallback onTap;

  const _PopularSuggestionCard({
    required this.product,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(9),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(9),
        child: Container(
          width: 150,
          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
          decoration: BoxDecoration(
            border: Border.all(color: const Color(0xFFE3E7EE)),
            borderRadius: BorderRadius.circular(9),
            boxShadow: const [
              BoxShadow(color: Color(0x09000000), blurRadius: 8, offset: Offset(0, 2)),
            ],
          ),
          child: Row(
            children: [
              SizedBox(
                width: 48,
                height: 60,
                child: _ProductImage(url: product.imageUrl),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  product.name,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: OvanieColors.navy,
                    fontSize: 11.5,
                    height: 1.2,
                    fontWeight: FontWeight.w800,
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

class _SearchCategoryCard extends StatelessWidget {
  final SearchCategorySuggestion category;
  final VoidCallback onTap;

  const _SearchCategoryCard({
    required this.category,
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
          width: 122,
          padding: const EdgeInsets.all(10),
          decoration: BoxDecoration(
            border: Border.all(color: const Color(0xFFE1E6ED)),
            borderRadius: BorderRadius.circular(12),
          ),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Container(
                width: 58,
                height: 58,
                decoration: BoxDecoration(
                  color: const Color(0xFFF6F8FB),
                  borderRadius: BorderRadius.circular(14),
                ),
                child: const Icon(Icons.category_outlined, size: 32, color: Color(0xFF8794A8)),
              ),
              const SizedBox(height: 8),
              Text(
                category.name,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                textAlign: TextAlign.center,
                style: const TextStyle(
                  color: OvanieColors.navy,
                  fontSize: 11.5,
                  height: 1.15,
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

class _SearchProductCard extends StatelessWidget {
  final ProductModel product;
  final VoidCallback onTap;
  final VoidCallback onFavorite;
  final VoidCallback onCart;

  const _SearchProductCard({
    required this.product,
    required this.onTap,
    required this.onFavorite,
    required this.onCart,
  });

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: Listenable.merge([SessionStore.instance, FavoritesStore.instance]),
      builder: (context, _) {
        final favorite = SessionStore.instance.isAuthenticated && FavoritesStore.instance.contains(product);
        return Material(
          color: Colors.white,
          borderRadius: BorderRadius.circular(12),
          child: InkWell(
            onTap: onTap,
            borderRadius: BorderRadius.circular(12),
            child: Container(
              height: 132,
              padding: const EdgeInsets.fromLTRB(10, 10, 8, 10),
              decoration: BoxDecoration(
                border: Border.all(color: const Color(0xFFE2E7EE)),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Row(
                children: [
                  SizedBox(
                    width: 118,
                    child: Stack(
                      children: [
                        Positioned.fill(child: _ProductImage(url: product.imageUrl)),
                        if (product.hasDiscount)
                          Positioned(
                            top: 0,
                            left: 0,
                            child: _Badge(
                              text: '-${product.discountPercent}%',
                              background: const Color(0xFFE40D20),
                            ),
                          ),
                      ],
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        if (!product.hasDiscount && _isNew(product))
                          const Padding(
                            padding: EdgeInsets.only(bottom: 5),
                            child: _Badge(text: 'NOUVEAU', background: Color(0xFF10A94D)),
                          ),
                        Text(
                          product.name,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            color: OvanieColors.navy,
                            fontSize: 14,
                            height: 1.15,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          product.homeSubtitle,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(color: OvanieColors.muted, fontSize: 11.5),
                        ),
                        const Spacer(),
                        Row(
                          children: [
                            const Icon(Icons.star_rounded, color: OvanieColors.warning, size: 16),
                            const SizedBox(width: 3),
                            Text(
                              product.rating?.toStringAsFixed(1) ?? '—',
                              style: const TextStyle(color: OvanieColors.muted, fontSize: 11.5),
                            ),
                            if (product.reviewsCount > 0)
                              Text(
                                ' (${product.reviewsCount})',
                                style: const TextStyle(color: OvanieColors.muted, fontSize: 11.5),
                              ),
                          ],
                        ),
                        const SizedBox(height: 4),
                        Row(
                          children: [
                            Icon(
                              product.stock > 0 ? Icons.check_rounded : Icons.schedule_rounded,
                              color: product.stock > 0 ? const Color(0xFF0AA145) : OvanieColors.muted,
                              size: 15,
                            ),
                            const SizedBox(width: 3),
                            Text(
                              product.availabilityLabel,
                              style: TextStyle(
                                color: product.stock > 0 ? const Color(0xFF0AA145) : OvanieColors.muted,
                                fontSize: 11.5,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(width: 8),
                  SizedBox(
                    width: 104,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.end,
                      children: [
                        IconButton(
                          visualDensity: VisualDensity.compact,
                          padding: EdgeInsets.zero,
                          onPressed: onFavorite,
                          icon: Icon(
                            favorite ? Icons.favorite_rounded : Icons.favorite_border_rounded,
                            color: favorite ? OvanieColors.orange : OvanieColors.navy,
                            size: 24,
                          ),
                        ),
                        const Spacer(),
                        Text(
                          formatFcfa(product.homeDisplayPrice),
                          textAlign: TextAlign.right,
                          maxLines: 1,
                          style: const TextStyle(
                            color: OvanieColors.orange,
                            fontSize: 14,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                        if (product.homeOriginalPrice > 0)
                          Text(
                            formatFcfa(product.homeOriginalPrice),
                            style: const TextStyle(
                              color: OvanieColors.muted,
                              fontSize: 11,
                              decoration: TextDecoration.lineThrough,
                            ),
                          ),
                        const SizedBox(height: 5),
                        SizedBox(
                          width: 44,
                          height: 42,
                          child: FilledButton(
                            onPressed: product.canAddToCart ? onCart : null,
                            style: FilledButton.styleFrom(
                              padding: EdgeInsets.zero,
                              backgroundColor: OvanieColors.orange,
                              disabledBackgroundColor: const Color(0xFFE7EAF0),
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                            ),
                            child: const Icon(Icons.shopping_cart_outlined, size: 21),
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
  }
}

bool _isNew(ProductModel product) {
  return product.tags.any((tag) {
    final value = tag.toLowerCase();
    return value == 'new' || value == 'nouveau' || value == 'new-arrival';
  });
}

class _ProductImage extends StatelessWidget {
  final String url;

  const _ProductImage({required this.url});

  @override
  Widget build(BuildContext context) {
    if (url.trim().isEmpty) {
      return const Center(
        child: Icon(Icons.inventory_2_outlined, size: 42, color: Color(0xFFB4BECC)),
      );
    }
    return Padding(
      padding: const EdgeInsets.all(5),
      child: Image.network(
        url,
        fit: BoxFit.contain,
        errorBuilder: (_, __, ___) => const Center(
          child: Icon(Icons.broken_image_outlined, size: 38, color: Color(0xFFB4BECC)),
        ),
      ),
    );
  }
}

class _Badge extends StatelessWidget {
  final String text;
  final Color background;

  const _Badge({required this.text, required this.background});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 4),
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(7),
      ),
      child: Text(
        text,
        style: const TextStyle(
          color: Colors.white,
          fontSize: 10,
          height: 1,
          fontWeight: FontWeight.w900,
        ),
      ),
    );
  }
}

class _SearchCategoriesCallout extends StatelessWidget {
  final VoidCallback onTap;

  const _SearchCategoriesCallout({required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(14, 14, 12, 14),
      decoration: BoxDecoration(
        color: const Color(0xFFFFF8F4),
        border: Border.all(color: const Color(0xFFF2E6DE)),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        children: [
          Container(
            width: 64,
            height: 62,
            decoration: BoxDecoration(
              color: const Color(0xFFFFEEE4),
              borderRadius: BorderRadius.circular(18),
            ),
            child: const Icon(Icons.shopping_bag_outlined, color: Color(0xFFF29B6B), size: 34),
          ),
          const SizedBox(width: 14),
          const Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Vous ne trouvez pas ce que vous cherchez ?',
                  style: TextStyle(
                    color: OvanieColors.navy,
                    fontSize: 13,
                    height: 1.2,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                SizedBox(height: 5),
                Text(
                  'Parcourez nos catégories pour découvrir nos produits.',
                  style: TextStyle(color: OvanieColors.muted, fontSize: 10.5, height: 1.35),
                ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          OutlinedButton(
            onPressed: onTap,
            style: OutlinedButton.styleFrom(
              foregroundColor: OvanieColors.orange,
              side: const BorderSide(color: OvanieColors.orange),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
            ),
            child: const Text(
              'Voir les catégories',
              style: TextStyle(fontSize: 11, fontWeight: FontWeight.w800),
            ),
          ),
        ],
      ),
    );
  }
}

class _SearchError extends StatelessWidget {
  final String message;
  final VoidCallback onRetry;

  const _SearchError({required this.message, required this.onRetry});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.wifi_off_rounded, size: 48, color: OvanieColors.muted),
            const SizedBox(height: 14),
            Text(message, textAlign: TextAlign.center),
            const SizedBox(height: 14),
            FilledButton(
              onPressed: onRetry,
              style: FilledButton.styleFrom(backgroundColor: OvanieColors.orange),
              child: const Text('Réessayer'),
            ),
          ],
        ),
      ),
    );
  }
}

class _InlineError extends StatelessWidget {
  final String message;
  final VoidCallback onRetry;

  const _InlineError({required this.message, required this.onRetry});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: const Color(0xFFFFF3F2),
        border: Border.all(color: const Color(0xFFFFD3CF)),
        borderRadius: BorderRadius.circular(10),
      ),
      child: Row(
        children: [
          const Icon(Icons.error_outline_rounded, color: OvanieColors.danger),
          const SizedBox(width: 8),
          Expanded(child: Text(message, style: const TextStyle(fontSize: 11.5))),
          TextButton(onPressed: onRetry, child: const Text('Réessayer')),
        ],
      ),
    );
  }
}
