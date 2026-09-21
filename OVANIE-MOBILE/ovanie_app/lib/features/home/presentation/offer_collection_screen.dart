import 'dart:async';

import 'package:flutter/material.dart';

import '../../../app/theme.dart';
import '../../../core/network/api_client.dart';
import '../../../core/utils/formatters.dart';
import '../../../shared/widgets/api_error_card.dart';
import '../../../shared/widgets/ovanie_bottom_navigation.dart';
import '../../shell/domain/main_navigation_store.dart';
import '../../auth/domain/session_store.dart';
import '../../cart/domain/cart_store.dart';
import '../../categories/domain/category_model.dart';
import '../../favorites/domain/favorites_store.dart';
import '../../products/domain/product_model.dart';
import '../../products/presentation/product_detail_screen.dart';
import '../data/marketplace_repository.dart';

enum OvanieOfferKind { flash, bestSellers, latest, blackFriday }

class OvanieOfferCollectionScreen extends StatefulWidget {
  final OvanieOfferKind kind;
  final String? initialOffer;

  const OvanieOfferCollectionScreen({
    super.key,
    required this.kind,
    this.initialOffer,
  });

  @override
  State<OvanieOfferCollectionScreen> createState() =>
      _OvanieOfferCollectionScreenState();
}

class _OvanieOfferCollectionScreenState
    extends State<OvanieOfferCollectionScreen> {
  final MarketplaceRepository _repository = const MarketplaceRepository();
  final TextEditingController _searchController = TextEditingController();

  List<ProductModel> _products = const [];
  List<CategoryModel> _categories = const [];
  bool _loading = true;
  bool _loadingMore = false;
  Object? _error;
  int _page = 1;
  int _lastPage = 1;
  int _total = 0;
  String? _categorySlug;
  String _sort = 'popular';
  DateTime? _flashEndsAtUtc;
  Duration _serverOffset = Duration.zero;
  int _blackFridayMaxDiscount = 0;
  Timer? _searchDebounce;

  int get _visibleMaxDiscount {
    var maxValue = 0;
    for (final product in _products) {
      if (product.discountPercent > maxValue) {
        maxValue = product.discountPercent;
      }
    }
    return maxValue;
  }

  String get _offer {
    if (widget.initialOffer != null && widget.initialOffer!.trim().isNotEmpty) {
      return widget.initialOffer!.trim();
    }
    switch (widget.kind) {
      case OvanieOfferKind.flash:
        return 'vente-flash';
      case OvanieOfferKind.bestSellers:
        return 'top';
      case OvanieOfferKind.latest:
        return 'new';
      case OvanieOfferKind.blackFriday:
        return 'black-friday';
    }
  }

  String get _title {
    switch (widget.kind) {
      case OvanieOfferKind.flash:
        return 'Vente flash';
      case OvanieOfferKind.bestSellers:
        return 'Meilleures ventes';
      case OvanieOfferKind.latest:
        return 'Nouveautés';
      case OvanieOfferKind.blackFriday:
        return 'Black Friday';
    }
  }

  @override
  void initState() {
    super.initState();
    _load(reset: true);
  }

  @override
  void dispose() {
    _searchDebounce?.cancel();
    _searchController.dispose();
    super.dispose();
  }

  Future<void> _load({required bool reset}) async {
    if (reset) {
      setState(() {
        _loading = true;
        _error = null;
        _page = 1;
      });
    } else {
      if (_loadingMore || _page >= _lastPage) return;
      setState(() => _loadingMore = true);
      _page += 1;
    }

    try {
      HomeMarketplaceData? home;
      if (reset) {
        try {
          home = await _repository.getHomeData();
          _categories = home.categories.take(8).toList(growable: false);
          _flashEndsAtUtc = home.flashSaleEndsAtUtc;
          _serverOffset = home.serverClockOffset;
          _blackFridayMaxDiscount = home.blackFridayMaxDiscountPercent;
        } catch (_) {
          // La page produits reste utilisable même si le payload Home échoue.
        }
      }

      final page = await _repository.getCatalogPage(
        offer: _offer,
        categorySlug: _categorySlug,
        query: _searchController.text.trim(),
        sort: widget.kind == OvanieOfferKind.latest && _sort == 'popular'
            ? 'recent'
            : _sort,
        page: _page,
        perPage: 48,
      );

      if (!mounted) return;
      setState(() {
        _products = reset ? page.products : [..._products, ...page.products];
        _page = page.currentPage;
        _lastPage = page.lastPage;
        _total = page.total;
        _error = null;
      });
    } catch (error) {
      if (!mounted) return;
      if (!reset && _page > 1) _page -= 1;
      setState(() => _error = error);
    } finally {
      if (!mounted) return;
      setState(() {
        _loading = false;
        _loadingMore = false;
      });
    }
  }

  void _onSearchChanged(String _) {
    _searchDebounce?.cancel();
    _searchDebounce = Timer(const Duration(milliseconds: 450), () {
      if (mounted) _load(reset: true);
    });
  }

  Future<void> _selectSort() async {
    final selected = await showModalBottomSheet<String>(
      context: context,
      showDragHandle: true,
      backgroundColor: Colors.white,
      builder: (context) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(18, 0, 18, 18),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Trier les produits',
                style: TextStyle(fontSize: 19, fontWeight: FontWeight.w900),
              ),
              const SizedBox(height: 10),
              _SortTile(
                label: 'Pertinence',
                value: 'popular',
                selected: _sort,
              ),
              _SortTile(
                label: 'Prix croissant',
                value: 'price_asc',
                selected: _sort,
              ),
              _SortTile(
                label: 'Prix décroissant',
                value: 'price_desc',
                selected: _sort,
              ),
              _SortTile(
                label: 'Plus récents',
                value: 'recent',
                selected: _sort,
              ),
            ],
          ),
        ),
      ),
    );
    if (selected == null || selected == _sort || !mounted) return;
    setState(() => _sort = selected);
    _load(reset: true);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      body: SafeArea(
        bottom: false,
        child: Column(
          children: [
            _OfferTopBar(title: _title),
            Expanded(
              child: RefreshIndicator(
                color: OvanieColors.orange,
                onRefresh: () => _load(reset: true),
                child: _buildContent(),
              ),
            ),
          ],
        ),
      ),
      bottomNavigationBar: const OvanieBottomNavigation(
        selectedTab: OvanieMainTab.home,
      ),
    );
  }

  Widget _buildContent() {
    if (_loading) {
      return ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        children: const [
          SizedBox(height: 260),
          Center(child: CircularProgressIndicator(color: OvanieColors.orange)),
        ],
      );
    }

    if (_error != null && _products.isEmpty) {
      return ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(16),
        children: [
          const SizedBox(height: 120),
          ApiErrorCard(
            message: ApiClient.friendlyError(_error!),
            onRetry: () => _load(reset: true),
          ),
        ],
      );
    }

    return LayoutBuilder(
      builder: (context, constraints) {
        final width = constraints.maxWidth;
        final isPhone = width < 600;
        final columns = widget.kind == OvanieOfferKind.latest
            ? (width >= 760 ? 3 : 2)
            : 2;
        final horizontal = isPhone ? 16.0 : 28.0;
        final gap = isPhone ? 10.0 : 14.0;
        final cardWidth = (width - horizontal * 2 - gap * (columns - 1)) / columns;
        final cardHeight = widget.kind == OvanieOfferKind.latest
            ? (cardWidth * 1.43).clamp(255.0, 365.0)
            : (cardWidth * 1.32).clamp(235.0, 350.0);

        return ListView(
          physics: const AlwaysScrollableScrollPhysics(
            parent: BouncingScrollPhysics(),
          ),
          padding: EdgeInsets.fromLTRB(horizontal, 10, horizontal, 24),
          children: [
            _OfferHero(
              kind: widget.kind,
              maxDiscountPercent: widget.kind == OvanieOfferKind.blackFriday
                  ? _blackFridayMaxDiscount
                  : _visibleMaxDiscount,
              flashEndsAtUtc: _flashEndsAtUtc,
              serverOffset: _serverOffset,
            ),
            const SizedBox(height: 14),
            _OfferSearchField(
              controller: _searchController,
              kind: widget.kind,
              onChanged: _onSearchChanged,
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: _ControlButton(
                    icon: Icons.tune_rounded,
                    label: 'Filtres',
                    onTap: () => _showCategoryFilters(),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: _ControlButton(
                    icon: Icons.swap_vert_rounded,
                    label: 'Trier',
                    onTap: _selectSort,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            if (_categories.isNotEmpty) _buildCategoryChips(),
            const SizedBox(height: 14),
            Row(
              children: [
                Expanded(
                  child: Text(
                    widget.kind == OvanieOfferKind.blackFriday
                        ? 'Offres Black Friday'
                        : '$_total produits${widget.kind == OvanieOfferKind.bestSellers ? ' populaires' : ''}',
                    style: const TextStyle(
                      color: Color(0xFF101A33),
                      fontSize: 16,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ),
                if (widget.kind == OvanieOfferKind.blackFriday)
                  Text(
                    '$_total résultats',
                    style: const TextStyle(
                      color: Color(0xFF556073),
                      fontSize: 12,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
              ],
            ),
            const SizedBox(height: 10),
            if (_products.isEmpty)
              const Padding(
                padding: EdgeInsets.symmetric(vertical: 80),
                child: Center(
                  child: Text(
                    'Aucun produit disponible pour le moment.',
                    style: TextStyle(color: Color(0xFF697386)),
                  ),
                ),
              )
            else
              GridView.builder(
                shrinkWrap: true,
                physics: const NeverScrollableScrollPhysics(),
                itemCount: _products.length,
                gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
                  crossAxisCount: columns,
                  crossAxisSpacing: gap,
                  mainAxisSpacing: gap,
                  mainAxisExtent: cardHeight,
                ),
                itemBuilder: (context, index) => _OfferProductCard(
                  product: _products[index],
                  kind: widget.kind,
                  rank: widget.kind == OvanieOfferKind.bestSellers
                      ? index + 1
                      : null,
                ),
              ),
            if (_page < _lastPage) ...[
              const SizedBox(height: 18),
              SizedBox(
                height: 48,
                child: FilledButton(
                  onPressed: _loadingMore ? null : () => _load(reset: false),
                  style: FilledButton.styleFrom(
                    backgroundColor: OvanieColors.orange,
                    foregroundColor: Colors.white,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                  ),
                  child: _loadingMore
                      ? const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: Colors.white,
                          ),
                        )
                      : const Text(
                          'Charger plus de produits',
                          style: TextStyle(fontWeight: FontWeight.w900),
                        ),
                ),
              ),
            ],
          ],
        );
      },
    );
  }

  Widget _buildCategoryChips() {
    return SizedBox(
      height: 42,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        itemCount: _categories.length + 1,
        separatorBuilder: (_, __) => const SizedBox(width: 8),
        itemBuilder: (context, index) {
          final all = index == 0;
          final category = all ? null : _categories[index - 1];
          final selected = all ? _categorySlug == null : _categorySlug == category!.slug;
          return ChoiceChip(
            selected: selected,
            label: Text(all ? 'Tout' : category!.name),
            onSelected: (_) {
              setState(() => _categorySlug = all ? null : category!.slug);
              _load(reset: true);
            },
            selectedColor: OvanieColors.orange,
            backgroundColor: Colors.white,
            side: BorderSide(
              color: selected ? OvanieColors.orange : const Color(0xFFE1E5EB),
            ),
            labelStyle: TextStyle(
              color: selected ? Colors.white : const Color(0xFF101A33),
              fontWeight: FontWeight.w800,
              fontSize: 11,
            ),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(10),
            ),
          );
        },
      ),
    );
  }

  Future<void> _showCategoryFilters() async {
    if (_categories.isEmpty) return;
    final selected = await showModalBottomSheet<String>(
      context: context,
      showDragHandle: true,
      backgroundColor: Colors.white,
      builder: (context) => SafeArea(
        child: ListView(
          shrinkWrap: true,
          padding: const EdgeInsets.fromLTRB(18, 0, 18, 24),
          children: [
            const Text(
              'Filtrer par catégorie',
              style: TextStyle(fontSize: 19, fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: 8),
            ListTile(
              contentPadding: EdgeInsets.zero,
              title: const Text('Toutes les catégories'),
              trailing: _categorySlug == null
                  ? const Icon(Icons.check_rounded, color: OvanieColors.orange)
                  : null,
              onTap: () => Navigator.pop(context, ''),
            ),
            ..._categories.map(
              (category) => ListTile(
                contentPadding: EdgeInsets.zero,
                title: Text(category.name),
                trailing: _categorySlug == category.slug
                    ? const Icon(Icons.check_rounded, color: OvanieColors.orange)
                    : null,
                onTap: () => Navigator.pop(context, category.slug),
              ),
            ),
          ],
        ),
      ),
    );
    if (!mounted || selected == null) return;
    setState(() => _categorySlug = selected.isEmpty ? null : selected);
    _load(reset: true);
  }
}

class _OfferTopBar extends StatelessWidget {
  final String title;

  const _OfferTopBar({required this.title});

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 62,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 12),
        child: Row(
          children: [
            IconButton(
              onPressed: () => Navigator.maybePop(context),
              icon: const Icon(Icons.arrow_back_rounded, size: 27),
              color: const Color(0xFF101A33),
            ),
            Expanded(
              child: Text(
                title,
                textAlign: TextAlign.center,
                style: const TextStyle(
                  color: Color(0xFF101A33),
                  fontSize: 20,
                  fontWeight: FontWeight.w900,
                ),
              ),
            ),
            IconButton(
              onPressed: () {},
              icon: const Icon(Icons.ios_share_rounded, size: 24),
              color: const Color(0xFF101A33),
            ),
          ],
        ),
      ),
    );
  }
}

