import 'package:flutter/material.dart';

import '../../../app/theme.dart';
import '../../../core/config/app_config.dart';
import '../../../core/network/api_client.dart';
import '../../../core/platform/external_url_launcher.dart';
import '../../../core/sync/live_sync_service.dart';
import '../../../shared/widgets/ovanie_bottom_navigation.dart';
import '../../cart/domain/cart_store.dart';
import '../../orders/presentation/order_detail_screen.dart';
import '../../orders/presentation/orders_screen.dart';
import '../../payments/presentation/payment_ui.dart';
import '../../returns/presentation/returns_screen.dart';
import '../../shell/domain/main_navigation_store.dart';
import '../../support/presentation/support_center_screen.dart';
import '../../tracking/presentation/client_delivery_tracking_screen.dart';
import '../data/client_account_repository.dart';
import '../domain/account_models.dart';

const _navy = Color(0xFF0A1B3D);
const _muted = Color(0xFF6B7890);
const _line = Color(0xFFE7ECF2);

enum _Kind { order, payment, delivery, promotion, support, refund, other }

_Kind _kindOf(String rawCategory) {
  final c = rawCategory.toLowerCase().trim();
  if (c.contains('refund') || c.contains('rembours') || c.contains('return') || c.contains('retour')) {
    return _Kind.refund;
  }
  if (c.contains('deliver') || c.contains('livraison') || c.contains('shipping')) return _Kind.delivery;
  if (c.contains('payment') || c.contains('paiement')) return _Kind.payment;
  if (c.contains('order') || c.contains('commande')) return _Kind.order;
  if (c.contains('promo') || c.contains('offer') || c.contains('black')) return _Kind.promotion;
  if (c.contains('support') || c.contains('ticket') || c.contains('message')) return _Kind.support;
  return _Kind.other;
}

class NotificationsScreen extends StatefulWidget {
  final VoidCallback? onOpenHome;
  final VoidCallback? onOpenCategories;
  final VoidCallback? onOpenCart;
  final VoidCallback? onOpenFavorites;

  const NotificationsScreen({super.key, this.onOpenHome, this.onOpenCategories, this.onOpenCart, this.onOpenFavorites});

