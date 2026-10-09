import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/network/api_client.dart';
import '../../core/sync/live_sync_service.dart';
import '../../core/ui/vendor_mockup_ui.dart';
import '../../data/vendor_repository.dart';
import 'order_detail_screen.dart';
import 'order_ui.dart';
import 'order_widgets.dart';

/// Suivi de livraison d'une commande : carte, état, étapes, prise en charge
/// OVANIE Logistics, livreur, récapitulatif et dernières mises à jour.
class OrderTrackingScreen extends StatefulWidget {
  const OrderTrackingScreen({
    super.key,
    required this.orderId,
    this.initialOrder,
  });
  final int orderId;
  final Map<String, dynamic>? initialOrder;
  @override
  State<OrderTrackingScreen> createState() => _OrderTrackingScreenState();
}

class _OrderTrackingScreenState extends State<OrderTrackingScreen>
    with WidgetsBindingObserver, LiveSyncListener<OrderTrackingScreen> {
  Map<String, dynamic> _order = {};
  bool _loading = true, _fetching = false, _foreground = true;
  String? _error;
  Timer? _timer;

  @override
  void initState() {
    super.initState();
    if (widget.initialOrder != null) _order = Map<String, dynamic>.from(widget.initialOrder!);
    WidgetsBinding.instance.addObserver(this);
    _load();
    // Réservation, collecte, livraison par le livreur : mise à jour immédiate.
    listenLiveSync({'shop'}, () => _load(silent: true));
    // Position du livreur sur la carte : rafraîchie régulièrement.
    _timer = Timer.periodic(const Duration(seconds: 15), (_) {
      if (_foreground && (ModalRoute.of(context)?.isCurrent ?? false)) {
        _load(silent: true);
      }
    });
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    _foreground = state == AppLifecycleState.resumed;
    if (_foreground) _load(silent: true);
  }

  @override
  void dispose() {
    _timer?.cancel();
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  Future<void> _load({bool silent = false}) async {
    if (_fetching || !mounted) return;
    _fetching = true;
    if (!silent) setState(() => _loading = true);
    try {
      final data = await VendorRepository.instance.order(widget.orderId, fresh: true);
      if (!mounted) return;
      final detail = orderMap(data['order']);
      setState(() {
        if (detail.isEmpty) {
          _error = 'Commande indisponible.';
        } else {
          _order = detail;
          _error = null;
        }
      });
    } catch (e) {
      // Un rafraîchissement silencieux qui échoue ne remplace pas l'écran par une erreur.
      if (mounted && (!silent || _order.isEmpty)) setState(() => _error = ApiClient.friendlyError(e));
    } finally {
      _fetching = false;
      if (mounted) setState(() => _loading = false);
    }
  }

  void _detail() => Navigator.push(
        context,
        MaterialPageRoute<void>(builder: (_) => OrderDetailScreen(orderId: widget.orderId)),
      );

  bool get _ovanie => orderUsesOvanieLogistics(_order);

  @override
  Widget build(BuildContext context) {
    final tracking = orderMap(_order['tracking']);
    final driver = orderMap(tracking['driver']);
    final hasDriver = orderValue(driver['name'], '').isNotEmpty;
    final delivered = orderValue(_order['delivery_status'], '') == 'delivered';

    return OrderPage(
      title: 'Suivi de livraison',
      subtitle: 'Suivez l’avancement de votre commande en temps réel.',
      trailing: _order.isEmpty
          ? null
          : ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 96),
              child: FittedBox(
                fit: BoxFit.scaleDown,
                child: Text(
                  orderValue(_order['order_number'], ''),
                  style: const TextStyle(color: Mk.navy, fontSize: 14, fontWeight: FontWeight.w800),
                ),
              ),
            ),
      loading: _loading,
      error: _error,
      hasContent: _order.isNotEmpty,
      onRefresh: _load,
      children: [
        if (_hasCoordinates(tracking)) _mapCard(tracking),
        _statusCard(tracking),
        _progressCard(),
        if (_ovanie) _logisticsCard(tracking),
        if (hasDriver && !delivered) _driverCard(driver),
        _summaryCard(),
        _updatesCard(),
      ],
    );
  }

  bool _hasCoordinates(Map<String, dynamic> t) =>
      double.tryParse('${t['delivery_latitude']}') != null && double.tryParse('${t['delivery_longitude']}') != null;

  Widget _mapCard(Map<String, dynamic> t) {
    final latitude = double.parse('${t['delivery_latitude']}');
    final longitude = double.parse('${t['delivery_longitude']}');
    final pickupLat = double.tryParse('${t['pickup_latitude']}');
    final pickupLng = double.tryParse('${t['pickup_longitude']}');
    final origin = pickupLat != null && pickupLng != null ? '&origin=$pickupLat,$pickupLng' : '';
    return ClipRRect(
      borderRadius: BorderRadius.circular(14),
      child: AspectRatio(
        aspectRatio: 16 / 9,
        child: Stack(
          fit: StackFit.expand,
          children: [
            OrderMapImage(url: orderValue(t['route_map_url'], '')),
            Positioned(
              right: 8,
              bottom: 8,
              child: Material(
                color: Colors.white,
                elevation: 2,
                borderRadius: BorderRadius.circular(10),
                clipBehavior: Clip.antiAlias,
                child: InkWell(
                  onTap: () async {
                    final messenger = ScaffoldMessenger.of(context);
                    final opened = await orderOpenUrl(
                      'https://www.google.com/maps/dir/?api=1$origin&destination=$latitude,$longitude',
                    );
                    if (!opened) {
                      messenger.showSnackBar(
                        const SnackBar(content: Text('Impossible d’ouvrir l’application de cartes sur ce téléphone.')),
                      );
                    }
                  },
                  child: const Padding(
                    padding: EdgeInsets.symmetric(horizontal: 12, vertical: 9),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(Icons.map_outlined, color: orderBlue, size: 20),
                        SizedBox(width: 8),
                        Text('Voir l’itinéraire', style: TextStyle(color: orderBlue, fontSize: 13, fontWeight: FontWeight.w700)),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _statusCard(Map<String, dynamic> tracking) {
    final delivery = orderValue(_order['delivery_status'], '');
    final missionStatus = orderValue(tracking['status'], '');
    final driverReserved = tracking['driver_reserved'] == true;
    final vendorPickupReady = tracking['vendor_pickup_ready'] == true;
    final delivered = delivery == 'delivered';
    final moving = ['picked_up', 'in_transit', 'late'].contains(delivery);
    final cancelled = delivery == 'cancelled' || _order['vendor_status'] == 'cancelled';
    final failed = delivery == 'failed';
    final sellerDriverPlanned = !_ovanie && orderValue(orderMap(tracking['driver'])['name'], '').isNotEmpty;
    final title = delivered
        ? 'Votre commande a été livrée'
        : cancelled
            ? 'La livraison a été annulée'
            : failed
                ? 'La livraison a rencontré un problème'
                : moving
                    ? 'Votre commande est en cours de livraison'
                    : _ovanie && driverReserved && vendorPickupReady
                        ? 'Votre commande est prête pour enlèvement'
                        : _ovanie && driverReserved
                            ? 'Un livreur partenaire a réservé la mission'
                            : _ovanie && missionStatus == 'offered'
                                ? 'Mission proposée aux livreurs partenaires'
                                : delivery == 'assigned'
                                    ? 'Un livreur a été affecté à votre commande'
                                    : sellerDriverPlanned
                                        ? 'Votre chauffeur est planifié'
                                    : 'Votre commande attend sa prise en charge';
    final message = delivered
        ? 'La livraison est terminée.'
        : moving
            ? 'Le livreur poursuit sa tournée de livraison.'
            : _ovanie
                ? 'OVANIE Logistics organise automatiquement la prise en charge.'
                : sellerDriverPlanned && !cancelled && !failed
                    ? 'La livraison démarre dès qu’il ouvre sa mission et quitte la boutique.'
                : orderValue(_order['delivery_status_label'], 'La prise en charge sera confirmée par le livreur.');
    final color = delivered
        ? orderGreen
        : (cancelled || failed)
            ? const Color(0xFFD92D20)
            : moving
                ? orderBlue
                : const Color(0xFFE85400);
    final estimated = orderShortDate(tracking['estimated_delivery_at']);

    return OrderCard(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 52,
            height: 52,
            decoration: const BoxDecoration(color: Mk.softBlue, shape: BoxShape.circle),
            child: const Icon(Icons.local_shipping_outlined, color: orderBlue, size: 28),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: const TextStyle(color: Mk.navy, fontSize: 16, height: 1.25, fontWeight: FontWeight.w800)),
                const SizedBox(height: 3),
                Text(message, style: const TextStyle(color: Mk.muted, fontSize: 13, height: 1.35, fontWeight: FontWeight.w500)),
                const Divider(height: 18, color: orderBorder),
                Wrap(
                  spacing: 14,
                  runSpacing: 6,
                  crossAxisAlignment: WrapCrossAlignment.center,
                  children: [
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 5),
                      decoration: BoxDecoration(color: color.withValues(alpha: .13), borderRadius: BorderRadius.circular(99)),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(
                            delivered ? Icons.check_circle_outline_rounded : Icons.schedule_rounded,
                            color: color,
                            size: 18,
                          ),
                          const SizedBox(width: 6),
                          Flexible(
                            child: Text(
                              orderValue(_order['delivery_status_label'], 'En attente'),
                              style: TextStyle(color: color, fontSize: 13, fontWeight: FontWeight.w700),
                            ),
                          ),
                        ],
                      ),
                    ),
                    if (estimated.isNotEmpty)
                      Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          const Icon(Icons.schedule_rounded, color: Mk.icon, size: 19),
                          const SizedBox(width: 6),
                          Flexible(
                            child: Text(
                              'Livraison estimée : $estimated',
                              style: const TextStyle(color: Mk.icon, fontSize: 13, fontWeight: FontWeight.w500),
                            ),
                          ),
                        ],
                      ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _progressCard() {
    final timeline = orderMap(_order['timeline']);
    final steps = <(String, Object?)>[
      ('Commande reçue', timeline['received_at'] ?? _order['created_at']),
      ('Préparation', timeline['prepared_at']),
      ('Expédiée', timeline['shipped_at']),
      // Une fois remise à OVANIE Logistics, le suivi vendeur s'arrête à « Expédiée ».
      if (!_ovanie) ('Livrée', timeline['delivered_at']),
    ];
    final firstPending = steps.indexWhere((step) => step.$2 == null);
    return OrderCard(
      padding: const EdgeInsets.fromLTRB(6, 14, 6, 14),
      child: OrderStepsRow(
        steps: [
          for (var i = 0; i < steps.length; i++)
            OrderStep(
              steps[i].$1,
              done: steps[i].$2 != null,
              active: i == firstPending,
              caption: steps[i].$2 != null ? orderShortDateTime(steps[i].$2).replaceFirst(' à ', '\n') : 'En attente',
            ),
        ],
      ),
    );
  }

  Widget _logisticsCard(Map<String, dynamic> tracking) {
    final driverReserved = tracking['driver_reserved'] == true;
    final vendorPickupReady = tracking['vendor_pickup_ready'] == true;
    final state = orderValue(tracking['pickup_completed_at'], '').isNotEmpty
        ? 'Remise au livreur confirmée'
        : orderValue(tracking['pickup_verified_at'], '').isNotEmpty
            ? 'Articles vérifiés · chargement en cours'
            : orderValue(tracking['pickup_arrived_at'], '').isNotEmpty
                ? 'Livreur arrivé au point de collecte'
                : driverReserved
                    ? (vendorPickupReady ? 'Livreur réservé · vendeur prêt' : 'Livreur réservé · préparation en cours')
                    : orderValue(tracking['status'], '') == 'offered'
                        ? 'En attente de réservation'
                        : 'Organisation en cours';
    final code = orderValue(tracking['pickup_handover_code'], '');
    final mission = orderValue(tracking['mission_number'], '');

    return OrderCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 42,
                height: 42,
                decoration: BoxDecoration(color: Mk.softBlue, borderRadius: BorderRadius.circular(12)),
                child: const Icon(Icons.local_shipping_rounded, color: orderBlue, size: 24),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const OrderSectionTitle('Prise en charge OVANIE Logistics'),
                    const SizedBox(height: 2),
                    Text(
                      orderValue(tracking['assignment_message'], 'OVANIE Logistics organise automatiquement la collecte.'),
                      style: const TextStyle(color: Mk.muted, fontSize: 13, height: 1.35, fontWeight: FontWeight.w500),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          if (mission.isNotEmpty) _line('Mission', mission),
          _line('État', state),
          if (code.isNotEmpty) ...[
            const SizedBox(height: 10),
            Container(
              padding: const EdgeInsets.fromLTRB(12, 12, 12, 12),
              decoration: BoxDecoration(
                color: const Color(0xFFFFF7E6),
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: const Color(0xFFF2C06B), width: 1.2),
              ),
              child: Column(
                children: [
                  const Text(
                    'Code de remise vendeur → livreur',
                    style: TextStyle(color: Color(0xFF7A4B00), fontSize: 13, fontWeight: FontWeight.w700),
                  ),
                  const SizedBox(height: 4),
                  Semantics(
                    label: 'Code de remise : ${code.split('').join(' ')}',
                    excludeSemantics: true,
                    child: FittedBox(
                      fit: BoxFit.scaleDown,
                      child: Text(
                        code,
                        style: const TextStyle(color: Mk.navy, fontSize: 34, fontWeight: FontWeight.w800, letterSpacing: 10),
                      ),
                    ),
                  ),
                  const SizedBox(height: 4),
                  const Text(
                    'Communiquez ce code uniquement lorsque tous les articles ont été vérifiés et chargés dans le véhicule du livreur.',
                    textAlign: TextAlign.center,
                    style: TextStyle(color: Mk.muted, fontSize: 12, height: 1.35, fontWeight: FontWeight.w500),
                  ),
                ],
              ),
            ),
          ],
          const SizedBox(height: 10),
          const TrueDataNotice(
            message: 'Le vendeur ne choisit pas le livreur et ne démarre pas la livraison. '
                'Lorsque la préparation est terminée, OVANIE informe automatiquement le livreur réservé.',
          ),
        ],
      ),
    );
  }

  Widget _line(String label, String value) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 3),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            SizedBox(
              width: 84,
              child: Text(label, style: const TextStyle(color: Mk.muted, fontSize: 13, fontWeight: FontWeight.w500)),
            ),
            Expanded(
              child: Text(value, style: const TextStyle(color: Mk.navy, fontSize: 13.5, fontWeight: FontWeight.w700)),
            ),
          ],
        ),
      );

  Widget _driverCard(Map<String, dynamic> driver) {
    final phone = orderValue(driver['phone'], '');
    final rating = double.tryParse('${driver['rating'] ?? ''}');
    Widget cell(String label, String value) => Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(label, style: const TextStyle(color: Mk.muted, fontSize: 12, fontWeight: FontWeight.w500)),
              const SizedBox(height: 2),
              Text(value, style: const TextStyle(color: Mk.navy, fontSize: 13, fontWeight: FontWeight.w700)),
            ],
          ),
        );

    return OrderCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Container(
                width: 46,
                height: 46,
                decoration: const BoxDecoration(color: Mk.softBlue, shape: BoxShape.circle),
                child: const Icon(Icons.person_outline_rounded, color: Mk.navy, size: 26),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const OrderSectionTitle('Votre livreur'),
                    Row(
                      children: [
                        Flexible(
                          child: Text(
                            orderValue(driver['name']),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(color: Mk.navy, fontSize: 14.5, fontWeight: FontWeight.w600),
                          ),
                        ),
                        if (rating != null && rating > 0) ...[
                          const SizedBox(width: 8),
                          const Icon(Icons.star_rounded, color: Mk.orange, size: 18),
                          Text(
                            rating.toStringAsFixed(1).replaceAll('.', ','),
                            style: const TextStyle(color: Color(0xFFE85400), fontSize: 13.5, fontWeight: FontWeight.w700),
                          ),
                        ],
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              cell('Téléphone', phone.isEmpty ? '—' : orderPhoneLabel(phone)),
              const SizedBox(width: 8),
              if (orderValue(driver['vehicle_label'], '').isNotEmpty) ...[
                cell('Véhicule', orderValue(driver['vehicle_label'])),
                const SizedBox(width: 8),
              ],
              cell('Immatriculation', orderValue(driver['vehicle_plate'])),
            ],
          ),
          if (phone.isNotEmpty) ...[
            const SizedBox(height: 12),
            MkBarButton(
              label: 'Appeler le livreur',
              icon: Icons.call_rounded,
              filled: true,
              color: orderBlue,
              height: 46,
              onTap: () => showOrderPhoneSheet(context, name: orderValue(driver['name'], 'Livreur'), phone: phone),
            ),
          ],
        ],
      ),
    );
  }

  Widget _summaryCard() {
    final items = orderList(_order['items']).map(orderMap).toList();
    final quantity = items.fold<int>(0, (total, item) => total + (int.tryParse('${item['quantity']}') ?? 0));
    return Semantics(
      button: true,
      label: 'Voir le détail de la commande',
      child: Material(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: _detail,
          child: Padding(
            padding: const EdgeInsets.fromLTRB(14, 14, 6, 14),
            child: Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const OrderSectionTitle('Récapitulatif de la commande'),
                      const SizedBox(height: 10),
                      Row(
                        children: [
                          for (final item in items.take(3))
                            Padding(
                              padding: const EdgeInsets.only(right: 6),
                              child: OrderProductImage(url: item['image_url'], size: 52),
                            ),
                          if (items.length > 3)
                            Container(
                              width: 52,
                              height: 52,
                              alignment: Alignment.center,
                              decoration: BoxDecoration(color: Mk.softBlue, borderRadius: BorderRadius.circular(10)),
                              child: Text(
                                '+${items.length - 3}',
                                style: const TextStyle(color: Mk.navy, fontSize: 14, fontWeight: FontWeight.w800),
                              ),
                            ),
                          const SizedBox(width: 8),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  items.length > 1 ? '${items.length} articles' : '${items.length} article',
                                  style: const TextStyle(color: Mk.navy, fontSize: 14.5, fontWeight: FontWeight.w800),
                                ),
                                Text(
                                  'Quantité totale : $quantity',
                                  style: const TextStyle(color: Mk.muted, fontSize: 12.5, fontWeight: FontWeight.w500),
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
                const Icon(Icons.chevron_right_rounded, color: Mk.icon, size: 26),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _updatesCard() {
    final rows = orderList(_order['status_history']).map(orderMap).toList();
    if (rows.isEmpty) {
      final timeline = orderMap(_order['timeline']);
      for (final step in [
        ('delivered_at', 'Livrée'),
        ('shipped_at', 'Expédiée'),
        ('prepared_at', 'Préparation terminée'),
        ('received_at', 'Commande reçue'),
      ]) {
        if (timeline[step.$1] != null) {
          rows.add({'label': step.$2, 'created_at': timeline[step.$1]});
        }
      }
    }
    rows.sort((a, b) {
      final byDate = '${b['created_at']}'.compareTo('${a['created_at']}');
      return byDate != 0 ? byDate : (int.tryParse('${b['id']}') ?? 0).compareTo(int.tryParse('${a['id']}') ?? 0);
    });
    // Une même étape est enregistrée pour chaque article : elle n'est affichée qu'une fois.
    final seen = <String>{};
    rows.retainWhere((row) => seen.add('${row['label']}|${orderShortDateTime(row['created_at'])}'));

    return OrderCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const OrderSectionTitle('Dernières mises à jour'),
          const SizedBox(height: 10),
          if (rows.isEmpty)
            const Text('Aucune mise à jour enregistrée.', style: TextStyle(color: Mk.muted, fontSize: 13.5)),
          for (var i = 0; i < rows.length; i++)
            IntrinsicHeight(
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Column(
                    children: [
                      Icon(
                        i == 0 ? Icons.circle : Icons.check_circle_rounded,
                        color: i == 0 ? Mk.orange : orderGreen,
                        size: 22,
                      ),
                      if (i < rows.length - 1) Expanded(child: Container(width: 2, color: orderBorder)),
                    ],
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Padding(
                      padding: const EdgeInsets.only(bottom: 14),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            orderValue(rows[i]['label'], 'Mise à jour'),
                            style: const TextStyle(color: Mk.navy, fontSize: 13.5, fontWeight: FontWeight.w700),
                          ),
                          if (orderValue(rows[i]['message'], '').isNotEmpty)
                            Text(
                              orderValue(rows[i]['message']),
                              style: const TextStyle(color: Mk.muted, fontSize: 12.5, height: 1.35, fontWeight: FontWeight.w500),
                            ),
                          Text(
                            orderShortDateTime(rows[i]['created_at']),
                            style: const TextStyle(color: Mk.muted, fontSize: 12, fontWeight: FontWeight.w500),
                          ),
                        ],
                      ),
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