class _OfferSearchField extends StatelessWidget {
  final TextEditingController controller;
  final OvanieOfferKind kind;
  final ValueChanged<String> onChanged;

  const _OfferSearchField({
    required this.controller,
    required this.kind,
    required this.onChanged,
  });

  @override
  Widget build(BuildContext context) {
    final hint = switch (kind) {
      OvanieOfferKind.flash => 'Rechercher dans la vente flash...',
      OvanieOfferKind.bestSellers => 'Rechercher parmi les meilleures ventes...',
      OvanieOfferKind.latest => 'Rechercher dans les nouveautés...',
      OvanieOfferKind.blackFriday => 'Rechercher une offre Black Friday...',
    };

    return TextField(
      controller: controller,
      onChanged: onChanged,
      decoration: InputDecoration(
        hintText: hint,
        hintStyle: const TextStyle(color: Color(0xFF7A8494), fontSize: 13),
        prefixIcon: const Icon(Icons.search_rounded, color: Color(0xFF687386)),
        suffixIcon: const Icon(Icons.center_focus_weak_rounded, color: Color(0xFF101A33)),
        filled: true,
        fillColor: Colors.white,
        contentPadding: const EdgeInsets.symmetric(vertical: 14),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(11),
          borderSide: const BorderSide(color: Color(0xFFDDE2E8)),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(11),
          borderSide: const BorderSide(color: OvanieColors.orange),
        ),
      ),
    );
  }
}

