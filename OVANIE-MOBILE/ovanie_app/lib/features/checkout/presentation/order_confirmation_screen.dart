import 'package:flutter/material.dart';

import '../../../app/theme.dart';
import '../../../core/network/api_client.dart';
import '../../../core/utils/formatters.dart';
import '../../orders/data/orders_repository.dart';
import '../../orders/domain/order_model.dart';
import '../../orders/presentation/invoice_screen.dart';
import '../../orders/presentation/order_detail_screen.dart';
import '../../payments/presentation/resume_payment_screen.dart';
import '../../support/presentation/support_center_screen.dart';

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

class _OrderConfirmationScreenState extends State<OrderConfirmationScreen> {
  final _repository = const OrdersRepository();
  MobileOrder? _order;
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    if (mounted) {
      setState(() {
        _loading = true;
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
    }
  }

  bool get _failed {
    final order = _order;
    final state = widget.returnState.toLowerCase().trim();
    return state == 'failed' ||
        state == 'cancelled' ||
        order?.isPaymentFailed == true ||
        order?.isPaymentCancelled == true;
  }

  bool get _success {
    final order = _order;
    final state = widget.returnState.toLowerCase().trim();
    return state == 'completed' ||
        state == 'success' ||
        state == 'confirmed' ||
        order?.isPaymentConfirmed == true;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      body: SafeArea(
        child: _loading
            ? const Center(child: CircularProgressIndicator())
            : _error != null && _order == null
                ? _ErrorState(message: _error!, onRetry: _load)
                : RefreshIndicator(
                    onRefresh: _load,
                    child: _failed
                        ? _PaymentFailedView(
                            order: _order,
                            orderId: widget.orderId,
                            fallbackAmount: widget.initialAmount,
                            fallbackMethod: widget.initialPaymentMethod,
                            fallbackOrderNumber: widget.initialOrderNumber,
                          )
                        : _PaymentSuccessView(
                            order: _order,
                            orderId: widget.orderId,
                            fallbackAmount: widget.initialAmount,
                            fallbackMethod: widget.initialPaymentMethod,
                            fallbackOrderNumber: widget.initialOrderNumber,
                            pending: !_success,
                          ),
                  ),
      ),
    );
  }
}

class _CheckoutHeader extends StatelessWidget {
  final bool failed;
  final bool success;

  const _CheckoutHeader({required this.failed, required this.success});

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        SizedBox(
          height: 58,
          child: Row(
            children: [
              IconButton(
                onPressed: () => Navigator.of(context).maybePop(),
                icon: const Icon(Icons.arrow_back_rounded, color: OvanieColors.navy, size: 27),
              ),
              const Expanded(
                child: Text(
                  'Checkout',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    color: OvanieColors.navy,
                    fontSize: 23,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              const Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Icon(Icons.shield_outlined, color: OvanieColors.navy, size: 21),
                  SizedBox(width: 5),
                  Text(
                    'Paiement 100% sécurisé',
                    style: TextStyle(color: OvanieColors.navy, fontSize: 10.5, fontWeight: FontWeight.w700),
                  ),
                  SizedBox(width: 8),
                ],
              ),
            ],
          ),
        ),
        Padding(
          padding: const EdgeInsets.fromLTRB(22, 8, 22, 8),
          child: _CheckoutProgress(
            current: 4,
            completedThrough: (failed || success) ? 3 : 2,
            validationError: failed,
          ),
        ),
      ],
    );
  }
}

class _CheckoutProgress extends StatelessWidget {
  final int current;
  final int completedThrough;
  final bool validationError;

  const _CheckoutProgress({
    required this.current,
    required this.completedThrough,
    this.validationError = false,
  });

