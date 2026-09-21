import 'dart:async';

import 'package:flutter/material.dart';

import '../../../core/navigation/commercial_tab_bus.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/theme/ovanie_colors.dart';
import '../../../core/widgets/brand_background.dart';
import '../../clients/data/clients_service.dart';
import '../../clients/presentation/client_chrome.dart';
import '../../clients/presentation/clients_screen.dart';
import '../../dashboard/models/dashboard_data.dart';
import '../data/shops_service.dart';
import '../models/shop_data.dart';
import 'open_shop_screen.dart';
import 'shop_chrome.dart';
import 'shop_detail_screen.dart';

class CommercialShopsScreen extends StatefulWidget {
  const CommercialShopsScreen({
    super.key,
    required this.shopsService,
    required this.clientsService,
    required this.initialUser,
    required this.onLogout,
  });

  final ShopsService shopsService;
  final ClientsService clientsService;
  final Map<String, dynamic> initialUser;
  final Future<void> Function() onLogout;

  @override
  State<CommercialShopsScreen> createState() => _CommercialShopsScreenState();
}

class _CommercialShopsScreenState extends State<CommercialShopsScreen> {
  final TextEditingController _searchController = TextEditingController();
  Timer? _debounce;
  CommercialShopsData? _data;
  ShopFilter _filter = ShopFilter.all;
  bool _loading = true;
  String? _error;

  CommercialProfile get _profile => CommercialProfile.fromJson(
        _data?.profile.isNotEmpty == true ? _data!.profile : widget.initialUser,
      );