class _ControlButton extends StatelessWidget {
  final IconData icon;
  final String label;
  final VoidCallback onTap;

  const _ControlButton({
    required this.icon,
    required this.label,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return OutlinedButton.icon(
      onPressed: onTap,
      icon: Icon(icon, size: 20),
      label: Text(label),
      style: OutlinedButton.styleFrom(
        foregroundColor: const Color(0xFF101A33),
        minimumSize: const Size.fromHeight(48),
        side: const BorderSide(color: Color(0xFFDDE2E8)),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(11)),
        textStyle: const TextStyle(fontWeight: FontWeight.w900),
      ),
    );
  }
}

class _SortTile extends StatelessWidget {
  final String label;
  final String value;
  final String selected;

  const _SortTile({
    required this.label,
    required this.value,
    required this.selected,
  });

  @override
  Widget build(BuildContext context) {
    return ListTile(
      contentPadding: EdgeInsets.zero,
      title: Text(label, style: const TextStyle(fontWeight: FontWeight.w700)),
      trailing: value == selected
          ? const Icon(Icons.check_rounded, color: OvanieColors.orange)
          : null,
      onTap: () => Navigator.pop(context, value),
    );
  }
}

class _OfferHero extends StatelessWidget {
  final OvanieOfferKind kind;
  final int maxDiscountPercent;
  final DateTime? flashEndsAtUtc;
  final Duration serverOffset;