  @override
  Widget build(BuildContext context) {
    const labels = ['Adresse', 'Contact', 'Paiement', 'Validation'];
    return LayoutBuilder(
      builder: (context, constraints) {
        final gap = constraints.maxWidth / 4;
        return SizedBox(
          height: 72,
          child: Stack(
            children: [
              Positioned(
                top: 18,
                left: gap / 2,
                right: gap / 2,
                child: Container(height: 2, color: const Color(0xFFD9DEE8)),
              ),
              Positioned(
                top: 18,
                left: gap / 2,
                width: gap * completedThrough,
                child: Container(height: 2, color: OvanieColors.orange),
              ),
              Row(
                children: List.generate(4, (index) {
                  final step = index + 1;
                  final done = step <= completedThrough;
                  final isCurrent = step == current;
                  return Expanded(
                    child: Column(
                      children: [
                        Container(
                          width: 36,
                          height: 36,
                          alignment: Alignment.center,
                          decoration: BoxDecoration(
                            shape: BoxShape.circle,
                            color: done ? const Color(0xFFFF3E0D) : Colors.white,
                            border: Border.all(
                              color: (isCurrent || done) ? const Color(0xFFFF3E0D) : const Color(0xFFC8D0DE),
                              width: 1.4,
                            ),
                          ),
                          child: done
                              ? const Icon(Icons.check_rounded, color: Colors.white, size: 22)
                              : validationError && isCurrent
                                  ? const Text('!', style: TextStyle(color: OvanieColors.orange, fontWeight: FontWeight.w900, fontSize: 20))
                                  : Text(
                                      '$step',
                                      style: TextStyle(
                                        color: isCurrent ? OvanieColors.orange : const Color(0xFF6B7487),
                                        fontWeight: FontWeight.w800,
                                      ),
                                    ),
                        ),
                        const SizedBox(height: 6),
                        Text(
                          labels[index],
                          style: TextStyle(
                            color: (done || isCurrent) ? OvanieColors.orange : const Color(0xFF515D75),
                            fontSize: 10.8,
                            fontWeight: FontWeight.w600,
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
      },
    );
  }
}

class _PaymentFailedView extends StatelessWidget {
  final MobileOrder? order;
  final int orderId;
  final double fallbackAmount;
  final String fallbackMethod;
  final String fallbackOrderNumber;

  const _PaymentFailedView({
    required this.order,
    required this.orderId,
    required this.fallbackAmount,
    required this.fallbackMethod,
    required this.fallbackOrderNumber,
  });

  @override
  Widget build(BuildContext context) {
    final amount = order?.total ?? fallbackAmount;
    final method = _resolvedMethod(order, fallbackMethod);
    final reference = order?.paymentReference.trim().isNotEmpty == true
        ? order!.paymentReference.trim()
        : '—';
    final date = order?.paymentAttemptCreatedAt ?? order?.updatedAt ?? order?.createdAt;

    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(18, 0, 18, 26),
      children: [
        const _CheckoutHeader(failed: true, success: false),
        const SizedBox(height: 6),
        Container(
          padding: const EdgeInsets.fromLTRB(22, 22, 22, 20),
          decoration: BoxDecoration(
            color: const Color(0xFFFFFCFC),
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: const Color(0xFFFF6B5F), width: 1.2),
          ),
          child: Column(
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Container(
                    width: 104,
                    height: 104,
                    alignment: Alignment.center,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      color: const Color(0xFFFFEFEF),
                      border: Border.all(color: const Color(0xFFFFDCDC), width: 8),
                    ),
                    child: Container(
                      width: 78,
                      height: 78,
                      alignment: Alignment.center,
                      decoration: const BoxDecoration(shape: BoxShape.circle, color: Color(0xFFFF2E1F)),
                      child: const Icon(Icons.close_rounded, color: Colors.white, size: 58),
                    ),
                  ),
                  const SizedBox(width: 20),
                  const Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Paiement échoué',
                          style: TextStyle(color: OvanieColors.navy, fontSize: 22, fontWeight: FontWeight.w900),
                        ),
                        SizedBox(height: 7),
                        Text(
                          'Votre paiement n’a pas pu être finalisé.\nAucun montant n’a été débité.\nVeuillez vérifier vos informations ou essayer un autre mode de paiement.',
                          style: TextStyle(color: OvanieColors.navy, fontSize: 12.5, height: 1.45),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 17),
              const Divider(height: 1, color: Color(0xFFFFD8D2)),
              const SizedBox(height: 14),
              const Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Icon(Icons.info_outline_rounded, color: Color(0xFFFF2E1F), size: 21),
                  SizedBox(width: 12),
                  Expanded(
                    child: Text(
                      'Si un débit apparaît, il sera automatiquement annulé selon votre banque ou opérateur.',
                      style: TextStyle(color: OvanieColors.navy, fontSize: 11.5, height: 1.4),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
        const SizedBox(height: 12),
        _ConfirmationCard(
          title: 'Détails de la tentative',
          child: Column(
            children: [
              _DetailLine(icon: Icons.account_balance_wallet_outlined, label: 'Montant à payer', value: formatFcfa(amount), valueColor: OvanieColors.orange),
              _DetailLine(iconWidget: _operatorLogo(order, fallbackMethod), icon: Icons.payments_outlined, label: 'Méthode', value: method),
              _DetailLine(icon: Icons.tag_rounded, label: 'Référence', value: reference),
              _DetailLine(icon: Icons.calendar_month_outlined, label: 'Date', value: _dateTime(date)),
              _DetailLine(icon: Icons.schedule_rounded, label: 'Statut', value: 'Échoué', badgeColor: const Color(0xFFFFE6E5), valueColor: const Color(0xFFD92D20), last: true),
            ],
          ),
        ),
        const SizedBox(height: 12),
        const _ConfirmationCard(
          title: 'Cause possible',
          child: Column(
            children: [
              _CauseLine(icon: Icons.account_balance_wallet_outlined, text: 'Solde insuffisant sur votre compte.'),
              _CauseLine(icon: Icons.credit_card_outlined, text: 'Numéro de compte ou informations non valides.'),
              _CauseLine(icon: Icons.schedule_outlined, text: 'Délai de confirmation dépassé.'),
              _CauseLine(icon: Icons.wifi_rounded, text: 'Problème réseau temporaire.', last: true),
            ],
          ),
        ),
        const SizedBox(height: 12),
        _ConfirmationCard(
          title: 'Que souhaitez-vous faire ?',
          child: Row(
            children: [
              Expanded(
                child: SizedBox(
                  height: 49,
                  child: FilledButton.icon(
                    onPressed: order == null
                        ? null
                        : () => Navigator.of(context).pushReplacement(
                              MaterialPageRoute<void>(builder: (_) => ResumePaymentScreen(order: order!)),
                            ),
                    style: FilledButton.styleFrom(
                      backgroundColor: const Color(0xFFFF2F14),
                      foregroundColor: Colors.white,
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(9)),
                    ),
                    icon: const Icon(Icons.refresh_rounded),
                    label: const Text('Réessayer', style: TextStyle(fontWeight: FontWeight.w900)),
                  ),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: SizedBox(
                  height: 49,
                  child: OutlinedButton.icon(
                    onPressed: order == null
                        ? null
                        : () => Navigator.of(context).pushReplacement(
                              MaterialPageRoute<void>(builder: (_) => ResumePaymentScreen(order: order!)),
                            ),
                    style: OutlinedButton.styleFrom(
                      foregroundColor: const Color(0xFFFF2F14),
                      side: const BorderSide(color: Color(0xFFFF2F14)),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(9)),
                    ),
                    icon: const Icon(Icons.swap_horiz_rounded),
                    label: const Text('Changer de paiement', style: TextStyle(fontWeight: FontWeight.w800)),
                  ),
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 12),
        _HelpCard(
          onTap: () => Navigator.of(context).push(
            MaterialPageRoute<void>(builder: (_) => const SupportCenterScreen()),
          ),
        ),
      ],
    );
  }
}

class _PaymentSuccessView extends StatelessWidget {
  final MobileOrder? order;
  final int orderId;
  final double fallbackAmount;
  final String fallbackMethod;
  final String fallbackOrderNumber;
  final bool pending;

  const _PaymentSuccessView({
    required this.order,
    required this.orderId,
    required this.fallbackAmount,
    required this.fallbackMethod,
    required this.fallbackOrderNumber,
    required this.pending,
  });

  @override
  Widget build(BuildContext context) {
    final amount = order?.total ?? fallbackAmount;
    final method = _resolvedMethod(order, fallbackMethod);
    final reference = order?.paymentReference.trim().isNotEmpty == true
        ? order!.paymentReference.trim()
        : '—';
    final date = order?.paymentAttemptCreatedAt ?? order?.updatedAt ?? order?.createdAt;
    final orderNumber = order?.orderNumber.trim().isNotEmpty == true
        ? order!.orderNumber
        : fallbackOrderNumber;
    final online = (order?.paymentMethod ?? '').toLowerCase().contains('paydunya') ||
        !method.toLowerCase().contains('livraison');

    final title = pending
        ? 'Paiement en cours'
        : online
            ? 'Paiement réussi !'
            : 'Commande confirmée !';
    final description = pending
        ? 'Nous vérifions votre paiement auprès de notre prestataire sécurisé.'
        : online
            ? 'Votre paiement a été validé avec succès.\nVotre commande va maintenant passer\nà l’étape de validation.'
            : 'Votre commande a été enregistrée avec succès.\nLe règlement sera effectué lors de la livraison.';

    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(18, 0, 18, 26),
      children: [
        _CheckoutHeader(failed: false, success: !pending),
        const SizedBox(height: 6),
        Container(
          padding: const EdgeInsets.fromLTRB(28, 24, 28, 24),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: const Color(0xFFE2E7EF)),
            boxShadow: const [BoxShadow(color: Color(0x10071B48), blurRadius: 18, offset: Offset(0, 6))],
          ),
          child: Row(
            children: [
              Stack(
                clipBehavior: Clip.none,
                children: [
                  Container(
                    width: 126,
                    height: 126,
                    alignment: Alignment.center,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      color: pending ? const Color(0xFFFFF1DD) : const Color(0xFFE1F7E8),
                    ),
                    child: Container(
                      width: 104,
                      height: 104,
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        gradient: LinearGradient(
                          begin: Alignment.topLeft,
                          end: Alignment.bottomRight,
                          colors: pending
                              ? const [Color(0xFFFFB340), Color(0xFFFF8A00)]
                              : const [Color(0xFF21C45A), Color(0xFF0AA142)],
                        ),
                      ),
                      child: Icon(
                        pending ? Icons.schedule_rounded : Icons.check_rounded,
                        color: Colors.white,
                        size: 70,
                      ),
                    ),
                  ),
                  if (!pending) ...const [
                    Positioned(left: 4, top: -12, child: Icon(Icons.star_rounded, size: 18, color: Color(0xFF14AF49))),
                    Positioned(right: -8, top: 3, child: Icon(Icons.star_rounded, size: 14, color: Color(0xFF14AF49))),
                  ],
                ],
              ),
              const SizedBox(width: 26),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(title, style: const TextStyle(color: OvanieColors.navy, fontSize: 28, fontWeight: FontWeight.w900)),
                    const SizedBox(height: 8),
                    Text(description, style: const TextStyle(color: OvanieColors.navy, fontSize: 13.5, height: 1.45)),
                    if (!pending && online) ...[
                      const SizedBox(height: 14),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 9),
                        decoration: BoxDecoration(color: const Color(0xFFF0FAF3), borderRadius: BorderRadius.circular(8)),
                        child: const Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Icon(Icons.mail_outline_rounded, color: Color(0xFF16A34A), size: 21),
                            SizedBox(width: 10),
                            Flexible(
                              child: Text(
                                'Un reçu a été envoyé par SMS et par e-mail.',
                                style: TextStyle(color: OvanieColors.navy, fontSize: 11.8),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ],
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 12),
        _ConfirmationCard(
          title: online ? 'Détails du paiement' : 'Paiement à la livraison',
          child: Column(
            children: [
              _DetailLine(
                icon: Icons.account_balance_wallet_outlined,
                label: online
                    ? (pending ? 'Montant à payer' : 'Montant payé')
                    : 'Total à régler à la réception',
                value: formatFcfa(amount),
                valueColor: OvanieColors.orange,
              ),
              _DetailLine(iconWidget: _operatorLogo(order, fallbackMethod), icon: Icons.payments_outlined, label: 'Méthode', value: method),
              _DetailLine(icon: Icons.tag_rounded, label: 'Référence', value: reference),
              _DetailLine(icon: Icons.calendar_month_outlined, label: 'Date', value: _dateTime(date)),
              _DetailLine(
                icon: Icons.schedule_rounded,
                label: 'Statut',
                value: pending ? 'En attente' : (online ? 'Payé' : 'À la livraison'),
                badgeColor: pending ? const Color(0xFFFFF3DF) : const Color(0xFFE6F7E9),
                valueColor: pending ? const Color(0xFFB46B00) : const Color(0xFF138D3D),
                last: true,
              ),
            ],
          ),
        ),
        const SizedBox(height: 12),
        _ConfirmationCard(
          title: 'Commande associée',
          child: Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    _OrderInfoLine(icon: Icons.receipt_long_outlined, text: 'Commande n° $orderNumber'),
                    const SizedBox(height: 8),
                    _OrderInfoLine(icon: Icons.inventory_2_outlined, text: '${order?.items.length ?? 0} articles'),
                    const SizedBox(height: 8),
                    _OrderInfoLine(
                      icon: Icons.account_balance_wallet_outlined,
                      text: 'Montant commande : ${formatFcfa(amount)}',
                      accentPart: formatFcfa(amount),
                    ),
                  ],
                ),
              ),
              if (order != null && order!.items.isNotEmpty)
                SizedBox(
                  width: 205,
                  height: 82,
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.end,
                    children: order!.items.take(3).map((item) => Padding(
                          padding: const EdgeInsets.only(left: 7),
                          child: Container(
                            width: 58,
                            height: 76,
                            padding: const EdgeInsets.all(5),
                            decoration: BoxDecoration(
                              color: Colors.white,
                              borderRadius: BorderRadius.circular(8),
                              border: Border.all(color: OvanieColors.border),
                            ),
                            child: item.imageUrl.trim().isEmpty
                                ? const Icon(Icons.inventory_2_outlined, color: OvanieColors.muted)
                                : Image.network(
                                    item.imageUrl,
                                    fit: BoxFit.contain,
                                    errorBuilder: (_, __, ___) => const Icon(Icons.inventory_2_outlined, color: OvanieColors.muted),
                                  ),
                          ),
                        )).toList(growable: false),
                  ),
                ),
            ],
          ),
        ),
        const SizedBox(height: 12),
        if (order != null) ...[
          _ConfirmationCard(
            title: 'Informations de livraison',
            child: Column(
              children: [
                _DetailLine(
                  icon: Icons.location_on_outlined,
                  label: 'Adresse',
                  value: [order!.address, order!.quartier, order!.commune]
                      .where((value) => value.trim().isNotEmpty)
                      .toSet()
                      .join(', '),
                ),
                _DetailLine(
                  icon: Icons.person_outline_rounded,
                  label: 'Destinataire',
                  value: order!.deliveryRecipientName.trim().isEmpty
                      ? 'Client OVANIE'
                      : order!.deliveryRecipientName,
                ),
                _DetailLine(
                  icon: Icons.phone_outlined,
                  label: 'Téléphone',
                  value: order!.deliveryPhone.trim().isEmpty
                      ? '—'
                      : order!.deliveryPhone,
                  last: true,
                ),
              ],
            ),
          ),
          const SizedBox(height: 12),
        ],
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 13),
          decoration: BoxDecoration(
            color: const Color(0xFFF3F8FF),
            borderRadius: BorderRadius.circular(9),
            border: Border.all(color: const Color(0xFF9FC6FF)),
          ),
          child: Row(
            children: [
              const Icon(Icons.info_outline_rounded, color: Color(0xFF1266F1), size: 22),
              const SizedBox(width: 12),
              Expanded(
                child: Text(
                  pending
                      ? 'Le statut sera mis à jour automatiquement dès confirmation du prestataire.'
                      : online
                          ? 'Vous pourrez consulter le détail complet de votre commande après validation.'
                          : 'Aucun paiement en ligne n’a été effectué. Préparez le règlement à la réception de la commande.',
                  style: const TextStyle(color: Color(0xFF0759C7), fontSize: 12.5, height: 1.35),
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 12),
        Row(
          children: [
            Expanded(
              child: SizedBox(
                height: 52,
                child: FilledButton(
                  onPressed: order == null
                      ? null
                      : () => Navigator.of(context).pushReplacement(
                            MaterialPageRoute<void>(builder: (_) => OrderDetailScreen(orderId: order!.id, initialOrder: order)),
                          ),
                  style: FilledButton.styleFrom(
                    backgroundColor: const Color(0xFFFF2F14),
                    foregroundColor: Colors.white,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(9)),
                  ),
                  child: Text(
                    pending ? 'Vérifier le statut' : 'Continuer',
                    style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 16),
                  ),
                ),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: SizedBox(
                height: 52,
                child: OutlinedButton.icon(
                  onPressed: order == null
                      ? null
                      : () => Navigator.of(context).push(
                            MaterialPageRoute<void>(builder: (_) => InvoiceScreen(orderId: order!.id, initialOrder: order)),
                          ),
                  style: OutlinedButton.styleFrom(
                    foregroundColor: const Color(0xFFFF2F14),
                    side: const BorderSide(color: Color(0xFFFF2F14)),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(9)),
                  ),
                  icon: const Icon(Icons.file_download_outlined),
                  label: const Text('Télécharger le reçu', style: TextStyle(fontWeight: FontWeight.w800)),
                ),
              ),
            ),
          ],
        ),
        const SizedBox(height: 10),
        TextButton(
          onPressed: () => Navigator.of(context).popUntil((route) => route.isFirst),
          child: const Text(
            'Retour à l’accueil',
            style: TextStyle(color: OvanieColors.navy, fontWeight: FontWeight.w700, fontSize: 14),
          ),
        ),
      ],
    );
  }
}

class _ConfirmationCard extends StatelessWidget {
  final String title;
  final Widget child;

  const _ConfirmationCard({required this.title, required this.child});

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.fromLTRB(16, 15, 16, 14),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: const Color(0xFFDDE4ED)),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(title, style: const TextStyle(color: OvanieColors.navy, fontSize: 16, fontWeight: FontWeight.w900)),
            const SizedBox(height: 10),
            child,
          ],
        ),
      );
}

class _DetailLine extends StatelessWidget {
  final IconData icon;
  final Widget? iconWidget;
  final String label;
  final String value;
  final Color? valueColor;
  final Color? badgeColor;
  final bool last;

  const _DetailLine({
    required this.icon,
    required this.label,
    required this.value,
    this.iconWidget,
    this.valueColor,
    this.badgeColor,
    this.last = false,
  });

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.symmetric(vertical: 10),
        decoration: BoxDecoration(
          border: last ? null : const Border(bottom: BorderSide(color: Color(0xFFD8DFEA), style: BorderStyle.solid)),
        ),
        child: Row(
          children: [
            SizedBox(
              width: 32,
              child: iconWidget ?? Icon(icon, color: const Color(0xFF172B62), size: 22),
            ),
            const SizedBox(width: 10),
            Expanded(child: Text(label, style: const TextStyle(color: Color(0xFF526181), fontSize: 12.3))),
            const SizedBox(width: 10),
            Flexible(
              child: badgeColor == null
                  ? Text(
                      value,
                      maxLines: 3,
                      overflow: TextOverflow.ellipsis,
                      textAlign: TextAlign.right,
                      style: TextStyle(
                        color: valueColor ?? OvanieColors.navy,
                        fontSize: 14,
                        fontWeight: valueColor != null ? FontWeight.w800 : FontWeight.w700,
                      ),
                    )
                  : Align(
                      alignment: Alignment.centerRight,
                      child: Container(
                        padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 7),
                        decoration: BoxDecoration(color: badgeColor, borderRadius: BorderRadius.circular(8)),
                        child: Text(
                          value,
                          style: TextStyle(color: valueColor ?? OvanieColors.navy, fontSize: 12.5, fontWeight: FontWeight.w700),
                        ),
                      ),
                    ),
            ),
          ],
        ),
      );
}

