import 'dart:async';
import '../../core/sync/live_sync_service.dart';

import 'package:flutter/material.dart';

import '../../core/network/api_client.dart';
import '../../core/ui/vendor_mockup_ui.dart';
import '../../data/vendor_repository.dart';
import '../notifications/vendor_notifications_screen.dart';
import 'order_detail_screen.dart';
import 'order_ui.dart';

class OrdersScreen extends StatefulWidget {
  const OrdersScreen({super.key});

  @override
  State<OrdersScreen> createState() => _OrdersScreenState();
}

class _OrdersScreenState extends State<OrdersScreen>
    with LiveSyncListener<OrdersScreen> {
  final TextEditingController _search = TextEditingController();
  final ScrollController _scroll = ScrollController();

  final List<Map<String, dynamic>> _items = <Map<String, dynamic>>[];
  Map<String, dynamic> _stats = <String, dynamic>{};
  Timer? _debounce;
  int _request = 0;
  bool _loading = true;
  bool _loadingMore = false;
  String? _error;
  int _page = 1;
  int _lastPage = 1;
  String _status = 'all';
  String _sort = 'newest';
  int _notificationCount = 0;

  static const Map<String, String> _filters = <String, String>{
    'all': 'Toutes',
    'pending': 'Nouvelles',
    'preparing': 'Préparation',
    'shipped': 'Expédiées',
    'delivered': 'Livrées',
    'cancelled': 'Annulées',
  };

  @override
  void initState() {
    super.initState();
    _scroll.addListener(_onScroll);
    _load(reset: true);
    _loadNotifications();
    listenLiveSync({'shop', 'notifications'}, () {
      if (!_loadingMore) _load();
      _loadNotifications();
    });
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _search.dispose();
    _scroll.dispose();
    super.dispose();
  }

  void _onScroll() {
    if (!_scroll.hasClients || _loadingMore || _loading || _page >= _lastPage) {
      return;
    }
    if (_scroll.position.pixels >= _scroll.position.maxScrollExtent - 520) {
      _loadMore();
    }
  }

  Future<void> _load({bool reset = false}) async {
    final request = ++_request;
    if (reset) {
      setState(() {
        _loading = true;
        _loadingMore = false;
        _error = null;
        _page = 1;
        _lastPage = 1;
        _items.clear();
      });
    }
    try {
      final data = await VendorRepository.instance.orders(
        query: _search.text,
        status: _status,
        page: 1,
        perPage: 20,
        sort: _sort,
      );
      final rows = orderList(data['data']).map((e) => orderMap(e)).toList();
      final meta = orderMap(data['meta']);
      if (!mounted || request != _request) return;
      setState(() {
        _items
          ..clear()
          ..addAll(rows);
        _stats = orderMap(data['stats']);
        _page = int.tryParse('${meta['current_page'] ?? 1}') ?? 1;
        _lastPage = int.tryParse('${meta['last_page'] ?? 1}') ?? 1;
      });
    } catch (e) {
      if (mounted && request == _request) {
        setState(() => _error = ApiClient.friendlyError(e));
      }
    } finally {
      if (mounted && request == _request) setState(() => _loading = false);
    }
  }

  Future<void> _loadMore() async {
    if (_page >= _lastPage || _loadingMore) return;
    final request = _request;
    setState(() => _loadingMore = true);
    try {
      final nextPage = _page + 1;
      final data = await VendorRepository.instance.orders(
        query: _search.text,
        status: _status,
        page: nextPage,
        perPage: 20,
        sort: _sort,
      );
      final rows = orderList(data['data']).map((e) => orderMap(e)).toList();
      final meta = orderMap(data['meta']);
      if (!mounted || request != _request) return;
      setState(() {
        _items.addAll(rows);
        _page = int.tryParse('${meta['current_page'] ?? nextPage}') ?? nextPage;
        _lastPage =
            int.tryParse('${meta['last_page'] ?? _lastPage}') ?? _lastPage;
        if (data['stats'] is Map) _stats = orderMap(data['stats']);
      });
    } catch (_) {
      // Le premier écran reste utilisable même si une page suivante échoue.
    } finally {
      if (mounted && request == _request) setState(() => _loadingMore = false);
    }
  }

  Future<void> _openOrder(Map<String, dynamic> order) async {
    final id = int.tryParse('${order['id'] ?? ''}');
    if (id == null) return;
    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => OrderDetailScreen(orderId: id, initialOrder: order),
      ),
    );
    if (mounted) _load(reset: true);
  }

  /// Feuille de choix unique : lignes encadrées avec pictogramme et coche orange.
  Future<String?> _pickOne(String title, List<_Option> options, String selected) {
    FocusManager.instance.primaryFocus?.unfocus();
    return showModalBottomSheet<String>(
      context: context,
      backgroundColor: Colors.transparent,
      isScrollControlled: true,
      builder: (sheetContext) => MkTheme(
        lightStatusBar: false,
        child: Container(
          constraints: BoxConstraints(maxHeight: MediaQuery.sizeOf(sheetContext).height * .86),
          decoration: const BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.vertical(top: Radius.circular(26)),
          ),
          child: SafeArea(
            top: false,
            child: SingleChildScrollView(
              padding: const EdgeInsets.fromLTRB(16, 10, 16, 14),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Center(
                    child: Container(
                      width: 44,
                      height: 5,
                      decoration: BoxDecoration(color: const Color(0xFFC9CED8), borderRadius: BorderRadius.circular(3)),
                    ),
                  ),
                  const SizedBox(height: 16),
                  Text(title, style: const TextStyle(color: Mk.navy, fontSize: 20, fontWeight: FontWeight.w800)),
                  const SizedBox(height: 12),
                  for (final option in options)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 8),
                      child: _OptionRow(
                        option: option,
                        selected: option.key == selected,
                        onTap: () => Navigator.pop(sheetContext, option.key),
                      ),
                    ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  Future<void> _chooseSort() async {
    final selected = await _pickOne(
      'Trier les commandes',
      const [
        _Option('newest', 'Plus récentes', Icons.schedule_rounded, Mk.orange),
        _Option('oldest', 'Plus anciennes', Icons.history_rounded, Mk.icon),
      ],
      _sort,
    );
    if (selected != null && selected != _sort) {
      setState(() => _sort = selected);
      _load(reset: true);
    }
  }

  Future<void> _chooseFilter() async {
    final selected = await _pickOne(
      'Filtres',
      const [
        _Option('all', 'Toutes', Icons.grid_view_rounded, Mk.icon),
        _Option('pending', 'Nouvelles', Icons.article_outlined, Color(0xFF1668E3)),
        _Option('preparing', 'Préparation', Icons.schedule_rounded, Mk.orange),
        _Option('shipped', 'Expédiées', Icons.local_shipping_outlined, Color(0xFF6D3BD9)),
        _Option('delivered', 'Livrées', Icons.check_circle_outline_rounded, Color(0xFF168A2B)),
        _Option('cancelled', 'Annulées', Icons.cancel_outlined, Color(0xFFD92D20)),
      ],
      _status,
    );
    if (selected != null) _setFilter(selected);
  }

  void _setFilter(String value) {
    if (_status == value) return;
    setState(() => _status = value);
    _load(reset: true);
  }

  Future<void> _openNotifications() async {
    await Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => const VendorNotificationsScreen()));
    if (mounted) _loadNotifications();
  }

  /// Nombre de notifications non lues, pour la pastille de la cloche.
  Future<void> _loadNotifications() async {
    try {
      final notifications = await VendorRepository.instance.notifications(page: 1);
      final unread = int.tryParse('${notifications['unread_count'] ?? 0}') ?? 0;
      if (mounted) setState(() => _notificationCount = unread);
    } catch (_) {
      // La liste des commandes reste utilisable sans la pastille.
    }
  }

  int _stat(String key) => int.tryParse('${_stats[key] ?? 0}') ?? 0;

  @override
  Widget build(BuildContext context) {
    final top = MediaQuery.paddingOf(context).top;
    return MkTheme(
      lightStatusBar: false,
      child: ColoredBox(
        color: const Color(0xFFF5F7FB),
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
            const Divider(height: 1, color: _line),
            Expanded(
              child: RefreshIndicator(
                color: Mk.orange,
                onRefresh: () => _load(reset: true),
                child: CustomScrollView(
                  controller: _scroll,
                  physics: const AlwaysScrollableScrollPhysics(),
                  keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
                  slivers: [
                    SliverToBoxAdapter(child: _intro()),
                    if (_loading)
                      const SliverFillRemaining(
                        hasScrollBody: false,
                        child: Center(child: CircularProgressIndicator(color: Mk.orange)),
                      )
                    else if (_error != null)
                      SliverFillRemaining(
                        hasScrollBody: false,
                        child: Padding(
                          padding: const EdgeInsets.all(20),
                          child: Column(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              MkNotice(_error!),
                              const SizedBox(height: 14),
                              MkBarButton(label: 'Réessayer', filled: true, onTap: () => _load(reset: true)),
                            ],
                          ),
                        ),
                      )
                    else if (_items.isEmpty)
                      SliverFillRemaining(
                        hasScrollBody: false,
                        child: Padding(
                          padding: const EdgeInsets.all(28),
                          child: Column(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              const Icon(Icons.inventory_2_outlined, color: Mk.hint, size: 48),
                              const SizedBox(height: 12),
                              Text(
                                _search.text.trim().isNotEmpty
                                    ? 'Aucune commande ne correspond à votre recherche'
                                    : (_status == 'all' ? 'Aucune commande pour le moment' : 'Aucune commande dans cette liste'),
                                textAlign: TextAlign.center,
                                style: const TextStyle(color: Mk.navy, fontSize: 16.5, fontWeight: FontWeight.w800),
                              ),
                              const SizedBox(height: 6),
                              const Text(
                                'Les nouvelles commandes de vos clients s’affichent ici.',
                                textAlign: TextAlign.center,
                                style: TextStyle(color: Mk.muted, fontSize: 13.5, height: 1.4),
                              ),
                            ],
                          ),
                        ),
                      )
                    else ...[
                      SliverPadding(
                        padding: const EdgeInsets.fromLTRB(12, 0, 12, 12),
                        sliver: SliverList.separated(
                          itemCount: _items.length,
                          separatorBuilder: (_, __) => const SizedBox(height: 10),
                          itemBuilder: (context, index) => _orderCard(_items[index]),
                        ),
                      ),
                      if (_loadingMore)
                        const SliverToBoxAdapter(
                          child: Padding(
                            padding: EdgeInsets.symmetric(vertical: 18),
                            child: Center(child: CircularProgressIndicator(color: Mk.orange, strokeWidth: 2.5)),
                          ),
                        ),
                    ],
                    const SliverToBoxAdapter(child: SizedBox(height: 16)),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  /// Titre, recherche, bloc « À traiter », actions rapides et onglets.
  Widget _intro() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const Padding(
          padding: EdgeInsets.fromLTRB(16, 14, 16, 0),
          child: Text(
            'Mes commandes',
            style: TextStyle(color: Mk.navy, fontSize: 24, fontWeight: FontWeight.w800, letterSpacing: -.4),
          ),
        ),
        const Padding(
          padding: EdgeInsets.fromLTRB(16, 2, 16, 12),
          child: Text(
            'Suivez et traitez vos commandes efficacement',
            style: TextStyle(color: Mk.muted, fontSize: 13.5, fontWeight: FontWeight.w500),
          ),
        ),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 12),
          child: Row(
            children: [
              Expanded(
                child: SizedBox(
                  height: 46,
                  child: TextField(
                    controller: _search,
                    textInputAction: TextInputAction.search,
                    onChanged: (_) {
                      _debounce?.cancel();
                      _debounce = Timer(const Duration(milliseconds: 350), () => _load(reset: true));
                    },
                    onSubmitted: (_) => _load(reset: true),
                    style: const TextStyle(fontFamily: Mk.font, color: Mk.navy, fontSize: 14.5, fontWeight: FontWeight.w600),
                    decoration: InputDecoration(
                      hintText: 'Rechercher une commande…',
                      hintStyle: const TextStyle(fontFamily: Mk.font, color: Mk.hint, fontSize: 14, fontWeight: FontWeight.w500),
                      prefixIcon: const Icon(Icons.search_rounded, color: Mk.icon, size: 24),
                      filled: true,
                      fillColor: Colors.white,
                      contentPadding: EdgeInsets.zero,
                      border: _searchBorder(_line),
                      enabledBorder: _searchBorder(_line),
                      focusedBorder: _searchBorder(Mk.blue),
                    ),
                  ),
                ),
              ),
              const SizedBox(width: 8),
              _ToolButton(icon: Icons.filter_alt_outlined, label: 'Filtres', onTap: _chooseFilter),
              const SizedBox(width: 8),
              _ToolButton(icon: Icons.swap_vert_rounded, label: 'Trier par', onTap: _chooseSort),
            ],
          ),
        ),
        const SizedBox(height: 12),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 12),
          child: _SummaryCard(
            total: _stat('total'),
            fresh: _stat('pending'),
            toProcess: _stat('to_process'),
            delivered: _stat('delivered'),
          ),
        ),
        const _Caption('ACTIONS RAPIDES'),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 12),
          child: Row(
            children: [
              Expanded(
                child: _QuickAction(
                  icon: Icons.assignment_turned_in_outlined,
                  label: 'Préparer',
                  color: const Color(0xFFE85400),
                  background: const Color(0xFFFFEBDD),
                  onTap: () => _setFilter('pending'),
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: _QuickAction(
                  icon: Icons.local_shipping_outlined,
                  label: 'Expédier',
                  color: const Color(0xFF1456D9),
                  background: const Color(0xFFE1ECFD),
                  onTap: () => _setFilter('preparing'),
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: _QuickAction(
                  icon: Icons.schedule_rounded,
                  label: 'Historique',
                  color: Mk.navy,
                  background: const Color(0xFFE9ECF2),
                  onTap: () => _setFilter('delivered'),
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 8),
        _tabs(),
        const Divider(height: 1, color: _line),
        const _Caption('LISTE DES COMMANDES'),
      ],
    );
  }

  /// Onglets soulignés des états de commande.
  Widget _tabs() {
    return SizedBox(
      height: 46,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: 14),
        itemCount: _filters.length,
        separatorBuilder: (_, __) => const SizedBox(width: 18),
        itemBuilder: (context, index) {
          final entry = _filters.entries.elementAt(index);
          final active = _status == entry.key;
          return Semantics(
            selected: active,
            button: true,
            child: InkWell(
              onTap: () => _setFilter(entry.key),
              child: Column(
                mainAxisAlignment: MainAxisAlignment.end,
                children: [
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 4),
                    child: Text(
                      entry.value,
                      style: TextStyle(
                        color: active ? Mk.navy : Mk.muted,
                        fontSize: 14,
                        fontWeight: active ? FontWeight.w800 : FontWeight.w500,
                      ),
                    ),
                  ),
                  const SizedBox(height: 9),
                  AnimatedContainer(
                    duration: const Duration(milliseconds: 160),
                    height: 3,
                    width: active ? 64 : 0,
                    decoration: BoxDecoration(color: Mk.orange, borderRadius: BorderRadius.circular(2)),
                  ),
                ],
              ),
            ),
          );
        },
      ),
    );
  }

  Widget _orderCard(Map<String, dynamic> item) {
    final status = item['vendor_status'];
    final method = '${item['payment_method_label'] ?? item['payment_method'] ?? ''}'.trim();
    final number = orderNumberLabel(item['order_number']);
    final client = '${item['client_display_name'] ?? item['client_name'] ?? 'Client'}';
    final day = orderDayLabel(item['created_at']);
    final meta = [if (day.isNotEmpty) day, if (method.isNotEmpty) method].join(' · ');

    return Semantics(
      button: true,
      label: 'Ouvrir la commande $number',
      child: Material(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: () => _openOrder(item),
          child: Padding(
            padding: const EdgeInsets.fromLTRB(14, 12, 4, 12),
            child: Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Expanded(
                            child: Text(
                              number,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(color: Mk.navy, fontSize: 15, fontWeight: FontWeight.w800),
                            ),
                          ),
                          const SizedBox(width: 8),
                          Text(
                            orderMoney(item['seller_products_total'] ?? item['public_products_total']),
                            style: const TextStyle(color: Mk.navy, fontSize: 15, fontWeight: FontWeight.w800),
                          ),
                        ],
                      ),
                      const SizedBox(height: 3),
                      Text(
                        client,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(color: Mk.navy, fontSize: 13.5, fontWeight: FontWeight.w500),
                      ),
                      const SizedBox(height: 6),
                      Wrap(
                        spacing: 8,
                        runSpacing: 6,
                        crossAxisAlignment: WrapCrossAlignment.center,
                        alignment: WrapAlignment.spaceBetween,
                        children: [
                          if (meta.isNotEmpty)
                            Text(meta, style: const TextStyle(color: Mk.muted, fontSize: 12.5, fontWeight: FontWeight.w500)),
                          Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              OrderStatusPill(status: status, compact: true),
                              const SizedBox(width: 6),
                              OrderPaymentPill(status: item['payment_status'], label: _paymentLabel(item)),
                            ],
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
                const Icon(Icons.chevron_right_rounded, color: Mk.icon, size: 24),
              ],
            ),
          ),
        ),
      ),
    );
  }

  /// « Payée » ou « En attente », d'après l'état de paiement renvoyé par l'API.
  String _paymentLabel(Map<String, dynamic> item) => orderIsPaid(item['payment_status']) ? 'Payée' : 'En attente';
}

