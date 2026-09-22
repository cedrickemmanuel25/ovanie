import 'dart:async';

import 'package:flutter/material.dart';

import '../../../app/theme.dart';
import '../../../core/network/api_client.dart';
import '../../../core/storage/device_storage.dart';
import '../../../core/utils/formatters.dart';
import '../../auth/domain/session_store.dart';
import '../../auth/presentation/login_screen.dart';
import '../../auth/presentation/register_screen.dart';
import '../../cart/domain/cart_store.dart';
import '../../cart/presentation/cart_screen.dart';
import '../../favorites/domain/favorites_store.dart';
import '../../home/data/marketplace_repository.dart';
import '../../recent/domain/recently_viewed_store.dart';
import '../data/negotiation_repository.dart';
import '../domain/product_model.dart';

/// Fiche produit OVANIE reconstruite selon la maquette mobile.
/// Toutes les informations produit, les prix et la disponibilité proviennent
/// du même catalogue Laravel que le Web OVANIE.
class ProductDetailScreen extends StatefulWidget {
  final ProductModel product;

  const ProductDetailScreen({
    super.key,
    required this.product,
  });

  @override
  State<ProductDetailScreen> createState() => _ProductDetailScreenState();
}

class _ProductDetailScreenState extends State<ProductDetailScreen> {
  final MarketplaceRepository _repository = const MarketplaceRepository();
  late ProductModel _product;
  List<ProductModel> _similar = const <ProductModel>[];
  int _quantity = 1;
  int _galleryIndex = 0;
  int _selectedTab = 0;
  bool _loading = true;
  Object? _error;

  @override
  void initState() {
    super.initState();
    _product = widget.product;
    _quantity = _minimumQuantity(_product);
    RecentlyViewedStore.instance.record(_product);
    _loadDetail();
  }

  int _minimumQuantity(ProductModel product) {
    return product.minOrderQuantity <= 0 ? 1 : product.minOrderQuantity;
  }

  Future<void> _loadDetail() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      var fresh = _product;
      if (_product.slug.trim().isNotEmpty) {
        fresh = await _repository.getProductBySlug(_product.slug);
      }

      var similar = <ProductModel>[];
      if (fresh.categorySlug.trim().isNotEmpty) {
        try {
          final page = await _repository.getCatalogPage(
            categorySlug: fresh.categorySlug,
            sort: 'popular',
            perPage: 16,
          );
          similar = page.products
              .where((item) => item.id != fresh.id)
              .take(8)
              .toList(growable: false);
        } catch (_) {
          similar = <ProductModel>[];
        }
      }

      if (!mounted) return;
      setState(() {
        _product = fresh;
        _quantity = _minimumQuantity(fresh);
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

  void _toggleFavorite() {
    if (!SessionStore.instance.isAuthenticated) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Connectez-vous pour enregistrer ce produit.')),
      );
      return;
    }
    FavoritesStore.instance.toggle(_product);
  }

  void _changeQuantity(int direction) {
    final step = _minimumQuantity(_product);
    final next = _quantity + (direction * step);
    if (next < step) return;
    if (_product.stock > 0 && next > _product.stock) return;
    setState(() => _quantity = next);
  }

  void _selectQuantity(int quantity) {
    final minimum = _minimumQuantity(_product);
    var next = quantity < minimum ? minimum : quantity;
    if (_product.stock > 0 && next > _product.stock) next = _product.stock;
    setState(() => _quantity = next);
  }

  bool get _canBuy => _product.canAddToCart && _product.stock > 0;

