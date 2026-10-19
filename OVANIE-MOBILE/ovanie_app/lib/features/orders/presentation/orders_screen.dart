import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../app/theme.dart';
import '../../../core/network/api_client.dart';
import '../../../core/sync/live_sync_service.dart';
import '../../../core/utils/formatters.dart';
import '../../../shared/widgets/auth_required_view.dart';
import '../../../shared/widgets/ovanie_bottom_navigation.dart';
import '../../shell/domain/main_navigation_store.dart';
import '../../auth/domain/session_store.dart';
import '../../payments/presentation/payment_ui.dart';
import '../../payments/presentation/resume_payment_screen.dart';
import '../../support/presentation/support_center_screen.dart';
import '../../tracking/presentation/client_delivery_tracking_screen.dart';
import '../data/orders_repository.dart';
import '../domain/order_model.dart';
import 'invoice_screen.dart';
import 'order_detail_screen.dart';

class OrdersScreen extends StatefulWidget {
  final VoidCallback? onOpenAccount;
  final VoidCallback? onOpenCart;
  final VoidCallback? onStartShopping;

  const OrdersScreen({super.key, this.onOpenAccount, this.onOpenCart, this.onStartShopping});

  @override
  State<OrdersScreen> createState() => _OrdersScreenState();
}

class _OrdersScreenState extends State<OrdersScreen> with LiveSyncListener<OrdersScreen> {
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
        builder: (_) => ClientDeliveryTrackingScreen(orderId: order.id, initialOrderNumber: order.orderNumber),
      ),
    );
  }

  Future<void> _showSearch() async {
    _searchController.text = _query;
    final value = await showModalBottomSheet<String>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(26))),
      builder: (sheetContext) => Padding(
        padding: EdgeInsets.fromLTRB(20, 10, 20, 12 + MediaQuery.viewInsetsOf(sheetContext).bottom),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const _SheetHandle(),
            _SheetTitle(title: 'Rechercher une commande', onClose: () => Navigator.of(sheetContext).pop()),
            const SizedBox(height: 14),
            TextField(
              controller: _searchController,
              autofocus: true,
              textInputAction: TextInputAction.search,
              onSubmitted: (value) => Navigator.of(sheetContext).pop(value),
              style: const TextStyle(color: payNavy, fontSize: 16),
              decoration: InputDecoration(
                hintText: 'N° de commande ou produit',
                hintStyle: const TextStyle(color: Color(0xFF8A93A8), fontSize: 16),
                prefixIcon: const Icon(Icons.search_rounded, color: payMuted, size: 26),
                contentPadding: const EdgeInsets.symmetric(vertical: 16),
                enabledBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                  borderSide: const BorderSide(color: Color(0xFFD5DCE6)),
                ),
                focusedBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                  borderSide: const BorderSide(color: OvanieColors.orange, width: 1.4),
                ),
              ),
            ),
            const SizedBox(height: 14),
            PaymentButton(label: 'Rechercher', onPressed: () => Navigator.of(sheetContext).pop(_searchController.text)),
            Center(
              child: TextButton(
                onPressed: () => Navigator.of(sheetContext).pop(''),
                child: const Text(
                  'Effacer',
                  style: TextStyle(
                    color: payMuted,
                    fontSize: 15.5,
                    decoration: TextDecoration.underline,
                    decorationColor: payMuted,
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
    if (value == null || !mounted) return;
    setState(() => _query = value.trim());
  }

  Future<void> _showFilters() async {
    final selected = await showModalBottomSheet<String>(
      context: context,
      useSafeArea: true,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(26))),
      builder: (sheetContext) => Padding(
        padding: const EdgeInsets.fromLTRB(20, 10, 20, 16),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const _SheetHandle(),
            _SheetTitle(title: 'Filtrer les commandes', onClose: () => Navigator.of(sheetContext).pop()),
            const SizedBox(height: 6),
            for (var i = 0; i < _tabs.length; i++)
              InkWell(
                onTap: () => Navigator.of(sheetContext).pop(_tabs[i].key),
                child: Container(
                  height: 62,
                  decoration: BoxDecoration(
                    border: i == _tabs.length - 1 ? null : const Border(bottom: BorderSide(color: payBorder)),
                  ),
                  child: Row(
                    children: [
                      const SizedBox(width: 8),
                      _Radio(selected: _selectedTab == _tabs[i].key),
                      const SizedBox(width: 22),
                      Text(
                        '${_tabs[i].label} (${_countFor(_tabs[i].key)})',
                        style: TextStyle(
                          color: payNavy,
                          fontSize: 18,
                          fontWeight: _selectedTab == _tabs[i].key ? FontWeight.w700 : FontWeight.w400,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
          ],
        ),
      ),
    );
    if (selected != null && mounted) setState(() => _selectedTab = selected);
  }

  void _openSupport() {
    Navigator.of(
      context,
    ).push(MaterialPageRoute<void>(builder: (_) => const SupportCenterScreen(initialCategory: 'orders')));
  }

  Future<void> _payOrder(MobileOrder order) async {
    await Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => ResumePaymentScreen(order: order)));
    if (mounted) await _loadOrders(silent: true);
  }

  @override
  Widget build(BuildContext context) {
    final authenticated = SessionStore.instance.isAuthenticated;
    final hasOrders = authenticated && _orders.isNotEmpty;
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.dark.copyWith(statusBarColor: Colors.transparent),
      child: Scaffold(
        backgroundColor: hasOrders || _loading ? Colors.white : const Color(0xFFF8F9FB),
        bottomNavigationBar: const OvanieBottomNavigation(selectedTab: OvanieMainTab.account),
        body: SafeArea(
          bottom: false,
          child: Column(
            children: [
              Container(
                color: Colors.white,
                child: Column(
                  children: [
                    _OrdersHeader(
                      onBack: _goBack,
                      onSearch: hasOrders ? _showSearch : null,
                      onFilter: hasOrders ? _showFilters : null,
                    ),
                    // Compteurs et onglets seulement s'il y a des commandes :
                    // un historique vide n'affiche jamais de chiffres.
                    if (hasOrders) ...[
                      _OrdersSummaryStrip(
                        total: _orders.length,
                        toPay: _countFor('to_pay'),
                        inProgress: _countFor('preparing') + _countFor('shipping'),
                        delivered: _countFor('delivered'),
                      ),
                      _OrdersTabs(
                        selected: _selectedTab,
                        countFor: _countFor,
                        onSelected: (value) => setState(() => _selectedTab = value),
                      ),
                    ],
                  ],
                ),
              ),
              Expanded(
                child: authenticated
                    ? _buildBody()
                    : AuthRequiredView(padding: const EdgeInsets.fromLTRB(30, 20, 30, 70), onOpenAccount: _openAccount),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildBody() {
    if (_loading) {
      return const Center(child: CircularProgressIndicator(color: OvanieColors.orange));
    }

    if (_error != null && _orders.isEmpty) {
      return RefreshIndicator(
        color: OvanieColors.orange,
        onRefresh: _loadOrders,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.fromLTRB(24, 90, 24, 32),
          children: [
            Center(
              child: Container(
                width: 100,
                height: 100,
                decoration: const BoxDecoration(color: paySoft, shape: BoxShape.circle),
                child: const Icon(Icons.cloud_off_rounded, color: Color(0xFF4A556E), size: 46),
              ),
            ),
            const SizedBox(height: 20),
            Text(
              _error!,
              textAlign: TextAlign.center,
              style: const TextStyle(color: payNavy, fontSize: 16),
            ),
            const SizedBox(height: 20),
            PaymentButton(label: 'Réessayer', onPressed: _loadOrders),
          ],
        ),
      );
    }

    final orders = _visibleOrders;
    if (orders.isEmpty) {
      return RefreshIndicator(
        color: OvanieColors.orange,
        onRefresh: _loadOrders,
        child: ColoredBox(
          color: const Color(0xFFF8F9FB),
          child: _OrdersEmptyState(
            filtered: _orders.isNotEmpty,
            onCategories: _startShopping,
            onProducts: _startShopping,
            onSupport: _openSupport,
          ),
        ),
      );
    }

    return RefreshIndicator(
      color: OvanieColors.orange,
      onRefresh: _loadOrders,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(parent: BouncingScrollPhysics()),
        padding: const EdgeInsets.fromLTRB(16, 14, 16, 20),
        children: [
          if (_query.isNotEmpty) ...[
            Align(
              alignment: Alignment.centerLeft,
              child: _SearchResultChip(query: _query, onClear: () => setState(() => _query = '')),
            ),
            const SizedBox(height: 12),
          ],
          for (final order in orders)
            Padding(
              padding: const EdgeInsets.only(bottom: 14),
              child: _OrderCard(
                order: order,
                state: _stateOf(order),
                onOpen: () => _openOrder(order),
                onPay: () => _payOrder(order),
                onTrack: () => _openTracking(order),
                onInvoice: () => Navigator.of(context).push(
                  MaterialPageRoute<void>(
                    builder: (_) => InvoiceScreen(orderId: order.id, initialOrder: order),
                  ),
                ),
              ),
            ),
          _SupportLine(onTap: _openSupport),
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
}

class _OrdersTabData {
  final String key;
  final String label;
  const _OrdersTabData(this.key, this.label);
}

class _OrdersHeader extends StatelessWidget {
  final VoidCallback onBack;
  final VoidCallback? onSearch;
  final VoidCallback? onFilter;

  const _OrdersHeader({required this.onBack, required this.onSearch, required this.onFilter});

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 64,
      child: Row(
        children: [
          const SizedBox(width: 4),
          IconButton(
            onPressed: onBack,
            tooltip: 'Retour',
            icon: const Icon(Icons.chevron_left_rounded, color: payNavy, size: 36),
          ),
          const Expanded(
            child: Text(
              'Mes commandes',
              textAlign: TextAlign.center,
              style: TextStyle(color: payNavy, fontSize: 23, fontWeight: FontWeight.w800),
            ),
          ),
          SizedBox(
            width: 96,
            child: Row(
              mainAxisAlignment: MainAxisAlignment.end,
              children: [
                if (onFilter != null)
                  IconButton(
                    onPressed: onFilter,
                    tooltip: 'Filtrer',
                    icon: const Icon(Icons.tune_rounded, color: payNavy, size: 26),
                  ),
                if (onSearch != null)
                  IconButton(
                    onPressed: onSearch,
                    tooltip: 'Rechercher',
                    icon: const Icon(Icons.search_rounded, color: payNavy, size: 30),
                  ),
              ],
            ),
          ),
          const SizedBox(width: 4),
        ],
      ),
    );
  }
}

class _OrdersSummaryStrip extends StatelessWidget {
  final int total;
  final int toPay;
  final int inProgress;
  final int delivered;

  const _OrdersSummaryStrip({
    required this.total,
    required this.toPay,
    required this.inProgress,
    required this.delivered,
  });

  @override
  Widget build(BuildContext context) {
    final items = [(total, 'Total commandes'), (toPay, 'À payer'), (inProgress, 'En cours'), (delivered, 'Livrées')];
    return Container(
      margin: const EdgeInsets.fromLTRB(16, 0, 16, 8),
      padding: const EdgeInsets.symmetric(vertical: 14),
      decoration: BoxDecoration(color: paySoft, borderRadius: BorderRadius.circular(14)),
      child: Row(
        children: [
          for (var i = 0; i < items.length; i++) ...[
            Expanded(
              child: Column(
                children: [
                  Text(
                    '${items[i].$1}',
                    style: const TextStyle(color: payNavy, fontSize: 24, fontWeight: FontWeight.w900),
                  ),
                  const SizedBox(height: 2),
                  FittedBox(
                    fit: BoxFit.scaleDown,
                    child: Text(items[i].$2, maxLines: 1, style: const TextStyle(color: payMuted, fontSize: 13.5)),
                  ),
                ],
              ),
            ),
            if (i != items.length - 1) Container(width: 1, height: 44, color: const Color(0xFFD9DFE8)),
          ],
        ],
      ),
    );
  }
}

class _OrdersTabs extends StatelessWidget {
  final String selected;
  final int Function(String key) countFor;
  final ValueChanged<String> onSelected;

  const _OrdersTabs({required this.selected, required this.countFor, required this.onSelected});

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: const BoxDecoration(
        border: Border(bottom: BorderSide(color: payBorder)),
      ),
      child: SizedBox(
        height: 50,
        child: ListView.separated(
          padding: const EdgeInsets.symmetric(horizontal: 16),
          scrollDirection: Axis.horizontal,
          itemCount: _OrdersScreenState._tabs.length,
          separatorBuilder: (_, __) => const SizedBox(width: 22),
          itemBuilder: (context, index) {
            final tab = _OrdersScreenState._tabs[index];
            final active = tab.key == selected;
            return InkWell(
              onTap: () => onSelected(tab.key),
              child: IntrinsicWidth(
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.end,
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text(
                      '${tab.label} (${countFor(tab.key)})',
                      style: TextStyle(
                        color: active ? payNavy : const Color(0xFF4A556E),
                        fontSize: 15,
                        fontWeight: active ? FontWeight.w800 : FontWeight.w400,
                      ),
                    ),
                    const SizedBox(height: 10),
                    Container(
                      height: 3,
                      decoration: BoxDecoration(
                        color: active ? OvanieColors.orange : Colors.transparent,
                        borderRadius: BorderRadius.circular(2),
                      ),
                    ),
                  ],
                ),
              ),
            );
          },
        ),
      ),
    );
  }
}

enum _OrderVisualState { toPay, preparing, shipping, delivered, cancelled }

class _OrderCard extends StatelessWidget {
  final MobileOrder order;
  final _OrderVisualState state;
  final VoidCallback onOpen;
  final VoidCallback onPay;
  final VoidCallback onTrack;
  final VoidCallback onInvoice;

  const _OrderCard({
    required this.order,
    required this.state,
    required this.onOpen,
    required this.onPay,
    required this.onTrack,
    required this.onInvoice,
  });

  int get _articles => order.items.length;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(14),
      child: InkWell(
        onTap: onOpen,
        borderRadius: BorderRadius.circular(14),
        child: Container(
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: payBorder),
          ),
          clipBehavior: Clip.antiAlias,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Container(
                color: paySoft,
                padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
                child: Row(
                  children: [
                    Expanded(
                      child: Text(
                        'N° ${order.orderNumber.isEmpty ? order.id : order.orderNumber}',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(color: payNavy, fontSize: 16, fontWeight: FontWeight.w800),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Text(
                      formatFcfa(order.total),
                      style: const TextStyle(color: payNavy, fontSize: 16, fontWeight: FontWeight.w800),
                    ),
                  ],
                ),
              ),
              Padding(
                padding: const EdgeInsets.fromLTRB(14, 12, 14, 14),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.center,
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              for (var i = 0; i < 3; i++) ...[
                                Expanded(
                                  child: AspectRatio(
                                    aspectRatio: 1,
                                    child: i < order.items.length
                                        ? OrderItemThumbnail(url: order.items[i].imageUrl, height: double.infinity)
                                        : const SizedBox.shrink(),
                                  ),
                                ),
                                if (i != 2) const SizedBox(width: 6),
                              ],
                            ],
                          ),
                          const SizedBox(height: 10),
                          Text(
                            '$_articles article${_articles > 1 ? 's' : ''}  ·  commandé le ${paymentShortDate(order.createdAt)}',
                            maxLines: 2,
                            style: const TextStyle(color: payMuted, fontSize: 12.5),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(child: _statePanel()),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _statePanel() {
    switch (state) {
      case _OrderVisualState.toPay:
        final due = order.outstandingAmount > 0 ? order.outstandingAmount : order.total;
        return Column(
          children: [
            Container(
              padding: const EdgeInsets.fromLTRB(10, 10, 10, 10),
              decoration: BoxDecoration(
                color: const Color(0xFFFFF6EA),
                borderRadius: BorderRadius.circular(10),
                border: Border.all(color: const Color(0xFFF5C98B)),
              ),
              child: Row(
                children: [
                  Container(
                    width: 30,
                    height: 30,
                    decoration: const BoxDecoration(color: Color(0xFFFFE3C7), shape: BoxShape.circle),
                    child: const Icon(Icons.priority_high_rounded, color: OvanieColors.orange, size: 22),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const FittedBox(
                          fit: BoxFit.scaleDown,
                          child: Text('Montant à régler', style: TextStyle(color: Color(0xFFC2410C), fontSize: 13)),
                        ),
                        FittedBox(
                          fit: BoxFit.scaleDown,
                          child: Text(
                            formatFcfa(due),
                            style: const TextStyle(color: Color(0xFFC2410C), fontSize: 19, fontWeight: FontWeight.w900),
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            if (order.actions.canResumePayment) ...[
              const SizedBox(height: 10),
              PaymentButton(label: 'Payer maintenant', onPressed: onPay, height: 44),
            ],
          ],
        );
      case _OrderVisualState.preparing:
        return Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const _StateChip(label: 'En préparation'),
            const SizedBox(height: 12),
            const _MiniSteps(),
          ],
        );
      case _OrderVisualState.shipping:
        final date = order.estimatedMaxDate ?? order.estimatedMinDate;
        return Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const _StateChip(label: 'En livraison'),
            const SizedBox(height: 10),
            Row(
              children: [
                const Icon(Icons.local_shipping_outlined, color: payNavy, size: 30),
                const SizedBox(width: 8),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'En route vers vous',
                        style: TextStyle(color: payNavy, fontSize: 14, fontWeight: FontWeight.w700),
                      ),
                      if (date != null)
                        Text(
                          'Arrivée prévue le ${paymentShortDate(date)}',
                          style: const TextStyle(color: payMuted, fontSize: 12),
                        ),
                    ],
                  ),
                ),
              ],
            ),
            if (order.canTrackDelivery) ...[
              const SizedBox(height: 10),
              PaymentButton(label: 'Suivre la livraison', outlined: true, onPressed: onTrack, height: 42),
            ],
          ],
        );
      case _OrderVisualState.delivered:
        return Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
              decoration: BoxDecoration(color: payGreenSoft, borderRadius: BorderRadius.circular(10)),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const Icon(Icons.check_circle_rounded, color: payGreenDark, size: 20),
                  const SizedBox(width: 6),
                  Flexible(
                    child: FittedBox(
                      fit: BoxFit.scaleDown,
                      child: Text(
                        order.deliveredAt == null ? 'Livrée' : 'Livrée le ${paymentShortDate(order.deliveredAt)}',
                        style: const TextStyle(color: payGreenDark, fontSize: 14),
                      ),
                    ),
                  ),
                ],
              ),
            ),
            if (order.actions.canDownloadInvoice) ...[
              const SizedBox(height: 8),
              InkWell(
                onTap: onInvoice,
                child: const Padding(
                  padding: EdgeInsets.symmetric(vertical: 4),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(Icons.description_outlined, color: payBlue, size: 22),
                      SizedBox(width: 6),
                      Text(
                        'Facture',
                        style: TextStyle(
                          color: payBlue,
                          fontSize: 15,
                          decoration: TextDecoration.underline,
                          decorationColor: payBlue,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ],
          ],
        );
      case _OrderVisualState.cancelled:
        return Container(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
          decoration: BoxDecoration(color: paySoft, borderRadius: BorderRadius.circular(10)),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.cancel_outlined, color: payMuted, size: 20),
              const SizedBox(width: 6),
              Flexible(
                child: FittedBox(
                  fit: BoxFit.scaleDown,
                  child: Text(
                    order.updatedAt == null ? 'Annulée' : 'Annulée le ${paymentShortDate(order.updatedAt)}',
                    style: const TextStyle(color: Color(0xFF4A556E), fontSize: 14),
                  ),
                ),
              ),
            ],
          ),
        );
    }
  }
}

