import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../app/theme.dart';
import '../../../core/network/api_client.dart';
import '../../../core/utils/formatters.dart';
import '../../payments/presentation/payment_ui.dart';
import '../../../core/platform/external_url_launcher.dart';
import '../../orders/presentation/order_detail_screen.dart';
import '../../support/presentation/support_center_screen.dart';
import '../data/delivery_tracking_repository.dart';
import '../domain/delivery_tracking_model.dart';
import 'live_delivery_map.dart';

class ClientDeliveryTrackingScreen extends StatefulWidget {
  final int orderId;
  final String initialOrderNumber;

  const ClientDeliveryTrackingScreen({super.key, required this.orderId, this.initialOrderNumber = ''});

  @override
  State<ClientDeliveryTrackingScreen> createState() => _ClientDeliveryTrackingScreenState();
}

class _ClientDeliveryTrackingScreenState extends State<ClientDeliveryTrackingScreen> {
  final _repository = const DeliveryTrackingRepository();
  ClientDeliveryTracking? _tracking;
  bool _loading = true;
  String? _error;
  String? _selectedTrackingKey;
  Timer? _timer;

  @override
  void initState() {
    super.initState();
    _load(initial: true);
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  Future<void> _load({bool initial = false}) async {
    if (!mounted) return;
    setState(() {
      if (initial) _loading = true;
      _error = null;
    });
    try {
      final tracking = await _repository.fetchOrderTracking(widget.orderId);
      if (!mounted) return;
      setState(() {
        _tracking = tracking;
        _loading = false;
        _selectedTrackingKey = _resolveSelection(tracking);
      });
      _schedule(tracking?.pollSeconds ?? 12);
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _loading = false;
        _error = ApiClient.friendlyError(error);
      });
      _schedule(15);
    }
  }

  String? _resolveSelection(ClientDeliveryTracking? tracking) {
    if (tracking == null || tracking.shipments.isEmpty) return null;
    if (_selectedTrackingKey != null &&
        tracking.shipments.any((shipment) => shipment.trackingKey == _selectedTrackingKey)) {
      return _selectedTrackingKey;
    }
    for (final shipment in tracking.shipments) {
      if (!shipment.isFinished) return shipment.trackingKey;
    }
    return tracking.shipments.first.trackingKey;
  }

  ClientShipmentTracking? get _selectedShipment {
    final tracking = _tracking;
    if (tracking == null || tracking.shipments.isEmpty) return null;
    for (final shipment in tracking.shipments) {
      if (shipment.trackingKey == _selectedTrackingKey) return shipment;
    }
    return tracking.shipments.first;
  }

  void _schedule(int seconds) {
    _timer?.cancel();
    _timer = Timer(Duration(seconds: seconds.clamp(5, 60).toInt()), () {
      if (mounted) _load();
    });
  }

  @override
  Widget build(BuildContext context) {
    final Widget body;
    if (_loading) {
      body = const Center(child: CircularProgressIndicator(color: OvanieColors.orange));
    } else if (_error != null && _tracking == null) {
      body = SafeArea(
        child: Column(
          children: [
            Align(
              alignment: Alignment.centerLeft,
              child: Padding(
                padding: const EdgeInsets.all(12),
                child: _MapButton(icon: Icons.arrow_back_rounded, onTap: () => Navigator.of(context).maybePop()),
              ),
            ),
            Expanded(
              child: _TrackingError(message: _error!, onRetry: () => _load(initial: true)),
            ),
          ],
        ),
      );
    } else {
      body = _buildContent();
    }
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.dark.copyWith(statusBarColor: Colors.transparent),
      child: Scaffold(backgroundColor: Colors.white, body: body),
    );
  }

  _TrackingStage _stageOf(ClientShipmentTracking? shipment) {
    if (shipment == null) return _TrackingStage.preparing;
    if (shipment.isFinished) return _TrackingStage.delivered;
    if (shipment.isArrived) return _TrackingStage.arrived;
    if (shipment.isInTransit) return _TrackingStage.enRoute;
    if (shipment.deliveryStatus == 'waiting_driver') return _TrackingStage.searching;
    if (shipment.hasActualAssignment ||
        shipment.isCollecting ||
        shipment.isReadyForPickup ||
        shipment.isWaitingVendor ||
        const {'picked_up', 'assigned'}.contains(shipment.deliveryStatus)) {
      return _TrackingStage.collecting;
    }
    return _TrackingStage.preparing;
  }

  Widget _buildContent() {
    final tracking = _tracking;
    final shipment = _selectedShipment;
    final stage = _stageOf(shipment);
    final order = tracking?.order;
    final orderNumber = tracking?.orderNumber.trim().isNotEmpty == true
        ? tracking!.orderNumber
        : widget.initialOrderNumber;
    final top = MediaQuery.paddingOf(context).top;
    final height = MediaQuery.sizeOf(context).height;
    final preparing = stage == _TrackingStage.preparing;
    final headerHeight = height * (preparing ? .31 : .27);
    final orderAddress = [
      order?.address ?? '',
      order?.quartier ?? '',
      order?.commune ?? '',
    ].map((part) => part.trim()).where((part) => part.isNotEmpty).toSet().join(', ');
    final shipmentAddress = shipment?.destinationLabel.trim() ?? '';
    final destination = orderAddress.length > shipmentAddress.length ? orderAddress : shipmentAddress;
    final driverExpected = stage == _TrackingStage.enRoute || stage == _TrackingStage.arrived;
    final update = shipment?.lastUpdate ?? tracking?.lastUpdate;

    final (String title, String subtitle, Color subtitleColor) = switch (stage) {
      _TrackingStage.delivered => ('Commande livrée', 'Votre commande a été livrée', _trackGreen),
      _TrackingStage.arrived => ('Votre livreur est arrivé', 'Il vous attend à votre adresse', OvanieColors.orange),
      _TrackingStage.enRoute => (
        'Le livreur est en route vers votre adresse',
        shipment?.eta == null ? 'Heure d’arrivée en cours de calcul' : 'Arrivée ${_etaLabel(shipment!.eta)}',
        OvanieColors.orange,
      ),
      _TrackingStage.collecting => (
        'Un livreur prend en charge votre commande',
        'Collecte des articles en cours',
        OvanieColors.orange,
      ),
      _TrackingStage.searching => (
        'OVANIE recherche un livreur partenaire',
        'Recherche en cours pour la prise en charge',
        OvanieColors.orange,
      ),
      _TrackingStage.preparing => (
        'Votre commande est en préparation',
        'Le suivi s’affichera dès la prise en charge par le livreur.',
        _trackMuted,
      ),
    };
    final meta = stage == _TrackingStage.delivered
        ? 'Commande $orderNumber  ·  livrée ${_deliveredLabel(update)}'
        : 'Commande $orderNumber  ·  mis à jour ${_relativeTime(update)}';

    return Stack(
      children: [
        Positioned(
          top: 0,
          left: 0,
          right: 0,
          height: preparing ? headerHeight + 30 : height,
          child: preparing
              ? Image.asset('assets/images/checkout/tracking_preparing.jpg', fit: BoxFit.cover)
              : LiveDeliveryMap(shipment: shipment, bottomInset: height - headerHeight),
        ),
        Positioned(
          top: top + 10,
          left: 14,
          child: _MapButton(icon: Icons.arrow_back_rounded, onTap: () => Navigator.of(context).maybePop()),
        ),
        if (driverExpected && shipment?.hasLiveMap != true)
          Positioned(
            top: top + 12,
            left: 76,
            right: 14,
            child: _PositionUnavailable(destination: destination),
          ),
        DraggableScrollableSheet(
          initialChildSize: 1 - headerHeight / height,
          minChildSize: .4,
          maxChildSize: .94,
          builder: (context, controller) => Container(
            decoration: const BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.vertical(top: Radius.circular(26)),
              boxShadow: [BoxShadow(color: Color(0x1F000000), blurRadius: 16, offset: Offset(0, -3))],
            ),
            child: RefreshIndicator(
              color: OvanieColors.orange,
              onRefresh: () => _load(),
              child: ListView(
                controller: controller,
                padding: const EdgeInsets.fromLTRB(18, 10, 18, 24),
                children: [
                  Center(
                    child: Container(
                      width: 46,
                      height: 5,
                      decoration: BoxDecoration(color: const Color(0xFFD0D6DF), borderRadius: BorderRadius.circular(3)),
                    ),
                  ),
                  const SizedBox(height: 16),
                  Text(
                    title,
                    style: const TextStyle(color: _trackNavy, fontSize: 25, height: 1.15, fontWeight: FontWeight.w900),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    subtitle,
                    style: TextStyle(
                      color: subtitleColor,
                      fontSize: subtitleColor == _trackMuted ? 16 : 18.5,
                      fontWeight: subtitleColor == _trackMuted ? FontWeight.w400 : FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(meta, style: const TextStyle(color: _trackMuted, fontSize: 15)),
                  const SizedBox(height: 20),
                  _TrackingSteps(stage: stage),
                  if ((tracking?.shipments.length ?? 0) > 1) ...[
                    const SizedBox(height: 10),
                    Wrap(
                      spacing: 8,
                      children: [
                        for (final item in tracking!.shipments)
                          ChoiceChip(
                            label: Text(
                              item.deliveryLabel.trim().isEmpty
                                  ? 'Livraison ${item.deliveryNumber}'
                                  : item.deliveryLabel,
                            ),
                            selected: item.trackingKey == _selectedTrackingKey,
                            showCheckmark: false,
                            onSelected: (_) => setState(() => _selectedTrackingKey = item.trackingKey),
                          ),
                      ],
                    ),
                  ],
                  const SizedBox(height: 18),
                  if (preparing) ...[const _PendingPickupCard(), const SizedBox(height: 14)],
                  if (shipment != null && shipment.hasActualAssignment && stage != _TrackingStage.searching) ...[
                    _DriverCard(shipment: shipment),
                    const SizedBox(height: 14),
                  ],
                  if (stage == _TrackingStage.delivered) ...[
                    const _DeliveredCard(),
                    const SizedBox(height: 14),
                  ] else if (shipment != null && shipment.otpVisible && shipment.otpCode.trim().isNotEmpty) ...[
                    _CodeCard(code: shipment.otpCode.trim(), message: shipment.securityMessage),
                    const SizedBox(height: 14),
                  ],
                  _DeliveryCard(
                    title: _deliveryTitle(shipment, orderNumber),
                    address: destination,
                    recipient: order?.recipientName ?? '',
                    phone: order?.phone ?? '',
                    window: _windowLabelForShipment(shipment),
                    images: shipment?.items.map((item) => item.imageUrl).toList(growable: false) ?? const [],
                  ),
                  const SizedBox(height: 16),
                  Row(
                    children: [
                      Expanded(
                        child: _BottomAction(
                          icon: Icons.receipt_long_outlined,
                          label: 'Commande',
                          onTap: () => Navigator.of(
                            context,
                          ).push(MaterialPageRoute<void>(builder: (_) => OrderDetailScreen(orderId: widget.orderId))),
                        ),
                      ),
                      const SizedBox(width: 14),
                      Expanded(
                        child: _BottomAction(
                          icon: Icons.help_outline_rounded,
                          label: 'Aide',
                          onTap: () => Navigator.of(context).push(
                            MaterialPageRoute<void>(
                              builder: (_) => SupportCenterScreen(
                                initialCategory: 'delivery',
                                contextType: 'order',
                                contextId: widget.orderId,
                              ),
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
        ),
      ],
    );
  }
}

enum _TrackingStage { preparing, searching, collecting, enRoute, arrived, delivered }

const Color _trackNavy = Color(0xFF0A1B4F);
const Color _trackMuted = Color(0xFF6B7591);
const Color _trackBorder = Color(0xFFE3E8F0);
const Color _trackGreen = Color(0xFF16A34A);

String _deliveryTitle(ClientShipmentTracking? shipment, String orderNumber) {
  final mission = shipment?.missionNumber.trim() ?? '';
  if (mission.isNotEmpty) return 'Livraison $mission';
  final label = shipment?.deliveryLabel.trim() ?? '';
  if (label.isNotEmpty) return label;
  return 'Livraison de la commande $orderNumber';
}

String _deliveredLabel(DateTime? date) {
  if (date == null) return '';
  final local = date.toLocal();
  final now = DateTime.now();
  final hm = '${local.hour.toString().padLeft(2, '0')}h${local.minute.toString().padLeft(2, '0')}';
  if (local.year == now.year && local.month == now.month && local.day == now.day) return 'aujourd’hui à $hm';
  return 'le ${local.day.toString().padLeft(2, '0')}/${local.month.toString().padLeft(2, '0')} à $hm';
}

class _MapButton extends StatelessWidget {
  final IconData icon;
  final VoidCallback? onTap;

  const _MapButton({required this.icon, required this.onTap});

  @override
  Widget build(BuildContext context) => Material(
    color: Colors.white,
    shape: const CircleBorder(),
    elevation: 4,
    shadowColor: const Color(0x40000000),
    child: InkWell(
      customBorder: const CircleBorder(),
      onTap: onTap,
      child: SizedBox(width: 52, height: 52, child: Icon(icon, color: _trackNavy, size: 26)),
    ),
  );
}

class _PositionUnavailable extends StatelessWidget {
  final String destination;

  const _PositionUnavailable({required this.destination});

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.fromLTRB(12, 9, 12, 9),
    decoration: BoxDecoration(
      color: Colors.white.withValues(alpha: .95),
      borderRadius: BorderRadius.circular(14),
      boxShadow: const [BoxShadow(color: Color(0x26000000), blurRadius: 10, offset: Offset(0, 3))],
    ),
    child: Row(
      children: [
        const Icon(Icons.info_outline_rounded, color: _trackNavy, size: 24),
        const SizedBox(width: 10),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              const FittedBox(
                fit: BoxFit.scaleDown,
                alignment: Alignment.centerLeft,
                child: Text(
                  'Position du livreur momentanément indisponible',
                  maxLines: 1,
                  style: TextStyle(color: _trackNavy, fontSize: 13.5, fontWeight: FontWeight.w700),
                ),
              ),
              if (destination.trim().isNotEmpty)
                Text(
                  'Destination : $destination',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(color: _trackMuted, fontSize: 12.5),
                ),
            ],
          ),
        ),
      ],
    ),
  );
}

class _TrackingSteps extends StatelessWidget {
  final _TrackingStage stage;

  const _TrackingSteps({required this.stage});

  @override
  Widget build(BuildContext context) {
    const labels = ['Commande\nvalidée', 'Préparation', 'Collecte', 'En route', 'Livrée'];
    final current = switch (stage) {
      _TrackingStage.preparing => 1,
      _TrackingStage.searching || _TrackingStage.collecting => 2,
      _TrackingStage.enRoute || _TrackingStage.arrived => 3,
      _TrackingStage.delivered => 4,
    };
    final delivered = stage == _TrackingStage.delivered;
    final accent = delivered ? _trackGreen : OvanieColors.orange;

    Widget dot(int index) {
      final reached = index <= current;
      final isCurrent = index == current;
      return Container(
        width: isCurrent ? 30 : 22,
        height: isCurrent ? 30 : 22,
        decoration: BoxDecoration(
          color: reached ? accent : const Color(0xFFD5DAE2),
          shape: BoxShape.circle,
          border: isCurrent && !delivered ? Border.all(color: const Color(0xFFFFD9C2), width: 4) : null,
        ),
      );
    }

    return Column(
      children: [
        SizedBox(
          height: 30,
          child: Row(
            children: [
              const SizedBox(width: 22),
              for (var i = 0; i < labels.length; i++) ...[
                dot(i),
                if (i != labels.length - 1)
                  Expanded(child: Container(height: 5, color: i < current ? accent : const Color(0xFFDDE2EA))),
              ],
              const SizedBox(width: 22),
            ],
          ),
        ),
        const SizedBox(height: 8),
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            for (var i = 0; i < labels.length; i++)
              Expanded(
                child: Text(
                  labels[i],
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    color: i == current ? accent : (i < current ? _trackNavy : _trackMuted),
                    fontSize: 12.5,
                    height: 1.2,
                    fontWeight: i == current ? FontWeight.w800 : FontWeight.w400,
                  ),
                ),
              ),
          ],
        ),
      ],
    );
  }
}

class _PendingPickupCard extends StatelessWidget {
  const _PendingPickupCard();

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.fromLTRB(16, 16, 16, 16),
    decoration: BoxDecoration(
      color: const Color(0xFFF4F6FA),
      borderRadius: BorderRadius.circular(14),
      border: Border.all(color: _trackBorder),
    ),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          width: 60,
          height: 60,
          decoration: const BoxDecoration(color: Color(0xFFFFEDE2), shape: BoxShape.circle),
          child: const Icon(Icons.inventory_2_outlined, color: OvanieColors.orange, size: 30),
        ),
        const SizedBox(width: 16),
        const Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Prise en charge en attente',
                style: TextStyle(color: _trackNavy, fontSize: 17, fontWeight: FontWeight.w800),
              ),
              SizedBox(height: 4),
              Text(
                'Votre commande est en cours de préparation. Le suivi s’affichera dès la prise en charge par le livreur.',
                style: TextStyle(color: _trackMuted, fontSize: 14.5, height: 1.4),
              ),
            ],
          ),
        ),
      ],
    ),
  );
}

