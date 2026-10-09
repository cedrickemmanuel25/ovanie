import 'dart:math' as math;

import 'package:flutter/material.dart';
import '../../core/ui/vendor_mockup_ui.dart';

import '../../core/utils/formatters.dart';
import '../shell/vendor_tabs.dart';

const orderOrange = Mk.orange;
const orderNavy = Mk.navy;
const orderBlue = Color(0xFF1456D9);
const orderText = Mk.navy;
const orderMuted = Mk.muted;
const orderBorder = Color(0xFFE4E9F1);
const orderSoftBlue = Mk.softBlue;
const orderSoftOrange = Mk.softOrange;
const orderSoftGreen = Color(0xFFE6F6EC);
const orderGreen = Color(0xFF12894A);
const orderPage = Color(0xFFF5F7FB);

/// États de paiement pour lesquels l'argent est acquis.
bool orderIsPaid(Object? status) => const <String>{
      'paid',
      'escrow_held',
      'verified',
      'commission_paid',
      'released_to_vendor',
    }.contains('${status ?? ''}'.trim().toLowerCase());

/// Numéro de commande précédé de « # ».
String orderNumberLabel(Object? raw) {
  final number = '${raw ?? ''}'.trim();
  if (number.isEmpty) return 'Commande';
  return number.startsWith('#') ? number : '#$number';
}

/// « Aujourd'hui », « Hier » ou la date courte, sans l'heure.
String orderDayLabel(Object? raw) {
  final value = DateTime.tryParse('${raw ?? ''}');
  if (value == null) return '';
  final local = value.toLocal();
  final now = DateTime.now();
  final diff = DateTime(now.year, now.month, now.day).difference(DateTime(local.year, local.month, local.day)).inDays;
  if (diff == 0) return "Aujourd'hui";
  if (diff == 1) return 'Hier';
  return orderShortDate(raw);
}

/// Date au format 12/09/2026.
String orderShortDate(Object? raw) {
  final value = DateTime.tryParse('${raw ?? ''}');
  if (value == null) return '';
  final local = value.toLocal();
  String two(int v) => v.toString().padLeft(2, '0');
  return '${two(local.day)}/${two(local.month)}/${local.year}';
}

/// Date et heure au format « 12/09/2026 à 10:24 ».
String orderShortDateTime(Object? raw) {
  final date = orderShortDate(raw);
  if (date.isEmpty) return '';
  final time = orderTimeOnly(raw);
  return time.isEmpty ? date : '$date à $time';
}

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
      padding: EdgeInsets.symmetric(horizontal: compact ? 11 : 14, vertical: compact ? 4 : 6),
      decoration: BoxDecoration(color: orderStatusBackground(status), borderRadius: BorderRadius.circular(99)),
      child: Text(
        label ?? orderStatusLabel(status),
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
        style: TextStyle(color: color, fontSize: compact ? 12 : 13, fontWeight: FontWeight.w700),
      ),
    );
  }
}

/// Pastille de paiement : verte « Payée », grise tant que le paiement n'est pas acquis.
class OrderPaymentPill extends StatelessWidget {
  const OrderPaymentPill({super.key, required this.status, this.label, this.withIcon = false});
  final Object? status;
  final String? label;
  final bool withIcon;

  @override
  Widget build(BuildContext context) {
    final paid = orderIsPaid(status);
    final color = paid ? orderGreen : const Color(0xFF5B6478);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 4),
      decoration: BoxDecoration(
        color: paid ? orderSoftGreen : const Color(0xFFE9ECF1),
        borderRadius: BorderRadius.circular(99),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (withIcon && paid) ...[
            const Icon(Icons.check_circle_rounded, color: orderGreen, size: 17),
            const SizedBox(width: 6),
          ],
          Text(
            label ?? (paid ? 'Payée' : 'En attente'),
            style: TextStyle(color: color, fontSize: 12, fontWeight: FontWeight.w700),
          ),
        ],
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
  @override
  Widget build(BuildContext context) {
    return MkTheme(
      lightStatusBar: false,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          MkPageHeader(
            title: widget.title,
            subtitle: widget.subtitle.trim().isEmpty ? null : widget.subtitle,
            onBack: widget.onBack,
            trailing: widget.titleTrailing,
          ),
          if (widget.bottom != null)
            Container(
              color: Colors.white,
              padding: const EdgeInsets.fromLTRB(12, 0, 12, 10),
              child: widget.bottom,
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
            style: const TextStyle(fontFamily: Mk.font, color: Mk.navy, fontSize: 17, fontWeight: FontWeight.w800, letterSpacing: -.2),
          ),
        ),
        if (trailing != null) trailing!,
      ],
    );
  }
}

