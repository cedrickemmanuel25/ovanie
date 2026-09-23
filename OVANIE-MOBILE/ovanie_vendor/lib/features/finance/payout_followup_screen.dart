import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../core/network/api_client.dart';
import '../../data/vendor_repository.dart';
import '../orders/order_ui.dart';
import 'data_ui.dart';
import 'finance_ui.dart';
import '../support/vendor_support_screen.dart';

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

  Future<void> _start() async {
    final alreadyRequested = ('${_p['vendor_followup_requested_at'] ?? ''}').isNotEmpty;
    if (!alreadyRequested) {
      setState(() => _sending = true);
      try {
        await VendorRepository.instance.requestPayoutFollowUp(widget.payoutId);
      } catch (e) {
        if (mounted) setState(() => _error = ApiClient.friendlyError(e));
      } finally {
        if (mounted) setState(() => _sending = false);
      }
    }
    await _load();
  }

  Future<void> _load() async {
    setState(() => _loading = _p.isEmpty);
    try {
      final data = await VendorRepository.instance.payout(widget.payoutId);
      final detail = data['payout'];
      if (!mounted) return;
      setState(() {
        if (detail is Map) _p = Map<String, dynamic>.from(detail);
        _error = null;
      });
    } catch (e) {
      if (mounted && _p.isEmpty) setState(() => _error = ApiClient.friendlyError(e));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final requestedAt = orderHumanDate(_p['vendor_followup_requested_at']);
    final hasResponse = ('${_p['admin_note'] ?? ''}').trim().isNotEmpty;
    final closed = ['paid', 'cancelled'].contains('${_p['status'] ?? ''}');
    final reference = 'SV-${_p['payout_reference'] ?? _p['reference'] ?? widget.payoutId}';

    return Scaffold(
      backgroundColor: Colors.white,
      body: RefreshIndicator(
        onRefresh: _load,
        child: CustomScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          slivers: [
            SliverToBoxAdapter(
              child: OrderHeader(
                showBack: true,
                title: 'Suivi de ma demande de versement',
                subtitle: 'Suivez l’évolution de votre demande en temps réel.',
                titleTrailing: HeaderRefBadge(
                  reference: '# $reference',
                  dateLabel: requestedAt.isEmpty ? null : 'Créée le $requestedAt',
                ),
              ),
            ),
            if (_loading)
              const SliverFillRemaining(
                hasScrollBody: false,
                child: Center(child: CircularProgressIndicator(color: orderOrange)),
              )
            else
              SliverToBoxAdapter(
                child: ColoredBox(
                  color: const Color(0xFF031D47),
                  child: OrderSheet(
                    child: OrderResponsiveBody(
                      padding: const EdgeInsets.fromLTRB(18, 20, 18, 20),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          if (_error != null) ...[
                            Container(
                              padding: const EdgeInsets.all(13),
                              decoration: BoxDecoration(
                                color: const Color(0xFFFFF1F0),
                                borderRadius: BorderRadius.circular(12),
                                border: Border.all(color: const Color(0xFFF2B8B5)),
                              ),
                              child: Text(_error!, style: const TextStyle(color: Color(0xFFB42318), fontSize: 12.5)),
                            ),
                            const SizedBox(height: 14),
                          ],

                          // ---- Montant + infos ------------------------------
                          OrderCard(
                            padding: const EdgeInsets.all(16),
                            child: dataPair(
                              Row(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Container(
                                    width: 58,
                                    height: 58,
                                    alignment: Alignment.center,
                                    decoration: BoxDecoration(
                                      color: orderOrange.withValues(alpha: .11),
                                      borderRadius: BorderRadius.circular(16),
                                    ),
                                    child: const Icon(Icons.volunteer_activism_outlined, color: orderOrange, size: 27),
                                  ),
                                  const SizedBox(width: 14),
                                  Expanded(
                                    child: Column(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        const Text(
                                          'Montant du versement',
                                          style: TextStyle(color: orderMuted, fontSize: 12, fontWeight: FontWeight.w700),
                                        ),
                                        const SizedBox(height: 3),
                                        Text(
                                          orderMoney(_p['amount']),
                                          style: const TextStyle(color: orderText, fontSize: 24, fontWeight: FontWeight.w900),
                                        ),
                                        const SizedBox(height: 6),
                                        Row(
                                          children: [
                                            Expanded(
                                              child: Text(
                                                'Réf. demande : $reference',
                                                maxLines: 1,
                                                overflow: TextOverflow.ellipsis,
                                                style: const TextStyle(color: orderMuted, fontSize: 12),
                                              ),
                                            ),
                                            InkWell(
                                              onTap: () => Clipboard.setData(ClipboardData(text: reference)),
                                              child: const Padding(
                                                padding: EdgeInsets.only(left: 6),
                                                child: Icon(Icons.copy_outlined, size: 16, color: orderBlue),
                                              ),
                                            ),
                                          ],
                                        ),
                                        const SizedBox(height: 10),
                                        Container(
                                          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 7),
                                          decoration: BoxDecoration(
                                            color: orderOrange.withValues(alpha: .11),
                                            borderRadius: BorderRadius.circular(99),
                                          ),
                                          child: Text(
                                            _sending ? 'Envoi de la demande...' : 'Demande de suivi en cours',
                                            style: const TextStyle(color: orderOrange, fontSize: 12, fontWeight: FontWeight.w700),
                                          ),
                                        ),
                                      ],
                                    ),
                                  ),
                                ],
                              ),
                              Column(
                                crossAxisAlignment: CrossAxisAlignment.stretch,
                                children: [
                                  dataInfoRow(Icons.event_outlined, 'Date de la demande', requestedAt.isEmpty ? '—' : requestedAt),
                                  dataInfoRow(Icons.account_balance_outlined, 'Méthode de paiement', screenText(_p['payment_method_label'])),
                                  dataInfoRow(Icons.phone_iphone_outlined, 'Numéro de compte', screenText(_p['phone'])),
                                ],
                              ),
                              breakpoint: 520,
                            ),
                          ),
                          const SizedBox(height: 18),

                          // ---- Étapes de traitement ----------------------------
                          Row(
                            children: [
                              const Icon(Icons.add_task_outlined, color: orderText, size: 20),
                              const SizedBox(width: 9),
                              const Text(
                                'Étapes de traitement',
                                style: TextStyle(color: orderText, fontSize: 15, fontWeight: FontWeight.w800),
                              ),
                            ],
                          ),
                          const SizedBox(height: 12),
                          OrderCard(
                            child: Column(
                              children: [
                                _step(1, 'Demande envoyée', 'Votre demande de suivi a été enregistrée avec succès.', requestedAt, requestedAt.isNotEmpty),
                                _step(2, 'En cours de traitement', 'Notre équipe vérifie les informations auprès de notre partenaire de paiement.',
                                    requestedAt.isNotEmpty ? requestedAt : '', requestedAt.isNotEmpty),
                                _step(3, 'Réponse disponible', 'Vous serez notifié dès que nous aurons une réponse.',
                                    hasResponse ? orderHumanDate(_p['updated_at']) : '', hasResponse),
                                _step(4, 'Clôturée', 'La demande sera clôturée après résolution.',
                                    closed ? orderHumanDate(_p['paid_at']) : '', closed, isLast: true),
                              ],
                            ),
                          ),

                          if (hasResponse) ...[
                            const SizedBox(height: 18),
                            Container(
                              padding: const EdgeInsets.all(14),
                              decoration: BoxDecoration(
                                color: const Color(0xFFFFF4E5),
                                borderRadius: BorderRadius.circular(12),
                                border: Border.all(color: const Color(0xFFF0B429)),
                              ),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Row(
                                    children: const [
                                      Icon(Icons.chat_bubble_outline_rounded, color: Color(0xFFB07500), size: 18),
                                      SizedBox(width: 8),
                                      Text('Dernière mise à jour', style: TextStyle(color: Color(0xFF7A4E00), fontWeight: FontWeight.w800, fontSize: 13.5)),
                                    ],
                                  ),
                                  const SizedBox(height: 8),
                                  Text('${_p['admin_note']}', style: const TextStyle(color: Color(0xFF7A4E00), fontSize: 12.5, height: 1.4)),
                                ],
                              ),
                            ),
                          ],

                          const SizedBox(height: 18),
                          Container(
                            padding: const EdgeInsets.all(16),
                            decoration: BoxDecoration(
                              color: orderSoftBlue,
                              borderRadius: BorderRadius.circular(14),
                              border: Border.all(color: const Color(0xFFD8E7FF)),
                            ),
                            child: dataPair(
                              const Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Row(
                                    children: [
                                      Icon(Icons.info_rounded, color: orderBlue, size: 20),
                                      SizedBox(width: 8),
                                      Text('Besoin d’aide ?', style: TextStyle(color: orderText, fontSize: 15, fontWeight: FontWeight.w800)),
                                    ],
                                  ),
                                  SizedBox(height: 6),
                                  Text(
                                    'Notre équipe support est à votre écoute pour toute question concernant vos versements.',
                                    style: TextStyle(color: Color(0xFF16458D), fontSize: 12.5, height: 1.35),
                                  ),
                                ],
                              ),
                              OutlinedButton.icon(
                                onPressed: () => Navigator.of(context).push(MaterialPageRoute<void>(
                                  builder: (_) => VendorSupportScreen(
                                    initialCategory: 'payouts',
                                    initialContextType: 'vendor_payout',
                                    initialContextId: widget.payoutId,
                                    initialSubject: 'Assistance versement $reference',
                                    startNew: true,
                                  ),
                                )),
                                icon: const Icon(Icons.headset_mic_outlined, size: 18),
                                label: const Text('Contacter le support', style: TextStyle(fontSize: 12.5)),
                                style: OutlinedButton.styleFrom(
                                  foregroundColor: orderBlue,
                                  backgroundColor: Colors.white,
                                  side: const BorderSide(color: orderBlue),
                                  minimumSize: const Size(0, 46),
                                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(9)),
                                ),
                              ),
                              breakpoint: 420,
                            ),
                          ),

                          const SizedBox(height: 20),
                          Row(
                            children: [
                              Expanded(child: OrderSecondaryButton(label: 'Retour', onPressed: () => Navigator.of(context).pop())),
                              const SizedBox(width: 12),
                              Expanded(flex: 2, child: OrderPrimaryButton(label: 'Actualiser le statut', icon: Icons.refresh_rounded, onPressed: _load)),
                            ],
                          ),
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
  }

  Widget _step(int number, String title, String description, String date, bool done, {bool isLast = false}) {
    return IntrinsicHeight(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Column(
            children: [
              Container(
                width: 28,
                height: 28,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: done ? orderOrange : Colors.white,
                  shape: BoxShape.circle,
                  border: Border.all(color: done ? orderOrange : const Color(0xFFC9D3E1), width: 1.6),
                ),
                child: done ? const Icon(Icons.check_rounded, color: Colors.white, size: 16) : null,
              ),
              if (!isLast) Expanded(child: Container(width: 2, color: done ? orderOrange.withValues(alpha: .4) : const Color(0xFFE0E5EC))),
            ],
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Padding(
              padding: EdgeInsets.only(bottom: isLast ? 0 : 18),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(title, style: TextStyle(color: done ? orderText : orderMuted, fontSize: 14, fontWeight: FontWeight.w800)),
                        const SizedBox(height: 3),
                        Text(description, style: const TextStyle(color: orderMuted, fontSize: 12, height: 1.35)),
                      ],
                    ),
                  ),
                  Text(date.isEmpty ? '-' : date, style: const TextStyle(color: orderMuted, fontSize: 11)),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