class _DriverCard extends StatelessWidget {
  final ClientShipmentTracking shipment;

  const _DriverCard({required this.shipment});

  @override
  Widget build(BuildContext context) {
    final canCall = shipment.driverContactAllowed && shipment.driverPhone.trim().isNotEmpty;
    final name = shipment.driverName.trim().isEmpty ? 'Votre livreur' : shipment.driverName.trim();
    final initials = name
        .split(RegExp(r'\s+'))
        .where((part) => part.isNotEmpty)
        .take(2)
        .map((part) => part[0].toUpperCase())
        .join();
    final vehicle = [
      shipment.vehicleLabel.trim(),
      shipment.vehiclePlate.trim(),
    ].where((part) => part.isNotEmpty).join('  ·  ');
    return Container(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: _trackBorder),
      ),
      child: Row(
        children: [
          CircleAvatar(
            radius: 34,
            backgroundColor: const Color(0xFFE6EDF8),
            child: Text(
              initials.isEmpty ? '?' : initials,
              style: const TextStyle(color: _trackNavy, fontSize: 22, fontWeight: FontWeight.w800),
            ),
          ),
          const SizedBox(width: 16),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  name,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(color: _trackNavy, fontSize: 19, fontWeight: FontWeight.w800),
                ),
                if (vehicle.isNotEmpty) ...[
                  const SizedBox(height: 4),
                  Text(vehicle, style: const TextStyle(color: _trackMuted, fontSize: 15)),
                ],
              ],
            ),
          ),
          if (canCall)
            Material(
              color: const Color(0xFF15803D),
              shape: const CircleBorder(),
              child: InkWell(
                customBorder: const CircleBorder(),
                onTap: () => unawaited(ExternalUrlLauncher.open('tel:${shipment.driverPhone.trim()}')),
                child: const SizedBox(
                  width: 58,
                  height: 58,
                  child: Icon(Icons.call_rounded, color: Colors.white, size: 30),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

/// Cadre à bord pointillé (code de confirmation, livraison confirmée).
class _DashedFrame extends StatelessWidget {
  final Widget child;
  final Color color;
  final Color background;

  const _DashedFrame({required this.child, required this.color, required this.background});

  @override
  Widget build(BuildContext context) => CustomPaint(
    painter: _DashedBorderPainter(color: color),
    child: Container(
      width: double.infinity,
      padding: const EdgeInsets.fromLTRB(14, 16, 14, 16),
      decoration: BoxDecoration(color: background, borderRadius: BorderRadius.circular(14)),
      child: child,
    ),
  );
}

class _DashedBorderPainter extends CustomPainter {
  final Color color;

  const _DashedBorderPainter({required this.color});

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = color
      ..style = PaintingStyle.stroke
      ..strokeWidth = 1.4;
    final path = Path()..addRRect(RRect.fromRectAndRadius(Offset.zero & size, const Radius.circular(14)));
    for (final metric in path.computeMetrics()) {
      var distance = 0.0;
      while (distance < metric.length) {
        canvas.drawPath(metric.extractPath(distance, distance + 6), paint);
        distance += 10;
      }
    }
  }

  @override
  bool shouldRepaint(covariant _DashedBorderPainter oldDelegate) => oldDelegate.color != color;
}

class _CodeCard extends StatelessWidget {
  final String code;
  final String message;

  const _CodeCard({required this.code, required this.message});

  @override
  Widget build(BuildContext context) => _DashedFrame(
    color: const Color(0xFFE5B84A),
    background: const Color(0xFFFFF9E8),
    child: Column(
      children: [
        const Text(
          'Code de confirmation client',
          style: TextStyle(color: _trackNavy, fontSize: 16, fontWeight: FontWeight.w700),
        ),
        const SizedBox(height: 6),
        FittedBox(
          fit: BoxFit.scaleDown,
          child: Text(
            code.split('').join(' '),
            style: const TextStyle(color: _trackNavy, fontSize: 46, letterSpacing: 6, fontWeight: FontWeight.w900),
          ),
        ),
        const SizedBox(height: 4),
        Text(
          message.trim().isNotEmpty
              ? message.trim()
              : 'Ne communiquez jamais votre code avant d’avoir reçu et vérifié tous les articles.',
          textAlign: TextAlign.center,
          style: const TextStyle(color: Color(0xFF4A556E), fontSize: 13.5, height: 1.35),
        ),
      ],
    ),
  );
}

class _DeliveredCard extends StatelessWidget {
  const _DeliveredCard();

  @override
  Widget build(BuildContext context) => const _DashedFrame(
    color: Color(0xFF4ADE80),
    background: Color(0xFFEFFBF2),
    child: Column(
      children: [
        CircleAvatar(
          radius: 30,
          backgroundColor: Color(0xFF15803D),
          child: Icon(Icons.check_rounded, color: Colors.white, size: 38),
        ),
        SizedBox(height: 10),
        Text(
          'Livraison confirmée',
          style: TextStyle(color: _trackNavy, fontSize: 21, fontWeight: FontWeight.w800),
        ),
      ],
    ),
  );
}

class _DeliveryCard extends StatelessWidget {
  final String title;
  final String address;
  final String recipient;
  final String phone;
  final String window;
  final List<String> images;

  const _DeliveryCard({
    required this.title,
    required this.address,
    required this.recipient,
    required this.phone,
    required this.window,
    required this.images,
  });

  @override
  Widget build(BuildContext context) {
    Widget row(String label, List<String> values) => Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 118,
            child: Text(label, style: const TextStyle(color: _trackMuted, fontSize: 15)),
          ),
          const Text(':  ', style: TextStyle(color: _trackMuted, fontSize: 15)),
          Expanded(
            child: Text(
              values.where((value) => value.trim().isNotEmpty).join('\n').trim().isEmpty
                  ? '—'
                  : values.where((value) => value.trim().isNotEmpty).join('\n'),
              style: const TextStyle(color: _trackNavy, fontSize: 15, height: 1.35),
            ),
          ),
        ],
      ),
    );
    return Container(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: _trackBorder),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title,
            style: const TextStyle(color: _trackNavy, fontSize: 18, fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 8),
          row('Adresse', [address]),
          row('Destinataire', [recipient, phone.trim().isEmpty ? '' : formatCiPhoneDisplay(phone)]),
          row('Créneau', [window]),
          if (images.isNotEmpty) ...[
            const Divider(height: 26, color: _trackBorder),
            const Text(
              'Articles',
              style: TextStyle(color: _trackNavy, fontSize: 17, fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 12),
            OrderItemThumbnails(imageUrls: images, height: 92),
          ],
        ],
      ),
    );
  }
}

