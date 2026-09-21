import 'package:flutter/material.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/theme/ovanie_colors.dart';
import '../../../core/widgets/brand_background.dart';
import '../../../core/navigation/commercial_tab_bus.dart';
import '../data/dashboard_service.dart';
import '../models/dashboard_data.dart';
import '../../clients/data/clients_service.dart';
import '../../clients/presentation/clients_screen.dart';
import '../../clients/presentation/create_client_screen.dart';
import '../../shops/data/shops_service.dart';
import '../../shops/presentation/shops_screen.dart';
import '../../shops/presentation/open_shop_screen.dart';
import '../../products/data/products_service.dart';
import '../../products/presentation/products_screen.dart';
import '../../menu/data/menu_service.dart';
import '../../menu/presentation/menu_screen.dart';
import '../../prospecting/data/prospecting_service.dart';
import '../../prospecting/presentation/prospecting_screen.dart';

class CommercialHomeScreen extends StatefulWidget {
  const CommercialHomeScreen({
    super.key,
    required this.dashboardService,
    required this.clientsService,
    required this.shopsService,
    required this.productsService,
    required this.menuService,
    required this.prospectingService,
    required this.initialUser,
    required this.onLogout,
  });

  final DashboardService dashboardService;
  final ClientsService clientsService;
  final ShopsService shopsService;
  final ProductsService productsService;
  final MenuService menuService;
  final ProspectingService prospectingService;
  final Map<String, dynamic> initialUser;
  final Future<void> Function() onLogout;

  @override
  State<CommercialHomeScreen> createState() => _CommercialHomeScreenState();
}