class _StateChip extends StatelessWidget {
  final String label;

  const _StateChip({required this.label});

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
    decoration: BoxDecoration(color: const Color(0xFFE6F0FF), borderRadius: BorderRadius.circular(8)),
    child: Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          width: 8,
          height: 8,
          decoration: BoxDecoration(color: payBlue, borderRadius: BorderRadius.circular(2)),
        ),
        const SizedBox(width: 7),
        Text(
          label,
          style: const TextStyle(color: payBlue, fontSize: 13.5, fontWeight: FontWeight.w600),
        ),
      ],
    ),
  );
}

/// Confirmée ✓ — Préparation (en cours) — Expédition.
class _MiniSteps extends StatelessWidget {
  const _MiniSteps();

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Row(
          children: [
            Container(
              width: 26,
              height: 26,
              decoration: const BoxDecoration(color: payGreenDark, shape: BoxShape.circle),
              child: const Icon(Icons.check_rounded, color: Colors.white, size: 17),
            ),
            Expanded(child: Container(height: 2, color: payGreenDark)),
            Container(
              width: 26,
              height: 26,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: payNavy,
                border: Border.all(color: const Color(0xFFC9D3E6), width: 3),
              ),
            ),
            Expanded(child: Container(height: 2, color: const Color(0xFFDDE2EA))),
            Container(
              width: 26,
              height: 26,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: Colors.white,
                border: Border.all(color: const Color(0xFFC9D0DC), width: 1.5),
              ),
            ),
          ],
        ),
        const SizedBox(height: 4),
        const Row(
          children: [
            Expanded(
              child: FittedBox(
                fit: BoxFit.scaleDown,
                alignment: Alignment.centerLeft,
                child: Text('Confirmée', style: TextStyle(color: payMuted, fontSize: 10.5)),
              ),
            ),
            Expanded(
              child: FittedBox(
                fit: BoxFit.scaleDown,
                child: Text(
                  'Préparation',
                  style: TextStyle(color: payNavy, fontSize: 10.5, fontWeight: FontWeight.w700),
                ),
              ),
            ),
            Expanded(
              child: FittedBox(
                fit: BoxFit.scaleDown,
                alignment: Alignment.centerRight,
                child: Text('Expédition', style: TextStyle(color: payMuted, fontSize: 10.5)),
              ),
            ),
          ],
        ),
      ],
    );
  }
}

