import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../app/theme.dart';
import '../../../core/utils/formatters.dart';
import '../../auth/domain/session_store.dart';
import '../../checkout/presentation/checkout_screen.dart' show shortSaleUnit;
import '../../payments/presentation/payment_ui.dart';
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
      showOvanieToast(context, 'Connectez-vous pour créer une liste d’envies.', error: true);
      return;
    }
    final name = await showCreateWishlistDialog(context);
    if (name == null || !mounted) return;
    try {
      await WishlistsStore.instance.create(name);
      if (mounted) showOvanieToast(context, 'Liste créée.');
    } catch (_) {
      if (mounted) showOvanieToast(context, 'Impossible de créer cette liste pour le moment.', error: true);
    }
  }

  Future<void> _openListMenu(WishlistModel wishlist) async {
    final action = await showModalBottomSheet<String>(
      context: context,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(26))),
      builder: (sheetContext) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(24, 10, 24, 12),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const WishlistSheetHandle(),
              const SizedBox(height: 22),
              Text(
                wishlist.name,
                style: const TextStyle(color: payNavy, fontSize: 23, fontWeight: FontWeight.w800),
              ),
              const SizedBox(height: 14),
              WishlistSheetAction(
                icon: wishlist.alertsEnabled ? Icons.notifications_off_outlined : Icons.notifications_active_outlined,
                label: wishlist.alertsEnabled ? 'Désactiver les alertes' : 'Activer les alertes',
                onTap: () => Navigator.of(sheetContext).pop('alerts'),
              ),
              const Divider(height: 1, color: payBorder),
              WishlistSheetAction(
                icon: Icons.delete_outline_rounded,
                label: 'Supprimer la liste',
                danger: true,
                onTap: () => Navigator.of(sheetContext).pop('delete'),
              ),
            ],
          ),
        ),
      ),
    );
    if (!mounted || action == null) return;
    if (action == 'delete') await _deleteWishlist(wishlist);
    if (action == 'alerts') await _toggleAlerts(wishlist);
  }

  Future<void> _toggleAlerts(WishlistModel wishlist) async {
    final enable = !wishlist.alertsEnabled;
    try {
      await WishlistsStore.instance.setAlerts(wishlist, enable);
      if (mounted) showOvanieToast(context, enable ? 'Alertes activées pour cette liste.' : 'Alertes désactivées.');
    } catch (_) {
      if (mounted) showOvanieToast(context, 'Impossible de modifier les alertes.', error: true);
    }
  }

  Future<void> _deleteWishlist(WishlistModel wishlist) async {
    final confirmed = await showOvanieConfirmDialog(
      context,
      visual: const DangerCircle(icon: Icons.delete_outline_rounded),
      title: 'Supprimer cette liste ?',
      message: 'La liste « ${wishlist.name} » sera supprimée.',
      confirmLabel: 'Supprimer',
      confirmColor: const Color(0xFFDC2626),
      cancelLabel: 'Annuler',
    );
    if (!confirmed || !mounted) return;
    try {
      await WishlistsStore.instance.delete(wishlist);
      if (mounted) showOvanieToast(context, 'Liste supprimée.');
    } catch (_) {
      if (mounted) showOvanieToast(context, 'Impossible de supprimer cette liste.', error: true);
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
      if (mounted) showOvanieToast(context, 'Alertes activées pour vos listes d’envies.');
    } catch (_) {
      if (mounted) showOvanieToast(context, 'Impossible d’activer toutes les alertes.', error: true);
    } finally {
      if (mounted) setState(() => _bulkAlertBusy = false);
    }
  }

  void _openWishlist(WishlistModel wishlist) {
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(26))),
      builder: (sheetContext) => DraggableScrollableSheet(
        expand: false,
        initialChildSize: .72,
        minChildSize: .45,
        maxChildSize: .92,
        builder: (context, scrollController) => Column(
          children: [
            const Padding(padding: EdgeInsets.only(top: 10), child: WishlistSheetHandle()),
            Padding(
              padding: const EdgeInsets.fromLTRB(22, 18, 12, 12),
              child: Row(
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          wishlist.name,
                          style: const TextStyle(color: payNavy, fontSize: 22, fontWeight: FontWeight.w800),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          _countLabel(wishlist.productsCount),
                          style: const TextStyle(color: payMuted, fontSize: 15),
                        ),
                      ],
                    ),
                  ),
                  IconButton(
                    onPressed: () => Navigator.of(sheetContext).pop(),
                    tooltip: 'Fermer',
                    icon: const Icon(Icons.close_rounded, color: payNavy, size: 28),
                  ),
                ],
              ),
            ),
            const Divider(height: 1, color: payBorder),
            Expanded(
              child: wishlist.products.isEmpty
                  ? const Center(
                      child: Padding(
                        padding: EdgeInsets.all(28),
                        child: Text(
                          'Cette liste ne contient encore aucun produit. Ajoutez-en depuis vos favoris.',
                          textAlign: TextAlign.center,
                          style: TextStyle(color: payMuted, fontSize: 15, height: 1.4),
                        ),
                      ),
                    )
                  : ListView.separated(
                      controller: scrollController,
                      padding: const EdgeInsets.fromLTRB(20, 14, 20, 20),
                      itemCount: wishlist.products.length,
                      separatorBuilder: (_, __) => const Divider(height: 20, color: payBorder),
                      itemBuilder: (context, index) {
                        final product = wishlist.products[index];
                        return _WishlistProductTile(
                          product: product,
                          onTap: () {
                            Navigator.of(sheetContext).pop();
                            _openProduct(product);
                          },
                          onRemove: () async {
                            Navigator.of(sheetContext).pop();
                            try {
                              await WishlistsStore.instance.removeProduct(wishlist, product);
                              if (mounted) showOvanieToast(this.context, 'Produit retiré de la liste.');
                            } catch (_) {
                              if (mounted) showOvanieToast(this.context, 'Impossible de retirer ce produit.', error: true);
                            }
                          },
                        );
                      },
                    ),
            ),
          ],
        ),
      ),
    );
  }

  void _openProduct(ProductModel product) {
    Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => ProductDetailScreen(product: product)));
  }

  @override
  Widget build(BuildContext context) {
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.dark.copyWith(statusBarColor: Colors.transparent),
      child: Scaffold(
        backgroundColor: Colors.white,
        body: SafeArea(
          bottom: false,
          child: AnimatedBuilder(
            animation: WishlistsStore.instance,
            builder: (context, _) {
              final lists = WishlistsStore.instance.items;
              final loading = WishlistsStore.instance.loading && lists.isEmpty;
              final anyWithoutAlerts = lists.any((list) => !list.alertsEnabled);
              return RefreshIndicator(
                color: OvanieColors.orange,
                onRefresh: () async {
                  if (SessionStore.instance.isAuthenticated) await WishlistsStore.instance.refresh();
                },
                child: CustomScrollView(
                  physics: const AlwaysScrollableScrollPhysics(parent: BouncingScrollPhysics()),
                  slivers: [
                    const SliverToBoxAdapter(child: FavoritesPageTitle(title: 'Listes d’envies')),
                    SliverToBoxAdapter(
                      child: FavoritesSegmentedTabs(
                        favoritesCount: widget.favoritesCount,
                        wishlistsSelected: true,
                        onFavorites: widget.onBackToFavorites,
                        onWishlists: () {},
                      ),
                    ),
                    SliverToBoxAdapter(
                      child: Padding(
                        padding: const EdgeInsets.fromLTRB(18, 16, 18, 4),
                        child: Row(
                          children: [
                            const Expanded(
                              child: Text(
                                'Créez et gérez vos listes d’envies pour organiser vos produits préférés et les retrouver facilement plus tard.',
                                style: TextStyle(color: payMuted, fontSize: 13.5, height: 1.4),
                              ),
                            ),
                            if (lists.isNotEmpty) ...[
                              const SizedBox(width: 12),
                              SizedBox(
                                height: 44,
                                child: FilledButton.icon(
                                  onPressed: _createWishlist,
                                  style: FilledButton.styleFrom(
                                    elevation: 0,
                                    backgroundColor: OvanieColors.orange,
                                    foregroundColor: Colors.white,
                                    padding: const EdgeInsets.symmetric(horizontal: 14),
                                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                                  ),
                                  icon: const Icon(Icons.add_rounded, size: 22),
                                  label: const Text(
                                    'Créer une liste',
                                    style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700),
                                  ),
                                ),
                              ),
                            ],
                          ],
                        ),
                      ),
                    ),
                    if (loading)
                      const SliverFillRemaining(
                        hasScrollBody: false,
                        child: Center(child: CircularProgressIndicator(color: OvanieColors.orange)),
                      )
                    else if (lists.isEmpty)
                      SliverFillRemaining(hasScrollBody: false, child: _EmptyWishlists(onCreate: _createWishlist))
                    else ...[
                      SliverPadding(
                        padding: const EdgeInsets.fromLTRB(18, 14, 18, 0),
                        sliver: SliverList(
                          delegate: SliverChildBuilderDelegate(
                            (context, index) => Padding(
                              padding: const EdgeInsets.only(bottom: 14),
                              child: _WishlistCard(
                                wishlist: lists[index],
                                onOpen: () => _openWishlist(lists[index]),
                                onMenu: () => _openListMenu(lists[index]),
                              ),
                            ),
                            childCount: lists.length,
                          ),
                        ),
                      ),
                      if (anyWithoutAlerts)
                        SliverToBoxAdapter(
                          child: _PriceAlertBanner(busy: _bulkAlertBusy, onActivate: _activateAllAlerts),
                        ),
                      const SliverToBoxAdapter(child: SizedBox(height: 22)),
                    ],
                  ],
                ),
              );
            },
          ),
        ),
      ),
    );
  }
}