/// Petit titre en capitales espacées, en tête d'une carte.
class OrderCaption extends StatelessWidget {
  const OrderCaption(this.text, {super.key});
  final String text;

  @override
  Widget build(BuildContext context) {
    return Text(
      text.toUpperCase(),
      style: const TextStyle(fontFamily: Mk.font, color: Mk.muted, fontSize: 11.5, fontWeight: FontWeight.w700, letterSpacing: 1.6),
    );
  }
}

class OrderCard extends StatelessWidget {
  const OrderCard({
    super.key,
    required this.child,
    this.padding = const EdgeInsets.all(14),
    this.color = Colors.white,
    this.borderColor,
  });
  final Widget child;
  final EdgeInsets padding;
  final Color color;
  final Color? borderColor;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: padding,
      decoration: BoxDecoration(
        color: color,
        borderRadius: BorderRadius.circular(14),
        border: borderColor == null ? null : Border.all(color: borderColor!, width: 1.2),
      ),
      child: DefaultTextStyle.merge(
        style: const TextStyle(fontFamily: Mk.font, letterSpacing: 0),
        child: child,
      ),
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

/// Une étape de la frise de suivi.
class OrderStep {
  const OrderStep(this.label, {this.done = false, this.active = false, this.icon, this.caption});
  final String label;
  final bool done;
  final bool active;

  /// Pictogramme de l'étape en cours ; à défaut, son numéro.
  final IconData? icon;

  /// Précision sous le libellé (date, « En attente »).
  final String? caption;
}

/// Frise horizontale : étapes faites en vert, étape en cours en orange, suivantes en gris.
class OrderStepsRow extends StatelessWidget {
  const OrderStepsRow({super.key, required this.steps});
  final List<OrderStep> steps;

  @override
  Widget build(BuildContext context) {
    const circle = 36.0;
    return DefaultTextStyle.merge(
      style: const TextStyle(fontFamily: Mk.font, letterSpacing: 0),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          for (var i = 0; i < steps.length; i++)
            Expanded(
              child: Semantics(
                label: '${steps[i].label} : ${steps[i].done ? 'fait' : (steps[i].active ? 'en cours' : 'à venir')}',
                excludeSemantics: true,
                child: Column(
                  children: [
                    SizedBox(
                      height: circle + 6,
                      child: Row(
                        children: [
                          Expanded(
                            child: Container(
                              height: 2.5,
                              color: i == 0
                                  ? Colors.transparent
                                  : (steps[i].done || steps[i].active ? orderGreen : const Color(0xFFD5DAE4)),
                            ),
                          ),
                          Container(
                            width: steps[i].active ? circle + 4 : circle,
                            height: steps[i].active ? circle + 4 : circle,
                            alignment: Alignment.center,
                            decoration: BoxDecoration(
                              shape: BoxShape.circle,
                              color: steps[i].done
                                  ? orderGreen
                                  : (steps[i].active ? Mk.orange : const Color(0xFFE6E9F0)),
                              boxShadow: steps[i].active
                                  ? const [BoxShadow(color: Color(0x59FF6A00), blurRadius: 10)]
                                  : null,
                            ),
                            child: steps[i].done
                                ? const Icon(Icons.check_rounded, color: Colors.white, size: 22)
                                : (steps[i].active && steps[i].icon != null
                                    ? Icon(steps[i].icon, color: Colors.white, size: 20)
                                    : Text(
                                        '${i + 1}',
                                        style: TextStyle(
                                          color: steps[i].active ? Colors.white : Mk.icon,
                                          fontSize: 15,
                                          fontWeight: FontWeight.w700,
                                        ),
                                      )),
                          ),
                          Expanded(
                            child: Container(
                              height: 2.5,
                              color: i == steps.length - 1
                                  ? Colors.transparent
                                  : (steps[i].done ? orderGreen : const Color(0xFFD5DAE4)),
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 4),
                    Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 2),
                      child: Text(
                        steps[i].label,
                        textAlign: TextAlign.center,
                        maxLines: 2,
                        style: TextStyle(
                          color: steps[i].active ? const Color(0xFFE85400) : (steps[i].done ? Mk.navy : Mk.muted),
                          fontSize: 12,
                          height: 1.2,
                          fontWeight: steps[i].active ? FontWeight.w700 : FontWeight.w500,
                        ),
                      ),
                    ),
                    if ((steps[i].caption ?? '').isNotEmpty)
                      Padding(
                        padding: const EdgeInsets.only(top: 2, left: 2, right: 2),
                        child: Text(
                          steps[i].caption!,
                          textAlign: TextAlign.center,
                          maxLines: 2,
                          style: const TextStyle(color: Mk.muted, fontSize: 11, height: 1.2),
                        ),
                      ),
                  ],
                ),
              ),
            ),
        ],
      ),
    );
  }
}

/// Frise des quatre étapes du traitement d'une commande.
class OrderProgressStepper extends StatelessWidget {
  const OrderProgressStepper({
    super.key,
    required this.currentStep,
    this.timestamps = const <String, String>{},
    this.compact = false,
    this.lastLabel = 'Livrée',
  });