class _CommercialHomeScreenState extends State<CommercialHomeScreen> {
  CommercialDashboardData? _data;
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    CommercialTabBus.requestedTab.addListener(_handleExternalTabRequest);
    _load();
  }

  @override
  void dispose() {
    CommercialTabBus.requestedTab.removeListener(_handleExternalTabRequest);
    super.dispose();
  }

  void _handleExternalTabRequest() {
    final index = CommercialTabBus.requestedTab.value;
    if (index < 0 || !mounted) return;
    CommercialTabBus.clear();

    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      final navigator = Navigator.of(context);

      if (index == 0) {
        navigator.popUntil((route) => route.isFirst);
        return;
      }

      final Route<void>? route = switch (index) {
        1 => _clientsRoute(),
        2 => _shopsRoute(),
        3 => _productsRoute(),
        4 => _menuRoute(),
        _ => null,
      };

      if (route == null) return;

      // Push the destination first, then remove every secondary route.
      // This avoids the one-frame flash of Accueil that occurred when we
      // popped to the root before opening the requested tab.
      navigator.pushAndRemoveUntil(route, (route) => route.isFirst);
    });
  }

  Future<void> _load() async {
    if (!mounted) return;
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final data = await widget.dashboardService.fetch();
      if (!mounted) return;
      setState(() => _data = data);
    } on ApiException catch (error) {
      if (!mounted) return;
      setState(() => _error = error.message);
    } catch (_) {
      if (!mounted) return;
      setState(() => _error = 'Impossible de charger le tableau de bord.');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  CommercialProfile get _profile {
    if (_data != null) return _data!.profile;
    return CommercialProfile.fromJson(widget.initialUser);
  }

  Route<void> _clientsRoute() => MaterialPageRoute<void>(
        builder: (_) => CommercialClientsScreen(
          clientsService: widget.clientsService,
          shopsService: widget.shopsService,
          initialUser: widget.initialUser,
          onLogout: widget.onLogout,
        ),
      );

  Route<void> _shopsRoute() => MaterialPageRoute<void>(
        builder: (_) => CommercialShopsScreen(
          shopsService: widget.shopsService,
          clientsService: widget.clientsService,
          initialUser: widget.initialUser,
          onLogout: widget.onLogout,
        ),
      );

  Route<void> _productsRoute() => MaterialPageRoute<void>(
        builder: (_) => CommercialProductsScreen(
          productsService: widget.productsService,
          clientsService: widget.clientsService,
          shopsService: widget.shopsService,
          initialUser: {
            ...widget.initialUser,
            'first_name': _profile.firstName,
            'name': _profile.name,
            'avatar_url': _profile.avatarUrl,
            'unread_notifications': _data?.unreadNotifications ?? 0,
          },
          onLogout: widget.onLogout,
        ),
      );

  Route<void> _menuRoute() => MaterialPageRoute<void>(
        builder: (_) => CommercialMenuScreen(
          menuService: widget.menuService,
          initialUser: {
            ...widget.initialUser,
            'first_name': _profile.firstName,
            'name': _profile.name,
            'avatar_url': _profile.avatarUrl,
          },
          unreadNotifications: _data?.unreadNotifications ?? 0,
          onLogout: widget.onLogout,
          onOpenProspecting: _openProspecting,
        ),
      );

  Future<void> _openProspecting() async {
    await Navigator.of(context).push(MaterialPageRoute(builder: (_) => CommercialProspectingScreen(
      service: widget.prospectingService,
      shopsService: widget.shopsService,
      clientsService: widget.clientsService,
      initialUser: {...widget.initialUser, 'first_name': _profile.firstName, 'name': _profile.name, 'avatar_url': _profile.avatarUrl},
      unreadNotifications: _data?.unreadNotifications ?? 0,
      onLogout: widget.onLogout,
    )));
    if (mounted) _load();
  }

  Future<void> _openClients() async {
    await Navigator.of(context).push(_clientsRoute());
    if (mounted) _load();
  }

  Future<void> _openCreateClient() async {
    final result = await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => CommercialCreateClientScreen(
          clientsService: widget.clientsService,
          shopsService: widget.shopsService,
          initialUser: widget.initialUser,
          unreadNotifications: _data?.unreadNotifications ?? 0,
          onLogout: widget.onLogout,
        ),
      ),
    );
    if (result != null && mounted) {
      _load();
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Client créé avec succès.'),
          behavior: SnackBarBehavior.floating,
        ),
      );
    }
  }

  Future<void> _openShops() async {
    await Navigator.of(context).push(_shopsRoute());
    if (mounted) _load();
  }

  Future<void> _openCreateShop() async {
    final created = await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) => CommercialOpenShopScreen(
          shopsService: widget.shopsService,
          clientsService: widget.clientsService,
          initialUser: {
            ...widget.initialUser,
            'first_name': _profile.firstName,
            'name': _profile.name,
            'avatar_url': _profile.avatarUrl,
          },
          unreadNotifications: _data?.unreadNotifications ?? 0,
          onLogout: widget.onLogout,
        ),
      ),
    );
    if (created == true && mounted) _load();
  }


  Future<void> _openProducts() async {
    await Navigator.of(context).push(_productsRoute());
    if (mounted) _load();
  }

  Future<void> _openMenu() async {
    await Navigator.of(context).push(_menuRoute());
    if (mounted) _load();
  }

  void _futureModule(String title) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text('$title : cet écran sera relié dans le prochain lot.'),
        behavior: SnackBarBehavior.floating,
      ),
    );
  }

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
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 18,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 4),
              const Text(
                'Espace Commercial OVANIE',
                style: TextStyle(color: Color(0xFF9FB0CF)),
              ),
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

  @override
  Widget build(BuildContext context) {
    final width = MediaQuery.sizeOf(context).width;
    final scale = (width / 472).clamp(.82, 1.08).toDouble();
    double s(double value) => value * scale;

    final data = _data;
    final clients = data?.clients ?? const DashboardStat(total: 0, today: 0);
    final shops = data?.shops ?? const DashboardStat(total: 0, today: 0);
    final products = data?.products ?? const DashboardStat(total: 0, today: 0);
    final toComplete = data?.toComplete ?? 0;

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
                  padding: EdgeInsets.fromLTRB(s(16), s(12), s(16), s(102)),
                  sliver: SliverList(
                    delegate: SliverChildListDelegate.fixed([
                      _Header(
                        profile: _profile,
                        scale: scale,
                        unreadNotifications: data?.unreadNotifications ?? 0,
                        onAvatarTap: _showProfileMenu,
                        onNotificationsTap: () => _futureModule('Notifications'),
                      ),
                      SizedBox(height: s(18)),
                      _SearchBar(
                        scale: scale,
                        onFilterTap: () => _futureModule('Filtres de recherche'),
                        onSubmitted: (_) => _futureModule('Recherche'),
                      ),
                      SizedBox(height: s(14)),
                      if (_error != null) ...[
                        _ErrorBanner(
                          message: _error!,
                          scale: scale,
                          onRetry: _load,
                        ),
                        SizedBox(height: s(12)),
                      ],
                      if (_loading && data == null)
                        Padding(
                          padding: EdgeInsets.only(bottom: s(10)),
                          child: const LinearProgressIndicator(
                            minHeight: 2,
                            color: OvanieColors.blue,
                            backgroundColor: Color(0x332D88E8),
                          ),
                        ),
                      _StatsRow(
                        scale: scale,
                        clients: clients,
                        shops: shops,
                        products: products,
                        toComplete: toComplete,
                        onClients: _openClients,
                        onShops: _openShops,
                        onProducts: _openProducts,
                        onToComplete: _openProducts,
                      ),
                      SizedBox(height: s(15)),
                      Material(
                        color: Colors.transparent,
                        child: InkWell(
                          onTap: _openProspecting,
                          borderRadius: BorderRadius.circular(s(14)),
                          child: Container(
                            padding: EdgeInsets.all(s(14)),
                            decoration: BoxDecoration(color: const Color(0xFF062D5E), borderRadius: BorderRadius.circular(s(14)), border: Border.all(color: const Color(0xFF1F65A9))),
                            child: Row(children:[
                              Container(width:s(46),height:s(46),decoration:BoxDecoration(color:OvanieColors.orange.withOpacity(.16),shape:BoxShape.circle),child:Icon(Icons.storefront_outlined,color:OvanieColors.orange,size:s(25))),
                              SizedBox(width:s(12)),
                              Expanded(child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[Text('Mission de prospection',style:TextStyle(color:Colors.white,fontSize:s(14),fontWeight:FontWeight.w800)),SizedBox(height:s(3)),Text('Consultez la commune affectée par OVANIE, couvrez ses quartiers et enregistrez les vendeurs prospectés.',style:TextStyle(color:const Color(0xFFB8C7DD),fontSize:s(9.5),height:1.35))])),
                              Icon(Icons.chevron_right_rounded,color:OvanieColors.orange,size:s(26)),
                            ]),
                          ),
                        ),
                      ),
                      SizedBox(height: s(15)),
                      _SectionTitle(title: 'Actions rapides', scale: scale),
                      SizedBox(height: s(8)),
                      _QuickActions(
                        scale: scale,
                        onCreateClient: _openCreateClient,
                        onOpenShop: _openCreateShop,
                        onCapture: _openProducts,
                        onComplete: _openProducts,
                      ),
                      SizedBox(height: s(17)),
                      _SectionHeader(
                        title: 'Activité du jour',
                        action: 'Voir tout',
                        scale: scale,
                        onTap: () => _futureModule('Activité du jour'),
                      ),
                      SizedBox(height: s(8)),
                      _ActivityCard(
                        activities: data?.activities ?? const [],
                        scale: scale,
                        onActivityTap: (activity) {
                          switch (activity.type) {
                            case 'client':
                              _openClients();
                              break;
                            case 'shop':
                              _openShops();
                              break;
                            case 'session':
                              _openProducts();
                              break;
                            default:
                              _futureModule('Détail activité');
                          }
                        },
                      ),
                      SizedBox(height: s(14)),
                      _SectionTitle(title: 'Résumé du jour', scale: scale),
                      SizedBox(height: s(7)),
                      _DailySummary(data: data, scale: scale),
                    ]),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
      extendBody: true,
      bottomNavigationBar: _BottomNavigation(
        scale: scale,
        onTap: (index) {
          if (index == 0) return;
          if (index == 1) {
            _openClients();
            return;
          }
          if (index == 2) {
            _openShops();
            return;
          }
          if (index == 3) { _openProducts(); return; }
          if (index == 4) { _openMenu(); return; }
        },
      ),
    );
  }
}

