import 'package:flutter/material.dart';
import '../../../app/theme.dart';
import '../../../core/network/api_client.dart';
import '../../../core/utils/formatters.dart';
import '../../cart/data/cart_api_repository.dart';
import '../../payments/presentation/resume_payment_screen.dart';
import '../../returns/presentation/return_form_screen.dart';
import '../../tracking/presentation/client_delivery_tracking_screen.dart';
import '../data/orders_repository.dart';
import '../domain/order_model.dart';
import 'invoice_screen.dart';

class OrderDetailScreen extends StatefulWidget {
  final int orderId;
  final MobileOrder? initialOrder;

  const OrderDetailScreen({
    super.key,
    required this.orderId,
    this.initialOrder,
  });

  @override
  State<OrderDetailScreen> createState() => _OrderDetailScreenState();
}

class _OrderDetailScreenState extends State<OrderDetailScreen> {
  final _repository = const OrdersRepository();
  MobileOrderDetail? _detail;
  bool _loading = true;
  bool _actionLoading = false;
  String? _error;

  MobileOrder? get _order => _detail?.order ?? widget.initialOrder;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    if (!mounted) return;
    setState(() {
      _loading = _detail == null;
      _error = null;
    });
    try {
      final detail = await _repository.fetchOrderDetail(widget.orderId);
      if (!mounted) return;
      setState(() {
        _detail = detail;
        _loading = false;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _loading = false;
        _error = ApiClient.friendlyError(error);
      });
    }
  }

  Future<void> _confirmReception() async {
    final order = _order;
    if (order == null || !order.actions.canConfirmReception || _actionLoading) return;

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Confirmer la réception ?'),
        content: const Text(
          'Confirmez uniquement si tous les produits de cette commande ont réellement été reçus. Cette action met à jour le même suivi logistique que sur le Web.',
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Annuler')),
          FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Oui, j’ai reçu')),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;

    setState(() => _actionLoading = true);
    try {
      await _repository.confirmReception(order.id);
      await _load();
      if (!mounted) return;
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(const SnackBar(content: Text('Réception confirmée dans OVANIE.')));
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(SnackBar(content: Text(ApiClient.friendlyError(error))));
    } finally {
      if (mounted) setState(() => _actionLoading = false);
    }
  }

  Future<void> _cancelOrder() async {
    final order = _order;
    if (order == null || !order.actions.canCancel || _actionLoading) return;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Annuler cette commande ?'),
        content: const Text('OVANIE appliquera les mêmes règles d’annulation que sur le Web. Le stock, le panier, la livraison et le paiement seront mis à jour par Laravel.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Conserver la commande')),
          FilledButton(onPressed: () => Navigator.pop(context, true), style: FilledButton.styleFrom(backgroundColor: OvanieColors.danger), child: const Text('Annuler la commande')),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;
    setState(() => _actionLoading = true);
    try {
      await _repository.cancelOrder(order.id);
      try { await const CartApiRepository().refreshLocalCartFromServer(); } catch (_) {}
      await _load();
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Commande annulée dans OVANIE.')));
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(ApiClient.friendlyError(error))));
    } finally {
      if (mounted) setState(() => _actionLoading = false);
    }
  }

  Future<void> _reorder() async {
    final order = _order;
    if (order == null || !order.actions.canReorder || _actionLoading) return;
    setState(() => _actionLoading = true);
    try {
      final result = await _repository.reorder(order.id);
      await const CartApiRepository().refreshLocalCartFromServer();
      if (!mounted) return;
      final extra = result.skippedLines > 0 ? ' ${result.skippedLines} ligne(s) indisponible(s) ont été ignorée(s).' : '';
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('${result.message}$extra')));
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(ApiClient.friendlyError(error))));
    } finally {
      if (mounted) setState(() => _actionLoading = false);
    }
  }

  Future<void> _resumePayment() async {
    final order = _order;
    if (order == null || !order.actions.canResumePayment) return;
    await Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => ResumePaymentScreen(order: order)));
    await _load();
  }

  Future<void> _openInvoice() async {
    final order = _order;
    if (order == null || !order.actions.canDownloadInvoice) return;
    await Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => InvoiceScreen(orderId: order.id, initialOrder: order),
      ),
    );
  }

  Future<void> _openReturn() async {
    final order = _order;
    if (order == null || !order.actions.canOpenReturn) return;
    final result = await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => ReturnFormScreen(initialOrderId: order.id),
      ),
    );
    if (result != null) await _load();
  }

  void _openTracking() {
    final order = _order;
    if (order == null || !order.actions.canTrack) return;
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => ClientDeliveryTrackingScreen(
          orderId: order.id,
          initialOrderNumber: order.orderNumber,
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final order = _order;
    return Scaffold(
      appBar: AppBar(
        centerTitle: true,
        title: const Text(
          'Détail commande',
          style: TextStyle(fontWeight: FontWeight.w900),
        ),
        actions: [
          IconButton(onPressed: _loading ? null : _load, tooltip: 'Actualiser', icon: const Icon(Icons.refresh_rounded)),
        ],
      ),
      body: _loading && order == null
          ? const Center(child: CircularProgressIndicator())
          : order == null
              ? _DetailError(message: _error ?? 'Commande indisponible.', onRetry: _load)
              : RefreshIndicator(
                  onRefresh: _load,
                  child: ListView(
                    physics: const AlwaysScrollableScrollPhysics(),
                    padding: const EdgeInsets.fromLTRB(15, 12, 15, 80),
                    children: [
                      if (_error != null) ...[
                        _InlineInfo(text: _error!, icon: Icons.cloud_off_outlined, color: OvanieColors.warning),
                        const SizedBox(height: 10),
                      ],
                      _DetailHeading(order: order),
                      const SizedBox(height: 10),
                      _OrderOverview(order: order),
                      if ((_detail?.timeline ?? const <OrderTimelineEvent>[]).isNotEmpty) ...[
                        const SizedBox(height: 10),
                        _HorizontalOrderProgress(events: _detail!.timeline, order: order),
                      ],
                      if (order.estimatedMinDate != null || order.estimatedMaxDate != null || order.actions.canTrack) ...[
                        const SizedBox(height: 10),
                        _DeliveryStrip(order: order, onTrack: _openTracking),
                      ],
                      const SizedBox(height: 10),
                      _SectionCard(
                        title: 'Articles commandés',
                        icon: Icons.inventory_2_outlined,
                        child: Column(
                          children: [
                            for (var i = 0; i < order.items.length; i++) ...[
                              _ProductRow(item: order.items[i]),
                              if (i != order.items.length - 1) const Divider(height: 22),
                            ],
                          ],
                        ),
                      ),
                      const SizedBox(height: 10),
                      _FinancialCard(order: order),
                      const SizedBox(height: 10),
                      _PaymentAndAddress(order: order),
                      const SizedBox(height: 10),
                      _ActionsCard(
                        order: order,
                        busy: _actionLoading,
                        onTrack: _openTracking,
                        onReception: _confirmReception,
                        onInvoice: _openInvoice,
                        onReturn: _openReturn,
                        onCancel: _cancelOrder,
                        onReorder: _reorder,
                        onResumePayment: _resumePayment,
                      ),
                    ],
                  ),
                ),
    );
  }

  static String _fallback(String value, String fallback) => value.trim().isEmpty ? fallback : value;

  static String _date(DateTime? value, {bool withTime = false}) {
    if (value == null) return '—';
    String two(int n) => n.toString().padLeft(2, '0');
    final base = '${two(value.day)}/${two(value.month)}/${value.year}';
    return withTime ? '$base à ${two(value.hour)}:${two(value.minute)}' : base;
  }

  static String _fullAddress(MobileOrder order) {
    final values = <String>[order.address, order.quartier, order.commune, order.city]
        .map((value) => value.trim())
        .where((value) => value.isNotEmpty)
        .toList(growable: false);
    final unique = <String>[];
    for (final value in values) {
      if (!unique.any((existing) => existing.toLowerCase() == value.toLowerCase())) unique.add(value);
    }
    return unique.isEmpty ? 'Adresse non renseignée' : unique.join(', ');
  }
}

