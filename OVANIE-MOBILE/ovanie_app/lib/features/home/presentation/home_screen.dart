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
import '../../products/domain/product_model.dart';
import '../../products/presentation/product_detail_screen.dart';
import '../../search/presentation/search_screen.dart';
import '../data/marketplace_repository.dart';
import 'best_sellers_screen.dart';
import 'black_friday_screen.dart';
import 'flash_sale_screen.dart';
import 'new_arrivals_screen.dart';

typedef HomeCatalogNavigation = void Function({
  String? categorySlug,
  String? categoryName,
  String? offer,
});

class HomeScreen extends StatefulWidget {
  final VoidCallback? onOpenCart;
  final HomeCatalogNavigation? onOpenCatalog;

  const HomeScreen({
    super.key,
    this.onOpenCart,
    this.onOpenCatalog,
  });

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  final MarketplaceRepository _repository = const MarketplaceRepository();
  late Future<HomeMarketplaceData> _future;
  bool _refreshingAfterFlash = false;

  @override
  void initState() {
    super.initState();
    _future = _repository.getHomeData();
  }

  Future<void> _reload() async {
    final next = _repository.getHomeData();
    if (mounted) {
      setState(() => _future = next);
    }
    await next;
  }

  void _refreshAfterFlashExpiration() {
    if (_refreshingAfterFlash) return;
    _refreshingAfterFlash = true;

    Future<void>.delayed(const Duration(milliseconds: 650), () async {
      try {
        await _reload();
      } catch (_) {
        // Le RefreshIndicator / ApiErrorCard permettra un nouvel essai manuel.
      } finally {
        _refreshingAfterFlash = false;
      }
    });
  }

  void _openCatalog({
    String? categorySlug,
    String? categoryName,
    String? offer,
  }) {
    widget.onOpenCatalog?.call(
      categorySlug: categorySlug,
      categoryName: categoryName,
      offer: offer,
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      body: SafeArea(
        bottom: false,
        child: RefreshIndicator(
          color: OvanieColors.orange,
          onRefresh: _reload,
          child: FutureBuilder<HomeMarketplaceData>(
            future: _future,
            builder: (context, snapshot) {
              if (snapshot.connectionState != ConnectionState.done) {
                return const _HomeLoadingView();
              }

              if (snapshot.hasError) {
                return ListView(
                  physics: const AlwaysScrollableScrollPhysics(),
                  padding: const EdgeInsets.all(16),
                  children: [
                    const SizedBox(height: 100),
                    ApiErrorCard(
                      message: ApiClient.friendlyError(snapshot.error!),
                      onRetry: _reload,
                    ),
                  ],
                );
              }

              final data = snapshot.data!;
              return _OvanieHomeContent(
                data: data,
                onOpenCatalog: _openCatalog,
                onFlashExpired: _refreshAfterFlashExpiration,
              );
            },
          ),
        ),
      ),
    );
  }
}

class _HomeLoadingView extends StatelessWidget {
  const _HomeLoadingView();

  @override
  Widget build(BuildContext context) {
    return const CustomScrollView(
      physics: AlwaysScrollableScrollPhysics(),
      slivers: [
        SliverFillRemaining(
          hasScrollBody: false,
          child: Center(
            child: CircularProgressIndicator(color: OvanieColors.orange),
          ),
        ),
      ],
    );
  }
}

class _OvanieHomeContent extends StatelessWidget {
  final HomeMarketplaceData data;
  final HomeCatalogNavigation onOpenCatalog;
  final VoidCallback onFlashExpired;

  const _OvanieHomeContent({
    required this.data,
    required this.onOpenCatalog,
    required this.onFlashExpired,
  });

  HomeMarketplaceSection? _section(String mode) {
    for (final section in data.sections) {
      if (section.mode == mode) return section;
    }
    return null;
  }

