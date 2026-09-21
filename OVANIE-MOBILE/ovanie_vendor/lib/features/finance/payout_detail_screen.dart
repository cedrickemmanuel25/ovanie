import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import '../../core/network/api_client.dart';
import '../../data/vendor_repository.dart';
import '../orders/order_ui.dart';
import '../orders/order_detail_screen.dart';
import 'data_ui.dart';
import 'finance_ui.dart';
import 'payout_followup_screen.dart';

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
        _p = orderMap(data['payout']);
        if (_p.isEmpty) _error = 'Versement indisponible.';
      });
    } catch (e) {
      if (mounted) setState(() => _error = ApiClient.friendlyError(e));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _order() {
    final id = int.tryParse('${orderMap(_p['order'])['id']}');
    if (id != null) {
      Navigator.push(context, MaterialPageRoute(builder: (_) => OrderDetailScreen(orderId: id)));
    }
  }

  @override
  Widget build(BuildContext context) {
    final paid = _p['status'] == 'paid';
    final failed = ['failed', 'blocked', 'cancelled'].contains('${_p['status'] ?? ''}');
    final associated = orderMap(_p['order']);
    final associatedList = orderList(_p['orders']).map(orderMap).toList();
    final orders = associatedList.isNotEmpty
        ? associatedList
        : (associated.isEmpty ? <Map<String, dynamic>>[] : [associated]);

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
                title: 'Détail du versement',
                subtitle: 'Consultez toutes les informations de ce versement.',
                titleTrailing: _p.isEmpty
                    ? null
                    : PayoutHeaderBadge(
                        status: _p['status'],
                        dateLabel: _p['paid_at'] != null ? 'le ${orderDateOnly(_p['paid_at'])}' : null,
                      ),
              ),
            ),
            if (_loading)
              const SliverFillRemaining(
                hasScrollBody: false,
                child: Center(child: CircularProgressIndicator(color: orderOrange)),
              )
            else if (_p.isEmpty)
              SliverFillRemaining(
                hasScrollBody: false,
                child: Center(
                  child: Padding(
                    padding: const EdgeInsets.all(24),
                    child: Text(_error ?? 'Versement indisponible.', style: const TextStyle(color: orderMuted)),
                  ),
                ),
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
                          // ---- Montant + infos ------------------------------
                          OrderCard(
                            padding: const EdgeInsets.all(16),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.stretch,
                              children: [
                                Row(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Container(
                                      width: 58,
                                      height: 58,
                                      alignment: Alignment.center,
                                      decoration: BoxDecoration(
                                        color: orderOrange,
                                        borderRadius: BorderRadius.circular(16),
                                      ),
                                      child: const Icon(Icons.account_balance_outlined, color: Colors.white, size: 28),
                                    ),
                                    const SizedBox(width: 14),
                                    Expanded(
                                      child: Column(
                                        crossAxisAlignment: CrossAxisAlignment.start,
                                        children: [
                                          Text(
                                            paid ? 'Montant versé' : 'Montant du versement',
                                            style: const TextStyle(color: orderMuted, fontSize: 12.5, fontWeight: FontWeight.w700),
                                          ),
                                          const SizedBox(height: 3),
                                          Text(
                                            screenMoney(_p['amount']),
                                            style: const TextStyle(color: orderText, fontSize: 26, fontWeight: FontWeight.w900),
                                          ),
                                          const SizedBox(height: 6),
                                          Row(
                                            children: [
                                              Expanded(
                                                child: Text(
                                                  'Réf. versement : ${screenText(_p['reference'])}',
                                                  maxLines: 1,
                                                  overflow: TextOverflow.ellipsis,
                                                  style: const TextStyle(color: orderMuted, fontSize: 12),
                                                ),
                                              ),
                                              InkWell(
                                                onTap: () => Clipboard.setData(ClipboardData(text: screenText(_p['reference']))),
                                                child: const Padding(
                                                  padding: EdgeInsets.only(left: 6),
                                                  child: Icon(Icons.copy_outlined, size: 16, color: orderBlue),
                                                ),
                                              ),
                                            ],
                                          ),
                                        ],
                                      ),
                                    ),
                                  ],
                                ),
                                const Divider(height: 26, color: orderBorder),
                                _kv(Icons.calendar_today_outlined, 'Date de versement',
                                    _p['paid_at'] == null ? 'Non versé' : orderHumanDate(_p['paid_at'])),
                                const SizedBox(height: 12),
                                _kv(Icons.smartphone_outlined, 'Méthode de paiement', screenText(_p['payment_method_label'])),
                                const SizedBox(height: 12),
                                _kv(Icons.phone_outlined, 'Numéro de compte', screenText(_p['phone'])),
                              ],
                            ),
                          ),
                          const SizedBox(height: 16),

                          // ---- Statut du versement ---------------------------
                          _sectionCard(
                            icon: Icons.assignment_turned_in_outlined,
                            title: 'Statut du versement',
                            background: orderSoftGreen,
                            child: _timeline(failed),
                          ),
                          const SizedBox(height: 16),

                          // ---- Résumé du versement ---------------------------
                          _sectionCard(
                            icon: Icons.description_outlined,
                            title: 'Résumé du versement',
                            child: Column(
                              // La commission OVANIE et les frais de traitement ne
                              // s'affichent plus côté vendeur (déjà déduits du
                              // montant net) : seul le prix des produits vendus et
                              // le net à verser le concernent.
                              children: [
                                dataLine('Total des ventes concernées', _p['total_amount'], money: true),
                                const Divider(color: orderBorder, height: 22),
                                dataLine(paid ? 'Montant net versé' : 'Montant net prévu', _p['amount'], money: true, strong: true),
                              ],
                            ),
                          ),
                          const SizedBox(height: 16),

                          // ---- Informations de paiement -----------------------
                          _sectionCard(
                            icon: Icons.credit_card,
                            title: 'Informations de paiement',
                            child: Column(
                              children: [
                                _payMethodLine(screenText(_p['payment_method_label']), _p['payment_method']),
                                const Divider(color: orderBorder, height: 22),
                                dataLine('Numéro de téléphone', _p['phone']),
                                dataLine('Nom du bénéficiaire', _p['beneficiary_name']),
                                dataLine('Référence opérateur', _p['payout_reference'] ?? _p['reference']),
                                dataLine(
                                  'Date et heure du versement',
                                  _p['paid_at'] == null ? 'Non versé' : orderHumanDate(_p['paid_at']),
                                ),
                                const SizedBox(height: 4),
                                Row(
                                  children: [
                                    const Expanded(child: Text('Statut', style: TextStyle(color: orderMuted, fontSize: 12))),
                                    PayoutStatusPill(status: _p['status'], compact: true),
                                  ],
                                ),
                              ],
                            ),
                          ),
                          const SizedBox(height: 16),

                          // ---- Commandes incluses -----------------------------
                          _sectionCard(
                            icon: Icons.inventory_2_outlined,
                            title: 'Commandes incluses dans ce versement (${orders.length})',
                            trailing: orders.length > 1
                                ? TextButton(
                                    onPressed: _order,
                                    style: TextButton.styleFrom(padding: EdgeInsets.zero, minimumSize: Size.zero),
                                    child: const Row(
                                      mainAxisSize: MainAxisSize.min,
                                      children: [
                                        Text('Voir toutes', style: TextStyle(fontSize: 12.5)),
                                        Icon(Icons.chevron_right, size: 16),
                                      ],
                                    ),
                                  )
                                : null,
                            child: orders.isEmpty
                                ? const Text('Aucune commande associée disponible.', style: TextStyle(color: orderMuted, fontSize: 12.5))
                                : SizedBox(
                                    height: 78,
                                    child: ListView.separated(
                                      scrollDirection: Axis.horizontal,
                                      itemCount: orders.length,
                                      separatorBuilder: (_, __) => const SizedBox(width: 10),
                                      itemBuilder: (context, i) {
                                        final o = orders[i];
                                        return InkWell(
                                          onTap: _order,
                                          borderRadius: BorderRadius.circular(10),
                                          child: Container(
                                            width: 134,
                                            padding: const EdgeInsets.all(10),
                                            decoration: BoxDecoration(
                                              border: Border.all(color: orderBorder),
                                              borderRadius: BorderRadius.circular(10),
                                            ),
                                            child: Column(
                                              crossAxisAlignment: CrossAxisAlignment.start,
                                              children: [
                                                Text(
                                                  screenText(o['order_number']),
                                                  maxLines: 1,
                                                  overflow: TextOverflow.ellipsis,
                                                  style: const TextStyle(color: orderText, fontSize: 11.5, fontWeight: FontWeight.w800),
                                                ),
                                                const SizedBox(height: 4),
                                                Text(
                                                  orderDateOnly(o['created_at']),
                                                  style: const TextStyle(color: orderMuted, fontSize: 10.5),
                                                ),
                                                const Spacer(),
                                                Text(
                                                  screenMoney(o['amount']),
                                                  style: const TextStyle(color: orderText, fontSize: 12.5, fontWeight: FontWeight.w800),
                                                ),
                                              ],
                                            ),
                                          ),
                                        );
                                      },
                                    ),
                                  ),
                          ),
                          const SizedBox(height: 20),

                          // ---- Actions -----------------------------------------
                          Row(
                            children: [
                              Expanded(
                                child: dataButton(
                                  'Télécharger le reçu',
                                  Icons.download,
                                  _p['has_receipt'] == true ? () => downloadReceipt(context, widget.payoutId) : null,
                                ),
                              ),
                              const SizedBox(width: 12),
                              Expanded(
                                child: dataButton(
                                  'Voir les commandes',
                                  Icons.list_alt,
                                  orders.isEmpty ? null : _order,
                                  primary: true,
                                ),
                              ),
                            ],
                          ),
                          if (_p['has_receipt'] != true)
                            const Padding(
                              padding: EdgeInsets.only(top: 8),
                              child: Text(
                                'Le reçu sera disponible lorsqu’il aura été ajouté au versement.',
                                style: TextStyle(color: orderMuted, fontSize: 11),
                              ),
                            ),
                          const SizedBox(height: 10),
                          dataButton(
                            _p['vendor_followup_requested_at'] == null ? 'Demander un suivi' : 'Suivre ma demande',
                            Icons.support_agent,
                            () => Navigator.push(
                              context,
                              MaterialPageRoute(builder: (_) => PayoutFollowUpScreen(payoutId: widget.payoutId)),
                            ).then((_) {
                              if (mounted) _load();
                            }),
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

  Widget _sectionCard({
    required IconData icon,
    required String title,
    required Widget child,
    Color background = Colors.white,
    Widget? trailing,
  }) {
    return OrderCard(
      color: background,
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Icon(icon, color: orderText, size: 20),
              const SizedBox(width: 9),
              Expanded(
                child: Text(
                  title,
                  style: const TextStyle(color: orderText, fontSize: 14.5, fontWeight: FontWeight.w800),
                ),
              ),
              if (trailing != null) trailing,
            ],
          ),
          const SizedBox(height: 14),
          child,
        ],
      ),
    );
  }

  Widget _kv(IconData icon, String label, String value) => Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, size: 16, color: orderMuted),
          const SizedBox(width: 8),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(label, style: const TextStyle(color: orderMuted, fontSize: 11.5)),
                const SizedBox(height: 2),
                Text(value, style: const TextStyle(color: orderText, fontSize: 13.5, fontWeight: FontWeight.w700)),
              ],
            ),
          ),
        ],
      );

  Widget _payMethodLine(String label, Object? method) => Row(
        children: [
          const Expanded(child: Text('Méthode de paiement', style: TextStyle(color: orderMuted, fontSize: 12))),
          PayoutMethodIcon(method: method, size: 22, color: orderOrange),
          const SizedBox(width: 6),
          Text(label, style: const TextStyle(color: orderText, fontSize: 13, fontWeight: FontWeight.w700)),
        ],
      );

  Widget _timeline(bool failed) {
    final steps = [
      ('Versement planifié', _p['scheduled_for']),
      ('En cours de traitement', _p['processing_at']),
      ('Transfert effectué', _p['paid_at']),
      ('Versé', _p['status'] == 'paid' ? _p['paid_at'] : null),
    ];
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        for (var i = 0; i < steps.length; i++)
          Expanded(
            child: Column(
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Container(
                        height: 2,
                        color: i == 0
                            ? Colors.transparent
                            : (steps[i - 1].$2 != null && steps[i].$2 != null ? orderGreen : orderBorder),
                      ),
                    ),
                    Container(
                      width: 26,
                      height: 26,
                      alignment: Alignment.center,
                      decoration: BoxDecoration(
                        color: steps[i].$2 != null ? (failed ? const Color(0xFFD92D20) : orderGreen) : Colors.white,
                        shape: BoxShape.circle,
                        border: Border.all(color: steps[i].$2 != null ? Colors.transparent : orderBorder, width: 1.4),
                      ),
                      child: steps[i].$2 != null
                          ? Icon(failed ? Icons.close : Icons.check, color: Colors.white, size: 15)
                          : null,
                    ),
                    Expanded(
                      child: Container(
                        height: 2,
                        color: i == steps.length - 1
                            ? Colors.transparent
                            : (steps[i].$2 != null && steps[i + 1].$2 != null ? orderGreen : orderBorder),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                Text(
                  steps[i].$1,
                  textAlign: TextAlign.center,
                  maxLines: 2,
                  style: TextStyle(
                    color: steps[i].$2 != null ? orderText : orderMuted,
                    fontSize: 10,
                    fontWeight: FontWeight.w700,
                    height: 1.15,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  steps[i].$2 == null ? 'En attente' : '${orderDateOnly(steps[i].$2)}\n${orderTimeOnly(steps[i].$2)}',
                  textAlign: TextAlign.center,
                  style: const TextStyle(color: orderMuted, fontSize: 9),
                ),
              ],
            ),
          ),
      ],
    );
  }
}
