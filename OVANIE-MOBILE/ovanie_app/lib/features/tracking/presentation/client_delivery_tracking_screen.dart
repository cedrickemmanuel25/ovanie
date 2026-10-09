import 'dart:async';

import 'package:flutter/material.dart';

import '../../../app/theme.dart';
import '../../../core/network/api_client.dart';
import '../../../core/platform/external_url_launcher.dart';
import '../../orders/presentation/order_detail_screen.dart';
import '../../support/presentation/support_center_screen.dart';
import '../data/delivery_tracking_repository.dart';
import '../domain/delivery_tracking_model.dart';
import 'live_delivery_map.dart';

class ClientDeliveryTrackingScreen extends StatefulWidget {
  final int orderId;
  final String initialOrderNumber;

  const ClientDeliveryTrackingScreen({
    super.key,
    required this.orderId,
    this.initialOrderNumber = '',
  });

  @override
  State<ClientDeliveryTrackingScreen> createState() => _ClientDeliveryTrackingScreenState();
}

class _ClientDeliveryTrackingScreenState extends State<ClientDeliveryTrackingScreen> {
  final _repository = const DeliveryTrackingRepository();
  ClientDeliveryTracking? _tracking;
  bool _loading = true;
  bool _refreshing = false;
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
      _refreshing = !initial;
      _error = null;
    });
    try {
      final tracking = await _repository.fetchOrderTracking(widget.orderId);
      if (!mounted) return;
      setState(() {
        _tracking = tracking;
        _loading = false;
        _refreshing = false;
        _selectedTrackingKey = _resolveSelection(tracking);
      });
      _schedule(tracking?.pollSeconds ?? 12);
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _loading = false;
        _refreshing = false;
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
    if (_loading) {
      return const Scaffold(
        backgroundColor: Colors.white,
        body: Center(child: CircularProgressIndicator(color: OvanieColors.orange)),
      );
    }
    if (_error != null && _tracking == null) {
      return Scaffold(
        backgroundColor: Colors.white,
        appBar: AppBar(backgroundColor: Colors.white, foregroundColor: OvanieColors.navy, elevation: 0),
        body: _TrackingError(message: _error!, onRetry: () => _load(initial: true)),
      );
    }
    return Scaffold(backgroundColor: const Color(0xFFEFF2F6), body: _buildContent());
  }

  Widget _buildContent() {
    final tracking = _tracking;
    final top = MediaQuery.paddingOf(context).top;
    final screenHeight = MediaQuery.sizeOf(context).height;
    final back = Positioned(
      top: top + 8,
      left: 12,
      child: _MapButton(icon: Icons.arrow_back_rounded, onTap: () => Navigator.of(context).maybePop()),
    );

    if (tracking == null) {
      return Stack(
        children: [
          Center(
            child: Padding(
              padding: const EdgeInsets.all(32),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: const [
                  Icon(Icons.local_shipping_outlined, size: 64, color: Color(0xFFB9C4D3)),
                  SizedBox(height: 16),
                  Text(
                    'Votre commande est en préparation',
                    textAlign: TextAlign.center,
                    style: TextStyle(color: OvanieColors.navy, fontSize: 18, fontWeight: FontWeight.w700),
                  ),
                  SizedBox(height: 6),
                  Text(
                    'Le suivi s’affichera dès la prise en charge par le livreur.',
                    textAlign: TextAlign.center,
                    style: TextStyle(color: OvanieColors.muted, fontSize: 14),
                  ),
                ],
              ),
            ),
          ),
          back,
        ],
      );
    }

    final shipment = _selectedShipment;
    final order = tracking.order;
    final orderNumber = tracking.orderNumber.trim().isNotEmpty ? tracking.orderNumber : widget.initialOrderNumber;
    final statusLabel = _statusLabel(shipment?.deliveryStatus ?? tracking.deliveryStatus);
    final progress = _progressStep(tracking, shipment);
    final destination = shipment?.destinationLabel.trim().isNotEmpty == true ? shipment!.destinationLabel : (order?.address ?? '');
    final finished = shipment?.isFinished == true;

    return Stack(
      children: [
        Positioned.fill(
          child: shipment?.hasLiveMap == true
              ? LiveDeliveryMap(shipment: shipment!, height: screenHeight)
              : _RouteUnavailable(destination: destination),
        ),
        back,
        Positioned(
          top: top + 8,
          right: 12,
          child: _MapButton(
            icon: Icons.refresh_rounded,
            busy: _refreshing,
            onTap: _refreshing ? null : () => _load(),
          ),
        ),
        DraggableScrollableSheet(
          initialChildSize: .52,
          minChildSize: .3,
          maxChildSize: .92,
          builder: (context, controller) => Container(
            decoration: const BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.vertical(top: Radius.circular(22)),
              boxShadow: [BoxShadow(color: Color(0x22000000), blurRadius: 18, offset: Offset(0, -4))],
            ),
            child: ListView(
              controller: controller,
              padding: const EdgeInsets.fromLTRB(18, 10, 18, 24),
              children: [
                Center(
                  child: Container(
                    width: 42,
                    height: 4,
                    decoration: BoxDecoration(color: const Color(0xFFD9E0EB), borderRadius: BorderRadius.circular(4)),
                  ),
                ),
                const SizedBox(height: 14),
                Row(
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            finished ? 'Commande livrée' : 'Arrivée ${_etaLabel(shipment?.eta)}',
                            style: const TextStyle(color: OvanieColors.navy, fontSize: 20, fontWeight: FontWeight.w800),
                          ),
                          const SizedBox(height: 2),
                          Text(
                            'Commande $orderNumber · mis à jour ${_relativeTime(shipment?.lastUpdate ?? tracking.lastUpdate)}',
                            style: const TextStyle(color: OvanieColors.muted, fontSize: 13),
                          ),
                        ],
                      ),
                    ),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                      decoration: BoxDecoration(
                        color: finished ? const Color(0xFFE8F6E9) : const Color(0xFFEAF2FF),
                        borderRadius: BorderRadius.circular(20),
                      ),
                      child: Text(
                        statusLabel,
                        style: TextStyle(
                          color: finished ? const Color(0xFF288A45) : const Color(0xFF1266F1),
                          fontSize: 12.5,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 16),
                _TrackingProgress(step: progress, tracking: tracking, shipment: shipment),
                if (tracking.shipments.length > 1) ...[
                  const SizedBox(height: 8),
                  Wrap(
                    spacing: 8,
                    children: [
                      for (final item in tracking.shipments)
                        ChoiceChip(
                          label: Text(item.deliveryLabel.trim().isEmpty ? 'Livraison ${item.deliveryNumber}' : item.deliveryLabel),
                          selected: item.trackingKey == _selectedTrackingKey,
                          showCheckmark: false,
                          onSelected: (_) => setState(() => _selectedTrackingKey = item.trackingKey),
                        ),
                    ],
                  ),
                ],
                if (shipment?.hasActualAssignment == true) ...[
                  const SizedBox(height: 12),
                  _DriverCard(shipment: shipment!),
                ],
                if (shipment != null && (shipment.otpRequired || shipment.otpVisible || shipment.isFinished)) ...[
                  const SizedBox(height: 12),
                  _DeliverySecurityCard(shipment: shipment),
                ],
                const SizedBox(height: 12),
                _TrackingCard(
                  child: Column(
                    children: [
                      _DataRow(icon: Icons.location_on_outlined, label: 'Adresse', value: destination.trim().isEmpty ? '—' : destination),
                      const Divider(height: 18),
                      _DataRow(
                        icon: Icons.person_outline_rounded,
                        label: 'Destinataire',
                        value: order?.recipientName.trim().isNotEmpty == true ? order!.recipientName : '—',
                      ),
                      const Divider(height: 18),
                      _DataRow(icon: Icons.schedule_outlined, label: 'Créneau', value: _windowLabelForShipment(shipment)),
                    ],
                  ),
                ),
                if (shipment != null && shipment.items.isNotEmpty) ...[
                  const SizedBox(height: 12),
                  _TrackingCard(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text('Articles', style: _TrackingText.sectionTitle),
                        const SizedBox(height: 6),
                        ...shipment.items.take(4).map((item) => _TrackingItem(item: item)),
                      ],
                    ),
                  ),
                ],
                const SizedBox(height: 14),
                Row(
                  children: [
                    Expanded(
                      child: OutlinedButton.icon(
                        onPressed: () => Navigator.of(context).push(
                          MaterialPageRoute<void>(builder: (_) => OrderDetailScreen(orderId: widget.orderId)),
                        ),
                        style: OutlinedButton.styleFrom(
                          minimumSize: const Size.fromHeight(46),
                          foregroundColor: OvanieColors.navy,
                          side: const BorderSide(color: OvanieColors.border),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                        ),
                        icon: const Icon(Icons.receipt_long_outlined, size: 18),
                        label: const Text('Commande', style: TextStyle(fontSize: 14.5, fontWeight: FontWeight.w600)),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: OutlinedButton.icon(
                        onPressed: () => Navigator.of(context).push(
                          MaterialPageRoute<void>(builder: (_) => const SupportCenterScreen()),
                        ),
                        style: OutlinedButton.styleFrom(
                          minimumSize: const Size.fromHeight(46),
                          foregroundColor: OvanieColors.navy,
                          side: const BorderSide(color: OvanieColors.border),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                        ),
                        icon: const Icon(Icons.headset_mic_outlined, size: 18),
                        label: const Text('Aide', style: TextStyle(fontSize: 14.5, fontWeight: FontWeight.w600)),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }
}

class _MapButton extends StatelessWidget {
  final IconData icon;
  final VoidCallback? onTap;
  final bool busy;
  const _MapButton({required this.icon, required this.onTap, this.busy = false});

  @override
  Widget build(BuildContext context) => Material(
        color: Colors.white,
        shape: const CircleBorder(),
        elevation: 3,
        shadowColor: const Color(0x33000000),
        child: InkWell(
          customBorder: const CircleBorder(),
          onTap: onTap,
          child: SizedBox(
            width: 44,
            height: 44,
            child: busy
                ? const Padding(
                    padding: EdgeInsets.all(12),
                    child: CircularProgressIndicator(strokeWidth: 2, color: OvanieColors.navy),
                  )
                : Icon(icon, color: OvanieColors.navy),
          ),
        ),
      );
}

class _TrackingProgress extends StatelessWidget {
  final int step;
  final ClientDeliveryTracking tracking;
  final ClientShipmentTracking? shipment;

  const _TrackingProgress({required this.step, required this.tracking, required this.shipment});

  @override
  Widget build(BuildContext context) {
    final current = shipment?.isFinished == true
        ? 5
        : shipment?.isArrived == true || shipment?.isInTransit == true
            ? 4
            : shipment?.isCollecting == true || shipment?.deliveryStatus == 'picked_up'
                ? 3
                : 2;
    final update = shipment?.lastUpdate ?? tracking.lastUpdate;
    final labels = <(String, DateTime?)>[
      ('Commande\nvalidée', tracking.order?.createdAt),
      ('Préparation', shipment?.acceptedAt ?? tracking.order?.updatedAt),
      ('Collecte', current >= 3 ? update : null),
      ('En route', current >= 4 ? update : null),
      ('Livrée', current >= 5 ? update : null),
    ];

    return SizedBox(
      height: 92,
      child: LayoutBuilder(
        builder: (context, constraints) {
          final segment = constraints.maxWidth / 5;
          return Stack(
            children: [
              Positioned(
                left: segment / 2,
                right: segment / 2,
                top: 17,
                child: Container(height: 2, color: const Color(0xFFD9E0EB)),
              ),
              Positioned(
                left: segment / 2,
                top: 17,
                width: segment * (current - 1).clamp(0, 4).toDouble(),
                child: Container(height: 2, color: const Color(0xFF1266F1)),
              ),
              Row(
                children: List.generate(5, (index) {
                  final position = index + 1;
                  final done = position < current;
                  final active = position == current && current < 5;
                  final delivered = current == 5 && position == 5;
                  final highlighted = done || active || delivered;
                  return Expanded(
                    child: Column(
                      children: [
                        Container(
                          width: 34,
                          height: 34,
                          alignment: Alignment.center,
                          decoration: BoxDecoration(
                            shape: BoxShape.circle,
                            color: highlighted ? const Color(0xFF1266F1) : const Color(0xFFF0F2F6),
                          ),
                          child: done || delivered
                              ? const Icon(Icons.check_rounded, color: Colors.white, size: 20)
                              : Text('$position', style: TextStyle(color: highlighted ? Colors.white : OvanieColors.navy, fontWeight: FontWeight.w800)),
                        ),
                        const SizedBox(height: 5),
                        Text(
                          labels[index].$1,
                          textAlign: TextAlign.center,
                          style: TextStyle(color: highlighted ? OvanieColors.navy : OvanieColors.muted, fontSize: 11.5, height: 1.15, fontWeight: highlighted ? FontWeight.w800 : FontWeight.normal),
                        ),
                        const SizedBox(height: 2),
                        if (labels[index].$2 != null && (done || active || delivered))
                          Text(_shortDate(labels[index].$2), textAlign: TextAlign.center, style: const TextStyle(color: Color(0xFF5C6880), fontSize: 12)),
                      ],
                    ),
                  );
                }),
              ),
            ],
          );
        },
      ),
    );
  }
}

class _RouteUnavailable extends StatelessWidget {
  final String destination;
  const _RouteUnavailable({required this.destination});

  @override
  Widget build(BuildContext context) => Container(
        width: double.infinity,
        padding: EdgeInsets.fromLTRB(24, 0, 24, MediaQuery.sizeOf(context).height * .5),
        color: const Color(0xFFEFF2F6),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(Icons.location_searching_rounded, color: OvanieColors.muted, size: 36),
            const SizedBox(height: 8),
            const Text(
              'Position du livreur momentanément indisponible',
              textAlign: TextAlign.center,
              style: TextStyle(color: OvanieColors.navy, fontWeight: FontWeight.w800),
            ),
            if (destination.trim().isNotEmpty) ...[
              const SizedBox(height: 5),
              Text(
                'Destination : $destination',
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                textAlign: TextAlign.center,
                style: const TextStyle(color: OvanieColors.muted, fontSize: 12),
              ),
            ],
          ],
        ),
      );
}

class _DriverCard extends StatelessWidget {
  final ClientShipmentTracking shipment;
  const _DriverCard({required this.shipment});

  @override
  Widget build(BuildContext context) {
    final canContact = shipment.driverContactAllowed && shipment.driverPhone.trim().isNotEmpty;
    final vehicle = shipment.vehicleLabel.trim();
    Widget action(IconData icon, String scheme) => Material(
          color: const Color(0xFFEAF2FF),
          shape: const CircleBorder(),
          child: InkWell(
            customBorder: const CircleBorder(),
            onTap: canContact ? () => unawaited(ExternalUrlLauncher.open('$scheme:${shipment.driverPhone.trim()}')) : null,
            child: SizedBox(
              width: 44,
              height: 44,
              child: Icon(icon, color: canContact ? const Color(0xFF1266F1) : const Color(0xFFA9B6CC), size: 20),
            ),
          ),
        );
    return _TrackingCard(
      child: Row(
        children: [
          const CircleAvatar(
            radius: 24,
            backgroundColor: Color(0xFFEAF2FF),
            child: Icon(Icons.person_rounded, color: Color(0xFF1266F1), size: 26),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(shipment.driverName, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: OvanieColors.navy, fontSize: 15, fontWeight: FontWeight.w700)),
                const SizedBox(height: 2),
                Text(vehicle.isEmpty ? 'Votre livreur' : vehicle, style: const TextStyle(color: OvanieColors.muted, fontSize: 13)),
              ],
            ),
          ),
          action(Icons.chat_bubble_outline_rounded, 'sms'),
          const SizedBox(width: 8),
          action(Icons.call_outlined, 'tel'),
        ],
      ),
    );
  }
}

