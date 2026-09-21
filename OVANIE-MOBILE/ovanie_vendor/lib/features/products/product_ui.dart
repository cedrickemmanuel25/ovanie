import 'package:flutter/material.dart';

import '../../core/config/app_config.dart';
import '../../core/ui/vendor_design.dart';
import '../../data/vendor_repository.dart';
import '../notifications/vendor_notifications_screen.dart';

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
      alignment: Alignment.center,
      child: Text(status, style: TextStyle(color: color, fontWeight: FontWeight.w800, fontSize: 11.5)),
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
  static int? _cachedUnread;
  static DateTime? _cachedUnreadAt;

  int _unread = _cachedUnread ?? 0;

  @override
  void initState() {
    super.initState();
    if (widget.showNotifications) {
      final fresh = _cachedUnreadAt != null && DateTime.now().difference(_cachedUnreadAt!) < const Duration(minutes: 2);
      if (!fresh) {
        WidgetsBinding.instance.addPostFrameCallback((_) {
          if (mounted) _loadCount();
        });
      }
    }
  }

  Future<void> _loadCount() async {
    try {
      final data = await VendorRepository.instance.dashboard();
      var unread = int.tryParse('${data['unread_notifications_count'] ?? 0}') ?? 0;
      if (unread <= 0) {
        try {
          final notifications = await VendorRepository.instance.notifications(page: 1);
          unread = int.tryParse('${notifications['unread_count'] ?? unread}') ?? unread;
        } catch (_) {
          // Conserver uniquement les données réelles disponibles.
        }
      }
      _cachedUnread = unread;
      _cachedUnreadAt = DateTime.now();
      if (!mounted) return;
      setState(() => _unread = unread);
    } catch (_) {
      // Pas de badge fictif si l'API est indisponible.
    }
  }

  Future<void> _openNotifications() async {
    await Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => const VendorNotificationsScreen()),
    );
    if (mounted) _loadCount();
  }

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
            child: IgnorePointer(
              child: Opacity(
                opacity: .20,
                child: ColorFiltered(
                  colorFilter: const ColorFilter.mode(Color(0xFF06386F), BlendMode.multiply),
                  child: Image.asset(
                    'assets/images/vendor_construction_blue.png',
                    fit: BoxFit.cover,
                    alignment: Alignment.centerRight,
                  ),
                ),
              ),
            ),
          ),
          SafeArea(
            bottom: false,
            child: Padding(
              padding: EdgeInsets.fromLTRB(24, widget.compact ? 10 : 18, 24, widget.bottom == null ? (widget.compact ? 18 : 28) : 26),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Row(
                    children: [
                      if (widget.showBack) ...[
                        IconButton(
                          onPressed: widget.onBack ?? () => Navigator.maybePop(context),
                          icon: const Icon(Icons.arrow_back_ios_new_rounded, color: Colors.white, size: 24),
                        ),
                        const SizedBox(width: 6),
                      ],
                      const Expanded(child: ProductBrandLockup()),
                      if (widget.showNotifications)
                        Stack(
                          clipBehavior: Clip.none,
                          children: [
                            IconButton(
                              onPressed: _openNotifications,
                              tooltip: 'Notifications',
                              icon: const Icon(Icons.notifications_none_rounded, color: Colors.white, size: 30),
                            ),
                            if (_unread > 0)
                              Positioned(
                                top: -1,
                                right: 0,
                                child: Container(
                                  constraints: const BoxConstraints(minWidth: 22, minHeight: 22),
                                  padding: const EdgeInsets.symmetric(horizontal: 5),
                                  alignment: Alignment.center,
                                  decoration: const BoxDecoration(color: productOrange, shape: BoxShape.circle),
                                  child: Text(
                                    _unread > 99 ? '99+' : '$_unread',
                                    style: const TextStyle(color: Colors.white, fontSize: 10.5, fontWeight: FontWeight.w900),
                                  ),
                                ),
                              ),
                          ],
                        ),
                    ],
                  ),
                  if (!widget.compact && widget.title.isNotEmpty) ...[
                    const SizedBox(height: 18),
                    Text(widget.title, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w900, fontSize: 29, height: 1.05)),
                    const SizedBox(height: 7),
                    Text(widget.subtitle, style: const TextStyle(color: Color(0xFFF1F5FB), fontSize: 15.5, height: 1.3)),
                  ],
                  if (widget.bottom != null) ...[
                    const SizedBox(height: 22),
                    widget.bottom!,
                  ],
                ],
              ),
            ),
          ),
        ],
      ),
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

  Future<void> _open(BuildContext context) async {
    FocusManager.instance.primaryFocus?.unfocus();
    final picked = await showModalBottomSheet<T>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      barrierColor: Colors.black.withValues(alpha: .38),
      builder: (sheetContext) => SafeArea(
        top: false,
        child: Container(
          constraints: BoxConstraints(maxHeight: MediaQuery.sizeOf(context).height * .68),
          padding: const EdgeInsets.fromLTRB(18, 10, 18, 18),
          decoration: const BoxDecoration(color: Colors.white, borderRadius: BorderRadius.vertical(top: Radius.circular(24))),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(width: 44, height: 4, decoration: BoxDecoration(color: const Color(0xFFD5DBE5), borderRadius: BorderRadius.circular(99))),
              const SizedBox(height: 14),
              Row(
                children: [
                  Expanded(child: Text(label, style: const TextStyle(color: vendorText, fontSize: 18, fontWeight: FontWeight.w900))),
                  IconButton(onPressed: () => Navigator.pop(sheetContext), icon: const Icon(Icons.close_rounded)),
                ],
              ),
              const SizedBox(height: 4),
              Flexible(
                child: ListView.separated(
                  shrinkWrap: true,
                  itemCount: items.length,
                  separatorBuilder: (_, __) => const Divider(height: 1),
                  itemBuilder: (_, index) {
                    final item = items[index];
                    final selected = item == value;
                    return ListTile(
                      contentPadding: const EdgeInsets.symmetric(horizontal: 4, vertical: 2),
                      title: Text(display(item), style: TextStyle(color: vendorText, fontWeight: selected ? FontWeight.w900 : FontWeight.w600)),
                      trailing: selected ? const Icon(Icons.check_circle_rounded, color: vendorBlue) : null,
                      onTap: () => Navigator.pop(sheetContext, item),
                    );
                  },
                ),
              ),
            ],
          ),
        ),
      ),
    );
    if (picked != null) onChanged(picked);
  }

  @override
  Widget build(BuildContext context) {
    final text = buttonText ?? (value == null ? hint : display(value as T));
    return InkWell(
      borderRadius: BorderRadius.circular(14),
      onTap: items.isEmpty ? null : () => _open(context),
      child: Container(
        constraints: const BoxConstraints(minHeight: 54),
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
        decoration: BoxDecoration(color: Colors.white, border: Border.all(color: productBorder), borderRadius: BorderRadius.circular(14)),
        child: Row(
          children: [
            Icon(icon, color: vendorText, size: 22),
            const SizedBox(width: 12),
            Expanded(child: Text(text, maxLines: 1, overflow: TextOverflow.ellipsis, style: TextStyle(color: value == null ? vendorMuted : vendorText, fontWeight: FontWeight.w700))),
            const Icon(Icons.keyboard_arrow_down_rounded, color: vendorText),
          ],
        ),
      ),
    );
  }
}