class _Header extends StatelessWidget {
  const _Header({
    required this.profile,
    required this.unreadNotifications,
    required this.scale,
    required this.onAvatarTap,
    required this.onNotificationsTap,
  });

  final CommercialProfile profile;
  final int unreadNotifications;
  final double scale;
  final VoidCallback onAvatarTap;
  final VoidCallback onNotificationsTap;

  @override
  Widget build(BuildContext context) {
    double s(double value) => value * scale;
    return SizedBox(
      height: s(68),
      child: Row(
        children: [
          Image.asset(
            'assets/images/ovanie_logo.png',
            width: s(144),
            fit: BoxFit.contain,
          ),
          SizedBox(width: s(12)),
          Expanded(
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Bonjour ${profile.firstName}',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: Colors.white,
                    fontSize: s(17),
                    fontWeight: FontWeight.w800,
                    letterSpacing: -.25,
                  ),
                ),
                SizedBox(height: s(3)),
                Text(
                  'Prête pour vos actions terrain aujourd’hui',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: const Color(0xFFBDC7DB),
                    fontSize: s(10.5),
                    fontWeight: FontWeight.w400,
                  ),
                ),
              ],
            ),
          ),
          SizedBox(width: s(8)),
          InkWell(
            onTap: onNotificationsTap,
            customBorder: const CircleBorder(),
            child: Stack(
              clipBehavior: Clip.none,
              children: [
                Container(
                  width: s(48),
                  height: s(48),
                  decoration: BoxDecoration(
                    color: const Color(0xFF05214B),
                    shape: BoxShape.circle,
                    border: Border.all(color: const Color(0xFF24538B)),
                  ),
                  child: Icon(
                    Icons.notifications_none_rounded,
                    color: Colors.white,
                    size: s(27),
                  ),
                ),
                if (unreadNotifications > 0)
                  Positioned(
                    right: s(2),
                    top: s(2),
                    child: Container(
                      width: s(8),
                      height: s(8),
                      decoration: const BoxDecoration(
                        color: OvanieColors.orange,
                        shape: BoxShape.circle,
                      ),
                    ),
                  ),
              ],
            ),
          ),
          SizedBox(width: s(8)),
          InkWell(
            onTap: onAvatarTap,
            customBorder: const CircleBorder(),
            child: Container(
              width: s(49),
              height: s(49),
              padding: EdgeInsets.all(s(2)),
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                border: Border.all(color: OvanieColors.blue, width: 1.1),
                color: const Color(0xFF153D70),
              ),
              child: ClipOval(
                child: profile.avatarUrl != null
                    ? Image.network(
                        profile.avatarUrl!,
                        fit: BoxFit.cover,
                        errorBuilder: (_, __, ___) => _AvatarFallback(
                          name: profile.name,
                          scale: scale,
                        ),
                      )
                    : _AvatarFallback(name: profile.name, scale: scale),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _AvatarFallback extends StatelessWidget {
  const _AvatarFallback({required this.name, required this.scale});

  final String name;
  final double scale;

  @override
  Widget build(BuildContext context) {
    final initials = name
        .trim()
        .split(RegExp(r'\s+'))
        .where((part) => part.isNotEmpty)
        .take(2)
        .map((part) => part[0].toUpperCase())
        .join();
    return DecoratedBox(
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [Color(0xFFE8D0BA), Color(0xFF806149)],
        ),
      ),
      child: Center(
        child: Text(
          initials.isEmpty ? 'OC' : initials,
          style: TextStyle(
            color: Colors.white,
            fontSize: 14 * scale,
            fontWeight: FontWeight.w800,
          ),
        ),
      ),
    );
  }
}

