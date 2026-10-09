import 'package:flutter/material.dart';

import '../../../app/theme.dart';
import '../../../core/network/api_client.dart';
import '../../../core/sync/live_sync_service.dart';
import '../../../core/utils/formatters.dart';
import '../../../shared/widgets/auth_required_view.dart';
import '../../../shared/widgets/ovanie_bottom_navigation.dart';
import '../../shell/domain/main_navigation_store.dart';
import '../../auth/domain/session_store.dart';
import '../../cart/presentation/cart_screen.dart';
import '../../tracking/presentation/client_delivery_tracking_screen.dart';
import '../data/orders_repository.dart';
import '../domain/order_model.dart';
import 'invoice_screen.dart';
import 'order_detail_screen.dart';

class OrdersScreen extends StatefulWidget {
  final VoidCallback? onOpenAccount;
  final VoidCallback? onOpenCart;
  final VoidCallback? onStartShopping;

  const OrdersScreen({
    super.key,
    this.onOpenAccount,
    this.onOpenCart,
    this.onStartShopping,
  });

  @override
  State<OrdersScreen> createState() => _OrdersScreenState();
}

class _OrdersScreenState extends State<OrdersScreen>
    with LiveSyncListener<OrdersScreen> {
  static const _apiBuckets = ['in_progress', 'delivered', 'cancelled', 'returned'];
  static const _tabs = <_OrdersTabData>[
    _OrdersTabData('all', 'Toutes'),
    _OrdersTabData('to_pay', 'À payer'),
    _OrdersTabData('preparing', 'À préparer'),
    _OrdersTabData('shipping', 'En livraison'),
    _OrdersTabData('delivered', 'Livrées'),
    _OrdersTabData('cancelled', 'Annulées'),
  ];

  final OrdersRepository _repository = const OrdersRepository();
  final TextEditingController _searchController = TextEditingController();

  List<MobileOrder> _orders = const [];
  String _selectedTab = 'all';
  bool _loading = true;
  String? _error;
  String _query = '';

  @override
  void initState() {
    super.initState();
    SessionStore.instance.addListener(_sessionChanged);
    _loadOrders();
    listenLiveSync({'orders'}, () => _loadOrders(silent: true));
  }

  @override
  void dispose() {
    SessionStore.instance.removeListener(_sessionChanged);
    _searchController.dispose();
    super.dispose();
  }

  void _sessionChanged() {
    if (!mounted) return;
    if (SessionStore.instance.isAuthenticated) {
      _loadOrders();
    } else {
      setState(() {
        _orders = const [];
        _loading = false;
        _error = null;
      });
    }
  }

  Future<void> _loadOrders({bool silent = false}) async {
    if (!SessionStore.instance.isAuthenticated) {
      if (mounted) setState(() => _loading = false);
      return;
    }

    // Rafraîchissement synchronisé : pas de loader si la liste est affichée.
    if (!silent || _orders.isEmpty) {
      setState(() {
        _loading = true;
        _error = null;
      });
    }

    try {
      final pages = await Future.wait(
        _apiBuckets.map((bucket) => _repository.fetchOrders(bucket: bucket, page: 1, perPage: 50)),
      );
      final byId = <int, MobileOrder>{};
      for (final page in pages) {
        for (final order in page.orders) {
          byId[order.id] = order;
        }
      }
      final next = byId.values.toList()
        ..sort((a, b) {
          final left = a.createdAt ?? DateTime.fromMillisecondsSinceEpoch(0);
          final right = b.createdAt ?? DateTime.fromMillisecondsSinceEpoch(0);
          return right.compareTo(left);
        });
      if (!mounted) return;
      setState(() {
        _orders = next;
        _loading = false;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _loading = false;
        _error = ApiClient.friendlyError(error);
      });
    }
  }

  List<MobileOrder> get _visibleOrders {
    Iterable<MobileOrder> result = _orders;
    switch (_selectedTab) {
      case 'to_pay':
        result = result.where(_isToPay);
        break;
      case 'preparing':
        result = result.where(_isPreparing);
        break;
      case 'shipping':
        result = result.where(_isShipping);
        break;
      case 'delivered':
        result = result.where((order) => order.clientBucket == 'delivered');
        break;
      case 'cancelled':
        result = result.where((order) => order.clientBucket == 'cancelled');
        break;
    }

    final q = _query.trim().toLowerCase();
    if (q.isNotEmpty) {
      result = result.where((order) {
        return order.orderNumber.toLowerCase().contains(q) ||
            order.invoiceNumber.toLowerCase().contains(q) ||
            order.items.any((item) => item.productName.toLowerCase().contains(q));
      });
    }
    return result.toList(growable: false);
  }

  bool _isToPay(MobileOrder order) {
    if (order.clientBucket != 'in_progress') return false;
    // Le montant d'une commande COD reste à encaisser jusqu'à la livraison :
    // ce n'est pas une commande à payer en ligne.
    if (order.paymentMethod == 'cash_on_delivery') return false;
    return !order.isPaymentConfirmed &&
        (order.actions.canResumePayment ||
            order.outstandingAmount > 0 ||
            order.paymentState == 'pending' ||
            order.paymentStatus == 'pending');
  }

  bool _isShipping(MobileOrder order) {
    if (order.clientBucket != 'in_progress' || _isToPay(order)) return false;
    final delivery = order.deliveryStatus.toLowerCase();
    // Le suivi est consultable dès la commande : seul l'état réel de la
    // livraison indique que le colis est parti.
    return delivery.contains('transit') ||
        delivery.contains('livraison') ||
        delivery.contains('delivery') ||
        delivery.contains('shipped') ||
        delivery.contains('picked') ||
        delivery.contains('assigned');
  }

  bool _isPreparing(MobileOrder order) {
    return order.clientBucket == 'in_progress' && !_isToPay(order) && !_isShipping(order);
  }

  int _countFor(String key) {
    switch (key) {
      case 'to_pay':
        return _orders.where(_isToPay).length;
      case 'preparing':
        return _orders.where(_isPreparing).length;
      case 'shipping':
        return _orders.where(_isShipping).length;
      case 'delivered':
        return _orders.where((o) => o.clientBucket == 'delivered').length;
      case 'cancelled':
        return _orders.where((o) => o.clientBucket == 'cancelled').length;
      default:
        return _orders.length;
    }
  }

  void _goBack() => Navigator.of(context).maybePop();

  void _openCart() {
    if (widget.onOpenCart == null) {
      Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => const CartScreen()));
      return;
    }
    Navigator.of(context).maybePop();
    WidgetsBinding.instance.addPostFrameCallback((_) => widget.onOpenCart?.call());
  }

  void _openAccount() {
    Navigator.of(context).maybePop();
    WidgetsBinding.instance.addPostFrameCallback((_) => widget.onOpenAccount?.call());
  }

  void _startShopping() {
    Navigator.of(context).maybePop();
    WidgetsBinding.instance.addPostFrameCallback((_) => widget.onStartShopping?.call());
  }

  Future<void> _openOrder(MobileOrder order) async {
    await Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => OrderDetailScreen(orderId: order.id, initialOrder: order),
      ),
    );
    if (mounted) await _loadOrders();
  }

  void _openTracking(MobileOrder order) {
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => ClientDeliveryTrackingScreen(
          orderId: order.id,
          initialOrderNumber: order.orderNumber,
        ),
      ),
    );
  }

  Future<void> _showSearch() async {
    _searchController.text = _query;
    final value = await showDialog<String>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Rechercher une commande'),
        content: TextField(
          controller: _searchController,
          autofocus: true,
          decoration: const InputDecoration(
            hintText: 'N° de commande ou produit',
            prefixIcon: Icon(Icons.search_rounded),
          ),
          onSubmitted: (value) => Navigator.of(context).pop(value),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.of(context).pop(''), child: const Text('Effacer')),
          FilledButton(onPressed: () => Navigator.of(context).pop(_searchController.text), child: const Text('Rechercher')),
        ],
      ),
    );
    if (value == null || !mounted) return;
    setState(() => _query = value);
  }

  Future<void> _showFilters() async {
    final selected = await showModalBottomSheet<String>(
      context: context,
      showDragHandle: true,
      backgroundColor: Colors.white,
      builder: (context) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(20, 0, 20, 24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text('Filtrer les commandes', style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800, color: OvanieColors.navy)),
              const SizedBox(height: 12),
              ..._tabs.map(
                (tab) => RadioListTile<String>(
                  value: tab.key,
                  groupValue: _selectedTab,
                  activeColor: OvanieColors.orange,
                  title: Text('${tab.label} (${_countFor(tab.key)})'),
                  onChanged: (value) => Navigator.of(context).pop(value),
                ),
              ),
            ],
          ),
        ),
      ),
    );
    if (selected != null && mounted) setState(() => _selectedTab = selected);
  }

  @override
  Widget build(BuildContext context) {
    if (!SessionStore.instance.isAuthenticated) {
      return Scaffold(
        backgroundColor: Colors.white,
        bottomNavigationBar: const OvanieBottomNavigation(
          selectedTab: OvanieMainTab.account,
        ),
        body: SafeArea(
          child: Column(
            children: [
              _OrdersHeader(onBack: _goBack, onSearch: _showSearch, onFilter: _showFilters),
              Expanded(
                child: AuthRequiredView(
                  padding: const EdgeInsets.fromLTRB(30, 20, 30, 70),
                  onOpenAccount: _openAccount,
                ),
              ),
            ],
          ),
        ),
      );
    }

    return Scaffold(
      backgroundColor: Colors.white,
      bottomNavigationBar: const OvanieBottomNavigation(
        selectedTab: OvanieMainTab.account,
      ),
      body: SafeArea(
        bottom: false,
        child: Column(
          children: [
            _OrdersHeader(onBack: _goBack, onSearch: _showSearch, onFilter: _showFilters),
            _OrdersTabs(
              selected: _selectedTab,
              onSelected: (value) => setState(() => _selectedTab = value),
            ),
            Expanded(child: _buildBody()),
          ],
        ),
      ),
    );
  }

  Widget _buildBody() {
    if (_loading) {
      return const Center(child: CircularProgressIndicator(color: OvanieColors.orange));
    }

    if (_error != null) {
      return RefreshIndicator(
        color: OvanieColors.orange,
        onRefresh: _loadOrders,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.fromLTRB(24, 90, 24, 32),
          children: [
            const Icon(Icons.cloud_off_outlined, size: 58, color: OvanieColors.muted),
            const SizedBox(height: 18),
            Text(_error!, textAlign: TextAlign.center, style: const TextStyle(color: OvanieColors.text, fontSize: 16)),
            const SizedBox(height: 18),
            Center(
              child: OutlinedButton.icon(
                onPressed: _loadOrders,
                icon: const Icon(Icons.refresh_rounded),
                label: const Text('Réessayer'),
              ),
            ),
          ],
        ),
      );
    }

    final orders = _visibleOrders;
    if (orders.isEmpty) {
      return RefreshIndicator(
        color: OvanieColors.orange,
        onRefresh: _loadOrders,
        child: _OrdersEmptyState(
          filtered: _orders.isNotEmpty,
          onCategories: _startShopping,
          onProducts: _startShopping,
          onContact: _showSupportMessage,
        ),
      );
    }

    return RefreshIndicator(
      color: OvanieColors.orange,
      onRefresh: _loadOrders,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(parent: BouncingScrollPhysics()),
        padding: const EdgeInsets.fromLTRB(18, 18, 18, 26),
        children: [
          if (_query.isNotEmpty) ...[
            _SearchResultChip(query: _query, onClear: () => setState(() => _query = '')),
            const SizedBox(height: 12),
          ],
          ...orders.map(
            (order) => Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: _OrderCard(
                order: order,
                state: _stateOf(order),
                onOpen: () => _openOrder(order),
                onPrimary: () => _primaryAction(order),
                onInvoice: () => Navigator.of(context).push(
                  MaterialPageRoute<void>(builder: (_) => InvoiceScreen(orderId: order.id, initialOrder: order)),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  _OrderVisualState _stateOf(MobileOrder order) {
    if (order.clientBucket == 'cancelled') return _OrderVisualState.cancelled;
    if (order.clientBucket == 'delivered' ||
        order.clientBucket == 'returned' ||
        order.deliveredAt != null ||
        order.deliveryStatus == 'delivered') {
      return _OrderVisualState.delivered;
    }
    if (_isToPay(order)) return _OrderVisualState.toPay;
    if (_isShipping(order)) return _OrderVisualState.shipping;
    return _OrderVisualState.preparing;
  }

  void _primaryAction(MobileOrder order) {
    if (_isShipping(order) && order.canTrackDelivery) {
      _openTracking(order);
      return;
    }
    _openOrder(order);
  }

  void _showSupportMessage() {
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('Support OVANIE : 01 61 78 18 18')),
    );
  }
}

class _OrdersTabData {
  final String key;
  final String label;
  const _OrdersTabData(this.key, this.label);
}

class _OrdersHeader extends StatelessWidget {
  final VoidCallback onBack;
  final VoidCallback onSearch;
  final VoidCallback onFilter;

  const _OrdersHeader({required this.onBack, required this.onSearch, required this.onFilter});

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 72,
      child: Stack(
        alignment: Alignment.center,
        children: [
          const Center(
            child: Text(
              'Mes commandes',
              style: TextStyle(color: OvanieColors.navy, fontSize: 22, fontWeight: FontWeight.w900),
            ),
          ),
          Positioned(
            left: 10,
            child: IconButton(
              onPressed: onBack,
              icon: const Icon(Icons.arrow_back_rounded, color: OvanieColors.navy, size: 30),
            ),
          ),
          Positioned(
            right: 10,
            child: Row(
              children: [
                IconButton(onPressed: onSearch, icon: const Icon(Icons.search_rounded, color: OvanieColors.navy, size: 30)),
                IconButton(onPressed: onFilter, icon: const Icon(Icons.filter_alt_outlined, color: OvanieColors.navy, size: 30)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _OrdersTabs extends StatelessWidget {
  final String selected;
  final ValueChanged<String> onSelected;

  const _OrdersTabs({required this.selected, required this.onSelected});

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: const BoxDecoration(
        border: Border(bottom: BorderSide(color: Color(0xFFE7EAF0))),
      ),
      child: SizedBox(
        height: 55,
        child: ListView.separated(
          padding: const EdgeInsets.symmetric(horizontal: 18),
          scrollDirection: Axis.horizontal,
          itemCount: _OrdersScreenState._tabs.length,
          separatorBuilder: (_, __) => const SizedBox(width: 24),
          itemBuilder: (context, index) {
            final tab = _OrdersScreenState._tabs[index];
            final active = tab.key == selected;
            return InkWell(
              onTap: () => onSelected(tab.key),
              child: Column(
                mainAxisAlignment: MainAxisAlignment.end,
                children: [
                  Text(
                    tab.label,
                    style: TextStyle(
                      color: active ? OvanieColors.orange : OvanieColors.navy,
                      fontSize: 14.5,
                      fontWeight: active ? FontWeight.w800 : FontWeight.w600,
                    ),
                  ),
                  const SizedBox(height: 13),
                  Container(
                    width: 64,
                    height: 2.5,
                    color: active ? OvanieColors.orange : Colors.transparent,
                  ),
                ],
              ),
            );
          },
        ),
      ),
    );
  }
}

class _OrdersSummaryStrip extends StatelessWidget {
  final int total;
  final int toPay;
  final int inProgress;
  final int delivered;

  const _OrdersSummaryStrip({required this.total, required this.toPay, required this.inProgress, required this.delivered});

  @override
  Widget build(BuildContext context) {
    final items = [
      _SummaryItem(Icons.shopping_bag_outlined, total, 'Total\ncommandes', const Color(0xFF1664D9), const Color(0xFFEAF2FF)),
      _SummaryItem(Icons.schedule_rounded, toPay, 'À payer', OvanieColors.orange, const Color(0xFFFFF0E7)),
      _SummaryItem(Icons.inventory_2_outlined, inProgress, 'En cours', const Color(0xFFF0A000), const Color(0xFFFFF7DD)),
      _SummaryItem(Icons.check_circle_outline_rounded, delivered, 'Livrées', const Color(0xFF2E9B54), const Color(0xFFE8F7EC)),
    ];

    return SizedBox(
      height: 100,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        itemCount: items.length,
        separatorBuilder: (_, __) => const SizedBox(width: 10),
        itemBuilder: (context, index) => SizedBox(width: 132, child: _SummaryCard(item: items[index])),
      ),
    );
  }
}

class _SummaryItem {
  final IconData icon;
  final int value;
  final String label;
  final Color color;
  final Color background;
  const _SummaryItem(this.icon, this.value, this.label, this.color, this.background);
}

class _SummaryCard extends StatelessWidget {
  final _SummaryItem item;
  const _SummaryCard({required this.item});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(13),
      decoration: BoxDecoration(
        color: const Color(0xFFFBFCFE),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: const Color(0xFFF0F2F6)),
      ),
      child: Row(
        children: [
          Container(
            width: 38,
            height: 38,
            decoration: BoxDecoration(color: item.background, borderRadius: BorderRadius.circular(10)),
            child: Icon(item.icon, color: item.color, size: 22),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('${item.value}', style: const TextStyle(color: OvanieColors.navy, fontSize: 18, fontWeight: FontWeight.w900)),
                Text(item.label, style: const TextStyle(color: OvanieColors.navy, fontSize: 12, height: 1.15, fontWeight: FontWeight.w500)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

enum _OrderVisualState { toPay, preparing, shipping, delivered, cancelled }

class _OrderCard extends StatelessWidget {
  final MobileOrder order;
  final _OrderVisualState state;
  final VoidCallback onOpen;
  final VoidCallback onPrimary;
  final VoidCallback onInvoice;

  const _OrderCard({
    required this.order,
    required this.state,
    required this.onOpen,
    required this.onPrimary,
    required this.onInvoice,
  });

  String get _number => 'N° ${order.orderNumber.isEmpty ? order.id : order.orderNumber}';

  int get _articles => order.items.fold<int>(0, (sum, item) => sum + item.quantity);

  String get _destination =>
      [order.quartier, order.commune].where((e) => e.trim().isNotEmpty).join(', ');

  @override
  Widget build(BuildContext context) {
    return switch (state) {
      _OrderVisualState.toPay => _toPay(),
      _OrderVisualState.preparing => _preparing(),
      _OrderVisualState.shipping => _shipping(),
      _OrderVisualState.delivered => _delivered(),
      _OrderVisualState.cancelled => _cancelled(),
    };
  }

  Widget _frame({required Widget child, Color border = const Color(0xFFE4E7ED), Color color = Colors.white}) {
    return Material(
      color: color,
      borderRadius: BorderRadius.circular(14),
      child: InkWell(
        onTap: onOpen,
        borderRadius: BorderRadius.circular(14),
        child: Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: border),
          ),
          child: child,
        ),
      ),
    );
  }

  Widget _title(String trailing, {Color trailingColor = OvanieColors.muted}) => Row(
        children: [
          Expanded(
            child: Text(_number, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: OvanieColors.navy, fontSize: 15, fontWeight: FontWeight.w700)),
          ),
          Text(trailing, style: TextStyle(color: trailingColor, fontSize: 13, fontWeight: FontWeight.w600)),
        ],
      );

  Widget _button(String label, IconData icon, VoidCallback onPressed, {bool filled = true, Color color = OvanieColors.orange}) => SizedBox(
        height: 44,
        width: double.infinity,
        child: filled
            ? FilledButton.icon(
                onPressed: onPressed,
                style: FilledButton.styleFrom(backgroundColor: color, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10))),
                icon: Icon(icon, size: 18),
                label: Text(label, style: const TextStyle(fontSize: 14.5, fontWeight: FontWeight.w700)),
              )
            : OutlinedButton.icon(
                onPressed: onPressed,
                style: OutlinedButton.styleFrom(foregroundColor: color, side: BorderSide(color: color.withValues(alpha: .5)), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10))),
                icon: Icon(icon, size: 18),
                label: Text(label, style: const TextStyle(fontSize: 14.5, fontWeight: FontWeight.w700)),
              ),
      );

  /// À payer : le montant dû et l'action de paiement priment.
  Widget _toPay() {
    final due = order.outstandingAmount > 0 ? order.outstandingAmount : order.total;
    return _frame(
      border: const Color(0xFFFFD9B8),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _title(_dateOrEmpty(order.createdAt)),
          const SizedBox(height: 12),
          Row(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text('Montant à régler', style: TextStyle(color: OvanieColors.muted, fontSize: 13)),
                    const SizedBox(height: 2),
                    Text(formatFcfa(due), style: const TextStyle(color: OvanieColors.orange, fontSize: 22, fontWeight: FontWeight.w800)),
                  ],
                ),
              ),
              _OrderThumbs(items: order.items, maxItems: 2, itemSize: 40),
            ],
          ),
          const SizedBox(height: 12),
          _button('Payer maintenant', Icons.lock_outline_rounded, onPrimary),
        ],
      ),
    );
  }

  /// À préparer : où en est la commande (confirmée, préparation, expédition).
  Widget _preparing() {
    const steps = ['Confirmée', 'Préparation', 'Expédition'];
    const current = 1;
    return _frame(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _title(formatFcfa(order.total), trailingColor: OvanieColors.navy),
          const SizedBox(height: 4),
          Text(
            '$_articles article${_articles > 1 ? 's' : ''} · commandé le ${_dateOrEmpty(order.createdAt)}',
            style: const TextStyle(color: OvanieColors.muted, fontSize: 13),
          ),
          const SizedBox(height: 14),
          Row(
            children: [
              for (var i = 0; i < steps.length; i++) ...[
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Container(
                        height: 4,
                        decoration: BoxDecoration(
                          color: i <= current ? const Color(0xFF288A45) : const Color(0xFFE4E7ED),
                          borderRadius: BorderRadius.circular(4),
                        ),
                      ),
                      const SizedBox(height: 6),
                      Text(
                        steps[i],
                        style: TextStyle(
                          color: i <= current ? const Color(0xFF288A45) : OvanieColors.muted,
                          fontSize: 12.5,
                          fontWeight: i == current ? FontWeight.w700 : FontWeight.w500,
                        ),
                      ),
                    ],
                  ),
                ),
                if (i != steps.length - 1) const SizedBox(width: 6),
              ],
            ],
          ),
          if (order.deliveryStatusLabel.trim().isNotEmpty) ...[
            const SizedBox(height: 10),
            Text(order.deliveryStatusLabel, style: const TextStyle(color: OvanieColors.navy, fontSize: 13.5)),
          ],
        ],
      ),
    );
  }

  /// En livraison : date prévue, destination et suivi.
  Widget _shipping() {
    final date = order.estimatedMaxDate ?? order.estimatedMinDate;
    return _frame(
      border: const Color(0xFFC9DCFA),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _title(formatFcfa(order.total), trailingColor: OvanieColors.navy),
          const SizedBox(height: 12),
          Row(
            children: [
              Container(
                width: 40,
                height: 40,
                decoration: const BoxDecoration(color: Color(0xFFEAF2FF), shape: BoxShape.circle),
                child: const Icon(Icons.local_shipping_outlined, color: Color(0xFF1664D9), size: 21),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      date == null ? 'En route vers vous' : 'Arrivée prévue le ${_dateOrEmpty(date)}',
                      style: const TextStyle(color: OvanieColors.navy, fontSize: 14.5, fontWeight: FontWeight.w700),
                    ),
                    if (_destination.isNotEmpty)
                      Text(_destination, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: OvanieColors.muted, fontSize: 13)),
                  ],
                ),
              ),
            ],
          ),
          if (order.canTrackDelivery) ...[
            const SizedBox(height: 12),
            _button('Suivre la livraison', Icons.near_me_outlined, onPrimary, color: const Color(0xFF1664D9)),
          ],
        ],
      ),
    );
  }

  /// Livrée : récapitulatif sobre avec accès à la facture.
  Widget _delivered() {
    return _frame(
      child: Row(
        children: [
          if (order.items.isNotEmpty) _OrderThumb(item: order.items.first, size: 48),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(_number, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: OvanieColors.navy, fontSize: 15, fontWeight: FontWeight.w700)),
                const SizedBox(height: 2),
                Row(
                  children: [
                    const Icon(Icons.check_circle_rounded, size: 15, color: Color(0xFF288A45)),
                    const SizedBox(width: 4),
                    Expanded(
                      child: Text(
                        order.deliveredAt == null ? 'Livrée' : 'Livrée le ${_dateOrEmpty(order.deliveredAt)}',
                        style: const TextStyle(color: Color(0xFF288A45), fontSize: 13, fontWeight: FontWeight.w600),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 2),
                Text(formatFcfa(order.total), style: const TextStyle(color: OvanieColors.navy, fontSize: 14, fontWeight: FontWeight.w700)),
              ],
            ),
          ),
          TextButton.icon(
            onPressed: onInvoice,
            icon: const Icon(Icons.description_outlined, size: 18),
            label: const Text('Facture', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w600)),
          ),
        ],
      ),
    );
  }

  /// Annulée : ligne grisée, sans action.
  Widget _cancelled() {
    return _frame(
      color: const Color(0xFFF8F9FB),
      child: Row(
        children: [
          const Icon(Icons.block_rounded, color: Color(0xFF9AA3B2), size: 22),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(_number, style: const TextStyle(color: Color(0xFF6E7480), fontSize: 15, fontWeight: FontWeight.w600)),
                const SizedBox(height: 2),
                Text(
                  order.updatedAt == null ? 'Annulée' : 'Annulée le ${_dateOrEmpty(order.updatedAt)}',
                  style: const TextStyle(color: Color(0xFF9AA3B2), fontSize: 13),
                ),
              ],
            ),
          ),
          Text(
            formatFcfa(order.total),
            style: const TextStyle(
              color: Color(0xFF9AA3B2),
              fontSize: 14,
              fontWeight: FontWeight.w600,
              decoration: TextDecoration.lineThrough,
            ),
          ),
        ],
      ),
    );
  }
}