class _DetailHeading extends StatelessWidget {
  final MobileOrder order;
  const _DetailHeading({required this.order});
  @override
  Widget build(BuildContext context) => Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text('Commande n°  ${order.orderNumber}', style: const TextStyle(color: OvanieColors.navy, fontSize: 15, fontWeight: FontWeight.w900)),
        const SizedBox(height: 5),
        Text(_OrderDetailScreenState._date(order.createdAt, withTime: true), style: const TextStyle(color: OvanieColors.muted, fontSize: 11)),
      ]);
}

class _OrderOverview extends StatelessWidget {
  final MobileOrder order;
  const _OrderOverview({required this.order});
  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.fromLTRB(10, 12, 10, 9),
        decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(10), border: Border.all(color: OvanieColors.border)),
        child: Column(children: [
          Row(children: [
            Expanded(child: _OverviewValue(label: 'Statut de la commande', child: _StatusPill(text: order.statusLabel))),
            Container(width: 1, height: 46, color: OvanieColors.border),
            Expanded(child: _OverviewValue(label: 'Paiement', child: Text(order.isPaymentConfirmed ? '✓ Payé' : (order.paymentMethod.toLowerCase().contains('cash') || order.paymentMethodLabel.toLowerCase().contains('livraison') ? 'À payer à la livraison' : order.paymentStatusLabel), maxLines: 2, textAlign: TextAlign.center, overflow: TextOverflow.ellipsis, style: TextStyle(color: order.isPaymentConfirmed ? OvanieColors.success : OvanieColors.navy, fontSize: 10, fontWeight: FontWeight.w900)))),
            Container(width: 1, height: 46, color: OvanieColors.border),
            Expanded(child: _OverviewValue(label: 'Montant', child: FittedBox(fit: BoxFit.scaleDown, child: Text(formatFcfa(order.total), style: const TextStyle(color: OvanieColors.orange, fontSize: 18, fontWeight: FontWeight.w900))))),
          ]),
          const Divider(height: 18),
          Row(children: [
            const Icon(Icons.location_on_outlined, color: OvanieColors.navy, size: 19),
            const SizedBox(width: 7),
            const Text('Livraison à :', style: TextStyle(color: OvanieColors.muted, fontSize: 10)),
            const SizedBox(width: 7),
            Expanded(child: Text(_OrderDetailScreenState._fullAddress(order), maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: OvanieColors.navy, fontSize: 10.8, fontWeight: FontWeight.w800))),
          ]),
        ]),
      );
}

