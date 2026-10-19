import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../app/theme.dart';
import '../../../core/network/api_client.dart';
import '../../../core/utils/formatters.dart';
import '../../orders/data/orders_repository.dart';
import '../../orders/domain/order_model.dart';
import '../../orders/presentation/invoice_screen.dart';
import '../../orders/presentation/order_detail_screen.dart';
import '../../payments/presentation/payment_ui.dart';
import '../../payments/presentation/resume_payment_screen.dart';
import '../../support/presentation/support_center_screen.dart';

/// Résultat d'une commande : paiement réussi, commande confirmée (paiement à
/// la livraison), paiement en cours ou paiement échoué.
class OrderConfirmationScreen extends StatefulWidget {
  final int orderId;
  final String initialOrderNumber;
  final double initialAmount;
  final String initialPaymentMethod;
  final String returnState;

  const OrderConfirmationScreen({
    super.key,
    required this.orderId,
    this.initialOrderNumber = '',
    this.initialAmount = 0,
    this.initialPaymentMethod = '',
    this.returnState = '',
  });

  @override
  State<OrderConfirmationScreen> createState() => _OrderConfirmationScreenState();
}

enum _ResultKind { paid, cashOnDelivery, pending, failed }

class _OrderConfirmationScreenState extends State<OrderConfirmationScreen> {
  final _repository = const OrdersRepository();
  MobileOrder? _order;
  bool _loading = true;
  bool _refreshing = false;
  String? _error;
  Timer? _poller;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _poller?.cancel();
    super.dispose();
  }

  Future<void> _load({bool silent = false}) async {
    if (mounted) {
      setState(() {
        if (!silent) _loading = _order == null;
        _refreshing = true;
        _error = null;
      });
    }
    try {
      final order = await _repository.fetchOrder(widget.orderId);
      if (!mounted) return;
      setState(() {
        _order = order;
        _loading = false;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _loading = false;
        _error = ApiClient.friendlyError(error);
      });
    } finally {
      if (mounted) setState(() => _refreshing = false);
      _syncPolling();
    }
  }

  /// Tant que le paiement est en attente, le statut est relu automatiquement.
  void _syncPolling() {
    if (_kind == _ResultKind.pending) {
      _poller ??= Timer.periodic(const Duration(seconds: 6), (_) {
        if (!_refreshing) unawaited(_load(silent: true));
      });
    } else {
      _poller?.cancel();
      _poller = null;
    }
  }

  bool get _cashOnDelivery {
    final order = _order;
    final text = '${order?.paymentMethod ?? ''} ${order?.paymentMethodLabel ?? ''} ${widget.initialPaymentMethod}'
        .toLowerCase();
    return text.contains('cash') || text.contains('livraison');
  }

  _ResultKind get _kind {
    final order = _order;
    final state = widget.returnState.toLowerCase().trim();
    if (order?.isPaymentConfirmed == true) return _ResultKind.paid;
    if (order?.isPaymentFailed == true || order?.isPaymentCancelled == true) return _ResultKind.failed;
    if (order == null && (state == 'failed' || state == 'cancelled')) return _ResultKind.failed;
    if (_cashOnDelivery) return _ResultKind.cashOnDelivery;
    if (order == null && (state == 'completed' || state == 'success')) return _ResultKind.paid;
    return _ResultKind.pending;
  }

  String get _method {
    final order = _order;
    if (order != null) {
      final code = operatorCodeOf('${order.paymentProvider} ${order.paymentMethodLabel}');
      if (code.isNotEmpty) return operatorLabelOf(code);
      if (order.paymentMethodLabel.trim().isNotEmpty) return order.paymentMethodLabel.trim();
    }
    final fallback = widget.initialPaymentMethod.trim();
    return fallback.isEmpty ? 'Paiement en ligne' : fallback;
  }

  double get _amount => _order?.total ?? widget.initialAmount;

  void _openDetail() {
    final order = _order;
    if (order == null) return;
    Navigator.of(context).pushReplacement(
      MaterialPageRoute<void>(
        builder: (_) => OrderDetailScreen(orderId: order.id, initialOrder: order),
      ),
    );
  }

  void _openReceipt() {
    final order = _order;
    if (order == null) return;
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => InvoiceScreen(orderId: order.id, initialOrder: order),
      ),
    );
  }

  void _retryPayment({required bool sameOperator}) {
    final order = _order;
    if (order == null) return;
    Navigator.of(context).pushReplacement(
      MaterialPageRoute<void>(
        builder: (_) => ResumePaymentScreen(
          order: order,
          initialOperator: sameOperator ? operatorCodeOf('${order.paymentProvider} ${order.paymentMethodLabel}') : '',
        ),
      ),
    );
  }

  void _contactSupport() {
    final order = _order;
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => SupportCenterScreen(
          initialCategory: 'payments',
          contextType: 'order',
          contextId: widget.orderId,
          initialSubject:
              'Assistance commande ${order?.orderNumber.isNotEmpty == true ? order!.orderNumber : widget.initialOrderNumber}',
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.dark.copyWith(statusBarColor: Colors.transparent),
      child: Scaffold(
        backgroundColor: Colors.white,
        body: SafeArea(
          child: _loading
              ? const Center(child: CircularProgressIndicator(color: OvanieColors.orange))
              : _error != null && _order == null
              ? _ErrorState(message: _error!, onRetry: _load)
              : RefreshIndicator(color: OvanieColors.orange, onRefresh: _load, child: _buildResult()),
        ),
      ),
    );
  }

  Widget _buildResult() {
    final kind = _kind;
    final order = _order;
    final online = kind != _ResultKind.cashOnDelivery;

    final (String title, String description) = switch (kind) {
      _ResultKind.paid => (
        'Paiement réussi !',
        'Votre paiement a été validé avec succès. Votre commande va maintenant passer à l’étape de validation.',
      ),
      _ResultKind.cashOnDelivery => (
        'Commande confirmée !',
        'Votre commande a été enregistrée avec succès. Le règlement sera effectué lors de la livraison.',
      ),
      _ResultKind.pending => (
        'Paiement en cours',
        'Nous vérifions votre paiement auprès de notre prestataire sécurisé.',
      ),
      _ResultKind.failed => (
        'Paiement échoué',
        'Votre paiement n’a pas pu être finalisé. Vérifiez vos informations ou essayez un autre mode de paiement.',
      ),
    };

    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
      children: [
        if (online) const Center(child: PaymentSecureChip(label: 'Paiement 100% sécurisé')),
        const SizedBox(height: 14),
        PaymentFlowSteps(
          paymentState: switch (kind) {
            _ResultKind.failed => 'error',
            _ResultKind.pending => 'active',
            _ => 'done',
          },
          confirmed: kind == _ResultKind.paid || kind == _ResultKind.cashOnDelivery,
        ),
        const SizedBox(height: 20),
        Center(child: _ResultIcon(kind: kind)),
        const SizedBox(height: 16),
        Text(
          title,
          textAlign: TextAlign.center,
          style: const TextStyle(color: payNavy, fontSize: 30, fontWeight: FontWeight.w900),
        ),
        const SizedBox(height: 8),
        Text(
          description,
          textAlign: TextAlign.center,
          style: const TextStyle(color: payMuted, fontSize: 15, height: 1.45),
        ),
        if (kind == _ResultKind.pending) ...[
          const SizedBox(height: 12),
          const Center(
            child: PaymentStatusBadge(
              label: 'En attente',
              icon: Icons.schedule_rounded,
              color: Color(0xFFC2410C),
              background: Color(0xFFFFEDE2),
            ),
          ),
          const SizedBox(height: 10),
          const Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(Icons.info_rounded, color: Color(0xFF7C8598), size: 22),
              SizedBox(width: 8),
              Flexible(
                child: Text(
                  'Le statut sera mis à jour automatiquement dès confirmation du prestataire.',
                  style: TextStyle(color: payMuted, fontSize: 13, height: 1.35),
                ),
              ),
            ],
          ),
        ],
        if (kind == _ResultKind.failed) ...[
          const SizedBox(height: 16),
          const PaymentInfoNote(
            text:
                'Si votre compte a malgré tout été débité, contactez le support avec la référence ci-dessous : le montant sera vérifié.',
          ),
        ],
        const SizedBox(height: 18),
        _paymentCard(kind),
        const SizedBox(height: 14),
        if (kind == _ResultKind.failed) ...[
          const PaymentCard(
            title: 'Cause possible',
            child: Column(
              children: [
                _CauseLine('Solde insuffisant sur votre compte.'),
                _CauseLine('Numéro de compte ou informations non valides.'),
                _CauseLine('Délai de confirmation dépassé.'),
                _CauseLine('Problème réseau temporaire.'),
              ],
            ),
          ),
          const SizedBox(height: 22),
          const Text(
            'Que souhaitez-vous faire ?',
            style: TextStyle(color: payNavy, fontSize: 21, fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 14),
          PaymentButton(
            label: 'Réessayer',
            onPressed: order?.actions.canResumePayment == true ? () => _retryPayment(sameOperator: true) : null,
          ),
          const SizedBox(height: 12),
          PaymentButton(
            label: 'Changer de paiement',
            outlined: true,
            onPressed: order?.actions.canResumePayment == true ? () => _retryPayment(sameOperator: false) : null,
          ),
        ] else ...[
          if (order != null) ...[
            _associatedOrder(order),
            const SizedBox(height: 14),
            _deliveryCard(order),
            const SizedBox(height: 14),
          ],
          PaymentInfoNote(
            text: kind == _ResultKind.pending
                ? 'Vous pourrez consulter le détail complet de votre commande après confirmation du paiement.'
                : 'Vous pourrez consulter le détail complet de votre commande après validation.',
          ),
          const SizedBox(height: 16),
          if (kind == _ResultKind.pending)
            PaymentButton(label: 'Vérifier le statut', loading: _refreshing, onPressed: _load)
          else
            PaymentButton(label: 'Continuer', onPressed: order == null ? null : _openDetail),
          if (kind == _ResultKind.paid && order?.actions.canDownloadInvoice == true) ...[
            const SizedBox(height: 12),
            PaymentButton(label: 'Télécharger le reçu', outlined: true, onPressed: _openReceipt),
          ],
          const SizedBox(height: 10),
          Center(
            child: TextButton(
              onPressed: () => Navigator.of(context).popUntil((route) => route.isFirst),
              child: const Text(
                'Retour à l’accueil',
                style: TextStyle(
                  color: payNavy,
                  fontSize: 15.5,
                  decoration: TextDecoration.underline,
                  decorationColor: payNavy,
                ),
              ),
            ),
          ),
        ],
        const SizedBox(height: 14),
        PaymentHelpCard(onContact: _contactSupport),
      ],
    );
  }

  Widget _paymentCard(_ResultKind kind) {
    final order = _order;
    if (kind == _ResultKind.cashOnDelivery) {
      return PaymentCard(
        title: 'Paiement à la livraison',
        child: Column(
          children: [
            PaymentInfoRow(label: 'Total à régler à la réception', value: formatFcfa(_amount), strong: true),
            const PaymentInfoRow(
              label: 'Statut',
              last: true,
              valueWidget: PaymentStatusBadge(
                label: 'À la livraison',
                icon: Icons.local_shipping_rounded,
                color: Color(0xFF0B5ED7),
                background: Color(0xFFE6F0FF),
              ),
            ),
            const SizedBox(height: 6),
            const PaymentInfoNote(
              text: 'Aucun paiement en ligne n’a été effectué. Préparez le règlement à la réception de la commande.',
            ),
            const SizedBox(height: 4),
          ],
        ),
      );
    }
    final reference = order?.paymentReference.trim().isNotEmpty == true ? order!.paymentReference.trim() : '—';
    final date = order?.paymentAttemptCreatedAt ?? order?.updatedAt ?? order?.createdAt;
    final badge = switch (kind) {
      _ResultKind.paid => const PaymentStatusBadge(
        label: 'Payé',
        icon: Icons.check_circle_rounded,
        color: payGreenDark,
        background: payGreenSoft,
      ),
      _ResultKind.failed => const PaymentStatusBadge(
        label: 'Échoué',
        icon: Icons.cancel_rounded,
        color: Color(0xFFC81E1E),
        background: Color(0xFFFFE8E8),
      ),
      _ => const PaymentStatusBadge(
        label: 'En attente',
        icon: Icons.schedule_rounded,
        color: Color(0xFFC2410C),
        background: Color(0xFFFFEDE2),
      ),
    };
    return PaymentCard(
      title: kind == _ResultKind.failed ? 'Détails de la tentative' : 'Détails du paiement',
      child: Column(
        children: [
          PaymentInfoRow(
            label: kind == _ResultKind.paid ? 'Montant payé' : 'Montant à payer',
            value: formatFcfa(_amount),
            strong: true,
          ),
          PaymentInfoRow(label: 'Méthode', value: _method),
          PaymentInfoRow(label: 'Référence', value: reference),
          PaymentInfoRow(label: 'Date', value: paymentDateTime(date)),
          PaymentInfoRow(label: 'Statut', last: true, valueWidget: badge),
        ],
      ),
    );
  }

  Widget _associatedOrder(MobileOrder order) {
    final count = order.items.length;
    return PaymentCard(
      title: 'Commande associée',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const SizedBox(height: 6),
          OrderItemThumbnails(imageUrls: order.items.map((item) => item.imageUrl).toList(growable: false)),
          const SizedBox(height: 12),
          Text(
            'Commande n° ${order.orderNumber}',
            style: const TextStyle(color: payNavy, fontSize: 16, fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 2),
          Text('$count article${count > 1 ? 's' : ''}', style: const TextStyle(color: payMuted, fontSize: 14.5)),
          const SizedBox(height: 2),
          Text.rich(
            TextSpan(
              text: 'Montant commande : ',
              style: const TextStyle(color: payMuted, fontSize: 14.5),
              children: [
                TextSpan(
                  text: formatFcfa(order.total),
                  style: const TextStyle(color: payNavy),
                ),
              ],
            ),
          ),
          const SizedBox(height: 4),
        ],
      ),
    );
  }

  Widget _deliveryCard(MobileOrder order) {
    final address = <String>[];
    for (final value in [order.address, order.quartier, order.commune, order.city]) {
      final clean = value.trim();
      if (clean.isNotEmpty && !address.any((item) => item.toLowerCase() == clean.toLowerCase())) address.add(clean);
    }
    return PaymentCard(
      title: 'Informations de livraison',
      child: Column(
        children: [
          PaymentInfoRow(label: 'Adresse', value: address.isEmpty ? '—' : address.join(', ')),
          PaymentInfoRow(
            label: 'Destinataire',
            value: order.deliveryRecipientName.trim().isEmpty ? '—' : order.deliveryRecipientName.trim(),
          ),
          PaymentInfoRow(
            label: 'Téléphone',
            value: order.deliveryPhone.trim().isEmpty ? '—' : formatCiPhoneDisplay(order.deliveryPhone),
            last: true,
          ),
        ],
      ),
    );
  }
}