  @override
  Widget build(BuildContext context) {
    final flash = _section('flash');
    final bestSellers = _section('best_sellers');
    final latest = _section('latest');

    return CustomScrollView(
      physics: const AlwaysScrollableScrollPhysics(
        parent: BouncingScrollPhysics(),
      ),
      slivers: [
        SliverToBoxAdapter(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              _SearchBar(
                onTap: () => Navigator.of(context).push(
                  MaterialPageRoute<void>(
                    builder: (_) => const SearchScreen(),
                  ),
                ),
              ),
              _HomeHero(onTap: () => onOpenCatalog()),
              if (data.categories.isNotEmpty)
                _PopularCategories(
                  items: data.categories.take(6).toList(growable: false),
                  onTap: (category) => onOpenCatalog(
                    categorySlug: category.slug,
                    categoryName: category.name,
                  ),
                  onViewAll: () => onOpenCatalog(),
                ),
              if (flash != null && flash.products.isNotEmpty)
                _ProductSection(
                  title: 'Vente flash',
                  type: _HomeProductCardType.flash,
                  section: flash,
                  flashEndsAtUtc: data.flashSaleEndsAtUtc,
                  serverClockOffset: data.serverClockOffset,
                  onFlashExpired: onFlashExpired,
                  onViewAll: () => Navigator.of(context).push(
                    MaterialPageRoute<void>(
                      builder: (_) => FlashSaleScreen(offer: flash.catalogOffer),
                    ),
                  ),
                ),
              if (bestSellers != null && bestSellers.products.isNotEmpty)
                _ProductSection(
                  title: 'Meilleures ventes',
                  type: _HomeProductCardType.bestSeller,
                  section: bestSellers,
                  onViewAll: () => Navigator.of(context).push(
                    MaterialPageRoute<void>(
                      builder: (_) => BestSellersScreen(offer: bestSellers.catalogOffer),
                    ),
                  ),
                ),
              if (latest != null && latest.products.isNotEmpty)
                _ProductSection(
                  title: 'Nouveautés',
                  type: _HomeProductCardType.newArrival,
                  section: latest,
                  onViewAll: () => Navigator.of(context).push(
                    MaterialPageRoute<void>(
                      builder: (_) => NewArrivalsScreen(offer: latest.catalogOffer),
                    ),
                  ),
                ),
              _BlackFridayBanner(
                maxDiscountPercent: data.blackFridayMaxDiscountPercent,
                productCount: data.blackFridayProductCount,
                onTap: () => Navigator.of(context).push(
                  MaterialPageRoute<void>(
                    builder: (_) => const BlackFridayScreen(),
                  ),
                ),
              ),
              const SizedBox(height: 8),
            ],
          ),
        ),
      ],
    );
  }
}


class _SearchBar extends StatelessWidget {
  final VoidCallback onTap;

  const _SearchBar({required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
      child: Material(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(14),
          child: Container(
            height: 54,
            padding: const EdgeInsets.symmetric(horizontal: 15),
            decoration: BoxDecoration(
              border: Border.all(color: const Color(0xFFD8DDE5)),
              borderRadius: BorderRadius.circular(14),
            ),
            child: const Row(
              children: [
                Icon(
                  Icons.search_rounded,
                  size: 24,
                  color: Color(0xFF566174),
                ),
                SizedBox(width: 11),
                Expanded(
                  child: Text(
                    'Rechercher un produit, une marque...',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      fontSize: 14.5,
                      color: Color(0xFF7B8493),
                      fontWeight: FontWeight.w400,
                    ),
                  ),
                ),
                SizedBox(width: 8),
                Icon(
                  Icons.center_focus_weak_rounded,
                  size: 24,
                  color: Color(0xFF152139),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}


class _HomeHero extends StatelessWidget {
  final VoidCallback onTap;

  const _HomeHero({required this.onTap});

  @override
  Widget build(BuildContext context) {
    final phone = MediaQuery.sizeOf(context).width < 520;

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(14),
        child: AspectRatio(
          // Sur téléphone réel, un hero un peu plus haut rend les textes et
          // preuves lisibles sans modifier l'identité visuelle de la maquette.
          aspectRatio: phone ? 2.05 : 3.13,
          child: Stack(
            fit: StackFit.expand,
            children: [
              Image.asset(
                'assets/images/hero.png',
                fit: BoxFit.cover,
                alignment: Alignment.center,
              ),
              const DecoratedBox(
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    begin: Alignment.centerLeft,
                    end: Alignment.centerRight,
                    stops: [0, .42, .72, 1],
                    colors: [
                      Color(0xEA071525),
                      Color(0xB8071525),
                      Color(0x3D071525),
                      Color(0x00071525),
                    ],
                  ),
                ),
              ),
              Padding(
                padding: EdgeInsets.fromLTRB(
                  phone ? 16 : 18,
                  phone ? 14 : 12,
                  12,
                  phone ? 9 : 7,
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'MATÉRIAUX &\nÉQUIPEMENTS BTP',
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: phone ? 18 : 15.5,
                        height: 1.02,
                        fontWeight: FontWeight.w900,
                        letterSpacing: .1,
                      ),
                    ),
                    SizedBox(height: phone ? 6 : 5),
                    Text(
                      'Qualité professionnelle, livraison fiable',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: phone ? 10 : 8,
                        fontWeight: FontWeight.w400,
                      ),
                    ),
                    SizedBox(height: phone ? 8 : 7),
                    Row(
                      children: [
                        _HeroProof(
                          icon: Icons.verified_user_outlined,
                          label: 'Produits vérifiés',
                          phone: phone,
                        ),
                        SizedBox(width: phone ? 10 : 8),
                        _HeroProof(
                          icon: Icons.credit_card_outlined,
                          label: 'Paiement\nsécurisé',
                          phone: phone,
                        ),
                        SizedBox(width: phone ? 10 : 8),
                        _HeroProof(
                          icon: Icons.local_shipping_outlined,
                          label: 'Livraison\nOVANIE',
                          phone: phone,
                        ),
                      ],
                    ),
                    const Spacer(),
                    SizedBox(
                      height: phone ? 32 : 23,
                      child: FilledButton(
                        onPressed: onTap,
                        style: FilledButton.styleFrom(
                          backgroundColor: OvanieColors.orange,
                          foregroundColor: Colors.white,
                          padding: EdgeInsets.symmetric(
                            horizontal: phone ? 14 : 10,
                          ),
                          minimumSize: Size.zero,
                          tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(phone ? 8 : 6),
                          ),
                        ),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Text(
                              'Explorer le catalogue',
                              style: TextStyle(
                                fontSize: phone ? 11.5 : 8.2,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                            SizedBox(width: phone ? 8 : 6),
                            Icon(
                              Icons.arrow_forward_rounded,
                              size: phone ? 16 : 11,
                            ),
                          ],
                        ),
                      ),
                    ),
                    SizedBox(height: phone ? 4 : 3),
                    const Align(
                      alignment: Alignment.center,
                      child: _HeroDots(),
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


class _HeroProof extends StatelessWidget {
  final IconData icon;
  final String label;
  final bool phone;

  const _HeroProof({
    required this.icon,
    required this.label,
    required this.phone,
  });

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          width: phone ? 20 : 15,
          height: phone ? 20 : 15,
          decoration: const BoxDecoration(
            color: Color(0x52FFFFFF),
            shape: BoxShape.circle,
          ),
          alignment: Alignment.center,
          child: Icon(
            icon,
            size: phone ? 13 : 9,
            color: const Color(0xFFBBD8C7),
          ),
        ),
        SizedBox(width: phone ? 4 : 3),
        Text(
          label,
          style: TextStyle(
            color: Colors.white,
            fontSize: phone ? 7.6 : 5.7,
            height: 1.02,
            fontWeight: FontWeight.w500,
          ),
        ),
      ],
    );
  }
}

class _HeroDots extends StatelessWidget {
  const _HeroDots();

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          width: 10,
          height: 3.5,
          decoration: BoxDecoration(
            color: OvanieColors.orange,
            borderRadius: BorderRadius.circular(8),
          ),
        ),
        const SizedBox(width: 4),
        ...List.generate(
          3,
          (_) => Container(
            width: 3.5,
            height: 3.5,
            margin: const EdgeInsets.only(right: 4),
            decoration: const BoxDecoration(
              color: Color(0xFFD6D9DE),
              shape: BoxShape.circle,
            ),
          ),
        ),
      ],
    );
  }
}


