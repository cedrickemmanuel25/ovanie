import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../app/theme.dart';
import '../../../core/network/api_client.dart';
import '../../../core/sync/live_sync_service.dart';
import '../../../core/utils/formatters.dart';
import '../../../shared/widgets/ovanie_bottom_navigation.dart';
import '../../cart/data/cart_api_repository.dart';
import '../../payments/presentation/payment_ui.dart';
import '../../shell/domain/main_navigation_store.dart';
import '../../payments/presentation/resume_payment_screen.dart';
import '../../returns/presentation/return_form_screen.dart';
import '../../tracking/presentation/client_delivery_tracking_screen.dart';
import '../data/orders_repository.dart';
import '../domain/order_model.dart';
import 'invoice_screen.dart';

class OrderDetailScreen extends StatefulWidget {
  final int orderId;
  final MobileOrder? initialOrder;

  const OrderDetailScreen({super.key, required this.orderId, this.initialOrder});

  @override
  State<OrderDetailScreen> createState() => _OrderDetailScreenState();
}

class _OrderDetailScreenState extends State<OrderDetailScreen> with LiveSyncListener<OrderDetailScreen> {
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
    listenLiveSync({'orders'}, () {
      if (!_actionLoading) _load();
    });
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

    final confirmed = await showOvanieConfirmDialog(
      context,
      visual: Container(
        width: 112,
        height: 112,
        decoration: const BoxDecoration(color: payGreenSoft, shape: BoxShape.circle),
        child: Stack(
          alignment: Alignment.center,
          children: [
            Icon(Icons.inventory_2_rounded, color: Color(0xFFC08A4E), size: 58),
            Positioned(
              right: 22,
              bottom: 24,
              child: CircleAvatar(
                radius: 15,
                backgroundColor: payGreenDark,
                child: Icon(Icons.check_rounded, color: Colors.white, size: 20),
              ),
            ),
          ],
        ),
      ),
      title: 'Confirmer la réception ?',
      message:
          'Confirmez uniquement si tous les produits de cette commande ont réellement été reçus. Le vendeur est ensuite payé pour cette commande.',
      confirmLabel: 'Oui, j’ai reçu',
      confirmColor: payGreenDark,
      cancelLabel: 'Annuler',
    );
    if (!confirmed || !mounted) return;