class _ResultIcon extends StatelessWidget {
  final _ResultKind kind;

  const _ResultIcon({required this.kind});

  @override
  Widget build(BuildContext context) {
    switch (kind) {
      case _ResultKind.pending:
        return Container(
          width: 96,
          height: 96,
          padding: const EdgeInsets.all(26),
          decoration: const BoxDecoration(color: Color(0xFFFFEDE2), shape: BoxShape.circle),
          child: const CircularProgressIndicator(strokeWidth: 5, color: OvanieColors.orange),
        );
      case _ResultKind.failed:
        return Container(
          width: 120,
          height: 120,
          decoration: const BoxDecoration(color: Color(0xFFFFE1E1), shape: BoxShape.circle),
          child: const Icon(Icons.close_rounded, color: Color(0xFFC81E1E), size: 76),
        );
      case _ResultKind.paid:
      case _ResultKind.cashOnDelivery:
        return Container(
          width: 100,
          height: 100,
          decoration: const BoxDecoration(color: Color(0xFF1E8E3E), shape: BoxShape.circle),
          child: const Icon(Icons.check_rounded, color: Colors.white, size: 62),
        );
    }
  }
}

class _CauseLine extends StatelessWidget {
  final String text;

  const _CauseLine(this.text);

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 7),
    child: Row(
      children: [
        Container(
          width: 9,
          height: 9,
          decoration: const BoxDecoration(color: Color(0xFFDC2626), shape: BoxShape.circle),
        ),
        const SizedBox(width: 16),
        Expanded(
          child: Text(text, style: const TextStyle(color: payNavy, fontSize: 15)),
        ),
      ],
    ),
  );
}

class _ErrorState extends StatelessWidget {
  final String message;
  final Future<void> Function({bool silent}) onRetry;

  const _ErrorState({required this.message, required this.onRetry});

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 100,
            height: 100,
            decoration: const BoxDecoration(color: paySoft, shape: BoxShape.circle),
            child: const Icon(Icons.cloud_off_rounded, color: Color(0xFF4A556E), size: 46),
          ),
          const SizedBox(height: 20),
          Text(
            message,
            textAlign: TextAlign.center,
            style: const TextStyle(color: payNavy, fontSize: 16),
          ),
          const SizedBox(height: 20),
          PaymentButton(label: 'Réessayer', onPressed: () => onRetry()),
        ],
      ),
    ),
  );
}
