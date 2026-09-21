import 'dart:math' as math;

import 'package:flutter/material.dart';

import '../../core/network/api_client.dart';
import '../../core/ui/vendor_design.dart';
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

class _VendorDashboardScreenState extends State<VendorDashboardScreen> {
  Map<String, dynamic> _data = {};
  bool _loading = true;
  String? _error;
  int _notificationCount = 0;

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
    if (s.contains('delivered')) return 'Expédiée';
    if (s.contains('shipped') || s.contains('in_transit')) return 'Expédiée';
    if (s.contains('prepar') || s.contains('accepted')) return 'Préparation';
    if (s.contains('ready')) return 'Prête';
    if (s.contains('cancel')) return 'Annulée';
    return 'Nouvelle';
  }

  Color _statusColor(Object? raw) {
    final s = '${raw ?? ''}'.toLowerCase();
    if (s.contains('delivered') || s.contains('shipped') || s.contains('ready')) return const Color(0xFF16845B);
    if (s.contains('prepar') || s.contains('accepted')) return vendorOrange;
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

    return RefreshIndicator(
      onRefresh: _load,
      displacement: 100,
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
              onNotificationsTap: () async {
                await Navigator.of(context).push(
                  MaterialPageRoute(builder: (_) => const VendorNotificationsScreen()),
                );
                if (mounted) await _load();
              },
            ),
          ),
          SliverToBoxAdapter(
            child: Transform.translate(
              offset: const Offset(0, -10),
              child: Container(
                decoration: const BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
                ),
                padding: const EdgeInsets.fromLTRB(12, 16, 12, 18),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    if (_loading) const LinearProgressIndicator(minHeight: 2),
                    if (_error != null) ...[
                      VendorErrorBox(_error),
                      const SizedBox(height: 10),
                    ],
                    Row(
                      children: [
                        Expanded(
                          child: _MetricCard(
                            icon: Icons.bar_chart_rounded,
                            iconColor: vendorOrange,
                            label: 'Ventes du jour',
                            value: _shortMoney(stats['sales_today']),
                            unit: 'FCFA',
                          ),
                        ),
                        const SizedBox(width: 7),
                        Expanded(
                          child: _MetricCard(
                            icon: Icons.assignment_outlined,
                            iconColor: vendorBlue,
                            label: 'Commandes',
                            value: '${stats['orders_today'] ?? stats['orders'] ?? 0}',
                            unit: 'aujourd’hui',
                          ),
                        ),
                        const SizedBox(width: 7),
                        Expanded(
                          child: _MetricCard(
                            icon: Icons.inventory_2_rounded,
                            iconColor: const Color(0xFF209447),
                            label: 'Produits actifs',
                            value: '${stats['active_products'] ?? 0}',
                            unit: 'en ligne',
                          ),
                        ),
                        const SizedBox(width: 7),
                        Expanded(
                          child: _MetricCard(
                            icon: Icons.schedule_rounded,
                            iconColor: vendorOrange,
                            label: 'À traiter',
                            value: '${stats['orders_to_prepare'] ?? stats['pending_orders'] ?? 0}',
                            unit: 'en attente',
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 10),
                    _FinanceBanner(
                      available: _shortMoney(stats['payout_available'] ?? stats['pending_payouts']),
                      scheduleLabel: _payoutScheduleLabel(),
                      onTap: () => widget.onSelectTab(VendorTab.finance),
                    ),
                    const SizedBox(height: 14),
                    const _SectionLabel('Actions rapides'),
                    const SizedBox(height: 8),
                    GridView.count(
                      shrinkWrap: true,
                      physics: const NeverScrollableScrollPhysics(),
                      crossAxisCount: 3,
                      childAspectRatio: 2.45,
                      crossAxisSpacing: 7,
                      mainAxisSpacing: 7,
                      children: [
                        _QuickAction(icon: Icons.add_rounded, iconColor: vendorOrange, label: 'Ajouter un produit', onTap: _openAddProduct),
                        _QuickAction(icon: Icons.assignment_outlined, iconColor: vendorBlue, label: 'Mes commandes', onTap: () => widget.onSelectTab(VendorTab.orders)),
                        _QuickAction(icon: Icons.inventory_2_outlined, iconColor: const Color(0xFF17913E), label: 'Mes produits', onTap: () => widget.onSelectTab(VendorTab.products)),
                        _QuickAction(icon: Icons.headset_mic_outlined, iconColor: const Color(0xFF8A2BE2), label: 'Ouvrir SAV', onTap: () => _push(const AfterSalesScreen())),
                        _QuickAction(icon: Icons.account_balance_wallet_outlined, iconColor: vendorOrange, label: 'Finances', onTap: () => widget.onSelectTab(VendorTab.finance)),
                        _QuickAction(icon: Icons.storefront_outlined, iconColor: vendorBlue, label: 'Ma boutique', onTap: () => _push(const ShopScreen())),
                      ],
                    ),
                    const SizedBox(height: 14),
                    Row(
                      children: [
                        const Expanded(child: _SectionLabel('Commandes récentes')),
                        TextButton(
                          onPressed: () => widget.onSelectTab(VendorTab.orders),
                          child: const Text('Voir tout  ›', style: TextStyle(color: vendorOrange, fontSize: 12, fontWeight: FontWeight.w800)),
                        ),
                      ],
                    ),
                    const SizedBox(height: 4),
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
                    const SizedBox(height: 8),
                    Row(
                      children: [
                        Expanded(
                          child: _PerformanceCard(
                            icon: Icons.local_shipping_outlined,
                            iconColor: vendorOrange,
                            title: 'Taux de traitement',
                            value: '${rate.round()}%',
                            progress: rate / 100,
                            footer: rate > 0 ? '▲ suivi en temps réel' : 'Aucune donnée',
                          ),
                        ),
                        const SizedBox(width: 8),
                        Expanded(
                          child: _PerformanceCard(
                            icon: Icons.star_outline_rounded,
                            iconColor: const Color(0xFF199447),
                            title: 'Satisfaction client',
                            value: rating > 0 ? '${rating.toStringAsFixed(1)}/5' : '—/5',
                            progress: rating <= 0 ? 0.0 : math.min(1.0, rating / 5).toDouble(),
                            footer: '${stats['reviews'] ?? 0} avis client(s)',
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 14),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                      decoration: BoxDecoration(
                        color: const Color(0xFFFFFBF7),
                        borderRadius: BorderRadius.circular(10),
                        border: Border.all(color: const Color(0xFFFFD0AD)),
                      ),
                      child: Row(
                        children: [
                          const VendorCircleIcon(
                            icon: Icons.info_outline_rounded,
                            color: Colors.white,
                            background: vendorOrange,
                            size: 38,
                            iconSize: 21,
                          ),
                          const SizedBox(width: 10),
                          const Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text('Conseil OVANIE', style: TextStyle(color: vendorOrange, fontSize: 13, fontWeight: FontWeight.w900)),
                                SizedBox(height: 2),
                                Text(
                                  'Ajoutez des photos complètes et une description détaillée à vos produits. Cela augmente vos ventes et la confiance des acheteurs !',
                                  style: TextStyle(color: vendorText, fontSize: 10.5, height: 1.25),
                                ),
                              ],
                            ),
                          ),
                          const SizedBox(width: 8),
                          SizedBox(
                            width: 92,
                            height: 60,
                            child: Image.asset(
                              'assets/images/vendor_advice_items_full.png',
                              fit: BoxFit.contain,
                              alignment: Alignment.centerRight,
                              filterQuality: FilterQuality.high,
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
        ],
      ),
    );
  }
}

class _Header extends StatelessWidget {
  const _Header({
    required this.shopName,
    required this.sellerName,
    required this.active,
    required this.notificationCount,
    required this.shopLogoUrl,
    required this.onNotificationsTap,
  });

  final String shopName;
  final String sellerName;
  final bool active;
  final int notificationCount;
  final String? shopLogoUrl;
  final VoidCallback onNotificationsTap;

  @override
  Widget build(BuildContext context) {
    return Container(
      color: vendorNavyDeep,
      child: SafeArea(
        bottom: false,
        child: SizedBox(
          height: 184,
          child: Stack(
            fit: StackFit.expand,
            children: [
              Container(
                decoration: const BoxDecoration(
                  gradient: LinearGradient(
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                    colors: [Color(0xFF052653), Color(0xFF063C79)],
                  ),
                ),
              ),
              const Positioned.fill(
                child: IgnorePointer(
                  child: CustomPaint(painter: _ConstructionLinePainter()),
                ),
              ),
              Padding(
                padding: const EdgeInsets.fromLTRB(18, 10, 18, 12),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        const _OvanieOfficialLogo(),
                        const Spacer(),
                        Stack(
                          clipBehavior: Clip.none,
                          children: [
                            IconButton(
                              onPressed: onNotificationsTap,
                              icon: const Icon(Icons.notifications_none_rounded, color: Colors.white, size: 27),
                            ),
                            if (notificationCount > 0)
                              Positioned(
                                right: 5,
                                top: 2,
                                child: Container(
                                  constraints: const BoxConstraints(minWidth: 19, minHeight: 19),
                                  padding: const EdgeInsets.symmetric(horizontal: 5),
                                  alignment: Alignment.center,
                                  decoration: BoxDecoration(color: vendorOrange, borderRadius: BorderRadius.circular(99)),
                                  child: Text(
                                    notificationCount > 99 ? '99+' : '$notificationCount',
                                    style: const TextStyle(color: Colors.white, fontSize: 9.5, fontWeight: FontWeight.w900),
                                  ),
                                ),
                              ),
                          ],
                        ),
                      ],
                    ),
                    const SizedBox(height: 8),
                    RichText(
                      text: TextSpan(
                        style: const TextStyle(fontSize: 19, fontWeight: FontWeight.w800),
                        children: [
                          const TextSpan(text: 'Bonjour, ', style: TextStyle(color: Colors.white)),
                          TextSpan(text: 'M. $sellerName', style: const TextStyle(color: vendorOrange)),
                        ],
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text('Gérez votre boutique en toute simplicité', style: TextStyle(color: Colors.white.withValues(alpha: .88), fontSize: 12.5)),
                    const Spacer(),
                    Container(
                      height: 48,
                      padding: const EdgeInsets.symmetric(horizontal: 10),
                      decoration: BoxDecoration(
                        color: const Color(0xFF06234F).withValues(alpha: .72),
                        borderRadius: BorderRadius.circular(10),
                        border: Border.all(color: Colors.white.withValues(alpha: .2)),
                      ),
                      child: Row(
                        children: [
                          _ShopAvatar(imageUrl: shopLogoUrl),
                          const SizedBox(width: 10),
                          Expanded(
                            child: Text(
                              shopName,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(color: Colors.white, fontSize: 14.5, fontWeight: FontWeight.w800),
                            ),
                          ),
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 6),
                            decoration: BoxDecoration(
                              color: active ? const Color(0xFF155E4A) : const Color(0xFF6F5A15),
                              borderRadius: BorderRadius.circular(99),
                            ),
                            child: Row(
                              children: [
                                Container(width: 7, height: 7, decoration: BoxDecoration(color: active ? const Color(0xFF61DE8A) : Colors.amber, shape: BoxShape.circle)),
                                const SizedBox(width: 5),
                                Text(
                                  active ? 'Boutique active' : 'Boutique à vérifier',
                                  style: TextStyle(color: active ? const Color(0xFF86F2A6) : Colors.white, fontSize: 10.5, fontWeight: FontWeight.w700),
                                ),
                              ],
                            ),
                          ),
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
}

class _ShopAvatar extends StatelessWidget {
  const _ShopAvatar({required this.imageUrl});

  final String? imageUrl;

  Widget _fallback() {
    return const Center(
      child: Icon(Icons.storefront_outlined, color: vendorOrange, size: 21),
    );
  }

  @override
  Widget build(BuildContext context) {
    final url = (imageUrl ?? '').trim();
    return Container(
      width: 38,
      height: 38,
      decoration: BoxDecoration(
        color: Colors.white,
        shape: BoxShape.circle,
        border: Border.all(color: Colors.white.withValues(alpha: .9), width: 2),
      ),
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

class _ConstructionLinePainter extends CustomPainter {
  const _ConstructionLinePainter();

  @override
  void paint(Canvas canvas, Size size) {
    final line = Paint()
      ..color = const Color(0x245DA9F7)
      ..style = PaintingStyle.stroke
      ..strokeWidth = 1.0;
    final faint = Paint()
      ..color = const Color(0x145DA9F7)
      ..style = PaintingStyle.stroke
      ..strokeWidth = .8;

    final right = size.width;
    final bottom = size.height;

    // Grue stylisée, dessinée directement en Flutter : aucun rectangle bitmap.
    final mastX = right * .72;
    canvas.drawLine(Offset(mastX, bottom * .18), Offset(mastX, bottom), line);
    canvas.drawLine(Offset(mastX - 18, bottom * .24), Offset(mastX + 18, bottom * .24), line);
    canvas.drawLine(Offset(mastX, bottom * .20), Offset(right * .97, bottom * .32), line);
    canvas.drawLine(Offset(mastX, bottom * .20), Offset(right * .55, bottom * .27), faint);
    canvas.drawLine(Offset(right * .91, bottom * .29), Offset(right * .91, bottom * .55), faint);
    canvas.drawRect(Rect.fromLTWH(right * .895, bottom * .55, 13, 7), faint);

    // Structure d'immeuble en lignes fines à droite.
    final building = Rect.fromLTWH(right * .76, bottom * .50, right * .27, bottom * .50);
    canvas.drawRect(building, faint);
    for (var i = 1; i < 5; i++) {
      final y = building.top + building.height * i / 5;
      canvas.drawLine(Offset(building.left, y), Offset(building.right, y), faint);
    }
    for (var i = 1; i < 4; i++) {
      final x = building.left + building.width * i / 4;
      canvas.drawLine(Offset(x, building.top), Offset(x, building.bottom), faint);
    }
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}

class _OvanieOfficialLogo extends StatelessWidget {
  const _OvanieOfficialLogo();

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 172,
      height: 54,
      child: Image.asset(
        'assets/images/ovanie_logo.png',
        fit: BoxFit.contain,
        alignment: Alignment.centerLeft,
        filterQuality: FilterQuality.high,
      ),
    );
  }
}

class _MetricCard extends StatelessWidget {
  const _MetricCard({required this.icon, required this.iconColor, required this.label, required this.value, required this.unit});

  final IconData icon;
  final Color iconColor;
  final String label;
  final String value;
  final String unit;

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 92,
      padding: const EdgeInsets.all(9),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: const Color(0xFFE3E8EF)),
        boxShadow: const [BoxShadow(color: Color(0x08000F35), blurRadius: 10, offset: Offset(0, 4))],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              VendorCircleIcon(
                icon: icon,
                color: iconColor,
                background: iconColor.withValues(alpha: .10),
                size: 30,
                iconSize: 16,
              ),
              const SizedBox(width: 5),
              Expanded(
                child: FittedBox(
                  fit: BoxFit.scaleDown,
                  alignment: Alignment.centerLeft,
                  child: Text(
                    label,
                    maxLines: 1,
                    softWrap: false,
                    style: const TextStyle(color: vendorText, fontSize: 10.2, height: 1.05),
                  ),
                ),
              ),
            ],
          ),
          const Spacer(),
          FittedBox(
            fit: BoxFit.scaleDown,
            alignment: Alignment.centerLeft,
            child: Text(value, style: const TextStyle(color: vendorText, fontSize: 18, fontWeight: FontWeight.w900)),
          ),
          Text(unit, maxLines: 1, overflow: TextOverflow.ellipsis, style: TextStyle(color: unit == 'FCFA' ? vendorOrange : vendorMuted, fontSize: 9.5, fontWeight: unit == 'FCFA' ? FontWeight.w800 : FontWeight.w500)),
        ],
      ),
    );
  }
}

class _FinanceBanner extends StatelessWidget {
  const _FinanceBanner({required this.available, required this.scheduleLabel, required this.onTap});

  final String available;
  final String scheduleLabel;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 100,
      padding: const EdgeInsets.fromLTRB(14, 12, 12, 10),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(10),
        gradient: const LinearGradient(colors: [Color(0xFF073278), Color(0xFF0A48A6)]),
      ),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text('Revenus disponibles', style: TextStyle(color: Colors.white, fontSize: 12.5, fontWeight: FontWeight.w700)),
                const SizedBox(height: 4),
                FittedBox(
                  fit: BoxFit.scaleDown,
                  alignment: Alignment.centerLeft,
                  child: RichText(
                    text: TextSpan(
                      children: [
                        TextSpan(text: available, style: const TextStyle(color: Colors.white, fontSize: 24, fontWeight: FontWeight.w900)),
                        const TextSpan(text: ' FCFA', style: TextStyle(color: vendorOrange, fontSize: 13, fontWeight: FontWeight.w800)),
                      ],
                    ),
                  ),
                ),
                const Spacer(),
                Row(
                  children: [
                    const Icon(Icons.calendar_today_outlined, size: 12, color: Colors.white),
                    const SizedBox(width: 5),
                    Expanded(
                      child: Text(
                        scheduleLabel,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(color: Colors.white, fontSize: 9.5, fontWeight: FontWeight.w600),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
          SizedBox(
            width: 98,
            height: 76,
            child: Image.asset(
              'assets/images/vendor_wallet_transparent.png',
              fit: BoxFit.contain,
              alignment: Alignment.bottomCenter,
              filterQuality: FilterQuality.high,
            ),
          ),
          const SizedBox(width: 6),
          SizedBox(
            width: 90,
            height: 42,
            child: OutlinedButton(
              onPressed: onTap,
              style: OutlinedButton.styleFrom(
                padding: const EdgeInsets.symmetric(horizontal: 8),
                side: BorderSide(color: Colors.white.withValues(alpha: .7)),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(9)),
              ),
              child: const Text('Voir les finances  ›', textAlign: TextAlign.center, style: TextStyle(color: Colors.white, fontSize: 10.5, fontWeight: FontWeight.w700)),
            ),
          ),
        ],
      ),
    );
  }
}

class _SectionLabel extends StatelessWidget {
  const _SectionLabel(this.text);
  final String text;

  @override
  Widget build(BuildContext context) {
    return Text(text, style: const TextStyle(color: vendorText, fontSize: 14.5, fontWeight: FontWeight.w900));
  }
}

class _QuickAction extends StatelessWidget {
  const _QuickAction({required this.icon, required this.iconColor, required this.label, required this.onTap});

  final IconData icon;
  final Color iconColor;
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(9),
      child: InkWell(
        borderRadius: BorderRadius.circular(9),
        onTap: onTap,
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(9),
            border: Border.all(color: const Color(0xFFE3E8EF)),
          ),
          child: Row(
            children: [
              VendorCircleIcon(
                icon: icon,
                color: Colors.white,
                background: iconColor,
                size: 30,
                iconSize: 18,
              ),
              const SizedBox(width: 7),
              Expanded(
                child: FittedBox(
                  fit: BoxFit.scaleDown,
                  alignment: Alignment.centerLeft,
                  child: Text(
                    label,
                    maxLines: 1,
                    softWrap: false,
                    style: const TextStyle(color: vendorText, fontSize: 10.6, fontWeight: FontWeight.w700),
                  ),
                ),
              ),
            ],
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
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(10), border: Border.all(color: const Color(0xFFE3E8EF))),
        child: const Text('Aucune commande récente.', style: TextStyle(color: vendorMuted, fontSize: 11.5)),
      );
    }

    final visible = orders.take(3).whereType<Map>().toList();
    return Container(
      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(10), border: Border.all(color: const Color(0xFFE3E8EF))),
      child: Column(
        children: List.generate(visible.length, (index) {
          final order = Map<String, dynamic>.from(visible[index]);
          final status = order['vendor_status'];
          final color = statusColor(status);
          return InkWell(
            onTap: () => onTap(order),
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
              decoration: BoxDecoration(border: index == visible.length - 1 ? null : const Border(bottom: BorderSide(color: Color(0xFFE9EDF3)))),
              child: Row(
              children: [
                const VendorCircleIcon(icon: Icons.inventory_2_outlined, size: 31, iconSize: 16),
                const SizedBox(width: 8),
                Expanded(
                  flex: 5,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('${order['order_number'] ?? 'Commande'}', style: const TextStyle(color: vendorText, fontSize: 11.5, fontWeight: FontWeight.w900)),
                      const SizedBox(height: 1),
                      Text('${order['client_name'] ?? 'Client OVANIE'}', maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: vendorMuted, fontSize: 9.8)),
                    ],
                  ),
                ),
                Expanded(
                  flex: 3,
                  child: Text(
                    money(order['public_products_total']),
                    textAlign: TextAlign.right,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(color: vendorText, fontSize: 10.5, fontWeight: FontWeight.w800),
                  ),
                ),
                const SizedBox(width: 8),
                Container(
                  width: 72,
                  padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 5),
                  decoration: BoxDecoration(color: color.withValues(alpha: .10), borderRadius: BorderRadius.circular(99)),
                  child: Text(statusLabel(status), textAlign: TextAlign.center, style: TextStyle(color: color, fontSize: 9.5, fontWeight: FontWeight.w800)),
                ),
                const SizedBox(width: 4),
                const Icon(Icons.chevron_right_rounded, color: vendorText, size: 18),
                ],
              ),
            ),
          );
        }),
      ),
    );
  }
}