String _countLabel(int count) => '$count produit${count > 1 ? 's' : ''}';

/// Grand titre en haut des onglets Favoris / Listes d'envies.
class FavoritesPageTitle extends StatelessWidget {
  final String title;
  final Widget? trailing;

  const FavoritesPageTitle({super.key, required this.title, this.trailing});

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.fromLTRB(18, 18, 10, 12),
    child: Row(
      children: [
        Expanded(
          child: Text(
            title,
            style: const TextStyle(color: payNavy, fontSize: 34, height: 1.05, fontWeight: FontWeight.w900),
          ),
        ),
        if (trailing != null) trailing!,
      ],
    ),
  );
}

/// Onglets « Mes favoris (n) » / « Listes d'envies » : l'onglet actif est plein.
class FavoritesSegmentedTabs extends StatelessWidget {
  final int favoritesCount;
  final bool wishlistsSelected;
  final VoidCallback onFavorites;
  final VoidCallback onWishlists;

  const FavoritesSegmentedTabs({
    super.key,
    required this.favoritesCount,
    required this.wishlistsSelected,
    required this.onFavorites,
    required this.onWishlists,
  });

  @override
  Widget build(BuildContext context) {
    Widget tab(String label, bool selected, VoidCallback onTap) => Expanded(
      child: GestureDetector(
        onTap: onTap,
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 160),
          height: 52,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: selected ? const Color(0xFF0A1B4F) : Colors.transparent,
            borderRadius: BorderRadius.circular(12),
          ),
          child: Text(
            label,
            style: TextStyle(
              color: selected ? Colors.white : const Color(0xFF4A556E),
              fontSize: 17,
              fontWeight: selected ? FontWeight.w700 : FontWeight.w500,
            ),
          ),
        ),
      ),
    );
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 16),
      padding: const EdgeInsets.all(3),
      decoration: BoxDecoration(
        color: const Color(0xFFF7F8FA),
        borderRadius: BorderRadius.circular(15),
        border: Border.all(color: const Color(0xFFDDE2EA)),
      ),
      child: Row(
        children: [
          tab('Mes favoris ($favoritesCount)', !wishlistsSelected, onFavorites),
          tab('Listes d’envies', wishlistsSelected, onWishlists),
        ],
      ),
    );
  }
}