class _PopularCategories extends StatelessWidget {
  final List<CategoryModel> items;
  final ValueChanged<CategoryModel> onTap;
  final VoidCallback onViewAll;

  const _PopularCategories({
    required this.items,
    required this.onTap,
    required this.onViewAll,
  });

  @override
  Widget build(BuildContext context) {
    return _HomeSectionFrame(
      title: 'Catégories populaires',
      onViewAll: onViewAll,
      content: LayoutBuilder(
        builder: (context, constraints) {
          const gap = 10.0;
          final phone = constraints.maxWidth < 520;
          final count = items.length.clamp(1, 6).toInt();
          final width = phone
              ? (constraints.maxWidth * .285).clamp(96.0, 116.0).toDouble()
              : (constraints.maxWidth - (gap * (count - 1))) / count;

          return SizedBox(
            height: phone ? 104 : 72,
            child: ListView.separated(
              physics: const BouncingScrollPhysics(),
              scrollDirection: Axis.horizontal,
              itemCount: items.length,
              separatorBuilder: (_, __) => const SizedBox(width: gap),
              itemBuilder: (context, index) {
                final item = items[index];
                return SizedBox(
                  width: width,
                  child: _CategoryCard(
                    category: item,
                    onTap: () => onTap(item),
                  ),
                );
              },
            ),
          );
        },
      ),
    );
  }
}


class _CategoryCard extends StatelessWidget {
  final CategoryModel category;
  final VoidCallback onTap;

  const _CategoryCard({required this.category, required this.onTap});