class _SupportLine extends StatelessWidget {
  final VoidCallback onTap;

  const _SupportLine({required this.onTap});

  @override
  Widget build(BuildContext context) => Material(
    color: Colors.white,
    borderRadius: BorderRadius.circular(14),
    child: InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(14),
      child: Container(
        padding: const EdgeInsets.fromLTRB(14, 12, 12, 12),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: payBorder),
        ),
        child: const Row(
          children: [
            CircleAvatar(
              radius: 24,
              backgroundColor: paySoft,
              child: Icon(Icons.headset_mic_outlined, color: payNavy, size: 26),
            ),
            SizedBox(width: 14),
            Expanded(
              child: Text(
                'Support OVANIE : 01 61 78 00 00',
                style: TextStyle(color: payNavy, fontSize: 15.5, fontWeight: FontWeight.w700),
              ),
            ),
            Icon(Icons.chevron_right_rounded, color: payNavy, size: 26),
          ],
        ),
      ),
    ),
  );
}

class _OrdersEmptyState extends StatelessWidget {
  final bool filtered;
  final VoidCallback onCategories;
  final VoidCallback onProducts;
  final VoidCallback onSupport;

  const _OrdersEmptyState({
    required this.filtered,
    required this.onCategories,
    required this.onProducts,
    required this.onSupport,
  });

