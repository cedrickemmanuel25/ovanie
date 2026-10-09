import 'package:flutter/material.dart';
import '../../core/sync/live_sync_service.dart';

import '../../core/network/api_client.dart';
import '../../core/ui/vendor_design.dart';
import '../../core/ui/vendor_mockup_ui.dart';
import '../../data/vendor_repository.dart';
import '../notifications/vendor_notifications_screen.dart';
import '../shell/vendor_tabs.dart';
import 'product_boost_screen.dart';
import 'product_form_screen.dart';
import 'product_images_screen.dart';
import 'product_ui.dart';

/// Fiche produit de gestion Vendeur.
///
/// Les informations descriptives proviennent du même contrat produit que le
/// Web public et l'application Client. Le prix affiché ici reste toutefois le
/// prix saisi par le vendeur (seller_price) et jamais le prix public OVANIE.
class ProductDetailScreen extends StatefulWidget {
  const ProductDetailScreen({
    super.key,
    required this.productId,
    this.initialProduct,
  });

  final int productId;
  final Map<String, dynamic>? initialProduct;

  @override
  State<ProductDetailScreen> createState() => _ProductDetailScreenState();
}

class _ProductDetailScreenState extends State<ProductDetailScreen>
    with LiveSyncListener<ProductDetailScreen> {
  Map<String, dynamic>? _product;
  bool _refreshing = false;
  String? _error;
  int _imageIndex = 0;
  int _tab = 0;
  int _notificationCount = 0;

  @override
  void initState() {
    super.initState();
    if (widget.initialProduct != null) {
      _product = Map<String, dynamic>.from(widget.initialProduct!);
    }
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _load();
      _loadNotifications();
    });
    listenLiveSync({'shop', 'notifications'}, () {
      if (!_refreshing) _load(force: true);
      _loadNotifications();
    });
  }

  Future<void> _load({bool force = false}) async {
    if (!mounted) return;
    setState(() {
      _refreshing = true;
      if (_product == null) _error = null;
    });

    try {
      final data = await VendorRepository.instance.product(
        widget.productId,
        forceRefresh: force,
      );
      if (!mounted) return;

      final detailed = data['product'] is Map
          ? Map<String, dynamic>.from(data['product'] as Map)
          : <String, dynamic>{};

      setState(() {
        _product = <String, dynamic>{...?_product, ...detailed};
        _imageIndex = 0;
        _error = null;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() => _error = ApiClient.friendlyError(e));
    } finally {
      if (mounted) setState(() => _refreshing = false);
    }
  }

  Future<void> _edit() async {
    final changed = await Navigator.push<bool>(
      context,
      MaterialPageRoute(
        builder: (_) => ProductFormScreen(productId: widget.productId),
      ),
    );
    if (changed == true) await _load(force: true);
  }

  Future<void> _images() async {
    await Navigator.push<bool>(
      context,
      MaterialPageRoute(
        builder: (_) => ProductImagesScreen(productId: widget.productId),
      ),
    );
    await _load(force: true);
  }

  Future<void> _boost() async {
    final product = _product;
    final changed = await Navigator.push<bool>(
      context,
      MaterialPageRoute(
        builder: (_) => ProductBoostScreen(
          productId: widget.productId,
          productName: _text(product?['name'], fallback: 'Produit'),
          productImageUrl: product == null ? null : productImageUrl(product),
        ),
      ),
    );
    if (changed == true) await _load(force: true);
  }

  Future<void> _toggleActive() async {
    final product = _product;
    if (product == null || '${product['status']}'.toLowerCase() == 'archived') {
      return;
    }

    final active = _bool(product['is_active']);
    try {
      await VendorRepository.instance.toggleProduct(widget.productId, !active);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(active ? 'Produit désactivé.' : 'Produit activé.'),
        ),
      );
      await _load(force: true);
    } catch (e) {
      if (mounted) setState(() => _error = ApiClient.friendlyError(e));
    }
  }

  Future<void> _openNotifications() async {
    await Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => const VendorNotificationsScreen()),
    );
    if (mounted) _loadNotifications();
  }

  /// Nombre de notifications non lues, pour la pastille de la cloche.
  Future<void> _loadNotifications() async {
    try {
      final notifications = await VendorRepository.instance.notifications(page: 1);
      final unread = int.tryParse('${notifications['unread_count'] ?? 0}') ?? 0;
      if (mounted) setState(() => _notificationCount = unread);
    } catch (_) {
      // La fiche reste utilisable sans la pastille.
    }
  }

  Future<void> _showMore() async {
    await showModalBottomSheet<void>(
      context: context,
      backgroundColor: Colors.transparent,
      isScrollControlled: true,
      builder: (sheetContext) {
        void run(VoidCallback action) {
          Navigator.pop(sheetContext);
          action();
        }

        return MkTheme(
          lightStatusBar: false,
          child: Container(
            decoration: const BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.vertical(top: Radius.circular(26)),
            ),
            child: SafeArea(
              top: false,
              child: Padding(
                padding: const EdgeInsets.fromLTRB(20, 10, 20, 12),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Container(
                      width: 44,
                      height: 5,
                      decoration: BoxDecoration(color: const Color(0xFFC9CED8), borderRadius: BorderRadius.circular(3)),
                    ),
                    const SizedBox(height: 10),
                    _SheetAction(
                      icon: Icons.edit_outlined,
                      label: 'Modifier le produit',
                      onTap: () => run(_edit),
                    ),
                    const Divider(height: 1, color: _line),
                    _SheetAction(
                      icon: Icons.image_outlined,
                      label: 'Gérer les images',
                      onTap: () => run(_images),
                    ),
                    const Divider(height: 1, color: _line),
                    _SheetAction(
                      icon: Icons.bolt_rounded,
                      label: 'Booster ce produit',
                      orange: true,
                      onTap: () => run(_boost),
                    ),
                  ],
                ),
              ),
            ),
          ),
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final product = _product;
    final archived = '${product?['status']}'.toLowerCase() == 'archived';
    return MkTheme(
      lightStatusBar: false,
      child: Scaffold(
        backgroundColor: const Color(0xFFF5F7FB),
        body: Column(
          children: [
            _DetailHeader(
              breadcrumb: product == null ? 'Mes produits' : _breadcrumb(product),
              notificationCount: _notificationCount,
              onBack: () => Navigator.maybePop(context),
              onNotifications: _openNotifications,
              onMore: _showMore,
            ),
            if (_refreshing && _product != null)
              const LinearProgressIndicator(
                minHeight: 2,
                color: Mk.orange,
                backgroundColor: Color(0xFFFFEEE5),
              ),
            Expanded(
              child: product == null
                  ? (_error == null ? const _DetailSkeleton() : _errorView())
                  : RefreshIndicator(
                      color: Mk.orange,
                      onRefresh: () => _load(force: true),
                      child: _content(),
                    ),
            ),
          ],
        ),
        bottomNavigationBar: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            if (product != null)
              _BottomActions(
                active: _bool(product['is_active']),
                onToggle: archived ? null : _toggleActive,
                onEdit: _edit,
              ),
            const VendorBottomBar(selected: VendorTab.products),
          ],
        ),
      ),
    );
  }

  Widget _errorView() {
    return Padding(
      padding: const EdgeInsets.all(20),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          VendorErrorBox(_error),
          const SizedBox(height: 14),
          MkButton(label: 'Réessayer', arrow: false, onPressed: () => _load(force: true)),
        ],
      ),
    );
  }

  Widget _content() {
    final product = _product ?? <String, dynamic>{};
    final images = _productImages(product);
    final safeIndex = images.isEmpty ? 0 : _imageIndex.clamp(0, images.length - 1).toInt();

    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(12, 10, 12, 20),
      children: [
        _ProductMediaCard(
          images: images,
          selectedIndex: safeIndex,
          onSelect: (index) => setState(() => _imageIndex = index),
          onManage: _images,
        ),
        const SizedBox(height: 10),
        _SellerProductSummary(product: product),
        const SizedBox(height: 14),
        const Padding(
          padding: EdgeInsets.only(left: 2),
          child: Text(
            'INFORMATIONS PRODUIT',
            style: TextStyle(color: Mk.muted, fontSize: 11.5, fontWeight: FontWeight.w600, letterSpacing: 2.4),
          ),
        ),
        const SizedBox(height: 8),
        _ProductQuickFacts(product: product),
        if (product['variants'] is List && (product['variants'] as List).isNotEmpty) ...[
          const SizedBox(height: 10),
          _ProductVariantsCard(variants: (product['variants'] as List).whereType<Map>().map((v) => Map<String, dynamic>.from(v)).toList()),
        ],
        const SizedBox(height: 12),
        _VendorTabs(
          selected: _tab,
          onChanged: (value) => setState(() => _tab = value),
        ),
        const SizedBox(height: 10),
        _VendorTabContent(product: product, selected: _tab),
        if (_error != null) ...[
          const SizedBox(height: 14),
          VendorErrorBox(_error),
        ],
      ],
    );
  }
}