  @override
  State<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends State<NotificationsScreen> with LiveSyncListener<NotificationsScreen> {
  final _repository = const ClientAccountRepository();
  NotificationPageData? _data;
  bool _loading = true;
  bool _mutating = false;
  String? _error;
  String _selected = 'all';

  static const _filters = <(String, String)>[
    ('all', 'Toutes'),
    ('orders', 'Commandes'),
    ('payments', 'Paiements'),
    ('deliveries', 'Livraison'),
    ('promotions', 'Promotions'),
    ('support', 'Support'),
  ];

  @override
  void initState() {
    super.initState();
    _load();
    listenLiveSync({'notifications'}, () {
      if (!_mutating) _load(silent: true);
    });
  }

  Future<void> _load({bool silent = false}) async {
    if (mounted && (!silent || _data == null)) {
      setState(() {
        _loading = _data == null;
        _error = null;
      });
    }
    try {
      final data = await _repository.notifications();
      if (!mounted) return;
      setState(() {
        _data = data;
        _loading = false;
        _error = null;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _loading = false;
        _error = ApiClient.friendlyError(error);
      });
    }
  }

  List<ClientNotificationItem> get _visibleItems {
    final items = _data?.items ?? const <ClientNotificationItem>[];
    if (_selected == 'all') return items;
    return items.where((item) => _matches(_kindOf(item.category), _selected)).toList(growable: false);
  }

  bool _matches(_Kind kind, String filter) => switch (filter) {
    'orders' => kind == _Kind.order,
    'payments' => kind == _Kind.payment || kind == _Kind.refund,
    'deliveries' => kind == _Kind.delivery,
    'promotions' => kind == _Kind.promotion,
    'support' => kind == _Kind.support,
    _ => true,
  };

  void _markLocallyRead(String id) {
    final data = _data;
    if (data == null) return;
    final items = [
      for (final item in data.items)
        item.id == id && !item.isRead
            ? ClientNotificationItem(
                id: item.id,
                title: item.title,
                message: item.message,
                category: item.category,
                url: item.url,
                orderId: item.orderId,
                readAt: DateTime.now(),
                createdAt: item.createdAt,
              )
            : item,
    ];
    final unread = items.where((item) => !item.isRead).length;
    setState(
      () => _data = NotificationPageData(
        items: items,
        unreadCount: unread,
        currentPage: data.currentPage,
        lastPage: data.lastPage,
      ),
    );
  }

  Future<void> _openNotification(ClientNotificationItem item) async {
    if (!item.isRead) {
      _markLocallyRead(item.id);
      _repository.markNotificationRead(item.id).catchError((_) {});
    }
    final orderId = item.orderId;
    if (_kindOf(item.category) == _Kind.refund) {
      await Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => const ReturnsScreen()));
    } else if (orderId != null && orderId > 0) {
      final screen = _kindOf(item.category) == _Kind.delivery
          ? ClientDeliveryTrackingScreen(orderId: orderId)
          : OrderDetailScreen(orderId: orderId);
      await Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => screen));
    } else if ((item.url ?? '').trim().isNotEmpty) {
      final opened = await ExternalUrlLauncher.open(AppConfig.normalizeMediaUrl(item.url));
      if (!opened && mounted) showOvanieToast(context, 'Impossible d’ouvrir ce lien.', error: true);
    }
  }

  Future<void> _readAll() async {
    if (_mutating) return;
    setState(() => _mutating = true);
    try {
      await _repository.markAllNotificationsRead();
      await _load(silent: true);
      if (mounted) showOvanieToast(context, 'Toutes les notifications sont lues.');
    } catch (error) {
      if (mounted) showOvanieToast(context, ApiClient.friendlyError(error), error: true);
    } finally {
      if (mounted) setState(() => _mutating = false);
    }
  }

  void _goRoot(VoidCallback? callback) {
    if (callback == null) return;
    Navigator.of(context).popUntil((route) => route.isFirst);
    callback();
  }

  void _openOrders() {
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => OrdersScreen(onOpenCart: widget.onOpenCart, onStartShopping: widget.onOpenCategories),
      ),
    );
  }

  void _openSupport() {
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => SupportCenterScreen(
          onOpenHome: widget.onOpenHome,
          onOpenCategories: widget.onOpenCategories,
          onOpenCart: widget.onOpenCart,
          onOpenFavorites: widget.onOpenFavorites,
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: CartStore.instance,
      builder: (context, _) {
        final items = _visibleItems;
        final unread = _data?.unreadCount ?? 0;
        return Scaffold(
          backgroundColor: Colors.white,
          bottomNavigationBar: const OvanieBottomNavigation(selectedTab: OvanieMainTab.account),
          body: SafeArea(
            bottom: false,
            child: RefreshIndicator(
              color: OvanieColors.orange,
              onRefresh: _load,
              child: CustomScrollView(
                physics: const AlwaysScrollableScrollPhysics(),
                slivers: [
                  SliverToBoxAdapter(child: _buildHeader()),
                  SliverToBoxAdapter(child: _buildFilters()),
                  if (_loading)
                    const SliverFillRemaining(
                      hasScrollBody: false,
                      child: Center(child: CircularProgressIndicator(color: OvanieColors.orange)),
                    )
                  else if (_data == null)
                    SliverFillRemaining(
                      hasScrollBody: false,
                      child: _ErrorState(message: _error ?? 'Notifications indisponibles.', onRetry: _load),
                    )
                  else if (items.isEmpty)
                    SliverFillRemaining(
                      hasScrollBody: false,
                      child: _EmptyNotifications(
                        filtered: _selected != 'all',
                        onOrders: _openOrders,
                        onOffers: () => _goRoot(widget.onOpenCategories),
                      ),
                    )
                  else ...[
                    SliverToBoxAdapter(
                      child: Padding(
                        padding: const EdgeInsets.fromLTRB(16, 4, 10, 2),
                        child: Row(
                          children: [
                            Text(
                              '${items.length} notification${items.length > 1 ? 's' : ''}',
                              style: const TextStyle(color: _navy, fontSize: 15, fontWeight: FontWeight.w500),
                            ),
                            const Spacer(),
                            if (unread > 0)
                              TextButton(
                                onPressed: _mutating ? null : _readAll,
                                style: TextButton.styleFrom(
                                  foregroundColor: OvanieColors.orange,
                                  padding: const EdgeInsets.symmetric(horizontal: 6),
                                ),
                                child: const Text(
                                  'Tout marquer comme lu',
                                  style: TextStyle(fontSize: 14, fontWeight: FontWeight.w600),
                                ),
                              ),
                          ],
                        ),
                      ),
                    ),
                    SliverPadding(
                      padding: const EdgeInsets.fromLTRB(12, 6, 12, 0),
                      sliver: SliverList.separated(
                        itemCount: items.length,
                        separatorBuilder: (_, _) => const SizedBox(height: 8),
                        itemBuilder: (context, index) =>
                            _NotificationCard(item: items[index], onTap: () => _openNotification(items[index])),
                      ),
                    ),
                    SliverToBoxAdapter(
                      child: Padding(
                        padding: const EdgeInsets.fromLTRB(12, 12, 12, 18),
                        child: _HelpCard(onContact: _openSupport),
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ),
        );
      },
    );
  }

  Widget _buildHeader() {
    return Padding(
      padding: const EdgeInsets.fromLTRB(4, 8, 16, 0),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          IconButton(
            onPressed: () => Navigator.maybePop(context),
            tooltip: 'Retour',
            icon: const Icon(Icons.arrow_back_ios_new_rounded, color: _navy, size: 22),
          ),
          const SizedBox(width: 2),
          const Expanded(
            child: Padding(
              padding: EdgeInsets.only(top: 6),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Notifications',
                    style: TextStyle(color: _navy, fontSize: 24, fontWeight: FontWeight.w800, letterSpacing: -0.3),
                  ),
                  SizedBox(height: 2),
                  Text(
                    'Messages, alertes et mises à jour de votre compte.',
                    style: TextStyle(color: _muted, fontSize: 13, height: 1.35),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildFilters() {
    return SizedBox(
      height: 58,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.fromLTRB(12, 12, 12, 8),
        itemCount: _filters.length,
        separatorBuilder: (_, _) => const SizedBox(width: 8),
        itemBuilder: (context, index) {
          final (key, label) = _filters[index];
          final selected = key == _selected;
          return Material(
            color: selected ? OvanieColors.navy : const Color(0xFFF0F2F5),
            borderRadius: BorderRadius.circular(99),
            child: InkWell(
              onTap: () => setState(() => _selected = key),
              borderRadius: BorderRadius.circular(99),
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                child: Center(
                  child: Text(
                    label,
                    style: TextStyle(
                      color: selected ? Colors.white : _navy,
                      fontSize: 13.5,
                      fontWeight: selected ? FontWeight.w700 : FontWeight.w500,
                    ),
                  ),
                ),
              ),
            ),
          );
        },
      ),
    );
  }
}

class _KindStyle {
  final IconData icon;
  final Color color;

  const _KindStyle(this.icon, this.color);
}

_KindStyle _styleOf(_Kind kind) => switch (kind) {
  _Kind.order => const _KindStyle(Icons.inventory_2_outlined, Color(0xFF1463E0)),
  _Kind.payment || _Kind.refund => const _KindStyle(Icons.payment_outlined, Color(0xFF138A4B)),
  _Kind.delivery => const _KindStyle(Icons.local_shipping_outlined, OvanieColors.orange),
  _Kind.promotion => const _KindStyle(Icons.local_offer_outlined, Color(0xFFD32F2F)),
  _Kind.support => const _KindStyle(Icons.support_agent_rounded, Color(0xFF6B7280)),
  _Kind.other => const _KindStyle(Icons.notifications_none_rounded, Color(0xFF3B4A60)),
};

String _relativeTime(DateTime? value) {
  if (value == null) return '';
  final local = value.toLocal();
  final diff = DateTime.now().difference(local);
  if (diff.inMinutes < 1) return 'À l’instant';
  if (diff.inMinutes < 60) return 'Il y a ${diff.inMinutes} min';
  if (diff.inHours < 24) return 'Il y a ${diff.inHours} h';
  if (diff.inDays == 1) return 'Il y a 1 jour';
  if (diff.inDays < 7) return 'Il y a ${diff.inDays} jours';
  return paymentShortDate(local);
}

class _NotificationCard extends StatelessWidget {
  const _NotificationCard({required this.item, required this.onTap});

  final ClientNotificationItem item;
  final VoidCallback onTap;

  String? _action(_Kind kind, bool linked) {
    if (!linked) return null;
    if (item.orderId != null && item.orderId! > 0) {
      if (kind == _Kind.delivery) return 'Suivre';
      if (kind == _Kind.order) return 'Voir la commande';
    }
    return 'Voir le détail';
  }

  @override
  Widget build(BuildContext context) {
    final kind = _kindOf(item.category);
    final style = _styleOf(kind);
    final unread = !item.isRead;
    final linked = (item.orderId ?? 0) > 0 || (item.url ?? '').trim().isNotEmpty;
    final action = _action(kind, linked);

    return Material(
      color: unread ? const Color(0xFFF1F6FE) : Colors.white,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
        side: BorderSide(color: unread ? const Color(0xFFD9E6FA) : _line),
      ),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: linked || unread ? onTap : null,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(10, 12, 10, 10),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 50,
                height: 50,
                margin: const EdgeInsets.only(top: 2),
                decoration: BoxDecoration(color: style.color, shape: BoxShape.circle),
                child: Icon(style.icon, color: Colors.white, size: 25),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Expanded(
                          child: Text(
                            item.title,
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(color: _navy, fontSize: 15, fontWeight: FontWeight.w700),
                          ),
                        ),
                        const SizedBox(width: 8),
                        Text(_relativeTime(item.createdAt), style: const TextStyle(color: _muted, fontSize: 12)),
                        if (unread) ...[
                          const SizedBox(width: 6),
                          Container(
                            width: 8,
                            height: 8,
                            margin: const EdgeInsets.only(top: 4),
                            decoration: const BoxDecoration(color: OvanieColors.orange, shape: BoxShape.circle),
                          ),
                        ],
                      ],
                    ),
                    if (item.message.trim().isNotEmpty) ...[
                      const SizedBox(height: 3),
                      Text(
                        item.message,
                        maxLines: 3,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(color: _muted, fontSize: 13, height: 1.38),
                      ),
                    ],
                    if (action != null)
                      Align(
                        alignment: Alignment.centerRight,
                        child: Padding(
                          padding: const EdgeInsets.only(top: 4),
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Text(
                                action,
                                style: const TextStyle(
                                  color: OvanieColors.orange,
                                  fontSize: 13,
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                              const Icon(Icons.chevron_right_rounded, color: OvanieColors.orange, size: 18),
                            ],
                          ),
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
}

class _HelpCard extends StatelessWidget {
  const _HelpCard({required this.onContact});

  final VoidCallback onContact;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(12, 12, 12, 12),
      decoration: BoxDecoration(
        color: const Color(0xFFFFF8F2),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0xFFFBE3CF)),
      ),
      child: Row(
        children: [
          Container(
            width: 44,
            height: 44,
            decoration: const BoxDecoration(color: Color(0xFFF2F4F7), shape: BoxShape.circle),
            child: const Icon(Icons.headset_mic_outlined, color: _navy, size: 23),
          ),
          Container(width: 1, height: 36, margin: const EdgeInsets.symmetric(horizontal: 10), color: _line),
          const Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Besoin d’aide ?',
                  style: TextStyle(color: _navy, fontSize: 14.5, fontWeight: FontWeight.w700),
                ),
                SizedBox(height: 2),
                Text(
                  'Notre équipe support est là pour vous répondre rapidement.',
                  style: TextStyle(color: _muted, fontSize: 12, height: 1.35),
                ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          OutlinedButton(
            onPressed: onContact,
            style: OutlinedButton.styleFrom(
              foregroundColor: OvanieColors.orange,
              backgroundColor: Colors.white,
              side: const BorderSide(color: OvanieColors.orange, width: 1.2),
              padding: const EdgeInsets.symmetric(horizontal: 12),
              minimumSize: const Size(0, 38),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
            ),
            child: const Text('Nous contacter', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700)),
          ),
        ],
      ),
    );
  }
}

class _EmptyNotifications extends StatelessWidget {
  const _EmptyNotifications({required this.filtered, required this.onOrders, required this.onOffers});

  final bool filtered;
  final VoidCallback onOrders;
  final VoidCallback onOffers;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(28, 12, 28, 28),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Image.asset('assets/images/checkout/notifications_empty.png', width: 170),
          const SizedBox(height: 26),
          Text(
            filtered ? 'Aucune notification ici' : 'Aucune notification',
            textAlign: TextAlign.center,
            style: const TextStyle(color: _navy, fontSize: 23, fontWeight: FontWeight.w800, letterSpacing: -0.3),
          ),
          const SizedBox(height: 10),
          Text(
            filtered
                ? 'Aucune notification ne correspond à ce filtre pour le moment.'
                : 'Vous n’avez aucune notification pour le moment. Les alertes sur vos commandes, paiements, '
                      'livraisons et promotions apparaîtront ici.',
            textAlign: TextAlign.center,
            style: const TextStyle(color: _muted, fontSize: 14.5, height: 1.45),
          ),
          const SizedBox(height: 24),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 14),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                SizedBox(
                  height: 46,
                  child: FilledButton(
                    onPressed: onOrders,
                    style: FilledButton.styleFrom(
                      backgroundColor: OvanieColors.orange,
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                    ),
                    child: const Text(
                      'Voir mes commandes',
                      style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700),
                    ),
                  ),
                ),
                const SizedBox(height: 10),
                SizedBox(
                  height: 46,
                  child: OutlinedButton(
                    onPressed: onOffers,
                    style: OutlinedButton.styleFrom(
                      foregroundColor: OvanieColors.orange,
                      side: const BorderSide(color: OvanieColors.orange, width: 1.2),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                    ),
                    child: const Text(
                      'Découvrir les offres',
                      style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _ErrorState extends StatelessWidget {
  const _ErrorState({required this.message, required this.onRetry});

  final String message;
  final Future<void> Function() onRetry;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.all(28),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          const Icon(Icons.cloud_off_rounded, color: _muted, size: 52),
          const SizedBox(height: 14),
          Text(
            message,
            textAlign: TextAlign.center,
            style: const TextStyle(color: _muted, height: 1.45),
          ),
          const SizedBox(height: 18),
          PaymentButton(label: 'Réessayer', onPressed: onRetry),
        ],
      ),
    );
  }
}