  @override
  Widget build(BuildContext context) {
    final width = MediaQuery.sizeOf(context).width;
    return ListView(
      physics: const AlwaysScrollableScrollPhysics(parent: BouncingScrollPhysics()),
      padding: const EdgeInsets.fromLTRB(16, 30, 16, 20),
      children: [
        Center(
          child: SizedBox(
            width: (width * .78).clamp(220.0, 340.0),
            child: Image.asset('assets/images/checkout/orders_empty.png', fit: BoxFit.contain),
          ),
        ),
        const SizedBox(height: 26),
        Text(
          filtered ? 'Aucune commande dans cette catégorie' : 'Aucune commande pour le moment',
          textAlign: TextAlign.center,
          style: const TextStyle(color: payNavy, fontSize: 25, fontWeight: FontWeight.w900),
        ),
        const SizedBox(height: 12),
        Text(
          filtered
              ? 'Aucune commande ne correspond au filtre sélectionné.'
              : 'Vous n’avez pas encore passé de commande. Explorez nos catégories et démarrez votre prochain chantier avec OVANIE.',
          textAlign: TextAlign.center,
          style: const TextStyle(color: payMuted, fontSize: 16, height: 1.45),
        ),
        const SizedBox(height: 26),
        PaymentButton(label: 'Voir les catégories', onPressed: onCategories),
        const SizedBox(height: 12),
        PaymentButton(label: 'Découvrir nos produits', outlined: true, onPressed: onProducts),
        const SizedBox(height: 30),
        _SupportLine(onTap: onSupport),
      ],
    );
  }
}