class _CauseLine extends StatelessWidget {
  final IconData icon;
  final String text;
  final bool last;

  const _CauseLine({required this.icon, required this.text, this.last = false});

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.symmetric(vertical: 10),
        decoration: BoxDecoration(
          border: last ? null : const Border(bottom: BorderSide(color: Color(0xFFD8DFEA))),
        ),
        child: Row(
          children: [
            Icon(icon, color: const Color(0xFFFF2F14), size: 21),
            const SizedBox(width: 13),
            Expanded(child: Text(text, style: const TextStyle(color: OvanieColors.navy, fontSize: 12.2))),
          ],
        ),
      );
}

class _HelpCard extends StatelessWidget {
  final VoidCallback onTap;
  const _HelpCard({required this.onTap});

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 13),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: const Color(0xFFE2E6ED)),
        ),
        child: Row(
          children: [
            Container(
              width: 54,
              height: 54,
              decoration: const BoxDecoration(color: Color(0xFFFFF0EE), shape: BoxShape.circle),
              child: const Icon(Icons.headset_mic_outlined, color: OvanieColors.navy, size: 30),
            ),
            const SizedBox(width: 14),
            const Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('Besoin d’aide ?', style: TextStyle(color: OvanieColors.navy, fontWeight: FontWeight.w900, fontSize: 13)),
                  SizedBox(height: 3),
                  Text('Notre équipe peut vous accompagner.', style: TextStyle(color: Color(0xFF526181), fontSize: 11.2)),
                ],
              ),
            ),
            OutlinedButton.icon(
              onPressed: onTap,
              style: OutlinedButton.styleFrom(
                foregroundColor: OvanieColors.navy,
                side: const BorderSide(color: OvanieColors.navy),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
              ),
              icon: const Icon(Icons.chat_bubble_outline_rounded, size: 17),
              label: const Text('Contacter le support', style: TextStyle(fontWeight: FontWeight.w800)),
            ),
          ],
        ),
      );
}