class _SearchBar extends StatelessWidget {
  const _SearchBar({
    required this.scale,
    required this.onFilterTap,
    required this.onSubmitted,
  });

  final double scale;
  final VoidCallback onFilterTap;
  final ValueChanged<String> onSubmitted;

  @override
  Widget build(BuildContext context) {
    double s(double value) => value * scale;
    return Container(
      height: s(46),
      decoration: BoxDecoration(
        color: const Color(0xFF0B2852).withOpacity(.86),
        borderRadius: BorderRadius.circular(s(13)),
        border: Border.all(color: const Color(0xFF1B426F), width: .8),
      ),
      child: Row(
        children: [
          SizedBox(width: s(13)),
          Icon(Icons.search_rounded, color: const Color(0xFFAAB5CD), size: s(24)),
          SizedBox(width: s(9)),
          Expanded(
            child: TextField(
              onSubmitted: onSubmitted,
              textInputAction: TextInputAction.search,
              style: TextStyle(color: Colors.white, fontSize: s(12.5)),
              decoration: InputDecoration(
                border: InputBorder.none,
                isDense: true,
                hintText: 'Rechercher un client, une boutique, un produit...',
                hintStyle: TextStyle(
                  color: const Color(0xFFAAB5CD),
                  fontSize: s(12.3),
                ),
              ),
            ),
          ),
          Container(
            width: 1,
            height: s(30),
            color: const Color(0xFF31507A).withOpacity(.55),
          ),
          IconButton(
            onPressed: onFilterTap,
            icon: Icon(
              Icons.tune_rounded,
              color: const Color(0xFFBEC8DB),
              size: s(23),
            ),
          ),
          SizedBox(width: s(2)),
        ],
      ),
    );
  }
}

class _StatsRow extends StatelessWidget {
  const _StatsRow({
    required this.scale,
    required this.clients,
    required this.shops,
    required this.products,
    required this.toComplete,
    required this.onClients,
    required this.onShops,
    required this.onProducts,
    required this.onToComplete,
  });

  final double scale;
  final DashboardStat clients;
  final DashboardStat shops;
  final DashboardStat products;
  final int toComplete;
  final VoidCallback onClients;
  final VoidCallback onShops;
  final VoidCallback onProducts;
  final VoidCallback onToComplete;

  @override
  Widget build(BuildContext context) {
    double s(double value) => value * scale;
    return Row(
      children: [
        Expanded(
          child: _StatCard(
            scale: scale,
            icon: Icons.groups_2_outlined,
            iconColor: const Color(0xFF1685FF),
            label: 'Clients créés',
            value: clients.total.toString(),
            footer: '+${clients.today} aujourd’hui',
            footerColor: const Color(0xFF99BDD9),
            onTap: onClients,
          ),
        ),
        SizedBox(width: s(7)),
        Expanded(
          child: _StatCard(
            scale: scale,
            icon: Icons.storefront_outlined,
            iconColor: const Color(0xFF25B45C),
            label: 'Boutiques ouvertes',
            value: shops.total.toString(),
            footer: '+${shops.today} aujourd’hui',
            footerColor: const Color(0xFF48D77B),
            onTap: onShops,
          ),
        ),
        SizedBox(width: s(7)),
        Expanded(
          child: _StatCard(
            scale: scale,
            icon: Icons.inventory_2_outlined,
            iconColor: const Color(0xFF7350DC),
            label: 'Produits capturés',
            value: products.total.toString(),
            footer: '+${products.today} aujourd’hui',
            footerColor: const Color(0xFFBC88FF),
            onTap: onProducts,
          ),
        ),
        SizedBox(width: s(7)),
        Expanded(
          child: _StatCard(
            scale: scale,
            icon: Icons.assignment_outlined,
            iconColor: OvanieColors.orange,
            label: 'À compléter',
            value: toComplete.toString(),
            footer: 'Voir la liste',
            footerColor: const Color(0xFFC7CFDC),
            showArrow: true,
            onTap: onToComplete,
          ),
        ),
      ],
    );
  }
}

