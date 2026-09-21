import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../core/network/api_client.dart';
import '../../data/vendor_repository.dart';
import 'order_tracking_screen.dart';
import 'order_ui.dart';

class OrderExpeditionScreen extends StatefulWidget {
  const OrderExpeditionScreen({
    super.key,
    required this.orderId,
    this.initialOrder,
  });
  final int orderId;
  final Map<String, dynamic>? initialOrder;

  @override
  State<OrderExpeditionScreen> createState() => _OrderExpeditionScreenState();
}

class _OrderExpeditionScreenState extends State<OrderExpeditionScreen> {
  Map<String, dynamic> _order = <String, dynamic>{};
  bool _loading = true;
  bool _saving = false;
  bool _handoverConfirmed = false;
  bool _documentChecked = false;
  bool _clientNotified = false;
  String? _error;
  bool _showAll = false;
  String? _provider;
  final _driverName = TextEditingController();
  final _driverPhone = TextEditingController();
  final _vehiclePlate = TextEditingController();

  @override
  void dispose() {
    _driverName.dispose();
    _driverPhone.dispose();
    _vehiclePlate.dispose();
    super.dispose();
  }

  bool _shipped(Map<String, dynamic> item) =>
      [
        'picked_up',
        'in_transit',
        'late',
        'delivered',
      ].contains(item['delivery_status']) ||
      ['shipped', 'delivered'].contains(item['vendor_status']);
  bool get _alreadyShipped => _shipped(_order);
  bool get _editable =>
      !_loading &&
      !_saving &&
      _error == null &&
      _order['vendor_status'] == 'ready' &&
      !_alreadyShipped;
  bool get _canConfirm =>
      _editable &&
      _handoverConfirmed &&
      _documentChecked &&
      _clientNotified &&
      ['ovanie', 'seller'].contains(_provider) &&
      (_provider != 'seller' ||
          (_driverName.text.trim().isNotEmpty &&
              _driverPhone.text.trim().isNotEmpty &&
              _vehiclePlate.text.trim().isNotEmpty));

  @override
  void initState() {
    super.initState();
    if (widget.initialOrder != null) {
      _order = Map<String, dynamic>.from(widget.initialOrder!);
    }
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
  }