class _OverviewValue extends StatelessWidget {
  final String label;
  final Widget child;
  const _OverviewValue({required this.label, required this.child});
  @override
  Widget build(BuildContext context) => Padding(padding: const EdgeInsets.symmetric(horizontal: 5), child: Column(children: [Text(label, textAlign: TextAlign.center, style: const TextStyle(color: OvanieColors.muted, fontSize: 8.5)), const SizedBox(height: 7), child]));
}

class _HorizontalOrderProgress extends StatelessWidget {
  final List<OrderTimelineEvent> events;
  final MobileOrder order;
  const _HorizontalOrderProgress({required this.events, required this.order});
  @override
  Widget build(BuildContext context) {
    final paymentText = '${order.paymentMethod} ${order.paymentMethodLabel}'.toLowerCase();
    final cashOnDelivery = paymentText.contains('cash_on_delivery') ||
        paymentText.contains('cash on delivery') ||
        paymentText.contains('à la livraison') ||
        paymentText.contains('a la livraison');
    final labels = cashOnDelivery
        ? ['Commande\nreçue', 'Préparation', 'Expédiée', 'Livrée', 'Paiement à\nla livraison']
        : ['Commande\nreçue', 'Paiement\nconfirmé', 'Préparation', 'Expédiée', 'Livrée'];
    final status = '${order.status} ${order.deliveryStatusLabel}'.toLowerCase();
    var step = 1;
    if (order.isPaymentConfirmed) step = 2;
    if (status.contains('prepar') || status.contains('confirm')) step = 3;
    if (status.contains('livraison') || status.contains('expedi')) step = 4;
    if (order.deliveredAt != null || status.contains('livré') || status.contains('livre')) step = 5;
    final completed = cashOnDelivery
        ? <bool>[true, step >= 3, step >= 4, step >= 5, order.isPaymentConfirmed]
        : <bool>[true, order.isPaymentConfirmed, step >= 3, step >= 4, step >= 5];
    final visualStep = cashOnDelivery
        ? (order.isPaymentConfirmed ? 5 : step >= 5 ? 4 : step >= 4 ? 3 : step >= 3 ? 2 : 1)
        : step;
    return Container(
      padding: const EdgeInsets.fromLTRB(7, 11, 7, 7),
      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(10), border: Border.all(color: OvanieColors.border)),
      child: SizedBox(height: 66, child: LayoutBuilder(builder: (context, c) {
        final segment = c.maxWidth / 5;
        return Stack(children: [
          Positioned(left: segment / 2, right: segment / 2, top: 14, child: Container(height: 2, color: OvanieColors.border)),
          Positioned(left: segment / 2, top: 14, width: segment * (visualStep - 1), child: Container(height: 2, color: OvanieColors.blue)),
          Row(children: List.generate(5, (i) {
            final waitingForDeliveryPayment = i == 4 && cashOnDelivery && !order.isPaymentConfirmed;
            final nodeColor = completed[i]
                ? OvanieColors.blue
                : waitingForDeliveryPayment
                    ? const Color(0xFFFFF0D9)
                    : const Color(0xFFF0F2F6);
            return Expanded(child: Column(children: [
                Container(
                  width: 29,
                  height: 29,
                  alignment: Alignment.center,
                  decoration: BoxDecoration(shape: BoxShape.circle, color: nodeColor),
                  child: completed[i]
                      ? const Icon(Icons.check, size: 17, color: Colors.white)
                      : waitingForDeliveryPayment
                          ? const Icon(Icons.schedule_rounded, size: 16, color: OvanieColors.orange)
                          : Text('${i + 1}', style: const TextStyle(fontWeight: FontWeight.w800)),
                ),
                const SizedBox(height: 5),
                Text(labels[i], textAlign: TextAlign.center, style: TextStyle(color: waitingForDeliveryPayment ? OvanieColors.orange : OvanieColors.navy, fontSize: 8, height: 1.06, fontWeight: waitingForDeliveryPayment ? FontWeight.w700 : FontWeight.normal)),
              ]));
          })),
        ]);
      })),
    );
  }
}