const _line = Color(0xFFE4E9F1);

OutlineInputBorder _searchBorder(Color color) => OutlineInputBorder(
      borderRadius: BorderRadius.circular(12),
      borderSide: BorderSide(color: color, width: 1.2),
    );

class _Option {
  const _Option(this.key, this.label, this.icon, this.color);
  final String key;
  final String label;
  final IconData icon;
  final Color color;
}

class _OptionRow extends StatelessWidget {
  const _OptionRow({required this.option, required this.selected, required this.onTap});
  final _Option option;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      inMutuallyExclusiveGroup: true,
      checked: selected,
      button: true,
      label: option.label,
      excludeSemantics: true,
      child: Material(
        color: selected ? Mk.softOrange : Colors.white,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(12),
          side: BorderSide(color: selected ? const Color(0xFFF9B68C) : _line, width: 1.2),
        ),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: onTap,
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 9),
            child: Row(
              children: [
                Container(
                  width: 40,
                  height: 40,
                  decoration: BoxDecoration(color: option.color.withValues(alpha: .12), borderRadius: BorderRadius.circular(10)),
                  child: Icon(option.icon, color: option.color, size: 23),
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: Text(option.label, style: const TextStyle(color: Mk.navy, fontSize: 15.5, fontWeight: FontWeight.w600)),
                ),
                Icon(
                  selected ? Icons.check_circle_rounded : Icons.radio_button_unchecked_rounded,
                  color: selected ? Mk.orange : Mk.hint,
                  size: 26,
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// Bouton blanc « Filtres » / « Trier par » ; réduit à son pictogramme sur un écran étroit.
class _ToolButton extends StatelessWidget {
  const _ToolButton({required this.icon, required this.label, required this.onTap});
  final IconData icon;
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final compact = MediaQuery.sizeOf(context).width < 400 || MediaQuery.textScalerOf(context).scale(10) > 10.5;
    return Tooltip(
      message: label,
      child: Material(
        color: Colors.white,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(12),
          side: const BorderSide(color: _line, width: 1.2),
        ),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: onTap,
          child: Container(
            height: 46,
            constraints: const BoxConstraints(minWidth: 46),
            padding: EdgeInsets.symmetric(horizontal: compact ? 0 : 10),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Icon(icon, color: Mk.navy, size: 22),
                if (!compact) ...[
                  const SizedBox(width: 6),
                  Text(label, style: const TextStyle(color: Mk.navy, fontSize: 13, fontWeight: FontWeight.w600)),
                ],
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _Caption extends StatelessWidget {
  const _Caption(this.text);
  final String text;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(14, 14, 14, 8),
      child: Text(
        text,
        style: const TextStyle(color: Mk.muted, fontSize: 11.5, fontWeight: FontWeight.w600, letterSpacing: 2.4),
      ),
    );
  }
}

/// Bloc « À traiter » : quatre compteurs et l'illustration de palette.
class _SummaryCard extends StatelessWidget {
  const _SummaryCard({required this.total, required this.fresh, required this.toProcess, required this.delivered});
  final int total;
  final int fresh;
  final int toProcess;
  final int delivered;

  @override
  Widget build(BuildContext context) {
    Widget stat(int value, String label) => Expanded(
          child: Semantics(
            label: '$value $label',
            excludeSemantics: true,
            child: Column(
              children: [
                FittedBox(
                  fit: BoxFit.scaleDown,
                  child: Text('$value', style: const TextStyle(color: Mk.navy, fontSize: 21, fontWeight: FontWeight.w800, height: 1.1)),
                ),
                const SizedBox(height: 2),
                FittedBox(
                  fit: BoxFit.scaleDown,
                  child: Text(label, style: const TextStyle(color: Mk.muted, fontSize: 11.5, fontWeight: FontWeight.w500)),
                ),
              ],
            ),
          ),
        );
    const divider = SizedBox(height: 34, child: VerticalDivider(width: 9, color: _line));

    return Container(
      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(14)),
      clipBehavior: Clip.antiAlias,
      child: IntrinsicHeight(
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Container(width: 4, color: Mk.orange),
            Expanded(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(10, 12, 4, 12),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Padding(
                      padding: EdgeInsets.only(left: 4),
                      child: Text(
                        'À TRAITER',
                        style: TextStyle(color: Mk.navy, fontSize: 13.5, fontWeight: FontWeight.w800, letterSpacing: 1.6),
                      ),
                    ),
                    const SizedBox(height: 10),
                    Row(
                      children: [
                        stat(total, 'commandes'),
                        divider,
                        stat(fresh, 'nouvelles'),
                        divider,
                        stat(toProcess, 'à traiter'),
                        divider,
                        stat(delivered, 'livrées'),
                      ],
                    ),
                  ],
                ),
              ),
            ),
            if (MediaQuery.sizeOf(context).width >= 380)
              Padding(
                padding: const EdgeInsets.only(right: 4),
                child: Image.asset('assets/images/mockups/orders_pallet.jpg', width: 104, fit: BoxFit.contain, excludeFromSemantics: true),
              ),
          ],
        ),
      ),
    );
  }
}

class _QuickAction extends StatelessWidget {
  const _QuickAction({required this.icon, required this.label, required this.color, required this.background, required this.onTap});
  final IconData icon;
  final String label;
  final Color color;
  final Color background;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: background,
      borderRadius: BorderRadius.circular(12),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: SizedBox(
          height: 52,
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 6),
            child: FittedBox(
              fit: BoxFit.scaleDown,
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Icon(icon, color: color, size: 26),
                  const SizedBox(width: 8),
                  Text(label, style: TextStyle(color: color, fontSize: 14, fontWeight: FontWeight.w700)),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