  Future<void> _load({bool silent = false}) async {
    if (_saving || !mounted) return;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final data = await VendorRepository.instance.order(
        widget.orderId,
        fresh: true,
      );
      final detail = orderMap(data['order']);
      if (!mounted) return;
      setState(() {
        _order = detail;
        _error = detail.isEmpty ? 'Cette commande est indisponible.' : null;
        _handoverConfirmed = false;
        _documentChecked = false;
        _clientNotified = false;
        final provider = '${detail['delivery_provider'] ?? ''}';
        _provider = ['ovanie', 'seller'].contains(provider) ? provider : null;
        final driver = orderMap(orderMap(detail['tracking'])['driver']);
        _driverName.text = _value(driver['name'], '');
        _driverPhone.text = _value(driver['phone'], '');
        _vehiclePlate.text = _value(driver['vehicle_plate'], '');
      });
    } catch (e) {
      if (mounted) setState(() => _error = ApiClient.friendlyError(e));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _confirmExpedition() async {
    if (!_canConfirm) return;
    if (!_handoverConfirmed || !_documentChecked || !_clientNotified) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'Validez les contrôles avant de confirmer l’expédition.',
          ),
        ),
      );
      return;
    }
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      final provider = _provider!;
      final now = DateTime.now();
      String two(int value) => value.toString().padLeft(2, '0');
      final date = '${now.year}-${two(now.month)}-${two(now.day)}';

      final values = <String, dynamic>{
        'shipment_date': date,
        'delivery_provider': provider == 'seller' ? 'seller' : 'ovanie',
      };
      if (provider == 'seller') {
        final name = _driverName.text.trim();
        final phone = _driverPhone.text.trim();
        final plate = _vehiclePlate.text.trim();
        if (name.isEmpty || phone.isEmpty || plate.isEmpty) {
          throw Exception(
            'Les informations du chauffeur vendeur sont incomplètes. Renseignez le chauffeur avant l’expédition.',
          );
        }
        values.addAll(<String, dynamic>{
          'driver_name': name,
          'driver_phone': phone,
          'vehicle_plate': plate,
        });
      }

      await VendorRepository.instance.shipOrder(widget.orderId, values);
      final updated = Map<String, dynamic>.from(_order);
      if (!mounted) return;
      setState(() => _order = updated);
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('Expédition confirmée.')));
      Navigator.of(context).pushReplacement<void, void>(
        MaterialPageRoute<void>(
          builder: (_) => OrderTrackingScreen(
            orderId: widget.orderId,
            initialOrder: updated,
          ),
        ),
      );
    } catch (e) {
      if (mounted) setState(() => _error = ApiClient.friendlyError(e));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<void> _openPhone(String phone) async {
    if (phone.trim().isEmpty) return;
    try {
      final opened = await const MethodChannel('ovanie/external_url')
          .invokeMethod<bool>('openUrl', {
            'url': 'tel:${phone.replaceAll(RegExp(r"[^+0-9]"), '')}',
          });
      if (opened == true) return;
    } catch (_) {
      /* Le numéro reste accessible sur les plateformes sans composeur. */
    }
    if (!mounted) return;
    await showModalBottomSheet<void>(
      context: context,
      builder: (context) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              SelectableText(
                phone,
                style: const TextStyle(fontSize: 20, color: orderText),
              ),
              const SizedBox(height: 12),
              OutlinedButton.icon(
                onPressed: () async {
                  await Clipboard.setData(ClipboardData(text: phone));
                  if (context.mounted) Navigator.pop(context);
                },
                icon: const Icon(Icons.copy),
                label: const Text('Copier le numéro'),
              ),
            ],
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: Colors.white,
    bottomNavigationBar: SafeArea(
      top: false,
      child: Container(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 14),
        decoration: const BoxDecoration(
          color: Colors.white,
          border: Border(top: BorderSide(color: orderBorder)),
        ),
        child: Center(
          heightFactor: 1,
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 980),
            child: _actions(),
          ),
        ),
      ),
    ),
    body: RefreshIndicator(
      onRefresh: _load,
      child: CustomScrollView(
        physics: const AlwaysScrollableScrollPhysics(),
        slivers: [
          SliverToBoxAdapter(
            child: OrderHeader(
              title: 'Expédition de commande',
              subtitle: 'Confirmez la prise en charge de la livraison.',
              showBack: true,
              bottom: Align(
                alignment: Alignment.centerRight,
                child: Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 12,
                    vertical: 8,
                  ),
                  decoration: BoxDecoration(
                    color: const Color(0xFF16437C),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      Text(
                        _value(_order['order_number'], 'Commande'),
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 13,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      if (_order['created_at'] != null)
                        Text(
                          '${orderDateOnly(_order['created_at'])} à ${orderTimeOnly(_order['created_at'])}',
                          style: const TextStyle(
                            color: Color(0xFFB9CAE8),
                            fontSize: 11,
                          ),
                        ),
                    ],
                  ),
                ),
              ),
            ),
          ),
          if (_loading)
            const SliverFillRemaining(
              hasScrollBody: false,
              child: Center(
                child: CircularProgressIndicator(color: orderOrange),
              ),
            )
          else
            SliverToBoxAdapter(
              child: ColoredBox(
                color: const Color(0xFF031D47),
                child: OrderSheet(
                  child: OrderResponsiveBody(
                    padding: const EdgeInsets.fromLTRB(16, 16, 16, 20),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        _progress(),
                        if (_error != null)
                          Padding(
                            padding: const EdgeInsets.symmetric(vertical: 12),
                            child: Column(
                              children: [
                                Text(
                                  _error!,
                                  style: const TextStyle(
                                    color: Color(0xFFB42318),
                                  ),
                                ),
                                TextButton(
                                  onPressed: _saving ? null : _load,
                                  child: const Text('Réessayer'),
                                ),
                              ],
                            ),
                          ),
                        if (_order.isNotEmpty) ...[
                          _deliveryInfo(),
                          const SizedBox(height: 12),
                          _handoverCard(),
                          const SizedBox(height: 12),
                          _itemsSection(),
                          const SizedBox(height: 14),
                          _controlsBox(),
                          const SizedBox(height: 12),
                          const TrueDataNotice(
                            message:
                                'Le suivi de la livraison sera mis à jour après la prise en charge.',
                          ),
                        ],
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

  String _value(Object? value, [String fallback = '—']) {
    final text = '${value ?? ''}'.trim();
    return text.isEmpty ? fallback : text;
  }

  Widget _progress() {
    final step = orderWorkflowStep(
      _order['vendor_status'],
      deliveryStatus: _order['delivery_status'],
    );
    final current = _alreadyShipped
        ? step
        : _order['vendor_status'] == 'ready'
        ? 2
        : 1;
    final isOvanieLogistics =
        '${_order['delivery_provider'] ?? ''}'.toLowerCase() == 'ovanie';
    return Padding(
      padding: const EdgeInsets.only(bottom: 16),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: List.generate(4, (index) {
          final active = index < current;
          final color = active ? orderOrange : const Color(0xFF91A1BC);
          return Expanded(
            child: Column(
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Container(
                        height: 1,
                        color: index == 0 ? Colors.transparent : color,
                      ),
                    ),
                    Container(
                      width: 28,
                      height: 28,
                      alignment: Alignment.center,
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        color: active ? orderOrange : Colors.white,
                        border: Border.all(color: color),
                      ),
                      child: Text(
                        '${index + 1}',
                        style: TextStyle(
                          color: active ? Colors.white : color,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                    Expanded(
                      child: Container(
                        height: 1,
                        color: index == 3
                            ? Colors.transparent
                            : index + 1 < current
                            ? orderOrange
                            : orderBorder,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 6),
                Text(
                  [
                    'Commande reçue',
                    'Préparation',
                    'Expédiée',
                    isOvanieLogistics ? 'Remise à\nOVANIE' : 'Livrée',
                  ][index],
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    color: active ? orderOrange : orderMuted,
                    fontSize: 11,
                  ),
                ),
              ],
            ),
          );
        }),
      ),
    );
  }

  Widget _deliveryInfo() {
    final client = orderMap(_order['client']);
    final address = orderMap(_order['delivery_address']);
    final tracking = orderMap(_order['tracking']);
    final estimated = tracking['estimated_delivery_at'];
    final blocks = [
      _infoBlock(Icons.person_outline, 'Client', [
        Text(
          _value(client['name']),
          style: const TextStyle(
            color: orderText,
            fontWeight: FontWeight.w600,
            fontSize: 13,
          ),
        ),
        Text(
          _value(client['phone'], _value(address['recipient_phone'])),
          style: const TextStyle(color: orderMuted, fontSize: 12),
        ),
      ]),
      _infoBlock(Icons.location_on_outlined, 'Adresse de livraison', [
        Text(
          _value(address['formatted'], _value(address['address'])),
          style: const TextStyle(color: orderMuted, fontSize: 12, height: 1.4),
        ),
        if (address['latitude'] != null && address['longitude'] != null)
          TextButton.icon(
            onPressed: _showMap,
            icon: const Icon(Icons.map_outlined, size: 18),
            label: const Text(
              'Voir sur la carte',
              style: TextStyle(fontSize: 11),
            ),
          ),
      ]),
      _infoBlock(Icons.local_shipping_outlined, 'Mode de livraison', [
        Text(
          _value(_order['delivery_provider_label']),
          style: const TextStyle(
            color: orderText,
            fontSize: 12,
            fontWeight: FontWeight.w600,
          ),
        ),
        if (estimated != null) ...[
          const SizedBox(height: 6),
          const Text(
            'Livraison estimée',
            style: TextStyle(color: orderMuted, fontSize: 11),
          ),
          Text(
            orderDateOnly(estimated),
            style: const TextStyle(color: orderText, fontSize: 12),
          ),
        ] else if (_value(_order['estimated_delivery_label'], '').isNotEmpty)
          Text(
            _value(_order['estimated_delivery_label']),
            style: const TextStyle(color: orderMuted, fontSize: 11),
          ),
      ]),
    ];
    return OrderCard(
      padding: EdgeInsets.zero,
      child: LayoutBuilder(
        builder: (context, constraints) {
          if (constraints.maxWidth < 400 ||
              MediaQuery.textScalerOf(context).scale(12) > 16) {
            return Column(
              children: [
                for (var i = 0; i < blocks.length; i++) ...[
                  blocks[i],
                  if (i < 2) const Divider(height: 1, color: orderBorder),
                ],
              ],
            );
          }
          return IntrinsicHeight(
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Expanded(child: blocks[0]),
                const VerticalDivider(width: 1, color: orderBorder),
                Expanded(child: blocks[1]),
                const VerticalDivider(width: 1, color: orderBorder),
                Expanded(child: blocks[2]),
              ],
            ),
          );
        },
      ),
    );
  }

  Widget _infoBlock(IconData icon, String label, List<Widget> content) =>
      Padding(
        padding: const EdgeInsets.all(10),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: 28,
              height: 28,
              decoration: const BoxDecoration(
                color: orderSoftBlue,
                shape: BoxShape.circle,
              ),
              child: Icon(icon, size: 22, color: orderText),
            ),
            const SizedBox(width: 7),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    label,
                    style: const TextStyle(
                      color: orderText,
                      fontSize: 11,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 5),
                  ...content,
                ],
              ),
            ),
          ],
        ),
      );

  Future<void> _showMap() async {
    final address = orderMap(_order['delivery_address']);
    final latitude = double.tryParse('${address['latitude']}');
    final longitude = double.tryParse('${address['longitude']}');
    if (latitude == null || longitude == null) return;
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (context) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(20),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Text(
                'Adresse de livraison',
                style: TextStyle(
                  color: orderText,
                  fontSize: 18,
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(height: 12),
              Image.network(
                'https://staticmap.openstreetmap.de/staticmap.php?center=$latitude,$longitude&zoom=15&size=600x300&markers=$latitude,$longitude,red-pushpin',
                height: 220,
                width: double.infinity,
                fit: BoxFit.cover,
                errorBuilder: (_, __, ___) => const SizedBox(
                  height: 100,
                  child: Center(child: Text('La carte est indisponible.')),
                ),
              ),
              const SizedBox(height: 10),
              Text(_value(address['formatted'], _value(address['address']))),
              TextButton(
                onPressed: () => Navigator.pop(context),
                child: const Text('Fermer'),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _handoverCard() {
    final tracking = orderMap(_order['tracking']);
    final driver = orderMap(tracking['driver']);
    final assigned = driver.isNotEmpty && _value(driver['name'], '').isNotEmpty;
    return OrderCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Row(
            children: [
              Icon(Icons.local_shipping_outlined, color: orderText, size: 25),
              SizedBox(width: 9),
              Expanded(
                child: Text(
                  'Prise en charge livraison',
                  style: TextStyle(
                    color: orderText,
                    fontSize: 16,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          if (!assigned)
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(15),
              decoration: BoxDecoration(
                color: orderSoftBlue,
                borderRadius: BorderRadius.circular(12),
              ),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Icon(Icons.schedule_rounded, color: orderBlue),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Text(
                      '${tracking['assignment_message'] ?? 'En attente de l’affectation d’un livreur par OVANIE Logistics.'}',
                      style: const TextStyle(
                        color: orderText,
                        fontSize: 13,
                        height: 1.35,
                      ),
                    ),
                  ),
                ],
              ),
            )
          else
            LayoutBuilder(
              builder: (context, constraints) {
                final cells = <Widget>[
                  _driverValue('Livreur assigné', '${driver['name'] ?? '—'}'),
                  _driverValue('Téléphone', '${driver['phone'] ?? '—'}'),
                  _driverValue('Véhicule', '${driver['vehicle_label'] ?? '—'}'),
                  _driverValue(
                    'Immatriculation',
                    '${driver['vehicle_plate'] ?? '—'}',
                  ),
                  _driverValue(
                    'Mission / suivi',
                    '${tracking['mission_number'] ?? tracking['tracking_number'] ?? '—'}',
                  ),
                  _driverValue(
                    'Livraison estimée',
                    '${tracking['estimated_delivery_label'] ?? _order['estimated_delivery_label'] ?? '—'}',
                  ),
                ];
                if (constraints.maxWidth < 390) {
                  return Wrap(
                    spacing: 18,
                    runSpacing: 14,
                    children: cells
                        .map(
                          (e) => SizedBox(
                            width: (constraints.maxWidth - 18) / 2,
                            child: e,
                          ),
                        )
                        .toList(),
                  );
                }
                return Wrap(
                  spacing: 20,
                  runSpacing: 15,
                  children: cells
                      .map(
                        (e) => SizedBox(
                          width: (constraints.maxWidth - 40) / 3,
                          child: e,
                        ),
                      )
                      .toList(),
                );
              },
            ),
          const SizedBox(height: 14),
          _providerFields(),
          LayoutBuilder(
            builder: (context, constraints) {
              final call = SizedBox(
                height: 52,
                child: FilledButton.icon(
                  onPressed: _value(driver['phone'], '').isEmpty
                      ? null
                      : () => _openPhone('${driver['phone']}'),
                  icon: const Icon(Icons.phone_rounded),
                  label: const Text(
                    'Appeler le livreur',
                    style: TextStyle(fontWeight: FontWeight.w600, fontSize: 11),
                  ),
                  style: FilledButton.styleFrom(
                    backgroundColor: orderOrange,
                    foregroundColor: Colors.white,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(10),
                    ),
                  ),
                ),
              );
              final logistics = SizedBox(
                height: 52,
                child: OutlinedButton.icon(
                  onPressed: () => _openPhone('01 61 78 18 18'),
                  icon: const Icon(
                    Icons.headset_mic_outlined,
                    color: orderText,
                  ),
                  label: const Text(
                    'Contacter OVANIE Logistics',
                    style: TextStyle(
                      color: orderText,
                      fontWeight: FontWeight.w600,
                      fontSize: 11,
                    ),
                  ),
                  style: OutlinedButton.styleFrom(
                    side: const BorderSide(color: orderBlue),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(10),
                    ),
                  ),
                ),
              );
              if (constraints.maxWidth < 390) {
                return Column(
                  children: [
                    SizedBox(width: double.infinity, child: call),
                    const SizedBox(height: 9),
                    SizedBox(width: double.infinity, child: logistics),
                  ],
                );
              }
              return Row(
                children: [
                  Expanded(child: call),
                  const SizedBox(width: 14),
                  Expanded(child: logistics),
                ],
              );
            },
          ),
        ],
      ),
    );
  }

  Widget _driverValue(String label, String value) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Text(label, style: const TextStyle(color: orderMuted, fontSize: 11.5)),
      const SizedBox(height: 3),
      Text(
        value,
        maxLines: 2,
        overflow: TextOverflow.ellipsis,
        style: const TextStyle(
          color: orderText,
          fontSize: 13.5,
          fontWeight: FontWeight.w800,
        ),
      ),
    ],
  );

  Widget _providerFields() {
    final mixed = _order['delivery_provider'] == 'mixed';
    return Column(
      children: [
        if (mixed)
          Padding(
            padding: const EdgeInsets.only(bottom: 12),
            child: DropdownButtonFormField<String>(
              initialValue: _provider,
              decoration: const InputDecoration(
                labelText: 'Groupe de livraison',
              ),
              items: const [
                DropdownMenuItem(
                  value: 'ovanie',
                  child: Text('OVANIE Logistics'),
                ),
                DropdownMenuItem(
                  value: 'seller',
                  child: Text('Livraison vendeur'),
                ),
              ],
              onChanged: _editable
                  ? (value) => setState(() {
                      _provider = value;
                      _handoverConfirmed = false;
                      _documentChecked = false;
                      _clientNotified = false;
                    })
                  : null,
            ),
          ),
        if (_provider == 'seller' && !_alreadyShipped) ...[
          for (final field in [
            (_driverName, 'Nom du chauffeur'),
            (_driverPhone, 'Téléphone du chauffeur'),
            (_vehiclePlate, 'Immatriculation'),
          ])
            Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: TextField(
                controller: field.$1,
                enabled: _editable,
                keyboardType: field.$1 == _driverPhone
                    ? TextInputType.phone
                    : TextInputType.text,
                onChanged: (_) => setState(() {}),
                decoration: InputDecoration(
                  labelText: field.$2,
                  border: const OutlineInputBorder(),
                ),
              ),
            ),
        ],
      ],
    );
  }

  Widget _itemsSection() {
    final items = orderList(_order['items'])
        .map(orderMap)
        .where(
          (item) =>
              _order['delivery_provider'] != 'mixed' ||
              _provider == null ||
              item['delivery_provider'] == _provider,
        )
        .toList();
    final visible = _showAll ? items : items.take(3).toList();
    return Column(
      children: [
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(10),
            border: Border.all(color: orderBorder),
          ),
          child: Row(
            children: [
              const Icon(Icons.inventory_2_outlined, color: orderText),
              const SizedBox(width: 8),
              const Expanded(
                child: Text(
                  'Articles à expédier',
                  style: TextStyle(
                    color: orderText,
                    fontSize: 16,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
              if (items.length > 3)
                TextButton.icon(
                  onPressed: () => setState(() => _showAll = !_showAll),
                  icon: const Icon(Icons.visibility_outlined, size: 18),
                  label: Text(_showAll ? 'Réduire' : 'Tout voir'),
                ),
            ],
          ),
        ),
        for (final item in visible) _item(item),
        if (items.isEmpty)
          const Padding(
            padding: EdgeInsets.all(16),
            child: Text('Aucun article dans ce groupe de livraison.'),
          ),
      ],
    );
  }

  Widget _item(Map<String, dynamic> item) {
    final shipped = _shipped(item);
    return Container(
      margin: const EdgeInsets.only(top: 5),
      padding: const EdgeInsets.all(10),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: orderBorder),
      ),
      child: LayoutBuilder(
        builder: (context, constraints) {
          final product = Row(
            children: [
              OrderProductImage(url: item['image_url'], size: 58),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      _value(item['name'], 'Produit'),
                      style: const TextStyle(
                        color: orderText,
                        fontSize: 13,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      [item['brand'], item['weight_label'], item['unit_label']]
                          .map((e) => _value(e, ''))
                          .where((e) => e.isNotEmpty)
                          .join('  •  '),
                      style: const TextStyle(color: orderMuted, fontSize: 11),
                    ),
                    const SizedBox(height: 5),
                    OrderStatusPill(
                      status: shipped ? 'shipped' : item['vendor_status'],
                      label: shipped
                          ? 'Expédié'
                          : item['vendor_status'] == 'ready'
                          ? 'Prêt'
                          : orderStatusLabel(item['vendor_status']),
                      compact: true,
                    ),
                  ],
                ),
              ),
            ],
          );
          final quantity = Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      shipped ? 'Quantité expédiée' : 'Quantité à expédier',
                      style: const TextStyle(color: orderMuted, fontSize: 11),
                    ),
                    const SizedBox(height: 5),
                    Text(
                      '${_value(item['quantity'])} ${_value(item['unit_label'], '')}',
                      style: const TextStyle(
                        color: orderText,
                        fontSize: 13,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              Icon(
                shipped ? Icons.check_circle : Icons.inventory_2_outlined,
                color: shipped ? orderGreen : orderOrange,
                size: 25,
              ),
            ],
          );
          if (constraints.maxWidth < 400 ||
              MediaQuery.textScalerOf(context).scale(12) > 16) {
            return Column(
              children: [
                product,
                const SizedBox(height: 10),
                Padding(
                  padding: const EdgeInsets.only(left: 68),
                  child: quantity,
                ),
              ],
            );
          }
          return Row(
            children: [
              Expanded(flex: 6, child: product),
              const SizedBox(width: 14),
              Expanded(flex: 4, child: quantity),
            ],
          );
        },
      ),
    );
  }

  Widget _controlsBox() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: const Color(0xFFFFF5ED),
        borderRadius: BorderRadius.circular(14),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 50,
            height: 50,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              color: orderOrange,
              borderRadius: BorderRadius.circular(12),
            ),
            child: const Icon(
              Icons.assignment_turned_in_rounded,
              color: Colors.white,
              size: 28,
            ),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Contrôles avant expédition',
                  style: TextStyle(
                    color: orderOrange,
                    fontSize: 17,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 7),
                _check(
                  'Les articles préparés ont été remis au livreur',
                  _handoverConfirmed,
                  (v) => setState(() => _handoverConfirmed = v),
                ),
                _check(
                  'Le bon de livraison a été vérifié',
                  _documentChecked,
                  (v) => setState(() => _documentChecked = v),
                ),
                _check(
                  'Le client a été notifié de l’expédition',
                  _clientNotified,
                  (v) => setState(() => _clientNotified = v),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _check(String label, bool value, ValueChanged<bool> onChanged) =>
      InkWell(
        onTap: _editable ? () => onChanged(!value) : null,
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 4),
          child: Row(
            children: [
              Container(
                width: 24,
                height: 24,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: value ? orderOrange : Colors.white,
                  shape: BoxShape.circle,
                  border: Border.all(
                    color: value ? orderOrange : const Color(0xFFB7C2D3),
                  ),
                ),
                child: value
                    ? const Icon(
                        Icons.check_rounded,
                        color: Colors.white,
                        size: 16,
                      )
                    : null,
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  label,
                  style: const TextStyle(color: orderText, fontSize: 12.5),
                ),
              ),
            ],
          ),
        ),
      );

  Widget _actions() {
    final back = OutlinedButton.icon(
      onPressed: _saving ? null : () => Navigator.pop(context),
      icon: const Icon(Icons.arrow_back),
      label: const Text('Retour'),
      style: OutlinedButton.styleFrom(
        minimumSize: const Size(0, 52),
        foregroundColor: orderText,
        side: const BorderSide(color: orderBorder),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(9)),
      ),
    );
    final primary = FilledButton(
      onPressed: _loading || _saving || _error != null
          ? null
          : _alreadyShipped
          ? () => Navigator.of(context).pushReplacement(
              MaterialPageRoute(
                builder: (_) => OrderTrackingScreen(
                  orderId: widget.orderId,
                  initialOrder: _order,
                ),
              ),
            )
          : _canConfirm
          ? _confirmExpedition
          : null,
      style: FilledButton.styleFrom(
        backgroundColor: orderOrange,
        disabledBackgroundColor: const Color(0xFFFFD5BD),
        disabledForegroundColor: const Color(0xFF976747),
        minimumSize: const Size(0, 52),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(9)),
      ),
      child: _saving
          ? const SizedBox.square(
              dimension: 20,
              child: CircularProgressIndicator(
                color: Colors.white,
                strokeWidth: 2,
              ),
            )
          : Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Flexible(
                  child: Text(
                    _alreadyShipped
                        ? 'Suivre la livraison'
                        : 'Confirmer l’expédition',
                    textAlign: TextAlign.center,
                  ),
                ),
                const SizedBox(width: 10),
                const Icon(Icons.arrow_forward, size: 22),
              ],
            ),
    );
    return Row(
      children: [
        Expanded(child: back),
        const SizedBox(width: 16),
        Expanded(flex: 2, child: primary),
      ],
    );
  }
}
