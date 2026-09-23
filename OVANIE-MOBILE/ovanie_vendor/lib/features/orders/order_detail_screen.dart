import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../core/network/api_client.dart';
import '../../data/vendor_repository.dart';
import 'order_expedition_screen.dart';
import 'order_preparation_screen.dart';
import 'order_tracking_screen.dart';
import 'order_ui.dart';

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

class _OrderDetailScreenState extends State<OrderDetailScreen> {
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

  Future<void> _contactClient() async {
    final client = orderMap(_order['client']);
    final phone = _text(
      client['phone'],
      _text(orderMap(_order['delivery_address'])['recipient_phone'], ''),
    );
    if (phone.isEmpty) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Aucun numéro de téléphone client disponible.'),
          ),
        );
      }
      return;
    }
    await showModalBottomSheet<void>(
      context: context,
      backgroundColor: Colors.transparent,
      builder: (context) => SafeArea(
        top: false,
        child: Container(
          padding: const EdgeInsets.fromLTRB(20, 10, 20, 22),
          decoration: const BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 44,
                height: 4,
                decoration: BoxDecoration(
                  color: const Color(0xFFD6DDE8),
                  borderRadius: BorderRadius.circular(99),
                ),
              ),
              const SizedBox(height: 14),
              const Icon(
                Icons.phone_in_talk_outlined,
                color: orderText,
                size: 34,
              ),
              const SizedBox(height: 8),
              Text(
                '${client['name'] ?? 'Client'}',
                style: const TextStyle(
                  color: orderText,
                  fontSize: 18,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 5),
              Text(
                phone,
                style: const TextStyle(
                  color: orderMuted,
                  fontSize: 16,
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(height: 16),
              SizedBox(
                width: double.infinity,
                child: OutlinedButton.icon(
                  onPressed: () async {
                    await Clipboard.setData(ClipboardData(text: phone));
                    if (context.mounted) Navigator.pop(context);
                    if (mounted) {
                      ScaffoldMessenger.of(this.context).showSnackBar(
                        const SnackBar(content: Text('Numéro copié.')),
                      );
                    }
                  },
                  icon: const Icon(Icons.copy_rounded),
                  label: const Text('Copier le numéro'),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  bool get _paid => [
    'paid',
    'escrow_held',
    'verified',
    'commission_paid',
    'released_to_vendor',
  ].contains('${_order['payment_status']}'.toLowerCase());

  String _text(Object? value, [String fallback = '—']) {
    final text = '${value ?? ''}'.trim();
    return text.isEmpty ? fallback : text;
  }

  String _amount(Object? value) => value == null ? '—' : orderMoney(value);

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: Colors.white,
    bottomNavigationBar: const OrderBottomBar(),
    body: RefreshIndicator(
      onRefresh: _load,
      child: CustomScrollView(
        physics: const AlwaysScrollableScrollPhysics(),
        slivers: [
          const SliverToBoxAdapter(
            child: OrderHeader(
              title: 'Détail de la commande',
              subtitle: 'Suivez et gérez cette commande',
              showBack: true,
            ),
          ),
          if (_loading)
            const SliverFillRemaining(
              hasScrollBody: false,
              child: Center(
                child: CircularProgressIndicator(color: orderOrange),
              ),
            )
          else if (_error != null)
            SliverFillRemaining(
              hasScrollBody: false,
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    const Icon(
                      Icons.error_outline,
                      color: orderOrange,
                      size: 36,
                    ),
                    const SizedBox(height: 12),
                    Text(_error!, textAlign: TextAlign.center),
                    const SizedBox(height: 12),
                    FilledButton(
                      onPressed: _load,
                      child: const Text('Réessayer'),
                    ),
                  ],
                ),
              ),
            )
          else
            SliverToBoxAdapter(
              child: Stack(
                children: [
                  Positioned(
                    top: 0,
                    left: 0,
                    right: 0,
                    height: 44,
                    child: Container(
                      decoration: const BoxDecoration(
                        gradient: LinearGradient(
                          colors: [Color(0xFF031D47), Color(0xFF07539B)],
                        ),
                      ),
                    ),
                  ),
                  OrderResponsiveBody(
                    padding: const EdgeInsets.symmetric(horizontal: 16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        _summaryCard(),
                        const SizedBox(height: 18),
                        _heading('Articles commandés'),
                        const SizedBox(height: 9),
                        _itemsCard(),
                        const SizedBox(height: 12),
                        _pair(_clientCard(), _amountCard()),
                        const SizedBox(height: 12),
                        _pair(_trackingCard(), _actionsCard()),
                        const SizedBox(height: 20),
                      ],
                    ),
                  ),
                ],
              ),
            ),
        ],
      ),
    ),
  );

  Widget _heading(String text) => Text(
    text,
    style: const TextStyle(
      color: orderText,
      fontSize: 17,
      fontWeight: FontWeight.w700,
      letterSpacing: -.35,
    ),
  );

  Widget _card(Widget child, {EdgeInsets padding = const EdgeInsets.all(12)}) =>
      Container(
        width: double.infinity,
        padding: padding,
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(13),
          border: Border.all(color: const Color(0xFFEBEEF5)),
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

  Widget _pair(Widget left, Widget right) => LayoutBuilder(
    builder: (context, constraints) {
      if (constraints.maxWidth < 420 ||
          MediaQuery.textScalerOf(context).scale(14) > 18) {
        return Column(children: [left, const SizedBox(height: 12), right]);
      }
      return Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(child: left),
          const SizedBox(width: 12),
          Expanded(child: right),
        ],
      );
    },
  );

  Widget _summaryCard() {
    final number = _text(_order['order_number']);
    return _card(
      LayoutBuilder(
        builder: (context, constraints) {
          final compact = constraints.maxWidth < 360;
          return Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: compact ? 48 : 66,
                height: compact ? 65 : 76,
                decoration: BoxDecoration(
                  color: const Color(0xFFEAF0FC),
                  borderRadius: BorderRadius.circular(10),
                ),
                alignment: Alignment.center,
                child: CustomPaint(
                  size: Size.square(compact ? 33 : 43),
                  painter: const _DetailParcelPainter(),
                ),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Wrap(
                      alignment: WrapAlignment.spaceBetween,
                      spacing: 12,
                      runSpacing: 8,
                      crossAxisAlignment: WrapCrossAlignment.center,
                      children: [
                        Text(
                          number.startsWith('#') ? number : '#$number',
                          style: const TextStyle(
                            color: orderText,
                            fontSize: 18,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                        OrderStatusPill(
                          status: _order['vendor_status'],
                          compact: true,
                        ),
                      ],
                    ),
                    const SizedBox(height: 10),
                    Wrap(
                      spacing: 16,
                      runSpacing: 8,
                      crossAxisAlignment: WrapCrossAlignment.center,
                      children: [
                        _inline(
                          Icons.calendar_today_outlined,
                          orderHumanDate(_order['created_at']),
                        ),
                        _inline(
                          Icons.credit_card_outlined,
                          _text(
                            _order['payment_method_label'],
                            _text(_order['payment_method']),
                          ),
                        ),
                        OrderPaymentPill(
                          status: _order['payment_status'],
                          label: _text(
                            _order['payment_status_label'],
                            _paid ? 'Payée' : 'En attente',
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 10),
                    _inline(
                      Icons.local_shipping_outlined,
                      'Livraison : ${_text(_order['delivery_provider_label'])}',
                    ),
                  ],
                ),
              ),
            ],
          );
        },
      ),
      padding: const EdgeInsets.all(16),
    );
  }

  Widget _inline(IconData icon, String text) => Row(
    mainAxisSize: MainAxisSize.min,
    children: [
      Icon(icon, color: orderText, size: 17),
      const SizedBox(width: 7),
      Flexible(
        child: Text(
          text,
          style: const TextStyle(color: orderMuted, fontSize: 12),
        ),
      ),
    ],
  );

  Widget _itemsCard() {
    final items = orderList(_order['items']).map(orderMap).toList();
    return _card(
      Column(
        children: [
          if (items.isEmpty)
            const Padding(
              padding: EdgeInsets.all(12),
              child: Text('Aucun article visible pour votre boutique.'),
            ),
          for (var i = 0; i < items.length; i++) ...[
            _itemRow(items[i]),
            if (i < items.length - 1)
              const Divider(height: 1, color: orderBorder),
          ],
        ],
      ),
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 3),
    );
  }

  Widget _itemRow(Map<String, dynamic> item) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 9),
    child: LayoutBuilder(
      builder: (context, constraints) {
        final wide =
            constraints.maxWidth >= 410 &&
            MediaQuery.textScalerOf(context).scale(14) <= 18;
        final product = Row(
          children: [
            OrderProductImage(url: item['image_url'], size: wide ? 64 : 62),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    _text(item['name'], 'Produit'),
                    style: const TextStyle(
                      color: orderText,
                      fontSize: 14,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  const SizedBox(height: 4),
                  if (_text(item['category_name'], '').isNotEmpty)
                    Text(
                      _text(item['category_name']),
                      style: const TextStyle(color: orderMuted, fontSize: 11.5),
                    ),
                  const SizedBox(height: 7),
                  Text(
                    'Qté : ${_text(item['quantity'])} ${_text(item['unit_label'], '')}',
                    style: const TextStyle(color: orderMuted, fontSize: 12),
                  ),
                ],
              ),
            ),
          ],
        );
        // Le prix "public" inclut la commission OVANIE (c'est ce que paie le
        // client) : le vendeur doit voir son propre prix, celui qu'il touche
        // réellement, pas le prix majoré facturé au client.
        final prices = Row(
          children: [
            Expanded(
              child: _price(
                item['seller_unit_price'] ?? item['unit_price'],
                'Prix unitaire',
                orderText,
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: _price(
                item['seller_subtotal'] ?? item['public_subtotal'],
                'Sous-total',
                orderOrange,
              ),
            ),
          ],
        );
        return wide
            ? Row(
                children: [
                  Expanded(flex: 6, child: product),
                  const SizedBox(width: 12),
                  Expanded(flex: 5, child: prices),
                ],
              )
            : Column(
                children: [
                  product,
                  const SizedBox(height: 10),
                  Padding(
                    padding: const EdgeInsets.only(left: 74),
                    child: prices,
                  ),
                ],
              );
      },
    ),
  );

  Widget _price(Object? value, String label, Color color) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Text(
        _amount(value),
        style: TextStyle(
          color: color,
          fontSize: 13.5,
          fontWeight: FontWeight.w600,
        ),
      ),
      const SizedBox(height: 4),
      Text(label, style: const TextStyle(color: orderMuted, fontSize: 10.5)),
    ],
  );

  Widget _clientCard() {
    final client = orderMap(_order['client']);
    final address = orderMap(_order['delivery_address']);
    return _card(
      Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _heading('Informations client'),
          const SizedBox(height: 12),
          _info(
            Icons.person_outline,
            'Client',
            _text(address['site_name'], _text(client['name'])),
          ),
          _info(
            Icons.badge_outlined,
            'Contact',
            _text(address['recipient_name'], _text(client['name'])),
          ),
          _info(
            Icons.phone_outlined,
            'Téléphone',
            _text(client['phone'], _text(address['recipient_phone'])),
          ),
          _info(
            Icons.location_on_outlined,
            'Adresse de livraison',
            _text(address['formatted'], _text(address['address'])),
          ),
        ],
      ),
    );
  }

  Widget _info(IconData icon, String label, String value) => Padding(
    padding: const EdgeInsets.only(bottom: 10),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          width: 26,
          height: 28,
          decoration: BoxDecoration(
            border: Border.all(color: orderBorder),
            borderRadius: BorderRadius.circular(6),
          ),
          child: Icon(icon, color: orderText, size: 18),
        ),
        const SizedBox(width: 9),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                label,
                style: const TextStyle(color: orderMuted, fontSize: 11),
              ),
              const SizedBox(height: 2),
              Text(
                value,
                style: const TextStyle(
                  color: orderText,
                  fontSize: 12,
                  height: 1.35,
                ),
              ),
            ],
          ),
        ),
      ],
    ),
  );

  // La commission OVANIE n'a rien à faire sur cette fiche : un vendeur en
  // logistique OVANIE ne voit que le prix de ses produits, un vendeur en
  // logistique propre voit en plus le prix de livraison qu'il a lui-même
  // configuré. La commission (déjà déduite du net vendeur) ne s'affiche nulle
  // part côté vendeur.
  Widget _amountCard() {
    final isOvanieLogistics =
        _text(_order['delivery_provider'], '').toLowerCase() == 'ovanie';
    return _card(
      Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _heading('Montant'),
          const SizedBox(height: 10),
          // seller_* = le prix réel du vendeur, sans la commission OVANIE
          // ajoutée pour obtenir le prix payé par le client (public_*).
          _moneyLine(
            'Prix des produits',
            _order['seller_products_total'] ?? _order['public_products_total'],
          ),
          if (!isOvanieLogistics)
            _moneyLine(
              'Livraison',
              _order['seller_delivery_total'] ?? _order['delivery_total'],
            ),
          const Divider(color: orderBorder, height: 18),
          Container(
            padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(
              color: orderSoftGreen,
              borderRadius: BorderRadius.circular(7),
            ),
            child: Wrap(
              alignment: WrapAlignment.spaceBetween,
              spacing: 8,
              runSpacing: 4,
              children: [
                const Text(
                  'Net vendeur estimé',
                  style: TextStyle(
                    color: Color(0xFF145E23),
                    fontSize: 12,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                Text(
                  _amount(_order['vendor_payout_total']),
                  style: const TextStyle(
                    color: Color(0xFF117123),
                    fontSize: 18,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _moneyLine(String label, Object? value, {Color color = orderText}) =>
      Padding(
        padding: const EdgeInsets.symmetric(vertical: 5),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(
              flex: 1,
              child: Text(
                label,
                style: const TextStyle(color: orderMuted, fontSize: 11.5),
              ),
            ),
            const SizedBox(width: 6),
            Flexible(
              child: Text(
                _amount(value),
                textAlign: TextAlign.right,
                style: TextStyle(
                  color: color,
                  fontSize: 12,
                  fontWeight: FontWeight.w500,
                ),
              ),
            ),
          ],
        ),
      );

  Widget _trackingCard() {
    final timeline = orderMap(_order['timeline']);
    final status = _text(_order['vendor_status'], '');
    final step = orderWorkflowStep(
      status,
      deliveryStatus: _order['delivery_status'],
    );
    final cancelled = status == 'cancelled';
    final paid = _paid || timeline['payment_confirmed_at'] != null;
    // Une fois remise à OVANIE Logistics, la livraison n'est plus du ressort
    // du vendeur (pas de GPS, pas d'étapes de trajet côté espace vendeur) :
    // le suivi s'arrête donc à "Expédiée". Seule une boutique en logistique
    // propre voit "Livrée", car elle reste responsable de la marchandise
    // jusqu'à confirmation de son livreur.
    final isOvanieLogistics =
        _text(_order['delivery_provider'], '').toLowerCase() == 'ovanie';
    // orderWorkflowStep() regroupe volontairement 'ready' avec
    // 'accepted'/'preparing' (étape 2) pour les écrans qui ne distinguent que
    // "pas encore prêt" / "en cours de livraison". Ici on a besoin de
    // distinguer "en cours de préparation" de "prête, en attente d'expédition"
    // pour ne pas afficher "En préparation" alors que la commande est déjà
    // marquée prête (ce qui la désynchronisait visuellement du web).
    final deliveryStatusRaw = _text(_order['delivery_status'], '').toLowerCase();
    final isPrepared = status == 'ready' || step >= 3;
    final isShipped = status == 'shipped' ||
        status == 'delivered' ||
        [
          'assigned',
          'picked_up',
          'in_transit',
          'late',
          'delivered',
        ].contains(deliveryStatusRaw);
    final labels = [
      'Commande\nreçue',
      paid ? 'Paiement\nconfirmé' : 'Paiement\nen attente',
      isPrepared ? 'Préparée' : (status == 'preparing' ? 'En\npréparation' : 'À préparer'),
      isShipped && !isOvanieLogistics && step == 4 ? 'Livrée' : 'Expédiée',
    ];
    final done = [_order['created_at'] != null, paid, isPrepared, isShipped];
    final icons = [
      Icons.check_circle_outline,
      paid ? Icons.check_circle_outline : Icons.credit_card_outlined,
      Icons.inventory_2_outlined,
      Icons.local_shipping_outlined,
    ];
    return _card(
      Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _heading('Suivi de la commande'),
          const SizedBox(height: 14),
          if (cancelled)
            const Text(
              'Commande annulée',
              style: TextStyle(color: Color(0xFFD92D20)),
            )
          else
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: List.generate(4, (i) {
                final color = done[i]
                    ? orderGreen
                    : (i == 2 && status == 'preparing') ||
                            (i == 3 && isPrepared && !isShipped)
                    ? orderOrange
                    : const Color(0xFF9BA3B5);
                return Expanded(
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
                            height: 36,
                            decoration: BoxDecoration(
                              shape: BoxShape.circle,
                              border: Border.all(color: color),
                            ),
                            child: Icon(icons[i], color: color, size: 19),
                          ),
                          Expanded(
                            child: Container(
                              height: 1,
                              color: i == 3 ? Colors.transparent : orderBorder,
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 6),
                      Text(
                        labels[i],
                        textAlign: TextAlign.center,
                        style: TextStyle(
                          color: color == orderOrange ? color : orderMuted,
                          fontSize: 9,
                          height: 1.3,
                        ),
                      ),
                    ],
                  ),
                );
              }),
            ),
        ],
      ),
    );
  }

  Widget _actionsCard() {
    final status = _text(_order['vendor_status'], '');
    final step = orderWorkflowStep(
      status,
      deliveryStatus: _order['delivery_status'],
    );
    final isOvanieLogistics =
        _text(_order['delivery_provider'], '').toLowerCase() == 'ovanie';
    final canPrepare = ['pending', 'accepted', 'preparing'].contains(status);

    // OVANIE Logistics : une fois la commande prête, le vendeur ne doit pas
    // "expédier" lui-même. Il attend le livreur réservé et suit simplement la
    // prise en charge. Le bouton Expédier reste réservé à la logistique propre.
    final canShip = status == 'ready' && !isOvanieLogistics;
    final track = step >= 3 || (isOvanieLogistics && status == 'ready');
    return _card(
      Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _heading('Actions'),
          const SizedBox(height: 10),
          if (canPrepare || canShip || track) ...[
            _action(
              track
                  ? (isOvanieLogistics
                      ? 'Suivre la prise en charge'
                      : 'Suivre la livraison')
                  : canShip
                  ? 'Expédier la commande'
                  : 'Préparer la commande',
              track || canShip
                  ? Icons.local_shipping_outlined
                  : Icons.inventory_2_outlined,
              track
                  ? _openTracking
                  : canShip
                  ? _openExpedition
                  : _openPreparation,
              primary: true,
            ),
            const SizedBox(height: 8),
          ],
          _action(
            'Contacter le client',
            Icons.chat_bubble_outline,
            _contactClient,
          ),
        ],
      ),
    );
  }

  Widget _action(
    String label,
    IconData icon,
    VoidCallback onTap, {
    bool primary = false,
  }) => SizedBox(
    width: double.infinity,
    child: Material(
      color: primary ? orderOrange : Colors.white,
      borderRadius: BorderRadius.circular(8),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(8),
        child: Container(
          constraints: const BoxConstraints(minHeight: 44),
          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 8),
          decoration: BoxDecoration(
            border: Border.all(color: primary ? orderOrange : orderText),
            borderRadius: BorderRadius.circular(8),
          ),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(icon, color: primary ? Colors.white : orderText, size: 21),
              const SizedBox(width: 8),
              Flexible(
                child: Text(
                  label,
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    color: primary ? Colors.white : orderText,
                    fontSize: 12,
                    fontWeight: primary ? FontWeight.w600 : FontWeight.w400,
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    ),
  );
}

class _DetailParcelPainter extends CustomPainter {
  const _DetailParcelPainter();
  @override
  void paint(Canvas canvas, Size size) {
    final w = size.width;
    final h = size.height;
    final paint = Paint()
      ..color = orderText
      ..style = PaintingStyle.stroke
      ..strokeWidth = 2
      ..strokeJoin = StrokeJoin.round;
    canvas.drawPath(
      Path()
        ..moveTo(w / 2, 0)
        ..lineTo(w, h / 4)
        ..lineTo(w, h * .75)
        ..lineTo(w / 2, h)
        ..lineTo(0, h * .75)
        ..lineTo(0, h / 4)
        ..close(),
      paint,
    );
    canvas.drawPath(
      Path()
        ..moveTo(0, h / 4)
        ..lineTo(w / 2, h / 2)
        ..lineTo(w, h / 4)
        ..moveTo(w / 2, h / 2)
        ..lineTo(w / 2, h)
        ..moveTo(w / 4, h / 8)
        ..lineTo(w * .75, h * .375)
        ..lineTo(w * .75, h * .6),
      paint,
    );
  }

  @override
  bool shouldRepaint(_DetailParcelPainter oldDelegate) => false;
}