  @override
  Widget build(BuildContext context) {
    final imageUrl = category.imageUrl.isNotEmpty
        ? category.imageUrl
        : category.icon;

    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(11),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(11),
        child: Container(
          padding: const EdgeInsets.fromLTRB(7, 7, 7, 8),
          decoration: BoxDecoration(
            border: Border.all(color: const Color(0xFFE4E6EA), width: .9),
            borderRadius: BorderRadius.circular(11),
          ),
          child: Column(
            children: [
              Expanded(
                child: _NetworkImage(
                  url: imageUrl,
                  fit: BoxFit.contain,
                  fallbackIcon: Icons.category_outlined,
                ),
              ),
              const SizedBox(height: 5),
              Text(
                category.name,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                textAlign: TextAlign.center,
                style: const TextStyle(
                  color: Color(0xFF101A33),
                  fontSize: 10.2,
                  height: 1.08,
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


enum _HomeProductCardType { flash, bestSeller, newArrival }

class _ProductSection extends StatelessWidget {
  final String title;
  final HomeMarketplaceSection section;
  final _HomeProductCardType type;
  final VoidCallback onViewAll;
  final DateTime? flashEndsAtUtc;
  final Duration serverClockOffset;
  final VoidCallback? onFlashExpired;

  const _ProductSection({
    required this.title,
    required this.section,
    required this.type,
    required this.onViewAll,
    this.flashEndsAtUtc,
    this.serverClockOffset = Duration.zero,
    this.onFlashExpired,
  });

  @override
  Widget build(BuildContext context) {
    final isFlash = type == _HomeProductCardType.flash;
    return _HomeSectionFrame(
      title: title,
      titleTrailing: isFlash
          ? _FlashCountdownBadge(
              endsAtUtc: flashEndsAtUtc,
              serverClockOffset: serverClockOffset,
              onExpired: onFlashExpired,
            )
          : null,
      onViewAll: onViewAll,
      content: LayoutBuilder(
        builder: (context, constraints) {
          final phone = constraints.maxWidth < 520;
          final gap = phone ? 10.0 : (isFlash ? 7.0 : 6.0);

          final width = phone
              ? (isFlash
                  ? (constraints.maxWidth * .455).clamp(152.0, 178.0).toDouble()
                  : (constraints.maxWidth * .35).clamp(118.0, 142.0).toDouble())
              : (constraints.maxWidth -
                      gap * ((isFlash ? 4 : 6) - 1)) /
                  (isFlash ? 4 : 6);

          return SizedBox(
            height: phone ? (isFlash ? 218 : 178) : (isFlash ? 100 : 84),
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              physics: const BouncingScrollPhysics(),
              itemCount: section.products.length,
              separatorBuilder: (_, __) => SizedBox(width: gap),
              itemBuilder: (context, index) => SizedBox(
                width: width,
                child: _HomeProductCard(
                  product: section.products[index],
                  type: type,
                ),
              ),
            ),
          );
        },
      ),
    );
  }
}


class _HomeSectionFrame extends StatelessWidget {
  final String title;
  final Widget? titleTrailing;
  final VoidCallback onViewAll;
  final Widget content;

  const _HomeSectionFrame({
    required this.title,
    this.titleTrailing,
    required this.onViewAll,
    required this.content,
  });

  @override
  Widget build(BuildContext context) {
    final phone = MediaQuery.sizeOf(context).width < 520;

    return SizedBox(
      width: double.infinity,
      child: Padding(
        padding: EdgeInsets.fromLTRB(
          phone ? 16 : 13,
          phone ? 14 : 9,
          phone ? 16 : 13,
          0,
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
          SizedBox(
            width: double.infinity,
            height: phone ? 30 : 20,
            child: Stack(
              clipBehavior: Clip.none,
              children: [
                Positioned(
                  left: 0,
                  top: 0,
                  bottom: 0,
                  right: phone ? 104 : 70,
                  child: Row(
                    children: [
                      Flexible(
                        child: Text(
                          title,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(
                            color: const Color(0xFF101A33),
                            fontSize: phone ? 17 : 11,
                            height: 1,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                      ),
                      if (titleTrailing != null) ...[
                        SizedBox(width: phone ? 9 : 7),
                        titleTrailing!,
                      ],
                    ],
                  ),
                ),
                Positioned(
                  right: 0,
                  top: 0,
                  bottom: 0,
                  child: InkWell(
                    onTap: onViewAll,
                    borderRadius: BorderRadius.circular(8),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(
                          'Voir toutes',
                          style: TextStyle(
                            color: OvanieColors.orange,
                            fontSize: phone ? 11.5 : 7,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                        const SizedBox(width: 2),
                        Icon(
                          Icons.chevron_right_rounded,
                          color: OvanieColors.orange,
                          size: phone ? 17 : 10,
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            ),
          ),
          SizedBox(height: phone ? 9 : 5),
            content,
          ],
        ),
      ),
    );
  }
}

class _FlashCountdownBadge extends StatefulWidget {
  final DateTime? endsAtUtc;
  final Duration serverClockOffset;
  final VoidCallback? onExpired;

  const _FlashCountdownBadge({
    required this.endsAtUtc,
    required this.serverClockOffset,
    this.onExpired,
  });

  @override
  State<_FlashCountdownBadge> createState() => _FlashCountdownBadgeState();
}


class _FlashCountdownBadgeState extends State<_FlashCountdownBadge> {
  Timer? _timer;
  Duration _remaining = Duration.zero;
  bool _expirationSent = false;

  @override
  void initState() {
    super.initState();
    _synchronize();
  }

  @override
  void didUpdateWidget(covariant _FlashCountdownBadge oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.endsAtUtc != widget.endsAtUtc ||
        oldWidget.serverClockOffset != widget.serverClockOffset) {
      _expirationSent = false;
      _synchronize();
    }
  }

  void _synchronize() {
    _timer?.cancel();
    _tick();
    if (widget.endsAtUtc != null && _remaining > Duration.zero) {
      _timer = Timer.periodic(const Duration(seconds: 1), (_) => _tick());
    }
  }

  void _tick() {
    final end = widget.endsAtUtc;
    if (end == null) {
      if (mounted) setState(() => _remaining = Duration.zero);
      return;
    }

    final backendNow =
        DateTime.now().toUtc().add(widget.serverClockOffset);
    final next = end.difference(backendNow);
    final clamped = next.isNegative ? Duration.zero : next;

    if (mounted) {
      setState(() => _remaining = clamped);
    } else {
      _remaining = clamped;
    }

    if (clamped == Duration.zero && !_expirationSent) {
      _expirationSent = true;
      _timer?.cancel();
      widget.onExpired?.call();
    }
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  String _format(Duration duration) {
    final totalHours = duration.inHours;
    final minutes = duration.inMinutes.remainder(60);
    final seconds = duration.inSeconds.remainder(60);
    return [totalHours, minutes, seconds]
        .map((value) => value.toString().padLeft(2, '0'))
        .join(' : ');
  }

  @override
  Widget build(BuildContext context) {
    final hasDeadline = widget.endsAtUtc != null;
    final label = hasDeadline ? _format(_remaining) : 'À VENIR';
    final phone = MediaQuery.sizeOf(context).width < 520;

    return Container(
      padding: EdgeInsets.symmetric(
        horizontal: phone ? 8 : 5,
        vertical: phone ? 4 : 2.4,
      ),
      decoration: BoxDecoration(
        color: const Color(0xFFE31822),
        borderRadius: BorderRadius.circular(phone ? 7 : 4.5),
        boxShadow: const [
          BoxShadow(
            color: Color(0x1A000000),
            blurRadius: 3,
            offset: Offset(0, 1),
          ),
        ],
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            Icons.bolt_rounded,
            color: Colors.white,
            size: phone ? 13 : 7.5,
          ),
          SizedBox(width: phone ? 3 : 2),
          Text(
            label,
            style: TextStyle(
              color: Colors.white,
              fontSize: phone ? 10.5 : 6,
              height: 1,
              fontWeight: FontWeight.w900,
              letterSpacing: .1,
            ),
          ),
        ],
      ),
    );
  }
}

class _HomeProductCard extends StatelessWidget {
  final ProductModel product;
  final _HomeProductCardType type;

  const _HomeProductCard({required this.product, required this.type});

  bool get _isFlash => type == _HomeProductCardType.flash;

  void _toggleFavorite(BuildContext context) {
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
  }

  void _addToCart(BuildContext context) {
    if (!product.canAddToCart) {
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(
          SnackBar(content: Text(product.availabilityLabel)),
        );
      return;
    }

    CartStore.instance.add(product);
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        const SnackBar(
          duration: Duration(milliseconds: 900),
          content: Text('Produit ajouté au panier.'),
        ),
      );
  }

  @override
  Widget build(BuildContext context) {
    final displayedPrice = product.homeDisplayPrice;
    final originalPrice = product.homeOriginalPrice;
    final phone = MediaQuery.sizeOf(context).width < 520;
    final radius = phone ? 12.0 : 7.0;

    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(radius),
      child: InkWell(
        onTap: () => Navigator.of(context).push(
          MaterialPageRoute<void>(
            builder: (_) => ProductDetailScreen(product: product),
          ),
        ),
        borderRadius: BorderRadius.circular(radius),
        child: Container(
          decoration: BoxDecoration(
            border: Border.all(color: const Color(0xFFE5E7EB), width: .7),
            borderRadius: BorderRadius.circular(radius),
          ),
          padding: EdgeInsets.all(phone ? 8 : (_isFlash ? 5 : 4)),
          child: _isFlash
              ? _FlashProductCardBody(
                  product: product,
                  displayedPrice: displayedPrice,
                  originalPrice: originalPrice,
                  onFavorite: () => _toggleFavorite(context),
                  onAddToCart: () => _addToCart(context),
                )
              : _CompactProductCardBody(
                  product: product,
                  type: type,
                  displayedPrice: displayedPrice,
                  onFavorite: () => _toggleFavorite(context),
                ),
        ),
      ),
    );
  }
}


class _FlashProductCardBody extends StatelessWidget {
  final ProductModel product;
  final double displayedPrice;
  final double originalPrice;
  final VoidCallback onFavorite;
  final VoidCallback onAddToCart;

  const _FlashProductCardBody({
    required this.product,
    required this.displayedPrice,
    required this.originalPrice,
    required this.onFavorite,
    required this.onAddToCart,
  });

  @override
  Widget build(BuildContext context) {
    final phone = MediaQuery.sizeOf(context).width < 520;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SizedBox(
          height: phone ? 104 : 46,
          child: Stack(
            children: [
              Positioned.fill(
                child: Padding(
                  padding: EdgeInsets.fromLTRB(
                    phone ? 12 : 7,
                    phone ? 8 : 4,
                    phone ? 12 : 7,
                    0,
                  ),
                  child: _NetworkImage(url: product.imageUrl),
                ),
              ),
              if (product.discountPercent > 0)
                Positioned(
                  left: 0,
                  top: 0,
                  child: _ProductBadge.discount(product.discountPercent),
                ),
              Positioned(
                right: 0,
                top: 0,
                child: _FavoriteIconButton(
                  product: product,
                  onTap: onFavorite,
                ),
              ),
            ],
          ),
        ),
        SizedBox(height: phone ? 7 : 2),
        Text(
          product.name,
          maxLines: phone ? 2 : 1,
          overflow: TextOverflow.ellipsis,
          style: TextStyle(
            color: const Color(0xFF111A2F),
            fontSize: phone ? 12.2 : 6.6,
            height: 1.08,
            fontWeight: FontWeight.w800,
          ),
        ),
        SizedBox(height: phone ? 4 : 1),
        Text(
          product.homeSubtitle,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: TextStyle(
            color: const Color(0xFF6D7788),
            fontSize: phone ? 10.2 : 5.8,
            height: 1,
          ),
        ),
        const Spacer(),
        Row(
          crossAxisAlignment: CrossAxisAlignment.end,
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    formatFcfa(displayedPrice),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: OvanieColors.orange,
                      fontSize: phone ? 12.2 : 6.5,
                      height: 1,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  if (product.discountPercent > 0 && originalPrice > 0) ...[
                    SizedBox(height: phone ? 3 : 1),
                    Text(
                      formatFcfa(originalPrice),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        color: const Color(0xFF8A919E),
                        fontSize: phone ? 9.3 : 4.9,
                        height: 1,
                        decoration: TextDecoration.lineThrough,
                      ),
                    ),
                  ],
                ],
              ),
            ),
            SizedBox(width: phone ? 6 : 2),
            Material(
              color: product.canAddToCart
                  ? OvanieColors.orange
                  : const Color(0xFFBCC2CC),
              borderRadius: BorderRadius.circular(phone ? 8 : 4),
              child: InkWell(
                onTap: onAddToCart,
                borderRadius: BorderRadius.circular(phone ? 8 : 4),
                child: SizedBox(
                  width: phone ? 36 : 18,
                  height: phone ? 36 : 18,
                  child: Icon(
                    Icons.shopping_cart_outlined,
                    color: Colors.white,
                    size: phone ? 20 : 10,
                  ),
                ),
              ),
            ),
          ],
        ),
      ],
    );
  }
}


class _CompactProductCardBody extends StatelessWidget {
  final ProductModel product;
  final _HomeProductCardType type;
  final double displayedPrice;
  final VoidCallback onFavorite;

  const _CompactProductCardBody({
    required this.product,
    required this.type,
    required this.displayedPrice,
    required this.onFavorite,
  });

  @override
  Widget build(BuildContext context) {
    final phone = MediaQuery.sizeOf(context).width < 520;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SizedBox(
          height: phone ? 88 : 37,
          child: Stack(
            children: [
              Positioned.fill(
                child: Padding(
                  padding: EdgeInsets.fromLTRB(
                    phone ? 6 : 2,
                    phone ? 5 : 2,
                    phone ? 6 : 2,
                    0,
                  ),
                  child: _NetworkImage(url: product.imageUrl),
                ),
              ),
              if (type == _HomeProductCardType.newArrival)
                const Positioned(
                  left: 0,
                  top: 0,
                  child: _ProductBadge.newArrival(),
                ),
              Positioned(
                right: 0,
                top: 0,
                child: _FavoriteIconButton(
                  product: product,
                  onTap: onFavorite,
                  compact: true,
                ),
              ),
            ],
          ),
        ),
        SizedBox(height: phone ? 7 : 2),
        Text(
          product.name,
          maxLines: 2,
          overflow: TextOverflow.ellipsis,
          style: TextStyle(
            color: const Color(0xFF111A2F),
            fontSize: phone ? 10.8 : 5.4,
            height: 1.08,
            fontWeight: FontWeight.w700,
          ),
        ),
        const Spacer(),
        Text(
          formatFcfa(displayedPrice),
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: TextStyle(
            color: OvanieColors.orange,
            fontSize: phone ? 11.4 : 6,
            height: 1,
            fontWeight: FontWeight.w900,
          ),
        ),
        if (type == _HomeProductCardType.bestSeller) ...[
          SizedBox(height: phone ? 5 : 2),
          Row(
            children: [
              Icon(
                Icons.star_rounded,
                size: phone ? 12 : 6,
                color: const Color(0xFFF5A500),
              ),
              SizedBox(width: phone ? 2 : 1),
              Expanded(
                child: Text(
                  '${product.rating?.toStringAsFixed(1) ?? '—'} (${product.reviewsCount})',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: const Color(0xFF717A89),
                    fontSize: phone ? 8.8 : 4.5,
                    height: 1,
                  ),
                ),
              ),
            ],
          ),
        ],
      ],
    );
  }
}