class _BottomAction extends StatelessWidget {
  final IconData icon;
  final String label;
  final VoidCallback onTap;

  const _BottomAction({required this.icon, required this.label, required this.onTap});

  @override
  Widget build(BuildContext context) => SizedBox(
    height: 60,
    child: OutlinedButton.icon(
      onPressed: onTap,
      style: OutlinedButton.styleFrom(
        foregroundColor: _trackNavy,
        side: const BorderSide(color: _trackNavy, width: 1.5),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      ),
      icon: Icon(icon, size: 26),
      label: Text(label, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w700)),
    ),
  );
}

class _TrackingError extends StatelessWidget {
  final String message;
  final VoidCallback onRetry;

  const _TrackingError({required this.message, required this.onRetry});

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 100,
            height: 100,
            decoration: const BoxDecoration(color: Color(0xFFF1F4F8), shape: BoxShape.circle),
            child: const Icon(Icons.location_off_outlined, color: Color(0xFF4A556E), size: 46),
          ),
          const SizedBox(height: 18),
          Text(
            message,
            textAlign: TextAlign.center,
            style: const TextStyle(color: _trackNavy, fontSize: 16),
          ),
          const SizedBox(height: 18),
          PaymentButton(label: 'Réessayer', onPressed: onRetry),
        ],
      ),
    ),
  );
}

