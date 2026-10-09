import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../core/network/api_client.dart';
import '../../core/ui/vendor_mockup_ui.dart';
import '../../data/vendor_repository.dart';
import '../orders/order_detail_screen.dart';
import '../orders/order_ui.dart';
import '../orders/order_widgets.dart';
import '../shell/vendor_tabs.dart';
import 'data_ui.dart';
import 'finance_ui.dart';
import 'payout_followup_screen.dart';

/// Détail d'un versement : montant, étapes, résumé, compte de paiement,
/// commandes incluses, reçu et demande de suivi.
class PayoutDetailScreen extends StatefulWidget {
  const PayoutDetailScreen({super.key, required this.payoutId, this.initialPayout});
  final int payoutId;
  final Map<String, dynamic>? initialPayout;
  @override
  State<PayoutDetailScreen> createState() => _PayoutDetailScreenState();
}

class _PayoutDetailScreenState extends State<PayoutDetailScreen> {
  Map<String, dynamic> _p = {};
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    if (widget.initialPayout != null) _p = Map<String, dynamic>.from(widget.initialPayout!);
    _load();
  }

  Future<void> _load() async {
    if (!mounted) return;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final data = await VendorRepository.instance.payout(widget.payoutId, fresh: true);
      if (!mounted) return;
      setState(() {
        final detail = orderMap(data['payout']);
        if (detail.isEmpty) {
          _error = 'Versement indisponible.';
        } else {
          _p = detail;
        }
      });
    } catch (e) {
      if (mounted) setState(() => _error = ApiClient.friendlyError(e));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  String get _status => '${_p['status'] ?? ''}';
  bool get _paid => _status == 'paid';

  List<Map<String, dynamic>> get _orders {
    final list = orderList(_p['orders']).map(orderMap).toList();
    if (list.isNotEmpty) return list;
    final single = orderMap(_p['order']);
    return single.isEmpty ? [] : [single];
  }

  void _openOrder(Map<String, dynamic> order) {
    final id = int.tryParse('${order['id']}');
    if (id != null) {
      Navigator.push(context, MaterialPageRoute<void>(builder: (_) => OrderDetailScreen(orderId: id)));
    }
  }

  /// Une seule commande : son détail. Plusieurs : la liste à choisir.
  void _openOrders() {
    final orders = _orders;
    if (orders.length == 1) return _openOrder(orders.first);
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(22))),
      builder: (sheet) => FinanceSheet(
        children: [
          Text(
            'Commandes incluses (${orders.length})',
            style: const TextStyle(color: Mk.navy, fontSize: 18, fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 6),
          for (final order in orders)
            _orderRow(order, onTap: () {
              Navigator.pop(sheet);
              _openOrder(order);
            }),
        ],
      ),
    );
  }

  void _copy(String text) {
    Clipboard.setData(ClipboardData(text: text));
    ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Référence copiée.')));
  }

  Future<void> _followUp() async {
    await Navigator.push(
      context,
      MaterialPageRoute<void>(builder: (_) => PayoutFollowUpScreen(payoutId: widget.payoutId, initialPayout: _p)),
    );
    if (mounted) _load();
  }

  @override
  Widget build(BuildContext context) {
    final orders = _orders;
    final hasReceipt = _p['has_receipt'] == true;
    final followed = '${_p['vendor_followup_requested_at'] ?? ''}'.isNotEmpty;
    return OrderPage(
      tab: VendorTab.finance,
      title: 'Détail du versement',
      subtitle: 'Consultez toutes les informations de ce versement.',
      loading: _loading,
      error: _error,
      hasContent: _p.isNotEmpty,
      emptyMessage: 'Ce versement est indisponible.',
      onRefresh: _load,
      children: [
        _amountCard(),
        if (_status == 'failed' || _status == 'cancelled')
          MkNotice(
            _status == 'failed'
                ? 'Ce versement a échoué. Vérifiez votre compte de paiement ou demandez un suivi.'
                : 'Ce versement a été annulé.',
          ),
        _statusCard(),
        _summaryCard(),
        _paymentCard(),
        _ordersCard(orders),
        Row(
          children: [
            Expanded(
              child: FinanceActionButton(
                label: 'Télécharger le reçu',
                icon: Icons.file_download_outlined,
                onTap: hasReceipt ? () => downloadReceipt(context, widget.payoutId) : null,
              ),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: FinanceActionButton(
                label: orders.length > 1 ? 'Voir les commandes' : 'Voir la commande',
                icon: Icons.receipt_long_outlined,
                iconColor: Mk.icon,
                onTap: orders.isEmpty ? null : _openOrders,
              ),
            ),
          ],
        ),
        if (!hasReceipt)
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 4),
            child: Text(
              _paid
                  ? 'Le reçu sera disponible dès qu’il aura été ajouté à ce versement.'
                  : 'Le reçu sera disponible une fois le versement effectué.',
              style: const TextStyle(color: Mk.muted, fontSize: 12, height: 1.35, fontWeight: FontWeight.w500),
            ),
          ),
        MkBarButton(
          label: followed ? 'Suivre ma demande' : 'Demander un suivi',
          icon: Icons.support_agent_rounded,
          filled: true,
          color: Mk.navy,
          onTap: _followUp,
        ),
      ],
    );
  }

  // ---- Montant ------------------------------------------------------------------
  Widget _amountCard() {
    final reference = screenText(_p['reference']);
    final date = _p['paid_at'] ?? _p['scheduled_for'] ?? _p['expected_payment_date'];
    return Container(
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(14)),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Container(height: 4, color: Mk.orange),
          Padding(
            padding: const EdgeInsets.fromLTRB(14, 14, 14, 8),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const FinanceIconDisc(Icons.account_balance_rounded, color: Color(0xFF0B4FC4), size: 54),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Wrap(
                            alignment: WrapAlignment.spaceBetween,
                            crossAxisAlignment: WrapCrossAlignment.center,
                            spacing: 8,
                            runSpacing: 4,
                            children: [
                              Text(
                                _paid ? 'Montant versé' : 'Montant du versement',
                                style: const TextStyle(color: Mk.muted, fontSize: 13, fontWeight: FontWeight.w500),
                              ),
                              PayoutStatusPill(status: _status),
                            ],
                          ),
                          const SizedBox(height: 2),
                          FinanceAmount(_p['amount'], size: 28),
                          const SizedBox(height: 4),
                          InkWell(
                            onTap: () => _copy(reference),
                            borderRadius: BorderRadius.circular(6),
                            child: Padding(
                              padding: const EdgeInsets.symmetric(vertical: 3),
                              child: Row(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  Flexible(
                                    child: Text.rich(
                                      TextSpan(
                                        text: 'Réf. versement : ',
                                        children: [
                                          TextSpan(
                                            text: reference,
                                            style: const TextStyle(color: Mk.navy, fontWeight: FontWeight.w700),
                                          ),
                                        ],
                                      ),
                                      style: const TextStyle(color: Mk.muted, fontSize: 12.5, fontWeight: FontWeight.w500),
                                    ),
                                  ),
                                  const SizedBox(width: 6),
                                  const Icon(Icons.copy_rounded, size: 17, color: orderBlue, semanticLabel: 'Copier la référence'),
                                ],
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
                const Divider(height: 22, color: orderBorder),
                FinanceInfoLine(
                  icon: Icons.calendar_today_outlined,
                  label: _paid ? 'Date de versement' : 'Date prévue',
                  value: date == null ? 'À confirmer' : orderShortDate(date),
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
          ),
        ],
      ),
    );
  }

  // ---- Statut ---------------------------------------------------------------------
  Widget _statusCard() {
    String caption(Object? raw) => raw == null ? 'En attente' : orderShortDateTime(raw).replaceFirst(' à ', '\n');
    final steps = <(String, Object?)>[
      // Tant que la commande n'est pas livrée, le versement n'est pas encore planifié.
      ('Versement planifié', _status == 'blocked' ? null : (_p['scheduled_for'] ?? _p['approved_at'] ?? _p['created_at'])),
      ('En cours de traitement', _p['processing_at'] ?? _p['paid_at']),
      ('Transfert effectué', _p['paid_at']),
      ('Versé', _paid ? _p['paid_at'] : null),
    ];
    final firstPending = steps.indexWhere((step) => step.$2 == null);
    return OrderCard(
      padding: const EdgeInsets.fromLTRB(6, 14, 6, 14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Padding(
            padding: EdgeInsets.symmetric(horizontal: 8),
            child: OrderSectionTitle('Statut du versement'),
          ),
          const SizedBox(height: 12),
          OrderStepsRow(
            steps: [
              for (var i = 0; i < steps.length; i++)
                OrderStep(
                  steps[i].$1,
                  done: steps[i].$2 != null,
                  active: i == firstPending && !['failed', 'cancelled'].contains(_status),
                  caption: caption(steps[i].$2),
                ),
            ],
          ),
          if (_status == 'blocked')
            const Padding(
              padding: EdgeInsets.fromLTRB(8, 12, 8, 0),
              child: Text(
                'Ce versement sera planifié dès que la commande sera livrée et validée.',
                style: TextStyle(color: Mk.muted, fontSize: 12.5, height: 1.35, fontWeight: FontWeight.w500),
              ),
            ),
        ],
      ),
    );
  }

  // ---- Résumé -----------------------------------------------------------------------
  Widget _summaryCard() {
    // La commission et les frais sont déjà déduits du net : le vendeur ne voit
    // que le total de ses ventes et le montant qui lui revient.
    return OrderCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const OrderSectionTitle('Résumé du versement'),
          const SizedBox(height: 10),
          Row(
            children: [
              const FinanceIconDisc(Icons.bar_chart_rounded, color: Color(0xFF1668E3), size: 34),
              const SizedBox(width: 10),
              const Expanded(
                child: Text(
                  'Total des ventes concernées',
                  style: TextStyle(color: Mk.muted, fontSize: 13.5, fontWeight: FontWeight.w500),
                ),
              ),
              const SizedBox(width: 8),
              ConstrainedBox(constraints: const BoxConstraints(maxWidth: 140), child: FinanceAmount(_p['total_amount'], size: 15.5)),
            ],
          ),
          const SizedBox(height: 10),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 10),
            decoration: BoxDecoration(color: Mk.softOrange, borderRadius: BorderRadius.circular(10)),
            child: Row(
              children: [
                const FinanceIconDisc(Icons.savings_outlined, color: Mk.orange, size: 34),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    _paid ? 'Montant net versé' : 'Montant net prévu',
                    style: const TextStyle(color: Mk.navy, fontSize: 14, fontWeight: FontWeight.w800),
                  ),
                ),
                const SizedBox(width: 8),
                ConstrainedBox(
                  constraints: const BoxConstraints(maxWidth: 150),
                  child: FinanceAmount(_p['amount'], size: 19, color: const Color(0xFFE85400)),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  // ---- Informations de paiement -----------------------------------------------------
  Widget _paymentCard() {
    // Affichée seulement si l'opérateur a fourni sa propre référence, distincte de celle d'OVANIE.
    final operatorReference = screenText(_p['operator_reference'], '');
    return OrderCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const OrderSectionTitle('Informations de paiement'),
          const SizedBox(height: 10),
          Row(
            children: [
              PayoutOperatorLogo(operator: payoutOperatorKey(_p), size: 46),
              const SizedBox(width: 12),
              Expanded(
                child: Text(
                  screenText(_p['payment_method_label']),
                  style: const TextStyle(color: Mk.navy, fontSize: 16, fontWeight: FontWeight.w800),
                ),
              ),
            ],
          ),
          const SizedBox(height: 6),
          FinanceInfoLine(icon: Icons.call_outlined, label: 'Numéro de téléphone', value: screenText(payoutPhoneLabel(_p['phone']))),
          const Divider(height: 1, color: orderBorder),
          FinanceInfoLine(
            icon: Icons.person_outline_rounded,
            label: 'Nom du bénéficiaire',
            value: screenText(_p['beneficiary_name']),
          ),
          const Divider(height: 1, color: orderBorder),
          if (operatorReference.isNotEmpty) ...[
            FinanceInfoLine(icon: Icons.receipt_long_outlined, label: 'Référence opérateur', value: operatorReference),
            const Divider(height: 1, color: orderBorder),
          ],
          FinanceInfoLine(
            icon: Icons.event_outlined,
            label: 'Date et heure du versement',
            value: _p['paid_at'] == null ? 'Pas encore versé' : orderShortDateTime(_p['paid_at']),
          ),
          const Divider(height: 1, color: orderBorder),
          FinanceInfoLine(
            icon: Icons.verified_user_outlined,
            label: 'Statut',
            trailing: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 170),
              child: PayoutStatusPill(status: _status),
            ),
          ),
        ],
      ),
    );
  }

  // ---- Commandes incluses --------------------------------------------------------------
  Widget _ordersCard(List<Map<String, dynamic>> orders) {
    return OrderCard(
      padding: const EdgeInsets.fromLTRB(14, 12, 6, 6),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Padding(
            padding: const EdgeInsets.only(right: 8),
            child: OrderSectionTitle('Commandes incluses dans ce versement (${orders.length})'),
          ),
          const SizedBox(height: 4),
          if (orders.isEmpty)
            const Padding(
              padding: EdgeInsets.fromLTRB(0, 4, 8, 10),
              child: Text(
                'Aucune commande n’est rattachée à ce versement.',
                style: TextStyle(color: Mk.muted, fontSize: 13.5, fontWeight: FontWeight.w500),
              ),
            ),
          for (var i = 0; i < orders.length && i < 3; i++) ...[
            if (i > 0) const Divider(height: 1, color: orderBorder),
            _orderRow(orders[i], onTap: () => _openOrder(orders[i])),
          ],
          if (orders.length > 3)
            Align(
              alignment: Alignment.centerRight,
              child: TextButton(onPressed: _openOrders, child: Text('Voir les ${orders.length} commandes')),
            ),
        ],
      ),
    );
  }

  Widget _orderRow(Map<String, dynamic> order, {required VoidCallback onTap}) {
    return InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 9),
        child: Row(
          children: [
            const FinanceIconDisc(Icons.inventory_2_outlined, color: financeSlate, size: 38),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    screenText(order['order_number']),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(color: Mk.navy, fontSize: 13.5, fontWeight: FontWeight.w700),
                  ),
                  Text(
                    orderShortDate(order['created_at']),
                    style: const TextStyle(color: Mk.muted, fontSize: 12.5, fontWeight: FontWeight.w500),
                  ),
                ],
              ),
            ),
            if (order['amount'] != null) ...[
              const SizedBox(width: 8),
              ConstrainedBox(constraints: const BoxConstraints(maxWidth: 130), child: FinanceAmount(order['amount'], size: 14)),
            ],
            const Icon(Icons.chevron_right_rounded, color: Mk.icon, size: 24),
          ],
        ),
      ),
    );
  }
}