class _ProductBadge extends StatelessWidget {
  final String label;
  final Color background;

  const _ProductBadge._({required this.label, required this.background});

  factory _ProductBadge.discount(int percent) => _ProductBadge._(
        label: '-$percent%',
        background: const Color(0xFFE31822),
      );

  const _ProductBadge.newArrival()
      : label = 'NOUVEAU',
        background = const Color(0xFF17A34A);

  @override
  Widget build(BuildContext context) {
    final phone = MediaQuery.sizeOf(context).width < 520;

    return Container(
      padding: EdgeInsets.symmetric(
        horizontal: phone ? 7 : 3.2,
        vertical: phone ? 4 : 1.6,
      ),
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(phone ? 6 : 3),
        border: Border.all(color: Colors.white, width: phone ? .8 : .45),
        boxShadow: const [
          BoxShadow(
            color: Color(0x18000000),
            blurRadius: 3,
            offset: Offset(0, 1),
          ),
        ],
      ),
      child: Text(
        label,
        maxLines: 1,
        style: TextStyle(
          color: Colors.white,
          fontSize: phone ? 9.2 : 4.7,
          height: 1,
          fontWeight: FontWeight.w900,
          letterSpacing: .05,
        ),
      ),
    );
  }
}


class _FavoriteIconButton extends StatelessWidget {
  final ProductModel product;
  final VoidCallback onTap;
  final bool compact;