  int get _unread => _data?.unreadNotifications ?? 0;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _searchController.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    if (!mounted) return;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final data = await widget.shopsService.fetch(
        query: _searchController.text,
        filter: _filter,
      );
      if (!mounted) return;
      setState(() => _data = data);
    } on ApiException catch (error) {
      if (!mounted) return;
      setState(() => _error = error.message);
    } catch (_) {
      if (!mounted) return;
      setState(() => _error = 'Impossible de charger les boutiques.');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _onSearch(String _) {
    setState(() {});
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 350), _load);
  }

  Future<void> _setFilter(ShopFilter filter) async {
    if (_filter == filter) return;
    setState(() => _filter = filter);
    await _load();
  }

  Future<void> _openCreateShop() async {
    final created = await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) => CommercialOpenShopScreen(
          shopsService: widget.shopsService,
          clientsService: widget.clientsService,
          initialUser: {
            ...widget.initialUser,
            ...?_data?.profile,
          },
          unreadNotifications: _unread,
          onLogout: widget.onLogout,
        ),
      ),
    );
    if (created == true && mounted) await _load();
  }

  Future<void> _openShop(CommercialShop shop) async {
    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => CommercialShopDetailScreen(
          shopId: shop.id,
          initialShop: shop,
          shopsService: widget.shopsService,
          clientsService: widget.clientsService,
          initialUser: {
            ...widget.initialUser,
            ...?_data?.profile,
          },
          unreadNotifications: _unread,
          onLogout: widget.onLogout,
        ),
      ),
    );
    if (mounted) _load();
  }

  Future<void> _showFilters() async {
    final selected = await showModalBottomSheet<ShopFilter>(
      context: context,
      backgroundColor: const Color(0xFF071E43),
      showDragHandle: true,
      builder: (context) => SafeArea(
        top: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(18, 0, 18, 18),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Align(
                alignment: Alignment.centerLeft,
                child: Text(
                  'Filtrer les boutiques',
                  style: TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w800),
                ),
              ),
              const SizedBox(height: 10),
              for (final filter in ShopFilter.values)
                ListTile(
                  onTap: () => Navigator.pop(context, filter),
                  leading: Icon(
                    _filter == filter ? Icons.radio_button_checked : Icons.radio_button_off,
                    color: _filter == filter ? OvanieColors.blue : const Color(0xFF9FB0CF),
                  ),
                  title: Text(_filterLabel(filter), style: const TextStyle(color: Colors.white)),
                ),
            ],
          ),
        ),
      ),
    );
    if (selected != null) await _setFilter(selected);
  }

  String _filterLabel(ShopFilter filter) => switch (filter) {
        ShopFilter.all => 'Toutes',
        ShopFilter.online => 'En ligne',
        ShopFilter.pending => 'En attente',
        ShopFilter.suspended => 'Suspendues',
      };

  Future<void> _showProfileMenu() async {
    final action = await showModalBottomSheet<String>(
      context: context,
      backgroundColor: const Color(0xFF071E43),
      showDragHandle: true,
      builder: (context) => SafeArea(
        top: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(20, 0, 20, 20),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                _profile.name,
                style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w800),
              ),
              const SizedBox(height: 4),
              const Text('Espace Commercial OVANIE', style: TextStyle(color: Color(0xFF9FB0CF))),
              const SizedBox(height: 16),
              OutlinedButton.icon(
                onPressed: () => Navigator.pop(context, 'logout'),
                icon: const Icon(Icons.logout_rounded),
                label: const Text('Se déconnecter'),
              ),
            ],
          ),
        ),
      ),
    );
    if (action == 'logout') await widget.onLogout();
  }

  void _future(String title) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text('$title : cet écran sera relié dans le prochain lot.'), behavior: SnackBarBehavior.floating),
    );
  }

  void _nav(int index) {
    if (index == 2) return;
    CommercialTabBus.request(index);
  }

  @override
  Widget build(BuildContext context) {
    final width = MediaQuery.sizeOf(context).width;
    final scale = (width / 472).clamp(.78, 1.08).toDouble();
    double s(double value) => value * scale;
    final shops = _data?.shops ?? const <CommercialShop>[];
    final summary = _data?.summary ?? CommercialShopSummary.empty;

    return Scaffold(
      body: BrandBackground(
        child: SafeArea(
          bottom: false,
          child: RefreshIndicator(
            onRefresh: _load,
            color: OvanieColors.blue,
            child: CustomScrollView(
              physics: const AlwaysScrollableScrollPhysics(),
              slivers: [
                SliverPadding(
                  padding: EdgeInsets.fromLTRB(s(16), s(9), s(16), s(102)),
                  sliver: SliverList(
                    delegate: SliverChildListDelegate.fixed([
                      CommercialClientsHeader(
                        profile: _profile,
                        unreadNotifications: _unread,
                        scale: scale,
                        onNotificationsTap: () => _future('Notifications'),
                        onAvatarTap: _showProfileMenu,
                      ),
                      SizedBox(height: s(14)),
                      CommercialShopSearch(
                        controller: _searchController,
                        scale: scale,
                        onChanged: _onSearch,
                        onFilterTap: _showFilters,
                      ),
                      SizedBox(height: s(17)),
                      _TitleRow(scale: scale, onCreate: _openCreateShop),
                      SizedBox(height: s(15)),
                      _FilterRow(
                        scale: scale,
                        selected: _filter,
                        summary: summary,
                        onSelected: _setFilter,
                      ),
                      SizedBox(height: s(13)),
                      if (_error != null) ...[
                        _ErrorBanner(message: _error!, scale: scale, onRetry: _load),
                        SizedBox(height: s(10)),
                      ],
                      if (_loading && _data == null)
                        Padding(
                          padding: EdgeInsets.symmetric(vertical: s(30)),
                          child: const Center(child: CircularProgressIndicator(strokeWidth: 2.4)),
                        )
                      else if (shops.isEmpty)
                        _EmptyShops(scale: scale, onCreate: _openCreateShop)
                      else
                        ...shops.map(
                          (shop) => Padding(
                            padding: EdgeInsets.only(bottom: s(9)),
                            child: _ShopCard(
                              shop: shop,
                              scale: scale,
                              onTap: () => _openShop(shop),
                              onMenu: () => _future('Actions boutique'),
                            ),
                          ),
                        ),
                    ]),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
      extendBody: true,
      bottomNavigationBar: CommercialBottomNavigation(
        scale: scale,
        currentIndex: 2,
        onTap: _nav,
      ),
    );
  }
}

class _TitleRow extends StatelessWidget {
  const _TitleRow({required this.scale, required this.onCreate});
  final double scale;
  final VoidCallback onCreate;

  @override
  Widget build(BuildContext context) {
    double s(double value) => value * scale;
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Mes boutiques',
                style: TextStyle(color: Colors.white, fontSize: s(22.8), fontWeight: FontWeight.w800, letterSpacing: -.45),
              ),
              SizedBox(height: s(4)),
              Text(
                'Suivez les boutiques créées et accompagnez vos clients',
                style: TextStyle(color: const Color(0xFFC6CFDF), fontSize: s(10.8)),
              ),
            ],
          ),
        ),
        SizedBox(width: s(8)),
        SizedBox(
          height: s(43),
          child: FilledButton.icon(
            onPressed: onCreate,
            style: FilledButton.styleFrom(
              backgroundColor: OvanieColors.orange,
              foregroundColor: Colors.white,
              padding: EdgeInsets.symmetric(horizontal: s(13)),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(s(9))),
            ),
            icon: Icon(Icons.add_rounded, size: s(20)),
            label: Text('Ouvrir une boutique', style: TextStyle(fontSize: s(11.2), fontWeight: FontWeight.w600)),
          ),
        ),
      ],
    );
  }
}

