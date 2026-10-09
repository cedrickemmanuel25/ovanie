import 'package:flutter/material.dart';

import '../../core/network/api_client.dart';
import '../../core/ui/vendor_mockup_ui.dart';
import '../../core/ui/vendor_select.dart';
import '../../data/vendor_repository.dart';
import 'order_tracking_screen.dart';
import 'order_ui.dart';
import 'order_widgets.dart';

/// Expédition d'une commande prête : prise en charge par OVANIE Logistics ou
/// par le chauffeur de la boutique, contrôles, puis confirmation du départ.
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
  static const _checks = <String>[
    'Les articles préparés ont été remis au livreur',
    'Le bon de livraison a été vérifié',
    'Le client a été notifié de l’expédition',
  ];
  static const _providers = <String, String>{'ovanie': 'OVANIE Logistics', 'seller': 'Livraison vendeur'};

  /// Numéro du support OVANIE Logistics (coordonnées officielles).
  static const _logisticsPhone = '0161780000';

  Map<String, dynamic> _order = <String, dynamic>{};
  final List<bool> _confirmed = List<bool>.filled(_checks.length, false);
  bool _loading = true;
  bool _saving = false;
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

  bool get _alreadyShipped =>
      ['picked_up', 'in_transit', 'late', 'delivered'].contains(_order['delivery_status']) ||
      ['shipped', 'delivered'].contains(_order['vendor_status']);

  bool get _editable => !_loading && !_saving && _error == null && _order['vendor_status'] == 'ready' && !_alreadyShipped;

  bool get _driverComplete =>
      _driverName.text.trim().isNotEmpty && _driverPhone.text.trim().isNotEmpty && _vehiclePlate.text.trim().isNotEmpty;

  @override
  void initState() {
    super.initState();
    if (widget.initialOrder != null) {
      _order = Map<String, dynamic>.from(widget.initialOrder!);
    }
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
  }

  Future<void> _load() async {
    if (_saving || !mounted) return;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final data = await VendorRepository.instance.order(widget.orderId, fresh: true);
      final detail = orderMap(data['order']);
      if (!mounted) return;
      setState(() {
        if (detail.isEmpty) {
          _error = 'Cette commande est indisponible.';
          return;
        }
        _order = detail;
        final provider = '${detail['delivery_provider'] ?? ''}';
        _provider = _providers.containsKey(provider) ? provider : null;
        final driver = orderMap(orderMap(detail['tracking'])['driver']);
        _driverName.text = orderValue(driver['name'], '');
        _driverPhone.text = orderValue(driver['phone'], '');
        _vehiclePlate.text = orderValue(driver['vehicle_plate'], '');
      });
    } catch (e) {
      if (mounted) setState(() => _error = ApiClient.friendlyError(e));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _snack(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(message)));
  }

  Future<void> _confirmExpedition() async {
    if (!_editable) return;
    final provider = _provider;
    if (provider == null) {
      _snack('Choisissez le groupe de livraison.');
      return;
    }
    if (provider == 'seller' && !_driverComplete) {
      _snack('Renseignez le nom, le téléphone et l’immatriculation du chauffeur.');
      return;
    }
    if (provider == 'seller' && _driverPhone.text.replaceAll(RegExp(r'[^0-9]'), '').length < 10) {
      _snack('Le téléphone du chauffeur doit contenir 10 chiffres.');
      return;
    }
    if (!_confirmed.every((value) => value)) {
      _snack('Confirmez les trois contrôles avant l’expédition.');
      return;
    }
    FocusManager.instance.primaryFocus?.unfocus();
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      final now = DateTime.now();
      String two(int value) => value.toString().padLeft(2, '0');
      final values = <String, dynamic>{
        'shipment_date': '${now.year}-${two(now.month)}-${two(now.day)}',
        'delivery_provider': provider,
        if (provider == 'seller') ...{
          'driver_name': _driverName.text.trim(),
          'driver_phone': _driverPhone.text.trim(),
          'vehicle_plate': _vehiclePlate.text.trim(),
        },
      };

      await VendorRepository.instance.shipOrder(widget.orderId, values);
      if (!mounted) return;
      _snack('Expédition confirmée.');
      Navigator.of(context).pushReplacement<void, void>(
        MaterialPageRoute<void>(
          builder: (_) => OrderTrackingScreen(orderId: widget.orderId, initialOrder: Map<String, dynamic>.from(_order)),
        ),
      );
    } catch (e) {
      if (mounted) setState(() => _error = ApiClient.friendlyError(e));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return OrderPage(
      title: 'Expédition de commande',
      subtitle: 'Confirmez la prise en charge de la livraison.',
      loading: _loading,
      error: _error,
      hasContent: _order.isNotEmpty,
      onRefresh: _load,
      actions: [
        MkBarButton(label: 'Retour', icon: Icons.arrow_back_rounded, onTap: _saving ? null : () => Navigator.pop(context)),
        MkBarButton(
          label: 'Confirmer l’expédition',
          icon: Icons.arrow_forward_rounded,
          filled: true,
          loading: _saving,
          onTap: _editable ? _confirmExpedition : null,
        ),
      ],
      children: [
        OrderHeadCard(order: _order, currentStep: 3),
        OrderInfosCard(order: _order),
        if (_alreadyShipped)
          const MkInfoBox(
            icon: Icons.check_circle_outline_rounded,
            child: Text('Cette commande est déjà expédiée. Suivez sa livraison depuis le détail de la commande.'),
          ),
        _handoverCard(),
        _itemsCard(),
        if (!_alreadyShipped) _checksCard(),
        const TrueDataNotice(message: 'Le suivi de la livraison sera mis à jour après la prise en charge.'),
      ],
    );
  }

  Widget _handoverCard() {
    final tracking = orderMap(_order['tracking']);
    final driver = orderMap(tracking['driver']);
    final seller = _provider == 'seller';
    final mixed = _order['delivery_provider'] == 'mixed';
    final assigned = orderValue(driver['name'], '').isNotEmpty;
    final driverPhone = seller ? _driverPhone.text.trim() : orderValue(driver['phone'], '');

    return OrderCard(
      borderColor: orderBlue,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          OrderSectionTitle(seller ? 'Livraison vendeur' : 'Prise en charge livraison'),
          const SizedBox(height: 6),
          Center(
            child: Image.asset(
              'assets/images/mockups/${seller ? 'ship_seller' : 'ship_ovanie'}.jpg',
              height: 120,
              excludeFromSemantics: true,
            ),
          ),
          const SizedBox(height: 8),
          if (mixed) ...[
            const MkLabel('Groupe de livraison', required: true),
            _providerSelector(),
            const SizedBox(height: 6),
            const Text(
              'Options disponibles : OVANIE Logistics, Livraison vendeur',
              style: TextStyle(color: Mk.muted, fontSize: 12, fontWeight: FontWeight.w500),
            ),
            const SizedBox(height: 12),
          ],
          if (seller && !_alreadyShipped) ...[
            if (orderValue(tracking['assignment_message'], '').isNotEmpty)
              Padding(
                padding: const EdgeInsets.only(bottom: 12),
                child: Text(
                  orderValue(tracking['assignment_message']),
                  style: const TextStyle(color: Mk.muted, fontSize: 12.5, height: 1.4, fontWeight: FontWeight.w500),
                ),
              ),
            _driverField(_driverName, 'Nom du chauffeur', 'Ex. Yao Konan', Icons.person_outline_rounded),
            const SizedBox(height: 10),
            _driverField(_driverPhone, 'Téléphone du chauffeur', '07 00 00 00 00', Icons.call_outlined, phone: true),
            const SizedBox(height: 10),
            _driverField(_vehiclePlate, 'Immatriculation', 'Ex. 1234 AB 01', Icons.directions_car_outlined, upperCase: true),
          ] else if (!assigned)
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(color: Mk.softBlue, borderRadius: BorderRadius.circular(10)),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Icon(Icons.schedule_rounded, color: orderBlue, size: 24),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Text(
                      orderValue(
                        tracking['assignment_message'],
                        'En attente de l’affectation d’un livreur par OVANIE Logistics.',
                      ),
                      style: const TextStyle(color: orderBlue, fontSize: 13, height: 1.35, fontWeight: FontWeight.w600),
                    ),
                  ),
                ],
              ),
            )
          else
            _driverDetails(tracking, driver),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: MkBarButton(
                  label: seller ? 'Appeler le chauffeur' : 'Appeler le livreur',
                  icon: Icons.call_rounded,
                  color: Mk.icon,
                  height: 44,
                  onTap: driverPhone.isEmpty
                      ? null
                      : () => showOrderPhoneSheet(
                            context,
                            name: seller ? orderValue(_driverName.text, 'Chauffeur') : orderValue(driver['name'], 'Livreur'),
                            phone: driverPhone,
                          ),
                ),
              ),
              if (!seller) ...[
                const SizedBox(width: 8),
                Expanded(
                  child: MkBarButton(
                    label: 'OVANIE Logistics',
                    icon: Icons.headset_mic_rounded,
                    color: orderBlue,
                    height: 44,
                    onTap: () => showOrderPhoneSheet(context, name: 'OVANIE Logistics', phone: _logisticsPhone),
                  ),
                ),
              ],
            ],
          ),
        ],
      ),
    );
  }

  Widget _providerSelector() {
    return Material(
      color: Colors.white,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
        side: const BorderSide(color: orderBlue, width: 1.3),
      ),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: !_editable
            ? null
            : () async {
                final picked = await showVendorSelect<String>(
                  context,
                  title: 'Groupe de livraison',
                  items: _providers.keys.toList(),
                  label: (value) => _providers[value]!,
                  selected: _provider,
                );
                if (picked != null && mounted) setState(() => _provider = picked);
              },
        child: Padding(
          padding: const EdgeInsets.fromLTRB(14, 12, 8, 12),
          child: Row(
            children: [
              Expanded(
                child: Text(
                  _providers[_provider] ?? 'Sélectionnez le groupe de livraison',
                  style: TextStyle(
                    color: _provider == null ? Mk.hint : Mk.navy,
                    fontSize: 14.5,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
              const Icon(Icons.keyboard_arrow_down_rounded, color: orderBlue, size: 24),
            ],
          ),
        ),
      ),
    );
  }

  Widget _driverField(
    TextEditingController controller,
    String label,
    String hint,
    IconData icon, {
    bool phone = false,
    bool upperCase = false,
  }) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        MkLabel(label, required: true),
        TextField(
          controller: controller,
          enabled: _editable,
          keyboardType: phone ? TextInputType.phone : TextInputType.text,
          textCapitalization: upperCase ? TextCapitalization.characters : TextCapitalization.words,
          textInputAction: TextInputAction.next,
          onChanged: (_) => setState(() {}),
          style: mkFieldText,
          decoration: mkInput(hint: hint, icon: icon),
        ),
      ],
    );
  }

  Widget _driverDetails(Map<String, dynamic> tracking, Map<String, dynamic> driver) {
    final rows = <(String, String)>[
      ('Livreur assigné', orderValue(driver['name'])),
      ('Téléphone', orderValue(driver['phone'], '') == '' ? '—' : orderPhoneLabel('${driver['phone']}')),
      ('Véhicule', orderValue(driver['vehicle_label'])),
      ('Immatriculation', orderValue(driver['vehicle_plate'])),
      ('Mission / suivi', orderValue(tracking['mission_number'], orderValue(tracking['tracking_number']))),
      ('Livraison estimée', orderValue(orderShortDate(tracking['estimated_delivery_at']))),
    ];
    return Column(
      children: [
        for (final row in rows)
          Padding(
            padding: const EdgeInsets.symmetric(vertical: 4),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  flex: 5,
                  child: Text(row.$1, style: const TextStyle(color: Mk.muted, fontSize: 13, fontWeight: FontWeight.w500)),
                ),
                Expanded(
                  flex: 6,
                  child: Text(row.$2, style: const TextStyle(color: Mk.navy, fontSize: 13.5, fontWeight: FontWeight.w700)),
                ),
              ],
            ),
          ),
      ],
    );
  }

  Widget _itemsCard() {
    final items = orderList(_order['items']).map(orderMap).toList();
    final visible = _showAll ? items : items.take(3).toList();
    return OrderCard(
      padding: const EdgeInsets.fromLTRB(14, 12, 14, 4),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Expanded(child: OrderSectionTitle('Articles à expédier')),
              if (items.length > 3)
                TextButton.icon(
                  onPressed: () => setState(() => _showAll = !_showAll),
                  style: TextButton.styleFrom(
                    foregroundColor: orderBlue,
                    minimumSize: const Size(48, 40),
                    textStyle: const TextStyle(fontFamily: Mk.font, fontSize: 13, fontWeight: FontWeight.w700),
                  ),
                  icon: Icon(_showAll ? Icons.visibility_off_outlined : Icons.visibility_outlined, size: 19),
                  label: Text(_showAll ? 'Réduire' : 'Tout voir (${items.length})'),
                ),
            ],
          ),
          if (items.isEmpty)
            const Padding(
              padding: EdgeInsets.symmetric(vertical: 12),
              child: Text('Aucun article à expédier pour votre boutique.', style: TextStyle(color: Mk.muted, fontSize: 13.5)),
            ),
          for (var i = 0; i < visible.length; i++) ...[
            if (i > 0) const Divider(height: 1, color: orderBorder),
            OrderItemLine(
              item: visible[i],
              quantityLabel: 'Quantité à expédier',
              tag: const OrderTag('Prêt', color: orderGreen),
            ),
          ],
        ],
      ),
    );
  }

  Widget _checksCard() {
    return OrderCard(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Padding(
            padding: EdgeInsets.only(top: 2),
            child: Icon(Icons.assignment_turned_in_outlined, color: Mk.orange, size: 30),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const OrderSectionTitle('Contrôles avant expédition'),
                const SizedBox(height: 4),
                for (var i = 0; i < _checks.length; i++)
                  OrderCheckLine(
                    label: _checks[i],
                    value: _confirmed[i],
                    onChanged: _editable ? (value) => setState(() => _confirmed[i] = value) : null,
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
