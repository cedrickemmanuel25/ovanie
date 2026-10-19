import 'dart:async';

import 'package:flutter/material.dart';

import '../../../core/network/api_client.dart';
import '../../../core/utils/formatters.dart';
import '../../../shared/widgets/api_error_card.dart';
import '../../../shared/widgets/ovanie_bottom_navigation.dart';
import '../../shell/domain/main_navigation_store.dart';
import '../../shell/presentation/main_navigation_controller.dart';
import '../../auth/domain/session_store.dart';
import '../../auth/presentation/auth_branding.dart';
import '../../cart/domain/cart_store.dart';
import '../../categories/domain/category_model.dart';
import '../../categories/presentation/catalog_widgets.dart';
import '../../favorites/domain/favorites_store.dart';
import '../../products/domain/product_model.dart';
import '../../products/presentation/product_detail_screen.dart';
import '../data/marketplace_repository.dart';

enum OvanieOfferKind { flash, bestSellers, latest, blackFriday, promotions, selection }

/// Pages d'offres de l'accueil : Vente flash, Meilleures ventes, Nouveautés,
/// Black Friday, Offres en cours. Même écran, bannière et cartes selon [kind].

class OvanieOfferCollectionScreen extends StatefulWidget {
  final OvanieOfferKind kind;
  final String? initialOffer;
  final String? initialCategorySlug;
  final String? title;

  const OvanieOfferCollectionScreen({
    super.key,
    required this.kind,
    this.initialOffer,
    this.initialCategorySlug,
    this.title,
  });

  @override
  State<OvanieOfferCollectionScreen> createState() => _OvanieOfferCollectionScreenState();
}

class _OvanieOfferCollectionScreenState extends State<OvanieOfferCollectionScreen> {
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

  String? get _offer {
    if (widget.initialOffer != null && widget.initialOffer!.trim().isNotEmpty) {
      return widget.initialOffer!.trim();
    }
    switch (widget.kind) {
      case OvanieOfferKind.promotions:
        return 'promo';
      case OvanieOfferKind.selection:
        return null;
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
    if (widget.title != null && widget.title!.trim().isNotEmpty) return widget.title!.trim();
    switch (widget.kind) {
      case OvanieOfferKind.promotions:
        return 'Offres en cours';
      case OvanieOfferKind.selection:
        return 'Sélection OVANIE';
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
    _categorySlug = widget.initialCategorySlug;
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
          _categories = categoriesInDisplayOrder(home.categories.take(8).toList(growable: false));
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
        sort: widget.kind == OvanieOfferKind.latest && _sort == 'popular' ? 'recent' : _sort,
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
    final selected = await showCatalogChoiceSheet<String>(
      context,
      title: 'Trier les produits',
      options: const [
        ('popular', 'Pertinence'),
        ('price_asc', 'Prix croissant'),
        ('price_desc', 'Prix décroissant'),
        ('recent', 'Plus récents'),
      ],
      selected: _sort,
    );
    if (selected == null || selected == _sort || !mounted) return;
    setState(() => _sort = selected);
    _load(reset: true);
  }

  void _selectCategory(String? slug) {
    if (slug == _categorySlug) return;
    setState(() => _categorySlug = slug);
    _load(reset: true);
  }

  /// Bouton « Filtres » : choix de la catégorie, avec son visuel.
  Future<void> _showCategoryFilters() async {
    if (_categories.isEmpty) return;
    FocusScope.of(context).unfocus();
    final selected = await showCatalogSheet<String>(
      context,
      title: 'Filtrer par catégorie',
      builder: (context) => Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          CatalogRadioRow(
            label: 'Toutes les catégories',
            selected: _categorySlug == null,
            onTap: () => Navigator.of(context).pop(''),
          ),
          for (final category in _categories) ...[
            const Divider(height: 1, thickness: 1, color: catalogLine),
            CatalogRadioRow(
              label: category.name,
              selected: _categorySlug == category.slug,
              onTap: () => Navigator.of(context).pop(category.slug),
              leading: ClipRRect(
                borderRadius: BorderRadius.circular(5),
                child: SizedBox(
                  width: 70,
                  height: 30,
                  child: CatalogImage(
                    urls: rootCategoryImages(category),
                    placeholder: const ColoredBox(color: catalogSoft),
                  ),
                ),
              ),
            ),
          ],
        ],
      ),
    );
    if (!mounted || selected == null) return;
    _selectCategory(selected.isEmpty ? null : selected);
  }

