import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../core/config/app_config.dart';
import '../../../core/network/api_client.dart';
import '../../../core/sync/live_sync_service.dart';
import '../../../core/utils/formatters.dart';
import '../../auth/domain/session_store.dart';
import '../../auth/presentation/auth_branding.dart';
import '../../cart/domain/cart_store.dart';
import '../../cart/presentation/cart_screen.dart';
import '../../catalog/presentation/catalog_screen.dart';
import '../../categories/presentation/catalog_widgets.dart';
import '../../favorites/domain/favorites_store.dart';
import '../../home/data/marketplace_repository.dart';
import '../../recent/domain/recently_viewed_store.dart';
import '../domain/product_model.dart';
import 'negotiation_sheet.dart';

const Color _blue = Color(0xFF0B5ED7);
const Color _green = Color(0xFF15934A);
const Color _line = Color(0xFFE1E5EC);
const Color _panel = Color(0xFFF1F3F7);

/// Fiche produit OVANIE reconstruite selon la maquette mobile.
/// Toutes les informations produit, les prix et la disponibilité proviennent
/// du même catalogue Laravel que le Web OVANIE.
class ProductDetailScreen extends StatefulWidget {
  final ProductModel product;

  const ProductDetailScreen({super.key, required this.product});

  @override
  State<ProductDetailScreen> createState() => _ProductDetailScreenState();
}

class _ProductDetailScreenState extends State<ProductDetailScreen> with LiveSyncListener<ProductDetailScreen> {
  final MarketplaceRepository _repository = const MarketplaceRepository();
  final GlobalKey _tabsKey = GlobalKey();
  final ScrollController _similarController = ScrollController();
  final ScrollController _pageController = ScrollController();

  /// Vrai quand la photo est passée sous la barre d'état : une bande blanche
  /// la recouvre alors pour que l'heure et les icônes restent lisibles.
  final ValueNotifier<bool> _coverStatusBar = ValueNotifier<bool>(false);

  late ProductModel _product;
  List<ProductModel> _similar = const <ProductModel>[];
  int _quantity = 1;
  int _galleryIndex = 0;
  int _selectedTab = 0;
  bool _loading = true;
  Object? _error;
  ProductVariantModel? _selectedVariant;

  /// Un ajout au panier a été tenté sans choisir de modèle.
  bool _variantMissing = false;

  @override
  void initState() {
    super.initState();
    _product = widget.product;
    _quantity = _minimumQuantity(_product);
    RecentlyViewedStore.instance.record(_product);
    _pageController.addListener(_onPageScroll);
    _loadDetail();
    // Prix, stock, photos modifiés par le vendeur sur le web.
    listenLiveSync(const {}, () => _loadDetail(silent: true), watch: 'product:${_product.id}');
  }

  @override
  void dispose() {
    _similarController.dispose();
    _pageController.dispose();
    _coverStatusBar.dispose();
    super.dispose();
  }

  void _onPageScroll() {
    if (!_pageController.hasClients) return;
    final width = MediaQuery.sizeOf(context).width;
    _coverStatusBar.value = _pageController.offset > (width * .5).clamp(170.0, 380.0) - 4;
  }

  int _minimumQuantity(ProductModel product) {
    return product.minOrderQuantity <= 0 ? 1 : product.minOrderQuantity;
  }