class WishlistSheetHandle extends StatelessWidget {
  const WishlistSheetHandle({super.key});

  @override
  Widget build(BuildContext context) => Center(
    child: Container(
      width: 46,
      height: 5,
      decoration: BoxDecoration(color: const Color(0xFFD0D6DF), borderRadius: BorderRadius.circular(3)),
    ),
  );
}

/// Ligne d'action d'une fenêtre du bas (icône, libellé, chevron facultatif).
class WishlistSheetAction extends StatelessWidget {
  final IconData icon;
  final String label;
  final VoidCallback onTap;
  final bool danger;
  final bool chevron;

  const WishlistSheetAction({
    super.key,
    required this.icon,
    required this.label,
    required this.onTap,
    this.danger = false,
    this.chevron = false,
  });

  @override
  Widget build(BuildContext context) {
    final color = danger ? const Color(0xFFDC2626) : payNavy;
    return InkWell(
      onTap: onTap,
      child: SizedBox(
        height: 68,
        child: Row(
          children: [
            Icon(icon, color: color, size: 30),
            const SizedBox(width: 22),
            Expanded(
              child: Text(label, style: TextStyle(color: color, fontSize: 18)),
            ),
            if (chevron) const Icon(Icons.chevron_right_rounded, color: payMuted, size: 28),
          ],
        ),
      ),
    );
  }
}