class _SearchResultChip extends StatelessWidget {
  final String query;
  final VoidCallback onClear;

  const _SearchResultChip({required this.query, required this.onClear});

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.fromLTRB(16, 8, 8, 8),
    decoration: BoxDecoration(color: const Color(0xFFE6EDF8), borderRadius: BorderRadius.circular(20)),
    child: Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Text(
          'Recherche : $query',
          style: const TextStyle(color: payNavy, fontSize: 15, fontWeight: FontWeight.w600),
        ),
        const SizedBox(width: 6),
        InkWell(
          onTap: onClear,
          customBorder: const CircleBorder(),
          child: const Padding(
            padding: EdgeInsets.all(2),
            child: Icon(Icons.close_rounded, color: payNavy, size: 20),
          ),
        ),
      ],
    ),
  );
}

class _SheetHandle extends StatelessWidget {
  const _SheetHandle();

  @override
  Widget build(BuildContext context) => Center(
    child: Container(
      width: 46,
      height: 5,
      decoration: BoxDecoration(color: const Color(0xFFD0D6DF), borderRadius: BorderRadius.circular(3)),
    ),
  );
}

class _SheetTitle extends StatelessWidget {
  final String title;
  final VoidCallback onClose;

  const _SheetTitle({required this.title, required this.onClose});

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(top: 14),
    child: Row(
      children: [
        Expanded(
          child: Text(
            title,
            style: const TextStyle(color: payNavy, fontSize: 23, fontWeight: FontWeight.w800),
          ),
        ),
        IconButton(
          onPressed: onClose,
          tooltip: 'Fermer',
          icon: const Icon(Icons.close_rounded, color: payNavy, size: 28),
        ),
      ],
    ),
  );
}

class _Radio extends StatelessWidget {
  final bool selected;

  const _Radio({required this.selected});

  @override
  Widget build(BuildContext context) => Container(
    width: 28,
    height: 28,
    alignment: Alignment.center,
    decoration: BoxDecoration(
      shape: BoxShape.circle,
      border: Border.all(color: selected ? OvanieColors.orange : const Color(0xFF8A93A8), width: selected ? 2.2 : 1.6),
    ),
    child: selected
        ? Container(
            width: 16,
            height: 16,
            decoration: const BoxDecoration(color: OvanieColors.orange, shape: BoxShape.circle),
          )
        : null,
  );
}