    setState(() => _actionLoading = true);
    try {
      await _repository.confirmReception(order.id);
      await _load();
      if (!mounted) return;
      showOvanieToast(context, 'Réception confirmée dans OVANIE.');
    } catch (error) {
      if (!mounted) return;
      showOvanieToast(context, ApiClient.friendlyError(error), error: true);
    } finally {
      if (mounted) setState(() => _actionLoading = false);
    }
  }

  Future<void> _cancelOrder() async {
    final order = _order;
    if (order == null || !order.actions.canCancel || _actionLoading) return;
    final confirmed = await showOvanieConfirmDialog(
      context,
      visual: Container(
        width: 112,
        height: 112,
        decoration: const BoxDecoration(color: Color(0xFFFFE1E1), shape: BoxShape.circle),
        child: const Icon(Icons.close_rounded, color: Color(0xFFDC2626), size: 76),
      ),
      title: 'Annuler cette commande ?',
      message:
          'Le stock, le panier, la livraison et le paiement seront mis à jour selon les règles d’annulation OVANIE.',
      confirmLabel: 'Annuler la commande',
      confirmColor: const Color(0xFFDC2626),
      cancelLabel: 'Conserver la commande',
      cancelOutlined: true,
    );
    if (!confirmed || !mounted) return;
    setState(() => _actionLoading = true);
    try {
      await _repository.cancelOrder(order.id);
      try {
        await const CartApiRepository().refreshLocalCartFromServer();
      } catch (_) {}
      await _load();
      if (!mounted) return;
      showOvanieToast(context, 'Commande annulée.');
    } catch (error) {
      if (!mounted) return;
      showOvanieToast(context, ApiClient.friendlyError(error), error: true);
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
      final extra = result.skippedLines > 0
          ? ' ${result.skippedLines} ligne(s) indisponible(s) ont été ignorée(s).'
          : '';
      showOvanieToast(context, '${result.message}$extra');
    } catch (error) {
      if (!mounted) return;
      showOvanieToast(context, ApiClient.friendlyError(error), error: true);
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
    final result = await Navigator.of(
      context,
    ).push(MaterialPageRoute(builder: (_) => ReturnFormScreen(initialOrderId: order.id)));
    if (result != null) await _load();
  }

  void _openTracking() {
    final order = _order;
    if (order == null || !order.canTrackDelivery) return;
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => ClientDeliveryTrackingScreen(orderId: order.id, initialOrderNumber: order.orderNumber),
      ),
    );
  }

  bool _isCashOnDelivery(MobileOrder order) {
    final text = '${order.paymentMethod} ${order.paymentMethodLabel}'.toLowerCase();
    return text.contains('cash') || text.contains('livraison');
  }

  @override
  Widget build(BuildContext context) {
    final order = _order;
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.dark.copyWith(statusBarColor: Colors.transparent),
      child: Scaffold(
        backgroundColor: Colors.white,
        bottomNavigationBar: const OvanieBottomNavigation(selectedTab: OvanieMainTab.account),
        body: SafeArea(
          bottom: false,
          child: Column(
            children: [
              SizedBox(
                height: 60,
                child: Row(
                  children: [
                    const SizedBox(width: 4),
                    IconButton(
                      onPressed: () => Navigator.of(context).maybePop(),
                      tooltip: 'Retour',
                      icon: const Icon(Icons.chevron_left_rounded, color: payNavy, size: 36),
                    ),
                    const Expanded(
                      child: Text(
                        'Détail commande',
                        textAlign: TextAlign.center,
                        style: TextStyle(color: payNavy, fontSize: 22, fontWeight: FontWeight.w800),
                      ),
                    ),
                    IconButton(
                      onPressed: _loading ? null : _load,
                      tooltip: 'Actualiser',
                      icon: const Icon(Icons.refresh_rounded, color: payNavy, size: 28),
                    ),
                    const SizedBox(width: 4),
                  ],
                ),
              ),
              Expanded(
                child: _loading && order == null
                    ? const Center(child: CircularProgressIndicator(color: OvanieColors.orange))
                    : order == null
                    ? _DetailError(message: _error ?? 'Commande indisponible.', onRetry: _load)
                    : RefreshIndicator(color: OvanieColors.orange, onRefresh: _load, child: _buildDetail(order)),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildDetail(MobileOrder order) {
    final cod = _isCashOnDelivery(order);
    final cancelled = order.clientBucket == 'cancelled';
    final paymentOpen = !cod && !order.isPaymentConfirmed && !cancelled;
    final events = _detail?.timeline ?? const <OrderTimelineEvent>[];

    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(14, 4, 14, 20),
      children: [
        if (_actionLoading) const Padding(padding: EdgeInsets.only(bottom: 8), child: LinearProgressIndicator()),
        if (_error != null) ...[
          _InlineInfo(text: _error!, icon: Icons.cloud_off_outlined, color: OvanieColors.warning),
          const SizedBox(height: 10),
        ],
        if (cod && !order.isPaymentConfirmed && !cancelled) ...[
          const _Banner(
            icon: Icons.info_outline_rounded,
            title: 'Paiement à la livraison',
            message: 'Aucun paiement en ligne n’est demandé : vous réglez la commande à la réception.',
            color: payBlue,
            background: Color(0xFFEAF3FF),
            border: Color(0xFFB9D3FA),
          ),
          const SizedBox(height: 12),
        ] else if (paymentOpen) ...[
          if (order.isPaymentFailed || order.isPaymentCancelled)
            _Banner(
              icon: Icons.error_outline_rounded,
              title: 'Paiement échoué',
              message: 'Aucun débit n’est considéré comme confirmé. Vous pouvez reprendre le paiement.',
              color: const Color(0xFFDC2626),
              background: const Color(0xFFFFEEEE),
              border: const Color(0xFFF7B7B7),
              actions: [
                if (order.actions.canResumePayment)
                  PaymentButton(
                    label: 'Reprendre le paiement',
                    height: 50,
                    onPressed: _actionLoading ? null : _resumePayment,
                  ),
                if (order.actions.canCancel)
                  _DangerButton(label: 'Annuler la commande', onPressed: _actionLoading ? null : _cancelOrder),
              ],
            )
          else
            _Banner(
              icon: Icons.schedule_rounded,
              title: 'Paiement en attente',
              message: 'OVANIE attend la confirmation du prestataire de paiement.',
              color: const Color(0xFFB45309),
              background: const Color(0xFFFFF7EA),
              border: const Color(0xFFF5D09A),
              actions: [
                if (order.actions.canResumePayment)
                  PaymentButton(
                    label: 'Reprendre le paiement',
                    height: 50,
                    onPressed: _actionLoading ? null : _resumePayment,
                  ),
                if (order.actions.canCancel)
                  _DangerButton(label: 'Annuler la commande', onPressed: _actionLoading ? null : _cancelOrder),
              ],
            ),
          const SizedBox(height: 12),
        ],
        _HeaderCard(order: order, cashOnDelivery: cod),
        const SizedBox(height: 14),
        _OrderSteps(order: order, cashOnDelivery: cod),
        const SizedBox(height: 10),
        _DeliveryStrip(order: order, onTrack: _openTracking),
        const SizedBox(height: 12),
        _Section(
          title: 'Articles commandés',
          padding: const EdgeInsets.fromLTRB(12, 4, 12, 4),
          child: Column(
            children: [
              for (var i = 0; i < order.items.length; i++) ...[
                _ProductRow(item: order.items[i]),
                if (i != order.items.length - 1) const Divider(height: 1, color: payBorder),
              ],
            ],
          ),
        ),
        const SizedBox(height: 12),
        _Section(
          title: 'Paiement',
          child: _PaymentFacts(order: order, cashOnDelivery: cod),
        ),
        const SizedBox(height: 12),
        _Section(
          title: 'Adresse de livraison',
          child: Column(
            children: [
              _LabeledLine(label: 'Destinataire', value: order.deliveryRecipientName),
              _LabeledLine(
                label: 'Téléphone',
                value: order.deliveryPhone.trim().isEmpty ? '' : formatCiPhoneDisplay(order.deliveryPhone),
              ),
              _LabeledLine(label: 'Adresse', value: _fullAddress(order)),
            ],
          ),
        ),
        const SizedBox(height: 12),
        _Section(
          title: 'Récapitulatif',
          child: _Totals(order: order),
        ),
        if (events.isNotEmpty) ...[
          const SizedBox(height: 12),
          _Section(
            title: 'Historique de la commande',
            child: _History(events: events, closed: order.isClosed, cancelled: cancelled),
          ),
        ],
        const SizedBox(height: 12),
        _Actions(
          order: order,
          busy: _actionLoading,
          includeCancel: !paymentOpen,
          onTrack: _openTracking,
          onReception: _confirmReception,
          onInvoice: _openInvoice,
          onReturn: _openReturn,
          onCancel: _cancelOrder,
          onReorder: _reorder,
        ),
      ],
    );
  }

  static String _fullAddress(MobileOrder order) {
    final unique = <String>[];
    for (final value in [order.address, order.quartier, order.commune, order.city]) {
      final clean = value.trim();
      if (clean.isNotEmpty && !unique.any((existing) => existing.toLowerCase() == clean.toLowerCase())) {
        unique.add(clean);
      }
    }
    return unique.isEmpty ? 'Adresse non renseignée' : unique.join(', ');
  }
}

class _Banner extends StatelessWidget {
  final IconData icon;
  final String title;
  final String message;
  final Color color;
  final Color background;
  final Color border;
  final List<Widget> actions;

  const _Banner({
    required this.icon,
    required this.title,
    required this.message,
    required this.color,
    required this.background,
    required this.border,
    this.actions = const [],
  });

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.fromLTRB(14, 14, 14, 14),
    decoration: BoxDecoration(
      color: background,
      borderRadius: BorderRadius.circular(14),
      border: Border.all(color: border),
    ),
    child: Column(
      children: [
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: 52,
              height: 52,
              decoration: BoxDecoration(color: color.withValues(alpha: .12), shape: BoxShape.circle),
              child: Icon(icon, color: color, size: 30),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: TextStyle(color: color, fontSize: 18, fontWeight: FontWeight.w800),
                  ),
                  const SizedBox(height: 3),
                  Text(message, style: const TextStyle(color: payNavy, fontSize: 14, height: 1.35)),
                ],
              ),
            ),
          ],
        ),
        for (final action in actions) ...[const SizedBox(height: 10), action],
      ],
    ),
  );
}