  Future<void> _loadDetail({bool silent = false}) async {
    if (!silent) {
      setState(() {
        _loading = true;
        _error = null;
      });
    }

    try {
      var fresh = _product;
      if (_product.slug.trim().isNotEmpty) {
        fresh = await _repository.getProductBySlug(_product.slug);
      }

      var similar = <ProductModel>[];
      if (fresh.categorySlug.trim().isNotEmpty) {
        try {
          final page = await _repository.getCatalogPage(categorySlug: fresh.categorySlug, sort: 'popular', perPage: 16);
          similar = page.products.where((item) => item.id != fresh.id).take(8).toList(growable: false);
        } catch (_) {
          similar = <ProductModel>[];
        }
      }

      if (!mounted) return;
      setState(() {
        // Une actualisation silencieuse garde le modèle et la quantité choisis
        // tant qu'ils existent encore.
        final keptId = silent ? _selectedVariant?.id : null;
        _product = fresh;
        _selectedVariant = keptId == null ? null : fresh.variants.where((variant) => variant.id == keptId).firstOrNull;
        if (!silent || _selectedVariant == null && fresh.variants.isNotEmpty) {
          _quantity = _minimumQuantity(fresh);
        }
        _similar = similar;
      });
      RecentlyViewedStore.instance.record(fresh);
    } catch (error) {
      if (!mounted) return;
      setState(() => _error = error);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _toast(String message, {bool error = false}) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          content: Text(
            message,
            textAlign: TextAlign.center,
            style: const TextStyle(fontFamily: authFont, fontSize: 12.5, fontWeight: FontWeight.w500),
          ),
          behavior: SnackBarBehavior.floating,
          backgroundColor: error ? authDanger : const Color(0xFF1B1F2A),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(9)),
          margin: const EdgeInsets.fromLTRB(40, 0, 40, 10),
          duration: const Duration(milliseconds: 2200),
        ),
      );
  }

  void _toggleFavorite() {
    if (!SessionStore.instance.isAuthenticated) {
      _toast('Connectez-vous pour ajouter ce produit à vos favoris.');
      return;
    }
    FavoritesStore.instance.toggle(_product);
  }

  int get _availableStock => _selectedVariant?.stock ?? _product.stock;

  double get _unitPrice => _selectedVariant?.priceOverride ?? _product.homeDisplayPrice;

  void _changeQuantity(int direction) {
    final step = _minimumQuantity(_product);
    final next = _quantity + (direction * step);
    if (next < step) return;
    if (_availableStock > 0 && next > _availableStock) {
      _toast('Stock disponible : $_availableStock.');
      return;
    }
    setState(() => _quantity = next);
  }

  void _selectQuantity(int quantity) {
    final minimum = _minimumQuantity(_product);
    var next = quantity < minimum ? minimum : quantity;
    if (_availableStock > 0 && next > _availableStock) next = _availableStock;
    setState(() => _quantity = next);
  }

  bool get _canBuy =>
      _product.canAddToCart &&
      (_product.variants.isEmpty
          ? _product.stock >= _minimumQuantity(_product)
          : (_selectedVariant?.stock ?? 0) >= _minimumQuantity(_product));

  void _selectVariant(ProductVariantModel variant) {
    setState(() {
      _selectedVariant = variant;
      _variantMissing = false;
      _galleryIndex = 0;
      final minimum = _minimumQuantity(_product);
      _quantity = variant.stock >= minimum ? minimum : variant.stock.clamp(0, minimum).toInt();
    });
  }

  void _addToCart({bool openCart = false}) {
    if (_product.variants.isNotEmpty && _selectedVariant == null) {
      setState(() => _variantMissing = true);
      _toast('Choisissez une couleur ou un modèle avant d’ajouter au panier.');
      return;
    }
    final result = CartStore.instance.addQuantity(_product, _quantity, variant: _selectedVariant);
    if (!result.success) {
      _toast(result.message, error: true);
      return;
    }

    if (openCart) {
      _openCart();
      return;
    }
    _toast(result.message);
  }

  void _openCart() {
    Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => const CartScreen()));
  }

  /// Copie le lien de la page du produit sur le site.
  Future<void> _share() async {
    await Clipboard.setData(ClipboardData(text: '${AppConfig.backendOrigin}/product/${_product.slug}'));
    if (!mounted) return;
    _toast('Lien du produit copié.');
  }

  Future<void> _chooseQuantity() async {
    final minimum = _minimumQuantity(_product);
    final stock = _availableStock;
    final tiers = <int>{
      minimum,
      10,
      30,
      50,
      _quantity,
    }.where((value) => value >= minimum && (stock <= 0 || value <= stock)).toList()..sort();

    final chosen = await showCatalogSheet<int>(
      context,
      title: 'Choisir la quantité',
      builder: (context) => _QuantityChoices(product: _product, tiers: tiers, selected: _quantity),
    );
    if (chosen == null || !mounted) return;
    _selectQuantity(chosen);
  }

  void _showDeliveryOptions() {
    setState(() => _selectedTab = 3);
    final target = _tabsKey.currentContext;
    if (target != null) {
      Scrollable.ensureVisible(target, duration: const Duration(milliseconds: 350), curve: Curves.easeOut);
    }
  }

  void _openSimilar() {
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => CatalogScreen(
          categorySlug: _product.categorySlug,
          categoryName: _product.categoryName,
          showBackButton: true,
        ),
      ),
    );
  }

  List<String> get _images {
    final urls = <String>[];
    void add(String? value) {
      final url = (value ?? '').trim();
      if (url.isNotEmpty && !urls.contains(url)) urls.add(url);
    }

    add(_selectedVariant?.imageUrl);
    add(_product.imageUrl);
    _product.galleryUrls.forEach(add);
    return urls;
  }

  @override
  Widget build(BuildContext context) {
    final product = _product;
    final images = _images;
    final galleryIndex = images.isEmpty ? 0 : _galleryIndex.clamp(0, images.length - 1);
    final negotiable = product.isNegotiable && product.canAddToCart;

    return OvanieAuthTheme(
      child: Scaffold(
        backgroundColor: Colors.white,
        bottomNavigationBar: _ProductActionBar(
          total: _unitPrice * _quantity,
          canBuy: _canBuy || (product.variants.isNotEmpty && _selectedVariant == null && product.canAddToCart),
          onAdd: () => _addToCart(),
          onBuy: () => _addToCart(openCart: true),
        ),
        body: Stack(
          children: [
            _buildPage(product, images, galleryIndex, negotiable),
            ValueListenableBuilder<bool>(
              valueListenable: _coverStatusBar,
              builder: (context, cover, _) => cover
                  ? Container(height: MediaQuery.paddingOf(context).top, color: Colors.white)
                  : const SizedBox.shrink(),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildPage(ProductModel product, List<String> images, int galleryIndex, bool negotiable) {
    return RefreshIndicator(
      color: authOrange,
      onRefresh: _loadDetail,
      child: ListView(
        controller: _pageController,
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.only(bottom: 18),
        children: [
          _Gallery(
            images: images,
            index: galleryIndex,
            product: product,
            loading: _loading,
            onChanged: (index) => setState(() => _galleryIndex = index),
            onBack: () => Navigator.of(context).maybePop(),
            onShare: _share,
            onFavorite: _toggleFavorite,
            onCart: _openCart,
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(12, 8, 12, 0),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                if (_error != null && !_loading)
                  _DetailError(message: ApiClient.friendlyError(_error!), onRetry: _loadDetail),
                _Breadcrumb(product: product),
                const SizedBox(height: 4),
                if (product.brand.trim().isNotEmpty)
                  Text.rich(
                    TextSpan(
                      text: 'Marque : ',
                      children: [
                        TextSpan(
                          text: product.brand.trim(),
                          style: const TextStyle(color: _blue, fontWeight: FontWeight.w600),
                        ),
                      ],
                    ),
                    style: const TextStyle(color: catalogMuted, fontSize: 12),
                  ),
                Text(
                  product.name,
                  style: const TextStyle(color: authNavy, fontSize: 19, height: 1.25, fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 3),
                _RatingLine(product: product),
                const SizedBox(height: 8),
                _PriceBox(
                  product: product,
                  unitPrice: _unitPrice,
                  stock: _availableStock,
                  action: negotiable ? NegotiateButton(product: product, quantity: _quantity) : null,
                ),
                if (product.variants.isNotEmpty) ...[
                  const SizedBox(height: 10),
                  _VariantSelector(
                    variants: product.variants,
                    selected: _selectedVariant,
                    error: _variantMissing,
                    onSelected: _selectVariant,
                  ),
                ],
                const SizedBox(height: 8),
                const Text(
                  'Quantité',
                  style: TextStyle(color: authNavy, fontSize: 13.5, fontWeight: FontWeight.w700),
                ),
                const SizedBox(height: 6),
                _QuantityStepper(
                  product: product,
                  quantity: _quantity,
                  onMinus: () => _changeQuantity(-1),
                  onPlus: () => _changeQuantity(1),
                  onTapValue: _chooseQuantity,
                ),
                const SizedBox(height: 10),
                _FactChips(product: product),
                const SizedBox(height: 8),
                _DeliveryPanel(product: product, onOptions: _showDeliveryOptions),
                const SizedBox(height: 8),
                const _TrustGrid(),
                const SizedBox(height: 10),
                const _VerifiedRow(),
                const SizedBox(height: 12),
                _ProductTabs(
                  key: _tabsKey,
                  selected: _selectedTab,
                  onChanged: (index) => setState(() => _selectedTab = index),
                ),
                const SizedBox(height: 10),
                _TabContent(product: product, selected: _selectedTab),
              ],
            ),
          ),
          if (_similar.isNotEmpty) ...[
            Padding(
              padding: const EdgeInsets.fromLTRB(12, 18, 6, 6),
              child: Row(
                children: [
                  const Expanded(
                    child: Text(
                      'Produits similaires',
                      style: TextStyle(color: authNavy, fontSize: 16, fontWeight: FontWeight.w800),
                    ),
                  ),
                  InkWell(
                    onTap: _openSimilar,
                    borderRadius: BorderRadius.circular(6),
                    child: const Padding(
                      padding: EdgeInsets.symmetric(horizontal: 6, vertical: 6),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Text(
                            'Voir tout',
                            style: TextStyle(color: _blue, fontSize: 12.5, fontWeight: FontWeight.w600),
                          ),
                          Icon(Icons.chevron_right_rounded, size: 18, color: _blue),
                        ],
                      ),
                    ),
                  ),
                ],
              ),
            ),
            SizedBox(
              height: 156,
              child: Stack(
                children: [
                  ListView.separated(
                    controller: _similarController,
                    scrollDirection: Axis.horizontal,
                    physics: const BouncingScrollPhysics(),
                    padding: const EdgeInsets.symmetric(horizontal: 12),
                    itemCount: _similar.length,
                    separatorBuilder: (_, __) => const SizedBox(width: 6),
                    itemBuilder: (context, index) {
                      final item = _similar[index];
                      return _SimilarProductCard(
                        product: item,
                        onTap: () => Navigator.of(
                          context,
                        ).push(MaterialPageRoute<void>(builder: (_) => ProductDetailScreen(product: item))),
                      );
                    },
                  ),
                  if (_similar.length > 2)
                    Positioned(
                      right: 6,
                      top: 44,
                      child: Material(
                        color: Colors.white,
                        shape: const CircleBorder(),
                        elevation: 3,
                        shadowColor: const Color(0x40000000),
                        child: InkWell(
                          customBorder: const CircleBorder(),
                          onTap: () {
                            if (!_similarController.hasClients) return;
                            final position = _similarController.position;
                            _similarController.animateTo(
                              (position.pixels + 244).clamp(0.0, position.maxScrollExtent),
                              duration: const Duration(milliseconds: 300),
                              curve: Curves.easeOut,
                            );
                          },
                          child: const SizedBox(
                            width: 32,
                            height: 32,
                            child: Icon(
                              Icons.chevron_right_rounded,
                              size: 22,
                              color: authNavy,
                              semanticLabel: 'Produits suivants',
                            ),
                          ),
                        ),
                      ),
                    ),
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }
}

/// Grande photo pleine largeur, boutons ronds par-dessus, compteur « 1/4 »
/// et vignettes dessous.
class _Gallery extends StatefulWidget {
  const _Gallery({
    required this.images,
    required this.index,
    required this.product,
    required this.loading,
    required this.onChanged,
    required this.onBack,
    required this.onShare,
    required this.onFavorite,
    required this.onCart,
  });

  final List<String> images;
  final int index;
  final ProductModel product;
  final bool loading;
  final ValueChanged<int> onChanged;
  final VoidCallback onBack;
  final VoidCallback onShare;
  final VoidCallback onFavorite;
  final VoidCallback onCart;

  @override
  State<_Gallery> createState() => _GalleryState();
}

class _GalleryState extends State<_Gallery> {
  late final PageController _pages = PageController(initialPage: widget.index);

  @override
  void didUpdateWidget(covariant _Gallery oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (_pages.hasClients && (_pages.page?.round() ?? widget.index) != widget.index) {
      _pages.jumpToPage(widget.index);
    }
  }

  @override
  void dispose() {
    _pages.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final images = widget.images;
    final top = MediaQuery.paddingOf(context).top;
    final width = MediaQuery.sizeOf(context).width;
    final height = (width * .5).clamp(170.0, 380.0) + top;
    const placeholder = Center(child: Icon(Icons.inventory_2_outlined, size: 60, color: Color(0xFFB5BECA)));

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        SizedBox(
          height: height,
          child: Stack(
            fit: StackFit.expand,
            children: [
              const DecoratedBox(
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    begin: Alignment.topCenter,
                    end: Alignment.bottomCenter,
                    colors: [Color(0xFFE4E7EC), Color(0xFFF0F2F5)],
                  ),
                ),
              ),
              if (images.isEmpty)
                placeholder
              else
                PageView.builder(
                  controller: _pages,
                  itemCount: images.length,
                  onPageChanged: widget.onChanged,
                  itemBuilder: (context, index) => Padding(
                    padding: EdgeInsets.only(top: top * .5),
                    child: Image.network(
                      images[index],
                      fit: BoxFit.contain,
                      semanticLabel: 'Photo ${index + 1} du produit',
                      errorBuilder: (_, __, ___) => placeholder,
                    ),
                  ),
                ),
              if (widget.loading)
                const Align(
                  alignment: Alignment.bottomCenter,
                  child: LinearProgressIndicator(minHeight: 2, color: authOrange, backgroundColor: Colors.transparent),
                ),
              Positioned(
                left: 10,
                top: top + 6,
                child: _RoundButton(icon: Icons.arrow_back_rounded, label: 'Retour', onTap: widget.onBack),
              ),
              Positioned(
                right: 10,
                top: top + 6,
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    _RoundButton(icon: Icons.share_outlined, label: 'Copier le lien', onTap: widget.onShare),
                    const SizedBox(width: 8),
                    AnimatedBuilder(
                      animation: Listenable.merge([SessionStore.instance, FavoritesStore.instance]),
                      builder: (context, _) {
                        final favorite =
                            SessionStore.instance.isAuthenticated && FavoritesStore.instance.contains(widget.product);
                        return _RoundButton(
                          icon: favorite ? Icons.favorite_rounded : Icons.favorite_border_rounded,
                          color: favorite ? authOrange : authNavy,
                          label: favorite ? 'Retirer des favoris' : 'Ajouter aux favoris',
                          onTap: widget.onFavorite,
                        );
                      },
                    ),
                    const SizedBox(width: 8),
                    AnimatedBuilder(
                      animation: CartStore.instance,
                      builder: (context, _) => _RoundButton(
                        icon: Icons.shopping_cart_outlined,
                        label: 'Panier',
                        badge: CartStore.instance.itemsCount,
                        onTap: widget.onCart,
                      ),
                    ),
                  ],
                ),
              ),
              if (images.length > 1)
                Positioned(
                  right: 12,
                  bottom: 10,
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                    decoration: BoxDecoration(color: const Color(0xB3303743), borderRadius: BorderRadius.circular(8)),
                    child: Text(
                      '${widget.index + 1}/${images.length}',
                      style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w600),
                    ),
                  ),
                ),
            ],
          ),
        ),
        if (images.length > 1)
          SizedBox(
            height: 60,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.fromLTRB(12, 5, 12, 3),
              itemCount: images.length,
              separatorBuilder: (_, __) => const SizedBox(width: 6),
              itemBuilder: (context, index) {
                final selected = index == widget.index;
                return Semantics(
                  button: true,
                  selected: selected,
                  label: 'Photo ${index + 1}',
                  child: InkWell(
                    onTap: () => widget.onChanged(index),
                    borderRadius: BorderRadius.circular(7),
                    child: Container(
                      width: 78,
                      foregroundDecoration: BoxDecoration(
                        borderRadius: BorderRadius.circular(7),
                        border: Border.all(color: selected ? authNavy : Colors.transparent, width: 1.6),
                      ),
                      child: ClipRRect(
                        borderRadius: BorderRadius.circular(7),
                        child: ColoredBox(
                          color: const Color(0xFFEDEFF3),
                          child: Image.network(
                            images[index],
                            fit: BoxFit.contain,
                            excludeFromSemantics: true,
                            errorBuilder: (_, __, ___) => const SizedBox.shrink(),
                          ),
                        ),
                      ),
                    ),
                  ),
                );
              },
            ),
          ),
      ],
    );
  }
}