class _FilterRow extends StatelessWidget {
  const _FilterRow({
    required this.scale,
    required this.selected,
    required this.summary,
    required this.onSelected,
  });

  final double scale;
  final ShopFilter selected;
  final CommercialShopSummary summary;
  final ValueChanged<ShopFilter> onSelected;

  @override
  Widget build(BuildContext context) {
    double s(double value) => value * scale;
    final items = [
      (ShopFilter.all, Icons.storefront_outlined, 'Toutes', summary.all, const Color(0xFF2688FF)),
      (ShopFilter.online, Icons.circle, 'En ligne', summary.online, const Color(0xFF35DA91)),
      (ShopFilter.pending, Icons.circle, 'En attente', summary.pending, const Color(0xFFFFB21A)),
      (ShopFilter.suspended, Icons.circle, 'Suspendues', summary.suspended, const Color(0xFFFF3E58)),
    ];

    return LayoutBuilder(
      builder: (context, constraints) {
        return Row(
          children: List.generate(items.length, (i) {
            final item = items[i];
            final active = selected == item.$1;
            return Expanded(
              child: Padding(
                padding: EdgeInsets.only(right: i == items.length - 1 ? 0 : s(6)),
                child: InkWell(
                  onTap: () => onSelected(item.$1),
                  borderRadius: BorderRadius.circular(s(13)),
                  child: Container(
                    height: s(40),
                    padding: EdgeInsets.symmetric(horizontal: s(7)),
                    decoration: BoxDecoration(
                      color: active ? const Color(0xFF073E88) : const Color(0xFF0A2854),
                      borderRadius: BorderRadius.circular(s(13)),
                      border: Border.all(
                        color: active ? const Color(0xFF1780FF) : const Color(0xFF284C79),
                        width: .8,
                      ),
                    ),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(item.$2, color: item.$5, size: s(item.$1 == ShopFilter.all ? 17 : 7)),
                        SizedBox(width: s(4)),
                        Flexible(
                          child: Text(
                            item.$3,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(color: Colors.white, fontSize: s(9.2), fontWeight: FontWeight.w600),
                          ),
                        ),
                        SizedBox(width: s(4)),
                        Container(
                          constraints: BoxConstraints(minWidth: s(19), minHeight: s(19)),
                          padding: EdgeInsets.symmetric(horizontal: s(4)),
                          alignment: Alignment.center,
                          decoration: BoxDecoration(
                            color: const Color(0xFF125BC1),
                            borderRadius: BorderRadius.circular(s(10)),
                          ),
                          child: Text(
                            '${item.$4}',
                            style: TextStyle(color: Colors.white, fontSize: s(8.3), fontWeight: FontWeight.w700),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            );
          }),
        );
      },
    );
  }
}

class _ShopCard extends StatelessWidget {
  const _ShopCard({required this.shop, required this.scale, required this.onTap, required this.onMenu});
  final CommercialShop shop;
  final double scale;
  final VoidCallback onTap;
  final VoidCallback onMenu;

  String _money(double value) {
    final raw = value.round().toString();
    final b = StringBuffer();
    for (var i = 0; i < raw.length; i++) {
      if (i > 0 && (raw.length - i) % 3 == 0) b.write(' ');
      b.write(raw[i]);
    }
    return '${b.toString()} FCFA';
  }

  @override
  Widget build(BuildContext context) {
    double s(double value) => value * scale;
    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(s(10)),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: EdgeInsets.all(s(8)),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              ClipRRect(
                borderRadius: BorderRadius.circular(s(8)),
                child: Container(
                  width: s(74),
                  height: s(74),
                  color: const Color(0xFFE8EEF7),
                  child: shop.logoUrl != null
                      ? Image.network(
                          shop.logoUrl!,
                          fit: BoxFit.cover,
                          errorBuilder: (_, __, ___) => const Icon(Icons.storefront, color: Color(0xFF8AA5C9)),
                        )
                      : const Icon(Icons.storefront, color: Color(0xFF8AA5C9), size: 32),
                ),
              ),
              SizedBox(width: s(10)),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                shop.displayName,
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: TextStyle(
                                  color: const Color(0xFF071735),
                                  fontSize: s(12.3),
                                  height: 1.05,
                                  fontWeight: FontWeight.w800,
                                ),
                              ),
                              SizedBox(height: s(2)),
                              Text(
                                shop.category.isEmpty ? 'Boutique OVANIE' : shop.category,
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: TextStyle(color: const Color(0xFF345485), fontSize: s(9.0)),
                              ),
                              SizedBox(height: s(2)),
                              Row(
                                children: [
                                  Icon(Icons.location_on_outlined, color: const Color(0xFF1A4C93), size: s(13)),
                                  SizedBox(width: s(2)),
                                  Expanded(
                                    child: Text(
                                      shop.locationLabel,
                                      maxLines: 1,
                                      overflow: TextOverflow.ellipsis,
                                      style: TextStyle(color: const Color(0xFF345485), fontSize: s(8.9)),
                                    ),
                                  ),
                                ],
                              ),
                            ],
                          ),
                        ),
                        SizedBox(width: s(4)),
                        Column(
                          crossAxisAlignment: CrossAxisAlignment.end,
                          children: [
                            ShopStatusBadge(status: shop.status, label: shop.statusLabel, scale: scale * .92),
                            SizedBox(height: s(1)),
                            IconButton(
                              visualDensity: VisualDensity.compact,
                              padding: EdgeInsets.zero,
                              constraints: BoxConstraints.tightFor(width: s(23), height: s(22)),
                              onPressed: onMenu,
                              icon: Icon(Icons.more_vert_rounded, color: const Color(0xFF1657B2), size: s(17)),
                            ),
                          ],
                        ),
                      ],
                    ),
                    Divider(height: s(9), color: const Color(0xFFE1E7F0)),
                    Row(
                      children: [
                        Expanded(
                          flex: 10,
                          child: _Metric(icon: Icons.inventory_2_outlined, value: '${shop.productCount}', label: 'Produits', scale: scale * .94),
                        ),
                        _Divider(scale: scale, compact: true),
                        Expanded(
                          flex: 11,
                          child: _Metric(icon: Icons.shopping_cart_outlined, value: '${shop.orderCount}', label: 'Commandes', scale: scale * .94),
                        ),
                        _Divider(scale: scale, compact: true),
                        Expanded(
                          flex: 14,
                          child: _Metric(icon: Icons.bar_chart_rounded, value: _money(shop.sales30d), label: 'Ventes (30 j)', scale: scale * .92),
                        ),
                        SizedBox(width: s(4)),
                        Flexible(
                          flex: 15,
                          child: OutlinedButton(
                            onPressed: onTap,
                            style: OutlinedButton.styleFrom(
                              foregroundColor: const Color(0xFF0866E8),
                              side: const BorderSide(color: Color(0xFF1474FF)),
                              padding: EdgeInsets.symmetric(horizontal: s(5), vertical: s(6)),
                              minimumSize: Size.zero,
                              tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                              visualDensity: VisualDensity.compact,
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(s(7))),
                            ),
                            child: FittedBox(
                              fit: BoxFit.scaleDown,
                              child: Row(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  Text('Voir la boutique', style: TextStyle(fontSize: s(7.9), fontWeight: FontWeight.w700)),
                                  SizedBox(width: s(1)),
                                  Icon(Icons.chevron_right_rounded, size: s(13)),
                                ],
                              ),
                            ),
                          ),
                        ),
                      ],
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

