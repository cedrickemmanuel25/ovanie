import 'package:flutter/material.dart';
import '../../core/network/api_client.dart';
import '../../data/vendor_repository.dart';
import '../orders/order_ui.dart';
import 'data_ui.dart';
import 'finance_ui.dart';
import 'payouts_screen.dart';
import 'payout_detail_screen.dart';

class FinanceScreen extends StatefulWidget {
  const FinanceScreen({super.key});
  @override
  State<FinanceScreen> createState() => _FinanceScreenState();
}

class _FinanceScreenState extends State<FinanceScreen> {
  Map<String, dynamic> _summary = {};
  List<Map<String, dynamic>> _payouts = [];
  bool _loading = true;
  String? _error;
  int _months = 6;

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
      final data = await VendorRepository.instance.finance();
      if (!mounted) return;
      setState(() {
        _summary = orderMap(data['summary']);
        _payouts = orderList(data['recent_payouts']).map(orderMap).toList();
      });
    } catch (e) {
      if (mounted) setState(() => _error = ApiClient.friendlyError(e));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _list() async {
    await Navigator.push(
      context,
      MaterialPageRoute(builder: (_) => const PayoutsScreen()),
    );
    if (mounted) _load();
  }

  void _detail(Map<String, dynamic> p) {
    final id = int.tryParse('${p['id']}');
    if (id != null) {
      Navigator.push(
        context,
        MaterialPageRoute(builder: (_) => PayoutDetailScreen(payoutId: id)),
      );
    }
  }

  @override
  Widget build(BuildContext context) => VendorDataPage(
    title: 'Finances',
    subtitle: 'Suivez vos revenus et vos performances financières',
    onRefresh: _load,
    loading: _loading,
    error: _error,
    child: _error != null
        ? const SizedBox()
        : Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              // ---- Solde disponible ----------------------------------
              DataCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        const Expanded(
                          child: Row(
                            children: [
                              Text(
                                'Solde disponible',
                                style: TextStyle(
                                  color: orderText,
                                  fontSize: 16,
                                ),
                              ),
                              SizedBox(width: 6),
                              Icon(
                                Icons.info_outline,
                                size: 16,
                                color: orderMuted,
                              ),
                            ],
                          ),
                        ),
                        _statusPill('Actif', orderGreen),
                      ],
                    ),
                    const SizedBox(height: 8),
                    Text(
                      screenMoney(_summary['ready']),
                      style: const TextStyle(
                        color: orderText,
                        fontSize: 25,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    const Text(
                      'Disponible au reversement',
                      style: TextStyle(color: orderMuted, fontSize: 12),
                    ),
                    const Divider(height: 28, color: orderBorder),
                    // La commission OVANIE ne s'affiche pas côté vendeur : elle
                    // est déjà déduite de tous les montants montrés ici.
                    dataMetric(
                      'Ventes du mois',
                      screenMoney(_summary['month_sales']),
                      Icons.bar_chart,
                      color: orderOrange,
                    ),
                  ],
                ),
              ),

              // ---- Grille des 4 indicateurs ---------------------------
              dataMetrics([
                DataCard(
                  child: dataMetric(
                    'Ventes du mois',
                    screenMoney(_summary['month_sales']),
                    Icons.bar_chart,
                    color: orderOrange,
                    valueColor: orderOrange,
                  ),
                ),
                DataCard(
                  child: dataMetric(
                    'Commandes payées',
                    _summary['month_orders_count'],
                    Icons.assignment_outlined,
                    suffix: 'commandes',
                  ),
                ),
                DataCard(
                  child: dataMetric(
                    'En attente',
                    screenMoney(_summary['scheduled']),
                    Icons.schedule,
                    color: orderOrange,
                    valueColor: orderOrange,
                  ),
                ),
                DataCard(
                  child: dataMetric(
                    'Reversements reçus',
                    _summary['month_paid_count'],
                    Icons.account_balance_wallet_outlined,
                    color: orderGreen,
                    suffix: 'ce mois',
                  ),
                ),
              ]),

              // ---- Évolution des revenus ------------------------------
              DataCard(
                title: 'Évolution des revenus',
                trailing: DropdownButton<int>(
                  value: _months,
                  underline: const SizedBox(),
                  items: const [
                    DropdownMenuItem(
                      value: 3,
                      child: Text(
                        '3 derniers mois',
                        style: TextStyle(fontSize: 12),
                      ),
                    ),
                    DropdownMenuItem(
                      value: 6,
                      child: Text(
                        '6 derniers mois',
                        style: TextStyle(fontSize: 12),
                      ),
                    ),
                  ],
                  onChanged: (v) => setState(() => _months = v!),
                ),
                child: _chart(),
              ),

              // ---- Reversement à venir ---------------------------------
              DataCard(
                title: 'Reversement à venir',
                child: Column(
                  children: [
                    const Divider(height: 20, color: orderBorder),
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Expanded(
                          flex: 3,
                          child: _upcomingItem(
                            Icons.assignment_outlined,
                            orderBlue,
                            'Prochaine date',
                            orderDateOnly(_summary['next_payment_at']),
                          ),
                        ),
                        Expanded(
                          flex: 3,
                          child: _upcomingItem(
                            Icons.monetization_on_outlined,
                            orderOrange,
                            'Montant estimé',
                            screenMoney(_summary['next_payout_amount']),
                          ),
                        ),
                        Expanded(
                          flex: 2,
                          child: _upcomingItem(
                            Icons.account_balance_outlined,
                            orderGreen,
                            'Mode',
                            screenText(_summary['next_payout_method']),
                          ),
                        ),
                        InkWell(
                          borderRadius: BorderRadius.circular(9),
                          onTap: _list,
                          child: Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 12,
                              vertical: 12,
                            ),
                            decoration: BoxDecoration(
                              color: orderNavy,
                              borderRadius: BorderRadius.circular(9),
                            ),
                            child: const Row(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                Text(
                                  'Voir les\nreversements',
                                  textAlign: TextAlign.right,
                                  style: TextStyle(
                                    color: Colors.white,
                                    fontSize: 10.5,
                                    fontWeight: FontWeight.w700,
                                    height: 1.2,
                                  ),
                                ),
                                SizedBox(width: 2),
                                Icon(
                                  Icons.chevron_right,
                                  color: Colors.white,
                                  size: 18,
                                ),
                              ],
                            ),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),

              // ---- Dernières opérations ---------------------------------
              DataCard(
                title: 'Dernières opérations',
                trailing: TextButton(
                  onPressed: _list,
                  child: const Text('Voir tout'),
                ),
                child: Column(
                  children: [
                    if (_payouts.isEmpty)
                      const Padding(
                        padding: EdgeInsets.symmetric(vertical: 8),
                        child: Text(
                          'Aucune opération enregistrée.',
                          style: TextStyle(color: orderMuted),
                        ),
                      ),
                    for (var i = 0; i < _payouts.length; i++) ...[
                      _operationRow(_payouts[i]),
                      if (i != _payouts.length - 1)
                        const Divider(height: 1, color: orderBorder),
                    ],
                  ],
                ),
              ),

              // ---- Actions rapides ---------------------------------------
              Row(
                children: [
                  Expanded(
                    child: dataButton('Reversements', Icons.sync_alt, _list),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: dataButton('Historique', Icons.history, _list),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: dataButton(
                      'Télécharger rapport',
                      Icons.download,
                      () => exportData(context, 'rapport-finances.csv', [
                        ['Indicateur', 'Montant FCFA'],
                        ['Solde disponible', _summary['ready']],
                        ['Ventes du mois', _summary['month_sales']],
                        ['Commissions du mois', _summary['month_commission']],
                        ['Mois', 'Ventes', 'Commissions'],
                        ...orderList(_summary['monthly_revenue']).map((raw) {
                          final r = orderMap(raw);
                          return [r['month'], r['sales'], r['commission']];
                        }),
                      ]),
                    ),
                  ),
                ],
              ),
            ],
          ),
  );

  Widget _statusPill(String label, Color color) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 5),
    decoration: BoxDecoration(
      color: color.withValues(alpha: .11),
      borderRadius: BorderRadius.circular(99),
    ),
    child: Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(Icons.circle, size: 8, color: color),
        const SizedBox(width: 6),
        Text(
          label,
          style: TextStyle(
            color: color,
            fontSize: 12,
            fontWeight: FontWeight.w700,
          ),
        ),
      ],
    ),
  );

  Widget _upcomingItem(
    IconData icon,
    Color color,
    String label,
    String value,
  ) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Container(
        width: 30,
        height: 30,
        decoration: BoxDecoration(
          color: color.withValues(alpha: .11),
          borderRadius: BorderRadius.circular(8),
        ),
        child: Icon(icon, size: 17, color: color),
      ),
      const SizedBox(height: 8),
      Text(label, style: const TextStyle(color: orderMuted, fontSize: 10)),
      const SizedBox(height: 3),
      Text(
        value,
        maxLines: 2,
        style: const TextStyle(
          color: orderText,
          fontSize: 12,
          fontWeight: FontWeight.w700,
        ),
      ),
    ],
  );

  Widget _operationRow(Map<String, dynamic> p) {
    final color = payoutStatusColor(p['status']);
    return InkWell(
      onTap: () => _detail(p),
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 10),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: 36,
              height: 36,
              alignment: Alignment.center,
              decoration: BoxDecoration(
                color: color.withValues(alpha: .11),
                shape: BoxShape.circle,
              ),
              child: Icon(
                Icons.account_balance_wallet_outlined,
                color: color,
                size: 18,
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    screenText(p['reference']),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: orderText,
                      fontSize: 13,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(Icons.circle, size: 6, color: color),
                      const SizedBox(width: 5),
                      Text(
                        payoutStatusLabel(p['status']),
                        style: TextStyle(color: color, fontSize: 11),
                      ),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(width: 8),
            Column(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                Text(
                  screenMoney(p['amount']),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: color,
                    fontSize: 13,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  _relativeDate(
                    p['paid_at'] ?? p['scheduled_for'] ?? p['created_at'],
                  ),
                  style: const TextStyle(color: orderMuted, fontSize: 10),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  String _relativeDate(Object? raw) {
    final value = DateTime.tryParse('${raw ?? ''}');
    if (value == null) return '—';
    final local = value.toLocal();
    final now = DateTime.now();
    final today = DateTime(now.year, now.month, now.day);
    final day = DateTime(local.year, local.month, local.day);
    final diff = today.difference(day).inDays;
    if (diff == 0) return "Aujourd'hui";
    if (diff == 1) return 'Hier';
    return orderDateOnly(raw);
  }

  Widget _chart() {
    final all = orderList(_summary['monthly_revenue']).map(orderMap).toList();
    final rows = all
        .skip(all.length > _months ? all.length - _months : 0)
        .toList();
    if (rows.isEmpty) {
      return const Padding(
        padding: EdgeInsets.all(20),
        child: Text('Aucune donnée mensuelle disponible.'),
      );
    }
    final max = rows.fold<double>(0, (v, r) {
      final n = double.tryParse('${r['sales']}') ?? 0;
      return n > v ? n : v;
    });
    final niceMax = max <= 0
        ? 1000000.0
        : (((max * 1.08) / 1000000).ceilToDouble()) * 1000000;
    final barAreaHeight = 150.0;
    return Column(
      children: [
        SizedBox(
          height: barAreaHeight + 34,
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              SizedBox(
                width: 34,
                height: barAreaHeight,
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: [
                    for (var i = 4; i >= 0; i--)
                      Text(
                        i == 0
                            ? '0'
                            : '${(niceMax / 4 * i / 1000000).toStringAsFixed(1).replaceAll('.', ',')} M',
                        style: const TextStyle(
                          fontSize: 9,
                          color: orderMuted,
                        ),
                      ),
                  ],
                ),
              ),
              const SizedBox(width: 6),
              Expanded(
                child: SizedBox(
                  height: barAreaHeight + 34,
                  child: Stack(
                    children: [
                      SizedBox(
                        height: barAreaHeight,
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            for (var i = 4; i >= 0; i--)
                              Container(
                                height: 1,
                                color: const Color(0xFFEEF2F8),
                              ),
                          ],
                        ),
                      ),
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.end,
                        children: [
                          for (var i = 0; i < rows.length; i++)
                            Expanded(
                              child: Padding(
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 5,
                                ),
                                child: Column(
                                  mainAxisAlignment: MainAxisAlignment.end,
                                  children: [
                                    Text(
                                      _compact(rows[i]['sales']),
                                      style: const TextStyle(
                                        fontSize: 10,
                                        color: orderText,
                                      ),
                                    ),
                                    const SizedBox(height: 5),
                                    Container(
                                      height: niceMax <= 0
                                          ? 1
                                          : ((double.tryParse(
                                                        '${rows[i]['sales']}',
                                                      ) ??
                                                      0) /
                                                  niceMax *
                                                  barAreaHeight)
                                              .clamp(1, barAreaHeight),
                                      width: 32,
                                      decoration: BoxDecoration(
                                        color: i == rows.length - 1
                                            ? orderBlue
                                            : const Color(0xFF8CB4FF),
                                        borderRadius:
                                            const BorderRadius.vertical(
                                              top: Radius.circular(4),
                                            ),
                                      ),
                                    ),
                                    const SizedBox(height: 6),
                                    Text(
                                      _monthLabel(rows[i]['month']),
                                      style: const TextStyle(
                                        fontSize: 10,
                                        color: orderMuted,
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            ),
                        ],
                      ),
                    ],
                  ),
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 8),
        const Text(
          'Base payable au vendeur, par mois de création des reversements.',
          style: TextStyle(fontSize: 10, color: orderMuted),
        ),
      ],
    );
  }

  String _monthLabel(Object? raw) {
    final date = DateTime.tryParse('$raw-01');
    if (date == null) return screenText(raw);
    return [
      'Janv.',
      'Févr.',
      'Mars',
      'Avr.',
      'Mai',
      'Juin',
      'Juil.',
      'Août',
      'Sept.',
      'Oct.',
      'Nov.',
      'Déc.',
    ][date.month - 1];
  }

  String _compact(Object? v) {
    final n = double.tryParse('$v') ?? 0;
    return n >= 1000000
        ? '${(n / 1000000).toStringAsFixed(2).replaceAll('.', ',')} M'
        : n >= 1000
        ? '${(n / 1000).toStringAsFixed(0)} k'
        : '$n';
  }
}