class _RoundButton extends StatelessWidget {
  const _RoundButton({
    required this.icon,
    required this.label,
    required this.onTap,
    this.color = authNavy,
    this.badge = 0,
  });

  final IconData icon;
  final String label;
  final VoidCallback onTap;
  final Color color;
  final int badge;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      button: true,
      label: badge > 0 ? '$label, $badge article${badge > 1 ? 's' : ''}' : label,
      excludeSemantics: true,
      child: Stack(
        clipBehavior: Clip.none,
        children: [
          Material(
            color: Colors.white,
            shape: const CircleBorder(),
            elevation: 1.5,
            shadowColor: const Color(0x33000000),
            child: InkWell(
              customBorder: const CircleBorder(),
              onTap: onTap,
              child: SizedBox(width: 36, height: 36, child: Icon(icon, size: 19, color: color)),
            ),
          ),
          if (badge > 0)
            Positioned(
              right: -3,
              top: -4,
              child: Container(
                constraints: const BoxConstraints(minWidth: 16, minHeight: 16),
                padding: const EdgeInsets.symmetric(horizontal: 3),
                alignment: Alignment.center,
                decoration: BoxDecoration(color: authOrange, borderRadius: BorderRadius.circular(99)),
                child: Text(
                  badge > 99 ? '99+' : '$badge',
                  style: const TextStyle(color: Colors.white, fontSize: 9, height: 1, fontWeight: FontWeight.w800),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

class _Breadcrumb extends StatelessWidget {
  const _Breadcrumb({required this.product});

  final ProductModel product;

  @override
  Widget build(BuildContext context) {
    const style = TextStyle(color: catalogMuted, fontSize: 10.5);
    const chevron = Padding(
      padding: EdgeInsets.symmetric(horizontal: 3),
      child: Icon(Icons.chevron_right_rounded, color: catalogMuted, size: 14),
    );
    return ExcludeSemantics(
      child: Row(
        children: [
          const Text('Accueil', style: style),
          if (product.categoryName.trim().isNotEmpty) ...[
            chevron,
            Flexible(
              child: Text(product.categoryName.trim(), maxLines: 1, overflow: TextOverflow.ellipsis, style: style),
            ),
          ],
        ],
      ),
    );
  }
}

/// Cinq étoiles, avec demi-étoile.
class _Stars extends StatelessWidget {
  const _Stars({required this.rating, this.size = 14});

  final double rating;
  final double size;

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
              size: size,
              color: const Color(0xFFFFB400),
            ),
        ],
      ),
    );
  }
}

String _ratingText(double rating) => rating.toStringAsFixed(1).replaceAll('.', ',');

/// « ★★★★☆ 4,6 (18 avis) · 320 ventes » — uniquement les données réelles.
class _RatingLine extends StatelessWidget {
  const _RatingLine({required this.product});