class _DeliveryStrip extends StatelessWidget {
  final MobileOrder order;
  final VoidCallback onTrack;
  const _DeliveryStrip({required this.order, required this.onTrack});
  @override
  Widget build(BuildContext context) {
    final estimate = order.estimatedMaxDate ?? order.estimatedMinDate;
    return Container(
      padding: const EdgeInsets.all(10),
      decoration: BoxDecoration(color: const Color(0xFFF6F9FF), borderRadius: BorderRadius.circular(10), border: Border.all(color: OvanieColors.border)),
      child: Row(children: [
        const CircleAvatar(backgroundColor: Color(0xFFE3EEFF), child: Icon(Icons.delivery_dining_outlined, color: OvanieColors.blue)),
        const SizedBox(width: 9),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [const Text('Livraison estimée', style: TextStyle(color: OvanieColors.muted, fontSize: 9)), Text(estimate == null ? 'À confirmer' : _OrderDetailScreenState._date(estimate), style: const TextStyle(color: OvanieColors.blue, fontSize: 12, fontWeight: FontWeight.w900))])),
        if (order.actions.canTrack) OutlinedButton.icon(onPressed: onTrack, icon: const Icon(Icons.local_shipping_outlined, size: 16), label: const Text('Suivre', style: TextStyle(fontSize: 10))),
      ]),
    );
  }
}