class _DeliverySecurityCard extends StatelessWidget {
  final ClientShipmentTracking shipment;
  const _DeliverySecurityCard({required this.shipment});

  @override
  Widget build(BuildContext context) {
    final delivered = shipment.isFinished;
    final codeVisible = shipment.otpVisible && shipment.otpCode.length == 6;
    return _TrackingCard(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 42,
            height: 42,
            decoration: BoxDecoration(
              color: delivered ? const Color(0xFFEAF8F2) : const Color(0xFFFFF6E8),
              shape: BoxShape.circle,
            ),
            child: Icon(delivered ? Icons.verified_rounded : Icons.shield_outlined, color: delivered ? const Color(0xFF087A55) : OvanieColors.orange),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  delivered ? 'Livraison confirmée' : codeVisible ? 'Code de confirmation client' : 'Code de sécurité',
                  style: const TextStyle(color: OvanieColors.navy, fontSize: 11.5, fontWeight: FontWeight.w900),
                ),
                const SizedBox(height: 4),
                if (codeVisible) ...[
                  Text(
                    shipment.otpCode,
                    style: const TextStyle(color: Color(0xFF1266F1), fontSize: 26, fontWeight: FontWeight.w900, letterSpacing: 5),
                  ),
                  const SizedBox(height: 3),
                ],
                Text(
                  shipment.securityMessage.trim().isNotEmpty
                      ? shipment.securityMessage
                      : 'Ne communiquez jamais votre code avant d’avoir reçu et vérifié tous les articles.',
                  style: const TextStyle(color: OvanieColors.muted, fontSize: 11.5, height: 1.4),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _DataRow extends StatelessWidget {
  final IconData icon;
  final String label;
  final String value;
  const _DataRow({required this.icon, required this.label, required this.value});

  @override
  Widget build(BuildContext context) => Row(
        children: [
          Icon(icon, color: OvanieColors.navy, size: 19),
          const SizedBox(width: 10),
          SizedBox(width: 112, child: Text(label, style: const TextStyle(color: Color(0xFF59657B), fontSize: 12))),
          const SizedBox(width: 10),
          Expanded(child: Text(value, textAlign: TextAlign.right, style: const TextStyle(color: OvanieColors.navy, fontSize: 12, fontWeight: FontWeight.w700))),
        ],
      );
}

class _TrackingItem extends StatelessWidget {
  final TrackingDeliveryItem item;
  const _TrackingItem({required this.item});

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.only(bottom: 6),
        child: Row(
          children: [
            Container(
              width: 44,
              height: 38,
              padding: const EdgeInsets.all(3),
              decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(6), border: Border.all(color: OvanieColors.border)),
              child: item.imageUrl.trim().isEmpty
                  ? const Icon(Icons.inventory_2_outlined, color: OvanieColors.muted, size: 19)
                  : Image.network(item.imageUrl, fit: BoxFit.contain, errorBuilder: (_, __, ___) => const Icon(Icons.inventory_2_outlined, color: OvanieColors.muted, size: 19)),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: Text(item.name, maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(color: OvanieColors.navy, fontSize: 11.5, fontWeight: FontWeight.w800)),
            ),
            const SizedBox(width: 5),
            Text('x${item.quantity}', style: const TextStyle(color: OvanieColors.navy, fontSize: 12, fontWeight: FontWeight.w800)),
          ],
        ),
      );
}

class _TrackingCard extends StatelessWidget {
  final Widget child;
  final EdgeInsetsGeometry padding;
  const _TrackingCard({required this.child, this.padding = const EdgeInsets.all(12)});