class _ProductVariantsCard extends StatelessWidget {
  const _ProductVariantsCard({required this.variants});
  final List<Map<String, dynamic>> variants;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: _line, width: 1.1),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Modèles / couleurs', style: TextStyle(color: Mk.navy, fontSize: 15, fontWeight: FontWeight.w800)),
          const SizedBox(height: 10),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: variants.map((variant) {
              final value = _text(variant['value'], fallback: 'Variante');
              final type = _text(variant['type'], fallback: 'Modèle');
              final stock = _integer(variant['stock']);
              final image = _text(variant['image_url'] ?? variant['card_url']);
              return Container(
                constraints: const BoxConstraints(minWidth: 145),
                padding: const EdgeInsets.all(9),
                decoration: BoxDecoration(
                  color: const Color(0xFFF8FAFD),
                  borderRadius: BorderRadius.circular(9),
                  border: Border.all(color: const Color(0xFFDCE5F0)),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    if (image.isNotEmpty) ...[
                      ClipRRect(borderRadius: BorderRadius.circular(7), child: Image.network(image, width: 42, height: 42, fit: BoxFit.cover, errorBuilder: (_, __, ___) => const SizedBox(width: 42, height: 42))),
                      const SizedBox(width: 8),
                    ],
                    Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Text(value, style: const TextStyle(color: vendorText, fontSize: 12, fontWeight: FontWeight.w900)),
                      Text('$type · $stock en stock', style: const TextStyle(color: Color(0xFF6A7890), fontSize: 12)),
                    ]),
                  ],
                ),
              );
            }).toList(growable: false),
          ),
        ],
      ),
    );
  }
}

const _line = Color(0xFFE4E9F1);
const _blue = Color(0xFF1668E3);
const _green = Color(0xFF12894A);

/// En-tête blanc : retour, fil d'Ariane centré, cloche et menu.
class _DetailHeader extends StatelessWidget {
  const _DetailHeader({
    required this.breadcrumb,
    required this.notificationCount,
    required this.onBack,
    required this.onNotifications,
    required this.onMore,
  });

