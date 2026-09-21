import 'dart:math' as math;

import 'package:flutter/material.dart';

import '../../core/utils/formatters.dart';
import '../../data/vendor_repository.dart';
import '../notifications/vendor_notifications_screen.dart';
import '../shell/vendor_tabs.dart';

const orderOrange = Color(0xFFFF5D00);
const orderNavy = Color(0xFF062A62);
const orderBlue = Color(0xFF0B52C8);
const orderText = Color(0xFF08255B);
const orderMuted = Color(0xFF5E7094);
const orderBorder = Color(0xFFDDE5F1);
const orderSoftBlue = Color(0xFFF2F7FF);
const orderSoftOrange = Color(0xFFFFF3E9);
const orderSoftGreen = Color(0xFFEAF8EC);
const orderGreen = Color(0xFF168A2B);

/// Logo officiel partagé avec les écrans produits.
class OrderBrandLockup extends StatelessWidget {
  const OrderBrandLockup({super.key, this.compact = false});
  final bool compact;
  @override
  Widget build(BuildContext context) => SizedBox(
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

String orderStatusLabel(Object? raw) {
  switch ('${raw ?? ''}'.trim().toLowerCase()) {
    case 'pending':
      return 'Nouvelle';
    case 'accepted':
    case 'preparing':
      return 'Préparation';
    case 'ready':
      // Même libellé que l'espace vendeur web pour ce statut : la commande
      // est prête, distincte de "en cours de préparation".
      return 'Prête';
    case 'shipped':
    case 'assigned':
    case 'picked_up':
    case 'in_transit':
      return 'Expédiée';
    case 'delivered':
      return 'Livrée';
    case 'cancelled':
      return 'Annulée';
    default:
      final text = '${raw ?? ''}'.trim();
      return text.isEmpty ? 'Nouvelle' : text;
  }
}

Color orderStatusColor(Object? raw) {
  switch ('${raw ?? ''}'.trim().toLowerCase()) {
    case 'pending':
      return const Color(0xFF176BDF);
    case 'accepted':
    case 'preparing':
    case 'ready':
      return orderOrange;
    case 'shipped':
    case 'assigned':
    case 'picked_up':
    case 'in_transit':
      return const Color(0xFF158A2D);
    case 'delivered':
      return const Color(0xFF168A2B);
    case 'cancelled':
      return const Color(0xFFD92D20);
    default:
      return orderBlue;
  }
}

Color orderStatusBackground(Object? raw) {
  final color = orderStatusColor(raw);
  return color.withValues(alpha: .11);
}

class OrderStatusPill extends StatelessWidget {
  const OrderStatusPill({
    super.key,
    required this.status,
    this.label,
    this.compact = false,
  });

  final Object? status;
  final String? label;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    final color = orderStatusColor(status);
    return Container(
      padding: EdgeInsets.symmetric(
        horizontal: compact ? 12 : 16,
        vertical: compact ? 7 : 8,
      ),
      decoration: BoxDecoration(
        color: orderStatusBackground(status),
        borderRadius: BorderRadius.circular(99),
      ),
      child: Text(
        label ?? orderStatusLabel(status),
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
        style: TextStyle(
          color: color,
          fontSize: compact ? 12 : 13.5,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }
}

class OrderPaymentPill extends StatelessWidget {
  const OrderPaymentPill({super.key, required this.status, this.label});
  final Object? status;
  final String? label;

  @override
  Widget build(BuildContext context) {
    final paid = [
      'paid',
      'escrow_held',
      'verified',
      'commission_paid',
      'released_to_vendor',
    ].contains('${status ?? ''}'.toLowerCase());
    final color = paid ? orderGreen : orderOrange;
    final text = label ?? (paid ? 'Payée' : 'À la livraison');
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 5),
      decoration: BoxDecoration(
        color: color.withValues(alpha: .11),
        borderRadius: BorderRadius.circular(99),
      ),
      child: Text(
        text,
        style: TextStyle(
          color: color,
          fontSize: 12.5,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }
}

class OrderHeader extends StatefulWidget {
  const OrderHeader({
    super.key,
    required this.title,
    required this.subtitle,
    this.showBack = false,
    this.bottom,
    this.orderNumber,
    this.orderDate,
    this.showNotifications = true,
    this.onBack,
    this.titleTrailing,
  });

  final String title;
  final String subtitle;
  final bool showBack;
  final Widget? bottom;
  final String? orderNumber;
  final String? orderDate;
  final bool showNotifications;
  final VoidCallback? onBack;
  final Widget? titleTrailing;

  @override
  State<OrderHeader> createState() => _OrderHeaderState();
}

class _OrderHeaderState extends State<OrderHeader> {
  static int? _cachedUnread;
  static DateTime? _cachedAt;
  int _unread = _cachedUnread ?? 0;

  @override
  void initState() {
    super.initState();
    if (widget.showNotifications) {
      final fresh =
          _cachedAt != null &&
          DateTime.now().difference(_cachedAt!) < const Duration(minutes: 2);
      if (!fresh) {
        WidgetsBinding.instance.addPostFrameCallback((_) => _loadUnread());
      }
    }
  }

  Future<void> _loadUnread() async {
    try {
      final dashboard = await VendorRepository.instance.dashboard();
      final value =
          int.tryParse('${dashboard['unread_notifications_count'] ?? 0}') ?? 0;
      _cachedUnread = value;
      _cachedAt = DateTime.now();
      if (mounted) setState(() => _unread = value);
    } catch (_) {
      // Aucun compteur fictif en cas d'indisponibilité API.
    }
  }

  Future<void> _openNotifications() async {
    await Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => const VendorNotificationsScreen()),
    );
    if (mounted) _loadUnread();
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          colors: [Color(0xFF031D47), Color(0xFF043069)],
          begin: Alignment.centerLeft,
          end: Alignment.centerRight,
        ),
      ),
      child: Stack(
        children: [
          Positioned.fill(
            child: IgnorePointer(
              child: Opacity(
                opacity: .12,
                child: Image.asset(
                  'assets/images/vendor_construction_blue.png',
                  fit: BoxFit.cover,
                  alignment: Alignment.centerRight,
                ),
              ),
            ),
          ),
          SafeArea(
            bottom: false,
            child: Padding(
              padding: const EdgeInsets.fromLTRB(22, 12, 22, 22),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Row(
                    children: [
                      if (widget.showBack) ...[
                        SizedBox(
                          width: 44,
                          height: 44,
                          child: Material(
                            color: Colors.white.withValues(alpha: .08),
                            borderRadius: BorderRadius.circular(12),
                            child: InkWell(
                              borderRadius: BorderRadius.circular(12),
                              onTap:
                                  widget.onBack ??
                                  () => Navigator.maybePop(context),
                              child: const Icon(
                                Icons.arrow_back_ios_new_rounded,
                                color: Colors.white,
                                size: 22,
                              ),
                            ),
                          ),
                        ),
                        const SizedBox(width: 12),
                      ],
                      const Expanded(
                        child: Align(
                          alignment: Alignment.centerLeft,
                          child: OrderBrandLockup(),
                        ),
                      ),
                      if (widget.showNotifications)
                        Stack(
                          clipBehavior: Clip.none,
                          children: [
                            IconButton(
                              onPressed: _openNotifications,
                              icon: const Icon(
                                Icons.notifications_none_rounded,
                                color: Colors.white,
                                size: 31,
                              ),
                              tooltip: 'Notifications',
                            ),
                            if (_unread > 0)
                              Positioned(
                                right: -2,
                                top: -2,
                                child: Container(
                                  constraints: const BoxConstraints(
                                    minWidth: 22,
                                    minHeight: 22,
                                  ),
                                  padding: const EdgeInsets.symmetric(
                                    horizontal: 5,
                                  ),
                                  alignment: Alignment.center,
                                  decoration: const BoxDecoration(
                                    color: orderOrange,
                                    shape: BoxShape.circle,
                                  ),
                                  child: Text(
                                    _unread > 99 ? '99+' : '$_unread',
                                    style: const TextStyle(
                                      color: Colors.white,
                                      fontSize: 10.5,
                                      fontWeight: FontWeight.w900,
                                    ),
                                  ),
                                ),
                              ),
                          ],
                        ),
                    ],
                  ),
                  const SizedBox(height: 13),
                  Row(
                    crossAxisAlignment: widget.titleTrailing != null
                        ? CrossAxisAlignment.start
                        : CrossAxisAlignment.end,
                    children: [
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              widget.title,
                              style: const TextStyle(
                                color: Colors.white,
                                fontSize: 29,
                                height: 1.08,
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                            const SizedBox(height: 7),
                            Text(
                              widget.subtitle,
                              style: const TextStyle(
                                color: Color(0xFFF2F6FB),
                                fontSize: 15.5,
                                height: 1.3,
                              ),
                            ),
                          ],
                        ),
                      ),
                      if (widget.titleTrailing != null) ...[
                        const SizedBox(width: 12),
                        widget.titleTrailing!,
                      ] else if ((widget.orderNumber ?? '').trim().isNotEmpty) ...[
                        const SizedBox(width: 12),
                        Container(
                          constraints: const BoxConstraints(maxWidth: 220),
                          padding: const EdgeInsets.symmetric(
                            horizontal: 14,
                            vertical: 10,
                          ),
                          decoration: BoxDecoration(
                            color: const Color(
                              0xFF0F559F,
                            ).withValues(alpha: .72),
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.end,
                            children: [
                              Text(
                                widget.orderNumber!,
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: const TextStyle(
                                  color: Colors.white,
                                  fontSize: 13.5,
                                  fontWeight: FontWeight.w900,
                                ),
                              ),
                              if ((widget.orderDate ?? '').trim().isNotEmpty)
                                Text(
                                  widget.orderDate!,
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: const TextStyle(
                                    color: Color(0xFFD9E8FA),
                                    fontSize: 11.5,
                                  ),
                                ),
                            ],
                          ),
                        ),
                      ],
                    ],
                  ),
                  if (widget.bottom != null) ...[
                    const SizedBox(height: 20),
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

class OrderSectionTitle extends StatelessWidget {
  const OrderSectionTitle(this.title, {super.key, this.trailing});
  final String title;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(
          child: Text(
            title,
            style: const TextStyle(
              color: orderText,
              fontSize: 19,
              fontWeight: FontWeight.w900,
            ),
          ),
        ),
        if (trailing != null) trailing!,
      ],
    );
  }
}

class OrderCard extends StatelessWidget {
  const OrderCard({
    super.key,
    required this.child,
    this.padding = const EdgeInsets.all(16),
    this.color = Colors.white,
  });
  final Widget child;
  final EdgeInsets padding;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: padding,
      decoration: BoxDecoration(
        color: color,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: orderBorder),
        boxShadow: const [
          BoxShadow(
            color: Color(0x08062962),
            blurRadius: 10,
            offset: Offset(0, 3),
          ),
        ],
      ),
      child: child,
    );
  }
}