  const _FavoriteIconButton({
    required this.product,
    required this.onTap,
    this.compact = false,
  });

  @override
  Widget build(BuildContext context) {
    final phone = MediaQuery.sizeOf(context).width < 520;

    return AnimatedBuilder(
      animation: Listenable.merge([
        FavoritesStore.instance,
        SessionStore.instance,
      ]),
      builder: (context, _) {
        final favorite = SessionStore.instance.isAuthenticated &&
            FavoritesStore.instance.contains(product);

        return InkWell(
          onTap: onTap,
          customBorder: const CircleBorder(),
          child: Padding(
            padding: EdgeInsets.all(phone ? 4 : (compact ? 1 : 1.5)),
            child: Icon(
              favorite
                  ? Icons.favorite_rounded
                  : Icons.favorite_border_rounded,
              size: phone ? (compact ? 18 : 20) : (compact ? 9 : 10),
              color: favorite
                  ? OvanieColors.orange
                  : const Color(0xFF13213A),
            ),
          ),
        );
      },
    );
  }
}

class _NetworkImage extends StatelessWidget {
  final String url;
  final BoxFit fit;
  final IconData fallbackIcon;

  const _NetworkImage({
    required this.url,
    this.fit = BoxFit.contain,
    this.fallbackIcon = Icons.inventory_2_outlined,
  });