  void _addToCart({bool openCart = false}) {
    final result = CartStore.instance.addQuantity(_product, _quantity);
    if (!result.success) {
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(
          SnackBar(content: Text(result.message), backgroundColor: OvanieColors.danger),
        );
      return;
    }

    if (openCart) {
      Navigator.of(context).push(
        MaterialPageRoute<void>(builder: (_) => const CartScreen()),
      );
      return;
    }

    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(content: Text(result.message), backgroundColor: OvanieColors.navy),
      );
  }

  void _share() {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text('Lien produit prêt à partager : ${_product.name}')),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      body: SafeArea(
        bottom: false,
        child: Column(
          children: [
            _ProductHeader(
              onBack: () => Navigator.of(context).maybePop(),
              onFavorite: _toggleFavorite,
              onCart: () => Navigator.of(context).push(
                MaterialPageRoute<void>(builder: (_) => const CartScreen()),
              ),
              product: _product,
            ),
            Expanded(
              child: RefreshIndicator(
                color: OvanieColors.orange,
                onRefresh: _loadDetail,
                child: ListView(
                  physics: const AlwaysScrollableScrollPhysics(),
                  padding: const EdgeInsets.fromLTRB(16, 0, 16, 22),
                  children: [
                    if (_loading)
                      const LinearProgressIndicator(
                        minHeight: 2,
                        color: OvanieColors.orange,
                        backgroundColor: Colors.transparent,
                      ),
                    if (_error != null && !_loading)
                      _DetailError(
                        message: ApiClient.friendlyError(_error!),
                        onRetry: _loadDetail,
                      ),
                    _Breadcrumb(product: _product),
                    const SizedBox(height: 8),
                    _HeroProductBlock(
                      product: _product,
                      galleryIndex: _galleryIndex,
                      onGalleryChanged: (index) => setState(() => _galleryIndex = index),
                      onShare: _share,
                    ),
                    if (_product.isNegotiable && _canBuy) ...[
                      const SizedBox(height: 14),
                      _NegotiateSection(product: _product, quantity: _quantity),
                    ],
                    const SizedBox(height: 20),
                    _QuantitySection(
                      product: _product,
                      quantity: _quantity,
                      onSelect: _selectQuantity,
                      onMinus: () => _changeQuantity(-1),
                      onPlus: () => _changeQuantity(1),
                    ),
                    const SizedBox(height: 18),
                    const _DeliveryPanel(),
                    const SizedBox(height: 12),
                    const _TrustRow(),
                    const SizedBox(height: 18),
                    _ProductTabs(
                      selected: _selectedTab,
                      onChanged: (index) => setState(() => _selectedTab = index),
                    ),
                    const SizedBox(height: 14),
                    _TabContent(product: _product, selected: _selectedTab),
                    if (_similar.isNotEmpty) ...[
                      const SizedBox(height: 24),
                      Row(
                        children: [
                          const Expanded(
                            child: Text(
                              'Produits similaires',
                              style: TextStyle(
                                color: OvanieColors.navy,
                                fontSize: 17,
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                          ),
                          const Text(
                            'Voir tout',
                            style: TextStyle(
                              color: OvanieColors.orange,
                              fontSize: 12,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                          const SizedBox(width: 3),
                          const Icon(Icons.chevron_right_rounded, color: OvanieColors.orange, size: 18),
                        ],
                      ),
                      const SizedBox(height: 10),
                      SizedBox(
                        height: 188,
                        child: ListView.separated(
                          scrollDirection: Axis.horizontal,
                          physics: const BouncingScrollPhysics(),
                          itemCount: _similar.length,
                          separatorBuilder: (_, __) => const SizedBox(width: 9),
                          itemBuilder: (context, index) {
                            final item = _similar[index];
                            return _SimilarProductCard(
                              product: item,
                              onTap: () => Navigator.of(context).push(
                                MaterialPageRoute<void>(
                                  builder: (_) => ProductDetailScreen(product: item),
                                ),
                              ),
                            );
                          },
                        ),
                      ),
                    ],
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
      bottomNavigationBar: _ProductActionBar(
        canBuy: _canBuy,
        onAdd: () => _addToCart(),
        onBuy: () => _addToCart(openCart: true),
      ),
    );
  }
}

class _ProductHeader extends StatelessWidget {
  final VoidCallback onBack;
  final VoidCallback onFavorite;
  final VoidCallback onCart;
  final ProductModel product;

  const _ProductHeader({
    required this.onBack,
    required this.onFavorite,
    required this.onCart,
    required this.product,
  });

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(6, 5, 8, 4),
      child: Row(
        children: [
          IconButton(
            onPressed: onBack,
            icon: const Icon(Icons.arrow_back_rounded, color: OvanieColors.navy, size: 26),
          ),
          const Expanded(
            child: Text(
              'Détail du produit',
              style: TextStyle(
                color: OvanieColors.navy,
                fontSize: 18,
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
          AnimatedBuilder(
            animation: Listenable.merge([SessionStore.instance, FavoritesStore.instance]),
            builder: (context, _) {
              final favorite = SessionStore.instance.isAuthenticated && FavoritesStore.instance.contains(product);
              return IconButton(
                onPressed: onFavorite,
                icon: Icon(
                  favorite ? Icons.favorite_rounded : Icons.favorite_border_rounded,
                  color: favorite ? OvanieColors.orange : OvanieColors.navy,
                  size: 26,
                ),
              );
            },
          ),
          AnimatedBuilder(
            animation: CartStore.instance,
            builder: (context, _) => IconButton(
              onPressed: onCart,
              icon: Stack(
                clipBehavior: Clip.none,
                children: [
                  const Icon(Icons.shopping_cart_outlined, color: OvanieColors.navy, size: 26),
                  if (CartStore.instance.itemsCount > 0)
                    Positioned(
                      right: -7,
                      top: -7,
                      child: Container(
                        constraints: const BoxConstraints(minWidth: 18, minHeight: 18),
                        padding: const EdgeInsets.symmetric(horizontal: 3),
                        alignment: Alignment.center,
                        decoration: const BoxDecoration(color: OvanieColors.orange, shape: BoxShape.circle),
                        child: Text(
                          CartStore.instance.itemsCount > 99 ? '99+' : '${CartStore.instance.itemsCount}',
                          style: const TextStyle(color: Colors.white, fontSize: 8.5, fontWeight: FontWeight.w900),
                        ),
                      ),
                    ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _Breadcrumb extends StatelessWidget {
  final ProductModel product;

  const _Breadcrumb({required this.product});

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        const Text('Accueil', style: TextStyle(color: OvanieColors.muted, fontSize: 11.5)),
        const Padding(
          padding: EdgeInsets.symmetric(horizontal: 5),
          child: Icon(Icons.chevron_right_rounded, color: OvanieColors.muted, size: 16),
        ),
        if (product.categoryName.isNotEmpty) ...[
          Flexible(
            child: Text(
              product.categoryName,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(color: OvanieColors.muted, fontSize: 11.5),
            ),
          ),
          const Padding(
            padding: EdgeInsets.symmetric(horizontal: 5),
            child: Icon(Icons.chevron_right_rounded, color: OvanieColors.muted, size: 16),
          ),
        ],
        Flexible(
          child: Text(
            product.name,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(color: OvanieColors.orange, fontSize: 11.5, fontWeight: FontWeight.w700),
          ),
        ),
      ],
    );
  }
}

class _HeroProductBlock extends StatelessWidget {
  final ProductModel product;
  final int galleryIndex;
  final ValueChanged<int> onGalleryChanged;
  final VoidCallback onShare;

  const _HeroProductBlock({
    required this.product,
    required this.galleryIndex,
    required this.onGalleryChanged,
    required this.onShare,
  });

  List<String> get _images {
    final values = <String>[];
    for (final image in product.galleryUrls) {
      if (image.trim().isNotEmpty && !values.contains(image)) values.add(image);
    }
    if (product.imageUrl.trim().isNotEmpty && !values.contains(product.imageUrl)) {
      values.insert(0, product.imageUrl);
    }
    return values;
  }

  @override
  Widget build(BuildContext context) {
    final images = _images;
    return LayoutBuilder(
      builder: (context, constraints) {
        final compact = constraints.maxWidth < 650;
        if (!compact) {
          return Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                flex: 6,
                child: _GalleryPanel(
                  product: product,
                  images: images,
                  selected: galleryIndex,
                  onChanged: onGalleryChanged,
                  onShare: onShare,
                ),
              ),
              const SizedBox(width: 20),
              Expanded(flex: 5, child: _ProductSummary(product: product)),
            ],
          );
        }

        return Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(
              flex: 53,
              child: _GalleryPanel(
                product: product,
                images: images,
                selected: galleryIndex,
                onChanged: onGalleryChanged,
                onShare: onShare,
              ),
            ),
            const SizedBox(width: 12),
            Expanded(flex: 47, child: _ProductSummary(product: product)),
          ],
        );
      },
    );
  }
}

class _GalleryPanel extends StatelessWidget {
  final ProductModel product;
  final List<String> images;
  final int selected;
  final ValueChanged<int> onChanged;
  final VoidCallback onShare;

  const _GalleryPanel({
    required this.product,
    required this.images,
    required this.selected,
    required this.onChanged,
    required this.onShare,
  });

  @override
  Widget build(BuildContext context) {
    final currentIndex = images.isEmpty ? 0 : selected.clamp(0, images.length - 1).toInt();
    return Column(
      children: [
        AspectRatio(
          aspectRatio: 1.04,
          child: Container(
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(10),
              border: Border.all(color: const Color(0xFFE1E6ED)),
            ),
            child: Stack(
              children: [
                Positioned.fill(
                  child: Padding(
                    padding: const EdgeInsets.all(12),
                    child: images.isEmpty
                        ? const Icon(Icons.inventory_2_outlined, color: Color(0xFFB7C0CC), size: 70)
                        : Image.network(
                            images[currentIndex],
                            fit: BoxFit.contain,
                            errorBuilder: (_, __, ___) => const Icon(
                              Icons.broken_image_outlined,
                              color: Color(0xFFB7C0CC),
                              size: 60,
                            ),
                          ),
                  ),
                ),
                if (product.hasDiscount)
                  Positioned(
                    left: 7,
                    top: 7,
                    child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 4),
                      decoration: BoxDecoration(
                        color: const Color(0xFFE52012),
                        borderRadius: BorderRadius.circular(6),
                      ),
                      child: Text(
                        '-${product.discountPercent}%',
                        style: const TextStyle(color: Colors.white, fontSize: 9.5, fontWeight: FontWeight.w900),
                      ),
                    ),
                  ),
                Positioned(
                  right: 7,
                  top: 7,
                  child: _RoundIconButton(icon: Icons.share_outlined, onTap: onShare),
                ),
                const Positioned(
                  right: 7,
                  bottom: 7,
                  child: _RoundIconButton(icon: Icons.zoom_in_rounded),
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 8),
        if (images.isNotEmpty)
          SizedBox(
            height: 45,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              physics: const BouncingScrollPhysics(),
              itemCount: images.take(5).length,
              separatorBuilder: (_, __) => const SizedBox(width: 6),
              itemBuilder: (context, index) {
                final active = index == currentIndex;
                return InkWell(
                  onTap: () => onChanged(index),
                  borderRadius: BorderRadius.circular(7),
                  child: Container(
                    width: 45,
                    padding: const EdgeInsets.all(3),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(7),
                      border: Border.all(
                        color: active ? OvanieColors.orange : const Color(0xFFE1E6ED),
                        width: active ? 1.3 : 1,
                      ),
                    ),
                    child: Image.network(
                      images[index],
                      fit: BoxFit.contain,
                      errorBuilder: (_, __, ___) => const Icon(Icons.image_not_supported_outlined, size: 18),
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

class _RoundIconButton extends StatelessWidget {
  final IconData icon;
  final VoidCallback? onTap;

  const _RoundIconButton({required this.icon, this.onTap});

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white,
      shape: const CircleBorder(),
      child: InkWell(
        onTap: onTap,
        customBorder: const CircleBorder(),
        child: Container(
          width: 36,
          height: 36,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            border: Border.all(color: const Color(0xFFE0E5EC)),
          ),
          child: Icon(icon, color: OvanieColors.navy, size: 19),
        ),
      ),
    );
  }
}

class _ProductSummary extends StatelessWidget {
  final ProductModel product;

  const _ProductSummary({required this.product});

  @override
  Widget build(BuildContext context) {
    final facts = _productFacts(product);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Flexible(
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 4),
                decoration: BoxDecoration(
                  color: product.stock > 0 ? const Color(0xFF1CB04B) : const Color(0xFF929CAC),
                  borderRadius: BorderRadius.circular(6),
                ),
                child: Text(
                  product.availabilityLabel.toUpperCase(),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(color: Colors.white, fontSize: 8.5, fontWeight: FontWeight.w900),
                ),
              ),
            ),
            const SizedBox(width: 5),
            Expanded(
              child: Text(
                product.slug.trim().isEmpty ? '' : 'Réf. ${product.slug}',
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                textAlign: TextAlign.right,
                style: const TextStyle(color: OvanieColors.muted, fontSize: 8.2),
              ),
            ),
          ],
        ),
        const SizedBox(height: 8),
        Text(
          product.name,
          maxLines: 3,
          overflow: TextOverflow.ellipsis,
          style: const TextStyle(
            color: OvanieColors.navy,
            fontSize: 16,
            height: 1.05,
            fontWeight: FontWeight.w900,
          ),
        ),
        if (product.brand.isNotEmpty) ...[
          const SizedBox(height: 4),
          Text(
            'Marque : ${product.brand}',
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(color: OvanieColors.navy, fontSize: 10.5),
          ),
        ],
        const SizedBox(height: 8),
        Wrap(
          crossAxisAlignment: WrapCrossAlignment.center,
          spacing: 4,
          runSpacing: 2,
          children: [
            const Icon(Icons.star_rounded, color: OvanieColors.warning, size: 15),
            Text(
              '${product.rating?.toStringAsFixed(1) ?? '—'} (${product.reviewsCount} avis)',
              style: const TextStyle(color: OvanieColors.navy, fontSize: 9.5, fontWeight: FontWeight.w700),
            ),
            if (product.sales > 0) ...[
              const Text(' | ', style: TextStyle(color: OvanieColors.muted, fontSize: 9.5)),
              Text(
                '${product.sales} ventes',
                style: const TextStyle(color: OvanieColors.navy, fontSize: 9.5, fontWeight: FontWeight.w700),
              ),
            ],
          ],
        ),
        const SizedBox(height: 13),
        Wrap(
          crossAxisAlignment: WrapCrossAlignment.center,
          spacing: 8,
          runSpacing: 6,
          children: [
            Text(
              formatFcfa(product.homeDisplayPrice),
              style: const TextStyle(
                color: OvanieColors.orange,
                fontSize: 19,
                fontWeight: FontWeight.w900,
              ),
            ),
            if (product.isNegotiable)
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 4),
                decoration: BoxDecoration(
                  color: const Color(0xFFFFF5ED),
                  borderRadius: BorderRadius.circular(6),
                  border: Border.all(color: OvanieColors.orange.withValues(alpha: .35)),
                ),
                child: const Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(Icons.handshake_outlined, color: OvanieColors.orange, size: 12),
                    SizedBox(width: 4),
                    Text(
                      'Prix négociable',
                      style: TextStyle(color: OvanieColors.orange, fontSize: 9.5, fontWeight: FontWeight.w800),
                    ),
                  ],
                ),
              ),
          ],
        ),
        if (product.homeOriginalPrice > 0) ...[
          const SizedBox(height: 3),
          Wrap(
            crossAxisAlignment: WrapCrossAlignment.center,
            spacing: 7,
            children: [
              Text(
                formatFcfa(product.homeOriginalPrice),
                style: const TextStyle(
                  color: OvanieColors.muted,
                  fontSize: 11.5,
                  decoration: TextDecoration.lineThrough,
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 3),
                decoration: BoxDecoration(
                  color: OvanieColors.orange,
                  borderRadius: BorderRadius.circular(5),
                ),
                child: Text(
                  '-${product.discountPercent}%',
                  style: const TextStyle(color: Colors.white, fontSize: 8.5, fontWeight: FontWeight.w900),
                ),
              ),
            ],
          ),
        ],
        const SizedBox(height: 14),
        for (final fact in facts.take(4))
          Padding(
            padding: const EdgeInsets.only(bottom: 6),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Icon(Icons.verified_outlined, color: Color(0xFF8794A8), size: 14),
                const SizedBox(width: 5),
                Expanded(
                  child: Text(
                    fact,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(color: OvanieColors.navy, fontSize: 9.5, height: 1.2),
                  ),
                ),
              ],
            ),
          ),
      ],
    );
  }
}

List<String> _productFacts(ProductModel product) {
  final facts = <String>[];
  if (product.shortDescription.trim().isNotEmpty) facts.add(product.shortDescription.trim());
  if (product.standard.trim().isNotEmpty) facts.add('Norme : ${product.standard.trim()}');
  if (product.usageArea.trim().isNotEmpty) facts.add('Usage : ${product.usageArea.trim()}');
  if (product.materialGrade.trim().isNotEmpty) facts.add(product.materialGrade.trim());
  if (product.packaging.trim().isNotEmpty) facts.add('Conditionnement : ${product.packaging.trim()}');
  for (final entry in product.attributes.entries) {
    final line = '${entry.key} : ${entry.value}';
    if (!facts.contains(line)) facts.add(line);
  }
  if (facts.isEmpty) {
    facts.addAll(const [
      'Produit vérifié OVANIE',
      'Qualité professionnelle',
      'Disponibilité synchronisée avec le catalogue',
    ]);
  }
  return facts;
}

/// Bouton "Négocier" affiché sur la fiche produit quand le produit est
/// négociable. L'utilisateur a explicitement demandé que la négociation
/// exige une authentification, et qu'une fois la dernière offre expirée
/// (2 minutes sans ajout au panier), le produit ne soit plus négociable
/// pour ce client — d'où l'état persisté localement (DeviceStorage) plutôt
/// que recalculé à chaque ouverture de la fiche.
class _NegotiateSection extends StatefulWidget {
  final ProductModel product;
  final int quantity;

  const _NegotiateSection({required this.product, required this.quantity});

  @override
  State<_NegotiateSection> createState() => _NegotiateSectionState();
}

class _NegotiateSectionState extends State<_NegotiateSection> {
  bool _checkingExpiry = true;
  bool _expired = false;

  @override
  void initState() {
    super.initState();
    _loadExpiryState();
  }

  Future<void> _loadExpiryState() async {
    final raw = await DeviceStorage.instance.readString(_negotiateExpiredKey(widget.product.id));
    if (!mounted) return;
    setState(() {
      _expired = raw == '1';
      _checkingExpiry = false;
    });
  }

  Future<void> _openNegotiation() async {
    if (_expired) return;

    if (!SessionStore.instance.isAuthenticated) {
      final action = await _showNegotiationAuthRequired(context);
      if (!mounted || action == null) return;

      final bool? connected;
      if (action == _NegotiateAuthAction.login) {
        connected = await Navigator.of(context).push<bool>(
          MaterialPageRoute<bool>(builder: (_) => const LoginScreen()),
        );
      } else {
        connected = await Navigator.of(context).push<bool>(
          MaterialPageRoute<bool>(builder: (_) => const RegisterScreen()),
        );
      }

      if (!mounted) return;
      if (connected != true && !SessionStore.instance.isAuthenticated) return;
    }

    if (!mounted) return;
    final expiredNow = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      useSafeArea: true,
      builder: (_) => _NegotiationSheet(product: widget.product, quantity: widget.quantity),
    );

    if (expiredNow == true && mounted) {
      setState(() => _expired = true);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_checkingExpiry) return const SizedBox.shrink();

    return SizedBox(
      width: double.infinity,
      child: OutlinedButton.icon(
        onPressed: _expired ? null : _openNegotiation,
        icon: Icon(
          Icons.handshake_outlined,
          size: 18,
          color: _expired ? OvanieColors.muted : OvanieColors.orange,
        ),
        label: Text(
          _expired ? 'Négociation expirée pour ce produit' : 'Négocier',
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: TextStyle(
            fontWeight: FontWeight.w900,
            fontSize: 12,
            color: _expired ? OvanieColors.muted : OvanieColors.orange,
          ),
        ),
        style: OutlinedButton.styleFrom(
          minimumSize: const Size.fromHeight(46),
          side: BorderSide(color: _expired ? OvanieColors.border : OvanieColors.orange),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
        ),
      ),
    );
  }
}

String _negotiateExpiredKey(int productId) => 'ov_negotiate_expired_$productId';
String _negotiateFinalStartedKey(int productId) => 'ov_negotiate_final_started_$productId';

enum _NegotiateAuthAction { login, register }

/// Même maquette que le bottom sheet d'authentification du panier
/// (cart_screen.dart), dupliquée ici car privée à chaque écran.
Future<_NegotiateAuthAction?> _showNegotiationAuthRequired(BuildContext context) {
  return showModalBottomSheet<_NegotiateAuthAction>(
    context: context,
    backgroundColor: Colors.transparent,
    useSafeArea: true,
    builder: (sheetContext) {
      return Container(
        margin: const EdgeInsets.all(14),
        padding: const EdgeInsets.fromLTRB(20, 18, 20, 20),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(24),
          boxShadow: const [
            BoxShadow(color: Color(0x24000000), blurRadius: 28, offset: Offset(0, 12)),
          ],
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 58,
              height: 58,
              decoration: BoxDecoration(
                color: OvanieColors.blue.withValues(alpha: .08),
                shape: BoxShape.circle,
              ),
              child: const Icon(Icons.handshake_outlined, color: OvanieColors.blue, size: 30),
            ),
            const SizedBox(height: 14),
            const Text(
              'Connectez-vous pour négocier',
              textAlign: TextAlign.center,
              style: TextStyle(color: OvanieColors.text, fontSize: 18, fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: 8),
            const Text(
              'La négociation d’un prix est réservée aux clients connectés OVANIE.',
              textAlign: TextAlign.center,
              style: TextStyle(color: OvanieColors.muted, fontSize: 12.5, height: 1.45),
            ),
            const SizedBox(height: 18),
            SizedBox(
              width: double.infinity,
              height: 48,
              child: FilledButton(
                onPressed: () => Navigator.of(sheetContext).pop(_NegotiateAuthAction.login),
                style: FilledButton.styleFrom(
                  backgroundColor: OvanieColors.orange,
                  foregroundColor: Colors.white,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                ),
                child: const Text('Se connecter', style: TextStyle(fontWeight: FontWeight.w900)),
              ),
            ),
            const SizedBox(height: 10),
            SizedBox(
              width: double.infinity,
              height: 48,
              child: OutlinedButton(
                onPressed: () => Navigator.of(sheetContext).pop(_NegotiateAuthAction.register),
                style: OutlinedButton.styleFrom(
                  foregroundColor: OvanieColors.navy,
                  side: const BorderSide(color: OvanieColors.navy),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                ),
                child: const Text('Créer un compte', style: TextStyle(fontWeight: FontWeight.w900)),
              ),
            ),
          ],
        ),
      );
    },
  );
}

/// Bottom sheet de négociation guidée : offres réelles une par une
/// (price_p1/p2/p3 côté serveur), "Ajouter au panier à ce prix" ou "Voir
/// un meilleur prix", et un compte à rebours de 2 minutes sur la dernière
/// offre. Retourne `true` via Navigator.pop si la négociation a expiré,
/// pour que _NegotiateSection désactive durablement le bouton "Négocier".
class _NegotiationSheet extends StatefulWidget {
  final ProductModel product;
  final int quantity;

  const _NegotiationSheet({required this.product, required this.quantity});

  @override
  State<_NegotiationSheet> createState() => _NegotiationSheetState();
}

class _NegotiationSheetState extends State<_NegotiationSheet> {
  static const NegotiationRepository _repository = NegotiationRepository();

  bool _loading = true;
  Object? _loadError;
  List<int> _offers = const [];
  int _finalOfferTtlSeconds = 120;
  int _stepIndex = 0;
  int? _remainingSeconds;
  Timer? _timer;
  bool _submitting = false;
  bool _accepted = false;
  String? _resultMessage;
  bool _resultIsError = false;

  @override
  void initState() {
    super.initState();
    _loadOffers();
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  bool get _isLastStep => _offers.isNotEmpty && _stepIndex >= _offers.length - 1;

  int get _effectiveQuantity {
    final minimum = widget.product.minOrderQuantity <= 0 ? 1 : widget.product.minOrderQuantity;
    return widget.quantity < minimum ? minimum : widget.quantity;
  }

  Future<void> _loadOffers() async {
    setState(() {
      _loading = true;
      _loadError = null;
    });

    try {
      final offers = await _repository.getOffers(widget.product.slug);
      if (!mounted) return;
      setState(() {
        _offers = offers.amounts;
        _finalOfferTtlSeconds = offers.finalOfferTtlSeconds;
        _stepIndex = 0;
        _loading = false;
      });
      unawaited(_maybeStartTimer());
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _loadError = error;
        _loading = false;
      });
    }
  }

  Future<void> _maybeStartTimer() async {
    _timer?.cancel();
    if (!_isLastStep) {
      if (mounted) setState(() => _remainingSeconds = null);
      return;
    }

    final key = _negotiateFinalStartedKey(widget.product.id);
    final raw = await DeviceStorage.instance.readString(key);
    var startedAtMs = int.tryParse(raw ?? '');
    if (startedAtMs == null) {
      startedAtMs = DateTime.now().millisecondsSinceEpoch;
      await DeviceStorage.instance.writeString(key, '$startedAtMs');
    }
    final effectiveStart = startedAtMs;

    void tick() {
      final elapsedMs = DateTime.now().millisecondsSinceEpoch - effectiveStart;
      final remainingMs = (_finalOfferTtlSeconds * 1000) - elapsedMs;
      if (remainingMs <= 0) {
        _timer?.cancel();
        unawaited(_expireNegotiation());
        return;
      }
      if (!mounted) return;
      setState(() => _remainingSeconds = (remainingMs / 1000).ceil());
    }

    tick();
    _timer = Timer.periodic(const Duration(seconds: 1), (_) => tick());
  }

  Future<void> _expireNegotiation() async {
    await DeviceStorage.instance.writeString(_negotiateExpiredKey(widget.product.id), '1');
    await DeviceStorage.instance.remove(_negotiateFinalStartedKey(widget.product.id));
    if (!mounted) return;
    Navigator.of(context).pop(true);
  }

  void _showNext() {
    if (_isLastStep) return;
    _timer?.cancel();
    setState(() {
      _stepIndex += 1;
      _remainingSeconds = null;
      _resultMessage = null;
    });
    unawaited(_maybeStartTimer());
  }

  Future<void> _acceptCurrentOffer() async {
    if (_offers.isEmpty || _submitting) return;
    final proposedPrice = _offers[_stepIndex];

    setState(() {
      _submitting = true;
      _resultMessage = null;
    });

    try {
      final result = await _repository.acceptOffer(widget.product.slug, proposedPrice);
      if (!result.accepted || result.negotiationId == null) {
        if (!mounted) return;
        setState(() {
          _submitting = false;
          _resultMessage = result.message.isNotEmpty ? result.message : 'Cette offre n’est plus disponible.';
          _resultIsError = true;
        });
        return;
      }

      final cartMessage = await _repository.addNegotiatedToCart(
        productId: widget.product.id,
        negotiatedPrice: proposedPrice,
        negotiationId: result.negotiationId!,
        quantity: _effectiveQuantity,
      );

      CartStore.instance.applyNegotiatedLine(
        widget.product,
        _effectiveQuantity,
        proposedPrice.toDouble(),
      );

      _timer?.cancel();
      await DeviceStorage.instance.remove(_negotiateFinalStartedKey(widget.product.id));

      if (!mounted) return;
      setState(() {
        _submitting = false;
        _accepted = true;
        _resultMessage = cartMessage;
        _resultIsError = false;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _submitting = false;
        _resultMessage = ApiClient.friendlyError(error);
        _resultIsError = true;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return DraggableScrollableSheet(
      initialChildSize: 0.55,
      minChildSize: 0.35,
      maxChildSize: 0.85,
      expand: false,
      builder: (context, scrollController) {
        return Container(
          decoration: const BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.vertical(top: Radius.circular(22)),
          ),
          child: ListView(
            controller: scrollController,
            padding: const EdgeInsets.fromLTRB(20, 14, 20, 24),
            children: [
              Center(
                child: Container(
                  width: 40,
                  height: 4,
                  decoration: BoxDecoration(color: OvanieColors.border, borderRadius: BorderRadius.circular(2)),
                ),
              ),
              const SizedBox(height: 16),
              const Row(
                children: [
                  Icon(Icons.handshake_outlined, color: OvanieColors.orange, size: 22),
                  SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      'Proposition OVANIE',
                      style: TextStyle(color: OvanieColors.navy, fontSize: 16, fontWeight: FontWeight.w900),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 4),
              Text(
                widget.product.name,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(color: OvanieColors.muted, fontSize: 11.5),
              ),
              const SizedBox(height: 18),
              if (_loading)
                const Padding(
                  padding: EdgeInsets.symmetric(vertical: 30),
                  child: Center(child: CircularProgressIndicator(color: OvanieColors.orange)),
                )
              else if (_loadError != null) ...[
                Text(
                  ApiClient.friendlyError(_loadError!),
                  style: const TextStyle(color: OvanieColors.danger, fontSize: 12.5),
                ),
                const SizedBox(height: 12),
                SizedBox(
                  width: double.infinity,
                  height: 44,
                  child: OutlinedButton(
                    onPressed: _loadOffers,
                    child: const Text('Réessayer'),
                  ),
                ),
              ] else if (_accepted) ...[
                const Icon(Icons.check_circle, color: OvanieColors.success, size: 42),
                const SizedBox(height: 10),
                Text(
                  _resultMessage ?? 'Produit ajouté au panier à ce prix !',
                  textAlign: TextAlign.center,
                  style: const TextStyle(color: OvanieColors.navy, fontSize: 13, fontWeight: FontWeight.w700),
                ),
                const SizedBox(height: 16),
                SizedBox(
                  width: double.infinity,
                  height: 46,
                  child: FilledButton(
                    onPressed: () => Navigator.of(context).pop(false),
                    style: FilledButton.styleFrom(
                      backgroundColor: OvanieColors.orange,
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                    ),
                    child: const Text('Fermer', style: TextStyle(fontWeight: FontWeight.w900)),
                  ),
                ),
              ] else if (_offers.isNotEmpty) ...[
                Text(
                  'Offre ${_stepIndex + 1} sur ${_offers.length}',
                  style: const TextStyle(
                    color: OvanieColors.muted,
                    fontSize: 10.5,
                    fontWeight: FontWeight.w800,
                    letterSpacing: .3,
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  formatFcfa(_offers[_stepIndex]),
                  style: const TextStyle(color: OvanieColors.blue, fontSize: 26, fontWeight: FontWeight.w900),
                ),
                const SizedBox(height: 4),
                Text(
                  _isLastStep
                      ? 'Dernière offre possible sur ce produit.'
                      : 'OVANIE vous propose ce prix. Vous pouvez demander mieux.',
                  style: const TextStyle(color: OvanieColors.muted, fontSize: 11),
                ),
                if (_isLastStep && _remainingSeconds != null) ...[
                  const SizedBox(height: 10),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                    decoration: BoxDecoration(color: const Color(0xFFFDEDEC), borderRadius: BorderRadius.circular(6)),
                    child: Text(
                      'Dernière offre : ${_formatNegotiationDuration(_remainingSeconds!)} restantes',
                      style: const TextStyle(color: OvanieColors.danger, fontSize: 10.5, fontWeight: FontWeight.w800),
                    ),
                  ),
                ],
                if (_resultMessage != null) ...[
                  const SizedBox(height: 10),
                  Text(
                    _resultMessage!,
                    style: TextStyle(
                      color: _resultIsError ? OvanieColors.danger : OvanieColors.success,
                      fontSize: 11.5,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ],
                const SizedBox(height: 18),
                SizedBox(
                  width: double.infinity,
                  height: 48,
                  child: FilledButton.icon(
                    onPressed: _submitting ? null : _acceptCurrentOffer,
                    icon: _submitting
                        ? const SizedBox(
                            width: 16,
                            height: 16,
                            child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                          )
                        : const Icon(Icons.shopping_cart_outlined, size: 18),
                    label: const Text(
                      'Ajouter au panier à ce prix',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(fontWeight: FontWeight.w900),
                    ),
                    style: FilledButton.styleFrom(
                      backgroundColor: OvanieColors.orange,
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                    ),
                  ),
                ),
                if (!_isLastStep) ...[
                  const SizedBox(height: 10),
                  SizedBox(
                    width: double.infinity,
                    height: 46,
                    child: OutlinedButton(
                      onPressed: _submitting ? null : _showNext,
                      style: OutlinedButton.styleFrom(
                        foregroundColor: OvanieColors.orange,
                        side: const BorderSide(color: OvanieColors.orange),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                      ),
                      child: const Text('Voir un meilleur prix', style: TextStyle(fontWeight: FontWeight.w800)),
                    ),
                  ),
                ],
              ],
            ],
          ),
        );
      },
    );
  }
}

String _formatNegotiationDuration(int totalSeconds) {
  final minutes = totalSeconds ~/ 60;
  final seconds = totalSeconds % 60;
  return '$minutes:${seconds.toString().padLeft(2, '0')}';
}

class _QuantitySection extends StatelessWidget {
  final ProductModel product;
  final int quantity;
  final ValueChanged<int> onSelect;
  final VoidCallback onMinus;
  final VoidCallback onPlus;

  const _QuantitySection({
    required this.product,
    required this.quantity,
    required this.onSelect,
    required this.onMinus,
    required this.onPlus,
  });

  @override
  Widget build(BuildContext context) {
    final minimum = product.minOrderQuantity <= 0 ? 1 : product.minOrderQuantity;
    final tiers = <int>[minimum, 5, 10, 20]
        .where((value) => value >= minimum)
        .toSet()
        .take(4)
        .toList(growable: false);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          'Choisir la quantité',
          style: TextStyle(color: OvanieColors.navy, fontSize: 14, fontWeight: FontWeight.w900),
        ),
        const SizedBox(height: 9),
        Row(
          children: List.generate(tiers.length, (index) {
            final value = tiers[index];
            final active = quantity == value;
            final double totalWeight = product.weightKg > 0 ? product.weightKg * value : 0.0;
            final tierDiscount = _quantityDiscount(product, value);
            return Expanded(
              child: Padding(
                padding: EdgeInsets.only(right: index == tiers.length - 1 ? 0 : 7),
                child: InkWell(
                  onTap: () => onSelect(value),
                  borderRadius: BorderRadius.circular(8),
                  child: Container(
                    height: 58,
                    padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 7),
                    decoration: BoxDecoration(
                      color: active ? const Color(0xFFFFFBF8) : Colors.white,
                      borderRadius: BorderRadius.circular(8),
                      border: Border.all(
                        color: active ? OvanieColors.orange : const Color(0xFFE0E5EC),
                        width: active ? 1.2 : 1,
                      ),
                    ),
                    child: Stack(
                      children: [
                        Align(
                          alignment: Alignment.center,
                          child: Column(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Text(
                                '$value ${value > 1 ? _pluralUnit(product.unitLabel) : product.unitLabel}',
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: TextStyle(
                                  color: active ? OvanieColors.orange : OvanieColors.navy,
                                  fontSize: 10.5,
                                  fontWeight: FontWeight.w800,
                                ),
                              ),
                              const SizedBox(height: 4),
                              Text(
                                totalWeight > 0 ? '${ProductModel.compactNumber(totalWeight)} kg' : product.homeSubtitle,
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: const TextStyle(color: OvanieColors.muted, fontSize: 9.5),
                              ),
                            ],
                          ),
                        ),
                        if (tierDiscount != null)
                          Positioned(
                            right: 0,
                            top: 0,
                            child: Text(
                              '-$tierDiscount%',
                              style: const TextStyle(color: Color(0xFF18A957), fontSize: 8.5, fontWeight: FontWeight.w800),
                            ),
                          ),
                      ],
                    ),
                  ),
                ),
              ),
            );
          }),
        ),
        const SizedBox(height: 12),
        Row(
          children: [
            const Text('Quantité', style: TextStyle(color: OvanieColors.navy, fontSize: 11.5)),
            const SizedBox(width: 12),
            Container(
              height: 40,
              decoration: BoxDecoration(
                color: Colors.white,
                border: Border.all(color: const Color(0xFFE0E5EC)),
                borderRadius: BorderRadius.circular(8),
              ),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  IconButton(
                    visualDensity: VisualDensity.compact,
                    onPressed: onMinus,
                    icon: const Icon(Icons.remove_rounded, size: 18),
                  ),
                  SizedBox(
                    width: 30,
                    child: Text(
                      '$quantity',
                      textAlign: TextAlign.center,
                      style: const TextStyle(color: OvanieColors.navy, fontWeight: FontWeight.w900),
                    ),
                  ),
                  IconButton(
                    visualDensity: VisualDensity.compact,
                    onPressed: onPlus,
                    icon: const Icon(Icons.add_rounded, size: 18),
                  ),
                ],
              ),
            ),
            const SizedBox(width: 12),
            if (product.weightKg > 0)
              Text(
                '${ProductModel.compactNumber(product.weightKg * quantity)} kg',
                style: const TextStyle(color: OvanieColors.muted, fontSize: 11.5),
              ),
          ],
        ),
      ],
    );
  }
}