class _PaymentAndAddress extends StatelessWidget {
  final MobileOrder order;
  const _PaymentAndAddress({required this.order});
  Widget _card({required String title, required IconData icon, required List<Widget> children}) => Container(
        padding: const EdgeInsets.all(10),
        decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(10), border: Border.all(color: OvanieColors.border)),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [Icon(icon, color: OvanieColors.navy, size: 17), const SizedBox(width: 6), Expanded(child: Text(title, style: const TextStyle(color: OvanieColors.navy, fontSize: 11, fontWeight: FontWeight.w900)))]),
          const SizedBox(height: 10),
          ...children,
        ]),
      );
  Widget _payment() => _card(title: 'Paiement', icon: Icons.payments_outlined, children: [
        _MiniInfo(label: 'Méthode', value: order.paymentMethodLabel),
        _MiniInfo(label: 'Statut', value: order.paymentStatusLabel, valueColor: order.isPaymentConfirmed ? OvanieColors.success : null),
        if (order.paymentReference.isNotEmpty) _MiniInfo(label: 'Référence', value: order.paymentReference),
        if (order.outstandingAmount > 0) _MiniInfo(label: 'Reste', value: formatFcfa(order.outstandingAmount)),
      ]);
  Widget _address() => _card(title: 'Adresse de livraison', icon: Icons.location_on_outlined, children: [
        if (order.deliveryRecipientName.isNotEmpty) _MiniInfo(label: 'Destinataire', value: order.deliveryRecipientName),
        if (order.deliveryPhone.isNotEmpty) _MiniInfo(label: 'Téléphone', value: order.deliveryPhone),
        _MiniInfo(label: 'Adresse', value: _OrderDetailScreenState._fullAddress(order)),
      ]);
  @override
  Widget build(BuildContext context) => Row(crossAxisAlignment: CrossAxisAlignment.start, children: [Expanded(child: _payment()), const SizedBox(width: 8), Expanded(child: _address())]);
}

class _MiniInfo extends StatelessWidget {
  final String label;
  final String value;
  final Color? valueColor;
  const _MiniInfo({required this.label, required this.value, this.valueColor});
  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.only(bottom: 6),
        child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          SizedBox(width: 49, child: Text(label, style: const TextStyle(color: OvanieColors.muted, fontSize: 8.2))),
          Expanded(child: Text(value.trim().isEmpty ? '—' : value, maxLines: 3, overflow: TextOverflow.ellipsis, style: TextStyle(color: valueColor ?? OvanieColors.navy, fontSize: 8.8, height: 1.2, fontWeight: FontWeight.w700))),
        ]),
      );
}

class _HeaderCard extends StatelessWidget {
  final MobileOrder order;
  const _HeaderCard({required this.order});

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: OvanieColors.border),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text('Commande n° ${order.orderNumber}', style: const TextStyle(color: OvanieColors.navy, fontSize: 16, fontWeight: FontWeight.w900)),
                ),
                _StatusPill(text: order.statusLabel),
              ],
            ),
            const SizedBox(height: 7),
            Text(
              'Commande du ${_OrderDetailScreenState._date(order.createdAt, withTime: true)}',
              style: const TextStyle(color: OvanieColors.muted, fontSize: 11.5),
            ),
            if (order.invoiceNumber.isNotEmpty) ...[
              const SizedBox(height: 5),
              Text('Facture : ${order.invoiceNumber}', style: const TextStyle(color: OvanieColors.muted, fontSize: 11)),
            ],
            const SizedBox(height: 15),
            Text(formatFcfa(order.total), style: const TextStyle(color: OvanieColors.orange, fontSize: 22, fontWeight: FontWeight.w900)),
          ],
        ),
      );
}

class _StatusPill extends StatelessWidget {
  final String text;
  const _StatusPill({required this.text});

  @override
  Widget build(BuildContext context) => Container(
        constraints: const BoxConstraints(maxWidth: 145),
        padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 6),
        decoration: BoxDecoration(color: const Color(0xFFEAF2FF), borderRadius: BorderRadius.circular(30)),
        child: Text(text, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: OvanieColors.blue, fontSize: 10, fontWeight: FontWeight.w900)),
      );
}

class _SectionCard extends StatelessWidget {
  final String title;
  final IconData icon;
  final Widget child;
  const _SectionCard({required this.title, required this.icon, required this.child});

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.all(15),
        decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(18), border: Border.all(color: OvanieColors.border)),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(children: [
              Icon(icon, color: OvanieColors.blue, size: 20),
              const SizedBox(width: 8),
              Text(title, style: const TextStyle(color: OvanieColors.navy, fontSize: 13.5, fontWeight: FontWeight.w900)),
            ]),
            const SizedBox(height: 14),
            child,
          ],
        ),
      );
}

class _ProductRow extends StatelessWidget {
  final MobileOrderItem item;
  const _ProductRow({required this.item});

