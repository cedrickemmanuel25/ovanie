import 'package:flutter/material.dart';

import '../../core/network/api_client.dart';
import '../../core/ui/vendor_mockup_ui.dart';
import '../../data/vendor_repository.dart';
import 'order_expedition_screen.dart';
import 'order_tracking_screen.dart';
import 'order_ui.dart';
import 'order_widgets.dart';

/// Préparation d'une commande : le vendeur coche chaque article, confirme
/// trois vérifications, puis signale la commande prête.
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
  static const _checks = <String>[
    'Tous les articles sont disponibles et en bon état',
    'Les quantités correspondent à la commande',
    'Les produits sont bien emballés et étiquetés',
  ];

  Map<String, dynamic> _order = <String, dynamic>{};
  final Set<int> _checkedItems = <int>{};
  final List<bool> _confirmed = List<bool>.filled(_checks.length, false);
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
          _error = 'Le détail de cette commande est indisponible.';
        } else {
          _order = detail;
        }
        _checkedItems.clear();
        _confirmed.fillRange(0, _confirmed.length, false);
      });
    } catch (e) {
      if (mounted) setState(() => _error = ApiClient.friendlyError(e));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  List<Map<String, dynamic>> get _items => orderList(_order['items']).map(orderMap).toList();

  int get _checkedCount => _items.where((item) => _checkedItems.contains(int.tryParse('${item['id'] ?? ''}'))).length;

  bool get _allItemsChecked => _items.isNotEmpty && _checkedCount == _items.length;

  bool get _editable =>
      ['pending', 'accepted', 'preparing'].contains(_order['vendor_status']) && !_loading && !_saving && _error == null;

  Future<void> _validate() async {
    if (!_editable) return;
    if (!_allItemsChecked) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Cochez chaque article préparé.')));
      return;
    }
    if (!_confirmed.every((value) => value)) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Confirmez les trois vérifications avant de valider.')),
      );
      return;
    }
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      await VendorRepository.instance.updatePreparation(widget.orderId, 'ready');

      // Recharge immédiatement le dossier pour récupérer le vrai état
      // logistique : mission proposée, livreur réservé et numéro de mission.
      final refreshed = await VendorRepository.instance.order(widget.orderId, fresh: true);
      final updated = orderMap(refreshed['order']);
      final effectiveOrder = updated.isEmpty ? (Map<String, dynamic>.from(_order)..['vendor_status'] = 'ready') : updated;

      if (!mounted) return;
      setState(() => _order = effectiveOrder);

      if (orderUsesOvanieLogistics(effectiveOrder)) {
        final driverReserved = orderMap(effectiveOrder['tracking'])['driver_reserved'] == true;
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
        // « expédition » : il prépare, puis remet les produits au livreur.
        Navigator.of(context).pushReplacement<void, void>(
          MaterialPageRoute<void>(
            builder: (_) => OrderTrackingScreen(orderId: widget.orderId, initialOrder: effectiveOrder),
          ),
        );
        return;
      }

      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Préparation validée. La commande est prête.')),
      );
      Navigator.of(context).pushReplacement<bool, bool>(
        MaterialPageRoute<bool>(
          builder: (_) => OrderExpeditionScreen(orderId: widget.orderId, initialOrder: effectiveOrder),
        ),
      );
    } catch (e) {
      if (mounted) setState(() => _error = ApiClient.friendlyError(e));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  void _toggleAll() {
    setState(() {
      if (_allItemsChecked) {
        _checkedItems.clear();
      } else {
        for (final item in _items) {
          final id = int.tryParse('${item['id'] ?? ''}');
          if (id != null) _checkedItems.add(id);
        }
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final locked = _order.isNotEmpty && !_editable && !_saving && !_loading && _error == null;
    return OrderPage(
      title: 'Préparation de commande',
      subtitle: 'Préparez et vérifiez les articles avant de signaler la commande prête.',
      loading: _loading,
      error: _error,
      hasContent: _order.isNotEmpty,
      onRefresh: _load,
      actions: [
        MkBarButton(label: 'Retour', icon: Icons.arrow_back_rounded, onTap: _saving ? null : () => Navigator.pop(context)),
        MkBarButton(
          label: 'Valider la préparation',
          filled: true,
          loading: _saving,
          onTap: _editable ? _validate : null,
        ),
      ],
      children: [
        OrderHeadCard(order: _order, currentStep: 2),
        if (orderValue(orderMap(_order['tracking'])['assignment_message'], '').isNotEmpty) OrderLogisticsBanner(order: _order),
        OrderInfosCard(order: _order),
        if (locked)
          const MkInfoBox(
            icon: Icons.lock_outline_rounded,
            child: Text('La préparation ne peut plus être modifiée pour cette commande.'),
          ),
        _itemsCard(),
        _checksCard(),
      ],
    );
  }

  Widget _itemsCard() {
    final items = _items;
    final progress = items.isEmpty ? 0.0 : _checkedCount / items.length;
    return OrderCard(
      padding: const EdgeInsets.fromLTRB(14, 12, 14, 4),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(child: OrderSectionTitle('Articles à préparer (${items.length})')),
              if (_editable && items.isNotEmpty)
                InkWell(
                  onTap: _toggleAll,
                  borderRadius: BorderRadius.circular(8),
                  child: Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 8),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(
                          _allItemsChecked ? Icons.check_box_rounded : Icons.check_box_outline_blank_rounded,
                          color: orderBlue,
                          size: 22,
                        ),
                        const SizedBox(width: 6),
                        Text(
                          _allItemsChecked ? 'Tout décocher' : 'Tout cocher',
                          style: const TextStyle(color: orderBlue, fontSize: 13, fontWeight: FontWeight.w700),
                        ),
                      ],
                    ),
                  ),
                ),
            ],
          ),
          const SizedBox(height: 6),
          Row(
            children: [
              Expanded(
                child: ClipRRect(
                  borderRadius: BorderRadius.circular(99),
                  child: LinearProgressIndicator(
                    value: progress,
                    minHeight: 7,
                    color: Mk.orange,
                    backgroundColor: const Color(0xFFE6E9F0),
                  ),
                ),
              ),
              const SizedBox(width: 10),
              Text(
                '$_checkedCount/${items.length}',
                style: const TextStyle(color: Color(0xFFE85400), fontSize: 13, fontWeight: FontWeight.w800),
              ),
            ],
          ),
          const SizedBox(height: 4),
          if (items.isEmpty)
            const Padding(
              padding: EdgeInsets.symmetric(vertical: 12),
              child: Text('Aucun article à préparer pour votre boutique.', style: TextStyle(color: Mk.muted, fontSize: 13.5)),
            ),
          for (var i = 0; i < items.length; i++) ...[
            if (i > 0) const Divider(height: 1, color: orderBorder),
            _itemLine(items[i]),
          ],
        ],
      ),
    );
  }

  Widget _itemLine(Map<String, dynamic> item) {
    final id = int.tryParse('${item['id'] ?? ''}');
    final checked = id != null && _checkedItems.contains(id);
    final stock = item['stock_available'];
    final toggle = id == null || !_editable
        ? null
        : () => setState(() {
              if (checked) {
                _checkedItems.remove(id);
              } else {
                _checkedItems.add(id);
              }
            });
    return Semantics(
      checked: checked,
      label: 'Article préparé : ${orderValue(item['name'], 'Produit')}',
      child: OrderItemLine(
        item: item,
        quantityLabel: 'Quantité commandée',
        onTap: toggle,
        leading: OrderCheckBox(value: checked),
        tag: stock == true
            ? const OrderTag('En stock', color: orderGreen)
            : stock == false
                ? const OrderTag('Stock insuffisant', color: Color(0xFFD92D20))
                : const OrderTag('Stock à vérifier', color: Color(0xFFE85400)),
      ),
    );
  }

  Widget _checksCard() {
    return OrderCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const OrderSectionTitle('Vérifications avant validation'),
          const SizedBox(height: 8),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              if (MediaQuery.sizeOf(context).width >= 380)
                Padding(
                  padding: const EdgeInsets.only(right: 12, top: 4),
                  child: Image.asset('assets/images/mockups/prep_box.jpg', width: 86, excludeFromSemantics: true),
                ),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Assurez-vous que tous les articles sont correctement préparés, bien emballés et conformes à la commande.',
                      style: TextStyle(color: Mk.muted, fontSize: 12.5, height: 1.4, fontWeight: FontWeight.w500),
                    ),
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
        ],
      ),
    );
  }
}
