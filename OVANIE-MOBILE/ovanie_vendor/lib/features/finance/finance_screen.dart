import 'package:flutter/material.dart';

import '../../core/network/api_client.dart';
import '../../core/ui/vendor_mockup_ui.dart';
import '../../data/vendor_repository.dart';
import '../notifications/vendor_notifications_screen.dart';
import '../orders/order_ui.dart';
import 'data_ui.dart';
import 'finance_ui.dart';
import 'payout_detail_screen.dart';
import 'payout_settings_screen.dart';
import 'payouts_screen.dart';
import 'wallet_screen.dart';

/// Onglet Finances : solde disponible, indicateurs du mois, évolution des
/// revenus, prochain reversement et dernières opérations.
class FinanceScreen extends StatefulWidget {
  const FinanceScreen({super.key});
  @override
  State<FinanceScreen> createState() => _FinanceScreenState();
}

class _FinanceScreenState extends State<FinanceScreen> {
  Map<String, dynamic> _summary = {};
  List<Map<String, dynamic>> _payouts = [];
  bool _loading = true;
  bool _loaded = false;
  bool? _configured;
  String _modeLabel = '';
  String? _error;
  int _months = 6;
  int _notificationCount = 0;

  static const _monthNames = ['Janv.', 'Févr.', 'Mars', 'Avr.', 'Mai', 'Juin', 'Juil.', 'Août', 'Sept.', 'Oct.', 'Nov.', 'Déc.'];