  final String breadcrumb;
  final int notificationCount;
  final VoidCallback onBack;
  final VoidCallback onNotifications;
  final VoidCallback onMore;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: const BoxDecoration(
        color: Colors.white,
        border: Border(bottom: BorderSide(color: _line)),
      ),
      child: SafeArea(
        bottom: false,
        child: SizedBox(
          height: 54,
          child: Row(
            children: [
              const SizedBox(width: 4),
              IconButton(
                tooltip: 'Retour',
                onPressed: onBack,
                icon: const Icon(Icons.arrow_back_rounded, color: Mk.navy, size: 26),
              ),
              Expanded(
                child: Text(
                  breadcrumb,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  textAlign: TextAlign.center,
                  style: const TextStyle(color: Mk.muted, fontSize: 13.5, fontWeight: FontWeight.w500),
                ),
              ),
              IconButton(
                tooltip: 'Notifications',
                onPressed: onNotifications,
                icon: Badge(
                  isLabelVisible: notificationCount > 0,
                  backgroundColor: Mk.orange,
                  label: Text(notificationCount > 99 ? '99+' : '$notificationCount'),
                  child: const Icon(Icons.notifications_none_rounded, color: Mk.navy, size: 27),
                ),
              ),
              IconButton(
                tooltip: 'Plus d’actions',
                onPressed: onMore,
                icon: const Icon(Icons.more_vert_rounded, color: Mk.navy, size: 26),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Barre fixe en bas : activer ou désactiver, et modifier.
class _BottomActions extends StatelessWidget {
  const _BottomActions({required this.active, required this.onToggle, required this.onEdit});

  final bool active;
  final VoidCallback? onToggle;
  final VoidCallback onEdit;

  @override
  Widget build(BuildContext context) {
    final toggleColor = onToggle == null ? Mk.hint : (active ? Mk.orange : _green);
    return Container(
      padding: const EdgeInsets.fromLTRB(12, 10, 12, 8),
      decoration: const BoxDecoration(
        color: Colors.white,
        border: Border(top: BorderSide(color: _line)),
      ),
      child: Row(
        children: [
          Expanded(
            child: _ActionButton(
              icon: active ? Icons.power_settings_new_rounded : Icons.play_arrow_rounded,
              label: active ? 'Désactiver' : 'Activer',
              foreground: toggleColor,
              background: Colors.white,
              border: toggleColor,
              onTap: onToggle,
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: _ActionButton(
              icon: Icons.edit_outlined,
              label: 'Modifier',
              foreground: Colors.white,
              background: Mk.orange,
              border: Mk.orange,
              onTap: onEdit,
            ),
          ),
        ],
      ),
    );
  }
}

class _ActionButton extends StatelessWidget {
  const _ActionButton({
    required this.icon,
    required this.label,
    required this.foreground,
    required this.background,
    required this.border,
    required this.onTap,
  });

  final IconData icon;
  final String label;
  final Color foreground;
  final Color background;
  final Color border;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      button: true,
      enabled: onTap != null,
      label: label,
      excludeSemantics: true,
      child: Material(
        color: background,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(10),
          side: BorderSide(color: border, width: 1.5),
        ),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: onTap,
          child: SizedBox(
            height: 46,
            child: Center(
              child: FittedBox(
                fit: BoxFit.scaleDown,
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(icon, color: foreground, size: 22),
                    const SizedBox(width: 10),
                    Text(
                      label,
                      style: TextStyle(fontFamily: Mk.font, color: foreground, fontSize: 15, fontWeight: FontWeight.w700),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

/// Ligne d'action de la feuille « trois points ».
class _SheetAction extends StatelessWidget {
  const _SheetAction({required this.icon, required this.label, required this.onTap, this.orange = false});

  final IconData icon;
  final String label;
  final bool orange;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final color = orange ? Mk.orange : _blue;
    return InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 12),
        child: Row(
          children: [
            Container(
              width: 46,
              height: 46,
              decoration: BoxDecoration(color: color.withValues(alpha: .1), borderRadius: BorderRadius.circular(14)),
              child: Icon(icon, color: color, size: 25),
            ),
            const SizedBox(width: 18),
            Expanded(
              child: Text(
                label,
                style: TextStyle(color: orange ? Mk.orange : Mk.navy, fontSize: 15.5, fontWeight: FontWeight.w700),
              ),
            ),
            const Icon(Icons.chevron_right_rounded, color: Mk.icon, size: 24),
          ],
        ),
      ),
    );
  }
}

/// Pastille colorée (état, disponibilité, prix promotionnel).
class _Pill extends StatelessWidget {
  const _Pill(this.label, this.color, {this.large = false});

  final String label;
  final Color color;
  final bool large;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: EdgeInsets.symmetric(horizontal: large ? 14 : 11, vertical: large ? 6 : 4),
      decoration: BoxDecoration(color: color.withValues(alpha: .13), borderRadius: BorderRadius.circular(99)),
      child: Text(
        label,
        style: TextStyle(color: color, fontSize: large ? 13.5 : 12, fontWeight: FontWeight.w700),
      ),
    );
  }
}

class _ProductMediaCard extends StatelessWidget {
  const _ProductMediaCard({
    required this.images,
    required this.selectedIndex,
    required this.onSelect,
    required this.onManage,
  });

  final List<String> images;
  final int selectedIndex;
  final ValueChanged<int> onSelect;
  final VoidCallback onManage;

  @override
  Widget build(BuildContext context) {
    final mainUrl = images.isEmpty ? '' : images[selectedIndex];

    return Column(
      children: [
        _CardShell(
          padding: EdgeInsets.zero,
          child: AspectRatio(
            aspectRatio: 1.45,
            child: Stack(
              children: [
                Positioned.fill(
                  child: ProductNetworkImage(url: mainUrl, fit: BoxFit.contain, borderRadius: 14),
                ),
                Positioned(
                  top: 8,
                  left: 8,
                  child: Material(
                    color: const Color(0xFFF1F4F9),
                    shape: const StadiumBorder(side: BorderSide(color: _line)),
                    clipBehavior: Clip.antiAlias,
                    child: InkWell(
                      onTap: onManage,
                      child: const Padding(
                        padding: EdgeInsets.symmetric(horizontal: 12, vertical: 9),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Icon(Icons.image_outlined, color: Mk.icon, size: 19),
                            SizedBox(width: 7),
                            Text('Médias', style: TextStyle(color: Mk.icon, fontSize: 13, fontWeight: FontWeight.w600)),
                          ],
                        ),
                      ),
                    ),
                  ),
                ),
                if (images.isNotEmpty)
                  Positioned(
                    right: 10,
                    bottom: 10,
                    child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 5),
                      decoration: BoxDecoration(color: const Color(0xD92B3140), borderRadius: BorderRadius.circular(99)),
                      child: Text(
                        '${selectedIndex + 1}/${images.length}',
                        style: const TextStyle(color: Colors.white, fontSize: 12.5, fontWeight: FontWeight.w600),
                      ),
                    ),
                  ),
              ],
            ),
          ),
        ),
        if (images.length > 1) ...[
          const SizedBox(height: 8),
          LayoutBuilder(
            builder: (context, box) {
              final width = (box.maxWidth - 3 * 8) / 4;
              return SizedBox(
                height: width * .76,
                child: ListView.separated(
                  scrollDirection: Axis.horizontal,
                  itemCount: images.length,
                  separatorBuilder: (_, __) => const SizedBox(width: 8),
                  itemBuilder: (_, index) {
                    final selected = selectedIndex == index;
                    return Semantics(
                      button: true,
                      selected: selected,
                      label: 'Image ${index + 1}',
                      child: InkWell(
                        onTap: () => onSelect(index),
                        borderRadius: BorderRadius.circular(10),
                        child: Container(
                          width: width,
                          padding: const EdgeInsets.all(2),
                          decoration: BoxDecoration(
                            color: Colors.white,
                            borderRadius: BorderRadius.circular(10),
                            border: Border.all(color: selected ? Mk.orange : _line, width: selected ? 2 : 1.1),
                          ),
                          child: ProductNetworkImage(url: images[index], fit: BoxFit.cover, borderRadius: 7),
                        ),
                      ),
                    );
                  },
                ),
              );
            },
          ),
        ],
      ],
    );
  }
}

class _SellerProductSummary extends StatelessWidget {
  const _SellerProductSummary({required this.product});

  final Map<String, dynamic> product;

  @override
  Widget build(BuildContext context) {
    final unit = _capitalize(_displayUnit(product));
    final sellerPrice = _number(
      product['seller_effective_price'] ?? product['seller_promo_price'] ?? product['seller_price'] ?? product['price'],
    );
    final sellerBasePrice = _number(product['seller_price'] ?? product['price']);
    final hasPromo = sellerBasePrice > 0 && sellerPrice > 0 && sellerPrice < sellerBasePrice;
    final rating = _number(product['rating'] ?? product['average_rating']);
    final reviews = _integer(product['reviews_count'] ?? product['review_count']);
    final reference = _text(product['reference'] ?? product['sku']);
    final status = productStatus(product);
    final updatedAt = _dateValue(product['updated_at']);

    return _CardShell(
      padding: const EdgeInsets.fromLTRB(14, 12, 14, 12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              _Pill(status, productStatusColor(status)),
              const Spacer(),
              if (updatedAt.isNotEmpty)
                Flexible(
                  child: Text(
                    'Mis à jour $updatedAt',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(color: Mk.muted, fontSize: 12, fontWeight: FontWeight.w500),
                  ),
                ),
            ],
          ),
          const SizedBox(height: 8),
          Text(
            _text(product['name'], fallback: 'Produit OVANIE'),
            style: const TextStyle(color: Mk.navy, fontSize: 21, height: 1.15, fontWeight: FontWeight.w800, letterSpacing: -.3),
          ),
          if (reference.isNotEmpty) ...[
            const SizedBox(height: 4),
            Text('Réf. $reference', style: const TextStyle(color: Mk.muted, fontSize: 13, fontWeight: FontWeight.w500)),
          ],
          const SizedBox(height: 6),
          Row(
            children: [
              const Icon(Icons.star_rounded, color: Color(0xFFFFA800), size: 20),
              const SizedBox(width: 5),
              if (rating > 0 && reviews > 0) ...[
                Text(
                  rating.toStringAsFixed(1),
                  style: const TextStyle(color: Mk.navy, fontSize: 13.5, fontWeight: FontWeight.w800),
                ),
                const SizedBox(width: 6),
                Text('($reviews avis)', style: const TextStyle(color: Mk.muted, fontSize: 13.5, fontWeight: FontWeight.w500)),
              ] else
                const Text('Aucun avis pour le moment', style: TextStyle(color: Mk.muted, fontSize: 13.5, fontWeight: FontWeight.w500)),
            ],
          ),
          const SizedBox(height: 10),
          Container(
            width: double.infinity,
            padding: const EdgeInsets.fromLTRB(12, 10, 10, 10),
            decoration: BoxDecoration(color: const Color(0xFFF3F6FB), borderRadius: BorderRadius.circular(12)),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Expanded(
                      child: Text(
                        'Votre prix de vente',
                        style: TextStyle(color: Mk.icon, fontSize: 13, fontWeight: FontWeight.w500),
                      ),
                    ),
                    _AvailabilityBadge(product: product, large: true),
                  ],
                ),
                Wrap(
                  crossAxisAlignment: WrapCrossAlignment.end,
                  spacing: 7,
                  children: [
                    Text(
                      productMoney(sellerPrice),
                      style: const TextStyle(color: Mk.navy, fontSize: 27, height: 1.15, fontWeight: FontWeight.w800, letterSpacing: -.4),
                    ),
                    Padding(
                      padding: const EdgeInsets.only(bottom: 3),
                      child: Text('/ $unit', style: const TextStyle(color: Mk.muted, fontSize: 15, fontWeight: FontWeight.w500)),
                    ),
                  ],
                ),
                if (hasPromo) ...[
                  const SizedBox(height: 4),
                  Wrap(
                    crossAxisAlignment: WrapCrossAlignment.center,
                    spacing: 10,
                    runSpacing: 4,
                    children: [
                      Text(
                        productMoney(sellerBasePrice),
                        style: const TextStyle(
                          color: Mk.muted,
                          fontSize: 14.5,
                          fontWeight: FontWeight.w500,
                          decoration: TextDecoration.lineThrough,
                        ),
                      ),
                      const _Pill('Prix promotionnel', Color(0xFFE85400)),
                    ],
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// Quatre chiffres clés, puis état, conditionnement et disponibilité.
class _ProductQuickFacts extends StatelessWidget {
  const _ProductQuickFacts({required this.product});

  final Map<String, dynamic> product;

  @override
  Widget build(BuildContext context) {
    final unit = _capitalize(_displayUnit(product));
    final minimum = _minimum(product);
    final weight = _number(product['weight_kg']);
    final stock = _integer(product['stock']);
    final packaging = _text(product['packaging']);
    final state = _text(product['product_state_label'], fallback: 'Neuf');

    final rows = <Widget>[
      _InlineInfo(label: 'État', value: Text(state, style: _inlineValue)),
      if (packaging.isNotEmpty) _InlineInfo(label: 'Conditionnement', value: Text(packaging, style: _inlineValue)),
      _InlineInfo(label: 'Disponibilité', value: _AvailabilityBadge(product: product)),
    ];

    return Column(
      children: [
        IntrinsicHeight(
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Expanded(
                child: _FactTile(
                  icon: Icons.inventory_2_outlined,
                  label: 'Stock disponible',
                  value: '$stock ${stock > 1 ? _pluralUnit(unit) : unit}',
                ),
              ),
              const SizedBox(width: 6),
              Expanded(child: _FactTile(icon: Icons.shopping_bag_outlined, label: 'Unité de vente', value: unit)),
              const SizedBox(width: 6),
              Expanded(
                child: _FactTile(icon: Icons.widgets_outlined, label: 'Quantité minimum', value: '$minimum $unit'),
              ),
              const SizedBox(width: 6),
              Expanded(
                child: _FactTile(
                  icon: Icons.scale_outlined,
                  label: 'Poids unitaire',
                  value: weight > 0 ? '${_compact(weight)} kg' : '—',
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 8),
        _CardShell(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 2),
          child: Column(
            children: [
              for (var i = 0; i < rows.length; i++) ...[
                if (i > 0) const Divider(height: 1, color: _line),
                rows[i],
              ],
            ],
          ),
        ),
      ],
    );
  }
}

const _inlineValue = TextStyle(color: Mk.muted, fontSize: 13.5, fontWeight: FontWeight.w500);

/// Onglets en segments : l'onglet actif est une pastille blanche.
class _VendorTabs extends StatelessWidget {
  const _VendorTabs({required this.selected, required this.onChanged});

  final int selected;
  final ValueChanged<int> onChanged;

  static const labels = <String>['Description', 'Caractéristiques', 'Logistique', 'Avis'];
  static const _flex = <int>[11, 15, 10, 6];

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(3),
      decoration: BoxDecoration(color: const Color(0xFFEDF0F5), borderRadius: BorderRadius.circular(14)),
      child: Row(
        children: List.generate(labels.length, (index) {
          final active = selected == index;
          return Expanded(
            flex: _flex[index],
            child: Semantics(
              button: true,
              selected: active,
              child: InkWell(
                onTap: () => onChanged(index),
                borderRadius: BorderRadius.circular(11),
                child: AnimatedContainer(
                  duration: const Duration(milliseconds: 160),
                  height: 40,
                  padding: const EdgeInsets.symmetric(horizontal: 6),
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
                    color: active ? Colors.white : Colors.transparent,
                    borderRadius: BorderRadius.circular(11),
                    boxShadow: active
                        ? const [BoxShadow(color: Color(0x140A1B4F), blurRadius: 6, offset: Offset(0, 1))]
                        : null,
                  ),
                  child: FittedBox(
                    fit: BoxFit.scaleDown,
                    child: Text(
                      labels[index],
                      maxLines: 1,
                      style: TextStyle(
                        color: active ? Mk.navy : Mk.muted,
                        fontSize: 13.5,
                        fontWeight: active ? FontWeight.w700 : FontWeight.w500,
                      ),
                    ),
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

class _VendorTabContent extends StatelessWidget {
  const _VendorTabContent({required this.product, required this.selected});

  final Map<String, dynamic> product;
  final int selected;

  @override
  Widget build(BuildContext context) {
    if (selected == 1) return _CharacteristicsCard(product: product);
    if (selected == 2) return _LogisticsCard(product: product);
    if (selected == 3) return _ReviewsCard(product: product);
    return _DescriptionCard(product: product);
  }
}

const _bodyText = TextStyle(color: Mk.muted, fontSize: 13.5, height: 1.5, fontWeight: FontWeight.w500);

class _DescriptionCard extends StatelessWidget {
  const _DescriptionCard({required this.product});

  final Map<String, dynamic> product;

  @override
  Widget build(BuildContext context) {
    final description = _text(
      product['description'],
      fallback: _text(
        product['short_description'],
        fallback: 'Aucune description enregistrée pour ce produit.',
      ),
    );
    final technicalDetails = _text(product['technical_details']);

    return _CardShell(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const _SectionHeading('Description du produit'),
          const SizedBox(height: 6),
          Text(description, style: _bodyText),
          if (technicalDetails.isNotEmpty) ...[
            const SizedBox(height: 12),
            const Divider(height: 1, color: _line),
            const SizedBox(height: 12),
            const Text(
              'Détails techniques',
              style: TextStyle(color: Mk.navy, fontSize: 14.5, fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 4),
            Text(technicalDetails, style: _bodyText),
          ],
        ],
      ),
    );
  }
}

class _CharacteristicsCard extends StatelessWidget {
  const _CharacteristicsCard({required this.product});

  final Map<String, dynamic> product;

  @override
  Widget build(BuildContext context) {
    final rows = <MapEntry<String, String>>[];

    void add(String label, dynamic value) {
      final text = _text(value);
      if (text.isNotEmpty) rows.add(MapEntry(label, text));
    }

    add('Marque', product['brand']);
    add('Origine', product['origin_country']);
    add('Utilisation', product['usage_area']);
    add('Classe / Grade', product['material_grade']);
    add('Couleur', product['color']);
    add('Norme', product['standard']);
    add('Conditionnement', product['packaging']);

    final specs = _stringMap(product['technical_specs']);
    final attrs = _stringMap(product['product_attributes']);
    // Déjà affichés plus haut ou dans l'onglet Logistique : inutile de les répéter ici.
    const shownElsewhere = <String>{
      'unité de vente', 'quantité minimum', 'disponibilité', 'état du produit', 'stock',
      'poids', 'poids unitaire', 'dimensions', 'volume',
    };
    for (final entry in [...specs.entries, ...attrs.entries]) {
      final key = entry.key.toLowerCase();
      if (shownElsewhere.contains(key)) continue;
      if (!rows.any((row) => row.key.toLowerCase() == key)) {
        rows.add(MapEntry(entry.key, _capitalize(entry.value)));
      }
    }

    return _CardShell(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const _SectionHeading('Caractéristiques techniques'),
          const SizedBox(height: 10),
          if (rows.isEmpty)
            const Text('Aucune caractéristique technique supplémentaire n’est enregistrée.', style: _bodyText)
          else
            _StripedRows(rows),
        ],
      ),
    );
  }
}

class _LogisticsCard extends StatelessWidget {
  const _LogisticsCard({required this.product});

  final Map<String, dynamic> product;

  @override
  Widget build(BuildContext context) {
    final weight = _number(product['weight_kg']);
    final volume = _number(product['volume_m3']);
    final dimensions = _dimensionsLabel(product);
    final supplyDelay = _text(product['supply_delay']);
    final returnPolicy = _text(product['return_policy']);
    final unloading = _text(product['unloading_instructions']);
    final deliveryMode = _deliveryModeLabel(product['delivery_mode']);

    return _CardShell(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const _SectionHeading('Informations logistiques'),
          const SizedBox(height: 6),
          Center(
            child: Image.asset(
              'assets/images/mockups/logistics_truck.jpg',
              height: 150,
              excludeFromSemantics: true,
            ),
          ),
          const SizedBox(height: 6),
          _StripedRows([
            MapEntry('Poids unitaire', weight > 0 ? '${_compact(weight)} kg' : '—'),
            MapEntry('Dimensions', dimensions.isEmpty ? '—' : dimensions),
            MapEntry('Volume', volume > 0 ? '${_compactVolume(volume)} m³' : '—'),
            MapEntry('Produit fragile', _bool(product['fragile']) ? 'Oui' : 'Non'),
            MapEntry('Déchargement requis', _bool(product['requires_unloading']) ? 'Oui' : 'Non'),
            if (deliveryMode.isNotEmpty) MapEntry('Mode logistique', deliveryMode),
            if (supplyDelay.isNotEmpty) MapEntry('Délai indicatif', supplyDelay),
            if (unloading.isNotEmpty) MapEntry('Déchargement', unloading),
            if (returnPolicy.isNotEmpty) MapEntry('Retour', returnPolicy),
          ]),
        ],
      ),
    );
  }
}

class _ReviewsCard extends StatelessWidget {
  const _ReviewsCard({required this.product});

  final Map<String, dynamic> product;

  @override
  Widget build(BuildContext context) {
    final rating = _number(product['rating'] ?? product['average_rating']);
    final reviewsCount = _integer(product['reviews_count'] ?? product['review_count']);
    final hasReviews = reviewsCount > 0 && rating > 0;

    return _CardShell(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const _SectionHeading('Avis clients'),
          const SizedBox(height: 18),
          Center(
            child: Column(
              children: [
                Text(
                  hasReviews ? rating.toStringAsFixed(1) : '—',
                  style: const TextStyle(color: Mk.navy, fontSize: 56, height: 1, fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 10),
                Semantics(
                  label: hasReviews ? 'Note ${rating.toStringAsFixed(1)} sur 5' : 'Pas encore de note',
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      for (var star = 1; star <= 5; star++)
                        Padding(
                          padding: const EdgeInsets.symmetric(horizontal: 2),
                          child: Icon(
                            !hasReviews || rating < star - .75
                                ? Icons.star_rounded
                                : (rating < star - .25 ? Icons.star_half_rounded : Icons.star_rounded),
                            color: !hasReviews || rating < star - .75 ? const Color(0xFFD5DAE3) : const Color(0xFFFFA800),
                            size: 38,
                          ),
                        ),
                    ],
                  ),
                ),
                const SizedBox(height: 10),
                Text(
                  !hasReviews
                      ? 'Aucun avis pour le moment'
                      : '$reviewsCount avis client${reviewsCount > 1 ? 's' : ''}',
                  style: const TextStyle(color: Mk.muted, fontSize: 17, fontWeight: FontWeight.w600),
                ),
              ],
            ),
          ),
          const SizedBox(height: 22),
        ],
      ),
    );
  }
}

class _AvailabilityBadge extends StatelessWidget {
  const _AvailabilityBadge({required this.product, this.large = false});

  final Map<String, dynamic> product;
  final bool large;

  @override
  Widget build(BuildContext context) {
    final status = _text(product['availability_status']).toLowerCase();
    final stock = _integer(product['stock']);
    final inStock = status.isEmpty ? stock > 0 : status == 'in_stock';
    final color = inStock
        ? _green
        : status == 'out_of_stock' || stock <= 0
            ? const Color(0xFFD92D20)
            : _blue;

    return _Pill(_availabilityLabel(product), color, large: large);
  }
}

class _FactTile extends StatelessWidget {
  const _FactTile({required this.icon, required this.label, required this.value});

  final IconData icon;
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      label: '$label : $value',
      excludeSemantics: true,
      child: Container(
        padding: const EdgeInsets.fromLTRB(4, 10, 4, 10),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: _line, width: 1.1),
        ),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(icon, color: Mk.icon, size: 26),
            const SizedBox(height: 4),
            FittedBox(
              fit: BoxFit.scaleDown,
              child: Text(value, maxLines: 1, style: const TextStyle(color: Mk.navy, fontSize: 14, fontWeight: FontWeight.w800)),
            ),
            const SizedBox(height: 1),
            FittedBox(
              fit: BoxFit.scaleDown,
              child: Text(label, maxLines: 1, style: const TextStyle(color: Mk.muted, fontSize: 11, fontWeight: FontWeight.w500)),
            ),
          ],
        ),
      ),
    );
  }
}