/// Rond rouge pâle avec une icône rouge (fenêtres de suppression).
class DangerCircle extends StatelessWidget {
  final IconData icon;

  const DangerCircle({super.key, required this.icon});

  @override
  Widget build(BuildContext context) => Container(
    width: 112,
    height: 112,
    decoration: const BoxDecoration(color: Color(0xFFFDE4E4), shape: BoxShape.circle),
    child: Icon(icon, color: const Color(0xFFDC2626), size: 54),
  );
}

/// Fenêtre « Créer une liste » : renvoie le nom saisi, ou null.
Future<String?> showCreateWishlistDialog(BuildContext context) {
  return showDialog<String>(context: context, builder: (_) => const _CreateWishlistDialog());
}

class _CreateWishlistDialog extends StatefulWidget {
  const _CreateWishlistDialog();

  @override
  State<_CreateWishlistDialog> createState() => _CreateWishlistDialogState();
}

class _CreateWishlistDialogState extends State<_CreateWishlistDialog> {
  final _controller = TextEditingController();

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  void _submit() {
    final name = _controller.text.trim();
    if (name.isEmpty) return;
    Navigator.of(context).pop(name);
  }

  @override
  Widget build(BuildContext context) {
    return Dialog(
      backgroundColor: Colors.white,
      surfaceTintColor: Colors.white,
      insetPadding: const EdgeInsets.symmetric(horizontal: 30),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(22)),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(22, 26, 22, 22),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Center(
              child: Container(
                width: 96,
                height: 96,
                decoration: const BoxDecoration(color: Color(0xFFFFE9DD), shape: BoxShape.circle),
                child: const Icon(Icons.description_outlined, color: OvanieColors.orange, size: 46),
              ),
            ),
            const SizedBox(height: 16),
            const Center(
              child: Text(
                'Créer une liste',
                style: TextStyle(color: payNavy, fontSize: 26, fontWeight: FontWeight.w900),
              ),
            ),
            const SizedBox(height: 18),
            const Text(
              'Nom de la liste',
              style: TextStyle(color: payNavy, fontSize: 17, fontWeight: FontWeight.w700),
            ),
            const SizedBox(height: 10),
            TextField(
              controller: _controller,
              autofocus: true,
              maxLength: 60,
              textCapitalization: TextCapitalization.sentences,
              onChanged: (_) => setState(() {}),
              onSubmitted: (_) => _submit(),
              style: const TextStyle(color: payNavy, fontSize: 17),
              decoration: InputDecoration(
                hintText: 'Ex. Maison & Rénovation',
                hintStyle: const TextStyle(color: Color(0xFF8A93A8)),
                counterText: '',
                contentPadding: const EdgeInsets.symmetric(horizontal: 18, vertical: 18),
                enabledBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                  borderSide: const BorderSide(color: Color(0xFFD5DCE6)),
                ),
                focusedBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                  borderSide: const BorderSide(color: OvanieColors.orange, width: 1.4),
                ),
              ),
            ),
            const SizedBox(height: 22),
            Row(
              children: [
                Expanded(
                  child: SizedBox(
                    height: 54,
                    child: TextButton(
                      onPressed: () => Navigator.of(context).pop(),
                      style: TextButton.styleFrom(
                        backgroundColor: const Color(0xFFEFF2F6),
                        foregroundColor: const Color(0xFF4A556E),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                      ),
                      child: const Text('Annuler', style: TextStyle(fontSize: 17, fontWeight: FontWeight.w700)),
                    ),
                  ),
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: PaymentButton(label: 'Créer', onPressed: _controller.text.trim().isEmpty ? null : _submit),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

/// Mosaïque 2 × 2 des produits d'une liste ; « +n » sur la dernière case.
class WishlistThumbGrid extends StatelessWidget {
  final List<ProductModel> products;
  final double size;

  const WishlistThumbGrid({super.key, required this.products, required this.size});

  @override
  Widget build(BuildContext context) {
    final gap = size * .04;
    final cell = (size - gap) / 2;
    final extra = products.length - 4;
    Widget tile(int index) {
      if (index >= products.length) {
        return Container(
          width: cell,
          height: cell,
          decoration: BoxDecoration(color: const Color(0xFFF1F3F6), borderRadius: BorderRadius.circular(6)),
        );
      }
      final image = OrderItemThumbnail(url: products[index].imageUrl, width: cell, height: cell);
      if (index == 3 && extra > 0) {
        return Stack(
          children: [
            image,
            Positioned.fill(
              child: Container(
                alignment: Alignment.center,
                decoration: BoxDecoration(color: const Color(0x99263043), borderRadius: BorderRadius.circular(8)),
                child: Text(
                  '+$extra',
                  style: TextStyle(color: Colors.white, fontSize: cell * .32, fontWeight: FontWeight.w800),
                ),
              ),
            ),
          ],
        );
      }
      return image;
    }

    return SizedBox(
      width: size,
      height: size,
      child: Column(
        children: [
          Row(
            children: [
              tile(0),
              SizedBox(width: gap),
              tile(1),
            ],
          ),
          SizedBox(height: gap),
          Row(
            children: [
              tile(2),
              SizedBox(width: gap),
              tile(3),
            ],
          ),
        ],
      ),
    );
  }
}

class _WishlistCard extends StatelessWidget {
  final WishlistModel wishlist;
  final VoidCallback onOpen;
  final VoidCallback onMenu;

  const _WishlistCard({required this.wishlist, required this.onOpen, required this.onMenu});

  @override
  Widget build(BuildContext context) {
    final width = MediaQuery.sizeOf(context).width;
    final thumb = (width * .42).clamp(130.0, 190.0);
    final empty = wishlist.products.isEmpty;
    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        onTap: onOpen,
        borderRadius: BorderRadius.circular(16),
        child: Container(
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: payBorder),
          ),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              if (empty)
                CustomPaint(
                  painter: _DashedRectPainter(),
                  child: Container(
                    width: thumb,
                    height: thumb,
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(color: const Color(0xFFF4F6F9), borderRadius: BorderRadius.circular(10)),
                    child: const Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(Icons.inventory_2_outlined, color: Color(0xFF8A93A8), size: 38),
                        SizedBox(height: 10),
                        Text(
                          'Ajoutez vos premiers produits',
                          textAlign: TextAlign.center,
                          style: TextStyle(color: payMuted, fontSize: 13.5, height: 1.3),
                        ),
                      ],
                    ),
                  ),
                )
              else
                WishlistThumbGrid(products: wishlist.products, size: thumb),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Expanded(
                          child: Padding(
                            padding: const EdgeInsets.only(top: 8),
                            child: Text(
                              wishlist.name,
                              maxLines: 2,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(color: payNavy, fontSize: 19, fontWeight: FontWeight.w900),
                            ),
                          ),
                        ),
                        IconButton(
                          onPressed: onMenu,
                          tooltip: 'Options',
                          icon: const Icon(Icons.more_vert_rounded, color: payNavy, size: 28),
                        ),
                      ],
                    ),
                    if (!empty) ...[
                      Text(
                        _countLabel(wishlist.productsCount),
                        style: const TextStyle(color: payMuted, fontSize: 16, fontWeight: FontWeight.w700),
                      ),
                      if (wishlist.updatedAt != null) ...[
                        const SizedBox(height: 10),
                        Text(
                          'Modifiée le ${paymentShortDate(wishlist.updatedAt)}',
                          style: const TextStyle(color: payMuted, fontSize: 14),
                        ),
                      ],
                    ],
                    if (wishlist.alertsEnabled) ...[
                      const SizedBox(height: 14),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                        decoration: BoxDecoration(color: payGreenSoft, borderRadius: BorderRadius.circular(12)),
                        child: const Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Icon(Icons.notifications_none_rounded, color: payGreenDark, size: 20),
                            SizedBox(width: 6),
                            Text(
                              'Alertes actives',
                              style: TextStyle(color: payGreenDark, fontSize: 14.5, fontWeight: FontWeight.w700),
                            ),
                          ],
                        ),
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