class OrderMetric extends StatelessWidget {
  const OrderMetric({
    super.key,
    required this.icon,
    required this.value,
    required this.label,
    this.color = orderBlue,
  });
  final IconData icon;
  final Object value;
  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Container(
          width: 34,
          height: 34,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: color.withValues(alpha: .10),
            shape: BoxShape.circle,
          ),
          child: Icon(icon, color: color, size: 18),
        ),
        const SizedBox(width: 7),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                '$value',
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  color: orderText,
                  fontSize: 16,
                  fontWeight: FontWeight.w900,
                  height: 1,
                ),
              ),
              const SizedBox(height: 3),
              Text(
                label,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(color: orderMuted, fontSize: 10),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class OrderProgressStepper extends StatelessWidget {
  const OrderProgressStepper({
    super.key,
    required this.currentStep,
    this.timestamps = const <String, String>{},
    this.compact = false,
  });

  final int currentStep;
  final Map<String, String> timestamps;
  final bool compact;

  static const _labels = [
    'Commande reçue',
    'Préparation',
    'Expédiée',
    'Livrée',
  ];
  static const _keys = ['received', 'preparation', 'shipped', 'delivered'];

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final itemWidth = constraints.maxWidth / 4;
        final circleSize = compact ? 38.0 : 43.0;
        return SizedBox(
          height: compact ? 82 : 100,
          child: Stack(
            children: [
              Positioned(
                top: circleSize / 2 - 1,
                left: itemWidth / 2,
                right: itemWidth / 2,
                child: Row(
                  children: List.generate(3, (index) {
                    final completed = currentStep > index + 1;
                    return Expanded(
                      child: Container(
                        height: 2,
                        color: completed
                            ? orderOrange
                            : const Color(0xFFB7C2D3),
                      ),
                    );
                  }),
                ),
              ),
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: List.generate(4, (index) {
                  final number = index + 1;
                  final active = currentStep == number;
                  final done = currentStep > number;
                  final emphasized = active || done;
                  return SizedBox(
                    width: itemWidth,
                    child: Column(
                      children: [
                        Container(
                          width: circleSize,
                          height: circleSize,
                          alignment: Alignment.center,
                          decoration: BoxDecoration(
                            color: emphasized ? orderOrange : Colors.white,
                            shape: BoxShape.circle,
                            border: Border.all(
                              color: emphasized
                                  ? orderOrange
                                  : const Color(0xFF8EA3C5),
                              width: 1.6,
                            ),
                          ),
                          child: done
                              ? const Icon(
                                  Icons.check_rounded,
                                  color: Colors.white,
                                  size: 20,
                                )
                              : Text(
                                  '$number',
                                  style: TextStyle(
                                    color: emphasized
                                        ? Colors.white
                                        : orderMuted,
                                    fontSize: 17,
                                    fontWeight: FontWeight.w900,
                                  ),
                                ),
                        ),
                        const SizedBox(height: 7),
                        Text(
                          _labels[index],
                          textAlign: TextAlign.center,
                          maxLines: 2,
                          style: TextStyle(
                            color: emphasized ? orderOrange : orderMuted,
                            fontSize: compact ? 11.2 : 12.2,
                            fontWeight: emphasized
                                ? FontWeight.w800
                                : FontWeight.w600,
                            height: 1.15,
                          ),
                        ),
                        if (!compact &&
                            (timestamps[_keys[index]] ?? '').isNotEmpty) ...[
                          const SizedBox(height: 3),
                          Text(
                            timestamps[_keys[index]]!,
                            textAlign: TextAlign.center,
                            maxLines: 2,
                            style: const TextStyle(
                              color: orderMuted,
                              fontSize: 10.5,
                              height: 1.15,
                            ),
                          ),
                        ],
                      ],
                    ),
                  );
                }),
              ),
            ],
          ),
        );
      },
    );
  }
}