class _InlineInfo extends StatelessWidget {
  const _InlineInfo({required this.label, required this.value});

  final String label;
  final Widget value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 9),
      child: Row(
        children: [
          Text(label, style: const TextStyle(color: Mk.navy, fontSize: 13.5, fontWeight: FontWeight.w500)),
          const SizedBox(width: 12),
          Expanded(child: Align(alignment: Alignment.centerRight, child: value)),
        ],
      ),
    );
  }
}

/// Tableau à lignes alternées (caractéristiques, logistique).
class _StripedRows extends StatelessWidget {
  const _StripedRows(this.rows);

  final List<MapEntry<String, String>> rows;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(borderRadius: BorderRadius.circular(8), border: Border.all(color: _line)),
      clipBehavior: Clip.antiAlias,
      child: Column(
        children: [
          for (var i = 0; i < rows.length; i++)
            Container(
              color: i.isEven ? Colors.white : const Color(0xFFF4F6FA),
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 7),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    flex: 9,
                    child: Text(
                      rows[i].key,
                      style: const TextStyle(color: Mk.muted, fontSize: 13, fontWeight: FontWeight.w500),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    flex: 11,
                    child: Text(
                      rows[i].value,
                      style: const TextStyle(color: Mk.navy, fontSize: 13, fontWeight: FontWeight.w600),
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

class _SectionHeading extends StatelessWidget {
  const _SectionHeading(this.title);

  final String title;

  @override
  Widget build(BuildContext context) {
    return Text(
      title,
      style: const TextStyle(color: Mk.navy, fontSize: 17, fontWeight: FontWeight.w800, letterSpacing: -.2),
    );
  }
}

class _CardShell extends StatelessWidget {
  const _CardShell({
    required this.child,
    this.padding = const EdgeInsets.fromLTRB(14, 14, 14, 14),
  });

  final Widget child;
  final EdgeInsetsGeometry padding;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: padding,
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: _line, width: 1.1),
      ),
      clipBehavior: Clip.antiAlias,
      child: child,
    );
  }
}

class _DetailSkeleton extends StatelessWidget {
  const _DetailSkeleton();

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(12),
      children: [
        Container(height: 250, decoration: _skeletonDecoration()),
        const SizedBox(height: 10),
        Container(height: 190, decoration: _skeletonDecoration()),
        const SizedBox(height: 10),
        Container(height: 90, decoration: _skeletonDecoration()),
        const SizedBox(height: 10),
        Container(height: 190, decoration: _skeletonDecoration()),
      ],
    );
  }

  BoxDecoration _skeletonDecoration() => BoxDecoration(
        color: const Color(0xFFEBEEF3),
        borderRadius: BorderRadius.circular(14),
      );
}