class _Metric extends StatelessWidget {
  const _Metric({required this.icon, required this.value, required this.label, required this.scale});
  final IconData icon;
  final String value;
  final String label;
  final double scale;
  @override
  Widget build(BuildContext context) {
    double s(double value) => value * scale;
    return Row(
      mainAxisSize: MainAxisSize.max,
      children: [
        Icon(icon, size: s(16), color: const Color(0xFF154E9A)),
        SizedBox(width: s(3)),
        Expanded(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                value,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(color: const Color(0xFF071735), fontSize: s(9.2), fontWeight: FontWeight.w800),
              ),
              Text(
                label,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(color: const Color(0xFF294C81), fontSize: s(7.3)),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _Divider extends StatelessWidget {
  const _Divider({required this.scale, this.compact = false});
  final double scale;
  final bool compact;
  @override
  Widget build(BuildContext context) => Container(
        width: 1,
        height: 31 * scale,
        margin: EdgeInsets.symmetric(horizontal: (compact ? 3 : 7) * scale),
        color: const Color(0xFFE1E7F0),
      );
}

class _ErrorBanner extends StatelessWidget {
  const _ErrorBanner({required this.message, required this.scale, required this.onRetry});
  final String message;
  final double scale;
  final VoidCallback onRetry;
  @override
  Widget build(BuildContext context) {
    double s(double value) => value * scale;
    return Container(
      padding: EdgeInsets.all(s(12)),
      decoration: BoxDecoration(color: const Color(0xFF4A1F2B), borderRadius: BorderRadius.circular(s(10)), border: Border.all(color: const Color(0xFF8E4258))),
      child: Row(
        children: [
          Icon(Icons.error_outline, color: const Color(0xFFFFC7D0), size: s(19)),
          SizedBox(width: s(8)),
          Expanded(child: Text(message, style: TextStyle(color: const Color(0xFFFFDCE2), fontSize: s(9.5)))),
          TextButton(onPressed: onRetry, child: const Text('Réessayer')),
        ],
      ),
    );
  }
}

class _EmptyShops extends StatelessWidget {
  const _EmptyShops({required this.scale, required this.onCreate});
  final double scale;
  final VoidCallback onCreate;
  @override
  Widget build(BuildContext context) {
    double s(double value) => value * scale;
    return Container(
      padding: EdgeInsets.symmetric(horizontal: s(20), vertical: s(34)),
      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(s(12))),
      child: Column(
        children: [
          Icon(Icons.storefront_outlined, size: s(47), color: const Color(0xFF2B78D5)),
          SizedBox(height: s(9)),
          Text('Aucune boutique trouvée', style: TextStyle(color: const Color(0xFF071735), fontSize: s(15), fontWeight: FontWeight.w800)),
          SizedBox(height: s(5)),
          Text('Ouvrez une boutique vendeur directement depuis le terrain.', textAlign: TextAlign.center, style: TextStyle(color: const Color(0xFF5A7094), fontSize: s(10))),
          SizedBox(height: s(13)),
          FilledButton.icon(onPressed: onCreate, icon: const Icon(Icons.add), label: const Text('Ouvrir une boutique')),
        ],
      ),
    );
  }
}