class _DashedRectPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = const Color(0xFFC9D0DC)
      ..style = PaintingStyle.stroke
      ..strokeWidth = 1.2;
    final rect = const EdgeInsets.all(8).deflateRect(Offset.zero & size);
    final path = Path()..addRRect(RRect.fromRectAndRadius(rect, const Radius.circular(8)));
    for (final metric in path.computeMetrics()) {
      var distance = 0.0;
      while (distance < metric.length) {
        canvas.drawPath(metric.extractPath(distance, distance + 5), paint);
        distance += 9;
      }
    }
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}

class _PriceAlertBanner extends StatelessWidget {
  final bool busy;
  final VoidCallback onActivate;

  const _PriceAlertBanner({required this.busy, required this.onActivate});

  @override
  Widget build(BuildContext context) => Container(
    margin: const EdgeInsets.fromLTRB(18, 4, 18, 0),
    padding: const EdgeInsets.fromLTRB(14, 18, 16, 18),
    decoration: BoxDecoration(color: const Color(0xFFEAF2FC), borderRadius: BorderRadius.circular(16)),
    child: Row(
      children: [
        const SizedBox(
          width: 86,
          child: Icon(Icons.notifications_active_rounded, color: OvanieColors.orange, size: 62),
        ),
        Container(width: 1, height: 96, color: const Color(0xFFC8D6EA)),
        const SizedBox(width: 16),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Ne manquez aucune baisse de prix !',
                style: TextStyle(color: payNavy, fontSize: 18, fontWeight: FontWeight.w800),
              ),
              const SizedBox(height: 4),
              const Text(
                'Activez les alertes pour être notifié des promotions sur vos listes d’envies.',
                style: TextStyle(color: payMuted, fontSize: 14, height: 1.35),
              ),
              const SizedBox(height: 12),
              SizedBox(
                height: 46,
                child: FilledButton(
                  onPressed: busy ? null : onActivate,
                  style: FilledButton.styleFrom(
                    elevation: 0,
                    backgroundColor: OvanieColors.orange,
                    disabledBackgroundColor: OvanieColors.orange,
                    foregroundColor: Colors.white,
                    disabledForegroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(horizontal: 22),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      if (busy) ...[
                        const SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(strokeWidth: 2.2, color: Colors.white),
                        ),
                        const SizedBox(width: 10),
                      ],
                      Text(
                        busy ? 'Activation…' : 'Activer les alertes',
                        style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w700),
                      ),
                    ],
                  ),
                ),
              ),
            ],
          ),
        ),
      ],
    ),
  );
}

