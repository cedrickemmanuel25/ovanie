import 'package:flutter/material.dart';

import '../../core/network/api_client.dart';
import '../../core/ui/vendor_mockup_ui.dart';
import '../../data/vendor_repository.dart';
import '../orders/order_ui.dart';
import '../orders/order_widgets.dart';
import '../shell/vendor_tabs.dart';
import 'data_ui.dart';
import 'finance_ui.dart';
import 'payout_detail_screen.dart';
import 'payout_settings_screen.dart';

/// Historique des versements du vendeur : indicateurs, recherche, filtres par
/// statut, tri par date et accès au détail.
class PayoutsScreen extends StatefulWidget {
  const PayoutsScreen({super.key, this.initialStatus = 'all'});

  /// Statut affiché à l'ouverture (« paid » depuis le bouton Historique).
  final String initialStatus;

  @override
  State<PayoutsScreen> createState() => _PayoutsScreenState();
}

class _PayoutsScreenState extends State<PayoutsScreen> {
  final _search = TextEditingController();
  List<Map<String, dynamic>> _items = [];
  Map<String, dynamic> _summary = {};
  bool _loading = true, _loaded = false, _oldest = false;
  String? _error;
  late String _status = widget.initialStatus;

  static const _filters = {
    'all': 'Tous',
    'approved': 'En attente',
    'pending': 'Planifiés',
    'paid': 'Effectués',
    'failed': 'Échoués',
  };
  static const _moreFilters = {
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
        _loaded = true;
      });
    } catch (e) {
      if (mounted) setState(() => _error = ApiClient.friendlyError(e));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Object? _date(Map<String, dynamic> p) => p['paid_at'] ?? p['scheduled_for'] ?? p['expected_payment_date'] ?? p['created_at'];

  List<Map<String, dynamic>> get _visible {
    final q = _search.text.trim().toLowerCase();
    final rows = _items
        .where(
          (p) =>
              (_status == 'all' || p['status'] == _status) &&
              [p['reference'], p['payment_method_label'], p['amount'], p['beneficiary_name']].join(' ').toLowerCase().contains(q),
        )
        .toList();
    rows.sort((a, b) => '${_date(a)}'.compareTo('${_date(b)}') * (_oldest ? 1 : -1));
    return rows;
  }

  void _open(Map<String, dynamic> p) {
    final id = int.tryParse('${p['id']}');
    if (id == null) return;
    Navigator.push(context, MaterialPageRoute<void>(builder: (_) => PayoutDetailScreen(payoutId: id))).then((_) {
      if (mounted) _load();
    });
  }

  Future<void> _settings() async {
    final changed = await Navigator.of(context).push<bool>(
      MaterialPageRoute(builder: (_) => const PayoutSettingsScreen()),
    );
    if (changed == true && mounted) await _load();
  }

  void _statement() => exportData(context, 'releve-versements.csv', [
        ['Référence', 'Date', 'Montant FCFA', 'Statut', 'Méthode'],
        ..._visible.map((p) => [
              p['reference'],
              orderShortDate(_date(p)),
              p['amount'],
              payoutStatusLabel(p['status']),
              p['payment_method_label'],
            ]),
      ]);

  @override
  Widget build(BuildContext context) {
    final visible = _visible;
    final paidCount = int.tryParse('${_summary['month_paid_count'] ?? 0}') ?? 0;
    final next = orderShortDate(_summary['next_payment_at']);
    return OrderPage(
      tab: VendorTab.finance,
      title: 'Versements',
      subtitle: 'Suivez l’historique et le statut de vos reversements vendeur',
      loading: _loading,
      error: _error,
      hasContent: _loaded,
      emptyMessage: 'Vos versements sont indisponibles pour le moment.',
      onRefresh: _load,
      children: [
        _stats([
          _stat(Icons.trending_up_rounded, financeGreen, 'Total reversé', amount: _summary['paid']),
          _stat(Icons.schedule_rounded, Mk.orange, 'En attente', amount: _summary['scheduled']),
          _stat(
            Icons.calendar_today_outlined,
            const Color(0xFF1668E3),
            'Ce mois',
            value: '$paidCount',
            unit: paidCount > 1 ? 'versements' : 'versement',
          ),
          _stat(Icons.event_available_outlined, financePurple, 'Prochain versement', value: next.isEmpty ? '—' : next, small: true),
        ]),
        Row(
          children: [
            Expanded(
              child: TextField(
                controller: _search,
                onChanged: (_) => setState(() {}),
                textInputAction: TextInputAction.search,
                style: const TextStyle(color: Mk.navy, fontSize: 14.5, fontWeight: FontWeight.w500),
                decoration: InputDecoration(
                  hintText: 'Rechercher un versement…',
                  hintStyle: const TextStyle(color: Mk.hint, fontSize: 14, fontWeight: FontWeight.w400),
                  prefixIcon: const Icon(Icons.search_rounded, color: Mk.icon, size: 22),
                  filled: true,
                  fillColor: Colors.white,
                  isDense: true,
                  contentPadding: const EdgeInsets.symmetric(vertical: 13),
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: orderBorder)),
                  enabledBorder:
                      OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: orderBorder)),
                  focusedBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                    borderSide: const BorderSide(color: Mk.blue, width: 1.4),
                  ),
                ),
              ),
            ),
            const SizedBox(width: 8),
            Material(
              color: Colors.white,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(12),
                side: BorderSide(color: _moreFilters.containsKey(_status) || _oldest ? Mk.orange : Mk.border, width: 1.2),
              ),
              clipBehavior: Clip.antiAlias,
              child: InkWell(
                onTap: _openFilterSheet,
                child: Container(
                  height: 46,
                  padding: const EdgeInsets.symmetric(horizontal: 10),
                  child: const Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(Icons.tune_rounded, size: 20, color: Mk.navy),
                      SizedBox(width: 6),
                      Text('Filtrer / Trier', style: TextStyle(fontSize: 13, color: Mk.navy, fontWeight: FontWeight.w600)),
                      Icon(Icons.keyboard_arrow_down_rounded, size: 20, color: Mk.navy),
                    ],
                  ),
                ),
              ),
            ),
          ],
        ),
        SizedBox(
          height: 40,
          child: ListView.separated(
            scrollDirection: Axis.horizontal,
            itemCount: _filters.length + (_moreFilters.containsKey(_status) ? 1 : 0),
            separatorBuilder: (_, __) => const SizedBox(width: 8),
            itemBuilder: (context, i) {
              // Un statut choisi dans la feuille s'ajoute en fin de rangée pour rester visible.
              final entry = i < _filters.length
                  ? _filters.entries.elementAt(i)
                  : MapEntry(_status, _moreFilters[_status]!);
              return _chip(entry.value, _status == entry.key, () => setState(() => _status = entry.key));
            },
          ),
        ),
        const Padding(
          padding: EdgeInsets.fromLTRB(4, 4, 4, 0),
          child: OrderCaption('Historique des versements'),
        ),
        if (visible.isEmpty)
          OrderCard(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 26),
            child: Column(
              children: [
                const Icon(Icons.account_balance_wallet_outlined, color: Mk.hint, size: 42),
                const SizedBox(height: 10),
                Text(
                  _items.isEmpty ? 'Aucun versement pour le moment' : 'Aucun versement ne correspond',
                  textAlign: TextAlign.center,
                  style: const TextStyle(color: Mk.navy, fontSize: 15.5, fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 4),
                Text(
                  _items.isEmpty
                      ? 'Vos reversements apparaîtront ici après vos premières ventes livrées.'
                      : 'Modifiez la recherche ou le filtre pour voir d’autres versements.',
                  textAlign: TextAlign.center,
                  style: const TextStyle(color: Mk.muted, fontSize: 13, height: 1.4, fontWeight: FontWeight.w500),
                ),
              ],
            ),
          ),
        for (final p in visible) _payoutCard(p),
        const MkInfoBox(
          icon: Icons.info_rounded,
          child: Text('Les reversements sont effectués après validation des commandes livrées, selon la fréquence configurée.'),
        ),
        Row(
          children: [
            Expanded(
              child: FinanceActionButton(
                label: 'Télécharger relevé',
                icon: Icons.file_download_outlined,
                onTap: visible.isEmpty ? null : _statement,
              ),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: FinanceActionButton(
                label: 'Paramètres paiement',
                icon: Icons.settings_outlined,
                iconColor: Mk.icon,
                onTap: _settings,
              ),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: FinanceActionButton(
                label: 'Voir l’historique',
                icon: Icons.history_rounded,
                onTap: () => setState(() {
                  _status = 'all';
                  _oldest = false;
                  _search.clear();
                }),
              ),
            ),
          ],
        ),
      ],
    );
  }

  // ---- Indicateurs ------------------------------------------------------------
  Widget _stats(List<Widget> cells) => LayoutBuilder(
        builder: (context, box) {
          // Quatre cartes côte à côte lorsque la largeur le permet, sinon deux par ligne.
          final perRow = box.maxWidth >= 430 ? 4 : 2;
          final width = (box.maxWidth - 8 * (perRow - 1)) / perRow;
          return Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [for (final cell in cells) SizedBox(width: width, child: cell)],
          );
        },
      );

  Widget _stat(IconData icon, Color color, String label, {Object? amount, String? value, String? unit, bool small = false}) {
    return OrderCard(
      padding: const EdgeInsets.fromLTRB(10, 10, 10, 10),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          FinanceIconDisc(icon, color: color, size: 36),
          const SizedBox(height: 8),
          Text(
            label,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(color: Mk.muted, fontSize: 12, fontWeight: FontWeight.w500),
          ),
          const SizedBox(height: 2),
          FittedBox(
            fit: BoxFit.scaleDown,
            alignment: Alignment.centerLeft,
            child: Text(
              value ?? orderMoney(amount).replaceAll(RegExp(r'\s*FCFA$'), ''),
              maxLines: 1,
              style: TextStyle(color: Mk.navy, fontSize: small ? 15.5 : 17, fontWeight: FontWeight.w800),
            ),
          ),
          Text(
            value == null ? 'FCFA' : (unit ?? ''),
            style: const TextStyle(color: Mk.muted, fontSize: 11.5, fontWeight: FontWeight.w500),
          ),
        ],
      ),
    );
  }

  Widget _chip(String label, bool selected, VoidCallback onTap) => Semantics(
        button: true,
        selected: selected,
        child: Material(
          color: selected ? Mk.navy : Colors.white,
          shape: StadiumBorder(side: BorderSide(color: selected ? Mk.navy : orderBorder, width: 1.2)),
          clipBehavior: Clip.antiAlias,
          child: InkWell(
            onTap: onTap,
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: Center(
                widthFactor: 1,
                child: Text(
                  label,
                  style: TextStyle(
                    color: selected ? Colors.white : Mk.icon,
                    fontSize: 13.5,
                    fontWeight: selected ? FontWeight.w700 : FontWeight.w500,
                  ),
                ),
              ),
            ),
          ),
        ),
      );

  // ---- Carte d'un versement -----------------------------------------------------
  Widget _payoutCard(Map<String, dynamic> p) {
    final holder = screenText(p['beneficiary_name'], '');
    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(14),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: () => _open(p),
        child: Padding(
          padding: const EdgeInsets.fromLTRB(12, 12, 4, 12),
          child: Row(
            children: [
              PayoutOperatorLogo(operator: payoutOperatorKey(p), size: 48),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // Les références réelles sont longues : elles tiennent sur une ligne en rétrécissant.
                    FittedBox(
                      fit: BoxFit.scaleDown,
                      alignment: Alignment.centerLeft,
                      child: Text(
                        screenText(p['reference']),
                        maxLines: 1,
                        style: const TextStyle(color: Mk.navy, fontSize: 13.5, fontWeight: FontWeight.w800),
                      ),
                    ),
                    const SizedBox(height: 5),
                    Row(
                      children: [
                        Expanded(child: _iconLine(Icons.calendar_today_outlined, orderShortDate(_date(p)))),
                        const SizedBox(width: 8),
                        ConstrainedBox(
                          constraints: const BoxConstraints(maxWidth: 130),
                          child: FinanceAmount(p['amount'], size: 16),
                        ),
                      ],
                    ),
                    const SizedBox(height: 3),
                    _iconLine(Icons.credit_card_outlined, screenText(p['payment_method_label'])),
                    if (holder.isNotEmpty) ...[
                      const SizedBox(height: 3),
                      Text(
                        'Titulaire : $holder',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(color: Mk.muted, fontSize: 12.5, fontWeight: FontWeight.w500),
                      ),
                    ],
                    const SizedBox(height: 7),
                    PayoutStatusPill(status: p['status']),
                  ],
                ),
              ),
              const Icon(Icons.chevron_right_rounded, color: Mk.icon, size: 24),
            ],
          ),
        ),
      ),
    );
  }

  Widget _iconLine(IconData icon, String text) => Row(
        children: [
          Icon(icon, size: 15, color: Mk.icon),
          const SizedBox(width: 6),
          Flexible(
            child: Text(
              text.isEmpty ? '—' : text,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(color: Mk.muted, fontSize: 12.5, fontWeight: FontWeight.w500),
            ),
          ),
        ],
      );

  // ---- Feuille Filtrer / Trier ----------------------------------------------------
  Future<void> _openFilterSheet() => showModalBottomSheet<void>(
        context: context,
        isScrollControlled: true,
        backgroundColor: Colors.white,
        shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(22))),
        builder: (context) => StatefulBuilder(
          builder: (context, setSheet) {
            void sort(bool oldest) {
              setState(() => _oldest = oldest);
              setSheet(() {});
            }

            return FinanceSheet(
              children: [
                const Text('Trier par date', style: TextStyle(color: Mk.navy, fontSize: 18, fontWeight: FontWeight.w800)),
                const SizedBox(height: 12),
                FinanceRadioRow(label: 'Les plus récents', selected: !_oldest, onTap: () => sort(false)),
                const SizedBox(height: 8),
                FinanceRadioRow(label: 'Les plus anciens', selected: _oldest, onTap: () => sort(true)),
                const Divider(height: 30, color: orderBorder),
                const Text('Autres statuts', style: TextStyle(color: Mk.navy, fontSize: 18, fontWeight: FontWeight.w800)),
                const SizedBox(height: 12),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    for (final f in _moreFilters.entries)
                      SizedBox(
                        height: 40,
                        child: _chip(f.value, _status == f.key, () {
                          // Un second appui retire le filtre.
                          setState(() => _status = _status == f.key ? 'all' : f.key);
                          setSheet(() {});
                        }),
                      ),
                  ],
                ),
                const SizedBox(height: 18),
                MkBarButton(label: 'Afficher les versements', filled: true, onTap: () => Navigator.pop(context)),
              ],
            );
          },
        ),
      );
}