String _etaLabel(DateTime? eta) {
  if (eta == null) return 'À confirmer';
  final now = DateTime.now();
  if (eta.year == now.year && eta.month == now.month && eta.day == now.day) {
    return 'aujourd’hui vers ${eta.hour.toString().padLeft(2, '0')}h${eta.minute.toString().padLeft(2, '0')}';
  }
  return '${eta.day.toString().padLeft(2, '0')}/${eta.month.toString().padLeft(2, '0')} à ${eta.hour.toString().padLeft(2, '0')}h${eta.minute.toString().padLeft(2, '0')}';
}

String _windowLabelForShipment(ClientShipmentTracking? shipment) {
  if (shipment == null) return 'À confirmer';
  // Une échéance planifiée (fin de journée prévue) n'est pas une heure d'arrivée.
  if (shipment.etaSource == 'planned' && shipment.eta != null) {
    return 'Au plus tard ${_etaLabel(shipment.eta).replaceFirst(' vers ', ' à ')}';
  }
  final start = shipment.etaWindowStart;
  final end = shipment.etaWindowEnd;
  if (start != null && end != null) {
    String hm(DateTime value) => '${value.hour.toString().padLeft(2, '0')}h${value.minute.toString().padLeft(2, '0')}';
    final sameDay = start.year == end.year && start.month == end.month && start.day == end.day;
    if (sameDay) return '${hm(start)} – ${hm(end)}';
    return '${start.day}/${start.month} ${hm(start)} – ${end.day}/${end.month} ${hm(end)}';
  }
  return _windowLabel(shipment.eta);
}

String _windowLabel(DateTime? eta) {
  if (eta == null) return 'À confirmer';
  final start = eta.subtract(const Duration(minutes: 30));
  final end = eta.add(const Duration(minutes: 30));
  String hm(DateTime value) => '${value.hour.toString().padLeft(2, '0')}h${value.minute.toString().padLeft(2, '0')}';
  return '${hm(start)} – ${hm(end)}';
}

String _relativeTime(DateTime? date) {
  if (date == null) return 'à l’instant';
  final difference = DateTime.now().difference(date);
  if (difference.inMinutes < 1) return 'à l’instant';
  if (difference.inMinutes < 60) return 'il y a ${difference.inMinutes} min';
  if (difference.inHours < 24) return 'il y a ${difference.inHours} h';
  return 'il y a ${difference.inDays} j';
}