/// « Mes produits > Catégorie » : la catégorie principale quand elle existe.
String _breadcrumb(Map<String, dynamic> product) {
  final category = product['category'];
  var name = _categoryName(product);
  if (category is Map && category['parent'] is Map) {
    final parent = _text((category['parent'] as Map)['name']);
    if (parent.isNotEmpty) name = parent;
  }
  return name.isEmpty ? 'Mes produits' : 'Mes produits  >  $name';
}

/// Majuscule initiale, sauf pour les symboles d'unité (« m² », « kg ») qui restent en minuscules.
String _capitalize(String value) {
  if (value.isEmpty || RegExp(r'^(m²|m³|m2|m3|kg|ml|g|l)').hasMatch(value)) return value;
  return value[0].toUpperCase() + value.substring(1);
}

/// Volume en m³ : jusqu'à 4 décimales, sans zéros inutiles.
String _compactVolume(double value) =>
    value.toStringAsFixed(4).replaceFirst(RegExp(r'0+$'), '').replaceFirst(RegExp(r'\.$'), '').replaceAll('.', ',');

List<String> _productImages(Map<String, dynamic> product) {
  final values = <String>[];

  void add(dynamic value) {
    final text = _text(value);
    if (text.isNotEmpty && !values.contains(text)) values.add(text);
  }

  add(product['main_image_url']);
  add(product['image_url']);
  // La vignette « carte » est la même photo en plus petit : seulement en secours.
  if (values.isEmpty) add(product['card_image_url']);

  final images = product['images'];
  if (images is List) {
    for (final item in images) {
      if (item is Map) {
        add(item['url'] ?? item['card_url'] ?? item['thumb_url']);
      } else {
        add(item);
      }
    }
  }

  final gallery = product['gallery'];
  if (gallery is List) {
    for (final item in gallery) {
      add(item);
    }
  }

  return values;
}