class _StatCard extends StatelessWidget {
  const _StatCard({
    required this.scale,
    required this.icon,
    required this.iconColor,
    required this.label,
    required this.value,
    required this.footer,
    required this.footerColor,
    required this.onTap,
    this.showArrow = false,
  });

  final double scale;
  final IconData icon;
  final Color iconColor;
  final String label;
  final String value;
  final String footer;
  final Color footerColor;
  final VoidCallback onTap;
  final bool showArrow;

  @override
  Widget build(BuildContext context) {
    double s(double value) => value * scale;
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(s(10)),
        child: Container(
          height: s(126),
          padding: EdgeInsets.fromLTRB(s(10), s(10), s(8), s(9)),
          decoration: BoxDecoration(
            color: const Color(0xFF082853).withOpacity(.72),
            borderRadius: BorderRadius.circular(s(10)),
            border: Border.all(color: const Color(0xFF27517E).withOpacity(.85), width: .75),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: s(36),
                height: s(36),
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: iconColor.withOpacity(.18),
                  boxShadow: [
                    BoxShadow(color: iconColor.withOpacity(.22), blurRadius: s(8)),
                  ],
                ),
                child: Icon(icon, color: Colors.white, size: s(21)),
              ),
              SizedBox(height: s(7)),
              Text(
                label,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  color: Colors.white,
                  fontSize: s(10.5),
                  fontWeight: FontWeight.w500,
                ),
              ),
              SizedBox(height: s(3)),
              Text(
                value,
                style: TextStyle(
                  color: Colors.white,
                  fontSize: s(24),
                  height: 1,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const Spacer(),
              Row(
                children: [
                  Expanded(
                    child: Text(
                      footer,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        color: footerColor,
                        fontSize: s(9.5),
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                  ),
                  if (showArrow)
                    Icon(Icons.chevron_right_rounded, color: OvanieColors.orange, size: s(16))
                  else
                    Icon(Icons.north_east_rounded, color: footerColor, size: s(12)),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _SectionTitle extends StatelessWidget {
  const _SectionTitle({required this.title, required this.scale});

  final String title;
  final double scale;

  @override
  Widget build(BuildContext context) {
    return Text(
      title,
      style: TextStyle(
        color: Colors.white,
        fontSize: 14 * scale,
        fontWeight: FontWeight.w800,
        letterSpacing: -.15,
      ),
    );
  }
}

class _SectionHeader extends StatelessWidget {
  const _SectionHeader({
    required this.title,
    required this.action,
    required this.scale,
    required this.onTap,
  });

  final String title;
  final String action;
  final double scale;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(child: _SectionTitle(title: title, scale: scale)),
        TextButton(
          onPressed: onTap,
          style: TextButton.styleFrom(
            padding: EdgeInsets.symmetric(horizontal: 8 * scale, vertical: 1),
            minimumSize: Size.zero,
            tapTargetSize: MaterialTapTargetSize.shrinkWrap,
          ),
          child: Text(
            action,
            style: TextStyle(color: const Color(0xFF22A0FF), fontSize: 11 * scale),
          ),
        ),
      ],
    );
  }
}

class _QuickActions extends StatelessWidget {
  const _QuickActions({
    required this.scale,
    required this.onCreateClient,
    required this.onOpenShop,
    required this.onCapture,
    required this.onComplete,
  });

  final double scale;
  final VoidCallback onCreateClient;
  final VoidCallback onOpenShop;
  final VoidCallback onCapture;
  final VoidCallback onComplete;

  @override
  Widget build(BuildContext context) {
    double s(double value) => value * scale;

    // IMPORTANT: this row is inside a SliverList, whose vertical constraint is
    // unbounded. Giving the quick-action area an explicit height prevents the
    // cards from collapsing/disappearing on physical Android devices.
    return SizedBox(
      height: s(106),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Expanded(
            child: _QuickCard(
              scale: scale,
              title: 'Créer\nun client',
              icon: Icons.person_add_alt_1_rounded,
              color: const Color(0xFF198AFF),
              onTap: onCreateClient,
            ),
          ),
          SizedBox(width: s(7)),
          Expanded(
            child: _QuickCard(
              scale: scale,
              title: 'Ouvrir une\nboutique',
              icon: Icons.add_business_rounded,
              color: const Color(0xFF28A852),
              onTap: onOpenShop,
            ),
          ),
          SizedBox(width: s(7)),
          Expanded(
            child: _QuickCard(
              scale: scale,
              title: 'Capturer des\nproduits',
              icon: Icons.photo_camera_rounded,
              color: const Color(0xFF5E39BD),
              onTap: onCapture,
            ),
          ),
          SizedBox(width: s(7)),
          Expanded(
            child: _QuickCard(
              scale: scale,
              title: 'Produits à\ncompléter',
              icon: Icons.assignment_rounded,
              color: OvanieColors.orange,
              onTap: onComplete,
            ),
          ),
        ],
      ),
    );
  }
}

class _QuickCard extends StatelessWidget {
  const _QuickCard({
    required this.scale,
    required this.title,
    required this.icon,
    required this.color,
    required this.onTap,
  });