  const _OfferHero({
    required this.kind,
    required this.maxDiscountPercent,
    required this.flashEndsAtUtc,
    required this.serverOffset,
  });

  @override
  Widget build(BuildContext context) {
    if (kind == OvanieOfferKind.latest) {
      return _LatestHero();
    }
    if (kind == OvanieOfferKind.bestSellers) {
      return _BestSellerHero();
    }
    if (kind == OvanieOfferKind.blackFriday) {
      return _BlackFridayHero(maxDiscountPercent: maxDiscountPercent);
    }
    return _FlashHero(
      maxDiscountPercent: maxDiscountPercent,
      endsAtUtc: flashEndsAtUtc,
      serverOffset: serverOffset,
    );
  }
}

class _FlashHero extends StatelessWidget {
  final int maxDiscountPercent;
  final DateTime? endsAtUtc;
  final Duration serverOffset;

  const _FlashHero({
    required this.maxDiscountPercent,
    required this.endsAtUtc,
    required this.serverOffset,
  });

  @override
  Widget build(BuildContext context) {
    final pct = maxDiscountPercent > 0 ? maxDiscountPercent : 0;

    return Container(
      height: 150,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(14),
        gradient: const LinearGradient(
          colors: [Color(0xFF071426), Color(0xFF10243A)],
        ),
      ),
      clipBehavior: Clip.antiAlias,
      child: LayoutBuilder(
        builder: (context, constraints) {
          return Stack(
            fit: StackFit.expand,
            children: [
              Positioned(
                left: constraints.maxWidth * .40,
                top: -34,
                bottom: -34,
                width: constraints.maxWidth * .30,
                child: Transform.rotate(
                  angle: .12,
                  child: const DecoratedBox(
                    decoration: BoxDecoration(
                      gradient: LinearGradient(
                        begin: Alignment.topCenter,
                        end: Alignment.bottomCenter,
                        colors: [Color(0xFFFF2B00), Color(0xFFEF3B11)],
                      ),
                    ),
                  ),
                ),
              ),
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Expanded(
                      flex: 40,
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: const [
                          Text(
                            'VENTE\nFLASH',
                            maxLines: 2,
                            style: TextStyle(
                              color: Colors.white,
                              fontSize: 20,
                              height: .95,
                              fontStyle: FontStyle.italic,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                          SizedBox(height: 8),
                          Text(
                            'Des offres exceptionnelles\nsur une sélection de produits BTP !',
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(
                              color: Colors.white,
                              fontSize: 9.5,
                              height: 1.28,
                              fontWeight: FontWeight.w500,
                            ),
                          ),
                        ],
                      ),
                    ),
                    Expanded(
                      flex: 29,
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          const Text(
                            "Jusqu'à",
                            maxLines: 1,
                            style: TextStyle(
                              color: Colors.white,
                              fontSize: 12,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                          const SizedBox(height: 2),
                          FittedBox(
                            fit: BoxFit.scaleDown,
                            child: Text(
                              pct > 0 ? '-$pct%' : 'PROMO',
                              style: const TextStyle(
                                color: Colors.white,
                                fontSize: 32,
                                height: .95,
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                    Expanded(
                      flex: 31,
                      child: _CountdownMini(
                        endsAtUtc: endsAtUtc,
                        serverOffset: serverOffset,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          );
        },
      ),
    );
  }
}

class _BestSellerHero extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Container(
      height: 112,
      padding: const EdgeInsets.symmetric(horizontal: 18),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(14),
        gradient: const LinearGradient(
          colors: [Color(0xFF071A32), Color(0xFF0D2A4B)],
        ),
      ),
      child: Row(
        children: [
          Container(
            width: 70,
            height: 70,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              border: Border.all(color: OvanieColors.orange, width: 3),
            ),
            child: const Icon(Icons.emoji_events_rounded, color: Color(0xFFFFB000), size: 38),
          ),
          const SizedBox(width: 16),
          const Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Text.rich(
                  TextSpan(
                    style: TextStyle(fontSize: 20, fontWeight: FontWeight.w900),
                    children: [
                      TextSpan(text: 'MEILLEURES ', style: TextStyle(color: Colors.white)),
                      TextSpan(text: 'VENTES', style: TextStyle(color: OvanieColors.orange)),
                    ],
                  ),
                ),
                SizedBox(height: 5),
                Text(
                  'Les produits les plus achetés par nos clients',
                  style: TextStyle(color: Colors.white, fontSize: 11.5),
                ),
              ],
            ),
          ),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
            decoration: BoxDecoration(
              color: OvanieColors.orange,
              borderRadius: BorderRadius.circular(10),
            ),
            child: const Row(
              children: [
                Icon(Icons.star_rounded, color: Colors.white, size: 18),
                SizedBox(width: 4),
                Text('Top produits', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w900, fontSize: 10)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _LatestHero extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Container(
      height: 168,
      padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 14),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(14),
        gradient: const LinearGradient(
          colors: [Color(0xFF0B6E3A), Color(0xFF13834B)],
        ),
      ),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisAlignment: MainAxisAlignment.center,
              children: const [
                DecoratedBox(
                  decoration: BoxDecoration(
                    color: Color(0xFF10A654),
                    borderRadius: BorderRadius.all(Radius.circular(8)),
                  ),
                  child: Padding(
                    padding: EdgeInsets.symmetric(horizontal: 9, vertical: 5),
                    child: Text(
                      'NOUVEAU',
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: 9.5,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ),
                ),
                SizedBox(height: 11),
                Text(
                  'Les dernières\nnouveautés BTP',
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: Colors.white,
                    fontSize: 18,
                    height: 1.05,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                SizedBox(height: 7),
                Text(
                  'Soyez les premiers à découvrir\nnos nouveaux produits',
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: Colors.white,
                    fontSize: 10.5,
                    height: 1.28,
                    fontWeight: FontWeight.w500,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 10),
          SizedBox(
            width: 126,
            child: Stack(
              alignment: Alignment.center,
              children: [
                Container(
                  width: 118,
                  height: 96,
                  decoration: BoxDecoration(
                    color: const Color(0x33FFFFFF),
                    borderRadius: BorderRadius.circular(22),
                  ),
                ),
                const Icon(
                  Icons.inventory_2_rounded,
                  color: Color(0xFFFFD28A),
                  size: 70,
                ),
                const Positioned(
                  right: 3,
                  top: 15,
                  child: Icon(
                    Icons.handyman_rounded,
                    color: Colors.white,
                    size: 31,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _BlackFridayHero extends StatelessWidget {
  final int maxDiscountPercent;

  const _BlackFridayHero({required this.maxDiscountPercent});

  @override
  Widget build(BuildContext context) {
    return AspectRatio(
      aspectRatio: 2048 / 683,
      child: LayoutBuilder(
        builder: (context, constraints) {
          final width = constraints.maxWidth;
          final height = constraints.maxHeight;

          return ClipRRect(
            borderRadius: BorderRadius.circular(14),
            child: Stack(
              fit: StackFit.expand,
              children: [
                Image.asset(
                  'assets/images/black_friday.png',
                  fit: BoxFit.fill,
                  alignment: Alignment.center,
                ),
                Positioned(
                  left: 18,
                  top: height * .15,
                  width: width * .31,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: const [
                      Text(
                        'BLACK',
                        style: TextStyle(
                          color: Colors.white,
                          fontSize: 25,
                          height: .9,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      Text(
                        'FRIDAY',
                        style: TextStyle(
                          color: OvanieColors.orange,
                          fontSize: 25,
                          height: 1,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      SizedBox(height: 8),
                      Text(
                        "Les meilleures offres de l'année\nsur une sélection de produits BTP",
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                          color: Colors.white,
                          fontSize: 9.5,
                          height: 1.25,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ],
                  ),
                ),
                Positioned(
                  left: width * .345,
                  top: height * .255,
                  width: width * .205,
                  height: height * .37,
                  child: _BlackFridayHeroDiscount(
                    percent: maxDiscountPercent,
                  ),
                ),
              ],
            ),
          );
        },
      ),
    );
  }
}

class _BlackFridayHeroDiscount extends StatelessWidget {
  final int percent;

  const _BlackFridayHeroDiscount({required this.percent});

  @override
  Widget build(BuildContext context) {
    final hasDiscount = percent > 0;

    return Transform.rotate(
      angle: .09,
      child: FittedBox(
        fit: BoxFit.scaleDown,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              hasDiscount ? "Jusqu'à" : 'OFFRES',
              textAlign: TextAlign.center,
              style: const TextStyle(
                color: Colors.white,
                fontSize: 12,
                height: 1,
                fontWeight: FontWeight.w800,
              ),
            ),
            const SizedBox(height: 2),
            Text(
              hasDiscount ? '-$percent%' : 'BTP',
              textAlign: TextAlign.center,
              style: const TextStyle(
                color: OvanieColors.orange,
                fontSize: 31,
                height: .92,
                fontWeight: FontWeight.w900,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _CountdownMini extends StatefulWidget {
  final DateTime? endsAtUtc;
  final Duration serverOffset;

  const _CountdownMini({required this.endsAtUtc, required this.serverOffset});

  @override
  State<_CountdownMini> createState() => _CountdownMiniState();
}

class _CountdownMiniState extends State<_CountdownMini> {
  Timer? _timer;
  Duration _remaining = Duration.zero;

  @override
  void initState() {
    super.initState();
    _tick();
    _timer = Timer.periodic(const Duration(seconds: 1), (_) => _tick());
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  void _tick() {
    final end = widget.endsAtUtc;
    if (end == null) return;
    final now = DateTime.now().toUtc().add(widget.serverOffset);
    final next = end.difference(now);
    if (!mounted) return;
    setState(() => _remaining = next.isNegative ? Duration.zero : next);
  }

  @override
  Widget build(BuildContext context) {
    final hours = _remaining.inHours.toString().padLeft(2, '0');
    final minutes = (_remaining.inMinutes % 60).toString().padLeft(2, '0');
    final seconds = (_remaining.inSeconds % 60).toString().padLeft(2, '0');
    return Column(
      mainAxisAlignment: MainAxisAlignment.center,
      crossAxisAlignment: CrossAxisAlignment.end,
      children: [
        const Text('Se termine dans', style: TextStyle(color: Colors.white, fontSize: 9.5, fontWeight: FontWeight.w700)),
        const SizedBox(height: 8),
        FittedBox(
          fit: BoxFit.scaleDown,
          alignment: Alignment.centerRight,
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              _TimeBox(hours),
              const Text(' : ', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w900)),
              _TimeBox(minutes),
              const Text(' : ', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w900)),
              _TimeBox(seconds),
            ],
          ),
        ),
      ],
    );
  }
}

class _TimeBox extends StatelessWidget {
  final String value;

  const _TimeBox(this.value);

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 6),
      decoration: BoxDecoration(
        color: const Color(0xFF263346),
        borderRadius: BorderRadius.circular(6),
      ),
      child: Text(value, style: const TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w900)),
    );
  }
}

class _OfferProductCard extends StatelessWidget {
  final ProductModel product;
  final OvanieOfferKind kind;
  final int? rank;

  const _OfferProductCard({
    required this.product,
    required this.kind,
    this.rank,
  });

  void _open(BuildContext context) {
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => ProductDetailScreen(product: product),
      ),
    );
  }

  void _add(BuildContext context) {
    final result = CartStore.instance.add(product);
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          content: Text(result.message),
          duration: const Duration(milliseconds: 1000),
          backgroundColor: result.success ? OvanieColors.navy : OvanieColors.danger,
        ),
      );
  }

  @override
  Widget build(BuildContext context) {
    final discounted = product.discountPercent > 0;
    final showNew = kind == OvanieOfferKind.latest;
    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(13),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: () => _open(context),
        child: Container(
          decoration: BoxDecoration(
            border: Border.all(color: const Color(0xFFE2E6EC)),
            borderRadius: BorderRadius.circular(13),
          ),
          child: Padding(
            padding: const EdgeInsets.all(10),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  flex: 56,
                  child: Stack(
                    fit: StackFit.expand,
                    children: [
                      Padding(
                        padding: const EdgeInsets.all(6),
                        child: _ProductImage(url: product.imageUrl),
                      ),
                      if (discounted && !showNew && kind != OvanieOfferKind.bestSellers)
                        Positioned(
                          left: 0,
                          top: 0,
                          child: Container(
                            padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 4),
                            decoration: BoxDecoration(
                              color: const Color(0xFFE90018),
                              borderRadius: BorderRadius.circular(7),
                              boxShadow: const [
                                BoxShadow(color: Color(0x26000000), blurRadius: 6, offset: Offset(0, 2)),
                              ],
                            ),
                            child: Text(
                              '-${product.discountPercent}%',
                              style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.w900),
                            ),
                          ),
                        ),
                      if (showNew)
                        Positioned(
                          left: 0,
                          top: 0,
                          child: Container(
                            padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 4),
                            decoration: BoxDecoration(
                              color: const Color(0xFF10A654),
                              borderRadius: BorderRadius.circular(7),
                            ),
                            child: const Text('NOUVEAU', style: TextStyle(color: Colors.white, fontSize: 8.8, fontWeight: FontWeight.w900)),
                          ),
                        ),
                      if (rank != null)
                        Positioned(
                          left: 0,
                          top: 0,
                          child: CircleAvatar(
                            radius: 13,
                            backgroundColor: OvanieColors.orange,
                            child: Text('$rank', style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.w900)),
                          ),
                        ),
                      Positioned(
                        right: 0,
                        top: 0,
                        child: AnimatedBuilder(
                          animation: Listenable.merge([FavoritesStore.instance, SessionStore.instance]),
                          builder: (context, _) {
                            final favorite = SessionStore.instance.isAuthenticated && FavoritesStore.instance.contains(product);
                            return GestureDetector(
                              onTap: () {
                                if (!SessionStore.instance.isAuthenticated) {
                                  ScaffoldMessenger.of(context).showSnackBar(
                                    const SnackBar(content: Text('Connectez-vous pour ajouter ce produit aux favoris.')),
                                  );
                                  return;
                                }
                                FavoritesStore.instance.toggle(product);
                              },
                              child: Icon(
                                favorite ? Icons.favorite_rounded : Icons.favorite_border_rounded,
                                color: favorite ? OvanieColors.orange : const Color(0xFF25344B),
                                size: 22,
                              ),
                            );
                          },
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 5),
                Text(
                  product.name,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: Color(0xFF111A2E),
                    fontSize: 12.5,
                    height: 1.15,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                if (product.unitLabel.trim().isNotEmpty) ...[
                  const SizedBox(height: 2),
                  Text(
                    product.unitLabel,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(color: Color(0xFF697386), fontSize: 10),
                  ),
                ],
                const Spacer(),
                Row(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            formatFcfa(product.homeDisplayPrice),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(color: OvanieColors.orange, fontSize: 13.5, fontWeight: FontWeight.w900),
                          ),
                          if (product.homeOriginalPrice > product.homeDisplayPrice)
                            Text(
                              formatFcfa(product.homeOriginalPrice),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                color: Color(0xFF7D8795),
                                fontSize: 9.5,
                                decoration: TextDecoration.lineThrough,
                              ),
                            )
                          else if (kind == OvanieOfferKind.bestSellers || kind == OvanieOfferKind.latest)
                            Row(
                              children: [
                                const Icon(Icons.star_rounded, color: Color(0xFFFFB000), size: 13),
                                const SizedBox(width: 2),
                                Text(
                                  product.rating?.toStringAsFixed(1) ?? '—',
                                  style: const TextStyle(color: Color(0xFF5D6779), fontSize: 9.5),
                                ),
                                Text(
                                  ' (${product.reviewsCount})',
                                  style: const TextStyle(color: Color(0xFF929AA6), fontSize: 8.8),
                                ),
                              ],
                            ),
                        ],
                      ),
                    ),
                    const SizedBox(width: 6),
                    SizedBox(
                      width: 38,
                      height: 38,
                      child: FilledButton(
                        onPressed: product.canAddToCart ? () => _add(context) : null,
                        style: FilledButton.styleFrom(
                          backgroundColor: OvanieColors.orange,
                          foregroundColor: Colors.white,
                          padding: EdgeInsets.zero,
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(9)),
                        ),
                        child: const Icon(Icons.shopping_cart_outlined, size: 20),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _ProductImage extends StatelessWidget {
  final String url;

  const _ProductImage({required this.url});

  @override
  Widget build(BuildContext context) {
    if (url.trim().isEmpty) {
      return const Center(child: Icon(Icons.inventory_2_outlined, size: 46, color: Color(0xFFCBD1DA)));
    }
    return Image.network(
      url,
      fit: BoxFit.contain,
      filterQuality: FilterQuality.medium,
      errorBuilder: (_, __, ___) => const Center(child: Icon(Icons.broken_image_outlined, size: 42, color: Color(0xFFCBD1DA))),
    );
  }
}