  @override
  Widget build(BuildContext context) => Container(
        width: double.infinity,
        padding: padding,
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(10),
          border: Border.all(color: const Color(0xFFDDE4ED)),
          boxShadow: const [BoxShadow(color: Color(0x07071B48), blurRadius: 8, offset: Offset(0, 2))],
        ),
        child: child,
      );
}

class _TrackingText {
  static const sectionTitle = TextStyle(color: OvanieColors.navy, fontSize: 12.5, fontWeight: FontWeight.w900);
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
              const Icon(Icons.location_off_outlined, size: 56, color: OvanieColors.muted),
              const SizedBox(height: 12),
              Text(message, textAlign: TextAlign.center),
              const SizedBox(height: 14),
              FilledButton(onPressed: onRetry, child: const Text('Réessayer')),
            ],
          ),
        ),
      );
}

int _progressStep(ClientDeliveryTracking tracking, ClientShipmentTracking? shipment) {
  final index = shipment?.progressIndex ?? tracking.overallProgressIndex;
  if (index >= 5) return 5;
  if (index >= 4) return 4;
  if (index >= 1) return 3;
  final paid = tracking.order?.paymentStatus.toLowerCase();
  if (paid == 'paid' || paid == 'success' || paid == 'completed') return 2;
  return 1;
}