String _pluralUnit(String unit) {
  final value = unit.trim();
  if (value.isEmpty || value == 'unité') return 'unités';
  if (value.endsWith('s')) return value;
  return '${value}s';
}

int? _quantityDiscount(ProductModel product, int quantity) {
  final candidates = <String>[
    'remise_$quantity',
    'discount_$quantity',
    'remise $quantity',
  ];
  for (final entry in product.attributes.entries) {
    final key = entry.key.toLowerCase().replaceAll('%', '').trim();
    if (!candidates.contains(key)) continue;
    final digits = RegExp(r'\d+').firstMatch(entry.value)?.group(0);
    return int.tryParse(digits ?? '');
  }
  return null;
}

class _DeliveryPanel extends StatelessWidget {
  const _DeliveryPanel();

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      decoration: BoxDecoration(
        color: const Color(0xFFFFF5EF),
        borderRadius: BorderRadius.circular(10),
      ),
      child: Row(
        children: [
          const Icon(Icons.local_shipping_outlined, color: OvanieColors.orange, size: 30),
          const SizedBox(width: 12),
          const Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Livraison estimée',
                  style: TextStyle(color: OvanieColors.navy, fontSize: 11.5, fontWeight: FontWeight.w800),
                ),
                SizedBox(height: 3),
                Text(
                  'Délai de livraison confirmé avant validation',
                  style: TextStyle(color: OvanieColors.navy, fontSize: 9.5),
                ),
                SizedBox(height: 3),
                Text(
                  'Voir les options de livraison ›',
                  style: TextStyle(color: OvanieColors.orange, fontSize: 9.5, fontWeight: FontWeight.w700),
                ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          const Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Frais de livraison affichés avant validation',
                  style: TextStyle(color: OvanieColors.navy, fontSize: 9.5),
                ),
                SizedBox(height: 8),
                Text(
                  'Retour possible selon les conditions OVANIE',
                  style: TextStyle(color: OvanieColors.navy, fontSize: 9.5),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _TrustRow extends StatelessWidget {
  const _TrustRow();

  @override
  Widget build(BuildContext context) {
    final items = const <_TrustItem>[
      _TrustItem(Icons.verified_user_outlined, 'Produits 100% vérifiés', 'Qualité garantie'),
      _TrustItem(Icons.lock_outline_rounded, 'Paiement sécurisé', 'Plusieurs moyens'),
      _TrustItem(Icons.headset_mic_outlined, 'Support 7j/7', 'À votre écoute'),
      _TrustItem(Icons.workspace_premium_outlined, 'Meilleurs prix', 'Garantie'),
    ];
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: const Color(0xFFE1E6ED)),
      ),
      child: Row(
        children: List.generate(items.length, (index) {
          final item = items[index];
          return Expanded(
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 10),
              decoration: BoxDecoration(
                border: index == items.length - 1
                    ? null
                    : const Border(right: BorderSide(color: Color(0xFFE7EAF0))),
              ),
              child: Column(
                children: [
                  Icon(item.icon, color: OvanieColors.navy, size: 20),
                  const SizedBox(height: 5),
                  Text(
                    item.title,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    textAlign: TextAlign.center,
                    style: const TextStyle(color: OvanieColors.navy, fontSize: 8.5, fontWeight: FontWeight.w800),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    item.subtitle,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    textAlign: TextAlign.center,
                    style: const TextStyle(color: OvanieColors.muted, fontSize: 7.8),
                  ),
                ],
              ),
            ),
          );
        }),
      ),
    );
  }
}

