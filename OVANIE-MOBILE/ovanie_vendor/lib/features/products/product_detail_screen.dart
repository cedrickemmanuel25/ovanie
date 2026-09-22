import 'package:flutter/material.dart';

import '../../core/network/api_client.dart';
import '../../core/ui/vendor_design.dart';
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

class _ProductDetailScreenState extends State<ProductDetailScreen> {
  Map<String, dynamic>? _product;
  bool _refreshing = false;
  String? _error;
  int _imageIndex = 0;
  int _tab = 0;

  @override
  void initState() {
    super.initState();
    if (widget.initialProduct != null) {
      _product = Map<String, dynamic>.from(widget.initialProduct!);
    }
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
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
  }

  Future<void> _showMore() async {
    await showModalBottomSheet<void>(
      context: context,
      backgroundColor: Colors.transparent,
      builder: (sheetContext) => SafeArea(
        top: false,
        child: Container(
          padding: const EdgeInsets.fromLTRB(18, 10, 18, 18),
          decoration: const BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 44,
                height: 4,
                decoration: BoxDecoration(
                  color: const Color(0xFFD5DBE5),
                  borderRadius: BorderRadius.circular(99),
                ),
              ),
              const SizedBox(height: 12),
              ListTile(
                leading: const Icon(Icons.edit_outlined, color: vendorBlue),
                title: const Text(
                  'Modifier le produit',
                  style: TextStyle(fontWeight: FontWeight.w800),
                ),
                onTap: () {
                  Navigator.pop(sheetContext);
                  _edit();
                },
              ),
              ListTile(
                leading: const Icon(
                  Icons.photo_library_outlined,
                  color: vendorBlue,
                ),
                title: const Text(
                  'Gérer les images',
                  style: TextStyle(fontWeight: FontWeight.w800),
                ),
                onTap: () {
                  Navigator.pop(sheetContext);
                  _images();
                },
              ),
              ListTile(
                leading: const Icon(
                  Icons.bolt_rounded,
                  color: productOrange,
                ),
                title: const Text(
                  'Booster ce produit',
                  style: TextStyle(fontWeight: FontWeight.w800),
                ),
                onTap: () {
                  Navigator.pop(sheetContext);
                  _boost();
                },
              ),
            ],
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: vendorPage,
      body: Column(
        children: [
          _DetailHeader(
            onBack: () => Navigator.maybePop(context),
            onNotifications: _openNotifications,
            onMore: _showMore,
          ),
          if (_refreshing && _product != null)
            const LinearProgressIndicator(
              minHeight: 2,
              color: productOrange,
              backgroundColor: Color(0xFFFFEEE5),
            ),
          Expanded(
            child: _product == null
                ? (_error == null ? const _DetailSkeleton() : _errorView())
                : RefreshIndicator(
                    color: productOrange,
                    onRefresh: () => _load(force: true),
                    child: _content(),
                  ),
          ),
        ],
      ),
      bottomNavigationBar: const VendorBottomBar(selected: VendorTab.products),
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
          FilledButton(
            onPressed: () => _load(force: true),
            child: const Text('Réessayer'),
          ),
        ],
      ),
    );
  }

  Widget _content() {
    final product = _product ?? <String, dynamic>{};
    final images = _productImages(product);
    final safeIndex = images.isEmpty
        ? 0
        : _imageIndex.clamp(0, images.length - 1).toInt();

    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 26),
      children: [
        _Breadcrumb(product: product),
        const SizedBox(height: 12),
        _ManagementActions(
          product: product,
          onEdit: _edit,
          onToggle: _toggleActive,
        ),
        const SizedBox(height: 14),
        _ProductMediaCard(
          images: images,
          selectedIndex: safeIndex,
          onSelect: (index) => setState(() => _imageIndex = index),
          onManage: _images,
        ),
        const SizedBox(height: 14),
        _SellerProductSummary(product: product),
        const SizedBox(height: 14),
        _ProductQuickFacts(product: product),
        const SizedBox(height: 18),
        _VendorTabs(
          selected: _tab,
          onChanged: (value) => setState(() => _tab = value),
        ),
        const SizedBox(height: 12),
        _VendorTabContent(product: product, selected: _tab),
        if (_error != null) ...[
          const SizedBox(height: 14),
          VendorErrorBox(_error),
        ],
      ],
    );
  }
}

