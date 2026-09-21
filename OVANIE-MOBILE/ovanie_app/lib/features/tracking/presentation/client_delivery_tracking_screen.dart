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
    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(
        centerTitle: true,
        leading: IconButton(
          onPressed: () => Navigator.of(context).maybePop(),
          icon: const Icon(Icons.arrow_back_rounded, color: OvanieColors.navy, size: 27),
        ),
        title: const Text(
          'Suivi livraison',
          style: TextStyle(color: OvanieColors.navy, fontSize: 21, fontWeight: FontWeight.w900),
        ),
        actions: [
          IconButton(
            tooltip: 'Partager',
            onPressed: () {},
            icon: const Icon(Icons.ios_share_outlined, color: OvanieColors.navy),
          ),
          const SizedBox(width: 4),
        ],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null && _tracking == null
              ? _TrackingError(message: _error!, onRetry: () => _load(initial: true))
              : RefreshIndicator(
                  onRefresh: () => _load(),
                  child: _buildContent(),
                ),
    );
  }

  Widget _buildContent() {
    final tracking = _tracking;
    if (tracking == null) {
      return ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(24),
        children: const [
          SizedBox(height: 120),
          Icon(Icons.local_shipping_outlined, size: 72, color: Color(0xFFB9C4D3)),
          SizedBox(height: 18),
          Text(
            'La livraison est en cours de préparation.',
            textAlign: TextAlign.center,
            style: TextStyle(color: OvanieColors.navy, fontSize: 18, fontWeight: FontWeight.w900),
          ),
          SizedBox(height: 8),
          Text(
            'Le suivi détaillé apparaîtra dès qu’une mission logistique sera créée.',
            textAlign: TextAlign.center,
            style: TextStyle(color: OvanieColors.muted, fontSize: 12.5, height: 1.4),
          ),
        ],
      );
    }

    final shipment = _selectedShipment;
    final order = tracking.order;
    final orderNumber = tracking.orderNumber.trim().isNotEmpty
        ? tracking.orderNumber
        : widget.initialOrderNumber;
    final statusLabel = _statusLabel(shipment?.deliveryStatus ?? tracking.deliveryStatus);
    final progress = _progressStep(tracking, shipment);
    final destination = shipment?.destinationLabel.trim().isNotEmpty == true
        ? shipment!.destinationLabel
        : (order?.address ?? '');

    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(14, 0, 14, 18),
      children: [
        Row(
          children: [
            Expanded(
              child: Text.rich(
                TextSpan(
                  style: const TextStyle(color: OvanieColors.navy, fontSize: 12),
                  children: [
                    const TextSpan(text: 'Commande n°  '),
                    TextSpan(text: orderNumber, style: const TextStyle(fontWeight: FontWeight.w900)),
                  ],
                ),
              ),
            ),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
              decoration: BoxDecoration(
                color: const Color(0xFFF0F5FF),
                borderRadius: BorderRadius.circular(8),
                border: Border.all(color: const Color(0xFFD7E4FF)),
              ),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const Icon(Icons.local_shipping_outlined, color: Color(0xFF1266F1), size: 19),
                  const SizedBox(width: 6),
                  Text(statusLabel, style: const TextStyle(color: Color(0xFF1266F1), fontSize: 11.2, fontWeight: FontWeight.w800)),
                ],
              ),
            ),
          ],
        ),
        const SizedBox(height: 8),
        _TrackingCard(
          padding: const EdgeInsets.fromLTRB(10, 10, 10, 10),
          child: Column(
            children: [
              _TrackingProgress(step: progress, tracking: tracking, shipment: shipment),
              const SizedBox(height: 8),
              shipment?.hasLiveMap == true
                  ? LiveDeliveryMap(shipment: shipment!, height: 185)
                  : _RouteUnavailable(destination: destination),
              const SizedBox(height: 8),
              Row(
                children: [
                  const Icon(Icons.schedule_outlined, color: Color(0xFF1266F1), size: 22),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text.rich(
                      TextSpan(
                        style: const TextStyle(color: OvanieColors.navy, fontSize: 10.5),
                        children: [
                          const TextSpan(text: 'Arrivée estimée :\n'),
                          TextSpan(
                            text: _etaLabel(shipment?.eta),
                            style: const TextStyle(color: Color(0xFF1266F1), fontSize: 14, fontWeight: FontWeight.w900),
                          ),
                        ],
                      ),
                    ),
                  ),
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      const Text('Dernière mise à jour :', style: TextStyle(color: Color(0xFF59657B), fontSize: 9.2)),
                      Text(_relativeTime(shipment?.lastUpdate ?? tracking.lastUpdate), style: const TextStyle(color: OvanieColors.navy, fontSize: 9.5)),
                    ],
                  ),
                  const SizedBox(width: 5),
                  const Icon(Icons.circle, color: Color(0xFF16A34A), size: 7),
                ],
              ),
            ],
          ),
        ),
        if (tracking.shipments.length > 1) ...[
          const SizedBox(height: 7),
          Row(
            children: tracking.shipments.map((item) {
              final selected = item.trackingKey == _selectedTrackingKey;
              return Expanded(
                child: Padding(
                  padding: const EdgeInsets.only(right: 6),
                  child: InkWell(
                    onTap: () => setState(() => _selectedTrackingKey = item.trackingKey),
                    borderRadius: BorderRadius.circular(8),
                    child: Container(
                      height: 42,
                      decoration: BoxDecoration(
                        color: selected ? const Color(0xFFF4F8FF) : Colors.white,
                        borderRadius: BorderRadius.circular(8),
                        border: Border.all(color: selected ? const Color(0xFF8DB7FF) : const Color(0xFFDDE3EC)),
                      ),
                      alignment: Alignment.center,
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Icon(Icons.inventory_2_outlined, color: selected ? const Color(0xFF1266F1) : OvanieColors.muted, size: 18),
                          const SizedBox(width: 7),
                          Text(
                            item.deliveryLabel.trim().isEmpty ? 'Livraison ${item.deliveryNumber}' : item.deliveryLabel,
                            style: TextStyle(color: selected ? const Color(0xFF1266F1) : OvanieColors.navy, fontSize: 10.5, fontWeight: FontWeight.w700),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
              );
            }).toList(growable: false),
          ),
        ],
        const SizedBox(height: 7),
        if (shipment?.hasActualAssignment == true) _DriverCard(shipment: shipment!),
        const SizedBox(height: 7),
        _TrackingCard(
          child: Column(
            children: [
              _DataRow(icon: Icons.location_on_outlined, label: 'Adresse de livraison', value: destination),
              const Divider(height: 12),
              _DataRow(icon: Icons.person_outline_rounded, label: 'Destinataire', value: tracking.order?.recipientName.trim().isNotEmpty == true ? tracking.order!.recipientName : 'Non renseigné'),
              const Divider(height: 12),
              _DataRow(icon: Icons.phone_outlined, label: 'Téléphone', value: tracking.order?.phone.trim().isNotEmpty == true ? tracking.order!.phone : '—'),
              const Divider(height: 12),
              _DataRow(icon: Icons.schedule_outlined, label: 'Fenêtre estimée', value: _windowLabel(shipment?.eta)),
            ],
          ),
        ),
        const SizedBox(height: 7),
        LayoutBuilder(
          builder: (context, constraints) {
            final statusCard = _TrackingCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text('Statut actuel', style: _TrackingText.sectionTitle),
                    const SizedBox(height: 8),
                    Row(
                      children: [
                        Image.asset(
                          'assets/images/tracking_status_tricycle.png',
                          width: constraints.maxWidth < 600 ? 72 : 118,
                          height: constraints.maxWidth < 600 ? 78 : 110,
                          fit: BoxFit.contain,
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                _currentMessage(shipment?.deliveryStatus ?? tracking.deliveryStatus),
                                style: TextStyle(color: OvanieColors.navy, fontSize: constraints.maxWidth < 600 ? 10.5 : 13.2, fontWeight: FontWeight.w900, height: 1.25),
                              ),
                              const SizedBox(height: 8),
                              if (shipment?.hasLiveMap == true) Container(
                                padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 6),
                                decoration: BoxDecoration(color: const Color(0xFFFFEFE5), borderRadius: BorderRadius.circular(18)),
                                child: const Row(
                                  mainAxisSize: MainAxisSize.min,
                                  children: [
                                    Icon(Icons.location_on_outlined, color: OvanieColors.orange, size: 17),
                                    SizedBox(width: 5),
                                    Text('Position GPS disponible', style: TextStyle(color: OvanieColors.orange, fontSize: 9.5)),
                                  ],
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              );
            final itemsCard = _TrackingCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text('Articles concernés', style: _TrackingText.sectionTitle),
                    const SizedBox(height: 7),
                    if (shipment == null || shipment.items.isEmpty)
                      const Padding(
                        padding: EdgeInsets.symmetric(vertical: 18),
                        child: Text('Articles en cours de synchronisation.', style: TextStyle(color: OvanieColors.muted, fontSize: 10)),
                      )
                    else
                      ...shipment.items.take(4).map((item) => _TrackingItem(item: item)),
                  ],
                ),
              );
            if (constraints.maxWidth < 330) {
              return Column(
                children: [
                  statusCard,
                  const SizedBox(height: 8),
                  itemsCard,
                ],
              );
            }
            return Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(child: statusCard),
                const SizedBox(width: 8),
                Expanded(child: itemsCard),
              ],
            );
          },
        ),
        const SizedBox(height: 7),
        _TrackingCard(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
          child: Column(
            children: [
              Row(children: [
              Container(
                width: 42,
                height: 42,
                decoration: const BoxDecoration(color: Color(0xFFEFF5FF), shape: BoxShape.circle),
                child: const Icon(Icons.headset_mic_outlined, color: Color(0xFF1266F1), size: 24),
              ),
              const SizedBox(width: 10),
              const Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('Un problème avec la livraison ?', style: TextStyle(color: OvanieColors.navy, fontSize: 11.5, fontWeight: FontWeight.w900)),
                    SizedBox(height: 2),
                    Text('Notre équipe est là pour vous aider.', style: TextStyle(color: OvanieColors.muted, fontSize: 9.5)),
                  ],
                ),
              ),
              ]),
              const SizedBox(height: 8),
              Row(children: [
              Expanded(child: OutlinedButton.icon(
                onPressed: () => Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => const SupportCenterScreen())),
                style: OutlinedButton.styleFrom(
                  foregroundColor: const Color(0xFF1266F1),
                  side: const BorderSide(color: Color(0xFFD5E1F6)),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                ),
                icon: const Icon(Icons.headset_mic_outlined, size: 16),
                label: const FittedBox(child: Text('Besoin d’aide', style: TextStyle(fontSize: 9.5))),
              )),
              const SizedBox(width: 8),
              Expanded(child: OutlinedButton.icon(
                onPressed: () => Navigator.of(context).push(
                  MaterialPageRoute<void>(builder: (_) => OrderDetailScreen(orderId: widget.orderId)),
                ),
                style: OutlinedButton.styleFrom(
                  foregroundColor: const Color(0xFF1266F1),
                  side: const BorderSide(color: Color(0xFFD5E1F6)),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                ),
                icon: const Icon(Icons.description_outlined, size: 16),
                label: const FittedBox(child: Text('Voir la commande', style: TextStyle(fontSize: 9.5))),
              )),
              ]),
            ],
          ),
        ),
        const SizedBox(height: 10),
        SizedBox(
          height: 48,
          child: FilledButton.icon(
            onPressed: _refreshing ? null : () => _load(),
            style: FilledButton.styleFrom(
              backgroundColor: const Color(0xFFFF3A0B),
              foregroundColor: Colors.white,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
            ),
            icon: _refreshing
                ? const SizedBox.square(dimension: 17, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                : const Icon(Icons.refresh_rounded),
            label: const Text('Actualiser le suivi', style: TextStyle(fontWeight: FontWeight.w900, fontSize: 14)),
          ),
        ),
        const SizedBox(height: 7),
        SizedBox(
          height: 48,
          child: OutlinedButton.icon(
            onPressed: () => Navigator.of(context).push(
              MaterialPageRoute<void>(builder: (_) => OrderDetailScreen(orderId: widget.orderId)),
            ),
            style: OutlinedButton.styleFrom(
              foregroundColor: const Color(0xFFFF3A0B),
              side: const BorderSide(color: Color(0xFFFF3A0B), width: 1.3),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
            ),
            icon: const Icon(Icons.description_outlined),
            label: const Text('Voir ma commande', style: TextStyle(fontWeight: FontWeight.w900, fontSize: 14)),
          ),
        ),
      ],
    );
  }
}