  final ProductModel product;

  @override
  Widget build(BuildContext context) {
    final rating = product.rating ?? 0;
    final hasReviews = rating > 0 && product.reviewsCount > 0;
    if (!hasReviews && product.sales <= 0) return const SizedBox.shrink();

    final parts = <String>[
      if (hasReviews) '${_ratingText(rating)} (${product.reviewsCount} avis)',
      if (product.sales > 0) '${product.sales} vente${product.sales > 1 ? 's' : ''}',
    ];
    return Row(
      children: [
        if (hasReviews) ...[_Stars(rating: rating), const SizedBox(width: 6)],
        Flexible(
          child: Text(
            parts.join('  ·  '),
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(color: catalogMuted, fontSize: 12),
          ),
        ),
      ],
    );
  }
}

(String, Color) _stockStatus(ProductModel product, int stock) {
  final status = product.availabilityStatus.toLowerCase();
  if (status == 'on_order' || status == 'preorder') return ('Sur commande', _blue);
  if (stock <= 0 || status == 'out_of_stock') return ('Rupture de stock', authDanger);
  if (stock <= 5) return ('Stock limité', const Color(0xFFB4470F));
  return ('En stock', _green);
}

/// Encadré gris : prix par unité, ancien prix et remise, disponibilité,
/// mention « Prix négociable » et bouton Négocier.
class _PriceBox extends StatelessWidget {
  const _PriceBox({required this.product, required this.unitPrice, required this.stock, this.action});

  final ProductModel product;
  final double unitPrice;
  final int stock;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    final unit = product.unitLabel.trim();
    final oldPrice = product.hasDiscount ? product.homeOriginalPrice : 0.0;
    final (stockLabel, stockColor) = _stockStatus(product, stock);

    return Container(
      padding: const EdgeInsets.fromLTRB(11, 8, 9, 9),
      decoration: BoxDecoration(color: _panel, borderRadius: BorderRadius.circular(8)),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                FittedBox(
                  fit: BoxFit.scaleDown,
                  alignment: Alignment.centerLeft,
                  child: Text.rich(
                    TextSpan(
                      text: formatFcfa(unitPrice),
                      children: [
                        if (unit.isNotEmpty)
                          TextSpan(
                            text: '  / $unit',
                            style: const TextStyle(color: catalogMuted, fontSize: 13.5, fontWeight: FontWeight.w400),
                          ),
                      ],
                    ),
                    maxLines: 1,
                    style: const TextStyle(color: authNavy, fontSize: 23, fontWeight: FontWeight.w800),
                  ),
                ),
                if (oldPrice > unitPrice) ...[
                  const SizedBox(height: 1),
                  Row(
                    children: [
                      Text(
                        formatFcfa(oldPrice),
                        style: const TextStyle(
                          color: Color(0xFF6B7591),
                          fontSize: 12,
                          decoration: TextDecoration.lineThrough,
                          decorationColor: Color(0xFF6B7591),
                        ),
                      ),
                      if (product.discountPercent > 0) ...[
                        const SizedBox(width: 6),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 1.5),
                          decoration: BoxDecoration(color: authOrange, borderRadius: BorderRadius.circular(5)),
                          child: Text(
                            '-${product.discountPercent} %',
                            style: const TextStyle(color: Colors.white, fontSize: 10.5, fontWeight: FontWeight.w700),
                          ),
                        ),
                      ],
                    ],
                  ),
                ],
                const SizedBox(height: 5),
                Wrap(
                  spacing: 8,
                  runSpacing: 4,
                  crossAxisAlignment: WrapCrossAlignment.center,
                  children: [
                    Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Container(
                          width: 7,
                          height: 7,
                          decoration: BoxDecoration(color: stockColor, shape: BoxShape.circle),
                        ),
                        const SizedBox(width: 6),
                        Text(
                          stockLabel,
                          style: TextStyle(color: stockColor, fontSize: 11.5, fontWeight: FontWeight.w600),
                        ),
                      ],
                    ),
                    if (product.isNegotiable)
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
                        decoration: BoxDecoration(
                          color: const Color(0xFFDCEAFF),
                          borderRadius: BorderRadius.circular(99),
                        ),
                        child: const Text(
                          'Prix négociable',
                          style: TextStyle(color: _blue, fontSize: 10.5, fontWeight: FontWeight.w600),
                        ),
                      ),
                  ],
                ),
              ],
            ),
          ),
          if (action != null) ...[const SizedBox(width: 8), action!],
        ],
      ),
    );
  }
}

/// « Choisissez votre modèle » : une carte à bouton radio par variante.
class _VariantSelector extends StatelessWidget {
  const _VariantSelector({
    required this.variants,
    required this.selected,
    required this.error,
    required this.onSelected,
  });

  final List<ProductVariantModel> variants;
  final ProductVariantModel? selected;
  final bool error;
  final ValueChanged<ProductVariantModel> onSelected;

  @override
  Widget build(BuildContext context) {
    const hint = 'Sélection obligatoire avant ajout au panier';

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          'Choisissez votre modèle',
          style: TextStyle(color: authNavy, fontSize: 13.5, fontWeight: FontWeight.w700),
        ),
        if (!error) const Text(hint, style: TextStyle(color: catalogMuted, fontSize: 10.5)),
        const SizedBox(height: 6),
        LayoutBuilder(
          builder: (context, box) {
            const gap = 6.0;
            final columns = box.maxWidth >= 520 ? 4 : 3;
            final width = (box.maxWidth - gap * (columns - 1)) / columns - .01;
            return Wrap(
              spacing: gap,
              runSpacing: gap,
              children: [
                for (final variant in variants)
                  SizedBox(
                    width: width,
                    child: _VariantCard(
                      variant: variant,
                      selected: selected?.id == variant.id,
                      error: error,
                      onTap: variant.stock > 0 ? () => onSelected(variant) : null,
                    ),
                  ),
              ],
            );
          },
        ),
        if (error)
          const Padding(
            padding: EdgeInsets.only(top: 5),
            child: Text(hint, style: TextStyle(color: authDanger, fontSize: 11.5)),
          ),
      ],
    );
  }
}

