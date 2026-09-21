import 'package:flutter/material.dart';
import '../../core/network/api_client.dart';
import '../../data/vendor_repository.dart';
import '../orders/order_ui.dart';
import '../shell/vendor_tabs.dart';
import 'data_ui.dart';
import 'finance_ui.dart';
import 'payout_detail_screen.dart';

class PayoutsScreen extends StatefulWidget {
  const PayoutsScreen({super.key});
  @override
  State<PayoutsScreen> createState() => _PayoutsScreenState();
}

class _PayoutsScreenState extends State<PayoutsScreen> {
  final _search = TextEditingController();
  List<Map<String, dynamic>> _items = [];
  Map<String, dynamic> _summary = {};
  bool _loading = true, _oldest = false;
  String? _error;
  String _status = 'all';

  static const filters = {
    'all': 'Tous',
    'approved': 'En attente',
    'pending': 'Planifiés',
    'paid': 'Effectués',
    'failed': 'Échoués',
  };
  static const moreFilters = {
    'processing': 'En traitement',
    'blocked': 'En attente de livraison',
    'waiting_payment': 'Attente paiement',
    'waiting_reception': 'Attente réception',
    'cancelled': 'Annulés',
  };

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    if (!mounted) return;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final finance = await VendorRepository.instance.finance();
      final rows = <Map<String, dynamic>>[];
      var page = 1, last = 1;
      do {
        final data = await VendorRepository.instance.payouts(page: page);
        rows.addAll(orderList(data['data']).map(orderMap));
        last = int.tryParse('${orderMap(data['meta'])['last_page'] ?? 1}') ?? 1;
        page++;
      } while (page <= last && mounted);
      if (!mounted) return;
      setState(() {
        _items = rows;
        _summary = orderMap(finance['summary']);
      });
    } catch (e) {
      if (mounted) setState(() => _error = ApiClient.friendlyError(e));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  List<Map<String, dynamic>> get _visible {
    final q = _search.text.trim().toLowerCase();
    final rows = _items
        .where(
          (p) =>
              (_status == 'all' || p['status'] == _status) &&
              [
                p['reference'],
                p['payment_method_label'],
                p['amount'],
                p['beneficiary_name'],
              ].join(' ').toLowerCase().contains(q),
        )
        .toList();
    rows.sort(
      (a, b) =>
          '${a['created_at']}'.compareTo('${b['created_at']}') * (_oldest ? 1 : -1),
    );
    return rows;
  }

  void _open(Map<String, dynamic> p) {
    final id = int.tryParse('${p['id']}');
    if (id != null) {
      Navigator.push(
        context,
        MaterialPageRoute(builder: (_) => PayoutDetailScreen(payoutId: id)),
      ).then((_) {
        if (mounted) _load();
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      bottomNavigationBar: const VendorBottomBar(selected: VendorTab.finance),
      body: RefreshIndicator(
        onRefresh: _load,
        child: CustomScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          slivers: [
            SliverToBoxAdapter(
              child: OrderHeader(
                showBack: true,
                title: 'Versements',
                subtitle: 'Suivez l’historique et le statut de vos reversements vendeur',
                bottom: FinanceHeaderStats(
                  items: [
                    (Icons.trending_up, orderGreen, 'Total reversé', screenMoney(_summary['paid'])),
                    (Icons.schedule, orderOrange, 'En attente', screenMoney(_summary['scheduled'])),
                    (Icons.calendar_today_outlined, orderBlue, 'Ce mois', '${_summary['month_paid_count'] ?? 0}\nversements'),
                    (Icons.event, financePurple, 'Prochain versement', orderDateOnly(_summary['next_payment_at'])),
                  ],
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
                            Text(_error!, style: const TextStyle(color: Color(0xFFB42318))),
                            const SizedBox(height: 12),
                          ],

                          // ---- Recherche + Filtrer/Trier -------------------
                          Row(
                            children: [
                              Expanded(
                                child: TextField(
                                  controller: _search,
                                  onChanged: (_) => setState(() {}),
                                  decoration: InputDecoration(
                                    hintText: 'Rechercher un versement…',
                                    hintStyle: const TextStyle(fontSize: 13, color: orderMuted),
                                    prefixIcon: const Icon(Icons.search, color: orderMuted, size: 20),
                                    filled: true,
                                    fillColor: Colors.white,
                                    contentPadding: const EdgeInsets.symmetric(vertical: 12),
                                    border: OutlineInputBorder(
                                      borderRadius: BorderRadius.circular(12),
                                      borderSide: const BorderSide(color: orderBorder),
                                    ),
                                    enabledBorder: OutlineInputBorder(
                                      borderRadius: BorderRadius.circular(12),
                                      borderSide: const BorderSide(color: orderBorder),
                                    ),
                                  ),
                                ),
                              ),
                              const SizedBox(width: 10),
                              InkWell(
                                borderRadius: BorderRadius.circular(12),
                                onTap: _openFilterSheet,
                                child: Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
                                  decoration: BoxDecoration(
                                    border: Border.all(color: orderBorder),
                                    borderRadius: BorderRadius.circular(12),
                                  ),
                                  child: const Row(
                                    mainAxisSize: MainAxisSize.min,
                                    children: [
                                      Icon(Icons.tune, size: 17, color: orderText),
                                      SizedBox(width: 6),
                                      Text('Filtrer / Trier', style: TextStyle(fontSize: 12.5, color: orderText, fontWeight: FontWeight.w700)),
                                      SizedBox(width: 4),
                                      Icon(Icons.keyboard_arrow_down, size: 18, color: orderText),
                                    ],
                                  ),
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 14),

                          // ---- Chips de statut ------------------------------
                          SizedBox(
                            height: 40,
                            child: ListView.separated(
                              scrollDirection: Axis.horizontal,
                              itemCount: filters.length,
                              separatorBuilder: (_, __) => const SizedBox(width: 8),
                              itemBuilder: (context, i) {
                                final f = filters.entries.elementAt(i);
                                return PayoutFilterChip(
                                  label: f.value,
                                  selected: _status == f.key,
                                  onTap: () => setState(() => _status = f.key),
                                );
                              },
                            ),
                          ),
                          const SizedBox(height: 18),

                          // ---- Historique ------------------------------------
                          const OrderSectionTitle('Historique des versements'),
                          const SizedBox(height: 12),

                          if (_visible.isEmpty)
                            const Padding(
                              padding: EdgeInsets.symmetric(vertical: 24),
                              child: Center(
                                child: Text(
                                  'Aucun versement ne correspond à votre recherche.',
                                  style: TextStyle(color: orderMuted),
                                ),
                              ),
                            ),
                          for (final p in _visible) ...[
                            _payoutCard(p),
                            const SizedBox(height: 12),
                          ],

                          // ---- Bandeau info ------------------------------------
                          const SizedBox(height: 6),
                          Container(
                            padding: const EdgeInsets.all(14),
                            decoration: BoxDecoration(
                              color: orderSoftBlue,
                              borderRadius: BorderRadius.circular(12),
                              border: Border.all(color: const Color(0xFFD8E7FF)),
                            ),
                            child: const Row(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Icon(Icons.info_outline_rounded, color: orderBlue, size: 18),
                                SizedBox(width: 10),
                                Expanded(
                                  child: Text(
                                    'Les reversements sont effectués après validation des commandes livrées, selon la fréquence configurée.',
                                    style: TextStyle(color: Color(0xFF16458D), fontSize: 12.5, height: 1.35),
                                  ),
                                ),
                              ],
                            ),
                          ),
                          const SizedBox(height: 18),

                          // ---- Actions du bas ---------------------------------
                          Row(
                            children: [
                              Expanded(
                                child: dataButton(
                                  'Télécharger relevé',
                                  Icons.download,
                                  _items.isEmpty
                                      ? null
                                      : () => exportData(context, 'releve-versements.csv', [
                                            ['Référence', 'Date', 'Montant FCFA', 'Statut', 'Méthode'],
                                            ..._visible.map((p) => [
                                                  p['reference'],
                                                  p['paid_at'] ?? p['scheduled_for'],
                                                  p['amount'],
                                                  p['status_label'],
                                                  p['payment_method_label'],
                                                ]),
                                          ]),
                                ),
                              ),
                              const SizedBox(width: 8),
                              Expanded(
                                child: dataButton('Paramètres paiement', Icons.settings_outlined, _settings),
                              ),
                              const SizedBox(width: 8),
                              Expanded(
                                child: dataButton(
                                  'Voir l’historique',
                                  Icons.history,
                                  () => setState(() {
                                    _status = 'all';
                                    _search.clear();
                                  }),
                                ),
                              ),
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

  Widget _payoutCard(Map<String, dynamic> p) {
    final color = payoutStatusColor(p['status']);
    return InkWell(
      onTap: () => _open(p),
      borderRadius: BorderRadius.circular(16),
      child: OrderCard(
        padding: const EdgeInsets.all(14),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            PayoutMethodIcon(method: p['payment_method'], size: 52, color: color),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    screenText(p['reference']),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(color: orderText, fontSize: 14, fontWeight: FontWeight.w800),
                  ),
                  const SizedBox(height: 5),
                  _iconLine(Icons.calendar_today_outlined, orderDateOnly(p['paid_at'] ?? p['scheduled_for'] ?? p['created_at'])),
                  const SizedBox(height: 3),
                  _iconLine(Icons.account_balance_outlined, screenText(p['payment_method_label'])),
                  if ((p['beneficiary_name'] ?? '').toString().trim().isNotEmpty) ...[
                    const SizedBox(height: 3),
                    Text(
                      'Titulaire : ${p['beneficiary_name']}',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(color: orderMuted, fontSize: 11.5),
                    ),
                  ],
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
                  style: const TextStyle(color: orderText, fontSize: 15.5, fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 6),
                PayoutStatusPill(status: p['status'], compact: true),
              ],
            ),
            const SizedBox(width: 4),
            const Icon(Icons.chevron_right, color: orderMuted, size: 20),
          ],
        ),
      ),
    );
  }

  Widget _iconLine(IconData icon, String text) => Row(
        children: [
          Icon(icon, size: 12.5, color: orderMuted),
          const SizedBox(width: 5),
          Flexible(
            child: Text(
              text,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(color: orderMuted, fontSize: 11.5),
            ),
          ),
        ],
      );

  Future<void> _openFilterSheet() => showModalBottomSheet<void>(
        context: context,
        builder: (context) => StatefulBuilder(
          builder: (context, setSheet) => SafeArea(
            child: Padding(
              padding: const EdgeInsets.all(20),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  screenTitle('Trier par date'),
                  const SizedBox(height: 8),
                  RadioListTile<bool>(
                    contentPadding: EdgeInsets.zero,
                    title: const Text('Les plus récents'),
                    value: false,
                    groupValue: _oldest,
                    onChanged: (v) {
                      setState(() => _oldest = v!);
                      setSheet(() {});
                    },
                  ),
                  RadioListTile<bool>(
                    contentPadding: EdgeInsets.zero,
                    title: const Text('Les plus anciens'),
                    value: true,
                    groupValue: _oldest,
                    onChanged: (v) {
                      setState(() => _oldest = v!);
                      setSheet(() {});
                    },
                  ),
                  const Divider(),
                  screenTitle('Autres statuts'),
                  const SizedBox(height: 8),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      for (final f in moreFilters.entries)
                        ChoiceChip(
                          label: Text(f.value),
                          selected: _status == f.key,
                          selectedColor: const Color(0xFFFFEFE4),
                          onSelected: (_) {
                            setState(() => _status = f.key);
                            setSheet(() {});
                          },
                        ),
                    ],
                  ),
                ],
              ),
            ),
          ),
        ),
      );

  Future<void> _settings() => showModalBottomSheet<void>(
        context: context,
        builder: (context) => SafeArea(
          child: Padding(
            padding: const EdgeInsets.all(20),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                screenTitle('Informations de paiement'),
                const SizedBox(height: 8),
                if (_items.isEmpty)
                  const Text('Aucun moyen de paiement enregistré dans les versements.')
                else ...[
                  dataLine('Méthode du dernier versement', _items.first['payment_method_label']),
                  dataLine('Compte', _items.first['phone']),
                ],
                const SizedBox(height: 8),
                const Text('Pour modifier vos coordonnées de reversement, contactez le support.'),
                dataSupport(context),
              ],
            ),
          ),
        ),
      );
}