  @override
  Widget build(BuildContext context) => Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 62,
            height: 62,
            padding: const EdgeInsets.all(5),
            decoration: BoxDecoration(color: OvanieColors.background, borderRadius: BorderRadius.circular(12)),
            child: item.imageUrl.isEmpty
                ? const Icon(Icons.inventory_2_outlined, color: OvanieColors.muted)
                : Image.network(item.imageUrl, fit: BoxFit.contain, errorBuilder: (_, __, ___) => const Icon(Icons.inventory_2_outlined, color: OvanieColors.muted)),
          ),
          const SizedBox(width: 11),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(item.productName, maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(color: OvanieColors.text, fontSize: 12, fontWeight: FontWeight.w800)),
                const SizedBox(height: 5),
                Text('Qté : ${item.quantity} × ${formatFcfa(item.unitPrice)}', style: const TextStyle(color: OvanieColors.muted, fontSize: 10.5)),
                if (item.deliveryStatusLabel.isNotEmpty) ...[
                  const SizedBox(height: 4),
                  Text(item.deliveryStatusLabel, style: const TextStyle(color: OvanieColors.blue, fontSize: 10, fontWeight: FontWeight.w800)),
                ],
              ],
            ),
          ),
          const SizedBox(width: 8),
          ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 105),
            child: Text(
              formatFcfa(item.subtotal),
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              textAlign: TextAlign.right,
              style: const TextStyle(color: OvanieColors.navy, fontSize: 11, fontWeight: FontWeight.w900),
            ),
          ),
        ],
      );
}

class _FinancialCard extends StatelessWidget {
  final MobileOrder order;
  const _FinancialCard({required this.order});

  @override
  Widget build(BuildContext context) => _SectionCard(
        title: 'Récapitulatif',
        icon: Icons.receipt_long_outlined,
        child: Column(
          children: [
            _InfoRow(label: 'Produits', value: formatFcfa(order.subtotal)),
            _InfoRow(label: 'Livraison', value: formatFcfa(order.deliveryFee)),
            if (order.discount > 0) _InfoRow(label: 'Remise', value: '- ${formatFcfa(order.discount)}'),
            if (order.loyaltyDiscount > 0) _InfoRow(label: 'Avantage fidélité', value: '- ${formatFcfa(order.loyaltyDiscount)}'),
            const Divider(height: 22),
            _InfoRow(label: 'Total', value: formatFcfa(order.total), emphasize: true),
          ],
        ),
      );
}

class _InfoRow extends StatelessWidget {
  final String label;
  final String value;
  final bool emphasize;
  const _InfoRow({required this.label, required this.value, this.emphasize = false});

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 5),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            SizedBox(width: 105, child: Text(label, style: TextStyle(color: OvanieColors.muted, fontSize: emphasize ? 12 : 11))),
            const SizedBox(width: 8),
            Expanded(
              child: Text(
                value,
                textAlign: TextAlign.right,
                style: TextStyle(
                  color: emphasize ? OvanieColors.orange : OvanieColors.text,
                  fontSize: emphasize ? 16 : 11.2,
                  fontWeight: emphasize ? FontWeight.w900 : FontWeight.w700,
                ),
              ),
            ),
          ],
        ),
      );
}

class _TimelineCard extends StatelessWidget {
  final List<OrderTimelineEvent> events;
  const _TimelineCard({required this.events});

  @override
  Widget build(BuildContext context) => _SectionCard(
        title: 'Historique de la commande',
        icon: Icons.history_rounded,
        child: Column(
          children: [
            for (var index = 0; index < events.length; index++)
              _TimelineRow(event: events[index], last: index == events.length - 1),
          ],
        ),
      );
}

class _TimelineRow extends StatelessWidget {
  final OrderTimelineEvent event;
  final bool last;
  const _TimelineRow({required this.event, required this.last});

  @override
  Widget build(BuildContext context) => Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 24,
            child: Column(
              children: [
                Container(width: 10, height: 10, decoration: const BoxDecoration(color: OvanieColors.blue, shape: BoxShape.circle)),
                if (!last) Container(width: 2, height: 52, color: OvanieColors.border),
              ],
            ),
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Padding(
              padding: const EdgeInsets.only(bottom: 13),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(event.label, style: const TextStyle(color: OvanieColors.text, fontSize: 11.5, fontWeight: FontWeight.w900)),
                  if (event.message.isNotEmpty) ...[
                    const SizedBox(height: 3),
                    Text(event.message, style: const TextStyle(color: OvanieColors.muted, fontSize: 10.5, height: 1.3)),
                  ],
                  if (event.date != null) ...[
                    const SizedBox(height: 3),
                    Text(_OrderDetailScreenState._date(event.date, withTime: true), style: const TextStyle(color: OvanieColors.muted, fontSize: 9.7)),
                  ],
                ],
              ),
            ),
          ),
        ],
      );
}