  final double scale;
  final String title;
  final IconData icon;
  final Color color;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    double s(double value) => value * scale;
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(s(12)),
        child: Container(
          height: double.infinity,
          padding: EdgeInsets.fromLTRB(s(10), s(9), s(6), s(8)),
          decoration: BoxDecoration(
            color: const Color(0xFFF9FAFC),
            borderRadius: BorderRadius.circular(s(12)),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withOpacity(.08),
                blurRadius: 7,
                offset: const Offset(0, 3),
              ),
            ],
          ),
          child: Column(
            mainAxisSize: MainAxisSize.max,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: s(37),
                height: s(37),
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: color.withOpacity(.12),
                ),
                child: Icon(icon, color: color, size: s(22)),
              ),
              SizedBox(height: s(8)),
              Expanded(
                child: Align(
                  alignment: Alignment.bottomLeft,
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      Expanded(
                        child: Text(
                          title,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(
                            color: OvanieColors.ink,
                            fontSize: s(10.8),
                            height: 1.16,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ),
                      SizedBox(width: s(1)),
                      Icon(
                        Icons.chevron_right_rounded,
                        color: color,
                        size: s(16),
                      ),
                    ],
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

class _ActivityCard extends StatelessWidget {
  const _ActivityCard({
    required this.activities,
    required this.scale,
    required this.onActivityTap,
  });

  final List<DashboardActivity> activities;
  final double scale;
  final ValueChanged<DashboardActivity> onActivityTap;

  @override
  Widget build(BuildContext context) {
    double s(double value) => value * scale;
    final visible = activities.take(3).toList();

    return Container(
      decoration: BoxDecoration(
        color: const Color(0xFF082853).withOpacity(.68),
        borderRadius: BorderRadius.circular(s(10)),
        border: Border.all(color: const Color(0xFF28527D).withOpacity(.75), width: .8),
      ),
      child: visible.isEmpty
          ? Padding(
              padding: EdgeInsets.all(s(18)),
              child: Text(
                'Aucune activité enregistrée aujourd’hui.',
                textAlign: TextAlign.center,
                style: TextStyle(color: const Color(0xFFAEBBD1), fontSize: s(11.5)),
              ),
            )
          : Column(
              children: List.generate(visible.length, (index) {
                final activity = visible[index];
                return Column(
                  children: [
                    InkWell(
                      onTap: () => onActivityTap(activity),
                      child: _ActivityRow(activity: activity, scale: scale),
                    ),
                    if (index != visible.length - 1)
                      Padding(
                        padding: EdgeInsets.symmetric(horizontal: s(14)),
                        child: Divider(height: 1, color: const Color(0xFF385777).withOpacity(.65)),
                      ),
                  ],
                );
              }),
            ),
    );
  }
}

class _ActivityRow extends StatelessWidget {
  const _ActivityRow({required this.activity, required this.scale});

  final DashboardActivity activity;
  final double scale;

  @override
  Widget build(BuildContext context) {
    double s(double value) => value * scale;
    final config = _activityConfig(activity.type);
    final isInProgress = activity.status.toLowerCase().contains('cours');

    return Padding(
      padding: EdgeInsets.fromLTRB(s(12), s(8), s(10), s(7)),
      child: Row(
        children: [
          Container(
            width: s(39),
            height: s(39),
            decoration: BoxDecoration(shape: BoxShape.circle, color: config.color.withOpacity(.16)),
            child: Icon(config.icon, color: config.color, size: s(22)),
          ),
          SizedBox(width: s(11)),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  activity.subtitle,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(color: const Color(0xFFB9C4D8), fontSize: s(10.5)),
                ),
                SizedBox(height: s(2)),
                Text(
                  activity.title,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: Colors.white,
                    fontSize: s(12),
                    fontWeight: FontWeight.w700,
                  ),
                ),
                if (activity.detail != null && activity.detail!.isNotEmpty) ...[
                  SizedBox(height: s(3)),
                  Text(
                    activity.detail!,
                    style: TextStyle(color: const Color(0xFFBFC9DA), fontSize: s(9.5)),
                  ),
                ],
              ],
            ),
          ),
          Container(
            padding: EdgeInsets.symmetric(horizontal: s(9), vertical: s(4)),
            decoration: BoxDecoration(
              color: isInProgress
                  ? const Color(0xFF7A4700).withOpacity(.95)
                  : const Color(0xFF0451A4).withOpacity(.85),
              borderRadius: BorderRadius.circular(s(14)),
            ),
            child: Text(
              activity.status.isEmpty ? 'Terminé' : activity.status,
              style: TextStyle(
                color: isInProgress ? const Color(0xFFFFB128) : const Color(0xFF32A5FF),
                fontSize: s(9.2),
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
          SizedBox(width: s(10)),
          Text(
            activity.time,
            style: TextStyle(color: const Color(0xFFD2D8E4), fontSize: s(9.8)),
          ),
          SizedBox(width: s(4)),
          Icon(Icons.chevron_right_rounded, color: Colors.white, size: s(18)),
        ],
      ),
    );
  }
}

class _ActivityVisual {
  const _ActivityVisual(this.icon, this.color);
  final IconData icon;
  final Color color;
}

_ActivityVisual _activityConfig(String type) {
  switch (type) {
    case 'shop':
      return const _ActivityVisual(Icons.storefront_rounded, Color(0xFF3BC771));
    case 'client':
      return const _ActivityVisual(Icons.person_rounded, Color(0xFF198AFF));
    case 'product':
    case 'session':
      return const _ActivityVisual(Icons.inventory_2_rounded, Color(0xFF7546DD));
    default:
      return const _ActivityVisual(Icons.check_circle_outline_rounded, Color(0xFF5FA9FF));
  }
}

class _DailySummary extends StatelessWidget {
  const _DailySummary({required this.data, required this.scale});

  final CommercialDashboardData? data;
  final double scale;

  @override
  Widget build(BuildContext context) {
    double s(double value) => value * scale;
    final done = data?.dailyObjectiveDone;
    final target = data?.dailyObjectiveTarget;
    final dayProgress = done != null && target != null && target > 0
        ? (done / target).clamp(0.0, 1.0).toDouble()
        : 0.0;
    final monthly = data?.monthlyProgress;

    return Container(
      constraints: BoxConstraints(minHeight: s(102)),
      padding: EdgeInsets.symmetric(horizontal: s(14), vertical: s(14)),
      decoration: BoxDecoration(
        color: const Color(0xFFF8F9FB),
        borderRadius: BorderRadius.circular(s(12)),
        boxShadow: [
          BoxShadow(color: Colors.black.withOpacity(.08), blurRadius: 10, offset: const Offset(0, 4)),
        ],
      ),
      child: Row(
        children: [
          Expanded(
            flex: 13,
            child: _ObjectiveColumn(
              scale: scale,
              done: done,
              target: target,
              progress: dayProgress,
            ),
          ),
          _VerticalSeparator(scale: scale),
          Expanded(
            flex: 9,
            child: _SummaryMetric(
              scale: scale,
              icon: Icons.storefront_outlined,
              color: const Color(0xFF24AA55),
              label: 'Boutiques\nprospectées',
              value: '${data?.shopsProspectedToday ?? 0}',
            ),
          ),
          _VerticalSeparator(scale: scale),
          Expanded(
            flex: 10,
            child: _SummaryMetric(
              scale: scale,
              icon: Icons.flag_rounded,
              color: OvanieColors.orange,
              label: 'Objectif mensuel',
              value: monthly == null ? '—' : '${(monthly * 100).round()}%',
              footer: monthly == null ? 'Non configuré' : 'Progression',
            ),
          ),
        ],
      ),
    );
  }
}

class _ObjectiveColumn extends StatelessWidget {
  const _ObjectiveColumn({
    required this.scale,
    required this.done,
    required this.target,
    required this.progress,
  });

  final double scale;
  final int? done;
  final int? target;
  final double progress;

  @override
  Widget build(BuildContext context) {
    double s(double value) => value * scale;
    return Padding(
      padding: EdgeInsets.only(right: s(10)),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: s(35),
                height: s(35),
                decoration: const BoxDecoration(
                  shape: BoxShape.circle,
                  color: Color(0xFFE3F0FF),
                ),
                child: Icon(Icons.track_changes_rounded, color: const Color(0xFF1577E4), size: s(22)),
              ),
              SizedBox(width: s(8)),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Objectif du jour',
                      style: TextStyle(color: OvanieColors.ink, fontSize: s(10.5), fontWeight: FontWeight.w500),
                    ),
                    SizedBox(height: s(3)),
                    Text.rich(
                      TextSpan(
                        style: TextStyle(color: OvanieColors.ink, fontSize: s(18), fontWeight: FontWeight.w800),
                        children: [
                          TextSpan(text: done == null || target == null ? '— / —' : '$done / $target'),
                          TextSpan(
                            text: ' visites',
                            style: TextStyle(fontSize: s(10.5), fontWeight: FontWeight.w500),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          SizedBox(height: s(9)),
          ClipRRect(
            borderRadius: BorderRadius.circular(10),
            child: LinearProgressIndicator(
              value: progress,
              minHeight: s(4),
              backgroundColor: const Color(0xFFE5EAF1),
              color: const Color(0xFF1D86F5),
            ),
          ),
          SizedBox(height: s(4)),
          Text(
            done == null || target == null ? 'Objectif non configuré' : '${(progress * 100).round()}% atteint',
            style: TextStyle(color: const Color(0xFF34405A), fontSize: s(8.8)),
          ),
        ],
      ),
    );
  }
}

class _SummaryMetric extends StatelessWidget {
  const _SummaryMetric({
    required this.scale,
    required this.icon,
    required this.color,
    required this.label,
    required this.value,
    this.footer,
  });

  final double scale;
  final IconData icon;
  final Color color;
  final String label;
  final String value;
  final String? footer;

  @override
  Widget build(BuildContext context) {
    double s(double value) => value * scale;
    return Padding(
      padding: EdgeInsets.symmetric(horizontal: s(8)),
      child: Row(
        children: [
          Container(
            width: s(35),
            height: s(35),
            decoration: BoxDecoration(shape: BoxShape.circle, color: color.withOpacity(.10)),
            child: Icon(icon, color: color, size: s(21)),
          ),
          SizedBox(width: s(8)),
          Expanded(
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  label,
                  style: TextStyle(color: OvanieColors.ink, fontSize: s(9.5), height: 1.15),
                ),
                SizedBox(height: s(4)),
                Text(
                  value,
                  style: TextStyle(color: OvanieColors.ink, fontSize: s(18), fontWeight: FontWeight.w800),
                ),
                if (footer != null)
                  Text(
                    footer!,
                    style: TextStyle(color: const Color(0xFF495775), fontSize: s(8.6)),
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _VerticalSeparator extends StatelessWidget {
  const _VerticalSeparator({required this.scale});
  final double scale;

  @override
  Widget build(BuildContext context) {
    return Container(width: 1, height: 70 * scale, color: const Color(0xFFD6DAE2));
  }
}

class _BottomNavigation extends StatelessWidget {
  const _BottomNavigation({required this.scale, required this.onTap});

  final double scale;
  final ValueChanged<int> onTap;

  @override
  Widget build(BuildContext context) {
    double s(double value) => value * scale;
    const items = [
      (Icons.home_rounded, 'Accueil'),
      (Icons.groups_2_outlined, 'Clients'),
      (Icons.storefront_outlined, 'Boutiques'),
      (Icons.inventory_2_outlined, 'Produits'),
      (Icons.menu_rounded, 'Menu'),
    ];

    return SafeArea(
      top: false,
      minimum: EdgeInsets.fromLTRB(s(16), 0, s(16), s(8)),
      child: Container(
        height: s(66),
        padding: EdgeInsets.symmetric(horizontal: s(6), vertical: s(4)),
        decoration: BoxDecoration(
          color: const Color(0xFF031B42).withOpacity(.97),
          borderRadius: BorderRadius.circular(s(33)),
          border: Border.all(color: const Color(0xFF244B7A), width: .8),
          boxShadow: [
            BoxShadow(color: Colors.black.withOpacity(.25), blurRadius: 16, offset: const Offset(0, 5)),
          ],
        ),
        child: Row(
          children: List.generate(items.length, (index) {
            final active = index == 0;
            final item = items[index];
            return Expanded(
              child: InkWell(
                onTap: () => onTap(index),
                borderRadius: BorderRadius.circular(s(28)),
                child: Container(
                  decoration: active
                      ? BoxDecoration(
                          color: const Color(0xFF123867),
                          borderRadius: BorderRadius.circular(s(27)),
                          border: Border.all(color: const Color(0xFF2D5582), width: .7),
                        )
                      : null,
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(item.$1, color: active ? OvanieColors.orange : Colors.white, size: s(22)),
                      SizedBox(height: s(2)),
                      Text(
                        item.$2,
                        style: TextStyle(
                          color: active ? OvanieColors.orange : Colors.white,
                          fontSize: s(9.5),
                          fontWeight: active ? FontWeight.w700 : FontWeight.w500,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            );
          }),
        ),
      ),
    );
  }
}

class _ErrorBanner extends StatelessWidget {
  const _ErrorBanner({
    required this.message,
    required this.scale,
    required this.onRetry,
  });

  final String message;
  final double scale;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    double s(double value) => value * scale;
    return Container(
      padding: EdgeInsets.symmetric(horizontal: s(12), vertical: s(9)),
      decoration: BoxDecoration(
        color: const Color(0xFF4D1820).withOpacity(.92),
        borderRadius: BorderRadius.circular(s(10)),
        border: Border.all(color: const Color(0xFFA7474F).withOpacity(.65)),
      ),
      child: Row(
        children: [
          Icon(Icons.wifi_off_rounded, color: const Color(0xFFFFB4B8), size: s(18)),
          SizedBox(width: s(8)),
          Expanded(
            child: Text(
              message,
              style: TextStyle(color: Colors.white, fontSize: s(10.5), height: 1.25),
            ),
          ),
          TextButton(
            onPressed: onRetry,
            child: Text('Réessayer', style: TextStyle(fontSize: s(10.5))),
          ),
        ],
      ),
    );
  }
}
