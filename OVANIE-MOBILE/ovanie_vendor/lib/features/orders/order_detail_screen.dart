import 'package:flutter/material.dart';
import '../../core/sync/live_sync_service.dart';
import 'package:flutter/services.dart';

import '../../core/network/api_client.dart';
import '../../core/ui/mk_dialogs.dart';
import '../../core/ui/vendor_mockup_ui.dart';
import '../../data/vendor_repository.dart';
import '../finance/data_ui.dart' show downloadVendorFile;
import 'order_expedition_screen.dart';
import 'order_preparation_screen.dart';
import 'order_tracking_screen.dart';
import 'order_ui.dart';
import 'order_widgets.dart';

class OrderDetailScreen extends StatefulWidget {
  const OrderDetailScreen({
    super.key,
    required this.orderId,
    this.initialOrder,
  });

  final int orderId;
  final Map<String, dynamic>? initialOrder;

  @override
  State<OrderDetailScreen> createState() => _OrderDetailScreenState();
}

class _OrderDetailScreenState extends State<OrderDetailScreen>
    with LiveSyncListener<OrderDetailScreen> {
  Map<String, dynamic> _order = <String, dynamic>{};
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    if (widget.initialOrder != null) {
      _order = Map<String, dynamic>.from(widget.initialOrder!);
      _loading = true;
    }
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
    listenLiveSync({'shop'}, () => _load(silent: true));
  }

  Future<void> _load({bool silent = false}) async {
    if (!silent && mounted) {
      setState(() {
        _loading = true;
        _error = null;
      });
    }
    try {
      final data = await VendorRepository.instance.order(
        widget.orderId,
        fresh: true,
      );
      final detail = orderMap(data['order']);
      if (!mounted) return;
      setState(() {
        if (detail.isEmpty) {
          _error = 'Le détail de cette commande est indisponible.';
        } else {
          _order = detail;
        }
        if (detail.isNotEmpty) _error = null;
      });
    } catch (e) {
      if (mounted) setState(() => _error = ApiClient.friendlyError(e));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  /// Résumé de la commande et passage d'étape montrés dans les confirmations.
  Widget _confirmSummary() {
    final items = orderList(_order['items']);
    final count = items.length;
    return MkSheetSummary(
      child: Wrap(
        alignment: WrapAlignment.spaceBetween,
        crossAxisAlignment: WrapCrossAlignment.center,
        spacing: 12,
        runSpacing: 10,
        children: [
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                orderNumberLabel(_order['order_number']),
                style: const TextStyle(color: Mk.navy, fontSize: 17, fontWeight: FontWeight.w800),
              ),
              Text(
                _text(orderMap(_order['client'])['name'], 'Client OVANIE'),
                style: const TextStyle(color: Mk.muted, fontSize: 13.5, fontWeight: FontWeight.w500),
              ),
            ],
          ),
          Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.inventory_2_outlined, color: Mk.icon, size: 20),
              const SizedBox(width: 6),
              Text(
                count > 1 ? '$count articles' : '$count article',
                style: const TextStyle(color: Mk.icon, fontSize: 13.5, fontWeight: FontWeight.w600),
              ),
              const SizedBox(width: 14),
              const Icon(Icons.payments_outlined, color: Mk.icon, size: 20),
              const SizedBox(width: 6),
              Text(
                _amount(_order['seller_products_total'] ?? _order['public_products_total']),
                style: const TextStyle(color: Mk.navy, fontSize: 13.5, fontWeight: FontWeight.w700),
              ),
            ],
          ),
          OrderStatusPill(status: _order['vendor_status']),
        ],
      ),
    );
  }

  Widget _stepChange(String from, String to, IconData toIcon) => Row(
    mainAxisAlignment: MainAxisAlignment.center,
    children: [
      Flexible(
        child: Column(
          children: [
            Container(
              width: 42,
              height: 42,
              decoration: const BoxDecoration(color: orderGreen, shape: BoxShape.circle),
              child: const Icon(Icons.check_rounded, color: Colors.white, size: 26),
            ),
            const SizedBox(height: 6),
            Text(
              from,
              textAlign: TextAlign.center,
              style: const TextStyle(color: Mk.icon, fontSize: 13.5, fontWeight: FontWeight.w600),
            ),
          ],
        ),
      ),
      const Padding(
        padding: EdgeInsets.fromLTRB(18, 0, 18, 22),
        child: Icon(Icons.arrow_forward_rounded, color: Mk.hint, size: 26),
      ),
      Flexible(
        child: Column(
          children: [
            Container(
              width: 42,
              height: 42,
              decoration: BoxDecoration(
                color: Mk.orange,
                shape: BoxShape.circle,
                border: Border.all(
                  color: Mk.orange.withValues(alpha: .25),
                  width: 4,
                  strokeAlign: BorderSide.strokeAlignOutside,
                ),
              ),
              child: Icon(toIcon, color: Colors.white, size: 22),
            ),
            const SizedBox(height: 6),
            Text(
              to,
              textAlign: TextAlign.center,
              style: const TextStyle(color: Color(0xFFE85400), fontSize: 13.5, fontWeight: FontWeight.w700),
            ),
          ],
        ),
      ),
    ],
  );

  Future<void> _confirmPreparation() async {
    final ok = await showMkConfirmSheet(
      context,
      icon: Icons.inventory_2_outlined,
      iconColor: Mk.orange,
      title: 'Préparer la commande ?',
      summary: _confirmSummary(),
      body: _stepChange('Commande reçue', 'Préparation', Icons.inventory_2_outlined),
      message: 'Vous allez vérifier et préparer les articles de cette commande.',
      confirmLabel: 'Préparer la commande',
      confirmIcon: Icons.inventory_2_outlined,
    );
    if (ok && mounted) await _openPreparation();
  }

  Future<void> _confirmExpedition() async {
    final ok = await showMkConfirmSheet(
      context,
      icon: Icons.local_shipping_outlined,
      iconColor: const Color(0xFF1668E3),
      title: 'Expédier la commande ?',
      summary: _confirmSummary(),
      body: _stepChange('Préparation', 'Expédiée', Icons.local_shipping_outlined),
      message: 'Vous allez indiquer le chauffeur, puis confirmer l’expédition de cette commande.',
      confirmLabel: 'Expédier la commande',
      confirmIcon: Icons.local_shipping_outlined,
    );
    if (ok && mounted) await _openExpedition();
  }

  Future<void> _openPreparation() async {
    await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) => OrderPreparationScreen(
          orderId: widget.orderId,
          initialOrder: _order,
        ),
      ),
    );
    if (mounted) _load(silent: true);
  }

  Future<void> _openExpedition() async {
    await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) => OrderExpeditionScreen(
          orderId: widget.orderId,
          initialOrder: _order,
        ),
      ),
    );
    if (mounted) _load(silent: true);
  }

  Future<void> _openTracking() async {
    await Navigator.of(context).push<void>(
      MaterialPageRoute(
        builder: (_) =>
            OrderTrackingScreen(orderId: widget.orderId, initialOrder: _order),
      ),
    );
    if (mounted) _load(silent: true);
  }

  Future<void> _sendMissionLink() async {
    final mission = orderMap(_order['seller_mission']);
    final url = _text(mission['url'], '');
    if (url.isEmpty) return;
    final digits = _text(mission['driver_phone'], '').replaceAll(RegExp(r'[^0-9]'), '');
    final phone = digits.length == 10 ? '225$digits' : digits;
    final message = Uri.encodeComponent(
      'OVANIE : mission de livraison ${_text(_order['order_number'], '')}. '
      'Ouvrez ce lien sur votre téléphone et gardez la page ouverte jusqu’à la remise : $url',
    );
    var opened = false;
    try {
      opened = await const MethodChannel('ovanie/external_url').invokeMethod<bool>(
            'openUrl',
            {'url': 'https://wa.me/$phone?text=$message'},
          ) ??
          false;
    } catch (_) {}
    if (!opened) {
      await Clipboard.setData(ClipboardData(text: url));
      if (mounted) {
        mkToast(context, 'Lien de mission copié. Envoyez-le à votre chauffeur.');
      }
    }
  }

  Future<void> _reportCash() async {
    final confirmed = await showMkConfirmSheet(
      context,
      icon: Icons.payments_outlined,
      iconColor: orderGreen,
      title: 'Encaissement reçu ?',
      summary: _confirmSummary(),
      message:
          'Confirmez que le client a réglé cette commande à la livraison. OVANIE vérifie ensuite le montant avant votre reversement.',
      confirmLabel: 'Confirmer l’encaissement',
      confirmColor: orderGreen,
    );
    if (!confirmed || !mounted) return;
    try {
      final message = await VendorRepository.instance.reportCashPayment(widget.orderId);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(message ?? 'Encaissement signalé à OVANIE.')),
      );
      await _load(silent: true);
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(ApiClient.friendlyError(e))),
        );
      }
    }
  }

  Future<void> _contactClient() async {
    final client = orderMap(_order['client']);
    final phone = _text(client['phone'], _text(orderMap(_order['delivery_address'])['recipient_phone'], ''));
    if (phone.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Aucun numéro de téléphone client disponible.')),
      );
      return;
    }
    await showOrderPhoneSheet(context, name: _text(client['name'], 'Client'), phone: phone);
  }

  bool get _paid => orderIsPaid(_order['payment_status']);
  bool get _ovanie => orderUsesOvanieLogistics(_order);

  /// Commande passée quand la boutique utilisait l'autre mode logistique.
  bool get _otherMode {
    final shopMode = _text(_order['shop_logistics_type'], '');
    final provider = _text(_order['delivery_provider'], '');
    if (shopMode.isEmpty || !const ['seller', 'ovanie'].contains(provider)) return false;
    return provider != shopMode;
  }

  Widget _otherModeNotice() => MkInfoBox(
    icon: Icons.history_rounded,
    child: Text(
      _ovanie
          ? 'Commande passée quand votre boutique utilisait OVANIE Logistics. Elle garde ce mode de livraison.'
          : 'Commande passée quand votre boutique livrait elle-même. Elle garde ce mode de livraison.',
    ),
  );

  String _text(Object? value, [String fallback = '—']) => orderValue(value, fallback);

  String _amount(Object? value) => value == null ? '—' : orderMoney(value);

  /// Action principale selon l'état : préparer, expédier ou suivre la livraison.
  ({String label, IconData icon, VoidCallback onTap})? get _primaryAction {
    final status = _text(_order['vendor_status'], '');
    final step = orderWorkflowStep(status, deliveryStatus: _order['delivery_status']);
    final mission = orderMap(_order['seller_mission']);
    // Chauffeur déjà désigné : la commande part dès que le GPS détecte le départ.
    final sellerDriverSet = _text(mission['url'], '').isNotEmpty;
    // OVANIE Logistics : une fois la commande prête, le vendeur n'« expédie » pas
    // lui-même. Il attend le livreur réservé et suit la prise en charge.
    final track = step >= 3 || (_ovanie && status == 'ready') || (status == 'ready' && sellerDriverSet);
    if (track) return (label: 'Suivre la livraison', icon: Icons.local_shipping_outlined, onTap: _openTracking);
    if (status == 'ready' && !_ovanie) {
      return (label: 'Expédier la commande', icon: Icons.local_shipping_outlined, onTap: _confirmExpedition);
    }
    if (['pending', 'accepted', 'preparing'].contains(status)) {
      return (label: 'Préparer la commande', icon: Icons.inventory_2_outlined, onTap: _confirmPreparation);
    }
    return null;
  }

  /// « Livraison par votre boutique » se suffit ; les autres modes sont préfixés.
  String get _deliveryLine {
    final label = _text(_order['delivery_provider_label'], '');
    if (label.isEmpty) return 'Livraison : à définir';
    return label.toLowerCase().startsWith('livraison') ? label : 'Livraison : $label';
  }

  @override
  Widget build(BuildContext context) {
    final primary = _primaryAction;
    final hasContent = _order.isNotEmpty;
    return OrderPage(
      title: 'Détail de la commande',
      subtitle: 'Suivez et gérez cette commande',
      loading: _loading,
      error: _error,
      hasContent: hasContent,
      onRefresh: _load,
      actions: [
        if (!_ovanie)
          MkBarButton(label: 'Contacter le client', icon: Icons.chat_bubble_outline_rounded, onTap: _contactClient),
        if (primary != null) MkBarButton(label: primary.label, icon: primary.icon, filled: true, onTap: primary.onTap),
      ],
      children: [
        _summaryCard(),
        if (_otherMode) _otherModeNotice(),
        if (_ovanie && _text(orderMap(_order['tracking'])['assignment_message'], '').isNotEmpty)
          OrderLogisticsBanner(order: _order),
        _trackingCard(),
        _itemsCard(),
        _slipCard(),
        ..._extraActions(),
        _clientCard(),
        _amountCard(),
      ],
    );
  }

  Widget _summaryCard() {
    final method = _text(_order['payment_method_label'], _text(_order['payment_method'], ''));
    final date = orderShortDate(_order['created_at']);
    return OrderCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: FittedBox(
                  fit: BoxFit.scaleDown,
                  alignment: Alignment.centerLeft,
                  child: Text(
                    orderNumberLabel(_order['order_number']),
                    style: const TextStyle(
                      color: Mk.navy,
                      fontSize: 23,
                      fontWeight: FontWeight.w800,
                      letterSpacing: -.4,
                    ),
                  ),
                ),
              ),
              const SizedBox(width: 8),
              OrderStatusPill(status: _order['vendor_status']),
            ],
          ),
          const SizedBox(height: 10),
          Wrap(
            spacing: 16,
            runSpacing: 8,
            crossAxisAlignment: WrapCrossAlignment.center,
            children: [
              if (date.isNotEmpty) _inline(Icons.calendar_month_outlined, date),
              if (method.isNotEmpty) _inline(Icons.credit_card_outlined, method),
              OrderPaymentPill(
                status: _order['payment_status'],
                label: _paid ? 'Payée' : _text(_order['payment_status_label'], 'En attente'),
                withIcon: true,
              ),
            ],
          ),
          const Divider(height: 22, color: orderBorder),
          Row(
            children: [
              const Icon(Icons.local_shipping_outlined, color: Mk.orange, size: 22),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  _deliveryLine,
                  style: const TextStyle(color: Mk.icon, fontSize: 13.5, fontWeight: FontWeight.w500),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _inline(IconData icon, String text) => Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, color: Mk.icon, size: 20),
          const SizedBox(width: 8),
          Flexible(child: Text(text, style: const TextStyle(color: Mk.icon, fontSize: 13.5, fontWeight: FontWeight.w500))),
        ],
      );

  Widget _trackingCard() {
    final timeline = orderMap(_order['timeline']);
    final status = _text(_order['vendor_status'], '');
    final step = orderWorkflowStep(status, deliveryStatus: _order['delivery_status']);
    final paid = _paid || timeline['payment_confirmed_at'] != null;
    // 'ready' est distinct de « en cours de préparation » : la commande est
    // prête et attend son expédition, comme sur l'espace vendeur web.
    final deliveryStatus = _text(_order['delivery_status'], '').toLowerCase();
    final isPrepared = status == 'ready' || step >= 3;
    final isShipped = status == 'shipped' ||
        status == 'delivered' ||
        ['assigned', 'picked_up', 'in_transit', 'late', 'delivered'].contains(deliveryStatus);
    // Une fois remise à OVANIE Logistics, le suivi vendeur s'arrête à
    // « Expédiée ». Seule une boutique en logistique propre voit « Livrée ».
    final delivered = isShipped && step == 4;

    return OrderCard(
      padding: const EdgeInsets.fromLTRB(10, 14, 10, 14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Padding(padding: EdgeInsets.only(left: 4), child: OrderCaption('Suivi de la commande')),
          const SizedBox(height: 12),
          if (status == 'cancelled')
            const Padding(
              padding: EdgeInsets.only(left: 4),
              child: Text(
                'Commande annulée',
                style: TextStyle(color: Color(0xFFD92D20), fontSize: 14, fontWeight: FontWeight.w700),
              ),
            )
          else
            OrderStepsRow(
              steps: [
                const OrderStep('Commande reçue', done: true),
                OrderStep(
                  paid ? 'Paiement confirmé' : 'Paiement en attente',
                  done: paid,
                  active: !paid,
                  icon: Icons.credit_card_outlined,
                ),
                OrderStep(
                  isPrepared ? 'Préparée' : (status == 'preparing' ? 'En préparation' : 'À préparer'),
                  done: isPrepared,
                  active: !isPrepared && paid,
                  icon: Icons.inventory_2_outlined,
                ),
                OrderStep(
                  delivered ? 'Livrée' : (_ovanie ? 'Remise au livreur' : 'Expédiée'),
                  done: isShipped,
                  active: isPrepared && !isShipped,
                  icon: Icons.local_shipping_outlined,
                ),
              ],
            ),
        ],
      ),
    );
  }

  Widget _itemsCard() {
    final items = orderList(_order['items']).map(orderMap).toList();
    return OrderCard(
      padding: const EdgeInsets.fromLTRB(14, 14, 14, 4),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const OrderCaption('Articles commandés'),
          const SizedBox(height: 4),
          if (items.isEmpty)
            const Padding(
              padding: EdgeInsets.symmetric(vertical: 12),
              child: Text('Aucun article visible pour votre boutique.', style: TextStyle(color: Mk.muted, fontSize: 13.5)),
            ),
          for (var i = 0; i < items.length; i++) ...[
            if (i > 0) const Divider(height: 1, color: orderBorder),
            _itemRow(items[i]),
          ],
        ],
      ),
    );
  }

  Widget _itemRow(Map<String, dynamic> item) {
    final unit = orderUnitLabel(item['unit_label']);
    final details = [_text(item['variant_label'], ''), _text(item['category_name'], '')].where((e) => e.isNotEmpty).join(' • ');
    // Le prix « public » inclut la commission OVANIE (ce que paie le client) :
    // le vendeur voit son propre prix, celui qu'il touche réellement.
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 10),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          OrderProductImage(url: item['image_url'], size: 64),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  _text(item['name'], 'Produit'),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(color: Mk.navy, fontSize: 14, height: 1.2, fontWeight: FontWeight.w700),
                ),
                if (details.isNotEmpty)
                  Padding(
                    padding: const EdgeInsets.only(top: 2),
                    child: Text(
                      details,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(color: Mk.muted, fontSize: 12.5, fontWeight: FontWeight.w500),
                    ),
                  ),
                const SizedBox(height: 2),
                Text(
                  'Qté : ${_text(item['quantity'], '0')}${unit.isEmpty ? '' : ' $unit'}',
                  style: const TextStyle(color: Mk.muted, fontSize: 12.5, fontWeight: FontWeight.w500),
                ),
                const SizedBox(height: 6),
                Wrap(
                  spacing: 18,
                  runSpacing: 4,
                  children: [
                    _price('Prix unitaire', item['seller_unit_price'] ?? item['unit_price'], false),
                    _price('Sous-total', item['seller_subtotal'] ?? item['public_subtotal'], true),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _price(String label, Object? value, bool strong) => Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(label, style: const TextStyle(color: Mk.muted, fontSize: 11.5, fontWeight: FontWeight.w500)),
          Text(
            _amount(value),
            style: TextStyle(
              color: strong ? const Color(0xFFE85400) : Mk.navy,
              fontSize: strong ? 14.5 : 13.5,
              fontWeight: strong ? FontWeight.w800 : FontWeight.w600,
            ),
          ),
        ],
      );

  Widget _clientCard() {
    final client = orderMap(_order['client']);
    final address = orderMap(_order['delivery_address']);
    final name = _text(address['site_name'], _text(client['name'], 'Client'));
    final contact = _text(address['recipient_name'], '');
    final phone = _text(client['phone'], _text(address['recipient_phone'], ''));
    final place = _text(address['formatted'], _text(address['address'], ''));
    final rows = <(IconData, String, String)>[
      (Icons.person_outline_rounded, 'Client', name),
      // OVANIE Logistics : le serveur ne transmet ni contact, ni téléphone, ni adresse exacte.
      if (!_ovanie && contact.isNotEmpty && contact != name) (Icons.badge_outlined, 'Contact', contact),
      if (!_ovanie && phone.isNotEmpty) (Icons.call_outlined, 'Téléphone', orderPhoneLabel(phone)),
      if (place.isNotEmpty) (Icons.location_on_outlined, _ovanie ? 'Zone de livraison' : 'Adresse de livraison', place),
    ];
    final initial = name.trim().isEmpty ? '?' : name.trim()[0].toUpperCase();

    return OrderCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const OrderCaption('Informations client'),
          const SizedBox(height: 12),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 52,
                height: 52,
                alignment: Alignment.center,
                decoration: const BoxDecoration(color: Color(0xFFFFE3D0), shape: BoxShape.circle),
                child: Text(initial, style: const TextStyle(color: Color(0xFFE85400), fontSize: 24, fontWeight: FontWeight.w800)),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  children: [
                    for (final row in rows)
                      Padding(
                        padding: const EdgeInsets.only(bottom: 8),
                        child: Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Icon(row.$1, color: Mk.icon, size: 20),
                            const SizedBox(width: 10),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(row.$2, style: const TextStyle(color: Mk.muted, fontSize: 12, fontWeight: FontWeight.w500)),
                                  Text(
                                    row.$3,
                                    style: const TextStyle(color: Mk.navy, fontSize: 13.5, height: 1.3, fontWeight: FontWeight.w600),
                                  ),
                                ],
                              ),
                            ),
                          ],
                        ),
                      ),
                  ],
                ),
              ),
            ],
          ),
          if (_ovanie)
            const Text(
              'OVANIE Logistics gère le contact avec le client.',
              style: TextStyle(color: Mk.muted, fontSize: 12.5, fontWeight: FontWeight.w500),
            ),
        ],
      ),
    );
  }

  // La commission OVANIE n'apparaît pas sur cette fiche : un vendeur en
  // logistique OVANIE ne voit que le prix de ses produits, un vendeur en
  // logistique propre voit en plus le prix de livraison qu'il a configuré.
  Widget _amountCard() {
    return OrderCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const OrderCaption('Montant'),
          const SizedBox(height: 8),
          // seller_* = le prix réel du vendeur, sans la commission OVANIE.
          _moneyLine('Prix des produits', _order['seller_products_total'] ?? _order['public_products_total']),
          if (!_ovanie) _moneyLine('Livraison', _order['seller_delivery_total'] ?? _order['delivery_total']),
          const SizedBox(height: 8),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
            decoration: BoxDecoration(color: Mk.softOrange, borderRadius: BorderRadius.circular(10)),
            child: Row(
              children: [
                const Expanded(
                  child: Text(
                    'Net vendeur estimé',
                    style: TextStyle(color: Color(0xFFE85400), fontSize: 14, fontWeight: FontWeight.w700),
                  ),
                ),
                const SizedBox(width: 8),
                Text(
                  _amount(_order['vendor_payout_total']),
                  style: const TextStyle(color: Color(0xFFE85400), fontSize: 19, fontWeight: FontWeight.w800),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _moneyLine(String label, Object? value) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 4),
        child: Row(
          children: [
            Expanded(child: Text(label, style: const TextStyle(color: Mk.muted, fontSize: 13.5, fontWeight: FontWeight.w500))),
            const SizedBox(width: 8),
            Text(_amount(value), style: const TextStyle(color: Mk.navy, fontSize: 13.5, fontWeight: FontWeight.w600)),
          ],
        ),
      );

  /// Actions secondaires : lien de mission du chauffeur et encaissement à la livraison.
  bool _downloadingSlip = false;

  /// Bon de préparation PDF : articles, quantités, poids et zone de signature.
  Widget _slipCard() => OrderCard(
    padding: EdgeInsets.zero,
    child: Stack(
      children: [
        Positioned(
          top: 6,
          right: 6,
          child: ExcludeSemantics(
            child: Image.asset('assets/images/mockups/slip.jpg', width: 92, fit: BoxFit.fitWidth),
          ),
        ),
        Padding(
          padding: const EdgeInsets.fromLTRB(12, 14, 12, 12),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Padding(
                padding: const EdgeInsets.only(right: 92),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Container(
                      width: 46,
                      height: 46,
                      decoration: BoxDecoration(
                        color: const Color(0xFFFDE8E7),
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: const Icon(Icons.picture_as_pdf_outlined, color: Color(0xFFE5231B), size: 26),
                    ),
                    const SizedBox(width: 12),
                    const Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'Bon de préparation',
                            style: TextStyle(
                              color: Mk.navy,
                              fontSize: 16.5,
                              height: 1.2,
                              fontWeight: FontWeight.w800,
                              letterSpacing: -.2,
                            ),
                          ),
                          SizedBox(height: 4),
                          Text(
                            'PDF à imprimer : articles, quantités et signature de remise',
                            style: TextStyle(color: Mk.muted, fontSize: 12.5, height: 1.4, fontWeight: FontWeight.w500),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 14),
              Semantics(
                button: true,
                enabled: !_downloadingSlip,
                label: 'Télécharger le bon de préparation',
                excludeSemantics: true,
                child: Material(
                  color: _downloadingSlip ? const Color(0xFFEFF2F7) : Colors.white,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(10),
                    side: BorderSide(color: _downloadingSlip ? const Color(0xFFC9D1DF) : Mk.navy, width: 1.4),
                  ),
                  clipBehavior: Clip.antiAlias,
                  child: InkWell(
                    onTap: _downloadingSlip ? null : _downloadSlip,
                    child: SizedBox(
                      height: 46,
                      child: FittedBox(
                        fit: BoxFit.scaleDown,
                        child: Padding(
                          padding: const EdgeInsets.symmetric(horizontal: 10),
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              if (_downloadingSlip)
                                const SizedBox.square(
                                  dimension: 18,
                                  child: CircularProgressIndicator(strokeWidth: 2.2, color: Mk.orange),
                                )
                              else
                                const Icon(Icons.file_download_outlined, color: Mk.navy, size: 22),
                              const SizedBox(width: 10),
                              Text(
                                'Télécharger le bon de préparation',
                                style: TextStyle(
                                  color: _downloadingSlip ? Mk.hint : Mk.navy,
                                  fontSize: 14.5,
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
      ],
    ),
  );

  Future<void> _downloadSlip() async {
    setState(() => _downloadingSlip = true);
    final number = _text(_order['order_number'], '${widget.orderId}');
    await downloadVendorFile(
      context,
      '/mobile/v1/vendor/orders/${widget.orderId}/preparation-slip',
      'bon-preparation-$number.pdf',
      done: 'Bon de préparation enregistré.',
    );
    if (mounted) setState(() => _downloadingSlip = false);
  }

  List<Widget> _extraActions() {
    final mission = orderMap(_order['seller_mission']);
    final missionLinkAvailable =
        _text(mission['url'], '').isNotEmpty && !['arrived', 'delivered'].contains(_text(mission['status'], ''));
    final cashReport = orderMap(_order['cash_report']);
    final canReport = cashReport['can_report'] == true;
    final pending = cashReport['pending'] == true;
    if (!missionLinkAvailable && !canReport && !pending) return const [];
    return [
      OrderCard(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const OrderCaption('Autres actions'),
            const SizedBox(height: 10),
            if (missionLinkAvailable)
              MkBarButton(
                label: 'Envoyer le lien au chauffeur',
                icon: Icons.send_outlined,
                color: Mk.navy,
                height: 44,
                onTap: _sendMissionLink,
              ),
            if (missionLinkAvailable && (canReport || pending)) const SizedBox(height: 8),
            if (canReport)
              MkBarButton(
                label: 'Signaler l’encaissement',
                icon: Icons.payments_outlined,
                height: 44,
                onTap: _reportCash,
              ),
            if (pending)
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 11),
                decoration: BoxDecoration(color: const Color(0xFFFFF3D6), borderRadius: BorderRadius.circular(10)),
                child: const Row(
                  children: [
                    Icon(Icons.schedule_rounded, color: Color(0xFFB45309), size: 22),
                    SizedBox(width: 10),
                    Expanded(
                      child: Text(
                        'Encaissement signalé · vérification OVANIE en cours',
                        style: TextStyle(
                          color: Color(0xFFB45309),
                          fontSize: 13,
                          height: 1.35,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
          ],
        ),
      ),
    ];
  }
}