class _TrustItem {
  final IconData icon;
  final String title;
  final String subtitle;

  const _TrustItem(this.icon, this.title, this.subtitle);
}

class _ProductTabs extends StatelessWidget {
  final int selected;
  final ValueChanged<int> onChanged;

  const _ProductTabs({required this.selected, required this.onChanged});

  @override
  Widget build(BuildContext context) {
    const labels = <String>['Description', 'Caractéristiques', 'Avis', 'Livraison & retour'];
    return Container(
      decoration: const BoxDecoration(
        border: Border(bottom: BorderSide(color: Color(0xFFE1E6ED))),
      ),
      child: Row(
        children: List.generate(labels.length, (index) {
          final active = selected == index;
          return Expanded(
            child: InkWell(
              onTap: () => onChanged(index),
              child: Container(
                height: 38,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  border: active
                      ? const Border(bottom: BorderSide(color: OvanieColors.orange, width: 2))
                      : null,
                ),
                child: Text(
                  labels[index],
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: active ? OvanieColors.orange : OvanieColors.navy,
                    fontSize: 9.5,
                    fontWeight: active ? FontWeight.w800 : FontWeight.w600,
                  ),
                ),
              ),
            ),
          );
        }),
      ),
    );
  }
}

class _TabContent extends StatelessWidget {
  final ProductModel product;
  final int selected;

