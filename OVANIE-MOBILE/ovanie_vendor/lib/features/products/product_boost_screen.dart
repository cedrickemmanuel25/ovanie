import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../core/network/api_client.dart';
import '../../core/ui/vendor_mockup_ui.dart';
import '../../data/vendor_repository.dart';
import '../menu/menu_ui.dart' show launchUrl;
import 'product_ui.dart';

/// Écran « Booster ce produit » côté Vendeur mobile.
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
    this.productImageUrl,
  });

  final int productId;
  final String productName;
  final Object? productImageUrl;

  @override
  State<ProductBoostScreen> createState() => _ProductBoostScreenState();
}

class _ProductBoostScreenState extends State<ProductBoostScreen> {
  final _phone = TextEditingController();
  List<Map<String, dynamic>> _packages = [];
  String? _group;
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
        _group = packages.isEmpty ? null : _groupOf(packages.first);
        _selectedKey = packages.isEmpty ? null : '${packages.first['key']}';
      });
    } catch (e) {
      if (mounted) setState(() => _error = ApiClient.friendlyError(e));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  /// Famille d'un pack : la partie de son libellé avant « - » (SEMAINE, MOIS, PREMIUM).
  String _groupOf(Map<String, dynamic> package) {
    final label = '${package['label'] ?? ''}';
    final cut = label.indexOf(' - ');
    return cut > 0 ? label.substring(0, cut).trim() : 'PACKS';
  }

  List<String> get _groups {
    final groups = <String>[];
    for (final package in _packages) {
      final group = _groupOf(package);
      if (!groups.contains(group)) groups.add(group);
    }
    return groups;
  }

  List<Map<String, dynamic>> get _visiblePackages =>
      _packages.where((package) => _groupOf(package) == _group).toList(growable: false);

  Map<String, dynamic>? get _selectedPackage {
    for (final package in _packages) {
      if ('${package['key']}' == _selectedKey) return package;
    }
    return null;
  }

  void _selectGroup(String group) {
    setState(() {
      _group = group;
      final first = _visiblePackages;
      _selectedKey = first.isEmpty ? null : '${first.first['key']}';
    });
  }

  Future<void> _pay() async {
    final key = _selectedKey;
    if (key == null || _paying) return;
    FocusManager.instance.primaryFocus?.unfocus();

    final digits = _phone.text.replaceAll(RegExp(r'[^0-9]'), '');
    if (digits.isNotEmpty && digits.length != 10) {
      setState(() => _error = 'Le numéro Mobile Money doit contenir 10 chiffres.');
      return;
    }

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
    final selected = _selectedPackage;
    return MkTheme(
      lightStatusBar: false,
      child: Scaffold(
        backgroundColor: const Color(0xFFF5F7FB),
        body: Column(
          children: [
            const MkPageHeader(title: 'Booster ce produit'),
            Expanded(
              child: _loading
                  ? const Center(child: CircularProgressIndicator(color: Mk.orange))
                  : _packages.isEmpty
                      ? Padding(
                          padding: const EdgeInsets.all(20),
                          child: Column(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              MkNotice(_error ?? 'Aucun pack de boost n’est disponible pour le moment.'),
                              const SizedBox(height: 14),
                              MkBarButton(label: 'Réessayer', filled: true, onTap: _load),
                            ],
                          ),
                        )
                      : ListView(
                          keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
                          padding: const EdgeInsets.fromLTRB(12, 12, 12, 16),
                          children: [
                            ClipRRect(
                              borderRadius: BorderRadius.circular(14),
                              child: AspectRatio(
                                aspectRatio: 890 / 402,
                                child: Image.asset(
                                  'assets/images/mockups/boost_hero.jpg',
                                  fit: BoxFit.cover,
                                  excludeFromSemantics: true,
                                ),
                              ),
                            ),
                            const SizedBox(height: 10),
                            Container(
                              padding: const EdgeInsets.all(12),
                              decoration: BoxDecoration(
                                color: Mk.softOrange,
                                borderRadius: BorderRadius.circular(14),
                                border: Border.all(color: const Color(0xFFF9D2B8)),
                              ),
                              child: Row(
                                children: [
                                  Container(
                                    width: 42,
                                    height: 42,
                                    decoration: const BoxDecoration(gradient: Mk.orangeGradient, shape: BoxShape.circle),
                                    child: const Icon(Icons.bolt_rounded, color: Colors.white, size: 26),
                                  ),
                                  const SizedBox(width: 12),
                                  Expanded(
                                    child: Text(
                                      widget.productName,
                                      maxLines: 2,
                                      overflow: TextOverflow.ellipsis,
                                      style: const TextStyle(color: Mk.navy, fontSize: 16, fontWeight: FontWeight.w800, height: 1.2),
                                    ),
                                  ),
                                  if (widget.productImageUrl != null) ...[
                                    const SizedBox(width: 10),
                                    SizedBox(
                                      width: 48,
                                      height: 48,
                                      child: ProductNetworkImage(url: widget.productImageUrl, fit: BoxFit.cover, borderRadius: 8),
                                    ),
                                  ],
                                ],
                              ),
                            ),
                            const SizedBox(height: 10),
                            _Card(
                              children: [
                                const _Heading('Pack de boost'),
                                const SizedBox(height: 10),
                                if (_groups.length > 1) ...[
                                  _GroupTabs(groups: _groups, selected: _group, onSelect: _selectGroup),
                                  const SizedBox(height: 10),
                                ],
                                for (final package in _visiblePackages)
                                  Padding(
                                    padding: const EdgeInsets.only(bottom: 8),
                                    child: _BoostPackageTile(
                                      package: package,
                                      selected: '${package['key']}' == _selectedKey,
                                      onTap: () => setState(() => _selectedKey = '${package['key']}'),
                                    ),
                                  ),
                              ],
                            ),
                            const SizedBox(height: 10),
                            _Card(
                              children: [
                                const _Heading('Numéro Mobile Money'),
                                const SizedBox(height: 10),
                                TextFormField(
                                  controller: _phone,
                                  keyboardType: TextInputType.phone,
                                  inputFormatters: [
                                    FilteringTextInputFormatter.allow(RegExp(r'[0-9 +]')),
                                    LengthLimitingTextInputFormatter(17),
                                  ],
                                  style: mkFieldText,
                                  decoration: mkInput(hint: 'Ex : 07 00 00 00 00', icon: Icons.call_outlined),
                                ),
                                const SizedBox(height: 10),
                                Container(
                                  padding: const EdgeInsets.all(12),
                                  decoration: BoxDecoration(color: const Color(0xFFF1F4F9), borderRadius: BorderRadius.circular(10)),
                                  child: const Row(
                                    children: [
                                      Icon(Icons.lock_rounded, color: Mk.icon, size: 26),
                                      SizedBox(width: 12),
                                      Expanded(
                                        child: Text(
                                          'Vous serez redirigé vers PayDunya pour finaliser le paiement. '
                                          'Le boost s’active automatiquement dès la confirmation du paiement.',
                                          style: TextStyle(color: Mk.muted, fontSize: 12.5, height: 1.4, fontWeight: FontWeight.w500),
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                              ],
                            ),
                            if (_error != null) ...[
                              const SizedBox(height: 10),
                              MkNotice(_error!),
                            ],
                          ],
                        ),
            ),
          ],
        ),
        bottomNavigationBar: _loading || _packages.isEmpty
            ? null
            : MkActionBar(
                safeArea: true,
                children: [
                  MkBarButton(
                    label: selected == null ? 'Payer maintenant' : 'Payer ${productMoney(selected['price'])}',
                    filled: true,
                    height: 52,
                    loading: _paying,
                    onTap: _selectedKey == null ? null : _pay,
                  ),
                ],
              ),
      ),
    );
  }
}

class _Card extends StatelessWidget {
  const _Card({required this.children});
  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(12, 14, 12, 8),
      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(14)),
      child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: children),
    );
  }
}