Map<String, String> _stringMap(dynamic raw) {
  final result = <String, String>{};
  if (raw is Map) {
    for (final entry in raw.entries) {
      final key = _text(entry.key);
      final value = _text(entry.value);
      if (key.isNotEmpty && value.isNotEmpty) result[key] = value;
    }
  }
  return result;
}

String _categoryName(Map<String, dynamic> product) {
  final category = product['category'];
  if (category is Map) return _text(category['name']);
  return _text(product['category_name'] ?? product['category']);
}

String _displayUnit(Map<String, dynamic> product) {
  return _text(
    product['display_unit'] ?? product['unit_label'] ?? product['unit'],
    fallback: 'unité',
  );
}

int _minimum(Map<String, dynamic> product) {
  final value = _integer(product['min_order_quantity']);
  return value <= 0 ? 1 : value;
}

String _availabilityLabel(Map<String, dynamic> product) {
  return _text(
    product['availability_label'],
    fallback: _integer(product['stock']) > 0 ? 'En stock' : 'Indisponible',
  );
}

String _dimensionsLabel(Map<String, dynamic> product) {
  // Calculé d'abord à partir des mesures : le libellé du serveur garde des « ,00 » inutiles.
  final length = _number(product['length_cm']);
  final width = _number(product['width_cm']);
  final height = _number(product['height_cm']);
  final values = <String>[];
  if (length > 0) values.add(_compact(length));
  if (width > 0) values.add(_compact(width));
  if (height > 0) values.add(_compact(height));
  if (values.isNotEmpty) return '${values.join(' × ')} cm';
  return _text(product['dimensions_label']);
}