  void _openCart() {
    MainNavigationController.select(OvanieMainTab.cart);
    Navigator.of(context).popUntil((route) => route.isFirst);
  }

  String get _searchHint => switch (widget.kind) {
    OvanieOfferKind.flash => 'Rechercher dans la vente flash...',
    OvanieOfferKind.bestSellers => 'Rechercher parmi les meilleures ventes...',
    OvanieOfferKind.latest => 'Rechercher dans les nouveautés...',
    OvanieOfferKind.blackFriday => 'Rechercher une offre Black Friday...',
    OvanieOfferKind.promotions => 'Rechercher une offre...',
    OvanieOfferKind.selection => 'Rechercher dans la sélection...',
  };

  /// « 128 résultats », ou « Offres en cours • 128 produits » sur les pages
  /// de promotions.
  String get _countLabel {
    final products = _total == 1 ? '1 produit' : '$_total produits';
    return switch (widget.kind) {
      OvanieOfferKind.blackFriday => 'Offres Black Friday  •  $products',
      OvanieOfferKind.promotions => 'Offres en cours  •  $products',
      _ => _total == 1 ? '1 résultat' : '$_total résultats',
    };
  }

  bool get _showsStockGauge =>
      widget.kind == OvanieOfferKind.flash ||
      widget.kind == OvanieOfferKind.blackFriday ||
      widget.kind == OvanieOfferKind.promotions;

