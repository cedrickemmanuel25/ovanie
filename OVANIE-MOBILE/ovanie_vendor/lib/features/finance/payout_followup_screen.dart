import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../core/network/api_client.dart';
import '../../core/ui/vendor_mockup_ui.dart';
import '../../data/vendor_repository.dart';
import '../orders/order_ui.dart';
import '../orders/order_widgets.dart';
import '../shell/vendor_tabs.dart';
import '../support/vendor_support_screen.dart';
import 'data_ui.dart';
import 'finance_ui.dart';

/// Suivi d'une demande concernant un versement : la demande est envoyée à
/// l'ouverture si elle n'existe pas encore, puis l'écran en montre les étapes.
class PayoutFollowUpScreen extends StatefulWidget {
  const PayoutFollowUpScreen({super.key, required this.payoutId, this.initialPayout});
  final int payoutId;
  final Map<String, dynamic>? initialPayout;

  @override
  State<PayoutFollowUpScreen> createState() => _PayoutFollowUpScreenState();
}

class _PayoutFollowUpScreenState extends State<PayoutFollowUpScreen> {
  Map<String, dynamic> _p = <String, dynamic>{};
  bool _loading = true;
  bool _sending = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    if (widget.initialPayout != null) _p = Map<String, dynamic>.from(widget.initialPayout!);
    _start();
  }

  bool get _requested => '${_p['vendor_followup_requested_at'] ?? ''}'.isNotEmpty;

  Future<void> _start() async {
    // Sans donnée de départ, on lit d'abord le versement : une demande déjà
    // enregistrée n'est jamais renvoyée.
    if (_p.isEmpty) await _load();
    if (!mounted || _p.isEmpty || _requested) {
      if (mounted) setState(() => _loading = false);
      return;
    }
    setState(() => _sending = true);
    try {
      await VendorRepository.instance.requestPayoutFollowUp(widget.payoutId);
    } catch (e) {
      if (mounted) setState(() => _error = ApiClient.friendlyError(e));
    } finally {
      if (mounted) setState(() => _sending = false);
    }
    await _load(keepError: true);
  }

  /// Relit le versement ; n'envoie jamais de nouvelle demande.
  Future<void> _load({bool keepError = false}) async {
    if (!mounted) return;
    setState(() => _loading = true);
    try {
      final data = await VendorRepository.instance.payout(widget.payoutId, fresh: true);
      final detail = orderMap(data['payout']);
      if (!mounted) return;
      setState(() {
        if (detail.isNotEmpty) _p = detail;
        if (!keepError) _error = null;
      });
    } catch (e) {
      if (mounted) setState(() => _error = ApiClient.friendlyError(e));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _support(String reference) => Navigator.of(context).push(MaterialPageRoute<void>(
        builder: (_) => VendorSupportScreen(
          initialCategory: 'payouts',
          initialContextType: 'vendor_payout',
          initialContextId: widget.payoutId,
          initialSubject: 'Assistance versement $reference',
          startNew: true,
        ),
      ));

  @override
  Widget build(BuildContext context) {
    final reference = screenText(_p['payout_reference'] ?? _p['reference'], '${widget.payoutId}');
    final requestedOn = orderShortDate(_p['vendor_followup_requested_at']);
    return OrderPage(
      tab: VendorTab.finance,
      title: 'Suivi de ma demande',
      subtitle: 'Suivez l’évolution de votre demande de versement.',
      loading: _loading || _sending,
      error: _error,
      hasContent: _p.isNotEmpty,
      emptyMessage: 'Ce versement est indisponible.',
      onRefresh: _load,
      actions: [
        MkBarButton(label: 'Retour', icon: Icons.arrow_back_rounded, onTap: () => Navigator.of(context).pop()),
        MkBarButton(
          label: 'Actualiser le statut',
          icon: Icons.sync_rounded,
          filled: true,
          loading: _loading && _p.isNotEmpty,
          onTap: _sending ? null : _load,
        ),
      ],
      children: [
        _amountCard(reference, requestedOn),
        _stepsCard(requestedOn),
        _noteCard(),
        _helpCard(reference),
      ],
    );
  }

  // ---- Montant ----------------------------------------------------------------
  Widget _amountCard(String reference, String requestedOn) {
    final state = _sending
        ? 'Envoi de la demande…'
        : !_requested
            ? 'Demande non envoyée'
            : ['paid', 'cancelled'].contains('${_p['status']}')
                ? 'Demande clôturée'
                : 'Demande de suivi en cours';
    final wide = MediaQuery.sizeOf(context).width >= 380;
    return OrderCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const FinanceIconDisc(Icons.volunteer_activism_rounded, color: Mk.orange, size: 52),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Montant du versement',
                      style: TextStyle(color: Mk.muted, fontSize: 13, fontWeight: FontWeight.w500),
                    ),
                    FinanceAmount(_p['amount'], size: 27),
                    const SizedBox(height: 8),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 6),
                      decoration: BoxDecoration(color: Mk.softOrange, borderRadius: BorderRadius.circular(99)),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          const Icon(Icons.hourglass_top_rounded, color: Color(0xFFB54400), size: 16),
                          const SizedBox(width: 6),
                          Flexible(
                            child: Text(
                              state,
                              style: const TextStyle(color: Color(0xFFB54400), fontSize: 12.5, fontWeight: FontWeight.w700),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
              if (wide)
                Padding(
                  padding: const EdgeInsets.only(left: 6),
                  child: Image.asset('assets/images/mockups/fin_wallet.jpg', width: 104, excludeFromSemantics: true),
                ),
            ],
          ),
          const SizedBox(height: 8),
          Align(
            alignment: Alignment.centerLeft,
            child: InkWell(
              onTap: () {
                Clipboard.setData(ClipboardData(text: reference));
                ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Référence copiée.')));
              },
              borderRadius: BorderRadius.circular(6),
              child: Padding(
                padding: const EdgeInsets.symmetric(vertical: 3),
                child: FittedBox(
                  fit: BoxFit.scaleDown,
                  alignment: Alignment.centerLeft,
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text.rich(
                        TextSpan(
                          text: 'Réf. demande : ',
                          children: [
                            TextSpan(text: reference, style: const TextStyle(color: Mk.navy, fontWeight: FontWeight.w700)),
                          ],
                        ),
                        maxLines: 1,
                        style: const TextStyle(color: Mk.muted, fontSize: 12.5, fontWeight: FontWeight.w500),
                      ),
                      const SizedBox(width: 6),
                      const Icon(Icons.copy_rounded, size: 17, color: orderBlue, semanticLabel: 'Copier la référence'),
                    ],
                  ),
                ),
              ),
            ),
          ),
          const Divider(height: 22, color: orderBorder),
          FinanceInfoLine(
            icon: Icons.calendar_today_outlined,
            label: 'Date de la demande',
            value: requestedOn.isEmpty ? '—' : requestedOn,
          ),
          FinanceInfoLine(
            icon: Icons.credit_card_outlined,
            label: 'Méthode de paiement',
            value: screenText(_p['payment_method_label']),
          ),
          FinanceInfoLine(
            icon: Icons.person_outline_rounded,
            label: 'Numéro de compte',
            value: screenText(payoutPhoneLabel(_p['phone'])),
          ),
        ],
      ),
    );
  }

  // ---- Étapes -------------------------------------------------------------------
  Widget _stepsCard(String requestedOn) {
    final hasResponse = '${_p['admin_note'] ?? ''}'.trim().isNotEmpty;
    final closed = ['paid', 'cancelled'].contains('${_p['status'] ?? ''}');
    final steps = <(String, String, String, bool)>[
      ('Demande envoyée', 'Votre demande de suivi a été enregistrée avec succès.', requestedOn, _requested),
      (
        'En cours de traitement',
        'Notre équipe vérifie les informations auprès de notre partenaire de paiement.',
        '',
        _requested && (hasResponse || closed),
      ),
      (
        'Réponse disponible',
        hasResponse ? 'Une réponse de notre équipe est disponible ci-dessous.' : 'Vous serez notifié dès que nous aurons une réponse.',
        hasResponse ? orderShortDate(_p['updated_at']) : '',
        hasResponse,
      ),
      (
        'Clôturée',
        closed ? 'La demande est clôturée.' : 'La demande sera clôturée après résolution.',
        closed ? orderShortDate(_p['paid_at'] ?? _p['updated_at']) : '',
        closed,
      ),
    ];
    final current = _requested ? steps.indexWhere((step) => !step.$4) : -1;
    return OrderCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const OrderSectionTitle('Étapes de traitement'),
          const SizedBox(height: 14),
          for (var i = 0; i < steps.length; i++)
            _step(steps[i], done: steps[i].$4, current: i == current, isLast: i == steps.length - 1),
        ],
      ),
    );
  }

  Widget _step((String, String, String, bool) step, {required bool done, required bool current, required bool isLast}) {
    final color = done ? orderGreen : (current ? Mk.orange : const Color(0xFFB6C0D4));
    return IntrinsicHeight(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Column(
            children: [
              Container(
                width: 26,
                height: 26,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: color,
                  shape: BoxShape.circle,
                  border: Border.all(color: color.withValues(alpha: .25), width: 3, strokeAlign: BorderSide.strokeAlignOutside),
                ),
                child: done ? const Icon(Icons.check_rounded, color: Colors.white, size: 17) : null,
              ),
              if (!isLast) Expanded(child: Container(width: 3, color: done ? orderGreen : const Color(0xFFDCE2EC))),
            ],
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Padding(
              padding: EdgeInsets.only(bottom: isLast ? 0 : 18),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    step.$1,
                    style: TextStyle(color: done || current ? Mk.navy : Mk.icon, fontSize: 14.5, fontWeight: FontWeight.w800),
                  ),
                  const SizedBox(height: 2),
                  Text(step.$2, style: const TextStyle(color: Mk.muted, fontSize: 13, height: 1.4, fontWeight: FontWeight.w500)),
                  if (step.$3.isNotEmpty)
                    Padding(
                      padding: const EdgeInsets.only(top: 2),
                      child: Text(step.$3, style: const TextStyle(color: Mk.icon, fontSize: 12, fontWeight: FontWeight.w600)),
                    ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  // ---- Message --------------------------------------------------------------------
  Widget _noteCard() {
    final note = '${_p['admin_note'] ?? ''}'.trim();
    final order = screenText(orderMap(_p['order'])['order_number'], '');
    final String message;
    if (note.isNotEmpty) {
      message = note;
    } else if (_p['status'] == 'blocked') {
      message = order.isEmpty
          ? 'Le versement sera libéré après confirmation de la livraison de la commande.'
          : 'Le versement sera libéré après confirmation de livraison de la commande $order.';
    } else {
      return const SizedBox.shrink();
    }
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(color: const Color(0xFFFFF6DC), borderRadius: BorderRadius.circular(14)),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Icon(Icons.sms_outlined, color: Color(0xFF8A5200), size: 24),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              message,
              style: const TextStyle(color: Color(0xFF7A4500), fontSize: 13.5, height: 1.4, fontWeight: FontWeight.w700),
            ),
          ),
        ],
      ),
    );
  }

  // ---- Aide -------------------------------------------------------------------------
  Widget _helpCard(String reference) {
    final wide = MediaQuery.sizeOf(context).width >= 380;
    return OrderCard(
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const OrderSectionTitle('Besoin d’aide ?'),
                const SizedBox(height: 4),
                const Text(
                  'Notre équipe support est à votre écoute pour toute question concernant vos versements.',
                  style: TextStyle(color: Mk.muted, fontSize: 13, height: 1.4, fontWeight: FontWeight.w500),
                ),
                const SizedBox(height: 12),
                MkBarButton(
                  label: 'Contacter le support',
                  icon: Icons.headset_mic_outlined,
                  color: orderBlue,
                  height: 44,
                  onTap: () => _support(reference),
                ),
              ],
            ),
          ),
          if (wide)
            Padding(
              padding: const EdgeInsets.only(left: 10),
              child: Image.asset('assets/images/mockups/fin_support.jpg', width: 96, excludeFromSemantics: true),
            ),
        ],
      ),
    );
  }
}