class _DetailHeader extends StatelessWidget {
  const _DetailHeader({
    required this.onBack,
    required this.onNotifications,
    required this.onMore,
  });

  final VoidCallback onBack;
  final VoidCallback onNotifications;
  final VoidCallback onMore;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          colors: [Color(0xFF031D47), Color(0xFF084B94)],
          begin: Alignment.centerLeft,
          end: Alignment.centerRight,
        ),
      ),
      child: Stack(
        children: [
          Positioned.fill(
            child: Opacity(
              opacity: .17,
              child: Image.asset(
                'assets/images/vendor_construction_blue.png',
                fit: BoxFit.cover,
                alignment: Alignment.centerRight,
              ),
            ),
          ),
          SafeArea(
            bottom: false,
            child: SizedBox(
              height: 62,
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 14),
                child: Row(
                  children: [
                    IconButton(
                      onPressed: onBack,
                      icon: const Icon(
                        Icons.arrow_back_ios_new_rounded,
                        color: Colors.white,
                        size: 22,
                      ),
                    ),
                    const SizedBox(width: 4),
                    const Expanded(
                      child: Align(
                        alignment: Alignment.centerLeft,
                        child: ProductBrandLockup(compact: true),
                      ),
                    ),
                    IconButton(
                      onPressed: onNotifications,
                      icon: const Icon(
                        Icons.notifications_none_rounded,
                        color: Colors.white,
                        size: 27,
                      ),
                    ),
                    IconButton(
                      onPressed: onMore,
                      icon: const Icon(
                        Icons.more_vert_rounded,
                        color: Colors.white,
                        size: 26,
                      ),
                    ),
                  ],
                ),
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

  final Map<String, dynamic> product;

  @override
  Widget build(BuildContext context) {
    final category = _categoryName(product);
    return Text.rich(
      TextSpan(
        style: const TextStyle(color: vendorMuted, fontSize: 11.5),
        children: [
          const TextSpan(text: 'Mes produits  ›  '),
          if (category.isNotEmpty) ...[
            TextSpan(text: '$category  ›  '),
          ],
          TextSpan(
            text: _text(product['name'], fallback: 'Produit'),
            style: const TextStyle(
              color: vendorText,
              fontWeight: FontWeight.w800,
            ),
          ),
        ],
      ),
      maxLines: 1,
      overflow: TextOverflow.ellipsis,
    );
  }
}

class _ManagementActions extends StatelessWidget {
  const _ManagementActions({
    required this.product,
    required this.onEdit,
    required this.onToggle,
  });

  final Map<String, dynamic> product;
  final VoidCallback onEdit;
  final VoidCallback onToggle;

  @override
  Widget build(BuildContext context) {
    final status = productStatus(product);
    final active = _bool(product['is_active']);
    final archived = '${product['status']}'.toLowerCase() == 'archived';
    final updatedAt = _dateValue(product['updated_at']);

    return _CardShell(
      padding: const EdgeInsets.all(12),
      child: Column(
        children: [
          Row(
            children: [
              ProductStatusBadge(status),
              const Spacer(),
              if (updatedAt.isNotEmpty)
                Flexible(
                  child: Text(
                    'Mis à jour $updatedAt',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(color: vendorMuted, fontSize: 9.8),
                  ),
                ),
            ],
          ),
          const SizedBox(height: 11),
          Row(
            children: [
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: onEdit,
                  icon: const Icon(Icons.edit_outlined, size: 17),
                  label: const Text('Modifier'),
                  style: OutlinedButton.styleFrom(
                    foregroundColor: vendorText,
                    side: const BorderSide(color: productBorder),
                    padding: const EdgeInsets.symmetric(vertical: 11),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(10),
                    ),
                  ),
                ),
              ),
              const SizedBox(width: 9),
              Expanded(
                child: FilledButton.icon(
                  onPressed: archived ? null : onToggle,
                  icon: Icon(
                    active ? Icons.power_settings_new_rounded : Icons.play_arrow_rounded,
                    size: 17,
                  ),
                  label: Text(active ? 'Désactiver' : 'Activer'),
                  style: FilledButton.styleFrom(
                    backgroundColor: active ? productOrange : const Color(0xFF159447),
                    disabledBackgroundColor: const Color(0xFFE6EAF0),
                    padding: const EdgeInsets.symmetric(vertical: 11),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(10),
                    ),
                  ),
                ),
              ),
            ],
          ),
        ],
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

    return _CardShell(
      padding: const EdgeInsets.all(12),
      child: Column(
        children: [
          SizedBox(
            height: 300,
            width: double.infinity,
            child: Stack(
              children: [
                Positioned.fill(
                  child: ProductNetworkImage(
                    url: mainUrl,
                    fit: BoxFit.contain,
                    borderRadius: 12,
                  ),
                ),
                Positioned(
                  top: 10,
                  right: 10,
                  child: Material(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(10),
                    elevation: 1,
                    child: InkWell(
                      onTap: onManage,
                      borderRadius: BorderRadius.circular(10),
                      child: const Padding(
                        padding: EdgeInsets.symmetric(horizontal: 10, vertical: 8),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Icon(
                              Icons.photo_library_outlined,
                              color: vendorText,
                              size: 17,
                            ),
                            SizedBox(width: 6),
                            Text(
                              'Médias',
                              style: TextStyle(
                                color: vendorText,
                                fontSize: 11,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
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
                      padding: const EdgeInsets.symmetric(
                        horizontal: 9,
                        vertical: 5,
                      ),
                      decoration: BoxDecoration(
                        color: const Color(0xB8021A3E),
                        borderRadius: BorderRadius.circular(18),
                      ),
                      child: Text(
                        '${selectedIndex + 1}/${images.length}',
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 10.5,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ),
                  ),
              ],
            ),
          ),
          if (images.length > 1) ...[
            const SizedBox(height: 10),
            SizedBox(
              height: 58,
              child: ListView.separated(
                scrollDirection: Axis.horizontal,
                itemCount: images.length,
                separatorBuilder: (_, __) => const SizedBox(width: 8),
                itemBuilder: (_, index) {
                  final selected = selectedIndex == index;
                  return InkWell(
                    onTap: () => onSelect(index),
                    borderRadius: BorderRadius.circular(9),
                    child: Container(
                      width: 58,
                      padding: const EdgeInsets.all(2),
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(9),
                        border: Border.all(
                          color: selected ? productOrange : productBorder,
                          width: selected ? 2 : 1,
                        ),
                      ),
                      child: ProductNetworkImage(
                        url: images[index],
                        fit: BoxFit.contain,
                        borderRadius: 6,
                      ),
                    ),
                  );
                },
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _SellerProductSummary extends StatelessWidget {
  const _SellerProductSummary({required this.product});

  final Map<String, dynamic> product;

  @override
  Widget build(BuildContext context) {
    final category = _categoryName(product);
    final unit = _displayUnit(product);
    final sellerPrice = _number(
      product['seller_effective_price'] ??
          product['seller_promo_price'] ??
          product['seller_price'] ??
          product['price'],
    );
    final sellerBasePrice = _number(product['seller_price'] ?? product['price']);
    final hasPromo = sellerBasePrice > 0 &&
        sellerPrice > 0 &&
        sellerPrice < sellerBasePrice;
    final rating = _number(product['rating'] ?? product['average_rating']);
    final reviews = _integer(product['reviews_count'] ?? product['review_count']);
    final reference = _text(product['reference'] ?? product['sku']);

    return _CardShell(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              if (category.isNotEmpty)
                Expanded(
                  child: Text(
                    category.toUpperCase(),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: vendorBlue,
                      fontSize: 10.5,
                      fontWeight: FontWeight.w900,
                      letterSpacing: .3,
                    ),
                  ),
                )
              else
                const Spacer(),
              _AvailabilityBadge(product: product),
            ],
          ),
          const SizedBox(height: 9),
          Text(
            _text(product['name'], fallback: 'Produit OVANIE'),
            style: const TextStyle(
              color: vendorText,
              fontSize: 22,
              height: 1.12,
              fontWeight: FontWeight.w900,
            ),
          ),
          if (reference.isNotEmpty) ...[
            const SizedBox(height: 6),
            Text(
              'Réf. $reference',
              style: const TextStyle(color: vendorMuted, fontSize: 10.5),
            ),
          ],
          const SizedBox(height: 10),
          Row(
            children: [
              const Icon(Icons.star_rounded, color: Color(0xFFFFB000), size: 19),
              const SizedBox(width: 4),
              Text(
                rating > 0 ? rating.toStringAsFixed(1) : '—',
                style: const TextStyle(
                  color: vendorText,
                  fontSize: 12,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(width: 5),
              Text(
                '($reviews avis)',
                style: const TextStyle(color: vendorMuted, fontSize: 11),
              ),
            ],
          ),
          const SizedBox(height: 18),
          const Text(
            'Votre prix de vente',
            style: TextStyle(
              color: vendorMuted,
              fontSize: 11,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 4),
          Wrap(
            crossAxisAlignment: WrapCrossAlignment.end,
            spacing: 7,
            runSpacing: 3,
            children: [
              Text(
                productMoney(sellerPrice),
                style: const TextStyle(
                  color: productOrange,
                  fontSize: 29,
                  height: 1,
                  fontWeight: FontWeight.w900,
                ),
              ),
              Text(
                '/ $unit',
                style: const TextStyle(
                  color: vendorMuted,
                  fontSize: 12,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
          ),
          if (hasPromo) ...[
            const SizedBox(height: 6),
            Row(
              children: [
                Text(
                  productMoney(sellerBasePrice),
                  style: const TextStyle(
                    color: vendorMuted,
                    fontSize: 12,
                    decoration: TextDecoration.lineThrough,
                  ),
                ),
                const SizedBox(width: 8),
                const Text(
                  'Prix promotionnel',
                  style: TextStyle(
                    color: Color(0xFF159447),
                    fontSize: 10.5,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ],
            ),
          ],
        ],
      ),
    );
  }
}

class _ProductQuickFacts extends StatelessWidget {
  const _ProductQuickFacts({required this.product});

  final Map<String, dynamic> product;

  @override
  Widget build(BuildContext context) {
    final unit = _displayUnit(product);
    final minimum = _minimum(product);
    final weight = _number(product['weight_kg']);
    final stock = _integer(product['stock']);
    final packaging = _text(product['packaging']);
    final state = _text(product['product_state_label'], fallback: 'Neuf');

    return _CardShell(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const _SectionHeading(
            icon: Icons.inventory_2_outlined,
            title: 'Informations produit',
          ),
          const SizedBox(height: 12),
          LayoutBuilder(
            builder: (context, constraints) {
              final width = (constraints.maxWidth - 10) / 2;
              return Wrap(
                spacing: 10,
                runSpacing: 10,
                children: [
                  SizedBox(
                    width: width,
                    child: _FactTile(
                      icon: Icons.inventory_outlined,
                      label: 'Stock disponible',
                      value: '$stock ${_pluralUnit(unit)}',
                    ),
                  ),
                  SizedBox(
                    width: width,
                    child: _FactTile(
                      icon: Icons.sell_outlined,
                      label: 'Unité de vente',
                      value: unit,
                    ),
                  ),
                  SizedBox(
                    width: width,
                    child: _FactTile(
                      icon: Icons.shopping_bag_outlined,
                      label: 'Quantité minimum',
                      value: '$minimum $unit',
                    ),
                  ),
                  SizedBox(
                    width: width,
                    child: _FactTile(
                      icon: Icons.scale_outlined,
                      label: 'Poids unitaire',
                      value: weight > 0 ? '${_compact(weight)} kg' : '—',
                    ),
                  ),
                ],
              );
            },
          ),
          const SizedBox(height: 12),
          const Divider(height: 1, color: productBorder),
          const SizedBox(height: 10),
          _InlineInfo(label: 'État', value: state),
          if (packaging.isNotEmpty)
            _InlineInfo(label: 'Conditionnement', value: packaging),
          _InlineInfo(
            label: 'Disponibilité',
            value: _availabilityLabel(product),
          ),
        ],
      ),
    );
  }
}

class _VendorTabs extends StatelessWidget {
  const _VendorTabs({required this.selected, required this.onChanged});

  final int selected;
  final ValueChanged<int> onChanged;

  static const labels = <String>[
    'Description',
    'Caractéristiques',
    'Logistique',
    'Avis',
  ];

  static const icons = <IconData>[
    Icons.description_outlined,
    Icons.tune_rounded,
    Icons.local_shipping_outlined,
    Icons.star_outline_rounded,
  ];

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: Row(
        children: List.generate(labels.length, (index) {
          final active = selected == index;
          return Padding(
            padding: EdgeInsets.only(right: index == labels.length - 1 ? 0 : 8),
            child: InkWell(
              onTap: () => onChanged(index),
              borderRadius: BorderRadius.circular(10),
              child: AnimatedContainer(
                duration: const Duration(milliseconds: 160),
                padding: const EdgeInsets.symmetric(horizontal: 13, vertical: 10),
                decoration: BoxDecoration(
                  color: active ? const Color(0xFFFFF0E8) : Colors.white,
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(
                    color: active ? productOrange : productBorder,
                  ),
                ),
                child: Row(
                  children: [
                    Icon(
                      icons[index],
                      size: 17,
                      color: active ? productOrange : vendorText,
                    ),
                    const SizedBox(width: 6),
                    Text(
                      labels[index],
                      style: TextStyle(
                        color: active ? productOrange : vendorText,
                        fontSize: 11,
                        fontWeight: active ? FontWeight.w900 : FontWeight.w700,
                      ),
                    ),
                  ],
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
          const _SectionHeading(
            icon: Icons.description_outlined,
            title: 'Description du produit',
          ),
          const SizedBox(height: 12),
          Text(
            description,
            style: const TextStyle(
              color: Color(0xFF435675),
              fontSize: 12.5,
              height: 1.55,
            ),
          ),
          if (technicalDetails.isNotEmpty) ...[
            const SizedBox(height: 16),
            const Text(
              'Détails techniques',
              style: TextStyle(
                color: vendorText,
                fontSize: 12.5,
                fontWeight: FontWeight.w900,
              ),
            ),
            const SizedBox(height: 7),
            Text(
              technicalDetails,
              style: const TextStyle(
                color: Color(0xFF435675),
                fontSize: 11.5,
                height: 1.5,
              ),
            ),
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

    void add(String label, dynamic value, {String suffix = ''}) {
      final text = _text(value);
      if (text.isEmpty) return;
      rows.add(MapEntry(label, suffix.isEmpty ? text : '$text $suffix'));
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
    for (final entry in [...specs.entries, ...attrs.entries]) {
      if (!rows.any((row) => row.key.toLowerCase() == entry.key.toLowerCase())) {
        rows.add(entry);
      }
    }

    return _CardShell(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const _SectionHeading(
            icon: Icons.tune_rounded,
            title: 'Caractéristiques techniques',
          ),
          const SizedBox(height: 10),
          if (rows.isEmpty)
            const Text(
              'Aucune caractéristique technique supplémentaire n’est enregistrée.',
              style: TextStyle(color: vendorMuted, fontSize: 12),
            )
          else
            ...rows.map(
              (entry) => _DataRow(label: entry.key, value: entry.value),
            ),
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
          const _SectionHeading(
            icon: Icons.local_shipping_outlined,
            title: 'Informations logistiques',
          ),
          const SizedBox(height: 10),
          _DataRow(
            label: 'Poids unitaire',
            value: weight > 0 ? '${_compact(weight)} kg' : '—',
          ),
          _DataRow(
            label: 'Dimensions',
            value: dimensions.isEmpty ? '—' : dimensions,
          ),
          _DataRow(
            label: 'Volume',
            value: volume > 0 ? '${_compact(volume)} m³' : '—',
          ),
          _DataRow(
            label: 'Produit fragile',
            value: _bool(product['fragile']) ? 'Oui' : 'Non',
          ),
          _DataRow(
            label: 'Déchargement requis',
            value: _bool(product['requires_unloading']) ? 'Oui' : 'Non',
          ),
          if (deliveryMode.isNotEmpty)
            _DataRow(label: 'Mode logistique', value: deliveryMode),
          if (supplyDelay.isNotEmpty)
            _DataRow(label: 'Délai indicatif', value: supplyDelay),
          if (unloading.isNotEmpty)
            _DataRow(label: 'Déchargement', value: unloading),
          if (returnPolicy.isNotEmpty)
            _DataRow(label: 'Retour', value: returnPolicy),
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

    return _CardShell(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const _SectionHeading(
            icon: Icons.star_outline_rounded,
            title: 'Avis clients',
          ),
          const SizedBox(height: 14),
          Row(
            children: [
              Container(
                width: 54,
                height: 54,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: const Color(0xFFFFF6E1),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: const Icon(
                  Icons.star_rounded,
                  color: Color(0xFFFFB000),
                  size: 30,
                ),
              ),
              const SizedBox(width: 12),
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    rating > 0 ? rating.toStringAsFixed(1) : '—',
                    style: const TextStyle(
                      color: vendorText,
                      fontSize: 26,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  Text(
                    '$reviewsCount avis client${reviewsCount > 1 ? 's' : ''}',
                    style: const TextStyle(color: vendorMuted, fontSize: 11.5),
                  ),
                ],
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _AvailabilityBadge extends StatelessWidget {
  const _AvailabilityBadge({required this.product});

  final Map<String, dynamic> product;

  @override
  Widget build(BuildContext context) {
    final status = _text(product['availability_status']).toLowerCase();
    final stock = _integer(product['stock']);
    final inStock = status.isEmpty ? stock > 0 : status == 'in_stock';
    final color = inStock
        ? const Color(0xFF159447)
        : status == 'out_of_stock'
            ? const Color(0xFFD92D20)
            : vendorBlue;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
      decoration: BoxDecoration(
        color: color.withValues(alpha: .10),
        borderRadius: BorderRadius.circular(7),
      ),
      child: Text(
        _availabilityLabel(product),
        style: TextStyle(
          color: color,
          fontSize: 9.7,
          fontWeight: FontWeight.w900,
        ),
      ),
    );
  }
}

class _FactTile extends StatelessWidget {
  const _FactTile({
    required this.icon,
    required this.label,
    required this.value,
  });

  final IconData icon;
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Container(
      constraints: const BoxConstraints(minHeight: 70),
      padding: const EdgeInsets.all(10),
      decoration: BoxDecoration(
        color: productSoft,
        borderRadius: BorderRadius.circular(10),
      ),
      child: Row(
        children: [
          Container(
            width: 34,
            height: 34,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(9),
            ),
            child: Icon(icon, color: vendorBlue, size: 18),
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Text(
                  label,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(color: vendorMuted, fontSize: 9.5),
                ),
                const SizedBox(height: 3),
                Text(
                  value,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: vendorText,
                    fontSize: 11.2,
                    fontWeight: FontWeight.w900,
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

class _InlineInfo extends StatelessWidget {
  const _InlineInfo({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 116,
            child: Text(
              label,
              style: const TextStyle(color: vendorMuted, fontSize: 10.8),
            ),
          ),
          Expanded(
            child: Text(
              value,
              style: const TextStyle(
                color: vendorText,
                fontSize: 10.8,
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _DataRow extends StatelessWidget {
  const _DataRow({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 9),
      decoration: const BoxDecoration(
        border: Border(bottom: BorderSide(color: Color(0xFFEEF1F5))),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 126,
            child: Text(
              label,
              style: const TextStyle(color: vendorMuted, fontSize: 11),
            ),
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              value,
              style: const TextStyle(
                color: vendorText,
                fontSize: 11,
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _SectionHeading extends StatelessWidget {
  const _SectionHeading({required this.icon, required this.title});

  final IconData icon;
  final String title;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Container(
          width: 32,
          height: 32,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: const Color(0xFFF0F5FD),
            borderRadius: BorderRadius.circular(9),
          ),
          child: Icon(icon, color: vendorBlue, size: 18),
        ),
        const SizedBox(width: 9),
        Expanded(
          child: Text(
            title,
            style: const TextStyle(
              color: vendorText,
              fontSize: 14,
              fontWeight: FontWeight.w900,
            ),
          ),
        ),
      ],
    );
  }
}

class _CardShell extends StatelessWidget {
  const _CardShell({
    required this.child,
    this.padding = const EdgeInsets.all(16),
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
        border: Border.all(color: productBorder),
        boxShadow: const [
          BoxShadow(
            color: Color(0x0A0A2A63),
            blurRadius: 12,
            offset: Offset(0, 4),
          ),
        ],
      ),
      child: child,
    );
  }
}

class _DetailSkeleton extends StatelessWidget {
  const _DetailSkeleton();

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Container(height: 16, decoration: _skeletonDecoration()),
        const SizedBox(height: 14),
        Container(height: 50, decoration: _skeletonDecoration()),
        const SizedBox(height: 14),
        Container(height: 324, decoration: _skeletonDecoration()),
        const SizedBox(height: 14),
        Container(height: 190, decoration: _skeletonDecoration()),
        const SizedBox(height: 14),
        Container(height: 190, decoration: _skeletonDecoration()),
      ],
    );
  }

  BoxDecoration _skeletonDecoration() => BoxDecoration(
        color: const Color(0xFFF0F3F7),
        borderRadius: BorderRadius.circular(12),
      );
}

List<String> _productImages(Map<String, dynamic> product) {
  final values = <String>[];

  void add(dynamic value) {
    final text = _text(value);
    if (text.isNotEmpty && !values.contains(text)) values.add(text);
  }

  add(product['main_image_url']);
  add(product['image_url']);
  add(product['card_image_url']);

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
  final direct = _text(product['dimensions_label']);
  if (direct.isNotEmpty) return direct;

  final length = _number(product['length_cm']);
  final width = _number(product['width_cm']);
  final height = _number(product['height_cm']);
  final values = <String>[];
  if (length > 0) values.add(_compact(length));
  if (width > 0) values.add(_compact(width));
  if (height > 0) values.add(_compact(height));
  return values.isEmpty ? '' : '${values.join(' × ')} cm';
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
  if (value.endsWith('s')) return value;
  if (value == 'm²' || value == 'm³' || value == 'kg') return value;
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
      : value.toStringAsFixed(2).replaceFirst(RegExp(r'0+$'), '').replaceFirst(RegExp(r'\.$'), '');
}