class _PaymentStateBanner extends StatelessWidget {
  final MobileOrder order;
  const _PaymentStateBanner({required this.order});

  @override
  Widget build(BuildContext context) {
    final cod = order.paymentMethod.contains('cash') || order.paymentMethodLabel.toLowerCase().contains('livraison');
    late final Color color;
    late final IconData icon;
    late final String title;
    late final String message;

    if (order.isPaymentConfirmed) {
      color = OvanieColors.success;
      icon = Icons.verified_rounded;
      title = 'Paiement confirmé';
      message = 'Le paiement est confirmé dans Laravel.';
    } else if (cod) {
      color = OvanieColors.blue;
      icon = Icons.local_shipping_outlined;
      title = 'Paiement à la livraison';
      message = 'Aucun paiement en ligne n’est marqué comme payé avant la livraison.';
    } else if (order.isPaymentFailed) {
      color = OvanieColors.danger;
      icon = Icons.error_outline_rounded;
      title = 'Paiement échoué';
      message = 'Aucun débit n’est considéré confirmé. Vous pouvez reprendre le paiement si Laravel l’autorise.';
    } else if (order.isPaymentCancelled) {
      color = OvanieColors.danger;
      icon = Icons.cancel_outlined;
      title = 'Paiement annulé';
      message = 'La tentative a été annulée. Le statut affiché vient du backend OVANIE.';
    } else {
      color = OvanieColors.warning;
      icon = Icons.schedule_rounded;
      title = 'Paiement en attente';
      message = 'OVANIE attend la confirmation réelle du prestataire de paiement.';
    }

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(11),
      decoration: BoxDecoration(color: color.withValues(alpha: .07), borderRadius: BorderRadius.circular(12), border: Border.all(color: color.withValues(alpha: .20))),
      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Icon(icon, color: color, size: 19),
        const SizedBox(width: 8),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(title, style: TextStyle(color: color, fontWeight: FontWeight.w900, fontSize: 11.5)),
          const SizedBox(height: 2),
          Text(message, style: const TextStyle(color: OvanieColors.muted, fontSize: 10.3, height: 1.35)),
          if (order.paymentAttemptStatusLabel.isNotEmpty) ...[
            const SizedBox(height: 3),
            Text('Dernière tentative : ${order.paymentAttemptStatusLabel}', style: const TextStyle(color: OvanieColors.muted, fontSize: 9.8)),
          ],
        ])),
      ]),
    );
  }
}

class _ActionsCard extends StatelessWidget {
  final MobileOrder order;
  final bool busy;
  final VoidCallback onTrack;
  final VoidCallback onReception;
  final VoidCallback onInvoice;
  final VoidCallback onReturn;
  final VoidCallback onCancel;
  final VoidCallback onReorder;
  final VoidCallback onResumePayment;

  const _ActionsCard({
    required this.order,
    required this.busy,
    required this.onTrack,
    required this.onReception,
    required this.onInvoice,
    required this.onReturn,
    required this.onCancel,
    required this.onReorder,
    required this.onResumePayment,
  });