  final int currentStep;
  final Map<String, String> timestamps;
  final bool compact;

  /// Libellé de la dernière étape : « Livrée », ou « Remise à OVANIE » en logistique OVANIE.
  final String lastLabel;

  static const _keys = ['received', 'preparation', 'shipped', 'delivered'];

  @override
  Widget build(BuildContext context) {
    final labels = ['Commande reçue', 'Préparation', 'Expédiée', lastLabel];
    return OrderStepsRow(
      steps: [
        for (var i = 0; i < 4; i++)
          OrderStep(
            labels[i],
            done: currentStep > i + 1,
            active: currentStep == i + 1,
            caption: compact ? null : timestamps[_keys[i]],
          ),
      ],
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
    this.secondary,
    this.below,
  });
  final IconData icon;
  final String label;
  final String value;
  final Widget? trailing;

  /// Seconde ligne sous la valeur (téléphone du client).
  final String? secondary;

  /// Élément placé sous le texte (bouton « Voir sur la carte »).
  final Widget? below;

  @override
  Widget build(BuildContext context) {
    return DefaultTextStyle.merge(
      style: const TextStyle(fontFamily: Mk.font, letterSpacing: 0),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 42,
            height: 42,
            alignment: Alignment.center,
            decoration: BoxDecoration(color: const Color(0xFFEEF2F8), borderRadius: BorderRadius.circular(12)),
            child: Icon(icon, color: Mk.navy, size: 22),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(label, style: const TextStyle(color: Mk.muted, fontSize: 12, fontWeight: FontWeight.w500)),
                const SizedBox(height: 1),
                Text(
                  value.trim().isEmpty ? '—' : value,
                  style: const TextStyle(color: Mk.navy, fontSize: 14, height: 1.3, fontWeight: FontWeight.w700),
                ),
                if ((secondary ?? '').trim().isNotEmpty)
                  Text(secondary!, style: const TextStyle(color: Mk.navy, fontSize: 13.5, height: 1.3, fontWeight: FontWeight.w500)),
                if (below != null) Padding(padding: const EdgeInsets.only(top: 6), child: below),
              ],
            ),
          ),
          if (trailing != null) trailing!,
        ],
      ),
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
    this.height = 48,
  });

  final String label;
  final VoidCallback? onPressed;
  final IconData? icon;
  final bool loading;
  final double height;

  @override
  Widget build(BuildContext context) =>
      MkBarButton(label: label, icon: icon, filled: true, loading: loading, height: height, onTap: onPressed);
}

class OrderSecondaryButton extends StatelessWidget {
  const OrderSecondaryButton({
    super.key,
    required this.label,
    required this.onPressed,
    this.icon = Icons.arrow_back_rounded,
    this.height = 48,
    this.color = Mk.orange,
  });
  final String label;
  final VoidCallback? onPressed;
  final IconData icon;
  final double height;
  final Color color;

  @override
  Widget build(BuildContext context) => MkBarButton(label: label, icon: icon, color: color, height: height, onTap: onPressed);
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
              style: const TextStyle(fontFamily: Mk.font, color: Mk.muted, fontSize: 12.5, height: 1.35, fontWeight: FontWeight.w500),
            ),
          ),
        ],
      ),
    );
  }
}

class OrderProductImage extends StatelessWidget {
  const OrderProductImage({super.key, required this.url, this.size = 72});
  final Object? url;
  final double size;