  @override
  Widget build(BuildContext context) {
    return OvanieAuthTheme(
      child: Scaffold(
        backgroundColor: Colors.white,
        bottomNavigationBar: const OvanieBottomNavigation(selectedTab: OvanieMainTab.home),
        body: SafeArea(
          bottom: false,
          child: Column(
            children: [
              _OfferTopBar(title: _title, onOpenCart: _openCart),
              Expanded(
                child: RefreshIndicator(color: authOrange, onRefresh: () => _load(reset: true), child: _buildContent()),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildContent() {
    final firstLoad = _loading && _products.isEmpty;

    return LayoutBuilder(
      builder: (context, constraints) {
        final width = constraints.maxWidth;
        final columns = width >= 900 ? 4 : (width >= 600 ? 3 : 2);
        const side = 10.0;
        const gap = 6.0;
        final tileWidth = (width - side * 2 - gap * (columns - 1)) / columns;

        return ListView(
          keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
          physics: const AlwaysScrollableScrollPhysics(parent: BouncingScrollPhysics()),
          padding: const EdgeInsets.only(bottom: 14),
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(side, 0, side, 6),
              child: _OfferBanner(
                kind: widget.kind,
                title: _title,
                maxDiscountPercent: widget.kind == OvanieOfferKind.blackFriday && _blackFridayMaxDiscount > 0
                    ? _blackFridayMaxDiscount
                    : _visibleMaxDiscount,
                flashEndsAtUtc: _flashEndsAtUtc,
                serverOffset: _serverOffset,
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(side, 0, side, 8),
              child: _OfferSearchField(controller: _searchController, hint: _searchHint, onChanged: _onSearchChanged),
            ),
            if (_categories.isNotEmpty)
              SizedBox(
                height: 34,
                child: ListView(
                  scrollDirection: Axis.horizontal,
                  physics: const BouncingScrollPhysics(),
                  padding: const EdgeInsets.symmetric(horizontal: side),
                  children: [
                    CatalogPill(label: 'Tout', selected: _categorySlug == null, onTap: () => _selectCategory(null)),
                    for (final category in _categories) ...[
                      const SizedBox(width: 6),
                      CatalogPill(
                        label: category.name,
                        selected: _categorySlug == category.slug,
                        onTap: () => _selectCategory(category.slug),
                      ),
                    ],
                  ],
                ),
              ),
            Padding(
              padding: const EdgeInsets.fromLTRB(side + 2, 8, side, 8),
              child: Row(
                children: [
                  Expanded(
                    child: FittedBox(
                      fit: BoxFit.scaleDown,
                      alignment: Alignment.centerLeft,
                      child: Text(
                        firstLoad ? '' : _countLabel,
                        maxLines: 1,
                        style: const TextStyle(color: catalogMuted, fontSize: 12.5, fontWeight: FontWeight.w500),
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  CatalogToolbarButton(icon: Icons.swap_vert_rounded, label: 'Trier', strong: true, onTap: _selectSort),
                  const SizedBox(width: 6),
                  CatalogToolbarButton(
                    icon: Icons.tune_rounded,
                    label: 'Filtres',
                    strong: true,
                    badge: _categorySlug == null ? 0 : 1,
                    onTap: _showCategoryFilters,
                  ),
                ],
              ),
            ),
            if (firstLoad)
              const Padding(
                padding: EdgeInsets.symmetric(vertical: 70),
                child: Center(child: CircularProgressIndicator(color: authOrange)),
              )
            else if (_error != null && _products.isEmpty)
              Padding(
                padding: const EdgeInsets.fromLTRB(14, 20, 14, 0),
                child: ApiErrorCard(message: ApiClient.friendlyError(_error!), onRetry: () => _load(reset: true)),
              )
            else if (_products.isEmpty)
              Padding(
                padding: const EdgeInsets.only(top: 20),
                child: _categorySlug != null || _searchController.text.trim().isNotEmpty
                    ? CatalogNoResult(
                        message: 'Aucun produit ne correspond à votre recherche.',
                        onClear: () {
                          _searchController.clear();
                          setState(() => _categorySlug = null);
                          _load(reset: true);
                        },
                      )
                    : const CatalogEmptyState(
                        icon: Icons.inventory_2_rounded,
                        message: 'Aucun produit disponible pour le moment.',
                      ),
              )
            else
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: side),
                child: GridView.builder(
                  shrinkWrap: true,
                  padding: EdgeInsets.zero,
                  physics: const NeverScrollableScrollPhysics(),
                  itemCount: _products.length,
                  gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
                    crossAxisCount: columns,
                    crossAxisSpacing: gap,
                    mainAxisSpacing: gap,
                    mainAxisExtent: _OfferProductCard.extentFor(tileWidth, gauge: _showsStockGauge),
                  ),
                  itemBuilder: (context, index) => _OfferProductCard(
                    product: _products[index],
                    kind: widget.kind,
                    gauge: _showsStockGauge,
                    rank: widget.kind == OvanieOfferKind.bestSellers && _sort == 'popular' ? index + 1 : null,
                  ),
                ),
              ),
            if (_page < _lastPage && _products.isNotEmpty)
              Padding(
                padding: const EdgeInsets.fromLTRB(side + 3, 8, side + 3, 0),
                child: SizedBox(
                  height: 40,
                  child: OutlinedButton(
                    onPressed: _loadingMore ? null : () => _load(reset: false),
                    style: OutlinedButton.styleFrom(
                      foregroundColor: authNavy,
                      side: const BorderSide(color: authNavy, width: 1.2),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                    ),
                    child: _loadingMore
                        ? const SizedBox(
                            width: 18,
                            height: 18,
                            child: CircularProgressIndicator(strokeWidth: 2, color: authNavy),
                          )
                        : const Text(
                            'Charger plus de produits',
                            style: TextStyle(fontFamily: authFont, fontSize: 13, fontWeight: FontWeight.w700),
                          ),
                  ),
                ),
              ),
          ],
        );
      },
    );
  }
}

class _OfferTopBar extends StatelessWidget {
  const _OfferTopBar({required this.title, required this.onOpenCart});

  final String title;
  final VoidCallback onOpenCart;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 50,
      child: Row(
        children: [
          IconButton(
            onPressed: () => Navigator.maybePop(context),
            tooltip: 'Retour',
            icon: const Icon(Icons.arrow_back_rounded, size: 23, color: authNavy),
          ),
          Expanded(
            child: Text(
              title,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              textAlign: TextAlign.center,
              style: const TextStyle(color: Color(0xFF0A2A6B), fontSize: 16.5, fontWeight: FontWeight.w800),
            ),
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

class _OfferSearchField extends StatelessWidget {
  const _OfferSearchField({required this.controller, required this.hint, required this.onChanged});

  final TextEditingController controller;
  final String hint;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    OutlineInputBorder border(Color color) => OutlineInputBorder(
      borderRadius: BorderRadius.circular(8),
      borderSide: BorderSide(color: color),
    );

    return SizedBox(
      height: 38,
      child: TextField(
        controller: controller,
        onChanged: onChanged,
        textInputAction: TextInputAction.search,
        textAlignVertical: TextAlignVertical.center,
        cursorColor: authNavy,
        style: const TextStyle(color: authNavy, fontSize: 13, fontWeight: FontWeight.w500, fontFamily: authFont),
        decoration: InputDecoration(
          hintText: hint,
          hintStyle: const TextStyle(color: Color(0xFF7A849C), fontSize: 12.5, fontWeight: FontWeight.w400),
          isDense: true,
          filled: true,
          fillColor: const Color(0xFFF3F5F8),
          contentPadding: EdgeInsets.zero,
          prefixIcon: const Icon(Icons.search_rounded, size: 19, color: Color(0xFF6B7591)),
          prefixIconConstraints: const BoxConstraints(minWidth: 38),
          suffixIcon: ListenableBuilder(
            listenable: controller,
            builder: (context, _) => controller.text.isEmpty
                ? const SizedBox.shrink()
                : IconButton(
                    onPressed: () {
                      controller.clear();
                      onChanged('');
                    },
                    tooltip: 'Effacer',
                    padding: EdgeInsets.zero,
                    icon: const Icon(Icons.cancel_rounded, size: 18, color: Color(0xFF8A93A8)),
                  ),
          ),
          suffixIconConstraints: const BoxConstraints(minWidth: 34, minHeight: 34),
          enabledBorder: border(const Color(0xFFE1E5EC)),
          focusedBorder: border(const Color(0xFFB9C1D1)),
        ),
      ),
    );
  }
}

/// Bannière de la page : fond et visuel propres à chaque offre, textes et
/// chiffres (remise maximale, compte à rebours) venus du serveur.
class _OfferBanner extends StatelessWidget {
  const _OfferBanner({
    required this.kind,
    required this.title,
    required this.maxDiscountPercent,
    required this.flashEndsAtUtc,
    required this.serverOffset,
  });

  final OvanieOfferKind kind;
  final String title;
  final int maxDiscountPercent;
  final DateTime? flashEndsAtUtc;
  final Duration serverOffset;

  static const TextStyle _sub = TextStyle(
    color: Colors.white,
    fontSize: 10.5,
    height: 1.3,
    fontWeight: FontWeight.w500,
  );

  Widget _pill(String text, {Color background = Colors.white, Color color = authOrange}) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
    decoration: BoxDecoration(color: background, borderRadius: BorderRadius.circular(99)),
    child: Text(
      text,
      maxLines: 1,
      overflow: TextOverflow.ellipsis,
      style: TextStyle(color: color, fontSize: 10.5, fontWeight: FontWeight.w800),
    ),
  );

  Widget _heading(String text, {double size = 21, Color color = Colors.white, bool italic = false}) => FittedBox(
    fit: BoxFit.scaleDown,
    alignment: Alignment.centerLeft,
    child: Text(
      text,
      style: TextStyle(
        color: color,
        fontSize: size,
        height: 1.02,
        fontWeight: FontWeight.w800,
        fontStyle: italic ? FontStyle.italic : FontStyle.normal,
      ),
    ),
  );

  @override
  Widget build(BuildContext context) {
    final (String art, List<Color> colors) = switch (kind) {
      OvanieOfferKind.flash => ('flash', const [Color(0xFFFD5100), Color(0xFFFF6A00)]),
      OvanieOfferKind.bestSellers => ('best', const [Color(0xFF010920), Color(0xFF0A2A6B)]),
      OvanieOfferKind.latest => ('latest', const [Color(0xFF000C25), Color(0xFF0A2A6B)]),
      OvanieOfferKind.blackFriday => ('blackfriday', const [Color(0xFF010000), Color(0xFF14161A)]),
      OvanieOfferKind.promotions ||
      OvanieOfferKind.selection => ('promos', const [Color(0xFF061F3F), Color(0xFF1A2F46)]),
    };

    final List<Widget> text = switch (kind) {
      OvanieOfferKind.flash => [
        _heading('VENTE FLASH', size: 22),
        const SizedBox(height: 3),
        const Text('Des offres exceptionnelles sur une sélection de produits BTP !', maxLines: 3, style: _sub),
        if (maxDiscountPercent > 0) ...[const SizedBox(height: 6), _pill('Jusqu’à -$maxDiscountPercent %')],
        if (flashEndsAtUtc != null) ...[
          const SizedBox(height: 6),
          _Countdown(endsAtUtc: flashEndsAtUtc!, serverOffset: serverOffset),
        ],
      ],
      OvanieOfferKind.bestSellers => [
        _heading('MEILLEURES\nVENTES'),
        const SizedBox(height: 4),
        const Text('Les produits les plus achetés par nos clients', maxLines: 3, style: _sub),
        const SizedBox(height: 8),
        _pill('Top produits', background: authOrange, color: Colors.white),
      ],
      OvanieOfferKind.latest => [
        _pill('NOUVEAU', background: authOrange, color: Colors.white),
        const SizedBox(height: 7),
        _heading('Les dernières\nnouveautés BTP', size: 18),
        const SizedBox(height: 5),
        const Text('Soyez les premiers à découvrir nos nouveaux produits', maxLines: 3, style: _sub),
      ],
      OvanieOfferKind.blackFriday => [
        _heading('BLACK', size: 25, italic: true),
        _heading('FRIDAY', size: 25, color: authOrange, italic: true),
        const SizedBox(height: 4),
        const Text('Les meilleures offres de l’année sur une sélection de produits BTP', maxLines: 3, style: _sub),
        const SizedBox(height: 6),
        _pill(
          maxDiscountPercent > 0 ? 'Jusqu’à -$maxDiscountPercent % OFFRES BTP' : 'OFFRES BTP',
          background: authOrange,
          color: Colors.white,
        ),
      ],
      OvanieOfferKind.promotions => [
        _heading('OFFRES\nEN COURS', size: 23),
        const SizedBox(height: 4),
        const Text('Découvrez nos meilleures promotions sur une sélection de produits BTP', maxLines: 3, style: _sub),
        const SizedBox(height: 7),
        _pill('Offres du moment', color: const Color(0xFF0A2A6B)),
      ],
      OvanieOfferKind.selection => [
        _heading(title.toUpperCase(), size: 21),
        const SizedBox(height: 4),
        const Text('Des produits choisis par OVANIE pour vos chantiers', maxLines: 3, style: _sub),
      ],
    };

    return ClipRRect(
      borderRadius: BorderRadius.circular(9),
      child: AspectRatio(
        aspectRatio: 803 / 287,
        child: DecoratedBox(
          decoration: BoxDecoration(gradient: LinearGradient(colors: colors)),
          child: Stack(
            fit: StackFit.expand,
            children: [
              Align(
                alignment: Alignment.centerRight,
                child: Image.asset(
                  'assets/images/offers/$art.webp',
                  fit: BoxFit.fitHeight,
                  height: double.infinity,
                  excludeFromSemantics: true,
                ),
              ),
              // Le bloc de texte garde sa largeur ; s'il est trop haut (remise
              // et compte à rebours affichés ensemble), il est réduit en entier.
              LayoutBuilder(
                builder: (context, box) => Align(
                  alignment: Alignment.centerLeft,
                  child: Padding(
                    padding: const EdgeInsets.fromLTRB(12, 7, 0, 7),
                    child: FittedBox(
                      fit: BoxFit.scaleDown,
                      alignment: Alignment.centerLeft,
                      child: SizedBox(
                        width: box.maxWidth * .5,
                        child: Column(
                          mainAxisSize: MainAxisSize.min,
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: text,
                        ),
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

/// Compte à rebours de la vente flash, calé sur l'heure du serveur.
class _Countdown extends StatefulWidget {
  const _Countdown({required this.endsAtUtc, required this.serverOffset});

  final DateTime endsAtUtc;
  final Duration serverOffset;

  @override
  State<_Countdown> createState() => _CountdownState();
}

class _CountdownState extends State<_Countdown> {
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
    final now = DateTime.now().toUtc().add(widget.serverOffset);
    final next = widget.endsAtUtc.difference(now);
    if (!mounted) return;
    setState(() => _remaining = next.isNegative ? Duration.zero : next);
  }

  @override
  Widget build(BuildContext context) {
    String two(int value) => value.toString().padLeft(2, '0');
    final hours = two(_remaining.inHours);
    final minutes = two(_remaining.inMinutes % 60);
    final seconds = two(_remaining.inSeconds % 60);

    Widget box(String value) => Container(
      constraints: const BoxConstraints(minWidth: 27),
      padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 3),
      alignment: Alignment.center,
      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(5)),
      child: Text(
        value,
        style: const TextStyle(color: authOrange, fontSize: 13, height: 1.1, fontWeight: FontWeight.w800),
      ),
    );
    const colon = Padding(
      padding: EdgeInsets.symmetric(horizontal: 4),
      child: Text(
        ':',
        style: TextStyle(color: Colors.white, fontSize: 13, fontWeight: FontWeight.w800),
      ),
    );

    return Semantics(
      label: 'Se termine dans $hours heures $minutes minutes',
      excludeSemantics: true,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          const Text(
            'Se termine dans',
            style: TextStyle(color: Colors.white, fontSize: 9.5, fontWeight: FontWeight.w500),
          ),
          const SizedBox(height: 3),
          Row(mainAxisSize: MainAxisSize.min, children: [box(hours), colon, box(minutes), colon, box(seconds)]),
        ],
      ),
    );
  }
}

/// Carte produit des pages d'offres : remise, rang ou « NOUVEAU » sur la
/// photo, puis nom, conditionnement, prix, jauge de stock et panier.
class _OfferProductCard extends StatelessWidget {
  const _OfferProductCard({required this.product, required this.kind, required this.gauge, this.rank});

  final ProductModel product;
  final OvanieOfferKind kind;

  /// Affiche la jauge « Plus que N » (pages de promotions).
  final bool gauge;
  final int? rank;

  static const double _inset = 4;

  static double extentFor(double width, {required bool gauge}) =>
      _inset + (width - _inset * 2) / (377 / 208) + (gauge ? 120 : 106);

  /// Le stock restant n'est annoncé que lorsqu'il devient faible.
  static const int _lowStock = 20;

  void _open(BuildContext context) {
    Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => ProductDetailScreen(product: product)));
  }

  void _add(BuildContext context) {
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

  /// Ligne sous le nom : conditionnement, sinon poids ou unité de vente.
  String get _detail {
    final packaging = product.packaging.trim();
    if (packaging.isNotEmpty) return packaging;
    if (product.weightKg > 0) return '${ProductModel.compactNumber(product.weightKg)} kg';
    return product.unitLabel.trim();
  }

  Widget? _badge() {
    if (rank != null) {
      return Container(
        constraints: const BoxConstraints(minWidth: 24),
        padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 3),
        alignment: Alignment.center,
        decoration: BoxDecoration(color: authOrange, borderRadius: BorderRadius.circular(5)),
        child: Text(
          '$rank',
          style: const TextStyle(color: Colors.white, fontSize: 11.5, fontWeight: FontWeight.w800),
        ),
      );
    }
    if (kind == OvanieOfferKind.latest) {
      return Container(
        padding: const EdgeInsets.fromLTRB(7, 3, 9, 3),
        decoration: const BoxDecoration(
          color: Color(0xFF0A1B4F),
          borderRadius: BorderRadius.only(
            topLeft: Radius.circular(5),
            topRight: Radius.circular(99),
            bottomRight: Radius.circular(99),
          ),
        ),
        child: const Text(
          'NOUVEAU',
          style: TextStyle(color: Colors.white, fontSize: 9, fontWeight: FontWeight.w700),
        ),
      );
    }
    if (product.discountPercent > 0) {
      return Container(
        padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2.5),
        decoration: BoxDecoration(color: authOrange, borderRadius: BorderRadius.circular(4)),
        child: Text(
          '-${product.discountPercent} %',
          style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.w700),
        ),
      );
    }
    return null;
  }

  @override
  Widget build(BuildContext context) {
    final canBuy = product.canAddToCart && product.stock > 0;
    final (stockLabel, stockColor) = _stock;
    final oldPrice = product.homeOriginalPrice > product.homeDisplayPrice ? product.homeOriginalPrice : 0.0;
    final detail = _detail;
    final badge = _badge();
    final sold = product.sales;
    final low = product.stock > 0 && product.stock <= _lowStock;
    final showGauge = gauge && product.stock > 0 && (sold > 0 || low);
    final fill = sold + product.stock > 0 ? (sold / (sold + product.stock)).clamp(.08, 1.0) : 0.0;

    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(9),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: () => _open(context),
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
                          child: _ProductImage(url: product.imageUrl),
                        ),
                        if (badge != null) Positioned(left: 3, top: 3, child: badge),
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
                height: gauge ? 120 : 106,
                child: Stack(
                  children: [
                    Padding(
                      padding: const EdgeInsets.fromLTRB(7, 6, 7, 7),
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
                              height: 1.25,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                          if (detail.isNotEmpty)
                            Text(
                              detail,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                color: authNavy,
                                fontSize: 11,
                                height: 1.3,
                                fontWeight: FontWeight.w400,
                              ),
                            ),
                          const Spacer(),
                          Padding(
                            padding: EdgeInsets.only(right: gauge ? 0 : 38),
                            child: FittedBox(
                              fit: BoxFit.scaleDown,
                              alignment: Alignment.centerLeft,
                              child: Text.rich(
                                TextSpan(
                                  text: formatFcfa(product.homeDisplayPrice),
                                  children: [
                                    if (oldPrice > 0)
                                      TextSpan(
                                        text: '   ',
                                        style: const TextStyle(fontSize: 6),
                                        children: [
                                          TextSpan(
                                            text: formatFcfa(oldPrice),
                                            style: const TextStyle(
                                              color: Color(0xFF7B8296),
                                              fontSize: 10,
                                              fontWeight: FontWeight.w400,
                                              decoration: TextDecoration.lineThrough,
                                              decorationColor: Color(0xFF7B8296),
                                            ),
                                          ),
                                        ],
                                      ),
                                  ],
                                ),
                                maxLines: 1,
                                style: const TextStyle(color: authOrange, fontSize: 14.5, fontWeight: FontWeight.w800),
                              ),
                            ),
                          ),
                          if (gauge)
                            SizedBox(
                              height: 16,
                              child: showGauge
                                  ? Padding(
                                      padding: const EdgeInsets.only(right: 40),
                                      child: Row(
                                        children: [
                                          Expanded(
                                            child: ClipRRect(
                                              borderRadius: BorderRadius.circular(99),
                                              child: LinearProgressIndicator(
                                                value: fill.toDouble(),
                                                minHeight: 4,
                                                color: authOrange,
                                                backgroundColor: const Color(0xFFE6E9EF),
                                              ),
                                            ),
                                          ),
                                          if (low) ...[
                                            const SizedBox(width: 5),
                                            Text(
                                              'Plus que ${product.stock}',
                                              style: const TextStyle(
                                                color: authOrange,
                                                fontSize: 9,
                                                fontWeight: FontWeight.w500,
                                              ),
                                            ),
                                          ],
                                        ],
                                      ),
                                    )
                                  : null,
                            ),
                          const SizedBox(height: 3),
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
                      right: 6,
                      bottom: 7,
                      child: Material(
                        color: canBuy ? authOrange : const Color(0xFFCBD2DC),
                        borderRadius: BorderRadius.circular(7),
                        child: InkWell(
                          onTap: canBuy ? () => _add(context) : null,
                          borderRadius: BorderRadius.circular(7),
                          child: SizedBox(
                            width: 32,
                            height: 30,
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

class _ProductImage extends StatelessWidget {
  const _ProductImage({required this.url});

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
        filterQuality: FilterQuality.medium,
        errorBuilder: (_, __, ___) => placeholder,
      ),
    );
  }
}