int orderWorkflowStep(Object? rawStatus, {Object? deliveryStatus}) {
  final delivery = '${deliveryStatus ?? ''}'.toLowerCase();
  if (delivery == 'delivered') return 4;
  if (['assigned', 'picked_up', 'in_transit', 'late'].contains(delivery)) {
    return 3;
  }
  final status = '${rawStatus ?? ''}'.toLowerCase();
  if (status == 'delivered') return 4;
  if (status == 'shipped') return 3;
  if (['accepted', 'preparing', 'ready'].contains(status)) return 2;
  return 1;
}

class OrderInfoTile extends StatelessWidget {
  const OrderInfoTile({
    super.key,
    required this.icon,
    required this.label,
    required this.value,
    this.trailing,
  });
  final IconData icon;
  final String label;
  final String value;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          width: 35,
          height: 35,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: orderSoftBlue,
            borderRadius: BorderRadius.circular(10),
          ),
          child: Icon(icon, color: orderText, size: 19),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                label,
                style: const TextStyle(color: orderMuted, fontSize: 11.5),
              ),
              const SizedBox(height: 2),
              Text(
                value.trim().isEmpty ? '—' : value,
                style: const TextStyle(
                  color: orderText,
                  fontSize: 13.5,
                  height: 1.25,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
          ),
        ),
        if (trailing != null) trailing!,
      ],
    );
  }
}