class _VariantCard extends StatelessWidget {
  const _VariantCard({required this.variant, required this.selected, required this.error, required this.onTap});

  final ProductVariantModel variant;
  final bool selected;
  final bool error;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final available = variant.stock > 0;
    final label = variant.label.trim().isNotEmpty ? variant.label.trim() : variant.value.trim();
    final border = error ? authDanger : (selected ? authOrange : _line);
    final muted = !available && !error;

    return Semantics(
      button: true,
      selected: selected,
      enabled: available,
      child: Material(
        color: muted ? const Color(0xFFF6F7F9) : Colors.white,
        borderRadius: BorderRadius.circular(8),
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(8),
          child: Container(
            constraints: const BoxConstraints(minHeight: 46),
            padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 6),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(8),
              border: Border.all(color: border, width: selected || error ? 1.4 : 1),
            ),
            child: Row(
              children: [
                Container(
                  width: 15,
                  height: 15,
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    border: Border.all(
                      color: error ? authDanger : (selected ? authOrange : const Color(0xFFB9C0CE)),
                      width: 1.4,
                    ),
                  ),
                  child: selected
                      ? Container(
                          width: 7,
                          height: 7,
                          decoration: const BoxDecoration(color: authOrange, shape: BoxShape.circle),
                        )
                      : null,
                ),
                const SizedBox(width: 7),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        label,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                          color: muted ? const Color(0xFF9AA2B3) : authNavy,
                          fontSize: 11,
                          height: 1.2,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      Text(
                        available ? '${variant.stock} disponible(s)' : 'Rupture',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(color: muted ? const Color(0xFF9AA2B3) : catalogMuted, fontSize: 10),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

String _pluralUnit(String unit) {
  final value = unit.trim();
  if (value.isEmpty || value == 'unité') return 'unités';
  final space = value.indexOf(' ');
  final word = space < 0 ? value : value.substring(0, space);
  final rest = space < 0 ? '' : value.substring(space);
  if (word.endsWith('s') || word.endsWith('x') || word.contains('²') || word.length < 3) return value;
  return '${word}s$rest';
}

String _quantityLabel(ProductModel product, int quantity) {
  final unit = product.unitLabel.trim().isEmpty ? 'unité' : product.unitLabel.trim();
  return '$quantity ${quantity > 1 ? _pluralUnit(unit) : unit}';
}

String _weightLabel(ProductModel product, int quantity) {
  if (product.weightKg <= 0) return '';
  return '${formatFcfa(product.weightKg * quantity).replaceAll(' FCFA', '')} kg';
}

int? _quantityDiscount(ProductModel product, int quantity) {
  final candidates = <String>['remise_$quantity', 'discount_$quantity', 'remise $quantity'];
  for (final entry in product.attributes.entries) {
    final key = entry.key.toLowerCase().replaceAll('%', '').trim();
    if (!candidates.contains(key)) continue;
    final digits = RegExp(r'\d+').firstMatch(entry.value)?.group(0);
    return int.tryParse(digits ?? '');
  }
  return null;
}

/// « − 30 + » puis le poids total. Toucher le nombre ouvre le choix rapide.
class _QuantityStepper extends StatelessWidget {
  const _QuantityStepper({
    required this.product,
    required this.quantity,
    required this.onMinus,
    required this.onPlus,
    required this.onTapValue,
  });

  final ProductModel product;
  final int quantity;
  final VoidCallback onMinus;
  final VoidCallback onPlus;
  final VoidCallback onTapValue;

  Widget _box({required Widget child, required double width, VoidCallback? onTap, String? label}) => Semantics(
    button: true,
    label: label,
    child: Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(7),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(7),
        child: Container(
          width: width,
          height: 36,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(7),
            border: Border.all(color: _line),
          ),
          child: child,
        ),
      ),
    ),
  );

  @override
  Widget build(BuildContext context) {
    final weight = _weightLabel(product, quantity);
    return Row(
      children: [
        _box(
          width: 36,
          onTap: onMinus,
          label: 'Diminuer la quantité',
          child: const Icon(Icons.remove_rounded, size: 18, color: authNavy),
        ),
        _box(
          width: 52,
          onTap: onTapValue,
          label: 'Quantité $quantity, choisir',
          child: ExcludeSemantics(
            child: Text(
              '$quantity',
              style: const TextStyle(color: authNavy, fontSize: 14, fontWeight: FontWeight.w600),
            ),
          ),
        ),
        _box(
          width: 36,
          onTap: onPlus,
          label: 'Augmenter la quantité',
          child: const Icon(Icons.add_rounded, size: 18, color: authNavy),
        ),
        if (weight.isNotEmpty) ...[
          const SizedBox(width: 12),
          Text(weight, style: const TextStyle(color: authNavy, fontSize: 12)),
        ],
      ],
    );
  }
}

/// Contenu de la fenêtre « Choisir la quantité ».
class _QuantityChoices extends StatefulWidget {
  const _QuantityChoices({required this.product, required this.tiers, required this.selected});

  final ProductModel product;
  final List<int> tiers;
  final int selected;

  @override
  State<_QuantityChoices> createState() => _QuantityChoicesState();
}

class _QuantityChoicesState extends State<_QuantityChoices> {
  late int _value = widget.selected;

  @override
  Widget build(BuildContext context) {
    final product = widget.product;
    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const SizedBox(height: 4),
        Container(
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(8),
            border: Border.all(color: _line),
          ),
          child: Column(
            children: [
              for (var i = 0; i < widget.tiers.length; i++) ...[
                if (i > 0) const Divider(height: 1, thickness: 1, color: _line),
                _QuantityRow(
                  label: _quantityLabel(product, widget.tiers[i]),
                  weight: _weightLabel(product, widget.tiers[i]),
                  discount: _quantityDiscount(product, widget.tiers[i]),
                  selected: widget.tiers[i] == _value,
                  onTap: () => setState(() => _value = widget.tiers[i]),
                ),
              ],
            ],
          ),
        ),
        const SizedBox(height: 16),
        OvaniePrimaryButton(label: 'Valider', height: 46, onPressed: () => Navigator.of(context).pop(_value)),
      ],
    );
  }
}

class _QuantityRow extends StatelessWidget {
  const _QuantityRow({
    required this.label,
    required this.weight,
    required this.discount,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final String weight;
  final int? discount;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      button: true,
      selected: selected,
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 12),
          child: Row(
            children: [
              Container(
                width: 18,
                height: 18,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  border: Border.all(color: selected ? authOrange : const Color(0xFFB9C0CE), width: 1.5),
                ),
                child: selected
                    ? Container(
                        width: 9,
                        height: 9,
                        decoration: const BoxDecoration(color: authOrange, shape: BoxShape.circle),
                      )
                    : null,
              ),
              const SizedBox(width: 13),
              Flexible(
                child: Text(
                  label,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(color: authNavy, fontSize: 14, fontWeight: FontWeight.w600),
                ),
              ),
              if (discount != null) ...[
                const SizedBox(width: 8),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                  decoration: BoxDecoration(color: const Color(0xFFD6F5E0), borderRadius: BorderRadius.circular(99)),
                  child: Text(
                    '-$discount %',
                    style: const TextStyle(color: Color(0xFF12843C), fontSize: 11, fontWeight: FontWeight.w700),
                  ),
                ),
              ],
              const Spacer(),
              if (weight.isNotEmpty) Text(weight, style: const TextStyle(color: catalogMuted, fontSize: 12.5)),
            ],
          ),
        ),
      ),
    );
  }
}

