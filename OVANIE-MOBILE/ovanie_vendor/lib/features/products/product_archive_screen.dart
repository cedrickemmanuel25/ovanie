import 'package:flutter/material.dart';

import '../../core/network/api_client.dart';
import '../../core/ui/vendor_mockup_ui.dart';
import '../../data/vendor_repository.dart';
import '../shell/vendor_tabs.dart';
import 'product_ui.dart';

class ProductArchiveScreen extends StatelessWidget {
  const ProductArchiveScreen({super.key, required this.productId});
  final int productId;

  @override
  Widget build(BuildContext context) => _ProductActionScreen(productId: productId, restore: false);
}

class ProductRestoreScreen extends StatelessWidget {
  const ProductRestoreScreen({super.key, required this.productId});
  final int productId;

  @override
  Widget build(BuildContext context) => _ProductActionScreen(productId: productId, restore: true);
}

/// Confirmation d'archivage (orange) ou de restauration (bleu) d'un produit.
class _ProductActionScreen extends StatefulWidget {
  const _ProductActionScreen({required this.productId, required this.restore});
  final int productId;
  final bool restore;

  @override
  State<_ProductActionScreen> createState() => _ProductActionScreenState();
}

class _ProductActionScreenState extends State<_ProductActionScreen> {
  Map<String, dynamic>? _product;
  bool _loading = true;
  bool _saving = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final data = await VendorRepository.instance.product(widget.productId);
      if (!mounted) return;
      setState(() => _product = data['product'] is Map ? Map<String, dynamic>.from(data['product'] as Map) : null);
    } catch (e) {
      if (mounted) setState(() => _error = ApiClient.friendlyError(e));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _confirm() async {
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      if (widget.restore) {
        await VendorRepository.instance.restoreProduct(widget.productId);
      } else {
        await VendorRepository.instance.archiveProduct(widget.productId);
      }
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(widget.restore ? 'Produit restauré.' : 'Produit archivé.')));
      Navigator.pop(context, true);
    } catch (e) {
      if (mounted) setState(() => _error = ApiClient.friendlyError(e));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final restore = widget.restore;
    final accent = restore ? const Color(0xFF1456D9) : Mk.orange;
    final product = _product;
    final name = '${product?['name'] ?? ''}'.trim();

    return MkTheme(
      lightStatusBar: false,
      child: Scaffold(
        backgroundColor: const Color(0xFFF5F7FB),
        body: Column(
          children: [
            MkPageHeader(
              title: restore ? 'Restaurer un produit' : 'Archiver un produit',
              subtitle: restore
                  ? 'Remettez ce produit dans la liste des produits actifs'
                  : 'Retirez ce produit de la liste des produits actifs',
            ),
            Expanded(
              child: _loading
                  ? const Center(child: CircularProgressIndicator(color: Mk.orange))
                  : product == null
                      ? Padding(
                          padding: const EdgeInsets.all(20),
                          child: Column(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              MkNotice(_error ?? 'Produit introuvable.'),
                              const SizedBox(height: 14),
                              MkBarButton(label: 'Réessayer', filled: true, onTap: _load),
                            ],
                          ),
                        )
                      : ListView(
                          padding: const EdgeInsets.fromLTRB(12, 12, 12, 16),
                          children: [
                            _ProductSummary(product: product, restore: restore),
                            const SizedBox(height: 6),
                            Image.asset(
                              'assets/images/mockups/${restore ? 'restore_box' : 'archive_box'}.jpg',
                              height: 190,
                              fit: BoxFit.contain,
                              excludeFromSemantics: true,
                            ),
                            const SizedBox(height: 6),
                            Text(
                              restore ? 'Confirmer la restauration' : 'Confirmer l’archivage',
                              textAlign: TextAlign.center,
                              style: const TextStyle(color: Mk.navy, fontSize: 23, fontWeight: FontWeight.w800, letterSpacing: -.4),
                            ),
                            const SizedBox(height: 8),
                            Padding(
                              padding: const EdgeInsets.symmetric(horizontal: 10),
                              child: Text.rich(
                                TextSpan(
                                  text: 'Le produit « ',
                                  children: [
                                    TextSpan(text: name, style: const TextStyle(color: Mk.navy, fontWeight: FontWeight.w700)),
                                    TextSpan(
                                      text: restore
                                          ? ' » sera de nouveau publié et visible par vos clients dans la boutique.'
                                          : ' » sera retiré de la liste des produits actifs et ne sera plus visible par vos clients dans la boutique.',
                                    ),
                                  ],
                                ),
                                textAlign: TextAlign.center,
                                style: const TextStyle(color: Mk.muted, fontSize: 14, height: 1.45, fontWeight: FontWeight.w500),
                              ),
                            ),
                            const SizedBox(height: 14),
                            Container(
                              padding: const EdgeInsets.fromLTRB(14, 14, 14, 4),
                              decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(14)),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.stretch,
                                children: [
                                  const Text(
                                    'Ce qui va se passer',
                                    style: TextStyle(color: Mk.navy, fontSize: 16, fontWeight: FontWeight.w800),
                                  ),
                                  const SizedBox(height: 8),
                                  for (final text in restore
                                      ? const [
                                          'Le produit sera remis dans la liste des produits actifs',
                                          'Toutes les informations (prix, stock, images, historique) seront conservées',
                                          'Il sera à nouveau visible par vos clients',
                                        ]
                                      : const [
                                          'Le produit sera déplacé dans la liste des produits archivés',
                                          'Toutes les informations (prix, stock, images, historique) seront conservées',
                                          'Vous pourrez le restaurer à tout moment',
                                        ]) ...[
                                    const Divider(height: 1, color: Color(0xFFE4E9F1)),
                                    Padding(
                                      padding: const EdgeInsets.symmetric(vertical: 11),
                                      child: Row(
                                        children: [
                                          Icon(Icons.check_circle_rounded, color: accent, size: 24),
                                          const SizedBox(width: 12),
                                          Expanded(
                                            child: Text(
                                              text,
                                              style: const TextStyle(color: Mk.muted, fontSize: 13.5, height: 1.35, fontWeight: FontWeight.w500),
                                            ),
                                          ),
                                        ],
                                      ),
                                    ),
                                  ],
                                ],
                              ),
                            ),
                            if (_error != null) ...[
                              const SizedBox(height: 12),
                              MkNotice(_error!),
                            ],
                          ],
                        ),
            ),
          ],
        ),
        bottomNavigationBar: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            if (product != null)
              MkActionBar(
                children: [
                  MkBarButton(label: 'Annuler', color: accent, onTap: _saving ? null : () => Navigator.pop(context)),
                  MkBarButton(
                    label: restore ? 'Oui, restaurer le produit' : 'Oui, archiver le produit',
                    icon: restore ? Icons.restore_rounded : Icons.inventory_2_outlined,
                    filled: true,
                    color: accent,
                    loading: _saving,
                    onTap: _confirm,
                  ),
                ],
              ),
            const VendorBottomBar(selected: VendorTab.products),
          ],
        ),
      ),
    );
  }
}

