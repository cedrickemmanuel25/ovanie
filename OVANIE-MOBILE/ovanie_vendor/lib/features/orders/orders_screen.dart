import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/network/api_client.dart';
import '../../data/vendor_repository.dart';
import 'order_detail_screen.dart';
import 'order_ui.dart';

class OrdersScreen extends StatefulWidget {
  const OrdersScreen({super.key});

  @override
  State<OrdersScreen> createState() => _OrdersScreenState();
}

class _OrdersScreenState extends State<OrdersScreen> {
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
  String _status = 'pending';
  String _sort = 'newest';

  static const Map<String, String> _filters = <String, String>{
    'all': 'Toutes',
    'pending': 'Nouvelles',
    'preparing': 'Préparation',
    'shipped': 'Expédiées',
    'delivered': 'Livrées',
    'cancelled': 'Annulées',
  };

  static const Map<String, String> _sorts = <String, String>{
    'newest': 'Plus récentes',
    'oldest': 'Plus anciennes',
  };

  @override
  void initState() {
    super.initState();
    _scroll.addListener(_onScroll);
    _load(reset: true);
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

  Future<void> _chooseSort() async {
    final selected = await showModalBottomSheet<String>(
      context: context,
      backgroundColor: Colors.transparent,
      builder: (context) => SafeArea(
        top: false,
        child: Container(
          padding: const EdgeInsets.fromLTRB(18, 10, 18, 18),
          decoration: const BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 44,
                height: 4,
                decoration: BoxDecoration(
                  color: const Color(0xFFD6DDE8),
                  borderRadius: BorderRadius.circular(99),
                ),
              ),
              const SizedBox(height: 12),
              const Align(
                alignment: Alignment.centerLeft,
                child: Text(
                  'Trier les commandes',
                  style: TextStyle(
                    color: orderText,
                    fontSize: 18,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              const SizedBox(height: 8),
              ..._sorts.entries.map(
                (entry) => ListTile(
                  trailing: _sort == entry.key
                      ? const Icon(Icons.check, color: orderOrange)
                      : null,
                  title: Text(
                    entry.value,
                    style: const TextStyle(
                      color: orderText,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  onTap: () => Navigator.pop(context, entry.key),
                ),
              ),
            ],
          ),
        ),
      ),
    );
    if (selected != null && selected != _sort) {
      setState(() => _sort = selected);
      _load(reset: true);
    }
  }

  void _setFilter(String value) {
    if (_status == value) return;
    setState(() => _status = value);
    _load(reset: true);
  }

  @override
  Widget build(BuildContext context) {
    return ColoredBox(
      color: const Color(0xFF031D47),
      child: Column(
        children: [
          OrderHeader(
            title: 'Mes commandes',
            subtitle: 'Suivez et traitez vos commandes efficacement',
            bottom: TextField(
              controller: _search,
              textInputAction: TextInputAction.search,
              onChanged: (_) {
                _debounce?.cancel();
                _debounce = Timer(
                  const Duration(milliseconds: 350),
                  () => _load(reset: true),
                );
              },
              onSubmitted: (_) => _load(reset: true),
              decoration: InputDecoration(
                hintText: 'Rechercher une commande...',
                hintStyle: const TextStyle(
                  color: Color(0xFF7487A7),
                  fontSize: 15.5,
                ),
                suffixIcon: IconButton(
                  onPressed: () => _load(reset: true),
                  icon: const Icon(
                    Icons.search_rounded,
                    color: orderText,
                    size: 29,
                  ),
                ),
                filled: true,
                fillColor: Colors.white,
                contentPadding: const EdgeInsets.symmetric(
                  horizontal: 20,
                  vertical: 17,
                ),
                enabledBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(17),
                  borderSide: BorderSide.none,
                ),
                focusedBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(17),
                  borderSide: const BorderSide(color: Color(0xFFAAC3E6)),
                ),
              ),
            ),
          ),
          Expanded(
            child: OrderSheet(
              child: RefreshIndicator(
                onRefresh: () => _load(reset: true),
                child: CustomScrollView(
                  controller: _scroll,
                  physics: const AlwaysScrollableScrollPhysics(),
                  slivers: [
                    SliverToBoxAdapter(child: _topControls()),
                    if (_loading)
                      const SliverFillRemaining(
                        hasScrollBody: false,
                        child: Center(
                          child: CircularProgressIndicator(color: orderOrange),
                        ),
                      )
                    else if (_error != null)
                      SliverFillRemaining(
                        hasScrollBody: false,
                        child: Center(
                          child: Padding(
                            padding: const EdgeInsets.all(24),
                            child: Column(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                const Icon(
                                  Icons.error_outline_rounded,
                                  color: Color(0xFFD92D20),
                                  size: 42,
                                ),
                                const SizedBox(height: 12),
                                Text(
                                  _error!,
                                  textAlign: TextAlign.center,
                                  style: const TextStyle(
                                    color: orderMuted,
                                    height: 1.4,
                                  ),
                                ),
                                const SizedBox(height: 14),
                                FilledButton(
                                  onPressed: () => _load(reset: true),
                                  child: const Text('Réessayer'),
                                ),
                              ],
                            ),
                          ),
                        ),
                      )
                    else if (_items.isEmpty)
                      SliverFillRemaining(
                        hasScrollBody: false,
                        child: Center(
                          child: Padding(
                            padding: const EdgeInsets.all(28),
                            child: Column(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                const OrderIconSquare(
                                  icon: Icons.inventory_2_outlined,
                                  size: 82,
                                ),
                                const SizedBox(height: 14),
                                Text(
                                  _status == 'all'
                                      ? 'Aucune commande pour le moment'
                                      : 'Aucune commande dans ce statut',
                                  style: const TextStyle(
                                    color: orderText,
                                    fontSize: 17,
                                    fontWeight: FontWeight.w900,
                                  ),
                                ),
                                const SizedBox(height: 7),
                                const Text(
                                  'Les commandes validées apparaîtront automatiquement ici.',
                                  textAlign: TextAlign.center,
                                  style: TextStyle(
                                    color: orderMuted,
                                    height: 1.4,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ),
                      )
                    else ...[
                      SliverPadding(
                        padding: const EdgeInsets.fromLTRB(22, 0, 22, 12),
                        sliver: SliverList.separated(
                          itemCount: _items.length,
                          separatorBuilder: (_, __) =>
                              const SizedBox(height: 11),
                          itemBuilder: (context, index) =>
                              _orderCard(_items[index]),
                        ),
                      ),
                      SliverToBoxAdapter(child: _quickActions()),
                      if (_loadingMore)
                        const SliverToBoxAdapter(
                          child: Padding(
                            padding: EdgeInsets.symmetric(vertical: 18),
                            child: Center(
                              child: CircularProgressIndicator(
                                color: orderOrange,
                                strokeWidth: 2.5,
                              ),
                            ),
                          ),
                        ),
                    ],
                    const SliverToBoxAdapter(child: SizedBox(height: 24)),
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _topControls() {
    final total = _stats['total'] ?? 0;
    final newCount = _stats['pending'] ?? _stats['new'] ?? 0;
    final toProcess = _stats['to_process'] ?? 0;
    final delivered = _stats['delivered'] ?? 0;

    return OrderResponsiveBody(
      padding: const EdgeInsets.fromLTRB(22, 18, 22, 18),
      child: Column(
        children: [
          Row(
            children: [
              Expanded(
                child: _largeControl(
                  icon: Icons.filter_alt_outlined,
                  label: 'Filtres',
                  onTap: () {
                    showModalBottomSheet<void>(
                      context: context,
                      backgroundColor: Colors.transparent,
                      builder: (context) => SafeArea(
                        top: false,
                        child: Container(
                          padding: const EdgeInsets.fromLTRB(18, 10, 18, 18),
                          decoration: const BoxDecoration(
                            color: Colors.white,
                            borderRadius: BorderRadius.vertical(
                              top: Radius.circular(24),
                            ),
                          ),
                          child: Column(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Container(
                                width: 44,
                                height: 4,
                                decoration: BoxDecoration(
                                  color: const Color(0xFFD6DDE8),
                                  borderRadius: BorderRadius.circular(99),
                                ),
                              ),
                              const SizedBox(height: 12),
                              ..._filters.entries.map(
                                (entry) => ListTile(
                                  title: Text(
                                    entry.value,
                                    style: const TextStyle(
                                      color: orderText,
                                      fontWeight: FontWeight.w700,
                                    ),
                                  ),
                                  trailing: _status == entry.key
                                      ? const Icon(
                                          Icons.check_circle_rounded,
                                          color: orderOrange,
                                        )
                                      : null,
                                  onTap: () {
                                    Navigator.pop(context);
                                    _setFilter(entry.key);
                                  },
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                    );
                  },
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: _largeControl(
                  icon: Icons.swap_vert_rounded,
                  label: 'Trier par',
                  onTap: _chooseSort,
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          SizedBox(
            height: 42,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              itemCount: _filters.length,
              separatorBuilder: (_, __) => const SizedBox(width: 9),
              itemBuilder: (context, index) {
                final entry = _filters.entries.elementAt(index);
                final active = _status == entry.key;
                return InkWell(
                  borderRadius: BorderRadius.circular(12),
                  onTap: () => _setFilter(entry.key),
                  child: AnimatedContainer(
                    duration: const Duration(milliseconds: 160),
                    alignment: Alignment.center,
                    padding: const EdgeInsets.symmetric(horizontal: 15),
                    decoration: BoxDecoration(
                      gradient: active
                          ? const LinearGradient(
                              colors: [Color(0xFF062A62), Color(0xFF071D59)],
                            )
                          : null,
                      color: active ? null : Colors.white,
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(
                        color: active ? Colors.transparent : orderBorder,
                      ),
                    ),
                    child: Text(
                      entry.value,
                      style: TextStyle(
                        color: active ? Colors.white : orderText,
                        fontWeight: active ? FontWeight.w800 : FontWeight.w700,
                        fontSize: 13.5,
                      ),
                    ),
                  ),
                );
              },
            ),
          ),
          const SizedBox(height: 16),
          OrderCard(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 14),
            child: Row(
              children: [
                Expanded(
                  child: _ListMetric(
                    icon: Icons.inventory_2_outlined,
                    value: total,
                    label: 'commandes',
                  ),
                ),
                const SizedBox(height: 40, child: VerticalDivider(width: 1)),
                Expanded(
                  child: _ListMetric(
                    icon: Icons.star_border_rounded,
                    value: newCount,
                    label: 'nouvelles',
                    color: orderOrange,
                  ),
                ),
                const SizedBox(height: 40, child: VerticalDivider(width: 1)),
                Expanded(
                  child: _ListMetric(
                    icon: Icons.schedule_rounded,
                    value: toProcess,
                    label: 'à traiter',
                    color: orderOrange,
                  ),
                ),
                const SizedBox(height: 40, child: VerticalDivider(width: 1)),
                Expanded(
                  child: _ListMetric(
                    icon: Icons.check_circle_outline_rounded,
                    value: delivered,
                    label: 'livrées',
                    color: orderGreen,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 22),
          const Align(
            alignment: Alignment.centerLeft,
            child: OrderSectionTitle('Liste des commandes'),
          ),
        ],
      ),
    );
  }

  Widget _largeControl({
    required IconData icon,
    required String label,
    required VoidCallback onTap,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(13),
      child: Container(
        height: 48,
        padding: const EdgeInsets.symmetric(horizontal: 15),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(13),
          border: Border.all(color: orderBorder),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(icon, color: orderText, size: 24),
            const SizedBox(width: 11),
            Flexible(
              child: Text(
                label,
                maxLines: 1,
                style: const TextStyle(
                  color: orderText,
                  fontSize: 14.5,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
            const SizedBox(width: 9),
            const Icon(
              Icons.keyboard_arrow_down_rounded,
              color: orderText,
              size: 22,
            ),
          ],
        ),
      ),
    );
  }

  Widget _orderCard(Map<String, dynamic> item) {
    final status = item['vendor_status'];
    final color = status == 'pending' ? orderText : orderStatusColor(status);
    final paymentStatus = '${item['payment_status'] ?? ''}';
    final paymentLabel = '${item['payment_status_label'] ?? ''}'.trim();
    final method =
        '${item['payment_method_label'] ?? item['payment_method'] ?? ''}'
            .trim();
    final number = '${item['order_number'] ?? 'Commande'}';
    return LayoutBuilder(
      builder: (context, constraints) {
        final wide = constraints.maxWidth >= 340;
        return Semantics(
          button: true,
          label: 'Ouvrir la commande $number',
          child: InkWell(
            onTap: () => _openOrder(item),
            borderRadius: BorderRadius.circular(14),
            child: OrderCard(
              padding: EdgeInsets.all(wide ? 12 : 10),
              child: Row(
                children: [
                  Container(
                    width: wide ? 62 : 48,
                    height: wide ? 86 : 76,
                    decoration: BoxDecoration(
                      color: color.withValues(alpha: .07),
                      borderRadius: BorderRadius.circular(12),
                    ),
                    alignment: Alignment.center,
                    child: CustomPaint(
                      size: Size.square(wide ? 42 : 32),
                      painter: _ParcelPainter(color),
                    ),
                  ),
                  SizedBox(width: wide ? 12 : 10),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Expanded(
                              child: Text(
                                number.startsWith('#') ? number : '#$number',
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: TextStyle(
                                  color: orderText,
                                  fontSize: wide ? 14 : 13,
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                            ),
                            if (wide)
                              OrderStatusPill(status: status, compact: true),
                          ],
                        ),
                        const SizedBox(height: 4),
                        Text(
                          '${item['client_display_name'] ?? item['client_name'] ?? 'Client OVANIE'}',
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(
                            color: orderMuted,
                            fontSize: wide ? 12 : 12,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          '${orderHumanDate(item['created_at'])}${method.isEmpty ? '' : '  ?  $method'}',
                          style: TextStyle(
                            color: orderMuted,
                            fontSize: wide ? 10.5 : 10.5,
                          ),
                        ),
                        const SizedBox(height: 7),
                        Wrap(
                          spacing: 12,
                          runSpacing: 5,
                          crossAxisAlignment: WrapCrossAlignment.center,
                          children: [
                            Text(
                              orderMoney(item['public_products_total']),
                              style: TextStyle(
                                color: orderOrange,
                                fontSize: wide ? 16 : 14,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                            OrderPaymentPill(
                              status: paymentStatus,
                              label: paymentLabel.isNotEmpty
                                  ? paymentLabel
                                  : ([
                                          'paid',
                                          'escrow_held',
                                          'verified',
                                          'commission_paid',
                                          'released_to_vendor',
                                        ].contains(paymentStatus)
                                        ? 'Pay?e'
                                        : 'En attente'),
                            ),
                          ],
                        ),
                        if (!wide) ...[
                          const SizedBox(height: 6),
                          OrderStatusPill(status: status, compact: true),
                        ],
                      ],
                    ),
                  ),
                  const SizedBox(width: 10),
                  Container(
                    width: 32,
                    height: 38,
                    decoration: BoxDecoration(
                      border: Border.all(color: orderBorder),
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: const Icon(
                      Icons.chevron_right,
                      color: orderText,
                      size: 23,
                    ),
                  ),
                ],
              ),
            ),
          ),
        );
      },
    );
  }

  Widget _quickActions() => OrderResponsiveBody(
    padding: const EdgeInsets.fromLTRB(22, 4, 22, 0),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const OrderSectionTitle('Actions rapides'),
        const SizedBox(height: 10),
        Row(
          children: [
            Expanded(
              child: _quickButton(
                Icons.assignment_turned_in_outlined,
                'Pr?parer',
                orderText,
                () => _setFilter('preparing'),
              ),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: _quickButton(
                Icons.local_shipping_outlined,
                'Exp?dier',
                orderGreen,
                () => _setFilter('preparing'),
              ),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: _quickButton(
                Icons.schedule_outlined,
                'Historique',
                const Color(0xFF8017B6),
                () => _setFilter('all'),
              ),
            ),
          ],
        ),
      ],
    ),
  );

  Widget _quickButton(
    IconData icon,
    String label,
    Color color,
    VoidCallback onTap,
  ) => InkWell(
    onTap: onTap,
    borderRadius: BorderRadius.circular(12),
    child: OrderCard(
      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 10),
      child: LayoutBuilder(
        builder: (context, constraints) {
          final symbol = Container(
            width: 34,
            height: 34,
            decoration: BoxDecoration(
              color: color.withValues(alpha: .08),
              shape: BoxShape.circle,
            ),
            child: Icon(icon, color: color, size: 21),
          );
          final text = Text(
            label,
            maxLines: 1,
            style: const TextStyle(color: orderText, fontSize: 12),
          );
          return constraints.maxWidth >= 130
              ? Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [symbol, const SizedBox(width: 10), text],
                )
              : Column(children: [symbol, const SizedBox(height: 5), text]);
        },
      ),
    ),
  );
}

class _ListMetric extends StatelessWidget {
  const _ListMetric({
    required this.icon,
    required this.value,
    required this.label,
    this.color = orderText,
  });
  final IconData icon;
  final Object value;
  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) => LayoutBuilder(
    builder: (context, constraints) {
      final symbol = Container(
        width: 38,
        height: 38,
        decoration: BoxDecoration(
          color: color.withValues(alpha: .08),
          shape: BoxShape.circle,
        ),
        child: Icon(icon, color: color, size: 23),
      );
      final text = Column(
        crossAxisAlignment: constraints.maxWidth < 125
            ? CrossAxisAlignment.center
            : CrossAxisAlignment.start,
        children: [
          Text(
            '$value',
            style: const TextStyle(
              color: orderText,
              fontSize: 23,
              fontWeight: FontWeight.w700,
            ),
          ),
          Text(
            label,
            maxLines: 1,
            style: const TextStyle(color: orderMuted, fontSize: 11),
          ),
        ],
      );
      return constraints.maxWidth < 125
          ? Column(children: [symbol, const SizedBox(height: 5), text])
          : Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [symbol, const SizedBox(width: 10), text],
            );
    },
  );
}

class _ParcelPainter extends CustomPainter {
  const _ParcelPainter(this.color);
  final Color color;
  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = color
      ..style = PaintingStyle.stroke
      ..strokeWidth = 1.8
      ..strokeJoin = StrokeJoin.round;
    final w = size.width;
    final h = size.height;
    final outline = Path()
      ..moveTo(w * .5, 0)
      ..lineTo(w, h * .25)
      ..lineTo(w, h * .75)
      ..lineTo(w * .5, h)
      ..lineTo(0, h * .75)
      ..lineTo(0, h * .25)
      ..close();
    canvas.drawPath(outline, paint);
    canvas.drawPath(
      Path()
        ..moveTo(0, h * .25)
        ..lineTo(w * .5, h * .5)
        ..lineTo(w, h * .25)
        ..moveTo(w * .5, h * .5)
        ..lineTo(w * .5, h)
        ..moveTo(w * .25, h * .125)
        ..lineTo(w * .75, h * .375)
        ..lineTo(w * .75, h * .59),
      paint,
    );
  }

  @override
  bool shouldRepaint(_ParcelPainter oldDelegate) => color != oldDelegate.color;
}