String _statusLabel(String status) {
  switch (status.toLowerCase()) {
    case 'delivered':
    case 'completed':
      return 'Livrée';
    case 'arrived':
      return 'Livreur arrivé';
    case 'in_transit':
    case 'in_delivery':
      return 'En route vers vous';
    case 'collecting':
    case 'picked_up':
      return 'Collecte en cours';
    case 'ready_for_pickup':
      return 'Prête pour collecte';
    case 'driver_reserved':
    case 'assigned':
      return 'Livreur réservé';
    case 'waiting_driver':
      return 'Recherche d’un livreur';
    case 'preparing':
      return 'Préparation vendeur';
    case 'problem':
      return 'Incident en traitement';
    default:
      return 'En préparation';
  }
}

String _currentMessage(String status) {
  switch (status.toLowerCase()) {
    case 'delivered':
    case 'completed':
      return 'Votre commande a été livrée';
    case 'arrived':
      return 'Votre livreur est arrivé à votre adresse';
    case 'in_transit':
    case 'in_delivery':
      return 'Le livreur est en route vers votre adresse';
    case 'collecting':
    case 'picked_up':
      return 'Le livreur collecte les articles de votre commande';
    case 'driver_reserved':
    case 'assigned':
      return 'Un livreur partenaire a réservé votre mission';
    case 'waiting_driver':
      return 'OVANIE recherche un livreur partenaire';
    default:
      return 'Votre commande est en cours de préparation';
  }
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

String _shortDate(DateTime? date) {
  if (date == null) return '';
  final minute = date.minute.toString().padLeft(2, '0');
  return '${date.day}/${date.month} ${date.hour}:$minute';
}