class _ProductSummary extends StatelessWidget {
  const _ProductSummary({required this.product, required this.restore});
  final Map<String, dynamic> product;
  final bool restore;

  @override
  Widget build(BuildContext context) {
    final status = restore ? 'Archivé' : productStatus(product);
    final color = productStatusColor(status);
    final category = productCategoryLabel(product);
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(14)),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(width: 96, height: 96, child: ProductNetworkImage(url: productImageUrl(product), fit: BoxFit.cover, borderRadius: 10)),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  '${product['name'] ?? 'Produit'}',
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(color: Mk.navy, fontWeight: FontWeight.w800, fontSize: 16, height: 1.2),
                ),
                if (category.isNotEmpty)
                  Padding(
                    padding: const EdgeInsets.only(top: 3),
                    child: Text(
                      category,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(color: Mk.muted, fontSize: 12.5, fontWeight: FontWeight.w500),
                    ),
                  ),
                const SizedBox(height: 6),
                Text(
                  productMoney(product['promo_price'] ?? product['price']),
                  style: const TextStyle(color: Mk.orange, fontWeight: FontWeight.w800, fontSize: 18),
                ),
                const SizedBox(height: 2),
                Text('Stock : ${product['stock'] ?? 0}', style: const TextStyle(color: Mk.muted, fontSize: 13, fontWeight: FontWeight.w500)),
                const SizedBox(height: 8),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 5),
                  decoration: BoxDecoration(color: color.withValues(alpha: .13), borderRadius: BorderRadius.circular(99)),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Container(width: 8, height: 8, decoration: BoxDecoration(color: color, shape: BoxShape.circle)),
                      const SizedBox(width: 7),
                      Text(status, style: TextStyle(color: color, fontSize: 12.5, fontWeight: FontWeight.w700)),
                    ],
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