class _DangerButton extends StatelessWidget {
  final String label;
  final VoidCallback? onPressed;

  const _DangerButton({required this.label, required this.onPressed});

  @override
  Widget build(BuildContext context) => SizedBox(
    width: double.infinity,
    height: 46,
    child: OutlinedButton(
      onPressed: onPressed,
      style: OutlinedButton.styleFrom(
        foregroundColor: const Color(0xFFC81E1E),
        backgroundColor: Colors.white,
        side: const BorderSide(color: Color(0xFFC81E1E), width: 1.3),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
      ),
      child: Text(label, style: const TextStyle(fontSize: 15.5, fontWeight: FontWeight.w600)),
    ),
  );
}

class _HeaderCard extends StatelessWidget {
  final MobileOrder order;
  final bool cashOnDelivery;

  const _HeaderCard({required this.order, required this.cashOnDelivery});

  @override
  Widget build(BuildContext context) {
    final Widget payment;
    if (order.isPaymentConfirmed) {
      payment = const Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(Icons.check_rounded, color: payGreenDark, size: 20),
          SizedBox(width: 4),
          Text(
            'Payé',
            style: TextStyle(color: payGreenDark, fontSize: 17, fontWeight: FontWeight.w700),
          ),
        ],
      );
    } else {
      final cancelled = order.clientBucket == 'cancelled';
      payment = Text(
        cancelled
            ? 'Non payé'
            : cashOnDelivery
            ? 'À payer à la livraison'
            : (order.paymentStatusLabel.trim().isEmpty ? 'En attente' : order.paymentStatusLabel),
        textAlign: TextAlign.center,
        style: TextStyle(color: cancelled ? payMuted : OvanieColors.orange, fontSize: 15, fontWeight: FontWeight.w700),
      );
    }
    return Container(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: payBorder),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Commande n° ${order.orderNumber}',
            style: const TextStyle(color: payNavy, fontSize: 20, fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 4),
          Text(
            'Commande du ${paymentDateTime(order.createdAt)}',
            style: const TextStyle(color: payMuted, fontSize: 14),
          ),
          if (order.invoiceNumber.trim().isNotEmpty)
            Text('Facture : ${order.invoiceNumber}', style: const TextStyle(color: payMuted, fontSize: 14)),
          const Divider(height: 24, color: payBorder),
          IntrinsicHeight(
            child: Row(
              children: [
                Expanded(
                  child: _Fact(
                    label: 'Statut de la commande',
                    child: _StatusPill(order: order),
                  ),
                ),
                const VerticalDivider(width: 12, color: payBorder),
                Expanded(
                  child: _Fact(label: 'Paiement', child: payment),
                ),
                const VerticalDivider(width: 12, color: payBorder),
                Expanded(
                  child: _Fact(
                    label: 'Montant',
                    child: FittedBox(
                      fit: BoxFit.scaleDown,
                      child: Text(
                        formatFcfa(order.total),
                        style: const TextStyle(color: payNavy, fontSize: 18, fontWeight: FontWeight.w800),
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
  }
}

class _Fact extends StatelessWidget {
  final String label;
  final Widget child;

  const _Fact({required this.label, required this.child});

  @override
  Widget build(BuildContext context) => Column(
    children: [
      FittedBox(
        fit: BoxFit.scaleDown,
        child: Text(label, maxLines: 1, style: const TextStyle(color: payMuted, fontSize: 13)),
      ),
      const SizedBox(height: 6),
      child,
    ],
  );
}

class _OrderSteps extends StatelessWidget {
  final MobileOrder order;
  final bool cashOnDelivery;

  const _OrderSteps({required this.order, required this.cashOnDelivery});

  @override
  Widget build(BuildContext context) {
    if (order.clientBucket == 'cancelled') return _cancelledSteps();
    final status = '${order.status} ${order.statusLabel} ${order.deliveryStatus} ${order.deliveryStatusLabel}'
        .toLowerCase();
    final delivered = order.deliveredAt != null || order.clientBucket == 'delivered' || status.contains('livrée');
    final shipped =
        delivered ||
        status.contains('expédi') ||
        status.contains('expedi') ||
        status.contains('shipped') ||
        status.contains('transit') ||
        status.contains('en livraison');
    final preparing = shipped || status.contains('prépar') || status.contains('prepar');
    final paid = order.isPaymentConfirmed || cashOnDelivery;
    final done = [true, paid, preparing && paid, shipped, delivered];
    final labels = [
      'Commande reçue',
      cashOnDelivery
          ? 'Paiement à la livraison'
          : (order.isPaymentConfirmed ? 'Paiement confirmé' : 'Paiement en attente'),
      'Préparation',
      'Expédiée',
      'Livrée',
    ];
    // L'étape en cours est la première non terminée.
    var current = done.indexWhere((value) => !value);
    if (current == -1) current = labels.length;
    // « Préparation » terminée seulement quand le colis est parti.
    if (preparing && !shipped && paid) {
      done[2] = false;
      current = 2;
    }

    Widget dot(int i) {
      if (done[i]) {
        return Container(
          width: 34,
          height: 34,
          decoration: const BoxDecoration(color: payGreenDark, shape: BoxShape.circle),
          child: const Icon(Icons.check_rounded, color: Colors.white, size: 21),
        );
      }
      if (i == current) {
        return Container(
          width: 36,
          height: 36,
          decoration: BoxDecoration(
            color: OvanieColors.orange,
            shape: BoxShape.circle,
            border: Border.all(color: const Color(0xFFFFD2B8), width: 4),
          ),
        );
      }
      return Container(
        width: 34,
        height: 34,
        decoration: BoxDecoration(
          color: Colors.white,
          shape: BoxShape.circle,
          border: Border.all(color: const Color(0xFFC9D0DC), width: 1.6),
        ),
      );
    }

    Color lineColor(int i) =>
        done[i + 1] ? payGreenDark : (i + 1 == current ? OvanieColors.orange : const Color(0xFFDDE2EA));

    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 22),
          child: Row(
            children: [
              for (var i = 0; i < labels.length; i++) ...[
                dot(i),
                if (i != labels.length - 1) Expanded(child: Container(height: 2.5, color: lineColor(i))),
              ],
            ],
          ),
        ),
        const SizedBox(height: 6),
        Row(
          children: [
            for (var i = 0; i < labels.length; i++)
              Expanded(
                child: Text(
                  labels[i],
                  textAlign: TextAlign.center,
                  maxLines: 2,
                  style: TextStyle(
                    color: i == current ? OvanieColors.orange : payMuted,
                    fontSize: 11.5,
                    height: 1.2,
                    fontWeight: i == current ? FontWeight.w700 : FontWeight.w400,
                  ),
                ),
              ),
          ],
        ),
      ],
    );
  }

  /// Commande annulée : étapes grisées, la dernière marquée « Annulée ».
  Widget _cancelledSteps() {
    const grey = Color(0xFF8A93A8);
    const labels = ['Commande reçue', 'Paiement', 'Préparation', 'Expédiée', 'Annulée'];
    Widget dot(bool last) => Container(
      width: last ? 36 : 32,
      height: last ? 36 : 32,
      decoration: BoxDecoration(
        color: last ? Colors.white : grey,
        shape: BoxShape.circle,
        border: last ? Border.all(color: grey, width: 3) : null,
      ),
      child: Icon(last ? Icons.close_rounded : Icons.check_rounded, color: last ? grey : Colors.white, size: 20),
    );
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 22),
          child: Row(
            children: [
              for (var i = 0; i < labels.length; i++) ...[
                dot(i == labels.length - 1),
                if (i != labels.length - 1) Expanded(child: Container(height: 2.5, color: const Color(0xFFC9D0DC))),
              ],
            ],
          ),
        ),
        const SizedBox(height: 6),
        Row(
          children: [
            for (var i = 0; i < labels.length; i++)
              Expanded(
                child: Text(
                  labels[i],
                  textAlign: TextAlign.center,
                  maxLines: 2,
                  style: TextStyle(
                    color: i == labels.length - 1 ? payNavy : payMuted,
                    fontSize: 11.5,
                    height: 1.2,
                    fontWeight: i == labels.length - 1 ? FontWeight.w700 : FontWeight.w400,
                  ),
                ),
              ),
          ],
        ),
      ],
    );
  }
}