class _OrderInfoLine extends StatelessWidget {
  final IconData icon;
  final String text;
  final String? accentPart;

  const _OrderInfoLine({required this.icon, required this.text, this.accentPart});

  @override
  Widget build(BuildContext context) {
    final accent = accentPart;
    if (accent == null || !text.endsWith(accent)) {
      return Row(
        children: [
          Icon(icon, size: 18, color: OvanieColors.navy),
          const SizedBox(width: 10),
          Expanded(child: Text(text, style: const TextStyle(color: OvanieColors.navy, fontSize: 12.2))),
        ],
      );
    }
    final prefix = text.substring(0, text.length - accent.length);
    return Row(
      children: [
        Icon(icon, size: 18, color: OvanieColors.navy),
        const SizedBox(width: 10),
        Expanded(
          child: Text.rich(
            TextSpan(
              style: const TextStyle(color: OvanieColors.navy, fontSize: 12.2),
              children: [
                TextSpan(text: prefix),
                TextSpan(text: accent, style: const TextStyle(color: OvanieColors.orange)),
              ],
            ),
          ),
        ),
      ],
    );
  }
}

class _ErrorState extends StatelessWidget {
  final String message;
  final VoidCallback onRetry;

  const _ErrorState({required this.message, required this.onRetry});

  @override
  Widget build(BuildContext context) => Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.error_outline_rounded, size: 48, color: OvanieColors.orange),
              const SizedBox(height: 12),
              Text(message, textAlign: TextAlign.center),
              const SizedBox(height: 14),
              FilledButton(onPressed: onRetry, child: const Text('Réessayer')),
            ],
          ),
        ),
      );
}

