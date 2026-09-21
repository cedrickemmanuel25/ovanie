import 'package:flutter/material.dart';

import '../../../app/theme.dart';
import '../../../core/config/app_config.dart';
import '../../../core/network/api_client.dart';
import '../../../core/platform/external_url_launcher.dart';
import '../../../shared/widgets/ovanie_bottom_navigation.dart';
import '../../shell/domain/main_navigation_store.dart';
import '../../cart/domain/cart_store.dart';
import '../../orders/presentation/order_detail_screen.dart';
import '../../orders/presentation/orders_screen.dart';
import '../../support/presentation/support_center_screen.dart';
import '../data/client_account_repository.dart';
import '../domain/account_models.dart';

class NotificationsScreen extends StatefulWidget {
  final VoidCallback? onOpenHome;
  final VoidCallback? onOpenCategories;
  final VoidCallback? onOpenCart;
  final VoidCallback? onOpenFavorites;

  const NotificationsScreen({
    super.key,
    this.onOpenHome,
    this.onOpenCategories,
    this.onOpenCart,
    this.onOpenFavorites,
  });

  @override
  State<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends State<NotificationsScreen> {
  static const _navy = Color(0xFF071B53);
  static const _subtitle = Color(0xFF465A8A);
  static const _line = Color(0xFFE0E6F0);

  final _repository = const ClientAccountRepository();
  NotificationPageData? _data;
  bool _loading = true;
  bool _mutating = false;
  String? _error;
  String _selected = 'all';

  static const _filters = <_NotificationFilter>[
    _NotificationFilter('all', 'Toutes'),
    _NotificationFilter('orders', 'Commandes'),
    _NotificationFilter('payments', 'Paiements'),
    _NotificationFilter('deliveries', 'Livraison'),
    _NotificationFilter('promotions', 'Promotions'),
    _NotificationFilter('support', 'Support'),
  ];

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    if (mounted) {
      setState(() {
        _loading = true;
        _error = null;
      });
    }
    try {
      final data = await _repository.notifications();
      if (!mounted) return;
      setState(() {
        _data = data;
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

  List<ClientNotificationItem> get _visibleItems {
    final items = _data?.items ?? const <ClientNotificationItem>[];
    if (_selected == 'all') return items;
    return items.where((item) => _matches(item.category, _selected)).toList(growable: false);
  }

  bool _matches(String rawCategory, String filter) {
    final category = rawCategory.toLowerCase().trim();
    switch (filter) {
      case 'orders':
        return category.contains('order') || category.contains('commande');
      case 'payments':
        return category.contains('payment') || category.contains('paiement');
      case 'deliveries':
        return category.contains('deliver') || category.contains('livraison') || category.contains('shipping');
      case 'promotions':
        return category.contains('promo') || category.contains('offer') || category.contains('black');
      case 'support':
        return category.contains('support') || category.contains('ticket') || category.contains('message');
      default:
        return true;
    }
  }

  Future<void> _openNotification(ClientNotificationItem item) async {
    try {
      if (!item.isRead) await _repository.markNotificationRead(item.id);
      if (!mounted) return;

      if (item.orderId != null && item.orderId! > 0) {
        await Navigator.of(context).push(
          MaterialPageRoute<void>(builder: (_) => OrderDetailScreen(orderId: item.orderId!)),
        );
      } else if ((item.url ?? '').trim().isNotEmpty) {
        final opened = await ExternalUrlLauncher.open(AppConfig.normalizeMediaUrl(item.url));
        if (!opened && mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Impossible d’ouvrir ce lien.')),
          );
        }
      }
      await _load();
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(ApiClient.friendlyError(error))),
      );
    }
  }

  Future<void> _readAll() async {
    if (_mutating) return;
    setState(() => _mutating = true);
    try {
      await _repository.markAllNotificationsRead();
      await _load();
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(ApiClient.friendlyError(error))),
      );
    } finally {
      if (mounted) setState(() => _mutating = false);
    }
  }

  void _goRoot(VoidCallback? callback) {
    if (callback == null) return;
    Navigator.of(context).pop();
    callback();
  }

  void _openOrders() {
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => OrdersScreen(
          onOpenCart: widget.onOpenCart,
          onStartShopping: widget.onOpenCategories,
        ),
      ),
    );
  }

  void _openSupport() {
    Navigator.of(context).push(
      MaterialPageRoute<void>(builder: (_) => const SupportCenterScreen()),
    );
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: CartStore.instance,
      builder: (context, _) {
        return Scaffold(
          backgroundColor: Colors.white,
          bottomNavigationBar: const OvanieBottomNavigation(
            selectedTab: OvanieMainTab.account,
          ),
          body: SafeArea(
            bottom: false,
            child: RefreshIndicator(
              color: OvanieColors.orange,
              onRefresh: _load,
              child: CustomScrollView(
                physics: const AlwaysScrollableScrollPhysics(parent: BouncingScrollPhysics()),
                slivers: [
                  SliverToBoxAdapter(child: _buildHeader()),
                  SliverToBoxAdapter(child: _buildFilters()),
                  if (_loading)
                    const SliverToBoxAdapter(
                      child: SizedBox(
                        height: 420,
                        child: Center(child: CircularProgressIndicator(color: OvanieColors.orange)),
                      ),
                    )
                  else if (_error != null)
                    SliverToBoxAdapter(
                      child: Padding(
                        padding: const EdgeInsets.fromLTRB(28, 60, 28, 28),
                        child: Column(
                          children: [
                            const Icon(Icons.notifications_off_outlined, size: 54, color: OvanieColors.muted),
                            const SizedBox(height: 16),
                            Text(
                              _error!,
                              textAlign: TextAlign.center,
                              style: const TextStyle(color: _subtitle, fontSize: 14, height: 1.45),
                            ),
                            const SizedBox(height: 18),
                            OutlinedButton(onPressed: _load, child: const Text('Réessayer')),
                          ],
                        ),
                      ),
                    )
                  else if (_visibleItems.isEmpty)
                    SliverToBoxAdapter(child: _EmptyNotifications(onOrders: _openOrders, onOffers: () => _goRoot(widget.onOpenCategories), onSupport: _openSupport))
                  else ...[
                    SliverToBoxAdapter(child: _buildCountRow()),
                    SliverPadding(
                      padding: const EdgeInsets.fromLTRB(14, 10, 14, 18),
                      sliver: SliverList(
                        delegate: SliverChildBuilderDelegate(
                          (context, index) {
                            if (index.isOdd) return const SizedBox(height: 10);
                            final item = _visibleItems[index ~/ 2];
                            return _NotificationCard(
                              item: item,
                              onTap: () => _openNotification(item),
                            );
                          },
                          childCount: _visibleItems.length * 2 - 1,
                        ),
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
      padding: const EdgeInsets.fromLTRB(18, 16, 18, 0),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              IconButton(
                onPressed: () => Navigator.maybePop(context),
                padding: EdgeInsets.zero,
                constraints: const BoxConstraints.tightFor(width: 42, height: 42),
                icon: const Icon(Icons.arrow_back_rounded, size: 28, color: _navy),
              ),
              const SizedBox(width: 8),
              const Expanded(
                child: Text(
                  'Notifications',
                  style: TextStyle(
                    color: _navy,
                    fontSize: 27,
                    height: 1,
                    fontWeight: FontWeight.w900,
                    letterSpacing: -0.4,
                  ),
                ),
              ),
              TextButton(
                onPressed: _mutating ? null : _readAll,
                style: TextButton.styleFrom(
                  foregroundColor: OvanieColors.orange,
                  padding: const EdgeInsets.symmetric(horizontal: 2, vertical: 8),
                ),
                child: const Text('Tout lire', style: TextStyle(fontSize: 15, fontWeight: FontWeight.w600)),
              ),
            ],
          ),
          const Padding(
            padding: EdgeInsets.only(left: 50, top: 3),
            child: Text(
              'Messages, alertes et mises à jour de votre compte.',
              style: TextStyle(color: _subtitle, fontSize: 13.2, height: 1.35),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildFilters() {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 18, 0, 10),
      child: SizedBox(
        height: 48,
        child: ListView.separated(
          scrollDirection: Axis.horizontal,
          padding: const EdgeInsets.only(right: 16),
          itemCount: _filters.length,
          separatorBuilder: (_, __) => const SizedBox(width: 10),
          itemBuilder: (context, index) {
            final filter = _filters[index];
            final selected = filter.key == _selected;
            return InkWell(
              onTap: () => setState(() => _selected = filter.key),
              borderRadius: BorderRadius.circular(10),
              child: AnimatedContainer(
                duration: const Duration(milliseconds: 160),
                padding: const EdgeInsets.symmetric(horizontal: 18),
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: selected ? OvanieColors.orange : Colors.white,
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(color: selected ? OvanieColors.orange : _line),
                  boxShadow: selected
                      ? const [BoxShadow(color: Color(0x16000000), blurRadius: 8, offset: Offset(0, 2))]
                      : null,
                ),
                child: Text(
                  filter.label,
                  style: TextStyle(
                    color: selected ? Colors.white : _navy,
                    fontSize: 13.5,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            );
          },
        ),
      ),
    );
  }

  Widget _buildCountRow() {
    return Container(
      height: 57,
      margin: const EdgeInsets.fromLTRB(14, 4, 14, 0),
      padding: const EdgeInsets.symmetric(horizontal: 15),
      decoration: BoxDecoration(
        color: const Color(0xFFFBFCFF),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: _line),
      ),
      child: Row(
        children: [
          Text(
            '${_visibleItems.length} notification${_visibleItems.length > 1 ? 's' : ''}',
            style: const TextStyle(color: _navy, fontSize: 14, fontWeight: FontWeight.w700),
          ),
          const Spacer(),
          InkWell(
            onTap: _mutating ? null : _readAll,
            borderRadius: BorderRadius.circular(8),
            child: const Padding(
              padding: EdgeInsets.symmetric(horizontal: 4, vertical: 8),
              child: Row(
                children: [
                  Icon(Icons.check_circle_outline_rounded, size: 20, color: Color(0xFF104DDF)),
                  SizedBox(width: 8),
                  Text(
                    'Tout marquer comme lu',
                    style: TextStyle(color: Color(0xFF104DDF), fontSize: 13, fontWeight: FontWeight.w800),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _NotificationFilter {
  final String key;
  final String label;
  const _NotificationFilter(this.key, this.label);
}

class _NotificationCard extends StatelessWidget {
  final ClientNotificationItem item;
  final VoidCallback onTap;

  const _NotificationCard({required this.item, required this.onTap});

  static const _navy = Color(0xFF071B53);
  static const _muted = Color(0xFF4F628E);

  @override
  Widget build(BuildContext context) {
    final style = _styleFor(item.category, item.isRead);
    final action = _actionLabel(item);
    final clickable = item.orderId != null || (item.url ?? '').trim().isNotEmpty;
    final narrow = MediaQuery.sizeOf(context).width < 600;

    return Material(
      color: style.background,
      borderRadius: BorderRadius.circular(14),
      child: InkWell(
        onTap: clickable ? onTap : (item.isRead ? null : onTap),
        borderRadius: BorderRadius.circular(14),
        child: Container(
          constraints: const BoxConstraints(minHeight: 112),
          padding: const EdgeInsets.fromLTRB(10, 12, 10, 12),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: style.border),
          ),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              Container(
                width: 8,
                alignment: Alignment.center,
                child: Container(
                  width: 7,
                  height: 7,
                  decoration: BoxDecoration(
                    color: item.isRead ? const Color(0xFFA8A8A8) : style.accent,
                    shape: BoxShape.circle,
                  ),
                ),
              ),
              const SizedBox(width: 10),
              Container(
                width: 54,
                height: 54,
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(15),
                  boxShadow: const [BoxShadow(color: Color(0x0B000000), blurRadius: 10, offset: Offset(0, 3))],
                ),
                child: Icon(style.icon, size: 29, color: style.accent),
              ),
              const SizedBox(width: 15),
              Expanded(
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      item.title,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(color: _navy, fontSize: 15.2, height: 1.18, fontWeight: FontWeight.w900),
                    ),
                    const SizedBox(height: 5),
                    Text(
                      item.message,
                      maxLines: 3,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(color: _muted, fontSize: 12.7, height: 1.35, fontWeight: FontWeight.w500),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      _relativeTime(item.createdAt),
                      style: const TextStyle(color: _muted, fontSize: 11.5, fontWeight: FontWeight.w500),
                    ),
                    if (narrow && action != null && clickable) ...[
                      const SizedBox(height: 9),
                      Align(
                        alignment: Alignment.centerRight,
                        child: OutlinedButton(
                          onPressed: onTap,
                          style: OutlinedButton.styleFrom(
                            foregroundColor: style.actionColor,
                            side: BorderSide(color: style.actionColor, width: 1.1),
                            minimumSize: const Size(0, 34),
                            padding: const EdgeInsets.symmetric(horizontal: 12),
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                          ),
                          child: Text(action, style: const TextStyle(fontSize: 10.8, fontWeight: FontWeight.w800)),
                        ),
                      ),
                    ],
                  ],
                ),
              ),
              if (!narrow) const SizedBox(width: 10),
              if (!narrow && action != null && clickable)
                OutlinedButton(
                  onPressed: onTap,
                  style: OutlinedButton.styleFrom(
                    foregroundColor: style.actionColor,
                    side: BorderSide(color: style.actionColor, width: 1.2),
                    minimumSize: const Size(92, 38),
                    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(9)),
                  ),
                  child: Text(action, style: const TextStyle(fontSize: 11.8, fontWeight: FontWeight.w800)),
                )
              else if (!narrow && _isPayment(item.category))
                Icon(Icons.check_circle_outline_rounded, size: 29, color: style.accent)
              else if (!narrow && clickable)
                Icon(Icons.chevron_right_rounded, size: 28, color: _navy),
            ],
          ),
        ),
      ),
    );
  }

  static bool _isPayment(String raw) {
    final c = raw.toLowerCase();
    return c.contains('payment') || c.contains('paiement');
  }

  static String? _actionLabel(ClientNotificationItem item) {
    final c = item.category.toLowerCase();
    if (c.contains('order') || c.contains('commande')) return 'Voir la commande';
    if (c.contains('deliver') || c.contains('livraison')) return 'Suivre';
    if (c.contains('return') || c.contains('retour')) return 'Voir le détail';
    return null;
  }

  static _CardStyle _styleFor(String raw, bool read) {
    final c = raw.toLowerCase();
    if (c.contains('deliver') || c.contains('livraison')) {
      return const _CardStyle(
        icon: Icons.local_shipping_outlined,
        accent: Color(0xFF1057DE),
        border: Color(0xFFCFE0FF),
        background: Color(0xFFF6FAFF),
        actionColor: Color(0xFF1057DE),
      );
    }
    if (c.contains('payment') || c.contains('paiement')) {
      return const _CardStyle(
        icon: Icons.credit_card_rounded,
        accent: Color(0xFF0BB85C),
        border: Color(0xFFD6EBDD),
        background: Color(0xFFF8FCF9),
        actionColor: Color(0xFF0BB85C),
      );
    }
    if (c.contains('promo') || c.contains('offer') || c.contains('black')) {
      return const _CardStyle(
        icon: Icons.local_offer_outlined,
        accent: Color(0xFF7035F3),
        border: Color(0xFFE5DDFB),
        background: Color(0xFFFBFAFF),
        actionColor: Color(0xFF7035F3),
      );
    }
    if (c.contains('support') || c.contains('ticket') || c.contains('message')) {
      return const _CardStyle(
        icon: Icons.headset_mic_outlined,
        accent: Color(0xFF081F5B),
        border: Color(0xFFE3E7EE),
        background: Colors.white,
        actionColor: Color(0xFF081F5B),
      );
    }
    if (c.contains('account') || c.contains('address') || c.contains('adresse')) {
      return const _CardStyle(
        icon: Icons.location_on_outlined,
        accent: Color(0xFF0CB25B),
        border: Color(0xFFE2E8EE),
        background: Colors.white,
        actionColor: Color(0xFF0CB25B),
      );
    }
    if (c.contains('return') || c.contains('retour') || c.contains('refund') || c.contains('rembours')) {
      return const _CardStyle(
        icon: Icons.sync_rounded,
        accent: OvanieColors.orange,
        border: Color(0xFFFFD8BE),
        background: Color(0xFFFFFBF8),
        actionColor: OvanieColors.orange,
      );
    }
    return const _CardStyle(
      icon: Icons.assignment_outlined,
      accent: OvanieColors.orange,
      border: Color(0xFFFFD8BE),
      background: Color(0xFFFFFBF8),
      actionColor: OvanieColors.orange,
    );
  }

  static String _relativeTime(DateTime? value) {
    if (value == null) return '';
    final diff = DateTime.now().difference(value.toLocal());
    if (diff.inMinutes < 1) return 'À l’instant';
    if (diff.inMinutes < 60) return 'Il y a ${diff.inMinutes} min';
    if (diff.inHours < 24) return 'Il y a ${diff.inHours} h';
    if (diff.inDays == 1) return 'Il y a 1 jour';
    if (diff.inDays < 30) return 'Il y a ${diff.inDays} jours';
    return '${value.day.toString().padLeft(2, '0')}/${value.month.toString().padLeft(2, '0')}/${value.year}';
  }
}

class _CardStyle {
  final IconData icon;
  final Color accent;
  final Color border;
  final Color background;
  final Color actionColor;

  const _CardStyle({
    required this.icon,
    required this.accent,
    required this.border,
    required this.background,
    required this.actionColor,
  });
}

class _EmptyNotifications extends StatelessWidget {
  final VoidCallback onOrders;
  final VoidCallback onOffers;
  final VoidCallback onSupport;

  const _EmptyNotifications({
    required this.onOrders,
    required this.onOffers,
    required this.onSupport,
  });

  static const _navy = Color(0xFF071B53);
  static const _muted = Color(0xFF4F628E);

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 20, 16, 26),
      child: Column(
        children: [
          SizedBox(
            height: 275,
            width: double.infinity,
            child: Image.asset(
              'assets/images/notifications_empty_illustration.png',
              fit: BoxFit.contain,
              alignment: Alignment.center,
              errorBuilder: (_, __, ___) => const Icon(Icons.notifications_none_rounded, size: 130, color: Color(0xFFDCE5F7)),
            ),
          ),
          const SizedBox(height: 8),
          const Text(
            'Aucune notification',
            textAlign: TextAlign.center,
            style: TextStyle(color: _navy, fontSize: 25, height: 1.1, fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 15),
          const Padding(
            padding: EdgeInsets.symmetric(horizontal: 26),
            child: Text(
              'Vous n’avez aucune notification pour le moment.\nLes alertes sur vos commandes, paiements,\nlivraisons et promotions apparaîtront ici.',
              textAlign: TextAlign.center,
              style: TextStyle(color: _muted, fontSize: 14.5, height: 1.5, fontWeight: FontWeight.w500),
            ),
          ),
          const SizedBox(height: 24),
          Row(
            children: [
              Expanded(
                child: SizedBox(
                  height: 50,
                  child: OutlinedButton(
                    onPressed: onOrders,
                    style: OutlinedButton.styleFrom(
                      foregroundColor: _navy,
                      side: const BorderSide(color: _navy, width: 1.2),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                    ),
                    child: const FittedBox(
                      fit: BoxFit.scaleDown,
                      child: Text(
                        'Voir mes commandes',
                        maxLines: 1,
                        softWrap: false,
                        style: TextStyle(fontSize: 13.8, fontWeight: FontWeight.w800),
                      ),
                    ),
                  ),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: SizedBox(
                  height: 50,
                  child: FilledButton(
                    onPressed: onOffers,
                    style: FilledButton.styleFrom(
                      backgroundColor: OvanieColors.orange,
                      foregroundColor: Colors.white,
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                    ),
                    child: const Text('Découvrir les offres', style: TextStyle(fontSize: 13.8, fontWeight: FontWeight.w800)),
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 52),
          Container(
            width: double.infinity,
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 16),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: const Color(0xFFE0E6F0)),
              boxShadow: const [BoxShadow(color: Color(0x08000000), blurRadius: 12, offset: Offset(0, 3))],
            ),
            child: Row(
              children: [
                Container(
                  width: 58,
                  height: 58,
                  decoration: BoxDecoration(
                    color: const Color(0xFFF4F1FF),
                    borderRadius: BorderRadius.circular(14),
                  ),
                  child: const Icon(Icons.headset_mic_outlined, color: _navy, size: 31),
                ),
                const SizedBox(width: 16),
                const Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('Besoin d’aide ?', style: TextStyle(color: _navy, fontSize: 15, fontWeight: FontWeight.w900)),
                      SizedBox(height: 5),
                      Text(
                        'Notre équipe support est là pour vous\nrépondre rapidement.',
                        style: TextStyle(color: _muted, fontSize: 12.8, height: 1.4),
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 10),
                OutlinedButton(
                  onPressed: onSupport,
                  style: OutlinedButton.styleFrom(
                    foregroundColor: OvanieColors.orange,
                    side: const BorderSide(color: OvanieColors.orange),
                    minimumSize: const Size(116, 43),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(9)),
                  ),
                  child: const Text('Nous contacter', style: TextStyle(fontSize: 12.5, fontWeight: FontWeight.w800)),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