class _DeliveryStrip extends StatelessWidget {
  final MobileOrder order;
  final VoidCallback onTrack;

  const _DeliveryStrip({required this.order, required this.onTrack});

  @override
  Widget build(BuildContext context) {
    final cancelled = order.clientBucket == 'cancelled';
    final estimate = order.estimatedMaxDate ?? order.estimatedMinDate;
    final String strong;
    final String rest;
    if (cancelled) {
      strong = 'Commande annulée';
      rest = order.updatedAt == null ? '' : ' le ${paymentDateTime(order.updatedAt)}';
    } else if (order.deliveredAt != null) {
      strong = 'Livraison finalisée';
      rest = ' le ${paymentDateTime(order.deliveredAt)}';
    } else {
      strong = 'Livraison';
      rest = estimate == null ? ' à confirmer' : ' estimée ${paymentShortDate(estimate)}';
    }
    return Container(
      padding: const EdgeInsets.fromLTRB(14, 10, 10, 10),
      constraints: const BoxConstraints(minHeight: 58),
      decoration: BoxDecoration(color: paySoft, borderRadius: BorderRadius.circular(12)),
      child: Row(
        children: [
          const Icon(Icons.local_shipping_outlined, color: payNavy, size: 30),
          const SizedBox(width: 12),
          Expanded(
            child: Text.rich(
              TextSpan(
                text: strong,
                style: TextStyle(
                  color: cancelled ? payMuted : payNavy,
                  fontSize: 14.5,
                  fontWeight: cancelled ? FontWeight.w400 : FontWeight.w700,
                ),
                children: [
                  TextSpan(
                    text: rest,
                    style: const TextStyle(color: payMuted, fontWeight: FontWeight.w400),
                  ),
                ],
              ),
            ),
          ),
          if (order.canTrackDelivery)
            OutlinedButton(
              onPressed: onTrack,
              style: OutlinedButton.styleFrom(
                foregroundColor: payNavy,
                backgroundColor: Colors.white,
                side: const BorderSide(color: payNavy, width: 1.3),
                padding: const EdgeInsets.symmetric(horizontal: 20),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
              ),
              child: const Text('Suivre', style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700)),
            ),
        ],
      ),
    );
  }
}

