import 'package:flutter/material.dart';

import '../../core/network/api_client.dart';
import '../../core/ui/vendor_design.dart';
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
    try {
      final data = await VendorRepository.instance.product(widget.productId);
      if (!mounted) return;
      setState(() => _product = data['product'] is Map ? Map<String, dynamic>.from(data['product'] as Map) : <String, dynamic>{});
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
    final headerBottom = _loading
        ? Container(
            height: 124,
            alignment: Alignment.center,
            decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(18)),
            child: const CircularProgressIndicator(),
          )
        : _product == null
            ? const SizedBox.shrink()
            : _ProductSummary(product: _product!, restore: restore);

    return Scaffold(
      backgroundColor: const Color(0xFFF7F9FC),
      body: Column(
        children: [
          ProductHeader(
            title: restore ? 'Restaurer un produit' : 'Archiver un produit',
            subtitle: restore ? 'Remettez ce produit dans la liste des produits actifs' : 'Retirez ce produit de la liste des produits actifs',
            showBack: true,
            bottom: headerBottom,
          ),
          Expanded(
            child: Container(
              width: double.infinity,
              decoration: const BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
              ),
              child: ListView(
                padding: const EdgeInsets.fromLTRB(22, 20, 22, 30),
                children: [
                  Center(
                    child: Container(
                      width: 50,
                      height: 5,
                      decoration: BoxDecoration(color: const Color(0xFFB9C4D8), borderRadius: BorderRadius.circular(99)),
                    ),
                  ),
                  if (_error != null) ...[
                    const SizedBox(height: 14),
                    VendorErrorBox(_error),
                  ],
                  const SizedBox(height: 20),
                  VendorCircleIcon(
                    icon: restore ? Icons.restore_rounded : Icons.archive_rounded,
                    size: 96,
                    iconSize: 48,
                    color: restore ? vendorBlue : productOrange,
                    background: (restore ? vendorBlue : productOrange).withValues(alpha: .10),
                  ),
                  const SizedBox(height: 18),
                  Text(
                    restore ? 'Confirmer la restauration' : 'Confirmer l’archivage',
                    textAlign: TextAlign.center,
                    style: const TextStyle(color: vendorText, fontSize: 25, fontWeight: FontWeight.w900),
                  ),
                  const SizedBox(height: 13),
                  Text(
                    restore
                        ? 'Le produit « ${_product?['name'] ?? ''} » sera de nouveau publié et visible par vos clients dans la boutique.'
                        : 'Le produit « ${_product?['name'] ?? ''} » sera retiré de la liste des produits actifs et ne sera plus visible par vos clients dans la boutique.',
                    textAlign: TextAlign.center,
                    style: const TextStyle(color: vendorMuted, fontSize: 15, height: 1.5),
                  ),
                  const SizedBox(height: 24),
                  Container(
                    padding: const EdgeInsets.fromLTRB(18, 16, 18, 16),
                    decoration: BoxDecoration(
                      color: (restore ? vendorBlue : productOrange).withValues(alpha: .055),
                      borderRadius: BorderRadius.circular(16),
                    ),
                    child: Column(
                      children: (restore
                              ? const [
                                  'Le produit sera remis dans la liste des produits actifs',
                                  'Toutes les informations (prix, stock, images, historique) seront conservées',
                                  'Il sera à nouveau visible par vos clients',
                                ]
                              : const [
                                  'Le produit sera déplacé dans la liste des produits archivés',
                                  'Toutes les informations (prix, stock, images, historique) seront conservées',
                                  'Vous pourrez le restaurer à tout moment',
                                ])
                          .map(
                            (text) => Padding(
                              padding: const EdgeInsets.symmetric(vertical: 8),
                              child: Row(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Icon(Icons.check_circle_rounded, color: restore ? vendorBlue : productOrange, size: 23),
                                  const SizedBox(width: 12),
                                  Expanded(child: Text(text, style: const TextStyle(color: vendorText, fontSize: 14.2, height: 1.35))),
                                ],
                              ),
                            ),
                          )
                          .toList(),
                    ),
                  ),
                  const SizedBox(height: 26),
                  Row(
                    children: [
                      Expanded(
                        child: SizedBox(
                          height: 58,
                          child: OutlinedButton(
                            onPressed: _saving ? null : () => Navigator.pop(context),
                            style: OutlinedButton.styleFrom(
                              side: const BorderSide(color: Color(0xFF9CB7E4)),
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                            ),
                            child: const Text('Annuler', style: TextStyle(color: vendorText, fontSize: 16, fontWeight: FontWeight.w800)),
                          ),
                        ),
                      ),
                      const SizedBox(width: 14),
                      Expanded(
                        flex: 2,
                        child: SizedBox(
                          height: 58,
                          child: FilledButton.icon(
                            style: FilledButton.styleFrom(
                              backgroundColor: restore ? vendorBlue : productOrange,
                              foregroundColor: Colors.white,
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                            ),
                            onPressed: _saving ? null : _confirm,
                            icon: _saving
                                ? const SizedBox.square(dimension: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                                : Icon(restore ? Icons.restore_rounded : Icons.archive_outlined),
                            label: Text(
                              restore ? 'Oui, restaurer le produit' : 'Oui, archiver le produit',
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(fontSize: 15.5, fontWeight: FontWeight.w800),
                            ),
                          ),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
      bottomNavigationBar: const VendorBottomBar(selected: VendorTab.products),
    );
  }
}

class _ProductSummary extends StatelessWidget {
  const _ProductSummary({required this.product, required this.restore});
  final Map<String, dynamic> product;
  final bool restore;

  @override
  Widget build(BuildContext context) {
    final category = product['category'] is Map ? '${(product['category'] as Map)['name'] ?? ''}' : '';
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(color: Colors.white, border: Border.all(color: productBorder), borderRadius: BorderRadius.circular(18)),
      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
        SizedBox(width: 116, height: 100, child: ProductNetworkImage(url: product['image_url'], fit: BoxFit.cover)),
        const SizedBox(width: 14),
        Expanded(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text('${product['name'] ?? 'Produit'}', maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(color: vendorText, fontWeight: FontWeight.w900, fontSize: 17)),
            const SizedBox(height: 4),
            Text(category, style: const TextStyle(color: vendorMuted, fontSize: 12.5)),
            const SizedBox(height: 8),
            Text(productMoney(product['promo_price'] ?? product['price']), style: const TextStyle(color: productOrange, fontWeight: FontWeight.w900, fontSize: 16)),
            const SizedBox(height: 4),
            Text('Stock : ${product['stock'] ?? 0}', style: const TextStyle(color: vendorMuted, fontSize: 12.5)),
          ]),
        ),
        const SizedBox(width: 8),
        ProductStatusBadge(restore ? 'Archivé' : productStatus(product)),
      ]),
    );
  }
}
