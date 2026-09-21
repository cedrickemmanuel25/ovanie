import 'package:flutter/material.dart';

import '../../app/theme.dart';
import '../../core/utils/formatters.dart';
import '../../features/auth/domain/session_store.dart';
import '../../features/cart/domain/cart_store.dart';
import '../../features/favorites/domain/favorites_store.dart';
import '../../features/products/domain/product_model.dart';
import '../../features/products/presentation/product_detail_screen.dart';

class ProductCard extends StatelessWidget {
  /// Hauteur de référence unique pour les cartes produits affichées en grille.
  ///
  /// Toutes les grilles OVANIE utilisent cette valeur afin d'éviter qu'un
  /// écran (Favoris, Catalogue, Vu récemment...) impose une hauteur plus
  /// petite que le contenu réel de la carte et déclenche un RenderFlex
  /// overflow (bandes jaunes/noires en mode debug).
  static const double gridExtent = 360.0;

  /// Hauteur de secours pour les listes horizontales. Les écrans adaptatifs
  /// utilisent [extentForWidth] afin de tenir compte de la largeur réelle et
  /// du facteur de texte Android.
  static const double horizontalExtent = 360.0;

  static double extentForWidth(
    BuildContext context, {
    required double width,
  }) {
    final textScale = MediaQuery.textScalerOf(context)
        .scale(1.0)
        .clamp(1.0, 2.5)
        .toDouble();
    final imageHeight = (width * 0.77).clamp(116.0, 148.0).toDouble();
    // Réserve volontairement une marge de sécurité pour les polices Android
    // agrandies. Le contenu réel peut varier selon le moteur de texte et la
    // langue ; une petite marge vaut mieux qu'un RenderFlex overflow.
    final contentHeight = 222.0 + ((textScale - 1.0) * 90.0);
    return imageHeight + contentHeight;
  }

  final ProductModel product;
  final VoidCallback? onTap;

  const ProductCard({
    super.key,
    required this.product,
    this.onTap,
  });

