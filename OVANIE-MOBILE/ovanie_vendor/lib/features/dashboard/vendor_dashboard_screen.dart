import 'dart:math' as math;
import '../../core/sync/live_sync_service.dart';

import 'package:flutter/material.dart';

import '../../core/network/api_client.dart';
import '../../core/ui/vendor_design.dart';
import '../../core/ui/vendor_mockup_ui.dart';
import '../../core/utils/formatters.dart';
import '../../data/vendor_repository.dart';
import '../after_sales/after_sales_screen.dart';
import '../auth/vendor_session.dart';
import '../notifications/vendor_notifications_screen.dart';
import '../orders/order_detail_screen.dart';
import '../products/product_form_screen.dart';
import '../shop/shop_screen.dart';
import '../shell/vendor_tabs.dart';

class VendorDashboardScreen extends StatefulWidget {
  const VendorDashboardScreen({super.key, required this.onSelectTab});

  final ValueChanged<VendorTab> onSelectTab;

  @override
  State<VendorDashboardScreen> createState() => _VendorDashboardScreenState();
}

class _VendorDashboardScreenState extends State<VendorDashboardScreen>
    with LiveSyncListener<VendorDashboardScreen> {
  Map<String, dynamic> _data = {};
  bool _loading = true;
  String? _error;
  int _notificationCount = 0;

  @override
  void initState() {
    super.initState();
    _load();
    listenLiveSync({'shop', 'notifications'}, () {
      if (!_loading) _load();
    });
  }

  Future<void> _load() async {
    if (mounted) {
      setState(() {
        _loading = true;
        _error = null;
      });
    }

    try {
      final dashboard = await VendorRepository.instance.dashboard();
      var unread = int.tryParse('${dashboard['unread_notifications_count'] ?? 0}') ?? 0;

      // Compatibilité avec un backend qui n'a pas encore reçu le champ
      // unread_notifications_count : on interroge alors l'endpoint réel des
      // notifications au lieu d'utiliser les alertes du dashboard.
      try {
        final notifications = await VendorRepository.instance.notifications(page: 1);
        unread = int.tryParse('${notifications['unread_count'] ?? unread}') ?? unread;
      } catch (_) {
        // Le dashboard reste utilisable même si le chargement de la cloche échoue.
      }

      _data = dashboard;
      _notificationCount = unread;
      if (_data['shop'] is Map) {
        VendorSession.instance.shop = Map<String, dynamic>.from(_data['shop'] as Map);
      }
    } catch (error) {
      _error = ApiClient.friendlyError(error);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  num _num(Map<String, dynamic> stats, String key) {
    final raw = stats[key];
    if (raw is num) return raw;
    return num.tryParse('${raw ?? 0}') ?? 0;
  }

  String _shortMoney(Object? value) {
    final amount = double.tryParse('${value ?? 0}') ?? 0;
    return money(amount).replaceAll(' FCFA', '');
  }

  String _name() {
    final user = VendorSession.instance.user ?? const <String, dynamic>{};
    final raw = '${user['name'] ?? user['first_name'] ?? ''}'.trim();
    if (raw.isEmpty) return 'Vendeur';
    final parts = raw.split(RegExp(r'\s+'));
    if (parts.length > 1) return parts.last;
    return raw;
  }

  String _shopName() {
    final fromData = _data['shop'];
    if (fromData is Map && '${fromData['name'] ?? ''}'.trim().isNotEmpty) {
      return '${fromData['name']}'.trim();
    }
    return '${VendorSession.instance.shop?['name'] ?? 'Ma boutique'}';
  }

  String? _shopLogoUrl() {
    final shop = _data['shop'] is Map
        ? Map<String, dynamic>.from(_data['shop'] as Map)
        : (VendorSession.instance.shop ?? <String, dynamic>{});
    final url = '${shop['logo_url'] ?? ''}'.trim();
    return url.isEmpty ? null : url;
  }

  String _payoutScheduleLabel() {
    final shop = _data['shop'] is Map
        ? Map<String, dynamic>.from(_data['shop'] as Map)
        : (VendorSession.instance.shop ?? <String, dynamic>{});
    final label = '${shop['payment_mode_label'] ?? ''}'.trim();
    if (label.isNotEmpty) return label;
    return '${shop['payment_mode'] ?? ''}' == 'weekly'
        ? 'Paiement hebdomadaire'
        : 'Après livraison (72 h)';
  }

  bool _shopActive() {
    final shop = _data['shop'] is Map
        ? Map<String, dynamic>.from(_data['shop'] as Map)
        : (VendorSession.instance.shop ?? <String, dynamic>{});
    if (shop['is_active'] is bool) return shop['is_active'] == true;
    final status = '${shop['status'] ?? ''}'.toLowerCase();
    return status == 'approved' || status == 'active' || status == 'actif';
  }

  double _treatmentRate(Map<String, dynamic> stats) {
    final total = _num(stats, 'orders').toDouble();
    if (total <= 0) return 0;
    final toPrepare = _num(stats, 'orders_to_prepare').toDouble();
    return ((total - toPrepare) / total * 100).clamp(0.0, 100.0).toDouble();
  }

  Future<void> _openAddProduct() async {
    await Navigator.of(context).push(MaterialPageRoute(builder: (_) => const ProductFormScreen()));
    if (mounted) _load();
  }

  void _push(Widget page) {
    Navigator.of(context).push(MaterialPageRoute(builder: (_) => page));
  }

  String _statusLabel(Object? raw) {
    final s = '${raw ?? 'pending'}'.toLowerCase();
    if (s.contains('delivered')) return 'Livrée';
    if (s.contains('shipped') || s.contains('in_transit')) return 'Expédiée';
    if (s.contains('prepar') || s.contains('accepted')) return 'Préparation';
    if (s.contains('ready')) return 'Prête';
    if (s.contains('cancel')) return 'Annulée';
    return 'Nouvelle';
  }

  Color _statusColor(Object? raw) {
    final s = '${raw ?? ''}'.toLowerCase();
    if (s.contains('delivered') || s.contains('shipped') || s.contains('ready')) return const Color(0xFF16845B);
    if (s.contains('prepar') || s.contains('accepted')) return const Color(0xFFE85400);
    if (s.contains('cancel')) return const Color(0xFFC72B2B);
    return const Color(0xFF1B5FD0);
  }

  @override
  Widget build(BuildContext context) {
    final stats = _data['stats'] is Map
        ? Map<String, dynamic>.from(_data['stats'] as Map)
        : <String, dynamic>{};
    final orders = (_data['orders'] as List?) ?? const [];
    final rating = _num(stats, 'average_rating').toDouble();
    final rate = _treatmentRate(stats);
    final toProcess = int.tryParse('${stats['orders_to_prepare'] ?? stats['pending_orders'] ?? 0}') ?? 0;

    return MkTheme(
      child: ColoredBox(
        color: Colors.white,
        child: RefreshIndicator(
          onRefresh: _load,
          displacement: 80,
          child: CustomScrollView(
            physics: const AlwaysScrollableScrollPhysics(),
            slivers: [
              SliverToBoxAdapter(
                child: _Header(
                  shopName: _shopName(),
                  sellerName: _name(),
                  active: _shopActive(),
                  notificationCount: _notificationCount,
                  shopLogoUrl: _shopLogoUrl(),
                  onShopTap: () => _push(const ShopScreen()),
                  onNotificationsTap: () async {
                    await Navigator.of(context).push(
                      MaterialPageRoute(builder: (_) => const VendorNotificationsScreen()),
                    );
                    if (mounted) await _load();
                  },
                ),
              ),
              SliverPadding(
                padding: const EdgeInsets.fromLTRB(14, 0, 14, 24),
                sliver: SliverList.list(
                  children: [
                    if (_loading) const LinearProgressIndicator(minHeight: 2, color: Mk.orange),
                    if (_error != null) ...[
                      VendorErrorBox(_error),
                      const SizedBox(height: 12),
                    ],
                    IntrinsicHeight(
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          Expanded(
                            child: _StatCard(
                              icon: Icons.bar_chart_rounded,
                              color: Mk.orange,
                              label: 'Ventes du jour',
                              value: _shortMoney(stats['sales_today']),
                              caption: 'FCFA',
                              captionOrange: true,
                              onTap: () => widget.onSelectTab(VendorTab.finance),
                            ),
                          ),
                          const SizedBox(width: 8),
                          Expanded(
                            child: _StatCard(
                              icon: Icons.description_rounded,
                              color: const Color(0xFF1668E3),
                              label: 'Commandes',
                              value: '${stats['orders_today'] ?? stats['orders'] ?? 0}',
                              caption: 'aujourd’hui',
                              onTap: () => widget.onSelectTab(VendorTab.orders),
                            ),
                          ),
                          const SizedBox(width: 8),
                          Expanded(
                            child: _StatCard(
                              icon: Icons.inventory_2_rounded,
                              color: const Color(0xFF16845B),
                              label: 'Produits actifs',
                              value: '${stats['active_products'] ?? 0}',
                              caption: 'en ligne',
                              onTap: () => widget.onSelectTab(VendorTab.products),
                            ),
                          ),
                          const SizedBox(width: 8),
                          Expanded(
                            child: _StatCard(
                              icon: Icons.schedule_rounded,
                              color: const Color(0xFF6D3BD9),
                              label: 'À traiter',
                              value: '$toProcess',
                              caption: 'en attente',
                              onTap: () => widget.onSelectTab(VendorTab.orders),
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 12),
                    _FinanceBanner(
                      available: _shortMoney(stats['payout_available'] ?? stats['pending_payouts']),
                      scheduleLabel: _payoutScheduleLabel(),
                      onTap: () => widget.onSelectTab(VendorTab.finance),
                    ),
                    const SizedBox(height: 14),
                    const _SectionLabel('Actions rapides'),
                    const SizedBox(height: 10),
                    _ActionRow(
                      children: [
                        _QuickAction(
                          icon: Icons.add_rounded,
                          color: Mk.orange,
                          label: 'Ajouter un produit',
                          onTap: _openAddProduct,
                        ),
                        _QuickAction(
                          icon: Icons.description_rounded,
                          color: const Color(0xFF1668E3),
                          label: 'Mes commandes',
                          onTap: () => widget.onSelectTab(VendorTab.orders),
                        ),
                        _QuickAction(
                          icon: Icons.inventory_2_rounded,
                          color: const Color(0xFF16845B),
                          label: 'Mes produits',
                          onTap: () => widget.onSelectTab(VendorTab.products),
                        ),
                      ],
                    ),
                    const SizedBox(height: 8),
                    _ActionRow(
                      children: [
                        _QuickAction(
                          icon: Icons.headset_mic_rounded,
                          color: const Color(0xFF6D3BD9),
                          label: 'Ouvrir SAV',
                          onTap: () => _push(const AfterSalesScreen()),
                        ),
                        _QuickAction(
                          icon: Icons.account_balance_wallet_rounded,
                          color: Mk.orange,
                          label: 'Finances',
                          onTap: () => widget.onSelectTab(VendorTab.finance),
                        ),
                        _QuickAction(
                          icon: Icons.storefront_rounded,
                          color: const Color(0xFF1668E3),
                          label: 'Ma boutique',
                          onTap: () => _push(const ShopScreen()),
                        ),
                      ],
                    ),
                    const SizedBox(height: 6),
                    Row(
                      children: [
                        const Expanded(child: _SectionLabel('Commandes récentes')),
                        TextButton(
                          onPressed: () => widget.onSelectTab(VendorTab.orders),
                          style: TextButton.styleFrom(
                            foregroundColor: Mk.orange,
                            minimumSize: const Size(48, 48),
                            padding: const EdgeInsets.only(left: 8),
                            textStyle: const TextStyle(fontFamily: Mk.font, fontSize: 14, fontWeight: FontWeight.w700),
                          ),
                          child: const Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Text('Voir tout'),
                              Icon(Icons.chevron_right_rounded, size: 20),
                            ],
                          ),
                        ),
                      ],
                    ),
                    _RecentOrders(
                      orders: orders,
                      statusLabel: _statusLabel,
                      statusColor: _statusColor,
                      onTap: (order) {
                        final id = int.tryParse('${order['id'] ?? ''}');
                        if (id != null) _push(OrderDetailScreen(orderId: id));
                      },
                    ),
                    const SizedBox(height: 14),
                    const _SectionLabel('Performance boutique'),
                    const SizedBox(height: 10),
                    IntrinsicHeight(
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          Expanded(
                            child: _PerformanceCard(
                              icon: Icons.local_shipping_rounded,
                              color: const Color(0xFF1668E3),
                              barColor: const Color(0xFF16845B),
                              title: 'Taux de traitement',
                              value: '${rate.round()}',
                              unit: '%',
                              progress: rate / 100,
                              footer: 'suivi en temps réel',
                              footerIcon: Icons.arrow_drop_up_rounded,
                            ),
                          ),
                          const SizedBox(width: 8),
                          Expanded(
                            child: _PerformanceCard(
                              icon: Icons.star_rounded,
                              color: Mk.orange,
                              barColor: Mk.orange,
                              title: 'Satisfaction client',
                              value: rating > 0 ? rating.toStringAsFixed(1) : '—',
                              unit: rating > 0 ? '/5' : '',
                              progress: rating <= 0 ? 0.0 : math.min(1.0, rating / 5).toDouble(),
                              footer: _reviewsLabel(stats['reviews']),
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 12),
                    const _TipCard(),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  String _reviewsLabel(Object? raw) {
    final count = int.tryParse('${raw ?? 0}') ?? 0;
    if (count == 0) return 'Aucun avis';
    return count == 1 ? '1 avis client' : '$count avis clients';
  }
}

const _cardBorder = Color(0xFFE1E7F0);

BoxDecoration _cardBox([double radius = 12]) => BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(radius),
      border: Border.all(color: _cardBorder, width: 1.2),
      boxShadow: const [BoxShadow(color: Color(0x0D0A1B4F), blurRadius: 8, offset: Offset(0, 2))],
    );

/// En-tête : photo du magasin voilée de bleu, logo, cloche, salutation et
/// bandeau de la boutique. Le bas arrondi blanc amorce le contenu.
class _Header extends StatelessWidget {
  const _Header({
    required this.shopName,
    required this.sellerName,
    required this.active,
    required this.notificationCount,
    required this.shopLogoUrl,
    required this.onNotificationsTap,
    required this.onShopTap,
  });

  final String shopName;
  final String sellerName;
  final bool active;
  final int notificationCount;
  final String? shopLogoUrl;
  final VoidCallback onNotificationsTap;
  final VoidCallback onShopTap;

  @override
  Widget build(BuildContext context) {
    final top = MediaQuery.paddingOf(context).top;
    return Stack(
      children: [
        Positioned.fill(
          child: Image.asset(
            'assets/images/mockups/dash_header.jpg',
            fit: BoxFit.cover,
            alignment: const Alignment(.6, -.2),
            excludeFromSemantics: true,
          ),
        ),
        const Positioned.fill(
          child: DecoratedBox(
            decoration: BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.centerLeft,
                end: Alignment.centerRight,
                colors: [Color(0xEB0A2E7A), Color(0xB80B3A93), Color(0x520B3A93)],
                stops: [0, .5, 1],
              ),
            ),
          ),
        ),
        Padding(
          padding: EdgeInsets.fromLTRB(16, top + 8, 14, 32),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Image.asset('assets/images/mockups/logo_white.png', height: 36, semanticLabel: 'OVANIE Vendeur'),
                  const Spacer(),
                  IconButton(
                    tooltip: 'Notifications',
                    onPressed: onNotificationsTap,
                    icon: Badge(
                      isLabelVisible: notificationCount > 0,
                      backgroundColor: Mk.orange,
                      label: Text(notificationCount > 99 ? '99+' : '$notificationCount'),
                      child: const Icon(Icons.notifications_none_rounded, color: Colors.white, size: 28),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 6),
              Text.rich(
                TextSpan(
                  text: 'Bonjour, ',
                  children: [TextSpan(text: sellerName, style: const TextStyle(color: Mk.orange))],
                ),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(color: Colors.white, fontSize: 19, fontWeight: FontWeight.w800, letterSpacing: -.3),
              ),
              const SizedBox(height: 2),
              const Text(
                'Gérez votre boutique en toute simplicité',
                style: TextStyle(color: Color(0xFFD5E0F5), fontSize: 12.5, fontWeight: FontWeight.w500),
              ),
              const SizedBox(height: 10),
              Material(
                color: Colors.white.withValues(alpha: .08),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(16),
                  side: BorderSide(color: Colors.white.withValues(alpha: .75), width: 1.1),
                ),
                clipBehavior: Clip.antiAlias,
                child: InkWell(
                  onTap: onShopTap,
                  child: Padding(
                    padding: const EdgeInsets.fromLTRB(8, 6, 8, 6),
                    child: Row(
                      children: [
                        _ShopAvatar(imageUrl: shopLogoUrl),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Text(
                            shopName,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(color: Colors.white, fontSize: 14.5, fontWeight: FontWeight.w800),
                          ),
                        ),
                        const SizedBox(width: 8),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
                          decoration: BoxDecoration(
                            color: active ? const Color(0xFF12805C) : const Color(0xFF9A6200),
                            borderRadius: BorderRadius.circular(99),
                            border: Border.all(color: Colors.white.withValues(alpha: .45)),
                          ),
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Container(
                                width: 8,
                                height: 8,
                                decoration: const BoxDecoration(color: Colors.white, shape: BoxShape.circle),
                              ),
                              const SizedBox(width: 6),
                              Text(
                                active ? 'Boutique active' : 'En vérification',
                                style: const TextStyle(color: Colors.white, fontSize: 11.5, fontWeight: FontWeight.w600),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
        Positioned(
          left: 0,
          right: 0,
          bottom: -1,
          child: Container(
            height: 22,
            decoration: const BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.vertical(top: Radius.circular(22)),
            ),
          ),
        ),
      ],
    );
  }
}

class _ShopAvatar extends StatelessWidget {
  const _ShopAvatar({required this.imageUrl});

  final String? imageUrl;

  Widget _fallback() {
    return const Center(
      child: Icon(Icons.storefront_rounded, color: Mk.orange, size: 20),
    );
  }

  @override
  Widget build(BuildContext context) {
    final url = (imageUrl ?? '').trim();
    return Container(
      width: 34,
      height: 34,
      decoration: const BoxDecoration(color: Colors.white, shape: BoxShape.circle),
      clipBehavior: Clip.antiAlias,
      child: url.isEmpty
          ? _fallback()
          : Image.network(
              url,
              fit: BoxFit.cover,
              filterQuality: FilterQuality.medium,
              errorBuilder: (_, __, ___) => _fallback(),
            ),
    );
  }
}

/// Chiffre clé : pictogramme, libellé, valeur et précision.
class _StatCard extends StatelessWidget {
  const _StatCard({
    required this.icon,
    required this.color,
    required this.label,
    required this.value,
    required this.caption,
    required this.onTap,
    this.captionOrange = false,
  });

  final IconData icon;
  final Color color;
  final String label;
  final String value;
  final String caption;
  final bool captionOrange;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      button: true,
      label: '$label : $value $caption',
      excludeSemantics: true,
      child: DecoratedBox(
        decoration: _cardBox(),
        child: Material(
          color: Colors.transparent,
          child: InkWell(
            onTap: onTap,
            borderRadius: BorderRadius.circular(12),
            child: Padding(
              padding: const EdgeInsets.fromLTRB(4, 8, 4, 8),
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(icon, color: color, size: 24),
                  const SizedBox(height: 4),
                  FittedBox(
                    fit: BoxFit.scaleDown,
                    child: Text(label, maxLines: 1, style: const TextStyle(color: Mk.muted, fontSize: 10.5, fontWeight: FontWeight.w500)),
                  ),
                  const SizedBox(height: 1),
                  FittedBox(
                    fit: BoxFit.scaleDown,
                    child: Text(
                      value,
                      maxLines: 1,
                      style: const TextStyle(color: Mk.navy, fontSize: 19, fontWeight: FontWeight.w800, height: 1.15),
                    ),
                  ),
                  FittedBox(
                    fit: BoxFit.scaleDown,
                    child: Text(
                      caption,
                      maxLines: 1,
                      style: TextStyle(
                        color: captionOrange ? Mk.orange : Mk.muted,
                        fontSize: 10.5,
                        fontWeight: captionOrange ? FontWeight.w800 : FontWeight.w500,
                      ),
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
}

/// Bandeau bleu « Revenus disponibles » avec le portefeuille.
class _FinanceBanner extends StatelessWidget {
  const _FinanceBanner({required this.available, required this.scheduleLabel, required this.onTap});

  final String available;
  final String scheduleLabel;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      button: true,
      label: 'Revenus disponibles : $available FCFA. $scheduleLabel. Voir les finances',
      excludeSemantics: true,
      child: Container(
        decoration: BoxDecoration(
          gradient: const LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [Color(0xFF0B46B4), Color(0xFF062B7E)],
          ),
          borderRadius: BorderRadius.circular(14),
        ),
        child: Material(
          color: Colors.transparent,
          child: InkWell(
            onTap: onTap,
            borderRadius: BorderRadius.circular(14),
            child: Padding(
              padding: const EdgeInsets.fromLTRB(14, 12, 10, 12),
              child: LayoutBuilder(
                builder: (context, box) {
                  final texts = Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      const Text(
                        'Revenus disponibles',
                        style: TextStyle(color: Colors.white, fontSize: 13.5, fontWeight: FontWeight.w700),
                      ),
                      const SizedBox(height: 3),
                      FittedBox(
                        fit: BoxFit.scaleDown,
                        alignment: Alignment.centerLeft,
                        child: Text.rich(
                          TextSpan(
                            text: '$available ',
                            children: const [TextSpan(text: 'FCFA', style: TextStyle(color: Mk.orange))],
                          ),
                          maxLines: 1,
                          style: const TextStyle(color: Colors.white, fontSize: 22, fontWeight: FontWeight.w800, letterSpacing: -.3),
                        ),
                      ),
                      const SizedBox(height: 9),
                      Row(
                        children: [
                          const Icon(Icons.calendar_month_outlined, color: Colors.white, size: 16),
                          const SizedBox(width: 6),
                          Flexible(
                            child: Text(
                              scheduleLabel,
                              maxLines: 2,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w500),
                            ),
                          ),
                        ],
                      ),
                    ],
                  );
                  final button = Container(
                    padding: const EdgeInsets.fromLTRB(8, 5, 4, 5),
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(8),
                      border: Border.all(color: Colors.white, width: 1.1),
                    ),
                    child: const Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(
                          'Voir les finances',
                          style: TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w600),
                        ),
                        Icon(Icons.chevron_right_rounded, color: Colors.white, size: 15),
                      ],
                    ),
                  );
                  // Écran étroit ou texte agrandi : le portefeuille passe au-dessus du bouton.
                  final roomy = box.maxWidth >= 345 && MediaQuery.textScalerOf(context).scale(10) <= 10.5;
                  if (!roomy) {
                    return Row(
                      crossAxisAlignment: CrossAxisAlignment.end,
                      children: [
                        Expanded(child: texts),
                        const SizedBox(width: 8),
                        Column(
                          mainAxisSize: MainAxisSize.min,
                          crossAxisAlignment: CrossAxisAlignment.end,
                          children: [
                            Image.asset('assets/images/mockups/dash_wallet.png', height: 52),
                            const SizedBox(height: 6),
                            button,
                          ],
                        ),
                      ],
                    );
                  }
                  // Le portefeuille se pose à droite du montant, le bouton en bas à droite.
                  return Stack(
                    clipBehavior: Clip.none,
                    children: [
                      Padding(
                        padding: const EdgeInsets.only(right: 122),
                        child: texts,
                      ),
                      Positioned(
                        right: 26,
                        top: -4,
                        child: Image.asset('assets/images/mockups/dash_wallet.png', height: 52),
                      ),
                      Positioned(right: 0, bottom: 0, child: button),
                    ],
                  );
                },
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _SectionLabel extends StatelessWidget {
  const _SectionLabel(this.text);
  final String text;

  @override
  Widget build(BuildContext context) {
    return Text(
      text,
      style: const TextStyle(color: Mk.navy, fontSize: 16, fontWeight: FontWeight.w800, letterSpacing: -.2),
    );
  }
}

/// Trois actions rapides de même hauteur.
class _ActionRow extends StatelessWidget {
  const _ActionRow({required this.children});

  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    return IntrinsicHeight(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          for (var i = 0; i < children.length; i++) ...[
            if (i > 0) const SizedBox(width: 8),
            Expanded(child: children[i]),
          ],
        ],
      ),
    );
  }
}

class _QuickAction extends StatelessWidget {
  const _QuickAction({required this.icon, required this.color, required this.label, required this.onTap});

  final IconData icon;
  final Color color;
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: _cardBox(),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          borderRadius: BorderRadius.circular(12),
          onTap: onTap,
          child: Padding(
            padding: const EdgeInsets.fromLTRB(4, 9, 4, 9),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Container(
                  width: 38,
                  height: 38,
                  decoration: BoxDecoration(color: color, shape: BoxShape.circle),
                  child: Icon(icon, color: Colors.white, size: 22),
                ),
                const SizedBox(height: 6),
                Text(
                  label,
                  textAlign: TextAlign.center,
                  maxLines: 2,
                  style: const TextStyle(color: Mk.navy, fontSize: 11.5, fontWeight: FontWeight.w700, height: 1.2),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _RecentOrders extends StatelessWidget {
  const _RecentOrders({
    required this.orders,
    required this.statusLabel,
    required this.statusColor,
    required this.onTap,
  });

  final List<dynamic> orders;
  final String Function(Object?) statusLabel;
  final Color Function(Object?) statusColor;
  final ValueChanged<Map<String, dynamic>> onTap;

  @override
  Widget build(BuildContext context) {
    if (orders.isEmpty) {
      return Container(
        padding: const EdgeInsets.all(16),
        decoration: _cardBox(),
        child: const Row(
          children: [
            _OrderIcon(),
            SizedBox(width: 12),
            Expanded(
              child: Text(
                'Aucune commande pour le moment.',
                style: TextStyle(color: Mk.muted, fontSize: 14, fontWeight: FontWeight.w500),
              ),
            ),
          ],
        ),
      );
    }
    final visible = orders.take(3).whereType<Map>().toList();
    return Container(
      decoration: _cardBox(),
      clipBehavior: Clip.antiAlias,
      child: Column(
        children: List.generate(visible.length, (index) {
          final order = Map<String, dynamic>.from(visible[index]);
          final status = order['vendor_status'];
          final color = statusColor(status);
          return Material(
            color: Colors.transparent,
            child: InkWell(
              onTap: () => onTap(order),
              child: Container(
                margin: const EdgeInsets.symmetric(horizontal: 12),
                padding: const EdgeInsets.symmetric(vertical: 9),
                decoration: BoxDecoration(
                  border: index == visible.length - 1 ? null : const Border(bottom: BorderSide(color: _cardBorder)),
                ),
                child: Row(
                  children: [
                    const _OrderIcon(),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            '${order['order_number'] ?? ''}',
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(color: Mk.navy, fontSize: 13.5, fontWeight: FontWeight.w800),
                          ),
                          const SizedBox(height: 1),
                          Text(
                            '${order['client_name'] ?? 'Client'}',
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(color: Mk.muted, fontSize: 12, fontWeight: FontWeight.w500),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(width: 8),
                    _OrderAmount(
                      amount: money(order['seller_products_total'] ?? order['public_products_total']),
                      status: statusLabel(status),
                      color: color,
                    ),
                    const Icon(Icons.chevron_right_rounded, color: Mk.navy, size: 22),
                  ],
                ),
              ),
            ),
          );
        }),
      ),
    );
  }
}

/// Montant et statut d'une commande : côte à côte, ou empilés sur un écran étroit.
class _OrderAmount extends StatelessWidget {
  const _OrderAmount({required this.amount, required this.status, required this.color});

  final String amount;
  final String status;
  final Color color;

  @override
  Widget build(BuildContext context) {
    final wide = MediaQuery.sizeOf(context).width >= 390 && MediaQuery.textScalerOf(context).scale(10) <= 10.5;
    final price = Text(amount, style: const TextStyle(color: Mk.navy, fontSize: 12.5, fontWeight: FontWeight.w800));
    final pill = Container(
      constraints: BoxConstraints(minWidth: wide ? 84 : 0),
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
      decoration: BoxDecoration(color: color.withValues(alpha: .13), borderRadius: BorderRadius.circular(99)),
      child: Text(
        status,
        textAlign: TextAlign.center,
        style: TextStyle(color: color, fontSize: 11.5, fontWeight: FontWeight.w700),
      ),
    );
    if (wide) {
      return Row(mainAxisSize: MainAxisSize.min, children: [price, const SizedBox(width: 10), pill, const SizedBox(width: 4)]);
    }
    return Column(
      crossAxisAlignment: CrossAxisAlignment.end,
      children: [price, const SizedBox(height: 4), pill],
    );
  }
}

class _OrderIcon extends StatelessWidget {
  const _OrderIcon();

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 34,
      height: 34,
      decoration: const BoxDecoration(color: Mk.softBlue, shape: BoxShape.circle),
      child: const Icon(Icons.inventory_2_outlined, color: Color(0xFF1668E3), size: 19),
    );
  }
}

class _PerformanceCard extends StatelessWidget {
  const _PerformanceCard({
    required this.icon,
    required this.color,
    required this.barColor,
    required this.title,
    required this.value,
    required this.unit,
    required this.progress,
    required this.footer,
    this.footerIcon,
  });

  final IconData icon;
  final Color color;
  final Color barColor;
  final String title;
  final String value;
  final String unit;
  final double progress;
  final String footer;
  final IconData? footerIcon;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(10, 12, 10, 10),
      decoration: _cardBox(),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 36,
                height: 36,
                decoration: BoxDecoration(color: color.withValues(alpha: .14), shape: BoxShape.circle),
                child: Icon(icon, color: color, size: 21),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    FittedBox(
                      fit: BoxFit.scaleDown,
                      alignment: Alignment.centerLeft,
                      child: Text(title, maxLines: 1, style: const TextStyle(color: Mk.muted, fontSize: 12, fontWeight: FontWeight.w500)),
                    ),
                    Text.rich(
                      TextSpan(
                        text: value,
                        children: [TextSpan(text: unit, style: const TextStyle(fontSize: 14))],
                      ),
                      style: const TextStyle(color: Mk.navy, fontSize: 20, fontWeight: FontWeight.w800, height: 1.2),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          ClipRRect(
            borderRadius: BorderRadius.circular(99),
            child: LinearProgressIndicator(
              minHeight: 6,
              value: progress.clamp(0.0, 1.0),
              color: barColor,
              backgroundColor: const Color(0xFFE3E7ED),
            ),
          ),
          const SizedBox(height: 6),
          Row(
            children: [
              if (footerIcon != null) Icon(footerIcon, color: const Color(0xFF16845B), size: 18),
              Flexible(
                child: Text(
                  footer,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(color: Color(0xFF16845B), fontSize: 11.5, fontWeight: FontWeight.w600),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

/// Encadré « Conseil OVANIE » avec l'illustration de matériaux.
class _TipCard extends StatelessWidget {
  const _TipCard();

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(12, 12, 8, 12),
      decoration: BoxDecoration(
        color: Mk.softOrange,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0xFFF6C7A6), width: 1.2),
      ),
      child: Row(
        children: [
          const Expanded(
            flex: 6,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Icon(Icons.info_rounded, color: Mk.orange, size: 24),
                    SizedBox(width: 8),
                    Flexible(
                      child: Text(
                        'Conseil OVANIE',
                        style: TextStyle(color: Mk.orange, fontSize: 15, fontWeight: FontWeight.w800),
                      ),
                    ),
                  ],
                ),
                SizedBox(height: 6),
                Text(
                  'Ajoutez des photos complètes et une description détaillée à vos produits. '
                  'Cela augmente vos ventes et la confiance des acheteurs !',
                  style: TextStyle(color: Mk.muted, fontSize: 12, height: 1.4, fontWeight: FontWeight.w500),
                ),
              ],
            ),
          ),
          const SizedBox(width: 6),
          Expanded(
            flex: 5,
            child: Image.asset('assets/images/mockups/dash_tip.png', fit: BoxFit.contain, excludeFromSemantics: true),
          ),
        ],
      ),
    );
  }
}
