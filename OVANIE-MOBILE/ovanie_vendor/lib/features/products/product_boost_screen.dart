import 'package:flutter/material.dart';

import '../../core/network/api_client.dart';
import '../../core/ui/vendor_design.dart';
import '../../data/vendor_repository.dart';
import '../menu/menu_ui.dart' show launchUrl;
import 'product_ui.dart';

/// Écran "Booster ce produit" côté Vendeur mobile.
///
/// Reproduit le parcours du modal Web (resources/views/vendor/products/
/// index.blade.php) : choix d'un pack parmi VendorProductController::
/// BOOST_PACKAGES via GET /mobile/v1/vendor/boost/packages, puis paiement
/// PayDunya via POST .../boost/pay. Le lien de paiement retourné est ouvert
/// dans le navigateur externe via le même canal natif ('ovanie/external_url')
/// déjà utilisé ailleurs dans cette app (menu_ui.dart, after_sales_screen.dart).
class ProductBoostScreen extends StatefulWidget {
  const ProductBoostScreen({
    super.key,
    required this.productId,
    required this.productName,
  });

  final int productId;
  final String productName;

  @override
  State<ProductBoostScreen> createState() => _ProductBoostScreenState();
}

class _ProductBoostScreenState extends State<ProductBoostScreen> {
  final _phone = TextEditingController();
  List<Map<String, dynamic>> _packages = [];
  String? _selectedKey;
  bool _loading = true;
  bool _paying = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _phone.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final packages = await VendorRepository.instance.boostPackages();
      if (!mounted) return;
      setState(() {
        _packages = packages;
        _selectedKey = packages.isEmpty ? null : '${packages.first['key']}';
      });
    } catch (e) {
      if (mounted) setState(() => _error = ApiClient.friendlyError(e));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Map<String, dynamic>? get _selectedPackage {
    if (_selectedKey == null) return null;
    for (final package in _packages) {
      if ('${package['key']}' == _selectedKey) return package;
    }
    return null;
  }

  Future<void> _pay() async {
    final key = _selectedKey;
    if (key == null || _paying) return;

    setState(() {
      _paying = true;
      _error = null;
    });

    try {
      final redirectUrl = await VendorRepository.instance.payBoost(
        widget.productId,
        key,
        phone: _phone.text,
      );
      await launchUrl(Uri.parse(redirectUrl));
      if (!mounted) return;
      Navigator.pop(context, true);
    } catch (e) {
      if (mounted) setState(() => _error = ApiClient.friendlyError(e));
    } finally {
      if (mounted) setState(() => _paying = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: vendorPage,
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        foregroundColor: vendorText,
        title: const Text(
          'Booster ce produit',
          style: TextStyle(fontWeight: FontWeight.w900, fontSize: 16),
        ),
      ),
      body: SafeArea(
        child: _loading
            ? const Center(child: CircularProgressIndicator(color: productOrange))
            : ListView(
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 26),
                children: [
                  Container(
                    padding: const EdgeInsets.all(14),
                    decoration: BoxDecoration(
                      color: const Color(0xFFFFF0E8),
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: const Color(0xFFFFD3B8)),
                    ),
                    child: Row(
                      children: [
                        const Icon(Icons.bolt_rounded, color: productOrange, size: 22),
                        const SizedBox(width: 10),
                        Expanded(
                          child: Text(
                            widget.productName,
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              color: vendorText,
                              fontSize: 13.5,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 18),
                  const Text(
                    'Pack de boost',
                    style: TextStyle(
                      color: vendorText,
                      fontSize: 13,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 10),
                  ..._packages.map(
                    (package) => Padding(
                      padding: const EdgeInsets.only(bottom: 9),
                      child: _BoostPackageTile(
                        package: package,
                        selected: '${package['key']}' == _selectedKey,
                        onTap: () => setState(() => _selectedKey = '${package['key']}'),
                      ),
                    ),
                  ),
                  const SizedBox(height: 10),
                  const Text(
                    'Numéro Mobile Money',
                    style: TextStyle(
                      color: vendorText,
                      fontSize: 13,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 8),
                  TextFormField(
                    controller: _phone,
                    keyboardType: TextInputType.phone,
                    decoration: InputDecoration(
                      hintText: 'Ex : 07 00 00 00 00',
                      hintStyle: const TextStyle(color: Color(0xFF91A0BC), fontSize: 13.2),
                      prefixIcon: const Icon(Icons.phone_outlined, color: vendorText, size: 22),
                      filled: true,
                      fillColor: Colors.white,
                      contentPadding: const EdgeInsets.symmetric(horizontal: 13, vertical: 15),
                      border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(8),
                        borderSide: const BorderSide(color: Color(0xFFC7D4E7)),
                      ),
                      enabledBorder: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(8),
                        borderSide: const BorderSide(color: Color(0xFFC7D4E7)),
                      ),
                      focusedBorder: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(8),
                        borderSide: const BorderSide(color: vendorBlue, width: 1.3),
                      ),
                    ),
                  ),
                  const SizedBox(height: 22),
                  if (_error != null) ...[
                    VendorErrorBox(_error),
                    const SizedBox(height: 14),
                  ],
                  VendorPrimaryButton(
                    label: _selectedPackage == null
                        ? 'Payer maintenant'
                        : 'Payer ${productMoney(_selectedPackage!['price'])}',
                    loading: _paying,
                    onPressed: _selectedKey == null ? null : _pay,
                  ),
                  const SizedBox(height: 10),
                  const Text(
                    'Vous serez redirigé vers PayDunya pour finaliser le paiement. '
                    'Le boost s’active automatiquement dès la confirmation du paiement.',
                    style: TextStyle(color: vendorMuted, fontSize: 11, height: 1.4),
                  ),
                ],
              ),
      ),
    );
  }
}

class _BoostPackageTile extends StatelessWidget {
  const _BoostPackageTile({
    required this.package,
    required this.selected,
    required this.onTap,
  });

  final Map<String, dynamic> package;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final label = '${package['label'] ?? ''}';
    final days = int.tryParse('${package['days'] ?? 0}') ?? 0;
    final price = package['price'];

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 13),
        decoration: BoxDecoration(
          color: selected ? const Color(0xFFFFF0E8) : Colors.white,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: selected ? productOrange : productBorder, width: selected ? 2 : 1),
        ),
        child: Row(
          children: [
            Icon(
              selected ? Icons.radio_button_checked_rounded : Icons.radio_button_off_rounded,
              color: selected ? productOrange : const Color(0xFFA7B3C6),
              size: 22,
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    label,
                    style: const TextStyle(
                      color: vendorText,
                      fontSize: 13,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  if (days > 0) ...[
                    const SizedBox(height: 2),
                    Text(
                      'Visibilité pendant $days jours',
                      style: const TextStyle(color: vendorMuted, fontSize: 10.8),
                    ),
                  ],
                ],
              ),
            ),
            Text(
              productMoney(price),
              style: const TextStyle(
                color: productOrange,
                fontSize: 13.5,
                fontWeight: FontWeight.w900,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
