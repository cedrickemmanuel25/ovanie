import 'dart:async';
import 'package:flutter/material.dart';
import '../../core/network/api_client.dart';
import '../../data/vendor_repository.dart';
import '../finance/data_ui.dart';
import 'order_ui.dart';
import 'order_detail_screen.dart';

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
    with WidgetsBindingObserver {
  Map<String, dynamic> _order = {};
  bool _loading = true, _fetching = false, _foreground = true;
  String? _error;
  Timer? _timer;
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _load();
    _timer = Timer.periodic(const Duration(seconds: 30), (_) {
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
      final data = await VendorRepository.instance.order(
        widget.orderId,
        fresh: true,
      );
      if (!mounted) return;
      setState(() {
        _order = orderMap(data['order']);
        _error = _order.isEmpty ? 'Commande indisponible.' : null;
      });
    } catch (e) {
      if (mounted) setState(() => _error = ApiClient.friendlyError(e));
    } finally {
      _fetching = false;
      if (mounted) setState(() => _loading = false);
    }
  }

  void _detail() => Navigator.push(
    context,
    MaterialPageRoute(
      builder: (_) => OrderDetailScreen(orderId: widget.orderId),
    ),
  );
  @override
  Widget build(BuildContext context) {
    final tracking = orderMap(_order['tracking']);
    final driver = orderMap(tracking['driver']);
    final items = orderList(_order['items']).map(orderMap).toList();
    final delivery = screenText(_order['delivery_status'], '');
    final missionStatus = screenText(tracking['status'], '');
    final driverReserved = tracking['driver_reserved'] == true;
    final vendorPickupReady = tracking['vendor_pickup_ready'] == true;
    final delivered = delivery == 'delivered';
    final moving = ['picked_up', 'in_transit', 'late'].contains(delivery);
    final cancelled =
        delivery == 'cancelled' || _order['vendor_status'] == 'cancelled';
    final title = delivered
        ? 'Votre commande a été livrée'
        : cancelled
        ? 'La livraison a été annulée'
        : delivery == 'failed'
        ? 'La livraison a rencontré un problème'
        : moving
        ? 'Votre commande est en cours de livraison'
        : _isOvanieLogistics && driverReserved && vendorPickupReady
        ? 'Votre commande est prête pour enlèvement'
        : _isOvanieLogistics && driverReserved
        ? 'Un livreur partenaire a réservé la mission'
        : _isOvanieLogistics && missionStatus == 'offered'
        ? 'Mission proposée aux livreurs partenaires'
        : delivery == 'assigned'
        ? 'Un livreur a été affecté à votre commande'
        : 'Votre commande attend sa prise en charge';
    return VendorDataPage(
      title: 'Suivi de livraison',
      subtitle: 'Suivez l’avancement de votre commande en temps réel.',
      back: true,
      loading: _loading,
      error: _error,
      onRefresh: _load,
      child: _order.isEmpty
          ? const SizedBox()
          : Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Align(
                  alignment: Alignment.centerRight,
                  child: Text(
                    screenText(_order['order_number']),
                    style: const TextStyle(
                      color: orderText,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
                const SizedBox(height: 12),
                _progress(),
                DataCard(
                  color: orderSoftBlue,
                  child: dataPair(
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Icon(
                          Icons.local_shipping_outlined,
                          color: orderText,
                          size: 35,
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              screenTitle(title),
                              const SizedBox(height: 8),
                              Text(
                                delivered
                                    ? 'La livraison est terminée.'
                                    : moving
                                    ? 'Le livreur poursuit sa tournée de livraison.'
                                    : _isOvanieLogistics
                                    ? screenText(
                                        tracking['assignment_message'],
                                        'OVANIE Logistics organise automatiquement la prise en charge.',
                                      )
                                    : screenText(
                                        _order['delivery_status_label'],
                                        'La prise en charge sera confirmée par le livreur.',
                                      ),
                                style: const TextStyle(
                                  color: orderMuted,
                                  fontSize: 12,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        OrderStatusPill(
                          status: cancelled
                              ? 'cancelled'
                              : delivered
                              ? 'delivered'
                              : moving
                              ? 'shipped'
                              : 'ready',
                          label: screenText(
                            _order['delivery_status_label'],
                            'En attente',
                          ),
                          compact: true,
                        ),
                        const SizedBox(height: 8),
                        Text(
                          screenText(
                            tracking['estimated_delivery_label'] ??
                                _order['estimated_delivery_label'],
                            'Estimation non disponible',
                          ),
                          style: const TextStyle(
                            color: orderText,
                            fontSize: 12,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
                if (_isOvanieLogistics)
                  DataCard(
                    title: 'Prise en charge OVANIE Logistics',
                    icon: Icons.local_shipping_outlined,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          screenText(
                            tracking['assignment_message'],
                            'OVANIE Logistics organise automatiquement la collecte.',
                          ),
                          style: const TextStyle(
                            color: orderText,
                            fontSize: 12.5,
                            height: 1.4,
                          ),
                        ),
                        const SizedBox(height: 10),
                        if (screenText(tracking['mission_number'], '').isNotEmpty)
                          dataLine(
                            'Mission',
                            tracking['mission_number'],
                          ),
                        dataLine(
                          'État',
                          screenText(tracking['pickup_completed_at'], '').isNotEmpty
                              ? 'Remise au livreur confirmée'
                              : screenText(tracking['pickup_verified_at'], '').isNotEmpty
                                  ? 'Articles vérifiés · chargement en cours'
                                  : screenText(tracking['pickup_arrived_at'], '').isNotEmpty
                                      ? 'Livreur arrivé au point de collecte'
                                      : driverReserved
                                          ? (vendorPickupReady
                                              ? 'Livreur réservé · vendeur prêt'
                                              : 'Livreur réservé · préparation en cours')
                                          : missionStatus == 'offered'
                                              ? 'En attente de réservation'
                                              : 'Organisation en cours',
                        ),
                        if (screenText(tracking['pickup_handover_code'], '').isNotEmpty) ...[
                          const SizedBox(height: 12),
                          Container(
                            width: double.infinity,
                            padding: const EdgeInsets.all(14),
                            decoration: BoxDecoration(
                              color: const Color(0xFFFFF7E8),
                              borderRadius: BorderRadius.circular(12),
                              border: Border.all(color: const Color(0xFFF2C06B)),
                            ),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                const Text(
                                  'Code de remise vendeur → livreur',
                                  style: TextStyle(
                                    color: orderText,
                                    fontSize: 12,
                                    fontWeight: FontWeight.w800,
                                  ),
                                ),
                                const SizedBox(height: 6),
                                Text(
                                  screenText(tracking['pickup_handover_code']),
                                  style: const TextStyle(
                                    color: orderOrange,
                                    fontSize: 28,
                                    fontWeight: FontWeight.w900,
                                    letterSpacing: 7,
                                  ),
                                ),
                                const SizedBox(height: 6),
                                const Text(
                                  'Communiquez ce code uniquement lorsque tous les articles ont été vérifiés et chargés dans le véhicule du livreur.',
                                  style: TextStyle(color: orderMuted, fontSize: 11.5, height: 1.35),
                                ),
                              ],
                            ),
                          ),
                        ],
                        const SizedBox(height: 8),
                        const Text(
                          'Le vendeur ne choisit pas le livreur et ne démarre pas la livraison. '
                          'Lorsque la préparation est terminée, OVANIE informe automatiquement le livreur réservé.',
                          style: TextStyle(
                            color: orderMuted,
                            fontSize: 11.5,
                            height: 1.35,
                          ),
                        ),
                      ],
                    ),
                  ),
                // Une fois remise à OVANIE Logistics, le suivi GPS et le
                // livreur ne concernent plus le vendeur (il n'a plus la
                // marchandise) : afficher une carte et un contact livreur
                // qu'il ne peut plus joindre utilement n'a pas de sens.
                if (!_isOvanieLogistics) ...[
                _map(tracking, driver),
                DataCard(
                  title: 'Votre livreur',
                  child: driver.isEmpty
                      ? Text(
                          screenText(
                            tracking['assignment_message'],
                            'Aucun livreur affecté pour le moment.',
                          ),
                          style: const TextStyle(color: orderMuted),
                        )
                      : Column(
                          children: [
                            dataPair(
                              Row(
                                children: [
                                  const CircleAvatar(
                                    backgroundColor: Color(0xFFEAF1FF),
                                    child: Icon(
                                      Icons.person_outline,
                                      color: orderText,
                                    ),
                                  ),
                                  const SizedBox(width: 10),
                                  Expanded(
                                    child: Column(
                                      crossAxisAlignment:
                                          CrossAxisAlignment.start,
                                      children: [
                                        Text(
                                          screenText(driver['name']),
                                          style: const TextStyle(
                                            color: orderText,
                                            fontSize: 14,
                                            fontWeight: FontWeight.w700,
                                          ),
                                        ),
                                        if (driver['rating'] != null)
                                          Text(
                                            '★ ${driver['rating']}',
                                            style: const TextStyle(
                                              color: orderOrange,
                                            ),
                                          ),
                                      ],
                                    ),
                                  ),
                                ],
                              ),
                              Column(
                                children: [
                                  dataLine('Téléphone', driver['phone']),
                                  dataLine('Véhicule', driver['vehicle_label']),
                                  dataLine(
                                    'Immatriculation',
                                    driver['vehicle_plate'],
                                  ),
                                ],
                              ),
                            ),
                            const SizedBox(height: 10),
                            dataButton(
                              'Appeler le livreur',
                              Icons.phone_outlined,
                              screenText(driver['phone'], '').isEmpty
                                  ? null
                                  : () => openVendorUrl(
                                      context,
                                      'tel:${driver['phone']}',
                                    ),
                            ),
                          ],
                        ),
                ),
                ],
                _updates(),
                DataCard(
                  title: 'Récapitulatif de la commande',
                  icon: Icons.inventory_2_outlined,
                  trailing: IconButton(
                    tooltip: 'Voir le détail',
                    onPressed: _detail,
                    icon: const Icon(Icons.chevron_right),
                  ),
                  child: Column(
                    children: [
                      SizedBox(
                        height: 54,
                        child: ListView(
                          scrollDirection: Axis.horizontal,
                          children: [
                            for (final item in items.take(3))
                              Padding(
                                padding: const EdgeInsets.only(right: 6),
                                child: OrderProductImage(
                                  url: item['image_url'],
                                  size: 54,
                                ),
                              ),
                            if (items.length > 3)
                              Container(
                                width: 54,
                                alignment: Alignment.center,
                                color: orderSoftBlue,
                                child: Text('+${items.length - 3}'),
                              ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 8),
                      dataLine(
                        '${items.length} articles',
                        _order['public_products_total'],
                        money: true,
                        color: orderOrange,
                        strong: true,
                      ),
                      Text(
                        'Quantité totale : ${items.fold<int>(0, (n, item) => n + (int.tryParse('${item['quantity']}') ?? 0))}',
                        style: const TextStyle(color: orderMuted, fontSize: 12),
                      ),
                    ],
                  ),
                ),
                dataSupport(context),
              ],
            ),
    );
  }

  bool get _isOvanieLogistics =>
      '${_order['delivery_provider'] ?? ''}'.toLowerCase() == 'ovanie';

  Widget _progress() {
    final timeline = orderMap(_order['timeline']);
    final steps = [
      ('Commande reçue', timeline['received_at'] ?? _order['created_at']),
      ('Préparation', timeline['prepared_at']),
      ('Expédiée', timeline['shipped_at']),
      if (!_isOvanieLogistics) ('Livrée', timeline['delivered_at']),
    ];
    return Padding(
      padding: const EdgeInsets.only(bottom: 16),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          for (var i = 0; i < steps.length; i++)
            Expanded(
              child: Column(
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Container(
                          height: 1,
                          color: i == 0 ? Colors.transparent : orderBorder,
                        ),
                      ),
                      Container(
                        width: 30,
                        height: 30,
                        alignment: Alignment.center,
                        decoration: BoxDecoration(
                          shape: BoxShape.circle,
                          color: steps[i].$2 != null
                              ? orderOrange
                              : Colors.white,
                          border: Border.all(
                            color: steps[i].$2 != null
                                ? orderOrange
                                : orderMuted,
                          ),
                        ),
                        child: Text(
                          '${i + 1}',
                          style: TextStyle(
                            color: steps[i].$2 != null
                                ? Colors.white
                                : orderMuted,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ),
                      Expanded(
                        child: Container(
                          height: 1,
                          color: i == 3 ? Colors.transparent : orderBorder,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 7),
                  Text(
                    steps[i].$1,
                    textAlign: TextAlign.center,
                    style: const TextStyle(color: orderText, fontSize: 10),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    steps[i].$2 == null
                        ? 'En attente'
                        : '${orderDateOnly(steps[i].$2)}\n${orderTimeOnly(steps[i].$2)}',
                    textAlign: TextAlign.center,
                    style: const TextStyle(color: orderMuted, fontSize: 9),
                  ),
                ],
              ),
            ),
        ],
      ),
    );
  }

  Widget _map(Map<String, dynamic> t, Map<String, dynamic> d) {
    double? n(Object? v) => double.tryParse('$v');
    final lat = n(d['latitude']) ?? n(t['delivery_latitude']);
    final lng = n(d['longitude']) ?? n(t['delivery_longitude']);
    final destLat = n(t['delivery_latitude']),
        destLng = n(t['delivery_longitude']);
    final markers = <String>[];
    for (final point in [
      (t['pickup_latitude'], t['pickup_longitude'], 'blue-pushpin'),
      (t['delivery_latitude'], t['delivery_longitude'], 'red-pushpin'),
      (d['latitude'], d['longitude'], 'lightblue1'),
    ]) {
      if (n(point.$1) != null && n(point.$2) != null) {
        markers.add('${point.$1},${point.$2},${point.$3}');
      }
    }
    return DataCard(
      child: Column(
        children: [
          if (lat == null || lng == null)
            const SizedBox(
              height: 150,
              child: Center(
                child: Text(
                  'La position de livraison n’est pas encore disponible.',
                  textAlign: TextAlign.center,
                  style: TextStyle(color: orderMuted),
                ),
              ),
            )
          else
            ClipRRect(
              borderRadius: BorderRadius.circular(10),
              child: Image.network(
                'https://staticmap.openstreetmap.de/staticmap.php?center=$lat,$lng&zoom=12&size=800x300&markers=${Uri.encodeQueryComponent(markers.join('|'))}',
                height: 180,
                width: double.infinity,
                fit: BoxFit.cover,
                errorBuilder: (_, __, ___) => const SizedBox(
                  height: 150,
                  child: Center(
                    child: Text('Carte indisponible pour le moment.'),
                  ),
                ),
              ),
            ),
          if (d['location_updated_at'] != null)
            Padding(
              padding: const EdgeInsets.only(top: 8),
              child: Text(
                'Position reçue : ${orderHumanDate(d['location_updated_at'])}',
                style: const TextStyle(color: orderMuted, fontSize: 11),
              ),
            ),
          if (destLat != null && destLng != null)
            Align(
              alignment: Alignment.centerRight,
              child: dataButton(
                'Voir l’itinéraire',
                Icons.map_outlined,
                () => openVendorUrl(
                  context,
                  'https://www.google.com/maps/dir/?api=1&destination=$destLat,$destLng',
                ),
              ),
            ),
        ],
      ),
    );
  }

  Widget _updates() {
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
    rows.sort((a, b) => '${b['created_at']}'.compareTo('${a['created_at']}'));
    return DataCard(
      title: 'Dernières mises à jour',
      child: Column(
        children: [
          if (rows.isEmpty) const Text('Aucune mise à jour enregistrée.'),
          for (var i = 0; i < rows.length; i++)
            IntrinsicHeight(
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Column(
                    children: [
                      Icon(
                        i == 0 ? Icons.circle : Icons.check_circle,
                        color: i == 0 ? orderOrange : const Color(0xFF6A81AD),
                        size: 24,
                      ),
                      if (i < rows.length - 1)
                        Expanded(
                          child: Container(width: 2, color: orderBorder),
                        ),
                    ],
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Padding(
                      padding: const EdgeInsets.only(bottom: 16),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            screenText(rows[i]['label']),
                            style: TextStyle(
                              color: i == 0 ? orderOrange : orderText,
                              fontSize: 14,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                          if (rows[i]['message'] != null)
                            Text(
                              '${rows[i]['message']}',
                              style: const TextStyle(
                                color: orderMuted,
                                fontSize: 12,
                              ),
                            ),
                          const SizedBox(height: 4),
                          Text(
                            orderHumanDate(rows[i]['created_at']),
                            style: const TextStyle(
                              color: orderMuted,
                              fontSize: 10,
                            ),
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