class _Heading extends StatelessWidget {
  const _Heading(this.text);
  final String text;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(left: 2),
      child: Text(text, style: const TextStyle(color: Mk.navy, fontSize: 18, fontWeight: FontWeight.w800, letterSpacing: -.3)),
    );
  }
}

/// Onglets des familles de packs (SEMAINE, MOIS, PREMIUM).
class _GroupTabs extends StatelessWidget {
  const _GroupTabs({required this.groups, required this.selected, required this.onSelect});

  final List<String> groups;
  final String? selected;
  final ValueChanged<String> onSelect;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(2),
      decoration: BoxDecoration(
        color: const Color(0xFFF3F6FB),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0xFFD9E0EC), width: 1.2),
      ),
      child: Row(
        children: [
          for (final group in groups)
            Expanded(
              child: Semantics(
                button: true,
                selected: group == selected,
                child: InkWell(
                  onTap: () => onSelect(group),
                  borderRadius: BorderRadius.circular(10),
                  child: AnimatedContainer(
                    duration: const Duration(milliseconds: 150),
                    height: 40,
                    alignment: Alignment.center,
                    padding: const EdgeInsets.symmetric(horizontal: 4),
                    decoration: BoxDecoration(
                      gradient: group == selected ? Mk.orangeGradient : null,
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: FittedBox(
                      fit: BoxFit.scaleDown,
                      child: Text(
                        group,
                        maxLines: 1,
                        style: TextStyle(
                          color: group == selected ? Colors.white : Mk.icon,
                          fontSize: 13.5,
                          fontWeight: group == selected ? FontWeight.w700 : FontWeight.w600,
                        ),
                      ),
                    ),
                  ),
                ),
              ),
            ),
        ],
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
    final price = productMoney(package['price']);

    return Semantics(
      inMutuallyExclusiveGroup: true,
      checked: selected,
      button: true,
      label: '$label, $price',
      excludeSemantics: true,
      child: Material(
        color: selected ? Mk.softOrange : Colors.white,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(12),
          side: BorderSide(color: selected ? Mk.orange : const Color(0xFFD9E0EC), width: selected ? 1.6 : 1.2),
        ),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: onTap,
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
            child: Row(
              children: [
                Icon(
                  selected ? Icons.radio_button_checked_rounded : Icons.radio_button_off_rounded,
                  color: selected ? Mk.orange : Mk.hint,
                  size: 26,
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(label, style: const TextStyle(color: Mk.navy, fontSize: 14, fontWeight: FontWeight.w800, height: 1.2)),
                      if (days > 0) ...[
                        const SizedBox(height: 3),
                        Text(
                          'Visibilité pendant $days jours',
                          style: const TextStyle(color: Mk.muted, fontSize: 12.5, fontWeight: FontWeight.w500),
                        ),
                      ],
                    ],
                  ),
                ),
                const SizedBox(width: 8),
                Text(
                  price,
                  style: TextStyle(color: selected ? const Color(0xFFE5391B) : Mk.navy, fontSize: 15.5, fontWeight: FontWeight.w800),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
