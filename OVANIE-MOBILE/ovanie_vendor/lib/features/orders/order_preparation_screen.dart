import 'package:flutter/material.dart';

import '../../core/network/api_client.dart';
import '../../data/vendor_repository.dart';
import 'order_expedition_screen.dart';
import 'order_tracking_screen.dart';
import 'order_ui.dart';

class OrderPreparationScreen extends StatefulWidget {
  const OrderPreparationScreen({
    super.key,
    required this.orderId,
    this.initialOrder,
  });
  final int orderId;
  final Map<String, dynamic>? initialOrder;

  @override
  State<OrderPreparationScreen> createState() => _OrderPreparationScreenState();
}

class _OrderPreparationScreenState extends State<OrderPreparationScreen> {
  Map<String, dynamic> _order = <String, dynamic>{};
  final Set<int> _checkedItems = <int>{};
  bool _checkStock = false;
  bool _checkQuantity = false;
  bool _checkPackaging = false;
  bool _loading = true;
  bool _saving = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    if (widget.initialOrder != null) {
      _order = Map<String, dynamic>.from(widget.initialOrder!);
    }
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
  }

  Future<void> _load({bool silent = false}) async {
    if (_saving) return;
    if (!mounted) return;
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
        _error = detail.isEmpty
            ? 'Le détail de cette commande est indisponible.'
            : null;
        _checkedItems.clear();
        _checkStock = false;
        _checkQuantity = false;
        _checkPackaging = false;
      });
    } catch (e) {
      if (mounted) setState(() => _error = ApiClient.friendlyError(e));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  bool get _allItemsChecked {
    final items = orderList(_order['items']);
    if (items.isEmpty) return false;
    return items.every((raw) {
      final id = int.tryParse('${orderMap(raw)['id'] ?? ''}');
      return id != null && _checkedItems.contains(id);
    });
  }

  bool get _editable =>
      ['pending', 'accepted', 'preparing'].contains(_order['vendor_status']) &&
      !_loading &&
      !_saving &&
      _error == null;

  bool get _canValidate =>
      _editable &&
      _allItemsChecked &&
      _checkStock &&
      _checkQuantity &&
      _checkPackaging &&
      !_saving;

  Future<void> _validate() async {
    if (!_canValidate) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'Cochez tous les articles et les vérifications avant de valider la préparation.',
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
      await VendorRepository.instance.updatePreparation(
        widget.orderId,
        'ready',
      );

      // Recharge immédiatement le dossier pour récupérer le vrai état
      // logistique : mission proposée, livreur réservé et numéro de mission.
      final refreshed = await VendorRepository.instance.order(
        widget.orderId,
        fresh: true,
      );
      final updated = orderMap(refreshed['order']);
      final effectiveOrder = updated.isEmpty
          ? (Map<String, dynamic>.from(_order)..['vendor_status'] = 'ready')
          : updated;

      if (!mounted) return;
      setState(() => _order = effectiveOrder);

      final provider =
          '${effectiveOrder['delivery_provider'] ?? ''}'.toLowerCase();
      final tracking = orderMap(effectiveOrder['tracking']);
      final driverReserved = tracking['driver_reserved'] == true;

      if (provider == 'ovanie') {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              driverReserved
                  ? 'Préparation terminée. Le livreur réservé a été informé automatiquement.'
                  : 'Préparation terminée. OVANIE Logistics recherche un livreur partenaire disponible.',
            ),
          ),
        );

        // Avec OVANIE Logistics, le vendeur ne confirme pas lui-même une
        // "expédition". Sa responsabilité est de préparer puis remettre les
        // produits au livreur lorsque celui-ci vient les collecter.
        Navigator.of(context).pushReplacement<void, void>(
          MaterialPageRoute<void>(
            builder: (_) => OrderTrackingScreen(
              orderId: widget.orderId,
              initialOrder: effectiveOrder,
            ),
          ),
        );
        return;
      }

      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Préparation validée. La commande est prête.'),
        ),
      );
      Navigator.of(context).pushReplacement<bool, bool>(
        MaterialPageRoute<bool>(
          builder: (_) => OrderExpeditionScreen(
            orderId: widget.orderId,
            initialOrder: effectiveOrder,
          ),
        ),
      );
    } catch (e) {
      if (mounted) setState(() => _error = ApiClient.friendlyError(e));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
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
              title: 'Préparation de commande',
              subtitle:
                  'Préparez et vérifiez les articles avant de signaler la commande prête.',
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
                          _itemsSection(),
                          const SizedBox(height: 14),
                          _verificationBox(),
                          if (!_editable && !_saving && _error == null)
                            const Padding(
                              padding: EdgeInsets.only(top: 12),
                              child: Text(
                                'La préparation ne peut plus être modifiée pour ce statut.',
                                style: TextStyle(color: orderMuted),
                              ),
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
    final current = step < 2 ? 2 : step;
    // Une fois remise à OVANIE Logistics, la livraison n'est plus du ressort
    // du vendeur : le suivi s'arrête à "Expédiée", comme sur la fiche
    // commande. Seule une boutique en logistique propre voit "Livrée".
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
          if (constraints.maxWidth < 450 ||
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

  Widget _itemsSection() {
    final items = orderList(_order['items']).map((e) => orderMap(e)).toList();
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
          decoration: BoxDecoration(
            color: orderSoftBlue,
            borderRadius: const BorderRadius.vertical(top: Radius.circular(13)),
            border: Border.all(color: orderBorder),
          ),
          child: Row(
            children: [
              const Icon(
                Icons.inventory_2_outlined,
                color: orderText,
                size: 24,
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  'Articles à préparer (${items.length})',
                  style: const TextStyle(
                    color: orderText,
                    fontSize: 15,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
              OutlinedButton.icon(
                onPressed: !_editable || items.isEmpty
                    ? null
                    : () {
                        setState(() {
                          if (_allItemsChecked) {
                            _checkedItems.clear();
                          } else {
                            for (final item in items) {
                              final id = int.tryParse('${item['id'] ?? ''}');
                              if (id != null) _checkedItems.add(id);
                            }
                          }
                        });
                      },
                icon: Icon(
                  _allItemsChecked
                      ? Icons.check_box_rounded
                      : Icons.check_box_outline_blank_rounded,
                  color: orderText,
                  size: 19,
                ),
                label: Text(
                  _allItemsChecked ? 'Tout décocher' : 'Tout cocher',
                  style: TextStyle(
                    color: orderText,
                    fontWeight: FontWeight.w700,
                    fontSize: 12.5,
                  ),
                ),
                style: OutlinedButton.styleFrom(
                  side: const BorderSide(color: Color(0xFFA8BCE0)),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(9),
                  ),
                ),
              ),
            ],
          ),
        ),
        for (final item in items) _preparationItem(item),
      ],
    );
  }

  Widget _preparationItem(Map<String, dynamic> item) {
    final id = int.tryParse('${item['id'] ?? ''}');
    final checked = id != null && _checkedItems.contains(id);
    final stockAvailable = item['stock_available'];
    final quantity = int.tryParse('${item['quantity'] ?? 0}') ?? 0;
    final unit = '${item['unit_label'] ?? 'unité'}';
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
          final narrow =
              constraints.maxWidth < 420 ||
              MediaQuery.textScalerOf(context).scale(12) > 16;
          final productInfo = Row(
            children: [
              OrderProductImage(url: item['image_url'], size: 62),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      '${item['name'] ?? 'Produit'}',
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: orderText,
                        fontSize: 15,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      [item['brand'], item['weight_label'], unit]
                          .map((e) => '${e ?? ''}'.trim())
                          .where((e) => e.isNotEmpty)
                          .join('  •  '),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(color: orderMuted, fontSize: 12),
                    ),
                    const SizedBox(height: 7),
                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 10,
                        vertical: 4,
                      ),
                      decoration: BoxDecoration(
                        color: stockAvailable == true
                            ? orderSoftGreen
                            : stockAvailable == false
                            ? const Color(0xFFFFEEEE)
                            : orderSoftBlue,
                        borderRadius: BorderRadius.circular(99),
                      ),
                      child: Text(
                        stockAvailable == true
                            ? 'En stock'
                            : stockAvailable == false
                            ? 'Stock insuffisant'
                            : 'Stock à vérifier',
                        style: TextStyle(
                          color: stockAvailable == true
                              ? orderGreen
                              : const Color(0xFFD92D20),
                          fontSize: 11.5,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          );
          final quantityInfo = Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Quantité commandée',
                style: TextStyle(color: orderMuted, fontSize: 11.5),
              ),
              const SizedBox(height: 4),
              Text(
                '$quantity $unit',
                style: const TextStyle(
                  color: orderText,
                  fontSize: 16,
                  fontWeight: FontWeight.w900,
                ),
              ),
            ],
          );
          final checkbox = Checkbox(
            value: checked,
            activeColor: orderText,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(4),
            ),
            onChanged: id == null || !_editable
                ? null
                : (value) => setState(() {
                    if (value == true) {
                      _checkedItems.add(id);
                    } else {
                      _checkedItems.remove(id);
                    }
                  }),
          );
          if (narrow) {
            return Column(
              children: [
                productInfo,
                const SizedBox(height: 10),
                Row(
                  children: [
                    Expanded(child: quantityInfo),
                    checkbox,
                  ],
                ),
              ],
            );
          }
          return Row(
            children: [
              Expanded(flex: 6, child: productInfo),
              const SizedBox(width: 18),
              Expanded(flex: 3, child: quantityInfo),
              checkbox,
            ],
          );
        },
      ),
    );
  }

  Widget _verificationBox() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.fromLTRB(18, 16, 18, 16),
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
              Icons.inventory_rounded,
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
                  'Vérifications avant validation',
                  style: TextStyle(
                    color: orderOrange,
                    fontSize: 15,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 5),
                const Text(
                  'Assurez-vous que tous les articles sont correctement préparés, bien emballés et conformes à la commande.',
                  style: TextStyle(
                    color: orderText,
                    fontSize: 12.5,
                    height: 1.35,
                  ),
                ),
                const SizedBox(height: 10),
                _checkRow(
                  'Tous les articles sont disponibles et en bon état',
                  _checkStock,
                  (v) => setState(() => _checkStock = v),
                ),
                _checkRow(
                  'Les quantités correspondent à la commande',
                  _checkQuantity,
                  (v) => setState(() => _checkQuantity = v),
                ),
                _checkRow(
                  'Les produits sont bien emballés et étiquetés',
                  _checkPackaging,
                  (v) => setState(() => _checkPackaging = v),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _checkRow(String label, bool value, ValueChanged<bool> onChanged) {
    return InkWell(
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
                style: const TextStyle(
                  color: orderText,
                  fontSize: 12.5,
                  height: 1.25,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _actions() {
    final back = OutlinedButton.icon(
      onPressed: _saving ? null : () => Navigator.pop(context),
      icon: const Icon(Icons.arrow_back, size: 22),
      label: const Text('Retour'),
      style: OutlinedButton.styleFrom(
        minimumSize: const Size(0, 52),
        foregroundColor: orderText,
        side: const BorderSide(color: Color(0xFFB9CAE8)),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(9)),
      ),
    );
    final validate = FilledButton(
      onPressed: _canValidate ? _validate : null,
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
          : const Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Flexible(
                  child: Text(
                    'Valider la préparation',
                    textAlign: TextAlign.center,
                  ),
                ),
                SizedBox(width: 12),
                Icon(Icons.arrow_forward, size: 22),
              ],
            ),
    );
    return Row(
      children: [
        Expanded(child: back),
        const SizedBox(width: 16),
        Expanded(flex: 2, child: validate),
      ],
    );
  }
}
