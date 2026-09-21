import 'package:flutter/material.dart';

import '../../../app/theme.dart';
import '../../auth/domain/session_store.dart';
import '../../products/domain/product_model.dart';
import '../../products/presentation/product_detail_screen.dart';
import '../domain/wishlist_model.dart';
import '../domain/wishlists_store.dart';

class WishlistsScreen extends StatefulWidget {
  final int favoritesCount;
  final VoidCallback onBackToFavorites;
  final VoidCallback onOpenSearch;

  const WishlistsScreen({
    super.key,
    required this.favoritesCount,
    required this.onBackToFavorites,
    required this.onOpenSearch,
  });

  @override
  State<WishlistsScreen> createState() => _WishlistsScreenState();
}

class _WishlistsScreenState extends State<WishlistsScreen> {
  bool _bulkAlertBusy = false;

  Future<void> _createWishlist() async {
    if (!SessionStore.instance.isAuthenticated) {
      _message('Connectez-vous pour créer une liste d’envies.');
      return;
    }

    final controller = TextEditingController();
    final name = await showDialog<String>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Créer une liste'),
        content: TextField(
          controller: controller,
          autofocus: true,
          textCapitalization: TextCapitalization.sentences,
          decoration: const InputDecoration(
            labelText: 'Nom de la liste',
            hintText: 'Ex. Maison & Rénovation',
          ),
          onSubmitted: (value) => Navigator.of(dialogContext).pop(value.trim()),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(),
            child: const Text('Annuler'),
          ),
          FilledButton(
            onPressed: () => Navigator.of(dialogContext).pop(controller.text.trim()),
            style: FilledButton.styleFrom(backgroundColor: OvanieColors.orange),
            child: const Text('Créer'),
          ),
        ],
      ),
    );
    controller.dispose();
    if (name == null || name.trim().isEmpty) return;

    try {
      await WishlistsStore.instance.create(name.trim());
      _message('Liste créée.', success: true);
    } catch (_) {
      _message('Impossible de créer cette liste pour le moment.');
    }
  }

  Future<void> _deleteWishlist(WishlistModel wishlist) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Supprimer cette liste ?'),
        content: Text('La liste « ${wishlist.name} » sera supprimée.'),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(false),
            child: const Text('Annuler'),
          ),
          FilledButton(
            onPressed: () => Navigator.of(dialogContext).pop(true),
            style: FilledButton.styleFrom(backgroundColor: OvanieColors.danger),
            child: const Text('Supprimer'),
          ),
        ],
      ),
    );
    if (confirmed != true) return;
    try {
      await WishlistsStore.instance.delete(wishlist);
      _message('Liste supprimée.', success: true);
    } catch (_) {
      _message('Impossible de supprimer cette liste.');
    }
  }

  Future<void> _activateAllAlerts() async {
    if (_bulkAlertBusy) return;
    final lists = WishlistsStore.instance.items;
    if (lists.isEmpty) return;
    setState(() => _bulkAlertBusy = true);
    try {
      for (final wishlist in lists) {
        if (!wishlist.alertsEnabled) {
          await WishlistsStore.instance.setAlerts(wishlist, true);
        }
      }
      _message('Alertes activées pour vos listes d’envies.', success: true);
    } catch (_) {
      _message('Impossible d’activer toutes les alertes.');
    } finally {
      if (mounted) setState(() => _bulkAlertBusy = false);
    }
  }

  void _openWishlist(WishlistModel wishlist) {
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      backgroundColor: Colors.white,
      builder: (sheetContext) {
        return DraggableScrollableSheet(
          expand: false,
          initialChildSize: .72,
          minChildSize: .45,
          maxChildSize: .92,
          builder: (context, scrollController) {
            return Column(
              children: [
                Padding(
                  padding: const EdgeInsets.fromLTRB(20, 0, 20, 12),
                  child: Row(
                    children: [
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              wishlist.name,
                              style: const TextStyle(
                                color: OvanieColors.navy,
                                fontSize: 21,
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                            Text(
                              '${wishlist.productsCount} produit${wishlist.productsCount > 1 ? 's' : ''}',
                              style: const TextStyle(color: OvanieColors.muted),
                            ),
                          ],
                        ),
                      ),
                      Switch(
                        value: wishlist.alertsEnabled,
                        activeColor: OvanieColors.orange,
                        onChanged: (value) async {
                          try {
                            await WishlistsStore.instance.setAlerts(wishlist, value);
                          } catch (_) {
                            if (mounted) _message('Impossible de modifier les alertes.');
                          }
                        },
                      ),
                    ],
                  ),
                ),
                const Divider(height: 1),
                Expanded(
                  child: wishlist.products.isEmpty
                      ? const Center(
                          child: Padding(
                            padding: EdgeInsets.all(28),
                            child: Text(
                              'Cette liste ne contient encore aucun produit.',
                              textAlign: TextAlign.center,
                              style: TextStyle(
                                color: OvanieColors.muted,
                                fontSize: 15,
                              ),
                            ),
                          ),
                        )
                      : ListView.separated(
                          controller: scrollController,
                          padding: const EdgeInsets.all(18),
                          itemCount: wishlist.products.length,
                          separatorBuilder: (_, __) => const Divider(height: 18),
                          itemBuilder: (context, index) {
                            final product = wishlist.products[index];
                            return _WishlistProductTile(
                              product: product,
                              onTap: () {
                                Navigator.of(sheetContext).pop();
                                _openProduct(product);
                              },
                            );
                          },
                        ),
                ),
              ],
            );
          },
        );
      },
    );
  }

  void _openProduct(ProductModel product) {
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => ProductDetailScreen(product: product),
      ),
    );
  }

  void _message(String text, {bool success = false}) {
    if (!mounted) return;
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          content: Text(text),
          backgroundColor: success ? OvanieColors.success : OvanieColors.navy,
          behavior: SnackBarBehavior.floating,
        ),
      );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      body: SafeArea(
        bottom: false,
        child: AnimatedBuilder(
          animation: WishlistsStore.instance,
          builder: (context, _) {
            final lists = WishlistsStore.instance.items;
            return RefreshIndicator(
              color: OvanieColors.orange,
              onRefresh: () async {
                if (SessionStore.instance.isAuthenticated) {
                  await WishlistsStore.instance.refresh();
                }
              },
              child: CustomScrollView(
                physics: const AlwaysScrollableScrollPhysics(
                  parent: BouncingScrollPhysics(),
                ),
                slivers: [
                  SliverToBoxAdapter(
                    child: _WishlistHeader(
                      onBack: widget.onBackToFavorites,
                      onSearch: widget.onOpenSearch,
                      onCreate: _createWishlist,
                    ),
                  ),
                  SliverToBoxAdapter(
                    child: _WishlistTabs(
                      favoritesCount: widget.favoritesCount,
                      onFavorites: widget.onBackToFavorites,
                    ),
                  ),
                  SliverToBoxAdapter(
                    child: _IntroBanner(onCreate: _createWishlist),
                  ),
                  if (WishlistsStore.instance.loading && lists.isEmpty)
                    const SliverFillRemaining(
                      hasScrollBody: false,
                      child: Center(
                        child: CircularProgressIndicator(
                          color: OvanieColors.orange,
                        ),
                      ),
                    )
                  else if (lists.isEmpty)
                    SliverFillRemaining(
                      hasScrollBody: false,
                      child: _EmptyWishlists(onCreate: _createWishlist),
                    )
                  else ...[
                    SliverPadding(
                      padding: const EdgeInsets.fromLTRB(18, 2, 18, 10),
                      sliver: SliverList(
                        delegate: SliverChildBuilderDelegate(
                          (context, rawIndex) {
                            if (rawIndex.isOdd) {
                              return const SizedBox(height: 12);
                            }
                            final index = rawIndex ~/ 2;
                            final wishlist = lists[index];
                            return _WishlistCard(
                              wishlist: wishlist,
                              index: index,
                              onOpen: () => _openWishlist(wishlist),
                              onDelete: () => _deleteWishlist(wishlist),
                              onToggleAlerts: (enabled) async {
                                try {
                                  await WishlistsStore.instance.setAlerts(
                                    wishlist,
                                    enabled,
                                  );
                                } catch (_) {
                                  _message('Impossible de modifier les alertes.');
                                }
                              },
                            );
                          },
                          childCount: lists.length * 2 - 1,
                        ),
                      ),
                    ),
                    SliverToBoxAdapter(
                      child: _PriceAlertBanner(
                        busy: _bulkAlertBusy,
                        onActivate: _activateAllAlerts,
                      ),
                    ),
                    const SliverToBoxAdapter(child: SizedBox(height: 22)),
                  ],
                ],
              ),
            );
          },
        ),
      ),
    );
  }
}