  @override
  Widget build(BuildContext context) {
    final actions = <Widget>[];
    if (order.actions.canResumePayment) {
      actions.add(_ActionButton(icon: Icons.refresh_rounded, text: 'Reprendre le paiement', onPressed: busy ? null : onResumePayment, primary: true));
    }
    if (order.actions.canTrack) {
      actions.add(_ActionButton(icon: Icons.local_shipping_outlined, text: 'Suivre la livraison', onPressed: busy ? null : onTrack, primary: true));
    }
    if (order.actions.canConfirmReception) {
      actions.add(_ActionButton(icon: Icons.verified_outlined, text: 'Confirmer la réception', onPressed: busy ? null : onReception));
    }
    if (order.actions.canDownloadInvoice) {
      actions.add(_ActionButton(icon: Icons.picture_as_pdf_outlined, text: 'Consulter la facture', onPressed: busy ? null : onInvoice));
    }
    if (order.actions.canOpenReturn) {
      actions.add(_ActionButton(icon: Icons.assignment_return_outlined, text: 'Retour / réclamation', onPressed: busy ? null : onReturn));
    }
    if (order.actions.canReorder) {
      actions.add(_ActionButton(icon: Icons.replay_rounded, text: 'Commander à nouveau', onPressed: busy ? null : onReorder));
    }
    if (order.actions.canCancel) {
      actions.add(_ActionButton(icon: Icons.cancel_outlined, text: 'Annuler la commande', onPressed: busy ? null : onCancel));
    }

    if (actions.isEmpty) {
      return const _InlineInfo(
        text: 'Aucune action n’est disponible pour cette commande actuellement.',
        icon: Icons.info_outline_rounded,
        color: OvanieColors.muted,
      );
    }
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      if (busy) const Padding(padding: EdgeInsets.only(bottom: 10), child: LinearProgressIndicator()),
      if (actions.length > 2) ...[
        Container(
          width: double.infinity,
          padding: const EdgeInsets.all(10),
          decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(10), border: Border.all(color: OvanieColors.border)),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            const Text('Besoin d’aide ou d’autres actions ?', style: TextStyle(color: OvanieColors.navy, fontSize: 10.5, fontWeight: FontWeight.w800)),
            const SizedBox(height: 8),
            LayoutBuilder(builder: (context, c) {
              final width = (c.maxWidth - 8) / 2;
              return Wrap(spacing: 8, runSpacing: 8, children: [for (final action in actions) SizedBox(width: width, child: action)]);
            }),
          ]),
        ),
      ] else
        Row(children: [
          for (var i = 0; i < actions.length; i++) ...[
            Expanded(child: actions[i]),
            if (i != actions.length - 1) const SizedBox(width: 8),
          ],
        ]),
    ]);
  }
}

class _ActionButton extends StatelessWidget {
  final IconData icon;
  final String text;
  final VoidCallback? onPressed;
  final bool primary;
  const _ActionButton({required this.icon, required this.text, required this.onPressed, this.primary = false});

  @override
  Widget build(BuildContext context) => SizedBox(
        width: double.infinity,
        height: 46,
        child: primary
            ? FilledButton.icon(
                onPressed: onPressed,
                style: FilledButton.styleFrom(backgroundColor: OvanieColors.orange, foregroundColor: Colors.white, padding: const EdgeInsets.symmetric(horizontal: 8), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(7))),
                icon: Icon(icon, size: 17),
                label: FittedBox(child: Text(text, style: const TextStyle(fontSize: 10.5, fontWeight: FontWeight.w900))),
              )
            : OutlinedButton.icon(
                onPressed: onPressed,
                style: OutlinedButton.styleFrom(padding: const EdgeInsets.symmetric(horizontal: 7), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(7))),
                icon: Icon(icon, size: 17),
                label: FittedBox(child: Text(text, style: const TextStyle(fontSize: 10.5, fontWeight: FontWeight.w900))),
              ),
      );
}

class _InlineInfo extends StatelessWidget {
  final String text;
  final IconData icon;
  final Color color;
  const _InlineInfo({required this.text, required this.icon, required this.color});

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(color: color.withValues(alpha: .07), borderRadius: BorderRadius.circular(13), border: Border.all(color: color.withValues(alpha: .18))),
        child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Icon(icon, color: color, size: 18),
          const SizedBox(width: 8),
          Expanded(child: Text(text, style: TextStyle(color: color, fontSize: 10.7, height: 1.35, fontWeight: FontWeight.w700))),
        ]),
      );
}

class _DetailError extends StatelessWidget {
  final String message;
  final Future<void> Function() onRetry;
  const _DetailError({required this.message, required this.onRetry});

  @override
  Widget build(BuildContext context) => Center(
        child: Padding(
          padding: const EdgeInsets.all(28),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            const Icon(Icons.cloud_off_outlined, size: 48, color: OvanieColors.muted),
            const SizedBox(height: 12),
            Text(message, textAlign: TextAlign.center, style: const TextStyle(color: OvanieColors.muted)),
            const SizedBox(height: 14),
            OutlinedButton(onPressed: onRetry, child: const Text('Réessayer')),
          ]),
        ),
      );
}