/// Bloc à bandeau gris titré, comme sur la maquette du détail.
class _Section extends StatelessWidget {
  final String title;
  final Widget child;
  final EdgeInsetsGeometry padding;

  const _Section({required this.title, required this.child, this.padding = const EdgeInsets.fromLTRB(14, 10, 14, 10)});

  @override
  Widget build(BuildContext context) => Container(
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(12),
      border: Border.all(color: payBorder),
    ),
    clipBehavior: Clip.antiAlias,
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Container(
          color: paySoft,
          padding: const EdgeInsets.fromLTRB(14, 10, 14, 10),
          child: Text(
            title,
            style: const TextStyle(color: payNavy, fontSize: 17, fontWeight: FontWeight.w800),
          ),
        ),
        Padding(padding: padding, child: child),
      ],
    ),
  );
}

class _ProductRow extends StatelessWidget {
  final MobileOrderItem item;

  const _ProductRow({required this.item});

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 8),
    child: Row(
      children: [
        OrderItemThumbnail(url: item.imageUrl, width: 72, height: 62),
        const SizedBox(width: 14),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                item.productName,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(color: payNavy, fontSize: 14.5, fontWeight: FontWeight.w700),
              ),
              const SizedBox(height: 4),
              Text(
                'Qté : ${item.quantity} × ${formatFcfa(item.unitPrice)}',
                style: const TextStyle(color: payMuted, fontSize: 13.5),
              ),
            ],
          ),
        ),
        const SizedBox(width: 8),
        Text(
          formatFcfa(item.subtotal),
          style: const TextStyle(color: payNavy, fontSize: 15, fontWeight: FontWeight.w800),
        ),
      ],
    ),
  );
}