class _WishlistHeader extends StatelessWidget {
  final VoidCallback onBack;
  final VoidCallback onSearch;
  final VoidCallback onCreate;

  const _WishlistHeader({
    required this.onBack,
    required this.onSearch,
    required this.onCreate,
  });

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(12, 14, 18, 8),
      child: Row(
        children: [
          IconButton(
            onPressed: onBack,
            icon: const Icon(Icons.arrow_back_rounded),
            color: OvanieColors.navy,
            iconSize: 28,
          ),
          const SizedBox(width: 4),
          const Expanded(
            child: Text(
              "Listes d’envies",
              style: TextStyle(
                color: OvanieColors.navy,
                fontSize: 25,
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
          IconButton(
            onPressed: onSearch,
            icon: const Icon(Icons.search_rounded),
            color: OvanieColors.navy,
            iconSize: 29,
          ),
          IconButton(
            onPressed: onCreate,
            icon: const Icon(Icons.playlist_add_rounded),
            color: OvanieColors.navy,
            iconSize: 30,
          ),
        ],
      ),
    );
  }
}

class _WishlistTabs extends StatelessWidget {
  final int favoritesCount;
  final VoidCallback onFavorites;

  const _WishlistTabs({
    required this.favoritesCount,
    required this.onFavorites,
  });

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(18, 2, 18, 0),
      child: Row(
        children: [
          Expanded(
            child: InkWell(
              onTap: onFavorites,
              child: Container(
                height: 50,
                alignment: Alignment.center,
                decoration: const BoxDecoration(
                  border: Border(
                    bottom: BorderSide(color: OvanieColors.border, width: 1.2),
                  ),
                ),
                child: Text(
                  'Mes favoris ($favoritesCount)',
                  style: const TextStyle(
                    color: OvanieColors.navy,
                    fontSize: 15,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
            ),
          ),
          Expanded(
            child: Container(
              height: 50,
              alignment: Alignment.center,
              decoration: const BoxDecoration(
                border: Border(
                  bottom: BorderSide(color: OvanieColors.orange, width: 2.4),
                ),
              ),
              child: const Text(
                "Listes d’envies",
                style: TextStyle(
                  color: OvanieColors.orange,
                  fontSize: 15,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _IntroBanner extends StatelessWidget {
  final VoidCallback onCreate;

  const _IntroBanner({required this.onCreate});

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.fromLTRB(18, 14, 18, 14),
      padding: const EdgeInsets.fromLTRB(14, 13, 12, 13),
      decoration: BoxDecoration(
        color: const Color(0xFFF7FAFF),
        border: Border.all(color: const Color(0xFFDCE7FA)),
        borderRadius: BorderRadius.circular(9),
      ),
      child: Row(
        children: [
          const Icon(
            Icons.favorite_border_rounded,
            color: Color(0xFF1465FF),
            size: 25,
          ),
          const SizedBox(width: 12),
          const Expanded(
            child: Text(
              "Créez et gérez vos listes d’envies pour organiser vos produits préférés et les retrouver facilement plus tard.",
              style: TextStyle(
                color: OvanieColors.navy,
                fontSize: 12.5,
                height: 1.35,
              ),
            ),
          ),
          const SizedBox(width: 10),
          OutlinedButton.icon(
            onPressed: onCreate,
            icon: const Icon(Icons.add_rounded, size: 19),
            label: const Text('Créer une liste'),
            style: OutlinedButton.styleFrom(
              foregroundColor: const Color(0xFF1465FF),
              side: const BorderSide(color: Color(0xFF1465FF)),
              padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 10),
              textStyle: const TextStyle(fontSize: 11.5, fontWeight: FontWeight.w800),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(7),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _WishlistCard extends StatelessWidget {
  final WishlistModel wishlist;
  final int index;
  final VoidCallback onOpen;
  final VoidCallback onDelete;
  final ValueChanged<bool> onToggleAlerts;

  const _WishlistCard({
    required this.wishlist,
    required this.index,
    required this.onOpen,
    required this.onDelete,
    required this.onToggleAlerts,
  });

  @override
  Widget build(BuildContext context) {
    final palettes = <_WishlistPalette>[
      const _WishlistPalette(
        background: Color(0xFFFFF0EA),
        foreground: OvanieColors.orange,
        icon: Icons.home_outlined,
      ),
      const _WishlistPalette(
        background: Color(0xFFF0FAED),
        foreground: Color(0xFF48A53A),
        icon: Icons.handyman_outlined,
      ),
      const _WishlistPalette(
        background: Color(0xFFF2EFFF),
        foreground: Color(0xFF6346D8),
        icon: Icons.lightbulb_outline_rounded,
      ),
      const _WishlistPalette(
        background: Color(0xFFFFF6DF),
        foreground: Color(0xFFD38B00),
        icon: Icons.plumbing_outlined,
      ),
      const _WishlistPalette(
        background: Color(0xFFFFEEEE),
        foreground: Color(0xFFE73E48),
        icon: Icons.star_border_rounded,
      ),
    ];
    final palette = palettesafe(palettes, index);
    final products = wishlist.products;

    return InkWell(
      onTap: onOpen,
      borderRadius: BorderRadius.circular(12),
      child: Container(
        constraints: const BoxConstraints(minHeight: 160),
        padding: const EdgeInsets.fromLTRB(14, 13, 12, 12),
        decoration: BoxDecoration(
          color: Colors.white,
          border: Border.all(color: OvanieColors.border),
          borderRadius: BorderRadius.circular(12),
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: 56,
              height: 56,
              decoration: BoxDecoration(
                color: palette.background,
                borderRadius: BorderRadius.circular(10),
              ),
              child: Icon(palette.icon, color: palette.foreground, size: 28),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    wishlist.name,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: OvanieColors.navy,
                      fontSize: 17,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 5),
                  Text(
                    '${wishlist.productsCount} produit${wishlist.productsCount > 1 ? 's' : ''}',
                    style: const TextStyle(
                      color: Color(0xFF53607D),
                      fontSize: 13,
                    ),
                  ),
                  const SizedBox(height: 15),
                  SizedBox(
                    height: 58,
                    child: products.isEmpty
                        ? const Align(
                            alignment: Alignment.centerLeft,
                            child: Text(
                              'Ajoutez vos premiers produits',
                              style: TextStyle(
                                color: OvanieColors.muted,
                                fontSize: 12.5,
                              ),
                            ),
                          )
                        : SingleChildScrollView(
                            scrollDirection: Axis.horizontal,
                            child: Row(
                              children: [
                                ...products.take(4).map(
                                      (product) => Padding(
                                        padding: const EdgeInsets.only(right: 7),
                                        child: _PreviewImage(product: product),
                                      ),
                                    ),
                                if (products.length > 4)
                                  Container(
                                    width: 58,
                                    height: 58,
                                    alignment: Alignment.center,
                                    decoration: BoxDecoration(
                                      color: const Color(0xFFF8F9FC),
                                      border: Border.all(color: OvanieColors.border),
                                      borderRadius: BorderRadius.circular(8),
                                    ),
                                    child: Text(
                                      '+${products.length - 4}',
                                      style: const TextStyle(
                                        color: OvanieColors.navy,
                                        fontWeight: FontWeight.w800,
                                      ),
                                    ),
                                  ),
                              ],
                            ),
                          ),
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
                  Row(
                    mainAxisAlignment: MainAxisAlignment.end,
                    children: [
                      Expanded(
                        child: Text(
                          'Modifiée ${_formatDate(wishlist.updatedAt)}',
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          textAlign: TextAlign.end,
                          style: const TextStyle(
                            color: Color(0xFF53607D),
                            fontSize: 11.5,
                          ),
                        ),
                      ),
                      PopupMenuButton<String>(
                        padding: EdgeInsets.zero,
                        icon: const Icon(
                          Icons.more_vert_rounded,
                          color: OvanieColors.navy,
                        ),
                        onSelected: (value) {
                          if (value == 'delete') onDelete();
                          if (value == 'alerts') {
                            onToggleAlerts(!wishlist.alertsEnabled);
                          }
                        },
                        itemBuilder: (_) => [
                          PopupMenuItem<String>(
                            value: 'alerts',
                            child: Text(
                              wishlist.alertsEnabled
                                  ? 'Désactiver les alertes'
                                  : 'Activer les alertes',
                            ),
                          ),
                          const PopupMenuItem<String>(
                            value: 'delete',
                            child: Text('Supprimer la liste'),
                          ),
                        ],
                      ),
                    ],
                  ),
                  const Spacer(),
                  if (wishlist.alertsEnabled)
                    const Padding(
                      padding: EdgeInsets.only(right: 2),
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.end,
                        children: [
                          Icon(
                            Icons.notifications_active_outlined,
                            color: OvanieColors.success,
                            size: 17,
                          ),
                          SizedBox(width: 5),
                          Text(
                            'Alertes actives',
                            style: TextStyle(
                              color: OvanieColors.success,
                              fontSize: 11,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ],
                      ),
                    ),
                  const SizedBox(height: 10),
                  const Icon(
                    Icons.chevron_right_rounded,
                    color: OvanieColors.navy,
                    size: 26,
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  static _WishlistPalette palettesafe(List<_WishlistPalette> list, int index) {
    return list[index % list.length];
  }

  static String _formatDate(DateTime? date) {
    if (date == null) return 'récemment';
    const months = <String>[
      'janv.',
      'févr.',
      'mars',
      'avr.',
      'mai',
      'juin',
      'juil.',
      'août',
      'sept.',
      'oct.',
      'nov.',
      'déc.',
    ];
    final local = date.toLocal();
    return 'le ${local.day} ${months[local.month - 1]} ${local.year}';
  }
}

class _WishlistPalette {
  final Color background;
  final Color foreground;
  final IconData icon;

  const _WishlistPalette({
    required this.background,
    required this.foreground,
    required this.icon,
  });
}

class _PreviewImage extends StatelessWidget {
  final ProductModel product;

  const _PreviewImage({required this.product});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 58,
      height: 58,
      padding: const EdgeInsets.all(4),
      decoration: BoxDecoration(
        color: Colors.white,
        border: Border.all(color: OvanieColors.border),
        borderRadius: BorderRadius.circular(8),
      ),
      child: product.imageUrl.trim().isEmpty
          ? const Icon(Icons.inventory_2_outlined, color: Color(0xFFB7C1D0))
          : Image.network(
              product.imageUrl,
              fit: BoxFit.contain,
              errorBuilder: (_, __, ___) => const Icon(
                Icons.inventory_2_outlined,
                color: Color(0xFFB7C1D0),
              ),
            ),
    );
  }
}

class _PriceAlertBanner extends StatelessWidget {
  final bool busy;
  final VoidCallback onActivate;

  const _PriceAlertBanner({required this.busy, required this.onActivate});

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.fromLTRB(18, 0, 18, 0),
      padding: const EdgeInsets.fromLTRB(14, 10, 10, 10),
      decoration: BoxDecoration(
        color: const Color(0xFFFFF2EB),
        borderRadius: BorderRadius.circular(9),
      ),
      child: Row(
        children: [
          const Icon(Icons.sell_outlined, color: OvanieColors.orange, size: 25),
          const SizedBox(width: 11),
          const Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Ne manquez aucune baisse de prix !',
                  style: TextStyle(
                    color: OvanieColors.navy,
                    fontSize: 12.5,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                SizedBox(height: 2),
                Text(
                  "Activez les alertes pour être notifié des promotions sur vos listes d’envies.",
                  style: TextStyle(
                    color: OvanieColors.navy,
                    fontSize: 10.5,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 10),
          OutlinedButton(
            onPressed: busy ? null : onActivate,
            style: OutlinedButton.styleFrom(
              foregroundColor: OvanieColors.orange,
              side: const BorderSide(color: OvanieColors.orange),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(7),
              ),
            ),
            child: Text(busy ? 'Activation…' : 'Activer les alertes'),
          ),
        ],
      ),
    );
  }
}

class _EmptyWishlists extends StatelessWidget {
  final VoidCallback onCreate;

  const _EmptyWishlists({required this.onCreate});

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      physics: const AlwaysScrollableScrollPhysics(),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(28, 54, 28, 30),
        child: Column(
          children: [
            Container(
              width: 104,
              height: 104,
              decoration: const BoxDecoration(
                color: Color(0xFFF3F6FD),
                shape: BoxShape.circle,
              ),
              child: const Icon(
                Icons.favorite_border_rounded,
                color: OvanieColors.orange,
                size: 50,
              ),
            ),
            const SizedBox(height: 22),
            const Text(
              "Aucune liste d’envies",
              style: TextStyle(
                color: OvanieColors.navy,
                fontSize: 24,
                fontWeight: FontWeight.w900,
              ),
            ),
            const SizedBox(height: 12),
            const Text(
              "Créez votre première liste pour organiser vos produits préférés par projet ou par besoin.",
              textAlign: TextAlign.center,
              style: TextStyle(
                color: OvanieColors.muted,
                fontSize: 15,
                height: 1.5,
              ),
            ),
            const SizedBox(height: 26),
            SizedBox(
              width: 230,
              height: 50,
              child: FilledButton.icon(
                onPressed: onCreate,
                icon: const Icon(Icons.add_rounded),
                label: const Text('Créer une liste'),
                style: FilledButton.styleFrom(
                  backgroundColor: OvanieColors.orange,
                  textStyle: const TextStyle(fontWeight: FontWeight.w900),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _WishlistProductTile extends StatelessWidget {
  final ProductModel product;
  final VoidCallback onTap;

  const _WishlistProductTile({required this.product, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return ListTile(
      onTap: onTap,
      contentPadding: EdgeInsets.zero,
      leading: _PreviewImage(product: product),
      title: Text(
        product.name,
        maxLines: 2,
        overflow: TextOverflow.ellipsis,
        style: const TextStyle(
          color: OvanieColors.navy,
          fontWeight: FontWeight.w800,
        ),
      ),
      subtitle: Text(
        product.availabilityLabel,
        style: const TextStyle(color: OvanieColors.success),
      ),
      trailing: const Icon(Icons.chevron_right_rounded),
    );
  }
}
