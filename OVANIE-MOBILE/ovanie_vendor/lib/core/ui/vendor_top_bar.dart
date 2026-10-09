import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../data/vendor_repository.dart';
import '../../features/notifications/vendor_notifications_screen.dart';

const vendorTopBarColor = Color(0xFF052556);

/// Barre de titre commune de l'application Vendeur : compacte, sans logo ni
/// sous-titre explicatif. Les écrans racines ont un titre un peu plus grand.
class VendorTopBar extends StatefulWidget {
  const VendorTopBar({
    super.key,
    required this.title,
    this.showBack = false,
    this.onBack,
    this.meta,
    this.trailing,
    this.bottom,
    this.showNotifications = true,
  });

  final String title;
  final bool showBack;
  final VoidCallback? onBack;

  /// Ligne secondaire courte (ex. « OVANIE-7WFM8HNO · 29 sept. »).
  final String? meta;
  final Widget? trailing;
  final Widget? bottom;
  final bool showNotifications;

  @override
  State<VendorTopBar> createState() => _VendorTopBarState();
}

class _VendorTopBarState extends State<VendorTopBar> {
  static int? _cachedUnread;
  static DateTime? _cachedAt;
  int _unread = _cachedUnread ?? 0;

  @override
  void initState() {
    super.initState();
    if (!widget.showNotifications) return;
    final fresh = _cachedAt != null &&
        DateTime.now().difference(_cachedAt!) < const Duration(minutes: 2);
    if (!fresh) WidgetsBinding.instance.addPostFrameCallback((_) => _load());
  }

  Future<void> _load() async {
    try {
      final data = await VendorRepository.instance.dashboard();
      final value = int.tryParse('${data['unread_notifications_count'] ?? 0}') ?? 0;
      _cachedUnread = value;
      _cachedAt = DateTime.now();
      if (mounted) setState(() => _unread = value);
    } catch (_) {
      // Pas de compteur tant que l'API ne répond pas.
    }
  }

  Future<void> _openNotifications() async {
    await Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => const VendorNotificationsScreen()),
    );
    _cachedAt = null;
    if (mounted) _load();
  }

  @override
  Widget build(BuildContext context) {
    final meta = widget.meta?.trim() ?? '';
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.light.copyWith(statusBarColor: Colors.transparent),
      child: Container(
        width: double.infinity,
        color: vendorTopBarColor,
        child: SafeArea(
          bottom: false,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              ConstrainedBox(
                constraints: const BoxConstraints(minHeight: 56),
                child: Row(
                  children: [
                    if (widget.showBack)
                      IconButton(
                        tooltip: 'Retour',
                        onPressed: widget.onBack ?? () => Navigator.maybePop(context),
                        icon: const Icon(Icons.arrow_back_rounded, color: Colors.white),
                      )
                    else
                      const SizedBox(width: 16),
                    Expanded(
                      child: Padding(
                        padding: const EdgeInsets.symmetric(vertical: 8),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Text(
                              widget.title,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: TextStyle(
                                color: Colors.white,
                                fontSize: widget.showBack ? 18 : 21,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                            if (meta.isNotEmpty)
                              Text(
                                meta,
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: const TextStyle(
                                  color: Color(0xFFB9C8E4),
                                  fontSize: 12.5,
                                ),
                              ),
                          ],
                        ),
                      ),
                    ),
                    if (widget.trailing != null) ...[
                      widget.trailing!,
                      const SizedBox(width: 4),
                    ],
                    if (widget.showNotifications)
                      IconButton(
                        tooltip: 'Notifications',
                        onPressed: _openNotifications,
                        icon: Badge(
                          isLabelVisible: _unread > 0,
                          backgroundColor: const Color(0xFFFF6500),
                          label: Text(_unread > 99 ? '99+' : '$_unread'),
                          child: const Icon(
                            Icons.notifications_none_rounded,
                            color: Colors.white,
                          ),
                        ),
                      )
                    else
                      const SizedBox(width: 8),
                  ],
                ),
              ),
              if (widget.bottom != null)
                Padding(
                  padding: const EdgeInsets.fromLTRB(16, 4, 16, 14),
                  child: widget.bottom!,
                ),
            ],
          ),
        ),
      ),
    );
  }
}