/// Pastilles « Norme », « Usage », « Conditionnement » : seulement celles
/// renseignées sur le produit.
class _FactChips extends StatelessWidget {
  const _FactChips({required this.product});

  final ProductModel product;

  @override
  Widget build(BuildContext context) {
    final facts = <(IconData, String, String)>[
      if (product.standard.trim().isNotEmpty) (Icons.description_outlined, 'Norme', product.standard.trim()),
      if (product.usageArea.trim().isNotEmpty) (Icons.build_outlined, 'Usage', product.usageArea.trim()),
      if (product.packaging.trim().isNotEmpty)
        (Icons.inventory_2_outlined, 'Conditionnement', product.packaging.trim()),
    ];
    if (facts.isEmpty) return const SizedBox.shrink();

    return Wrap(
      spacing: 6,
      runSpacing: 6,
      children: [
        for (final (icon, label, value) in facts)
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 8),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(7),
              border: Border.all(color: _line),
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(icon, size: 16, color: authNavy),
                const SizedBox(width: 7),
                Flexible(
                  child: Text.rich(
                    TextSpan(
                      text: '$label : ',
                      style: const TextStyle(fontWeight: FontWeight.w700),
                      children: [
                        TextSpan(
                          text: value,
                          style: const TextStyle(fontWeight: FontWeight.w400),
                        ),
                      ],
                    ),
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(color: authNavy, fontSize: 11, height: 1.25),
                  ),
                ),
              ],
            ),
          ),
      ],
    );
  }
}

class _DeliveryPanel extends StatelessWidget {
  const _DeliveryPanel({required this.product, required this.onOptions});

  final ProductModel product;
  final VoidCallback onOptions;

  Widget _note(IconData icon, String text) => Row(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Padding(
        padding: const EdgeInsets.only(top: 1),
        child: Icon(icon, size: 13, color: catalogMuted),
      ),
      const SizedBox(width: 6),
      Expanded(
        child: Text(text, style: const TextStyle(color: catalogMuted, fontSize: 9.8, height: 1.3)),
      ),
    ],
  );

  @override
  Widget build(BuildContext context) {
    final delay = product.supplyDelay.trim();

    return Container(
      padding: const EdgeInsets.fromLTRB(9, 9, 9, 7),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: _line),
      ),
      child: IntrinsicHeight(
        child: Row(
          children: [
            const Icon(Icons.local_shipping_outlined, size: 24, color: authNavy),
            const SizedBox(width: 9),
            Expanded(
              flex: 11,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  const Text(
                    'Livraison estimée',
                    style: TextStyle(color: authNavy, fontSize: 12.5, fontWeight: FontWeight.w700),
                  ),
                  Text(
                    delay.isEmpty ? 'Délai de livraison confirmé avant validation' : 'Délai indicatif : $delay',
                    style: const TextStyle(color: catalogMuted, fontSize: 10.2, height: 1.3),
                  ),
                  InkWell(
                    onTap: onOptions,
                    child: const Padding(
                      padding: EdgeInsets.symmetric(vertical: 4),
                      child: Text.rich(
                        TextSpan(
                          text: 'Voir les options de livraison',
                          children: [TextSpan(text: '  ›')],
                        ),
                        style: TextStyle(color: _blue, fontSize: 10.8, fontWeight: FontWeight.w600),
                      ),
                    ),
                  ),
                ],
              ),
            ),
            const Padding(
              padding: EdgeInsets.symmetric(horizontal: 8),
              child: VerticalDivider(width: 1, thickness: 1, color: _line),
            ),
            Expanded(
              flex: 10,
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  _note(Icons.info_outline_rounded, 'Frais de livraison affichés avant validation'),
                  const SizedBox(height: 5),
                  _note(Icons.sync_rounded, 'Retour possible selon les conditions OVANIE'),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _TrustGrid extends StatelessWidget {
  const _TrustGrid();

  static const List<(IconData, String, String)> _items = [
    (Icons.verified_user_outlined, 'Produits 100% vérifiés', 'Qualité garantie'),
    (Icons.credit_card_rounded, 'Paiement sécurisé', 'Plusieurs moyens'),
    (Icons.headset_mic_outlined, 'Support 7j/7', 'À votre écoute'),
    (Icons.sell_outlined, 'Meilleurs prix', 'Garantie'),
  ];

  Widget _tile((IconData, String, String) item) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
    decoration: BoxDecoration(color: const Color(0xFFF8F9FB), borderRadius: BorderRadius.circular(6)),
    child: Row(
      children: [
        Icon(item.$1, size: 20, color: authNavy),
        const SizedBox(width: 9),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                item.$2,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(color: authNavy, fontSize: 10.8, fontWeight: FontWeight.w700),
              ),
              Text(
                item.$3,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(color: catalogMuted, fontSize: 10),
              ),
            ],
          ),
        ),
      ],
    ),
  );

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(5),
      decoration: BoxDecoration(color: _panel, borderRadius: BorderRadius.circular(8)),
      child: Column(
        children: [
          Row(
            children: [
              Expanded(child: _tile(_items[0])),
              const SizedBox(width: 5),
              Expanded(child: _tile(_items[1])),
            ],
          ),
          const SizedBox(height: 5),
          Row(
            children: [
              Expanded(child: _tile(_items[2])),
              const SizedBox(width: 5),
              Expanded(child: _tile(_items[3])),
            ],
          ),
        ],
      ),
    );
  }
}

class _VerifiedRow extends StatelessWidget {
  const _VerifiedRow();

  @override
  Widget build(BuildContext context) {
    Widget item(String text) => Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        const Icon(Icons.check_circle_rounded, size: 14, color: _green),
        const SizedBox(width: 5),
        Text(text, maxLines: 1, style: const TextStyle(color: authNavy, fontSize: 10)),
      ],
    );

    return FittedBox(
      fit: BoxFit.scaleDown,
      alignment: Alignment.centerLeft,
      child: Row(
        children: [
          item('Produit vérifié OVANIE'),
          const SizedBox(width: 14),
          item('Qualité professionnelle'),
          const SizedBox(width: 14),
          item('Disponibilité à jour'),
        ],
      ),
    );
  }
}

class _ProductTabs extends StatelessWidget {
  const _ProductTabs({super.key, required this.selected, required this.onChanged});

  final int selected;
  final ValueChanged<int> onChanged;