  const _TabContent({required this.product, required this.selected});

  @override
  Widget build(BuildContext context) {
    if (selected == 1) return _Characteristics(product: product);
    if (selected == 2) return _ReviewsSummary(product: product);
    if (selected == 3) return _DeliveryText(product: product);

    final description = product.description.trim().isNotEmpty
        ? product.description.trim()
        : product.shortDescription.trim().isNotEmpty
            ? product.shortDescription.trim()
            : 'Les informations détaillées de ce produit seront complétées depuis le catalogue OVANIE.';

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          description,
          style: const TextStyle(color: OvanieColors.navy, fontSize: 11.5, height: 1.55),
        ),
        const SizedBox(height: 12),
        ..._descriptionFacts(product).map(
          (line) => Padding(
            padding: const EdgeInsets.only(bottom: 4),
            child: Text(
              '• $line',
              style: const TextStyle(color: OvanieColors.navy, fontSize: 10.8, height: 1.35),
            ),
          ),
        ),
      ],
    );
  }
}

List<String> _descriptionFacts(ProductModel product) {
  final lines = <String>[];
  if (product.materialGrade.isNotEmpty) lines.add('Type : ${product.materialGrade}');
  if (product.usageArea.isNotEmpty) lines.add('Usage : ${product.usageArea}');
  if (product.packaging.isNotEmpty) lines.add('Conditionnement : ${product.packaging}');
  if (product.standard.isNotEmpty) lines.add('Norme : ${product.standard}');
  if (product.weightKg > 0) lines.add('Poids : ${ProductModel.compactNumber(product.weightKg)} kg');
  return lines;
}