class _PaymentFacts extends StatelessWidget {
  final MobileOrder order;
  final bool cashOnDelivery;

  const _PaymentFacts({required this.order, required this.cashOnDelivery});

  @override
  Widget build(BuildContext context) {
    final code = operatorCodeOf('${order.paymentProvider} ${order.paymentMethodLabel}');
    final method = cashOnDelivery
        ? 'À la livraison'
        : (code.isNotEmpty ? operatorLabelOf(code) : order.paymentMethodLabel);
    final cancelled = order.clientBucket == 'cancelled';
    final status = order.isPaymentConfirmed
        ? 'Payé'
        : cancelled
        ? 'Non payé'
        : cashOnDelivery
        ? 'À payer à la livraison'
        : (order.paymentStatusLabel.trim().isEmpty ? 'En attente' : order.paymentStatusLabel);
    final remaining = order.isPaymentConfirmed || cancelled
        ? 0.0
        : (order.outstandingAmount > 0 ? order.outstandingAmount : order.total);
    Widget pair(String label, String value, {Color? color, bool strong = false}) => Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        children: [
          SizedBox(
            width: 74,
            child: Text(label, style: const TextStyle(color: payMuted, fontSize: 13.5)),
          ),
          Expanded(
            child: Text(
              value.trim().isEmpty ? '—' : value,
              style: TextStyle(
                color: color ?? payNavy,
                fontSize: 13.5,
                fontWeight: strong ? FontWeight.w800 : FontWeight.w500,
              ),
            ),
          ),
        ],
      ),
    );
    if (order.isPaymentConfirmed && !cashOnDelivery) {
      final paidMethod = code.isEmpty || code == 'card' ? method : 'Mobile Money (${operatorLabelOf(code)})';
      Widget line(String label, String value) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 4),
        child: Row(
          children: [
            Text(label, style: const TextStyle(color: payMuted, fontSize: 14)),
            const SizedBox(width: 12),
            Expanded(
              child: Text(
                value.trim().isEmpty ? '—' : value,
                textAlign: TextAlign.right,
                style: const TextStyle(color: payNavy, fontSize: 14),
              ),
            ),
          ],
        ),
      );
      return Column(children: [line('Méthode', paidMethod), line('Référence', order.paymentReference)]);
    }
    return IntrinsicHeight(
      child: Row(
        children: [
          Expanded(
            child: Column(
              children: [
                pair('Méthode', method),
                pair('Référence', order.paymentReference.trim().isEmpty ? '—' : order.paymentReference),
              ],
            ),
          ),
          const VerticalDivider(width: 20, color: payBorder),
          Expanded(
            child: Column(
              children: [
                pair(
                  'Statut',
                  status,
                  color: order.isPaymentConfirmed ? payGreenDark : (cancelled ? payMuted : OvanieColors.orange),
                ),
                pair('Reste', formatFcfa(remaining), strong: true),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _LabeledLine extends StatelessWidget {
  final String label;
  final String value;

  const _LabeledLine({required this.label, required this.value});

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 4),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SizedBox(
          width: 110,
          child: Text(label, style: const TextStyle(color: payMuted, fontSize: 14)),
        ),
        Expanded(
          child: Text(value.trim().isEmpty ? '—' : value.trim(), style: const TextStyle(color: payNavy, fontSize: 14)),
        ),
      ],
    ),
  );
}

class _Totals extends StatelessWidget {
  final MobileOrder order;

  const _Totals({required this.order});

  @override
  Widget build(BuildContext context) {
    Widget line(String label, String value, {Color color = payNavy}) => Padding(
      padding: const EdgeInsets.symmetric(vertical: 3),
      child: Row(
        children: [
          Expanded(
            child: Text(label, style: const TextStyle(color: payMuted, fontSize: 14)),
          ),
          Text(value, style: TextStyle(color: color, fontSize: 14)),
        ],
      ),
    );
    return Column(
      children: [
        line('Produits', formatFcfa(order.subtotal)),
        line('Livraison', formatFcfa(order.deliveryFee)),
        if (order.discount > 0) line('Remise', '- ${formatFcfa(order.discount)}', color: payGreenDark),
        if (order.loyaltyDiscount > 0)
          line('Avantage fidélité', '- ${formatFcfa(order.loyaltyDiscount)}', color: payGreenDark),
        const Divider(height: 18, color: payBorder),
        Row(
          children: [
            const Expanded(
              child: Text(
                'Total',
                style: TextStyle(color: payNavy, fontSize: 16.5, fontWeight: FontWeight.w800),
              ),
            ),
            Text(
              formatFcfa(order.total),
              style: const TextStyle(color: payNavy, fontSize: 17, fontWeight: FontWeight.w800),
            ),
          ],
        ),
      ],
    );
  }
}

class _History extends StatelessWidget {
  final List<OrderTimelineEvent> events;
  final bool closed;
  final bool cancelled;

  const _History({required this.events, this.closed = false, this.cancelled = false});

  @override
  Widget build(BuildContext context) {
    final sorted = [...events]
      ..sort((a, b) {
        final left = a.date ?? DateTime.fromMillisecondsSinceEpoch(0);
        final right = b.date ?? DateTime.fromMillisecondsSinceEpoch(0);
        return right.compareTo(left);
      });
    Color dotColor(int index) {
      if (index != 0) return payGreenDark;
      if (cancelled) return const Color(0xFF6B7591);
      return closed ? payGreenDark : OvanieColors.orange;
    }

    return Column(
      children: [
        for (var i = 0; i < sorted.length; i++)
          Padding(
            padding: const EdgeInsets.symmetric(vertical: 4),
            child: Row(
              children: [
                SizedBox(
                  width: 138,
                  child: Text(
                    paymentDateTime(sorted[i].date, separator: ' · '),
                    style: const TextStyle(color: payMuted, fontSize: 12.5),
                  ),
                ),
                Container(
                  width: 11,
                  height: 11,
                  decoration: BoxDecoration(color: dotColor(i), shape: BoxShape.circle),
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: Text(sorted[i].label, style: const TextStyle(color: payNavy, fontSize: 13.5)),
                ),
              ],
            ),
          ),
      ],
    );
  }
}

class _Actions extends StatelessWidget {
  final MobileOrder order;
  final bool busy;
  final bool includeCancel;
  final VoidCallback onTrack;
  final VoidCallback onReception;
  final VoidCallback onInvoice;
  final VoidCallback onReturn;
  final VoidCallback onCancel;
  final VoidCallback onReorder;

  const _Actions({
    required this.order,
    required this.busy,
    required this.includeCancel,
    required this.onTrack,
    required this.onReception,
    required this.onInvoice,
    required this.onReturn,
    required this.onCancel,
    required this.onReorder,
  });

  @override
  Widget build(BuildContext context) {
    final secondary = <(String, VoidCallback)>[
      if (order.actions.canConfirmReception) ('Confirmer la réception', onReception),
      if (order.actions.canDownloadInvoice) ('Consulter la facture', onInvoice),
      if (order.actions.canOpenReturn) ('Retour / réclamation', onReturn),
      if (order.actions.canReorder) ('Commander à nouveau', onReorder),
      if (includeCancel && order.actions.canCancel) ('Annuler la commande', onCancel),
    ];
    if (!order.canTrackDelivery && secondary.isEmpty) {
      return const _Section(
        title: 'Actions',
        child: Padding(
          padding: EdgeInsets.symmetric(vertical: 6),
          child: Text(
            'Aucune action n’est disponible pour cette commande actuellement.',
            textAlign: TextAlign.center,
            style: TextStyle(color: payMuted, fontSize: 14),
          ),
        ),
      );
    }
    final reorderFirst = !order.canTrackDelivery && order.isClosed && order.actions.canReorder;
    if (reorderFirst) secondary.removeWhere((action) => action.$1 == 'Commander à nouveau');
    return _Section(
      title: 'Actions',
      child: Column(
        children: [
          if (order.canTrackDelivery) ...[
            PaymentButton(label: 'Suivre la livraison', height: 44, onPressed: busy ? null : onTrack),
            if (secondary.isNotEmpty) const SizedBox(height: 10),
          ],
          if (reorderFirst) ...[
            PaymentButton(label: 'Acheter à nouveau', height: 44, onPressed: busy ? null : onReorder),
            if (secondary.isNotEmpty) const SizedBox(height: 10),
          ],
          if (secondary.isNotEmpty)
            LayoutBuilder(
              builder: (context, constraints) {
                final perRow = secondary.length >= 3 ? 3 : secondary.length;
                final width = (constraints.maxWidth - 8 * (perRow - 1)) / perRow;
                return Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    for (final action in secondary)
                      SizedBox(
                        width: width,
                        height: 42,
                        child: OutlinedButton(
                          onPressed: busy ? null : action.$2,
                          style: OutlinedButton.styleFrom(
                            foregroundColor: payNavy,
                            side: const BorderSide(color: payNavy, width: 1.2),
                            padding: const EdgeInsets.symmetric(horizontal: 6),
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                          ),
                          child: FittedBox(
                            fit: BoxFit.scaleDown,
                            child: Text(
                              action.$1,
                              maxLines: 1,
                              style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
                            ),
                          ),
                        ),
                      ),
                  ],
                );
              },
            ),
        ],
      ),
    );
  }
}