class _PerformanceCard extends StatelessWidget {
  const _PerformanceCard({
    required this.icon,
    required this.iconColor,
    required this.title,
    required this.value,
    required this.progress,
    required this.footer,
  });

  final IconData icon;
  final Color iconColor;
  final String title;
  final String value;
  final double progress;
  final String footer;

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 90,
      padding: const EdgeInsets.all(9),
      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(10), border: Border.all(color: const Color(0xFFE3E8EF))),
      child: Row(
        children: [
          VendorCircleIcon(icon: icon, color: iconColor, background: iconColor.withValues(alpha: .10), size: 38, iconSize: 21),
          const SizedBox(width: 8),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: const TextStyle(color: vendorText, fontSize: 10.5, fontWeight: FontWeight.w700)),
                Text(value, style: const TextStyle(color: vendorText, fontSize: 18, fontWeight: FontWeight.w900)),
                const SizedBox(height: 4),
                ClipRRect(
                  borderRadius: BorderRadius.circular(99),
                  child: LinearProgressIndicator(
                    minHeight: 5,
                    value: progress,
                    color: vendorOrange,
                    backgroundColor: const Color(0xFFE3E7ED),
                  ),
                ),
                const SizedBox(height: 4),
                Text(footer, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: Color(0xFF16845B), fontSize: 8.5, fontWeight: FontWeight.w700)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