  static const List<String> _labels = ['Description', 'Caractéristiques', 'Avis', 'Livraison & retour'];

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: const BoxDecoration(
        border: Border(bottom: BorderSide(color: _line)),
      ),
      child: SingleChildScrollView(
        scrollDirection: Axis.horizontal,
        child: Row(
          children: [
            for (var i = 0; i < _labels.length; i++)
              Semantics(
                button: true,
                selected: i == selected,
                child: InkWell(
                  onTap: () => onChanged(i),
                  child: Container(
                    padding: const EdgeInsets.fromLTRB(9, 8, 9, 7),
                    decoration: BoxDecoration(
                      border: Border(
                        bottom: BorderSide(color: i == selected ? authOrange : Colors.transparent, width: 2),
                      ),
                    ),
                    child: Text(
                      _labels[i],
                      style: TextStyle(
                        color: i == selected ? authNavy : catalogMuted,
                        fontSize: 12.5,
                        fontWeight: i == selected ? FontWeight.w700 : FontWeight.w400,
                      ),
                    ),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

Widget _bullets(List<String> lines) => Column(
  crossAxisAlignment: CrossAxisAlignment.start,
  children: [
    for (final line in lines)
      Padding(
        padding: const EdgeInsets.only(left: 12, bottom: 3),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text('•  ', style: TextStyle(color: authNavy, fontSize: 11.5, height: 1.35)),
            Expanded(
              child: Text(line, style: const TextStyle(color: Color(0xFF3B4663), fontSize: 11.5, height: 1.35)),
            ),
          ],
        ),
      ),
  ],
);

class _TabContent extends StatelessWidget {
  const _TabContent({required this.product, required this.selected});

  final ProductModel product;
  final int selected;

  @override
  Widget build(BuildContext context) {
    if (selected == 1) return _Characteristics(product: product);
    if (selected == 2) return _ReviewsSummary(product: product);
    if (selected == 3) return _DeliveryInfo(product: product);

    final description = product.description.trim().isNotEmpty
        ? product.description.trim()
        : product.shortDescription.trim().isNotEmpty
        ? product.shortDescription.trim()
        : 'Les informations détaillées de ce produit seront complétées depuis le catalogue OVANIE.';

    final facts = <String>[
      if (product.materialGrade.isNotEmpty) 'Type : ${product.materialGrade}',
      if (product.usageArea.isNotEmpty) 'Usage : ${product.usageArea}',
      if (product.packaging.isNotEmpty) 'Conditionnement : ${product.packaging}',
      if (product.standard.isNotEmpty) 'Norme : ${product.standard}',
    ];

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(description, style: const TextStyle(color: Color(0xFF3B4663), fontSize: 11.5, height: 1.5)),
        if (facts.isNotEmpty) ...[const SizedBox(height: 8), _bullets(facts)],
      ],
    );
  }
}

/// Tableau rayé des caractéristiques, puis les points clés.
class _Characteristics extends StatelessWidget {
  const _Characteristics({required this.product});

  final ProductModel product;

  @override
  Widget build(BuildContext context) {
    final entries = <MapEntry<String, String>>[];
    if (product.brand.isNotEmpty) entries.add(MapEntry('Marque', product.brand));
    if (product.packaging.isNotEmpty) entries.add(MapEntry('Conditionnement', product.packaging));
    if (product.standard.isNotEmpty) entries.add(MapEntry('Norme', product.standard));
    if (product.originCountry.isNotEmpty) entries.add(MapEntry('Origine', product.originCountry));
    if (product.dimensionsLabel.isNotEmpty) entries.add(MapEntry('Dimensions', product.dimensionsLabel));
    for (final entry in [...product.technicalSpecs.entries, ...product.attributes.entries]) {
      final key = entry.key.toLowerCase();
      // Les remises par quantité sont des réglages, pas des caractéristiques.
      if (key.startsWith('remise') || key.startsWith('discount')) continue;
      if (!entries.any((item) => item.key.toLowerCase() == key)) entries.add(entry);
    }

    final facts = <String>[
      if (product.materialGrade.isNotEmpty) 'Type : ${product.materialGrade}',
      if (product.usageArea.isNotEmpty) 'Usage : ${product.usageArea}',
      if (product.weightKg > 0) 'Poids : ${ProductModel.compactNumber(product.weightKg)} kg',
    ];

    if (entries.isEmpty && facts.isEmpty && product.technicalDetails.isEmpty) {
      return const Text(
        'Aucune caractéristique technique supplémentaire n’est disponible.',
        style: TextStyle(color: catalogMuted, fontSize: 11.5),
      );
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (product.technicalDetails.isNotEmpty) ...[
          Text(product.technicalDetails, style: const TextStyle(color: Color(0xFF3B4663), fontSize: 11.5, height: 1.5)),
          const SizedBox(height: 10),
        ],
        if (entries.isNotEmpty)
          Container(
            clipBehavior: Clip.antiAlias,
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(6),
              border: Border.all(color: const Color(0xFFEDF0F4)),
            ),
            child: Column(
              children: [
                for (var i = 0; i < entries.length; i++)
                  ColoredBox(
                    color: i.isEven ? const Color(0xFFF4F6F9) : Colors.white,
                    child: IntrinsicHeight(
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          Expanded(
                            flex: 43,
                            child: ColoredBox(
                              color: i.isEven ? const Color(0xFFECEFF4) : const Color(0xFFF7F8FA),
                              child: Padding(
                                padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 6),
                                child: Text(
                                  entries[i].key,
                                  style: const TextStyle(color: Color(0xFF3B4663), fontSize: 11),
                                ),
                              ),
                            ),
                          ),
                          Expanded(
                            flex: 57,
                            child: Padding(
                              padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 6),
                              child: Text(entries[i].value, style: const TextStyle(color: authNavy, fontSize: 11.5)),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
              ],
            ),
          ),
        if (facts.isNotEmpty) ...[const SizedBox(height: 8), _bullets(facts)],
      ],
    );
  }
}

/// Note moyenne en grand et répartition des avis par nombre d'étoiles.
class _ReviewsSummary extends StatelessWidget {
  const _ReviewsSummary({required this.product});

  final ProductModel product;

  @override
  Widget build(BuildContext context) {
    final rating = product.rating ?? 0;
    if (rating <= 0 || product.reviewsCount <= 0) {
      return const Padding(
        padding: EdgeInsets.symmetric(vertical: 10),
        child: Text('Aucun avis pour le moment.', style: TextStyle(color: catalogMuted, fontSize: 12)),
      );
    }

    final ratings = product.reviewRatings;
    final count = product.reviewsCount;

    return Row(
      crossAxisAlignment: CrossAxisAlignment.center,
      children: [
        SizedBox(
          width: 110,
          child: Column(
            children: [
              Text(
                _ratingText(rating),
                style: const TextStyle(color: authNavy, fontSize: 40, height: 1.05, fontWeight: FontWeight.w800),
              ),
              _Stars(rating: rating, size: 17),
              const SizedBox(height: 3),
              Text(
                '$count avis client${count > 1 ? 's' : ''}',
                style: const TextStyle(color: authNavy, fontSize: 11.5),
              ),
            ],
          ),
        ),
        if (ratings.isNotEmpty) ...[
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              children: [
                for (var stars = 5; stars >= 1; stars--)
                  Padding(
                    padding: const EdgeInsets.symmetric(vertical: 3.5),
                    child: Row(
                      children: [
                        SizedBox(
                          width: 46,
                          child: Text(
                            '$stars étoile${stars > 1 ? 's' : ''}',
                            style: const TextStyle(color: Color(0xFF3B4663), fontSize: 10.5),
                          ),
                        ),
                        Expanded(
                          child: ClipRRect(
                            borderRadius: BorderRadius.circular(99),
                            child: LinearProgressIndicator(
                              value: ratings.where((value) => value == stars).length / ratings.length,
                              minHeight: 6,
                              color: const Color(0xFFFFB400),
                              backgroundColor: const Color(0xFFE3E6EB),
                              semanticsLabel: '$stars étoiles',
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
              ],
            ),
          ),
        ],
      ],
    );
  }
}

class _DeliveryInfo extends StatelessWidget {
  const _DeliveryInfo({required this.product});

  final ProductModel product;

  Widget _card(IconData icon, {String? title, required String text}) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 9),
    decoration: BoxDecoration(
      borderRadius: BorderRadius.circular(8),
      border: Border.all(color: _line),
    ),
    child: Row(
      children: [
        Icon(icon, size: 22, color: authNavy),
        const SizedBox(width: 14),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              if (title != null)
                Text(
                  title,
                  style: const TextStyle(color: authNavy, fontSize: 12, fontWeight: FontWeight.w700),
                ),
              Text(
                text,
                style: TextStyle(
                  color: title == null ? authNavy : catalogMuted,
                  fontSize: title == null ? 11.5 : 10.8,
                  height: 1.35,
                ),
              ),
            ],
          ),
        ),
      ],
    ),
  );

  @override
  Widget build(BuildContext context) {
    final delay = product.supplyDelay.trim();
    final returns = product.returnPolicy.trim();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _card(
          Icons.local_shipping_outlined,
          title: delay.isEmpty ? null : 'Délai indicatif : $delay.',
          text: 'Le délai estimé et les frais applicables sont présentés avant la validation de votre commande.',
        ),
        const SizedBox(height: 6),
        _card(
          Icons.undo_rounded,
          text: returns.isEmpty ? 'Les retours sont pris en charge conformément aux conditions OVANIE.' : returns,
        ),
      ],
    );
  }
}

