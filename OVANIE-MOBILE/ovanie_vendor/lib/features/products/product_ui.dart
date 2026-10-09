import 'package:flutter/material.dart';
import '../../core/ui/vendor_top_bar.dart';

import '../../core/config/app_config.dart';
import '../../core/ui/vendor_design.dart';
import '../../core/ui/vendor_select.dart';

const productOrange = Color(0xFFFF6500);
const productNavy = Color(0xFF062A62);
const productDeep = Color(0xFF031D47);
const productBorder = Color(0xFFE3E8F0);
const productSoft = Color(0xFFF4F7FB);

String productMoney(Object? value) {
  final amount = double.tryParse('${value ?? 0}') ?? 0;
  final rounded = amount.round();
  final raw = rounded.toString();
  final out = StringBuffer();
  for (var i = 0; i < raw.length; i++) {
    if (i > 0 && (raw.length - i) % 3 == 0) out.write(' ');
    out.write(raw[i]);
  }
  return '${out.toString()} FCFA';
}

String productStatus(Map<String, dynamic> p) {
  final status = '${p['status'] ?? ''}'.toLowerCase();
  final active = p['is_active'] == true || '${p['is_active']}' == '1';
  final stock = int.tryParse('${p['stock'] ?? 0}') ?? 0;
  if (status == 'archived') return 'Archivé';
  if (status == 'draft') return 'Brouillon';
  if (stock <= 0 || '${p['availability_status']}' == 'out_of_stock') return 'Rupture';
  if (!active) return 'Masqué';
  return 'Actif';
}

Color productStatusColor(String status) {
  switch (status.toLowerCase()) {
    case 'actif':
      return const Color(0xFF139448);
    case 'brouillon':
      return const Color(0xFFF29900);
    case 'rupture':
      return const Color(0xFFE53935);
    case 'archivé':
    case 'masqué':
      return const Color(0xFF667085);
    default:
      return vendorBlue;
  }
}

class ProductStatusBadge extends StatelessWidget {
  const ProductStatusBadge(this.status, {super.key});
  final String status;

  @override
  Widget build(BuildContext context) {
    final color = productStatusColor(status);
    return Container(
      constraints: const BoxConstraints(minWidth: 70),
      padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 5),
      decoration: BoxDecoration(color: color.withValues(alpha: .11), borderRadius: BorderRadius.circular(22)),
      child: Text(status, textAlign: TextAlign.center, style: TextStyle(color: color, fontWeight: FontWeight.w800, fontSize: 11.5)),
    );
  }
}

class ProductNetworkImage extends StatelessWidget {
  const ProductNetworkImage({
    super.key,
    required this.url,
    this.fit = BoxFit.contain,
    this.borderRadius = 12,
  });

  final Object? url;
  final BoxFit fit;
  final double borderRadius;

  @override
  Widget build(BuildContext context) {
    final resolved = AppConfig.mediaUrl(url);
    return ClipRRect(
      borderRadius: BorderRadius.circular(borderRadius),
      child: Container(
        color: const Color(0xFFF7F8FA),
        alignment: Alignment.center,
        child: resolved.isEmpty
            ? const Icon(Icons.inventory_2_outlined, color: Color(0xFF9AA6B7), size: 42)
            : Image.network(
                resolved,
                width: double.infinity,
                height: double.infinity,
                fit: fit,
                errorBuilder: (_, __, ___) => const Icon(Icons.broken_image_outlined, color: Color(0xFF9AA6B7), size: 40),
              ),
      ),
    );
  }
}

class ProductBrandLockup extends StatelessWidget {
  const ProductBrandLockup({super.key, this.compact = false});

  final bool compact;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: compact ? 164 : 184,
      height: compact ? 50 : 58,
      child: Image.asset(
        'assets/images/ovanie_logo.png',
        fit: BoxFit.contain,
        alignment: Alignment.centerLeft,
        filterQuality: FilterQuality.high,
      ),
    );
  }
}

class ProductHeader extends StatefulWidget {
  const ProductHeader({
    super.key,
    required this.title,
    required this.subtitle,
    this.showBack = false,
    this.compact = false,
    this.bottom,
    this.showNotifications = true,
    this.onBack,
  });

  final String title;
  final String subtitle;
  final bool showBack;
  final bool compact;
  final Widget? bottom;
  final bool showNotifications;
  final VoidCallback? onBack;

  @override
  State<ProductHeader> createState() => _ProductHeaderState();
}

class _ProductHeaderState extends State<ProductHeader> {
  @override
  Widget build(BuildContext context) {
    return VendorTopBar(
      title: widget.title,
      showBack: widget.showBack,
      onBack: widget.onBack,
      bottom: widget.bottom,
      showNotifications: widget.showNotifications,
    );
  }
}

class ProductSectionTitle extends StatelessWidget {
  const ProductSectionTitle({super.key, required this.title, this.trailing});
  final String title;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(child: Text(title, style: const TextStyle(color: vendorText, fontSize: 20, fontWeight: FontWeight.w900))),
        if (trailing != null) trailing!,
      ],
    );
  }
}

class ProductSheetSelector<T> extends StatelessWidget {
  const ProductSheetSelector({
    super.key,
    required this.value,
    required this.label,
    required this.items,
    required this.onChanged,
    required this.display,
    this.icon = Icons.keyboard_arrow_down_rounded,
    this.hint = 'Sélectionner',
    this.buttonText,
  });

  final T? value;
  final String label;
  final List<T> items;
  final ValueChanged<T> onChanged;
  final String Function(T value) display;
  final IconData icon;
  final String hint;
  final String? buttonText;

  @override
  Widget build(BuildContext context) {
    return VendorSelectField<T>(
      title: label,
      items: items,
      label: display,
      value: value,
      hint: hint,
      displayText: buttonText,
      onChanged: onChanged,
    );
  }
}

/// Photo d'un produit telle que renvoyée par l'API : vignette, sinon image principale.
Object? productImageUrl(Map<String, dynamic> product) {
  for (final key in const <String>['thumb_image_url', 'card_image_url', 'main_image_url', 'image_url']) {
    final value = '${product[key] ?? ''}'.trim();
    if (value.isNotEmpty && value != 'null') return value;
  }
  return null;
}

/// « Catégorie • Sous-catégorie » d'un produit.
String productCategoryLabel(Map<String, dynamic> product) {
  String name(Object? raw) => raw is Map ? '${raw['name'] ?? ''}'.trim() : '';
  final category = product['category'];
  final parent = category is Map ? name(category['parent']) : '';
  return <String>[parent, name(category), name(product['subcategory'])].where((part) => part.isNotEmpty).toSet().join(' • ');
}