class _Characteristics extends StatelessWidget {
  final ProductModel product;

  const _Characteristics({required this.product});

  @override
  Widget build(BuildContext context) {
    final entries = <MapEntry<String, String>>[];
    if (product.brand.isNotEmpty) entries.add(MapEntry('Marque', product.brand));
    if (product.packaging.isNotEmpty) entries.add(MapEntry('Conditionnement', product.packaging));
    if (product.standard.isNotEmpty) entries.add(MapEntry('Norme', product.standard));
    if (product.originCountry.isNotEmpty) entries.add(MapEntry('Origine', product.originCountry));
    if (product.dimensionsLabel.isNotEmpty) entries.add(MapEntry('Dimensions', product.dimensionsLabel));
    entries.addAll(product.technicalSpecs.entries);
    for (final entry in product.attributes.entries) {
      if (!entries.any((item) => item.key.toLowerCase() == entry.key.toLowerCase())) {
        entries.add(entry);
      }
    }

    if (entries.isEmpty && product.technicalDetails.isEmpty) {
      return const Text(
        'Aucune caractéristique technique supplémentaire n’est disponible.',
        style: TextStyle(color: OvanieColors.muted, fontSize: 11.5),
      );
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (product.technicalDetails.isNotEmpty) ...[
          Text(
            product.technicalDetails,
            style: const TextStyle(color: OvanieColors.navy, fontSize: 11.5, height: 1.5),
          ),
          const SizedBox(height: 12),
        ],
        for (final entry in entries)
          Padding(
            padding: const EdgeInsets.only(bottom: 7),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                SizedBox(
                  width: 120,
                  child: Text(
                    entry.key,
                    style: const TextStyle(color: OvanieColors.navy, fontSize: 10.5, fontWeight: FontWeight.w800),
                  ),
                ),
                Expanded(
                  child: Text(
                    entry.value,
                    style: const TextStyle(color: OvanieColors.muted, fontSize: 10.5),
                  ),
                ),
              ],
            ),
          ),
      ],
    );
  }
}