class _SimilarProductCard extends StatelessWidget {
  const _SimilarProductCard({required this.product, required this.onTap});

  final ProductModel product;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final unit = product.unitLabel.trim();
    final rating = product.rating ?? 0;

    return SizedBox(
      width: 116,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(8),
        child: Container(
          foregroundDecoration: BoxDecoration(
            borderRadius: BorderRadius.circular(8),
            border: Border.all(color: const Color(0xFFE8EBF0)),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              ClipRRect(
                borderRadius: const BorderRadius.vertical(top: Radius.circular(8)),
                child: SizedBox(
                  height: 78,
                  child: Stack(
                    fit: StackFit.expand,
                    children: [
                      ColoredBox(
                        color: const Color(0xFFF1F3F6),
                        child: CatalogImage(
                          urls: product.imageUrl.trim().isEmpty ? const <String>[] : <String>[product.imageUrl],
                          fit: BoxFit.contain,
                          placeholder: const Icon(Icons.inventory_2_outlined, size: 28, color: Color(0xFFB5BECA)),
                        ),
                      ),
                      Positioned(
                        right: 4,
                        top: 4,
                        child: AnimatedBuilder(
                          animation: Listenable.merge([SessionStore.instance, FavoritesStore.instance]),
                          builder: (context, _) {
                            final favorite =
                                SessionStore.instance.isAuthenticated && FavoritesStore.instance.contains(product);
                            return Container(
                              width: 22,
                              height: 22,
                              decoration: const BoxDecoration(color: Colors.white, shape: BoxShape.circle),
                              child: Icon(
                                favorite ? Icons.favorite_rounded : Icons.favorite_border_rounded,
                                size: 14,
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
              Padding(
                padding: const EdgeInsets.fromLTRB(6, 5, 6, 0),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      product.name,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(color: authNavy, fontSize: 10.8, fontWeight: FontWeight.w600),
                    ),
                    const SizedBox(height: 2),
                    FittedBox(
                      fit: BoxFit.scaleDown,
                      alignment: Alignment.centerLeft,
                      child: Text.rich(
                        TextSpan(
                          text: formatFcfa(product.homeDisplayPrice),
                          children: [
                            if (unit.isNotEmpty)
                              TextSpan(
                                text: ' / $unit',
                                style: const TextStyle(color: catalogMuted, fontSize: 9.5, fontWeight: FontWeight.w400),
                              ),
                          ],
                        ),
                        maxLines: 1,
                        style: const TextStyle(color: authNavy, fontSize: 12.5, fontWeight: FontWeight.w800),
                      ),
                    ),
                    if (rating > 0 && product.reviewsCount > 0) ...[
                      const SizedBox(height: 2),
                      Row(
                        children: [
                          _Stars(rating: rating, size: 10.5),
                          const SizedBox(width: 3),
                          Flexible(
                            child: Text(
                              '${_ratingText(rating)} (${product.reviewsCount})',
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(color: catalogMuted, fontSize: 8.8),
                            ),
                          ),
                        ],
                      ),
                    ],
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

/// Barre du bas : total, « Ajouter au panier » et « Acheter maintenant ».
class _ProductActionBar extends StatelessWidget {
  const _ProductActionBar({required this.total, required this.canBuy, required this.onAdd, required this.onBuy});

  final double total;
  final bool canBuy;
  final VoidCallback onAdd;
  final VoidCallback onBuy;

  @override
  Widget build(BuildContext context) {
    const label = TextStyle(fontFamily: authFont, fontSize: 11.5, fontWeight: FontWeight.w700);
    final shape = RoundedRectangleBorder(borderRadius: BorderRadius.circular(8));

    return OvanieAuthTheme(
      child: Container(
        decoration: const BoxDecoration(
          color: Colors.white,
          border: Border(top: BorderSide(color: Color(0xFFE8EAF0))),
        ),
        child: SafeArea(
          top: false,
          child: Padding(
            padding: const EdgeInsets.fromLTRB(12, 7, 10, 7),
            child: Row(
              children: [
                Expanded(
                  flex: 30,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const Text('Total', style: TextStyle(color: Color(0xFF3B4663), fontSize: 11.5)),
                      FittedBox(
                        fit: BoxFit.scaleDown,
                        alignment: Alignment.centerLeft,
                        child: Text(
                          formatFcfa(total),
                          maxLines: 1,
                          style: const TextStyle(color: authNavy, fontSize: 16, fontWeight: FontWeight.w800),
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 6),
                Expanded(
                  flex: 33,
                  child: OutlinedButton(
                    onPressed: canBuy ? onAdd : null,
                    style: OutlinedButton.styleFrom(
                      foregroundColor: authOrange,
                      minimumSize: const Size(0, 42),
                      padding: const EdgeInsets.symmetric(horizontal: 4),
                      side: BorderSide(color: canBuy ? authOrange : const Color(0xFFCBD2DC), width: 1.3),
                      shape: shape,
                    ),
                    child: const FittedBox(child: Text('Ajouter au panier', style: label)),
                  ),
                ),
                const SizedBox(width: 6),
                Expanded(
                  flex: 35,
                  child: FilledButton(
                    onPressed: canBuy ? onBuy : null,
                    style: FilledButton.styleFrom(
                      backgroundColor: authOrange,
                      foregroundColor: Colors.white,
                      minimumSize: const Size(0, 42),
                      padding: const EdgeInsets.symmetric(horizontal: 4),
                      shape: shape,
                    ),
                    child: const FittedBox(child: Text('Acheter maintenant', style: label)),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _DetailError extends StatelessWidget {
  const _DetailError({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.fromLTRB(11, 4, 4, 4),
      decoration: BoxDecoration(color: authDangerSoft, borderRadius: BorderRadius.circular(8)),
      child: Row(
        children: [
          const Icon(Icons.error_outline_rounded, color: authDanger, size: 18),
          const SizedBox(width: 8),
          Expanded(
            child: Text(message, style: const TextStyle(color: authDanger, fontSize: 11.5, height: 1.3)),
          ),
          TextButton(
            onPressed: onRetry,
            style: TextButton.styleFrom(foregroundColor: authNavy),
            child: const Text(
              'Réessayer',
              style: TextStyle(fontFamily: authFont, fontSize: 12, fontWeight: FontWeight.w700),
            ),
          ),
        ],
      ),
    );
  }
}