String _deliveryModeLabel(dynamic value) {
  final raw = _text(value);
  switch (raw.toLowerCase()) {
    case 'ovanie_logistics':
    case 'ovanie':
      return 'OVANIE Logistics';
    case 'seller':
    case 'vendor':
      return 'Logistique vendeur';
    case 'pickup':
      return 'Retrait';
    default:
      return raw;
  }
}

String _pluralUnit(String unit) {
  final value = unit.trim();
  if (value.isEmpty || value == 'unité') return 'unités';
  // « Sac (50 kg) » : seul le nom de l'unité prend le pluriel, pas la précision entre parenthèses.
  final detail = value.indexOf(' (');
  if (detail > 0) return '${_pluralUnit(value.substring(0, detail))}${value.substring(detail)}';
  final lower = value.toLowerCase();
  if (lower.endsWith('s') || lower.endsWith('x')) return value;
  if (const <String>{'m²', 'm³', 'm2', 'm3', 'kg', 'ml', 'g', 'l'}.contains(lower)) return value;
  if (lower.endsWith('eau')) return '${value}x';
  return '${value}s';
}

bool _bool(dynamic value) {
  if (value is bool) return value;
  final normalized = '${value ?? ''}'.trim().toLowerCase();
  return normalized == '1' ||
      normalized == 'true' ||
      normalized == 'yes' ||
      normalized == 'oui';
}

double _number(dynamic value) {
  if (value is num) return value.toDouble();
  return double.tryParse('${value ?? ''}') ?? 0.0;
}

int _integer(dynamic value) {
  if (value is num) return value.toInt();
  return int.tryParse('${value ?? ''}') ?? 0;
}

String _text(dynamic value, {String fallback = ''}) {
  final text = '${value ?? ''}'.trim();
  if (text.isEmpty || text == 'null') return fallback;
  return text;
}

String _dateValue(dynamic value) {
  final raw = _text(value);
  if (raw.isEmpty) return '';
  final date = DateTime.tryParse(raw)?.toLocal();
  if (date == null) return raw;
  final day = date.day.toString().padLeft(2, '0');
  final month = date.month.toString().padLeft(2, '0');
  return '$day/$month/${date.year}';
}

String _compact(double value) {
  return value == value.roundToDouble()
      ? value.round().toString()
      : value.toStringAsFixed(2).replaceFirst(RegExp(r'0+$'), '').replaceFirst(RegExp(r'\.$'), '').replaceAll('.', ',');
}