class _EmptyWishlists extends StatelessWidget {
  final VoidCallback onCreate;

  const _EmptyWishlists({required this.onCreate});

  @override
  Widget build(BuildContext context) {
    final width = MediaQuery.sizeOf(context).width;
    return Padding(
      padding: const EdgeInsets.fromLTRB(24, 30, 24, 30),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Image.asset(
            'assets/images/checkout/wishlists_empty.png',
            width: (width * .74).clamp(220.0, 340.0),
            fit: BoxFit.contain,
          ),
          const SizedBox(height: 26),
          const Text(
            'Aucune liste d’envies',
            textAlign: TextAlign.center,
            style: TextStyle(color: payNavy, fontSize: 26, fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 10),
          const Text(
            'Créez votre première liste pour organiser vos produits préférés par projet ou par besoin.',
            textAlign: TextAlign.center,
            style: TextStyle(color: payMuted, fontSize: 16, height: 1.45),
          ),
          const SizedBox(height: 24),
          SizedBox(
            width: (width * .64).clamp(220.0, 320.0),
            child: PaymentButton(label: 'Créer une liste', onPressed: onCreate),
          ),
        ],
      ),
    );
  }
}

class _WishlistProductTile extends StatelessWidget {
  final ProductModel product;
  final VoidCallback onTap;
  final VoidCallback onRemove;

  const _WishlistProductTile({required this.product, required this.onTap, required this.onRemove});

  @override
  Widget build(BuildContext context) => InkWell(
    onTap: onTap,
    child: Row(
      children: [
        OrderItemThumbnail(url: product.imageUrl, width: 72, height: 72),
        const SizedBox(width: 14),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                product.name,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(color: payNavy, fontSize: 15.5, fontWeight: FontWeight.w700),
              ),
              const SizedBox(height: 4),
              Text.rich(
                TextSpan(
                  text: formatFcfa(product.finalPrice),
                  style: const TextStyle(color: payNavy, fontSize: 15, fontWeight: FontWeight.w800),
                  children: [
                    TextSpan(
                      text: ' / ${shortSaleUnit(product.unitLabel)}',
                      style: const TextStyle(color: payMuted, fontWeight: FontWeight.w400),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
        IconButton(
          onPressed: onRemove,
          tooltip: 'Retirer de la liste',
          icon: const Icon(Icons.delete_outline_rounded, color: payMuted),
        ),
      ],
    ),
  );
}