String _dateOrEmpty(DateTime? value) => value == null ? '' : _formatDate(value);

class _OrderThumbs extends StatelessWidget {
  final List<MobileOrderItem> items;
  final int maxItems;
  final double itemSize;
  const _OrderThumbs({
    required this.items,
    this.maxItems = 3,
    this.itemSize = 58,
  });

  @override
  Widget build(BuildContext context) {
    final visible = items.take(maxItems).toList(growable: false);
    final extra = items.length - visible.length;
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        for (var i = 0; i < visible.length; i++) ...[
          _OrderThumb(item: visible[i], size: itemSize),
          if (i < visible.length - 1 || extra > 0) const SizedBox(width: 4),
        ],
        if (extra > 0)
          Container(
            width: itemSize,
            height: itemSize,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              color: const Color(0xFFF7F8FA),
              borderRadius: BorderRadius.circular(8),
              border: Border.all(color: const Color(0xFFE3E6EC)),
            ),
            child: Text('+$extra', style: const TextStyle(color: OvanieColors.navy, fontWeight: FontWeight.w700)),
          ),
      ],
    );
  }
}

class _OrderThumb extends StatelessWidget {
  final MobileOrderItem item;
  final double size;
  const _OrderThumb({required this.item, this.size = 58});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: size,
      height: size,
      padding: const EdgeInsets.all(4),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: const Color(0xFFE3E6EC)),
      ),
      child: item.imageUrl.isEmpty
          ? const Icon(Icons.inventory_2_outlined, color: Color(0xFFADB5C5))
          : Image.network(
              item.imageUrl,
              fit: BoxFit.contain,
              errorBuilder: (_, __, ___) => const Icon(Icons.inventory_2_outlined, color: Color(0xFFADB5C5)),
            ),
    );
  }
}