class _TrackingProgress extends StatelessWidget {
  final int step;
  final ClientDeliveryTracking tracking;
  final ClientShipmentTracking? shipment;

  const _TrackingProgress({required this.step, required this.tracking, required this.shipment});

  @override
  Widget build(BuildContext context) {
    final created = tracking.order?.createdAt;
    final update = shipment?.lastUpdate ?? tracking.lastUpdate;
    final paymentMethod = tracking.order?.paymentMethod.toLowerCase() ?? '';
    final paymentStatus = tracking.order?.paymentStatus.toLowerCase() ?? '';
    final cashOnDelivery = paymentMethod == 'cash_on_delivery' || paymentMethod.contains('cash');
    final paymentConfirmed = const {'paid', 'success', 'successful', 'completed', 'commission_paid', 'escrow_held'}.contains(paymentStatus);
    final paymentLabel = paymentConfirmed
        ? 'Paiement\nconfirmé'
        : cashOnDelivery
            ? 'Paiement à\nla livraison'
            : 'Paiement\nen attente';
    final labels = cashOnDelivery
        ? [
            ('Commande\nreçue', created),
            ('Préparation', step >= 3 ? tracking.order?.updatedAt : null),
            ('Expédiée', step >= 4 ? update : null),
            ('Livrée', shipment?.isFinished == true ? update : null),
            (paymentLabel, null),
          ]
        : [
            ('Commande\nreçue', created),
            (paymentLabel, null),
            ('Préparation', step >= 3 ? tracking.order?.updatedAt : null),
            ('Expédiée', step >= 4 ? update : null),
            ('Livrée', shipment?.isFinished == true ? update : null),
          ];
    final completed = cashOnDelivery
        ? <bool>[true, step >= 3, step >= 4, shipment?.isFinished == true, paymentConfirmed]
        : <bool>[true, paymentConfirmed, step >= 3, step >= 4, shipment?.isFinished == true];
    final visualStep = cashOnDelivery
        ? (paymentConfirmed ? 5 : shipment?.isFinished == true ? 4 : step >= 4 ? 3 : step >= 3 ? 2 : 1)
        : step;
    return SizedBox(
      height: 82,
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
                width: segment * (visualStep - 1).clamp(0, 4).toDouble(),
                child: Container(height: 2, color: const Color(0xFF1266F1)),
              ),
              Row(
                children: List.generate(5, (index) {
                  final done = completed[index];
                  final waitingPayment = index == (cashOnDelivery ? 4 : 1) && !paymentConfirmed;
                  return Expanded(
                    child: Column(
                      children: [
                        Container(
                          width: 34,
                          height: 34,
                          alignment: Alignment.center,
                          decoration: BoxDecoration(
                            shape: BoxShape.circle,
                            color: done ? const Color(0xFF1266F1) : waitingPayment ? const Color(0xFFFFF0D9) : const Color(0xFFF0F2F6),
                          ),
                          child: done
                              ? const Icon(Icons.check_rounded, color: Colors.white, size: 20)
                              : waitingPayment
                                  ? const Icon(Icons.schedule_rounded, color: OvanieColors.orange, size: 19)
                                  : Text('${index + 1}', style: const TextStyle(color: OvanieColors.navy, fontWeight: FontWeight.w800)),
                        ),
                        const SizedBox(height: 5),
                        Text(
                          labels[index].$1,
                          textAlign: TextAlign.center,
                          style: TextStyle(color: waitingPayment ? OvanieColors.orange : OvanieColors.navy, fontSize: 8.8, height: 1.15, fontWeight: waitingPayment ? FontWeight.w700 : FontWeight.normal),
                        ),
                        const SizedBox(height: 2),
                        if (labels[index].$2 != null)
                          Text(_shortDate(labels[index].$2), textAlign: TextAlign.center, style: const TextStyle(color: Color(0xFF5C6880), fontSize: 7.6)),
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
        height: 150,
        width: double.infinity,
        padding: const EdgeInsets.all(18),
        decoration: BoxDecoration(
          color: const Color(0xFFF5F7FA),
          borderRadius: BorderRadius.circular(9),
          border: Border.all(color: const Color(0xFFDCE3EC)),
        ),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(Icons.location_searching_rounded, color: OvanieColors.muted, size: 36),
            const SizedBox(height: 8),
            const Text(
              'Position réelle du livreur indisponible',
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
                style: const TextStyle(color: OvanieColors.muted, fontSize: 10.5),
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
    final name = shipment.driverName;
    final vehicle = shipment.vehicleLabel.trim().isNotEmpty
        ? shipment.vehicleLabel
        : 'Véhicule non renseigné';
    return _TrackingCard(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      child: Row(
        children: [
          ClipOval(
            child: const CircleAvatar(
              radius: 41,
              backgroundColor: Color(0xFFEAF2FF),
              child: Icon(Icons.person_outline_rounded, size: 42, color: Color(0xFF1266F1)),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(name, style: const TextStyle(color: OvanieColors.navy, fontSize: 13.5, fontWeight: FontWeight.w900)),
                const SizedBox(height: 7),
                Text('Véhicule :  $vehicle', style: const TextStyle(color: Color(0xFF59657B), fontSize: 10.2)),
                const SizedBox(height: 7),
                const Row(
                  children: [
                    Icon(Icons.shield_outlined, color: Color(0xFF16A34A), size: 17),
                    SizedBox(width: 6),
                    Expanded(
                      child: Text(
                        'Informations visibles uniquement pendant la livraison',
                        style: TextStyle(color: Color(0xFF16A34A), fontSize: 9.2, height: 1.25),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
          const SizedBox(width: 10),
          SizedBox(
            width: 148,
            child: Column(
              children: [
                SizedBox(
                  width: double.infinity,
                  height: 39,
                  child: OutlinedButton.icon(
                    onPressed: shipment.driverPhone.trim().isEmpty
                        ? null
                        : () { unawaited(ExternalUrlLauncher.open('tel:${shipment.driverPhone.trim()}')); },
                    style: OutlinedButton.styleFrom(
                      foregroundColor: const Color(0xFF1266F1),
                      side: const BorderSide(color: Color(0xFF1266F1)),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(7)),
                    ),
                    icon: const Icon(Icons.call_outlined, size: 17),
                    label: const Text('Appeler', style: TextStyle(fontSize: 10.2)),
                  ),
                ),
                const SizedBox(height: 7),
                SizedBox(
                  width: double.infinity,
                  height: 39,
                  child: OutlinedButton.icon(
                    onPressed: shipment.driverPhone.trim().isEmpty
                        ? null
                        : () { unawaited(ExternalUrlLauncher.open('sms:${shipment.driverPhone.trim()}')); },
                    style: OutlinedButton.styleFrom(
                      foregroundColor: const Color(0xFF1266F1),
                      side: const BorderSide(color: Color(0xFF1266F1)),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(7)),
                    ),
                    icon: const Icon(Icons.chat_bubble_outline_rounded, size: 17),
                    label: const Text('Message', style: TextStyle(fontSize: 10.2)),
                  ),
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
          SizedBox(width: 112, child: Text(label, style: const TextStyle(color: Color(0xFF59657B), fontSize: 10.2))),
          const SizedBox(width: 10),
          Expanded(child: Text(value, textAlign: TextAlign.right, style: const TextStyle(color: OvanieColors.navy, fontSize: 10.7, fontWeight: FontWeight.w700))),
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
              child: Text(item.name, maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(color: OvanieColors.navy, fontSize: 9.8, fontWeight: FontWeight.w800)),
            ),
            const SizedBox(width: 5),
            Text('x${item.quantity}', style: const TextStyle(color: OvanieColors.navy, fontSize: 10.5, fontWeight: FontWeight.w800)),
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
    case 'in_transit':
    case 'in_delivery':
      return 'En livraison';
    case 'picked_up':
    case 'shipped':
      return 'Expédiée';
    case 'assigned':
      return 'Livreur assigné';
    case 'preparing':
    case 'ready_for_pickup':
      return 'Préparation';
    default:
      return 'En cours';
  }
}

String _currentMessage(String status) {
  switch (status.toLowerCase()) {
    case 'delivered':
    case 'completed':
      return 'Votre commande a été livrée';
    case 'in_transit':
    case 'in_delivery':
      return 'Le livreur est en route vers votre adresse';
    case 'picked_up':
    case 'shipped':
      return 'Votre commande a été expédiée';
    case 'assigned':
      return 'Un livreur a été assigné à votre commande';
    default:
      return 'Votre commande est en cours de préparation';
  }
}

String _etaLabel(DateTime? eta) {
  if (eta == null) return 'À confirmer';
  final now = DateTime.now();
  if (eta.year == now.year && eta.month == now.month && eta.day == now.day) {
    return 'aujourd’hui avant ${eta.hour.toString().padLeft(2, '0')}h';
  }
  return '${eta.day.toString().padLeft(2, '0')}/${eta.month.toString().padLeft(2, '0')} à ${eta.hour.toString().padLeft(2, '0')}h';
}

String _windowLabel(DateTime? eta) {
  if (eta == null) return 'À confirmer';
  final start = (eta.hour - 1).clamp(0, 23).toString().padLeft(2, '0');
  final end = eta.hour.toString().padLeft(2, '0');
  return '${start}h00 - ${end}h00';
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