  void _openDetails(BuildContext context) {
    if (onTap != null) {
      onTap!();
      return;
    }

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
          backgroundColor: result.success ? OvanieColors.navy : OvanieColors.danger,
        ),
      );
  }

  @override
  Widget build(BuildContext context) {
    final canBuy = product.canAddToCart && product.stock > 0;

    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(17),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: () => _openDetails(context),
        child: Container(
          decoration: BoxDecoration(
            color: Colors.white,
            border: Border.all(color: OvanieColors.border),
            borderRadius: BorderRadius.circular(17),
            boxShadow: const [
              BoxShadow(
                color: Color(0x0A001B44),
                blurRadius: 14,
                offset: Offset(0, 6),
              ),
            ],
          ),
          clipBehavior: Clip.antiAlias,
          child: LayoutBuilder(
            builder: (context, constraints) {
              final imageHeight = (constraints.maxWidth * 0.77)
                  .clamp(116.0, 148.0)
                  .toDouble();
              final textScale = MediaQuery.textScalerOf(context)
                  .scale(1.0)
                  .clamp(1.0, 2.5)
                  .toDouble();
              final nameHeight = 34.0 + ((textScale - 1.0) * 28.0);

              return Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  SizedBox(
                    height: imageHeight,
                    width: double.infinity,
                    child: Stack(
                      children: [
                        Positioned.fill(
                          child: ColoredBox(
                            color: const Color(0xFFF8FAFC),
                            child: Padding(
                              padding: const EdgeInsets.all(10),
                              child: _ProductImage(url: product.imageUrl),
                            ),
                          ),
                        ),
                        Positioned(
                          top: 8,
                          right: 8,
                          child: AnimatedBuilder(
                            animation: Listenable.merge([
                              FavoritesStore.instance,
                              SessionStore.instance,
                            ]),
                            builder: (context, _) {
                              final favorite = SessionStore.instance.isAuthenticated &&
                                  FavoritesStore.instance.contains(product);

                              return Material(
                                color: Colors.white,
                                shape: const CircleBorder(),
                                elevation: 1,
                                child: InkWell(
                                  customBorder: const CircleBorder(),
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
                                  child: SizedBox(
                                    width: 34,
                                    height: 34,
                                    child: Icon(
                                      favorite
                                          ? Icons.favorite_rounded
                                          : Icons.favorite_border_rounded,
                                      size: 19,
                                      color: favorite
                                          ? OvanieColors.orange
                                          : OvanieColors.muted,
                                    ),
                                  ),
                                ),
                              );
                            },
                          ),
                        ),
                        if (product.hasDiscount)
                          Positioned(
                            left: 8,
                            top: 8,
                            child: Container(
                              padding: const EdgeInsets.symmetric(
                                horizontal: 8,
                                vertical: 5,
                              ),
                              decoration: BoxDecoration(
                                color: OvanieColors.orange,
                                borderRadius: BorderRadius.circular(9),
                              ),
                              child: const Text(
                                'PROMO',
                                style: TextStyle(
                                  color: Colors.white,
                                  fontSize: 8.2,
                                  fontWeight: FontWeight.w900,
                                  letterSpacing: 0.2,
                                ),
                              ),
                            ),
                          ),
                      ],
                    ),
                  ),
                  Expanded(
                    child: Padding(
                      padding: const EdgeInsets.fromLTRB(10, 9, 10, 10),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          if (product.categoryName.isNotEmpty) ...[
                            Text(
                              product.categoryName.toUpperCase(),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                color: OvanieColors.blue,
                                fontSize: 8.5,
                                fontWeight: FontWeight.w900,
                                letterSpacing: 0.2,
                              ),
                            ),
                            const SizedBox(height: 4),
                          ],
                          SizedBox(
                            height: nameHeight,
                            child: Text(
                              product.name,
                              maxLines: 2,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                color: OvanieColors.text,
                                fontSize: 11.7,
                                height: 1.22,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                          ),
                          const SizedBox(height: 6),
                          Row(
                            children: [
                              const Icon(
                                Icons.star_rounded,
                                size: 14,
                                color: OvanieColors.warning,
                              ),
                              const SizedBox(width: 3),
                              Text(
                                product.rating?.toStringAsFixed(1) ?? '—',
                                style: const TextStyle(
                                  fontSize: 9.5,
                                  color: OvanieColors.text,
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                              const SizedBox(width: 4),
                              Flexible(
                                child: Text(
                                  '(${product.reviewsCount})',
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: const TextStyle(
                                    fontSize: 9.1,
                                    color: OvanieColors.muted,
                                  ),
                                ),
                              ),
                            ],
                          ),
                          const Spacer(),
                          Text(
                            formatFcfa(product.finalPrice),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              color: OvanieColors.orange,
                              fontSize: 14.5,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                          const SizedBox(height: 3),
                          Row(
                            children: [
                              Container(
                                width: 6,
                                height: 6,
                                decoration: BoxDecoration(
                                  shape: BoxShape.circle,
                                  color: product.stock > 0 || product.isOrderable
                                      ? OvanieColors.success
                                      : OvanieColors.muted,
                                ),
                              ),
                              const SizedBox(width: 5),
                              Expanded(
                                child: Text(
                                  product.availabilityLabel,
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: TextStyle(
                                    color: product.stock > 0 || product.isOrderable
                                        ? OvanieColors.success
                                        : OvanieColors.muted,
                                    fontSize: 9.3,
                                    fontWeight: FontWeight.w700,
                                  ),
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 8),
                          _AddButton(
                            enabled: canBuy,
                            label: canBuy ? 'Ajouter' : product.availabilityLabel,
                            onPressed: canBuy ? () => _addToCart(context) : null,
                          ),
                        ],
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

class _AddButton extends StatelessWidget {
  final bool enabled;
  final String label;
  final VoidCallback? onPressed;

  const _AddButton({
    required this.enabled,
    required this.label,
    required this.onPressed,
  });

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: double.infinity,
      height: 36,
      child: Material(
        color: enabled ? const Color(0xFFFFF7F1) : const Color(0xFFF0F2F5),
        borderRadius: BorderRadius.circular(10),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: onPressed,
          child: Container(
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(10),
              border: Border.all(
                color: enabled ? OvanieColors.orange : const Color(0xFFDDE2E8),
              ),
            ),
            padding: const EdgeInsets.symmetric(horizontal: 8),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Icon(
                  enabled ? Icons.add_shopping_cart_rounded : Icons.block_rounded,
                  size: 15,
                  color: enabled ? OvanieColors.orange : OvanieColors.muted,
                ),
                const SizedBox(width: 5),
                Flexible(
                  child: Text(
                    label,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: enabled ? OvanieColors.orange : OvanieColors.muted,
                      fontSize: 9.8,
                      fontWeight: FontWeight.w900,
                    ),
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

class _ProductImage extends StatelessWidget {
  final String url;

  const _ProductImage({required this.url});

  @override
  Widget build(BuildContext context) {
    if (url.isEmpty) {
      return const Center(
        child: Icon(
          Icons.inventory_2_outlined,
          size: 44,
          color: Color(0xFFBAC4D3),
        ),
      );
    }

    return Image.network(
      url,
      fit: BoxFit.contain,
      cacheWidth: 320,
      cacheHeight: 320,
      filterQuality: FilterQuality.low,
      gaplessPlayback: true,
      loadingBuilder: (context, child, progress) {
        if (progress == null) return child;
        return const Center(
          child: SizedBox(
            width: 20,
            height: 20,
            child: CircularProgressIndicator(strokeWidth: 1.6),
          ),
        );
      },
      errorBuilder: (_, __, ___) {
        return const Center(
          child: Icon(
            Icons.broken_image_outlined,
            size: 40,
            color: Color(0xFFBAC4D3),
          ),
        );
      },
    );
  }
}