class _StatusChip extends StatelessWidget {
  final String label;
  final Color color;
  final Color background;
  const _StatusChip({required this.label, required this.color, required this.background});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
      decoration: BoxDecoration(color: background, borderRadius: BorderRadius.circular(7)),
      child: Text(label, style: TextStyle(color: color, fontSize: 12, fontWeight: FontWeight.w700)),
    );
  }
}

class _OrdersEmptyState extends StatelessWidget {
  final bool filtered;
  final VoidCallback onCategories;
  final VoidCallback onProducts;
  final VoidCallback onContact;

  const _OrdersEmptyState({required this.filtered, required this.onCategories, required this.onProducts, required this.onContact});

  @override
  Widget build(BuildContext context) {
    return ListView(
      physics: const AlwaysScrollableScrollPhysics(parent: BouncingScrollPhysics()),
      padding: const EdgeInsets.fromLTRB(26, 72, 26, 26),
      children: [
        Center(
          child: SizedBox(
            width: 330,
            child: Image.asset('assets/images/orders_empty_illustration.png', fit: BoxFit.contain),
          ),
        ),
        const SizedBox(height: 28),
        Text(
          filtered ? 'Aucune commande dans cette catégorie' : 'Aucune commande pour le moment',
          textAlign: TextAlign.center,
          style: const TextStyle(color: OvanieColors.navy, fontSize: 24, fontWeight: FontWeight.w900),
        ),
        const SizedBox(height: 14),
        Text(
          filtered
              ? 'Aucune commande ne correspond au filtre sélectionné.'
              : 'Vous n’avez pas encore passé de commande.\nExplorez nos catégories et démarrez votre\nprochain chantier avec OVANIE.',
          textAlign: TextAlign.center,
          style: const TextStyle(color: Color(0xFF33425F), fontSize: 15.5, height: 1.45, fontWeight: FontWeight.w500),
        ),
        const SizedBox(height: 30),
        Row(
          children: [
            Expanded(
              child: SizedBox(
                height: 52,
                child: OutlinedButton(
                  onPressed: onCategories,
                  style: OutlinedButton.styleFrom(
                    foregroundColor: OvanieColors.navy,
                    side: const BorderSide(color: OvanieColors.navy, width: 1.2),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(7)),
                  ),
                  child: const Text('Voir les catégories', style: TextStyle(fontWeight: FontWeight.w800)),
                ),
              ),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: SizedBox(
                height: 52,
                child: FilledButton(
                  onPressed: onProducts,
                  style: FilledButton.styleFrom(
                    backgroundColor: OvanieColors.orange,
                    foregroundColor: Colors.white,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(7)),
                  ),
                  child: const FittedBox(
                    fit: BoxFit.scaleDown,
                    child: Text(
                      'Découvrir nos produits',
                      maxLines: 1,
                      softWrap: false,
                      style: TextStyle(fontWeight: FontWeight.w800),
                    ),
                  ),
                ),
              ),
            ),
          ],
        ),
        const SizedBox(height: 38),
        _OrdersHelpCard(onContact: onContact),
      ],
    );
  }
}

class _OrdersHelpCard extends StatelessWidget {
  final VoidCallback onContact;
  const _OrdersHelpCard({required this.onContact});

  @override
  Widget build(BuildContext context) => const SizedBox.shrink();
}

class _SearchResultChip extends StatelessWidget {
  final String query;
  final VoidCallback onClear;
  const _SearchResultChip({required this.query, required this.onClear});

  @override
  Widget build(BuildContext context) {
    return Align(
      alignment: Alignment.centerLeft,
      child: InputChip(
        label: Text('Recherche : $query'),
        onDeleted: onClear,
        deleteIcon: const Icon(Icons.close_rounded, size: 17),
      ),
    );
  }
}

String _formatDateTime(DateTime? value) {
  if (value == null) return '';
  return '${_formatDate(value)} à ${value.hour.toString().padLeft(2, '0')}:${value.minute.toString().padLeft(2, '0')}';
}

String _formatDate(DateTime value) {
  const months = <String>[
    'janvier',
    'février',
    'mars',
    'avril',
    'mai',
    'juin',
    'juillet',
    'août',
    'septembre',
    'octobre',
    'novembre',
    'décembre',
  ];
  return '${value.day} ${months[value.month - 1]} ${value.year}';
}