class OrderPrimaryButton extends StatelessWidget {
  const OrderPrimaryButton({
    super.key,
    required this.label,
    required this.onPressed,
    this.icon,
    this.loading = false,
    this.height = 54,
  });

  final String label;
  final VoidCallback? onPressed;
  final IconData? icon;
  final bool loading;
  final double height;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: height,
      width: double.infinity,
      child: DecoratedBox(
        decoration: BoxDecoration(
          gradient: const LinearGradient(
            colors: [Color(0xFFFF6C00), Color(0xFFFF4F00)],
          ),
          borderRadius: BorderRadius.circular(11),
        ),
        child: Material(
          color: Colors.transparent,
          child: InkWell(
            borderRadius: BorderRadius.circular(11),
            onTap: loading ? null : onPressed,
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 18),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  if (loading)
                    const SizedBox.square(
                      dimension: 19,
                      child: CircularProgressIndicator(
                        color: Colors.white,
                        strokeWidth: 2.2,
                      ),
                    )
                  else if (icon != null)
                    Icon(icon, color: Colors.white, size: 22),
                  if (loading || icon != null) const SizedBox(width: 10),
                  Flexible(
                    child: Text(
                      label,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 15.5,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ),
                  if (!loading && icon == null) ...[
                    const SizedBox(width: 10),
                    const Icon(
                      Icons.arrow_forward_rounded,
                      color: Colors.white,
                      size: 23,
                    ),
                  ],
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class OrderSecondaryButton extends StatelessWidget {
  const OrderSecondaryButton({
    super.key,
    required this.label,
    required this.onPressed,
    this.icon = Icons.arrow_back_rounded,
    this.height = 54,
  });
  final String label;
  final VoidCallback? onPressed;
  final IconData icon;
  final double height;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: height,
      width: double.infinity,
      child: OutlinedButton.icon(
        onPressed: onPressed,
        icon: Icon(icon, color: orderText),
        label: Text(
          label,
          style: const TextStyle(
            color: orderText,
            fontWeight: FontWeight.w800,
            fontSize: 15,
          ),
        ),
        style: OutlinedButton.styleFrom(
          backgroundColor: Colors.white,
          side: const BorderSide(color: Color(0xFF9FB6D8)),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(11),
          ),
        ),
      ),
    );
  }
}

class OrderBottomBar extends StatelessWidget {
  const OrderBottomBar({super.key});