  @override
  void initState() {
    super.initState();
    _load();
    _loadNotifications();
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
        _configured = data['payout_configured'] is bool ? data['payout_configured'] as bool : null;
        _modeLabel = screenText(data['payment_mode_label'], '');
        _loaded = true;
      });
    } catch (e) {
      if (mounted) setState(() => _error = ApiClient.friendlyError(e));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _loadNotifications() async {
    try {
      final notifications = await VendorRepository.instance.notifications(page: 1);
      final unread = int.tryParse('${notifications['unread_count'] ?? 0}') ?? 0;
      if (mounted) setState(() => _notificationCount = unread);
    } catch (_) {
      // L'écran reste utilisable sans la pastille de la cloche.
    }
  }

  Future<void> _openNotifications() async {
    await Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => const VendorNotificationsScreen()));
    if (mounted) _loadNotifications();
  }

  Future<void> _list([String status = 'all']) async {
    await Navigator.push(context, MaterialPageRoute<void>(builder: (_) => PayoutsScreen(initialStatus: status)));
    if (mounted) _load();
  }

  Future<void> _wallet() async {
    await Navigator.push(context, MaterialPageRoute<void>(builder: (_) => const WalletScreen()));
    if (mounted) _load();
  }

  Future<void> _settings() async {
    await Navigator.push(context, MaterialPageRoute<bool>(builder: (_) => const PayoutSettingsScreen()));
    if (mounted) _load();
  }

  void _detail(Map<String, dynamic> p) {
    final id = int.tryParse('${p['id']}');
    if (id == null) return;
    Navigator.push(context, MaterialPageRoute<void>(builder: (_) => PayoutDetailScreen(payoutId: id))).then((_) {
      if (mounted) _load();
    });
  }

  double _number(Object? raw) => double.tryParse('${raw ?? ''}') ?? 0;

  void _balanceInfo() {
    showModalBottomSheet<void>(
      context: context,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(22))),
      builder: (_) => const FinanceSheet(
        children: [
          Text('Solde disponible', style: TextStyle(color: Mk.navy, fontSize: 18, fontWeight: FontWeight.w800)),
          SizedBox(height: 8),
          Text(
            'C’est le montant de vos ventes livrées et validées, prêt à être reversé sur votre compte Mobile Money. '
            'Les ventes en cours de livraison apparaissent dans « En attente » et rejoignent ce solde après validation.',
            style: TextStyle(color: Mk.muted, fontSize: 14, height: 1.45, fontWeight: FontWeight.w500),
          ),
        ],
      ),
    );
  }

  void _report() => exportData(context, 'rapport-finances.csv', [
        ['Indicateur', 'Montant FCFA'],
        ['Solde disponible', _summary['ready']],
        ['Ventes du mois', _summary['month_sales']],
        ['En attente', _summary['scheduled']],
        ['Mois', 'Revenus FCFA'],
        ...orderList(_summary['monthly_revenue']).map((raw) {
          final r = orderMap(raw);
          return [r['month'], r['sales']];
        }),
      ]);

  @override
  Widget build(BuildContext context) {
    final top = MediaQuery.paddingOf(context).top;
    return MkTheme(
      lightStatusBar: false,
      child: ColoredBox(
        color: orderPage,
        child: Column(
          children: [
            Container(
              color: Colors.white,
              padding: EdgeInsets.fromLTRB(16, top + 8, 6, 8),
              child: Row(
                children: [
                  Image.asset('assets/images/mockups/logo_navy.png', height: 34, semanticLabel: 'OVANIE Vendeur'),
                  const Spacer(),
                  IconButton(
                    tooltip: 'Notifications',
                    onPressed: _openNotifications,
                    icon: Badge(
                      isLabelVisible: _notificationCount > 0,
                      backgroundColor: Mk.orange,
                      label: Text(_notificationCount > 99 ? '99+' : '$_notificationCount'),
                      child: const Icon(Icons.notifications_none_rounded, color: Mk.navy, size: 28),
                    ),
                  ),
                ],
              ),
            ),
            const Divider(height: 1, color: orderBorder),
            if (_loading && _loaded)
              const LinearProgressIndicator(minHeight: 2, color: Mk.orange, backgroundColor: Color(0xFFFFEEE5)),
            Expanded(
              child: RefreshIndicator(
                color: Mk.orange,
                onRefresh: _load,
                child: ListView(
                  physics: const AlwaysScrollableScrollPhysics(),
                  padding: const EdgeInsets.fromLTRB(12, 14, 12, 18),
                  children: [
                    const Padding(
                      padding: EdgeInsets.symmetric(horizontal: 4),
                      child: Text(
                        'Finances',
                        style: TextStyle(color: Mk.navy, fontSize: 24, fontWeight: FontWeight.w800, letterSpacing: -.4),
                      ),
                    ),
                    const Padding(
                      padding: EdgeInsets.fromLTRB(4, 2, 4, 12),
                      child: Text(
                        'Suivez vos revenus et vos performances financières',
                        style: TextStyle(color: Mk.muted, fontSize: 13.5, fontWeight: FontWeight.w500),
                      ),
                    ),
                    if (!_loaded && _loading)
                      const Padding(
                        padding: EdgeInsets.only(top: 120),
                        child: Center(child: CircularProgressIndicator(color: Mk.orange)),
                      )
                    else if (!_loaded) ...[
                      MkNotice(_error ?? 'Vos finances sont indisponibles pour le moment.'),
                      const SizedBox(height: 14),
                      MkBarButton(label: 'Réessayer', filled: true, onTap: _load),
                    ] else ...[
                      if (_error != null) ...[MkNotice(_error!), const SizedBox(height: 10)],
                      _balanceCard(),
                      const SizedBox(height: 10),
                      _metrics(),
                      const SizedBox(height: 10),
                      _chartCard(),
                      const SizedBox(height: 10),
                      _upcomingCard(),
                      const SizedBox(height: 10),
                      _operationsCard(),
                      const SizedBox(height: 12),
                      Row(
                        children: [
                          Expanded(
                            child: FinanceActionButton(label: 'Reversements', icon: Icons.repeat_rounded, onTap: _list),
                          ),
                          const SizedBox(width: 8),
                          Expanded(
                            child: FinanceActionButton(
                              label: 'Historique',
                              icon: Icons.history_rounded,
                              onTap: () => _list('paid'),
                            ),
                          ),
                          const SizedBox(width: 8),
                          Expanded(
                            child: FinanceActionButton(
                              label: 'Télécharger rapport',
                              icon: Icons.file_download_outlined,
                              onTap: _report,
                            ),
                          ),
                        ],
                      ),
                    ],
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  // ---- Solde disponible ------------------------------------------------------
  Widget _balanceCard() {
    final ready = _number(_summary['ready']);
    final configured = _configured ?? true;
    return Semantics(
      button: true,
      label: 'Solde disponible ${orderMoney(_summary['ready'])}. Ouvrir le portefeuille.',
      child: Material(
        borderRadius: BorderRadius.circular(18),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: _wallet,
          child: Ink(
            decoration: const BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.centerLeft,
                end: Alignment.centerRight,
                colors: [Color(0xFF062153), Color(0xFF0A3FA8), Color(0xFF0E5BD6)],
                stops: [0, .55, 1],
              ),
            ),
            child: Stack(
              children: [
                Positioned(
                  right: 0,
                  top: 0,
                  bottom: 0,
                  child: ShaderMask(
                    blendMode: BlendMode.dstIn,
                    shaderCallback: (rect) => const LinearGradient(
                      colors: [Colors.transparent, Colors.white],
                      stops: [0, .22],
                    ).createShader(rect),
                    child: Image.asset(
                      'assets/images/mockups/fin_hero.jpg',
                      fit: BoxFit.cover,
                      alignment: Alignment.centerLeft,
                      excludeFromSemantics: true,
                    ),
                  ),
                ),
                Padding(
                  padding: const EdgeInsets.fromLTRB(16, 16, 12, 18),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          const Text(
                            'Solde disponible',
                            style: TextStyle(color: Colors.white, fontSize: 14, fontWeight: FontWeight.w700),
                          ),
                          InkWell(
                            onTap: _balanceInfo,
                            customBorder: const CircleBorder(),
                            child: const Padding(
                              padding: EdgeInsets.all(8),
                              child: Icon(Icons.info_outline_rounded, color: Colors.white, size: 18, semanticLabel: 'En savoir plus'),
                            ),
                          ),
                          const Spacer(),
                          _statePill(configured),
                        ],
                      ),
                      const SizedBox(height: 6),
                      FractionallySizedBox(
                        widthFactor: .62,
                        alignment: Alignment.centerLeft,
                        child: FinanceAmount(_summary['ready'], size: 34, color: Colors.white, unitColor: Mk.orange),
                      ),
                      const SizedBox(height: 4),
                      FractionallySizedBox(
                        widthFactor: .6,
                        alignment: Alignment.centerLeft,
                        child: Text(
                          ready > 0 ? 'Disponible au reversement' : 'Rien à reverser pour le moment',
                          style: const TextStyle(color: Color(0xFFDCE7FB), fontSize: 13, height: 1.3, fontWeight: FontWeight.w500),
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _statePill(bool configured) {
    final color = configured ? const Color(0xFF12894A) : const Color(0xFFE85400);
    return Material(
      color: color,
      borderRadius: BorderRadius.circular(99),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: configured ? null : _settings,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 5),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(width: 7, height: 7, decoration: const BoxDecoration(color: Colors.white, shape: BoxShape.circle)),
              const SizedBox(width: 6),
              Text(
                configured ? 'Actif' : 'À configurer',
                style: const TextStyle(color: Colors.white, fontSize: 12.5, fontWeight: FontWeight.w700),
              ),
            ],
          ),
        ),
      ),
    );
  }

  // ---- Quatre indicateurs ------------------------------------------------------
  Widget _metrics() {
    final paidAmount = _summary['month_paid_amount'];
    final paidCount = int.tryParse('${_summary['month_paid_count'] ?? 0}') ?? 0;
    final orders = int.tryParse('${_summary['month_orders_count'] ?? 0}') ?? 0;
    final cells = <Widget>[
      _metric(Icons.bar_chart_rounded, const Color(0xFF1668E3), 'Ventes du mois', amount: _summary['month_sales']),
      _metric(
        Icons.description_outlined,
        Mk.orange,
        'Commandes payées',
        count: orders,
        unit: orders > 1 ? 'commandes' : 'commande',
      ),
      _metric(Icons.schedule_rounded, Mk.orange, 'En attente', amount: _summary['scheduled']),
      paidAmount != null
          ? _metric(Icons.account_balance_wallet_rounded, financeGreen, 'Reversements reçus', amount: paidAmount, note: 'ce mois')
          : _metric(
              Icons.account_balance_wallet_rounded,
              financeGreen,
              'Reversements reçus',
              count: paidCount,
              unit: 'ce mois',
            ),
    ];
    return LayoutBuilder(
      builder: (context, box) {
        final width = (box.maxWidth - 8) / 2;
        return Wrap(
          spacing: 8,
          runSpacing: 8,
          children: [for (final cell in cells) SizedBox(width: width, child: cell)],
        );
      },
    );
  }

  Widget _metric(IconData icon, Color color, String label, {Object? amount, int? count, String? unit, String? note}) {
    return OrderCard(
      padding: const EdgeInsets.fromLTRB(10, 12, 10, 12),
      child: Row(
        children: [
          FinanceIconDisc(icon, color: color, size: 40),
          const SizedBox(width: 9),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  label,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(color: Mk.muted, fontSize: 12, fontWeight: FontWeight.w500),
                ),
                const SizedBox(height: 2),
                if (count != null)
                  FittedBox(
                    fit: BoxFit.scaleDown,
                    alignment: Alignment.centerLeft,
                    child: Text.rich(
                      TextSpan(
                        text: '$count',
                        children: [
                          TextSpan(text: ' $unit', style: const TextStyle(fontSize: 12.5, fontWeight: FontWeight.w600)),
                        ],
                      ),
                      style: const TextStyle(color: Mk.navy, fontSize: 18, fontWeight: FontWeight.w800),
                    ),
                  )
                else
                  FinanceAmount(amount, size: 17),
                if (note != null) Text(note, style: const TextStyle(color: Mk.muted, fontSize: 11.5, fontWeight: FontWeight.w500)),
              ],
            ),
          ),
        ],
      ),
    );
  }

  // ---- Évolution des revenus -----------------------------------------------------
  Widget _chartCard() {
    return OrderCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Wrap(
            alignment: WrapAlignment.spaceBetween,
            crossAxisAlignment: WrapCrossAlignment.center,
            spacing: 10,
            runSpacing: 8,
            children: [
              const OrderSectionTitle('Évolution des revenus'),
              Container(
                padding: const EdgeInsets.all(2),
                decoration: BoxDecoration(
                  color: const Color(0xFFF3F6FB),
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(color: orderBorder),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [for (final months in const [3, 6]) _rangeButton(months)],
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          _chart(),
          const SizedBox(height: 10),
          const Text(
            'Base payable au vendeur, par mois de création des reversements.',
            style: TextStyle(color: Mk.muted, fontSize: 12, height: 1.35, fontWeight: FontWeight.w500),
          ),
        ],
      ),
    );
  }

  Widget _rangeButton(int months) {
    final selected = _months == months;
    return Semantics(
      button: true,
      selected: selected,
      child: Material(
        color: selected ? const Color(0xFF0B4FC4) : Colors.transparent,
        borderRadius: BorderRadius.circular(8),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: () => setState(() => _months = months),
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
            child: Text(
              '$months derniers mois',
              style: TextStyle(
                color: selected ? Colors.white : Mk.icon,
                fontSize: 12,
                fontWeight: selected ? FontWeight.w700 : FontWeight.w500,
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _chart() {
    final all = orderList(_summary['monthly_revenue']).map(orderMap).toList();
    final rows = all.skip(all.length > _months ? all.length - _months : 0).toList();
    final values = [for (final row in rows) _number(row['sales'])];
    final max = values.fold<double>(0, (a, b) => b > a ? b : a);
    if (rows.isEmpty || max <= 0) {
      return const Padding(
        padding: EdgeInsets.symmetric(vertical: 26),
        child: Center(
          child: Text(
            'Vos revenus s’afficheront ici après vos premières ventes.',
            textAlign: TextAlign.center,
            style: TextStyle(color: Mk.muted, fontSize: 13.5, fontWeight: FontWeight.w500),
          ),
        ),
      );
    }
    // Échelle arrondie : 4 graduations lisibles au-dessus de la plus haute barre.
    final step = _niceStep(max / 4);
    final top = step * 4;
    const area = 150.0;
    const bubble = 30.0;

    return Semantics(
      label: 'Revenus par mois : ${[for (var i = 0; i < rows.length; i++) '${_monthLabel(rows[i]['month'])} ${orderMoney(values[i])}'].join(', ')}',
      child: ExcludeSemantics(
        child: SizedBox(
          height: area + bubble + 26,
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Padding(
                padding: const EdgeInsets.only(top: bubble - 7),
                child: SizedBox(
                  height: area + 14,
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      for (var i = 4; i >= 0; i--)
                        Text(_axisLabel(step * i), style: const TextStyle(color: Mk.muted, fontSize: 11, fontWeight: FontWeight.w500)),
                    ],
                  ),
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Stack(
                  children: [
                    Positioned(
                      left: 0,
                      right: 0,
                      top: bubble,
                      height: area,
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [for (var i = 0; i < 5; i++) Container(height: 1, color: const Color(0xFFE9EEF6))],
                      ),
                    ),
                    Positioned.fill(
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.end,
                        children: [
                          for (var i = 0; i < rows.length; i++)
                            Expanded(child: _bar(rows[i], values[i], top, area, last: i == rows.length - 1)),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _bar(Map<String, dynamic> row, double value, double top, double area, {required bool last}) {
    final height = value <= 0 ? 2.0 : (value / top * area).clamp(3.0, area);
    return Column(
      mainAxisAlignment: MainAxisAlignment.end,
      children: [
        if (last && value > 0)
          SizedBox(
            height: 28,
            child: OverflowBox(
              minWidth: 0,
              maxWidth: 160,
              alignment: Alignment.bottomCenter,
              child: Container(
              margin: const EdgeInsets.only(bottom: 4),
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
              decoration: BoxDecoration(color: const Color(0xFF0E2A6B), borderRadius: BorderRadius.circular(7)),
              child: Text(
                orderMoney(value),
                maxLines: 1,
                style: const TextStyle(color: Colors.white, fontSize: 11.5, fontWeight: FontWeight.w700),
                ),
              ),
            ),
          ),
        Container(
          height: height,
          margin: const EdgeInsets.symmetric(horizontal: 5),
          constraints: const BoxConstraints(maxWidth: 46),
          decoration: BoxDecoration(
            borderRadius: const BorderRadius.vertical(top: Radius.circular(6)),
            gradient: last
                ? const LinearGradient(
                    begin: Alignment.topCenter,
                    end: Alignment.bottomCenter,
                    colors: [Color(0xFFFF9A3D), Color(0xFFFF6A00)],
                  )
                : null,
            color: last ? null : const Color(0xFFB9D5FB),
          ),
        ),
        SizedBox(
          height: 26,
          child: Center(
            child: Text(
              _monthLabel(row['month']),
              maxLines: 1,
              style: const TextStyle(color: Mk.muted, fontSize: 11.5, fontWeight: FontWeight.w500),
            ),
          ),
        ),
      ],
    );
  }

  double _niceStep(double raw) {
    if (raw <= 0) return 1;
    var magnitude = 1.0;
    while (magnitude * 10 <= raw) {
      magnitude *= 10;
    }
    for (final factor in const [1.0, 2.0, 2.5, 5.0, 10.0]) {
      if (magnitude * factor >= raw) return magnitude * factor;
    }
    return magnitude * 10;
  }

  String _axisLabel(double value) {
    String trim(double v) => v.toStringAsFixed(v == v.roundToDouble() ? 0 : 1).replaceAll('.', ',');
    if (value <= 0) return '0';
    if (value >= 1000000) return '${trim(value / 1000000)} M';
    if (value >= 1000) return '${trim(value / 1000)} k';
    return trim(value);
  }

  String _monthLabel(Object? raw) {
    final date = DateTime.tryParse('$raw-01');
    return date == null ? screenText(raw, '') : _monthNames[date.month - 1];
  }

  // ---- Reversement à venir ----------------------------------------------------------
  Widget _upcomingCard() {
    final next = DateTime.tryParse('${_summary['next_payment_at'] ?? ''}')?.toLocal();
    final method = screenText(_summary['next_payout_method'], '');
    final button = MkBarButton(label: 'Voir les reversements', onTap: _list, height: 44);
    return OrderCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const OrderSectionTitle('Reversement à venir'),
          const SizedBox(height: 12),
          if (next == null) ...[
            const Text(
              'Aucun reversement n’est planifié pour le moment. Le prochain apparaîtra ici dès qu’une vente livrée sera validée.',
              style: TextStyle(color: Mk.muted, fontSize: 13.5, height: 1.4, fontWeight: FontWeight.w500),
            ),
            const SizedBox(height: 12),
            button,
          ] else ...[
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                _calendarTile(next),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      _upcomingLine(Icons.event_outlined, 'Prochaine date', orderShortDate(_summary['next_payment_at'])),
                      _upcomingLine(Icons.payments_outlined, 'Montant estimé', orderMoney(_summary['next_payout_amount'])),
                      if (_modeLabel.isNotEmpty) _upcomingLine(Icons.settings_outlined, 'Mode', _modeLabel),
                      if (method.isNotEmpty) _upcomingLine(Icons.account_balance_wallet_outlined, 'Compte', method),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 10),
            button,
          ],
        ],
      ),
    );
  }

  Widget _calendarTile(DateTime date) => Container(
        width: 74,
        clipBehavior: Clip.antiAlias,
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: orderBorder),
          boxShadow: const [BoxShadow(color: Color(0x14062962), blurRadius: 10, offset: Offset(0, 4))],
        ),
        child: Column(
          children: [
            Container(height: 16, color: Mk.orange),
            const SizedBox(height: 6),
            Text('${date.day}', style: const TextStyle(color: Mk.navy, fontSize: 30, height: 1, fontWeight: FontWeight.w800)),
            const SizedBox(height: 2),
            Text(
              _monthNames[date.month - 1].toUpperCase(),
              style: const TextStyle(color: Mk.navy, fontSize: 11.5, fontWeight: FontWeight.w700, letterSpacing: .8),
            ),
            const SizedBox(height: 8),
          ],
        ),
      );

  Widget _upcomingLine(IconData icon, String label, String value) => Padding(
        padding: const EdgeInsets.only(bottom: 8),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(icon, color: Mk.icon, size: 19),
            const SizedBox(width: 8),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(label, style: const TextStyle(color: Mk.muted, fontSize: 12, fontWeight: FontWeight.w500)),
                  Text(value, style: const TextStyle(color: Mk.navy, fontSize: 13.5, fontWeight: FontWeight.w700)),
                ],
              ),
            ),
          ],
        ),
      );

  // ---- Dernières opérations ------------------------------------------------------------
  Widget _operationsCard() {
    final rows = _payouts.take(3).toList();
    return OrderCard(
      padding: const EdgeInsets.fromLTRB(14, 10, 14, 6),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              const Expanded(child: OrderSectionTitle('Dernières opérations')),
              InkWell(
                onTap: _list,
                borderRadius: BorderRadius.circular(8),
                child: const Padding(
                  padding: EdgeInsets.symmetric(horizontal: 4, vertical: 8),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text('Voir tout', style: TextStyle(color: orderBlue, fontSize: 13.5, fontWeight: FontWeight.w700)),
                      Icon(Icons.chevron_right_rounded, color: orderBlue, size: 20),
                    ],
                  ),
                ),
              ),
            ],
          ),
          if (rows.isEmpty)
            const Padding(
              padding: EdgeInsets.fromLTRB(0, 4, 0, 12),
              child: Text(
                'Aucune opération enregistrée pour le moment.',
                style: TextStyle(color: Mk.muted, fontSize: 13.5, fontWeight: FontWeight.w500),
              ),
            ),
          for (var i = 0; i < rows.length; i++) ...[
            if (i > 0) const Divider(height: 1, color: orderBorder),
            _operationRow(rows[i]),
          ],
        ],
      ),
    );
  }

  Widget _operationRow(Map<String, dynamic> p) {
    return InkWell(
      onTap: () => _detail(p),
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 10),
        child: Row(
          children: [
            const FinanceIconDisc(Icons.account_balance_wallet_rounded, color: financeSlate, size: 40),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          screenText(p['reference']),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(color: Mk.navy, fontSize: 13.5, fontWeight: FontWeight.w800),
                        ),
                      ),
                      const SizedBox(width: 8),
                      ConstrainedBox(
                        constraints: const BoxConstraints(maxWidth: 130),
                        child: FinanceAmount(p['amount'], size: 14.5),
                      ),
                    ],
                  ),
                  const SizedBox(height: 4),
                  Row(
                    children: [
                      Text(
                        orderDayLabel(p['paid_at'] ?? p['scheduled_for'] ?? p['created_at']),
                        style: const TextStyle(color: Mk.muted, fontSize: 12.5, fontWeight: FontWeight.w500),
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Align(alignment: Alignment.centerRight, child: PayoutStatusPill(status: p['status'])),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