  @override
  Widget build(BuildContext context) {
    if (url.trim().isEmpty) {
      return Center(
        child: Icon(fallbackIcon, size: 18, color: OvanieColors.muted),
      );
    }

    return Image.network(
      url,
      fit: fit,
      errorBuilder: (_, __, ___) => Center(
        child: Icon(fallbackIcon, size: 18, color: OvanieColors.muted),
      ),
      loadingBuilder: (context, child, event) {
        if (event == null) return child;
        return const Center(
          child: SizedBox(
            width: 11,
            height: 11,
            child: CircularProgressIndicator(
              strokeWidth: 1.1,
              color: Color(0xFFCBD1DA),
            ),
          ),
        );
      },
    );
  }
}

class _BlackFridayBanner extends StatelessWidget {
  final int maxDiscountPercent;
  final int productCount;
  final VoidCallback onTap;

  const _BlackFridayBanner({
    required this.maxDiscountPercent,
    required this.productCount,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 0),
      child: AspectRatio(
        aspectRatio: 2048 / 683,
        child: LayoutBuilder(
          builder: (context, constraints) {
            final width = constraints.maxWidth;
            final height = constraints.maxHeight;
            final phone = width < 520;

            return ClipRRect(
              borderRadius: BorderRadius.circular(phone ? 14 : 8),
              child: Stack(
                fit: StackFit.expand,
                children: [
                  Image.asset(
                    'assets/images/black_friday.png',
                    fit: BoxFit.fill,
                    alignment: Alignment.center,
                  ),
                  Positioned(
                    left: phone ? 14 : 13,
                    top: height * .24,
                    width: width * (phone ? .31 : .30),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        RichText(
                          text: TextSpan(
                            style: TextStyle(
                              fontSize: phone ? 20 : 14.8,
                              height: .98,
                              fontWeight: FontWeight.w900,
                            ),
                            children: const [
                              TextSpan(
                                text: 'Black ',
                                style: TextStyle(color: Colors.white),
                              ),
                              TextSpan(
                                text: 'Friday',
                                style: TextStyle(color: OvanieColors.orange),
                              ),
                            ],
                          ),
                        ),
                        SizedBox(height: phone ? 6 : 2),
                        Text(
                          "Les meilleures offres de l'année !",
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(
                            color: Colors.white,
                            fontSize: phone ? 10.5 : 6.4,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                        SizedBox(height: phone ? 5 : 2),
                        Text(
                          productCount > 0
                              ? '$productCount offres Black Friday'
                              : 'Sélection de produits BTP',
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(
                            color: const Color(0xFFD5D9E0),
                            fontSize: phone ? 8.8 : 4.7,
                            fontWeight: FontWeight.w500,
                          ),
                        ),
                      ],
                    ),
                  ),
                  // Coordonnées alignées sur l'étiquette réellement dessinée
                  // dans black_friday.png (image 2048 x 683).
                  Positioned(
                    left: width * .345,
                    top: height * .255,
                    width: width * .205,
                    height: height * .37,
                    child: _BlackFridayDiscountTag(
                      percent: maxDiscountPercent,
                      phone: phone,
                    ),
                  ),
                  Positioned(
                    right: phone ? 14 : 13,
                    top: height * .38,
                    child: SizedBox(
                      height: phone ? 38 : 22,
                      child: FilledButton(
                        onPressed: onTap,
                        style: FilledButton.styleFrom(
                          backgroundColor: OvanieColors.orange,
                          foregroundColor: Colors.white,
                          minimumSize: Size.zero,
                          padding: EdgeInsets.symmetric(
                            horizontal: phone ? 10 : 9,
                          ),
                          tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(phone ? 9 : 5),
                          ),
                        ),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Text(
                              "J'en profite",
                              style: TextStyle(
                                fontSize: phone ? 9.5 : 6.4,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                            SizedBox(width: phone ? 5 : 4),
                            Icon(
                              Icons.arrow_forward_rounded,
                              size: phone ? 14 : 9,
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            );
          },
        ),
      ),
    );
  }
}

class _BlackFridayDiscountTag extends StatelessWidget {
  final int percent;
  final bool phone;

  const _BlackFridayDiscountTag({
    required this.percent,
    required this.phone,
  });

  @override
  Widget build(BuildContext context) {
    final hasRealDiscount = percent > 0;

    return Transform.rotate(
      angle: .09,
      child: FittedBox(
        fit: BoxFit.scaleDown,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              hasRealDiscount ? "JUSQU'À" : 'OFFRES',
              textAlign: TextAlign.center,
              style: TextStyle(
                color: Colors.white,
                fontSize: phone ? 9.2 : 5.4,
                height: 1,
                fontWeight: FontWeight.w900,
                letterSpacing: .25,
              ),
            ),
            SizedBox(height: phone ? 2 : 1),
            Text(
              hasRealDiscount ? '-$percent%' : 'BTP',
              textAlign: TextAlign.center,
              style: TextStyle(
                color: OvanieColors.orange,
                fontSize: phone ? 23 : 13,
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

class _BlackFridayFeatures extends StatelessWidget {
  const _BlackFridayFeatures();

  @override
  Widget build(BuildContext context) {
    return const Column(
      mainAxisAlignment: MainAxisAlignment.center,
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _BannerPoint(icon: Icons.inventory_2_outlined, label: 'Stock limité'),
        SizedBox(height: 2),
        _BannerPoint(
          icon: Icons.local_offer_outlined,
          label: 'Offres exclusives',
        ),
        SizedBox(height: 2),
        _BannerPoint(
          icon: Icons.local_shipping_outlined,
          label: 'Livraison rapide',
        ),
      ],
    );
  }
}

class _BannerPoint extends StatelessWidget {
  final IconData icon;
  final String label;

  const _BannerPoint({required this.icon, required this.label});

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, color: Colors.white, size: 6.5),
        const SizedBox(width: 3),
        Flexible(
          child: Text(
            label,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(color: Colors.white, fontSize: 4.8),
          ),
        ),
      ],
    );
  }
}