  @override
  Widget build(BuildContext context) {
    final value = '${url ?? ''}'.trim();
    const placeholder = Icon(Icons.inventory_2_outlined, color: Mk.hint, size: 30);
    return Container(
      width: size,
      height: size,
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        color: const Color(0xFFF7F8FA),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: orderBorder),
      ),
      child: value.isEmpty || value == 'null'
          ? placeholder
          : Image.network(value, fit: BoxFit.cover, errorBuilder: (_, __, ___) => placeholder),
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
  const OrderSheet({super.key, required this.child, this.topRadius = 0});
  final Widget child;
  final double topRadius;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      color: orderPage,
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

/// Situation OVANIE Logistics de la commande, identique à l'espace vendeur
/// web : livreur réservé ou recherche en cours, avec le message de l'API.
class OrderLogisticsBanner extends StatelessWidget {
  const OrderLogisticsBanner({super.key, required this.order});

  final Map<String, dynamic> order;

  @override
  Widget build(BuildContext context) {
    final tracking = orderMap(order['tracking']);
    final provider = '${order['delivery_provider'] ?? tracking['provider'] ?? ''}'.toLowerCase();
    final message = '${tracking['assignment_message'] ?? ''}'.trim();
    if (provider != 'ovanie' || message.isEmpty) return const SizedBox.shrink();

    final reserved = tracking['driver_reserved'] == true;
    final driver = orderMap(tracking['driver']);
    final driverName = '${driver['name'] ?? ''}'.trim();
    final title = reserved ? 'Mission réservée par un livreur partenaire' : 'Livraison OVANIE Logistics';

    return Container(
      width: double.infinity,
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: reserved ? const Color(0xFFEFF5FF) : const Color(0xFFFFF6EC),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: reserved ? const Color(0xFFBFD4F6) : const Color(0xFFFFD9B3)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(
            reserved ? Icons.how_to_reg_rounded : Icons.search_rounded,
            color: reserved ? const Color(0xFF16437C) : orderOrange,
            size: 24,
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(color: orderText, fontSize: 14, fontWeight: FontWeight.w800),
                ),
                if (reserved && driverName.isNotEmpty) ...[
                  const SizedBox(height: 3),
                  Text(
                    '$driverName a réservé la mission.',
                    style: const TextStyle(color: orderText, fontSize: 13, fontWeight: FontWeight.w600),
                  ),
                ],
                const SizedBox(height: 3),
                Text(
                  message,
                  style: const TextStyle(color: orderMuted, fontSize: 12.5, height: 1.35),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// Liste de contrôle affichée au moment de valider : l'écran reste court et
/// la confirmation n'est possible qu'une fois tous les points cochés.
Future<bool> showOrderChecklist(
  BuildContext context, {
  required String title,
  required List<String> items,
  required String confirmLabel,
}) async {
  final checked = List<bool>.filled(items.length, false);
  final result = await showModalBottomSheet<bool>(
    context: context,
    isScrollControlled: true,
    useSafeArea: true,
    backgroundColor: Colors.white,
    shape: const RoundedRectangleBorder(
      borderRadius: BorderRadius.vertical(top: Radius.circular(18)),
    ),
    builder: (sheetContext) => StatefulBuilder(
      builder: (context, setSheet) {
        final ready = checked.every((value) => value);
        return Padding(
          padding: const EdgeInsets.fromLTRB(20, 18, 20, 16),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                title,
                style: const TextStyle(
                  color: orderText,
                  fontSize: 17,
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(height: 8),
              for (var i = 0; i < items.length; i++)
                CheckboxListTile(
                  value: checked[i],
                  onChanged: (v) => setSheet(() => checked[i] = v ?? false),
                  contentPadding: EdgeInsets.zero,
                  controlAffinity: ListTileControlAffinity.leading,
                  activeColor: orderOrange,
                  title: Text(
                    items[i],
                    style: const TextStyle(color: orderText, fontSize: 14.5),
                  ),
                ),
              const SizedBox(height: 12),
              SizedBox(
                height: 50,
                child: FilledButton(
                  onPressed: ready ? () => Navigator.pop(sheetContext, true) : null,
                  style: FilledButton.styleFrom(
                    backgroundColor: orderOrange,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(10),
                    ),
                  ),
                  child: Text(
                    confirmLabel,
                    style: const TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
              ),
            ],
          ),
        );
      },
    ),
  );
  return result == true;
}