  @override
  Widget build(BuildContext context) =>
      const VendorBottomBar(selected: VendorTab.orders);
}

Map<String, dynamic> orderMap(Object? raw) =>
    raw is Map ? Map<String, dynamic>.from(raw) : <String, dynamic>{};
List<dynamic> orderList(Object? raw) =>
    raw is List ? List<dynamic>.from(raw) : <dynamic>[];

String orderDateOnly(Object? raw) {
  final value = DateTime.tryParse('${raw ?? ''}');
  if (value == null) return '—';
  final local = value.toLocal();
  const months = [
    'janv.',
    'févr.',
    'mars',
    'avr.',
    'mai',
    'juin',
    'juil.',
    'août',
    'sept.',
    'oct.',
    'nov.',
    'déc.',
  ];
  return '${local.day} ${months[local.month - 1]} ${local.year}';
}

String orderTimeOnly(Object? raw) {
  final value = DateTime.tryParse('${raw ?? ''}');
  if (value == null) return '';
  final local = value.toLocal();
  String two(int v) => v.toString().padLeft(2, '0');
  return '${two(local.hour)}:${two(local.minute)}';
}

String orderHumanDate(Object? raw) {
  final value = DateTime.tryParse('${raw ?? ''}');
  if (value == null) return '—';
  final local = value.toLocal();
  final now = DateTime.now();
  final today = DateTime(now.year, now.month, now.day);
  final day = DateTime(local.year, local.month, local.day);
  final diff = today.difference(day).inDays;
  final time = orderTimeOnly(raw);
  if (diff == 0) return "Aujourd'hui, $time";
  if (diff == 1) return 'Hier, $time';
  return '${orderDateOnly(raw)}, $time';
}

String orderMoney(Object? value) => money(value);

String orderCompactNumber(Object? value) {
  final n = int.tryParse('${value ?? 0}') ?? 0;
  return '$n';
}

class TrueDataNotice extends StatelessWidget {
  const TrueDataNotice({super.key, required this.message});
  final String message;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      decoration: BoxDecoration(
        color: const Color(0xFFEAF3FF),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        children: [
          const Icon(Icons.info_rounded, color: orderBlue, size: 22),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              message,
              style: const TextStyle(
                color: orderText,
                fontSize: 12.5,
                height: 1.35,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class OrderProductImage extends StatelessWidget {
  const OrderProductImage({super.key, required this.url, this.size = 82});
  final Object? url;
  final double size;

  @override
  Widget build(BuildContext context) {
    final value = '${url ?? ''}'.trim();
    return Container(
      width: size,
      height: size,
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        color: const Color(0xFFF4F7FA),
        borderRadius: BorderRadius.circular(12),
      ),
      child: value.isEmpty
          ? const Icon(Icons.inventory_2_outlined, color: orderText, size: 34)
          : Image.network(
              value,
              fit: BoxFit.contain,
              errorBuilder: (_, __, ___) => const Icon(
                Icons.inventory_2_outlined,
                color: orderText,
                size: 34,
              ),
            ),
    );
  }
}

class TrackingMapPreview extends StatelessWidget {
  const TrackingMapPreview({
    super.key,
    this.pickupLat,
    this.pickupLng,
    this.deliveryLat,
    this.deliveryLng,
    this.driverLat,
    this.driverLng,
  });

  final double? pickupLat;
  final double? pickupLng;
  final double? deliveryLat;
  final double? deliveryLng;
  final double? driverLat;
  final double? driverLng;

  String? get _mapUrl {
    final points = <List<double>>[];
    if (pickupLat != null && pickupLng != null) {
      points.add([pickupLat!, pickupLng!]);
    }
    if (deliveryLat != null && deliveryLng != null) {
      points.add([deliveryLat!, deliveryLng!]);
    }
    if (driverLat != null && driverLng != null) {
      points.add([driverLat!, driverLng!]);
    }
    if (points.isEmpty) return null;
    final lat = points.map((p) => p[0]).reduce((a, b) => a + b) / points.length;
    final lng = points.map((p) => p[1]).reduce((a, b) => a + b) / points.length;
    final markers = <String>[];
    if (pickupLat != null && pickupLng != null) {
      markers.add('$pickupLat,$pickupLng,blue-pushpin');
    }
    if (deliveryLat != null && deliveryLng != null) {
      markers.add('$deliveryLat,$deliveryLng,red-pushpin');
    }
    if (driverLat != null && driverLng != null) {
      markers.add('$driverLat,$driverLng,lightblue1');
    }
    final markerQuery = markers.map(Uri.encodeQueryComponent).join('|');
    return 'https://staticmap.openstreetmap.de/staticmap.php?center=$lat,$lng&zoom=12&size=900x330&maptype=mapnik&markers=$markerQuery';
  }

  @override
  Widget build(BuildContext context) {
    final url = _mapUrl;
    return Container(
      height: 220,
      width: double.infinity,
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        color: const Color(0xFFEAF1F8),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: orderBorder),
      ),
      child: Stack(
        fit: StackFit.expand,
        children: [
          if (url != null)
            Image.network(
              url,
              fit: BoxFit.cover,
              errorBuilder: (_, __, ___) => const _MapFallback(),
            )
          else
            const _MapFallback(),
          if (driverLat != null && driverLng != null)
            Center(
              child: Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 12,
                  vertical: 7,
                ),
                decoration: BoxDecoration(
                  color: orderOrange,
                  borderRadius: BorderRadius.circular(8),
                ),
                child: const Text(
                  'Le livreur est ici',
                  style: TextStyle(
                    color: Colors.white,
                    fontSize: 12,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

class _MapFallback extends StatelessWidget {
  const _MapFallback();

  @override
  Widget build(BuildContext context) {
    return CustomPaint(painter: _MapFallbackPainter());
  }
}

class _MapFallbackPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final background = Paint()..color = const Color(0xFFEAF1F8);
    canvas.drawRect(Offset.zero & size, background);

    final road = Paint()
      ..color = Colors.white
      ..strokeWidth = 4
      ..style = PaintingStyle.stroke;
    final minor = Paint()
      ..color = const Color(0xFFD1DCE9)
      ..strokeWidth = 1.4
      ..style = PaintingStyle.stroke;

    for (var i = -2; i < 10; i++) {
      final y = size.height * (i / 7);
      canvas.drawLine(
        Offset(0, y),
        Offset(size.width, y + size.height * .32),
        minor,
      );
    }
    for (var i = -2; i < 12; i++) {
      final x = size.width * (i / 9);
      canvas.drawLine(
        Offset(x, 0),
        Offset(x - size.width * .20, size.height),
        minor,
      );
    }

    final path = Path()
      ..moveTo(size.width * .08, size.height * .58)
      ..cubicTo(
        size.width * .28,
        size.height * .48,
        size.width * .42,
        size.height * .72,
        size.width * .55,
        size.height * .57,
      )
      ..cubicTo(
        size.width * .70,
        size.height * .40,
        size.width * .82,
        size.height * .48,
        size.width * .93,
        size.height * .30,
      );
    road.color = const Color(0xFF0C65DD);
    road.strokeWidth = 5;
    canvas.drawPath(path, road);
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}

class OrderAdaptiveTwoColumns extends StatelessWidget {
  const OrderAdaptiveTwoColumns({
    super.key,
    required this.left,
    required this.right,
    this.gap = 14,
    this.breakpoint = 720,
  });
  final Widget left;
  final Widget right;
  final double gap;
  final double breakpoint;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        if (constraints.maxWidth >= breakpoint) {
          return Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(child: left),
              SizedBox(width: gap),
              Expanded(child: right),
            ],
          );
        }
        return Column(
          children: [
            left,
            SizedBox(height: gap),
            right,
          ],
        );
      },
    );
  }
}

class OrderResponsiveBody extends StatelessWidget {
  const OrderResponsiveBody({
    super.key,
    required this.child,
    this.padding = const EdgeInsets.symmetric(horizontal: 22),
  });
  final Widget child;
  final EdgeInsets padding;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 980),
        child: Padding(padding: padding, child: child),
      ),
    );
  }
}

class OrderSheet extends StatelessWidget {
  const OrderSheet({super.key, required this.child, this.topRadius = 28});
  final Widget child;
  final double topRadius;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(topRadius)),
        boxShadow: const [
          BoxShadow(
            color: Color(0x1202173D),
            blurRadius: 18,
            offset: Offset(0, -5),
          ),
        ],
      ),
      child: child,
    );
  }
}

class OrderIconSquare extends StatelessWidget {
  const OrderIconSquare({
    super.key,
    required this.icon,
    this.color = orderBlue,
    this.size = 76,
  });
  final IconData icon;
  final Color color;
  final double size;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: size,
      height: size,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: color.withValues(alpha: .08),
        borderRadius: BorderRadius.circular(15),
      ),
      child: Icon(icon, color: color, size: math.min(40, size * .48)),
    );
  }
}