class _InlineInfo extends StatelessWidget {
  final String text;
  final IconData icon;
  final Color color;

  const _InlineInfo({required this.text, required this.icon, required this.color});

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(12),
    decoration: BoxDecoration(
      color: color.withValues(alpha: .07),
      borderRadius: BorderRadius.circular(12),
      border: Border.all(color: color.withValues(alpha: .18)),
    ),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, color: color, size: 18),
        const SizedBox(width: 8),
        Expanded(
          child: Text(
            text,
            style: TextStyle(color: color, fontSize: 13, height: 1.35, fontWeight: FontWeight.w600),
          ),
        ),
      ],
    ),
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
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 100,
            height: 100,
            decoration: const BoxDecoration(color: paySoft, shape: BoxShape.circle),
            child: const Icon(Icons.cloud_off_rounded, color: Color(0xFF4A556E), size: 46),
          ),
          const SizedBox(height: 18),
          Text(
            message,
            textAlign: TextAlign.center,
            style: const TextStyle(color: payNavy, fontSize: 16),
          ),
          const SizedBox(height: 18),
          PaymentButton(label: 'Réessayer', onPressed: onRetry),
        ],
      ),
    ),
  );
}

class _StatusPill extends StatelessWidget {
  final MobileOrder order;

  const _StatusPill({required this.order});

  @override
  Widget build(BuildContext context) {
    final (Color color, Color background) = switch (order.clientBucket) {
      'delivered' => (payGreenDark, payGreenSoft),
      'cancelled' || 'returned' => (const Color(0xFF4A556E), const Color(0xFFE4E8EF)),
      _ => (payBlue, const Color(0xFFE6F0FF)),
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 5),
      decoration: BoxDecoration(color: background, borderRadius: BorderRadius.circular(8)),
      child: Text(
        order.statusLabel,
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
        style: TextStyle(color: color, fontSize: 14, fontWeight: FontWeight.w600),
      ),
    );
  }
}