class _ReviewsSummary extends StatelessWidget {
  final ProductModel product;

  const _ReviewsSummary({required this.product});

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        const Icon(Icons.star_rounded, color: OvanieColors.warning, size: 28),
        const SizedBox(width: 8),
        Text(
          product.rating?.toStringAsFixed(1) ?? '—',
          style: const TextStyle(color: OvanieColors.navy, fontSize: 24, fontWeight: FontWeight.w900),
        ),
        const SizedBox(width: 8),
        Text(
          '${product.reviewsCount} avis clients',
          style: const TextStyle(color: OvanieColors.muted, fontSize: 11.5),
        ),
      ],
    );
  }
}

class _DeliveryText extends StatelessWidget {
  final ProductModel product;

  const _DeliveryText({required this.product});

  @override
  Widget build(BuildContext context) {
    final parts = <String>[];
    if (product.supplyDelay.trim().isNotEmpty) {
      parts.add('Délai indicatif : ${product.supplyDelay.trim()}.');
    } else {
      parts.add('Le délai estimé et les frais applicables sont présentés avant la validation de votre commande.');
    }
    if (product.returnPolicy.trim().isNotEmpty) {
      parts.add(product.returnPolicy.trim());
    } else {
      parts.add('Les retours sont pris en charge conformément aux conditions OVANIE.');
    }

    return Text(
      parts.join(' '),
      style: const TextStyle(color: OvanieColors.navy, fontSize: 11.5, height: 1.55),
    );
  }
}