String _resolvedMethod(MobileOrder? order, String fallback) {
  if (order != null) {
    if (order.paymentMethodLabel.trim().isNotEmpty) return order.paymentMethodLabel.trim();
    final provider = order.paymentProvider.trim().toLowerCase();
    if (provider == 'wave') return 'Wave';
    if (provider == 'orange') return 'Orange Money';
    if (provider == 'mtn') return 'MTN MoMo';
    if (provider == 'moov') return 'Moov Money';
    if (provider == 'card') return 'Carte bancaire';
    if (order.paymentMethod == 'cash_on_delivery') return 'Paiement à la livraison';
  }
  return fallback.trim().isEmpty ? 'Paiement sécurisé' : fallback.trim();
}

Widget? _operatorLogo(MobileOrder? order, String fallback) {
  final haystack = '${order?.paymentProvider ?? ''} ${order?.paymentMethodLabel ?? ''} ${order?.paymentMethod ?? ''} $fallback'.toLowerCase();
  String? asset;
  if (haystack.contains('wave')) asset = 'assets/images/operators/wave.png';
  if (haystack.contains('orange')) asset = 'assets/images/operators/orange.png';
  if (haystack.contains('mtn')) asset = 'assets/images/operators/mtn.png';
  if (haystack.contains('moov')) asset = 'assets/images/operators/moov.png';
  if (asset == null) return null;
  return SizedBox(width: 26, height: 26, child: Image.asset(asset, fit: BoxFit.contain));
}

String _dateTime(DateTime? date) {
  if (date == null) return '—';
  const months = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
  final minute = date.minute.toString().padLeft(2, '0');
  return '${date.day} ${months[date.month - 1]} ${date.year} à ${date.hour.toString().padLeft(2, '0')}:$minute';
}