class _SimilarProductCard extends StatelessWidget {
  final ProductModel product;
  final VoidCallback onTap;

  const _SimilarProductCard({required this.product, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(10),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(10),
        child: Container(
          width: 132,
          padding: const EdgeInsets.all(8),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(10),
            border: Border.all(color: const Color(0xFFE1E6ED)),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Stack(
                  children: [
                    Positioned.fill(
                      child: product.imageUrl.isEmpty
                          ? const Icon(Icons.inventory_2_outlined, color: Color(0xFFB7C0CC), size: 40)
                          : Image.network(
                              product.imageUrl,
                              fit: BoxFit.contain,
                              errorBuilder: (_, __, ___) => const Icon(Icons.broken_image_outlined),
                            ),
                    ),
                    const Positioned(
                      right: 0,
                      top: 0,
                      child: Icon(Icons.favorite_border_rounded, color: OvanieColors.navy, size: 18),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 5),
              Text(
                product.name,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(color: OvanieColors.navy, fontSize: 10.5, height: 1.15, fontWeight: FontWeight.w800),
              ),
              const SizedBox(height: 3),
              Text(
                product.homeSubtitle,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(color: OvanieColors.muted, fontSize: 9),
              ),
              const SizedBox(height: 5),
              Text(
                formatFcfa(product.homeDisplayPrice),
                style: const TextStyle(color: OvanieColors.orange, fontSize: 11.5, fontWeight: FontWeight.w900),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _ProductActionBar extends StatelessWidget {
  final bool canBuy;
  final VoidCallback onAdd;
  final VoidCallback onBuy;

  const _ProductActionBar({
    required this.canBuy,
    required this.onAdd,
    required this.onBuy,
  });

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white,
      child: SafeArea(
        top: false,
        child: Container(
          padding: const EdgeInsets.fromLTRB(10, 8, 10, 9),
          decoration: const BoxDecoration(
            border: Border(top: BorderSide(color: Color(0xFFE1E6ED))),
          ),
          child: Row(
            children: [
              Expanded(
                child: SizedBox(
                  height: 48,
                  child: FilledButton.icon(
                    onPressed: canBuy ? onAdd : null,
                    icon: const Icon(Icons.shopping_cart_outlined, size: 19),
                    label: const Text('Ajouter au panier', maxLines: 1, overflow: TextOverflow.ellipsis),
                    style: FilledButton.styleFrom(
                      backgroundColor: OvanieColors.orange,
                      disabledBackgroundColor: const Color(0xFFE6E9EE),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                      textStyle: const TextStyle(fontSize: 11.5, fontWeight: FontWeight.w800),
                    ),
                  ),
                ),
              ),
              const SizedBox(width: 7),
              Expanded(
                child: SizedBox(
                  height: 48,
                  child: FilledButton(
                    onPressed: canBuy ? onBuy : null,
                    style: FilledButton.styleFrom(
                      backgroundColor: OvanieColors.orange,
                      disabledBackgroundColor: const Color(0xFFE6E9EE),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                      textStyle: const TextStyle(fontSize: 11.5, fontWeight: FontWeight.w800),
                    ),
                    child: const Text('Acheter maintenant', maxLines: 1, overflow: TextOverflow.ellipsis),
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

class _DetailError extends StatelessWidget {
  final String message;
  final VoidCallback onRetry;

  const _DetailError({required this.message, required this.onRetry});

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: const Color(0xFFFFF3F2),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: const Color(0xFFFFD3CF)),
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
