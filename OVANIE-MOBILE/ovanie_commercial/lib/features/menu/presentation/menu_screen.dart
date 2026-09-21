import 'package:flutter/material.dart';

import '../../../core/theme/ovanie_colors.dart';
import '../../dashboard/models/dashboard_data.dart';
import '../data/menu_service.dart';
import '../models/menu_data.dart';
import 'about_screen.dart';
import 'draft_products_screen.dart';
import 'menu_shared.dart';
import 'notifications_screen.dart';
import 'preferences_screen.dart';
import 'profile_screen.dart';
import 'synchronization_screen.dart';
import 'visited_shops_screen.dart';

class CommercialMenuScreen extends StatefulWidget {
  const CommercialMenuScreen({
    super.key,
    required this.menuService,
    required this.initialUser,
    required this.unreadNotifications,
    required this.onLogout,
    required this.onOpenProspecting,
  });

  final MenuService menuService;
  final Map<String, dynamic> initialUser;
  final int unreadNotifications;
  final Future<void> Function() onLogout;
  final VoidCallback onOpenProspecting;

  @override
  State<CommercialMenuScreen> createState() => _CommercialMenuScreenState();
}

class _CommercialMenuScreenState extends State<CommercialMenuScreen> {
  MenuOverviewData? _data;
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    if (mounted) setState(() { _loading = true; _error = null; });
    try {
      final data = await widget.menuService.overview();
      if (mounted) setState(() => _data = data);
    } catch (e) {
      if (mounted) setState(() => _error = e.toString().replaceFirst('ApiException: ', ''));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  CommercialProfile get _headerProfile => menuHeaderProfile(
        initialUser: widget.initialUser,
        loaded: _data?.profile,
      );

  int get _unread => _data?.unreadNotifications ?? widget.unreadNotifications;

  Future<void> _push(Widget page) async {
    await Navigator.of(context).push(MaterialPageRoute(builder: (_) => page));
    if (mounted) _load();
  }

  Future<void> _openProfile() => _push(
        CommercialProfileScreen(
          menuService: widget.menuService,
          initialUser: widget.initialUser,
          initialProfile: _data?.profile,
          unreadNotifications: _unread,
          onLogout: widget.onLogout,
        ),
      );

  Future<void> _openNotifications() => _push(
        CommercialNotificationsScreen(
          menuService: widget.menuService,
          initialUser: widget.initialUser,
          initialProfile: _data?.profile,
          unreadNotifications: _unread,
        ),
      );

  Future<void> _openPreferences() => _push(
        CommercialPreferencesScreen(
          menuService: widget.menuService,
          initialUser: widget.initialUser,
          initialProfile: _data?.profile,
          unreadNotifications: _unread,
        ),
      );

  Future<void> _openVisits() => _push(
        CommercialVisitedShopsScreen(
          menuService: widget.menuService,
          initialUser: widget.initialUser,
          initialProfile: _data?.profile,
          unreadNotifications: _unread,
        ),
      );

  Future<void> _openDrafts() => _push(
        CommercialDraftProductsScreen(
          menuService: widget.menuService,
          initialUser: widget.initialUser,
          initialProfile: _data?.profile,
          unreadNotifications: _unread,
        ),
      );

  Future<void> _openAbout() => _push(
        CommercialAboutScreen(
          initialUser: widget.initialUser,
          initialProfile: _data?.profile,
          unreadNotifications: _unread,
        ),
      );

  Future<void> _openSync() => _push(
        CommercialSynchronizationScreen(
          menuService: widget.menuService,
          initialUser: widget.initialUser,
          initialProfile: _data?.profile,
          unreadNotifications: _unread,
        ),
      );

  void _support() {
    showDialog<void>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Aide & support OVANIE'),
        content: const Text('Pour toute assistance, contactez le support OVANIE au 01 61 78 18 18.'),
        actions: [TextButton(onPressed: () => Navigator.pop(context), child: const Text('Fermer'))],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final s = menuScaleOf(context).call;
    return MenuPageScaffold(
      profile: _headerProfile,
      unreadNotifications: _unread,
      menuRoot: true,
      onNotificationsTap: _openNotifications,
      onAvatarTap: _openProfile,
      body: RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          padding: EdgeInsets.fromLTRB(s(16), s(12), s(16), s(102)),
          children: [
            MenuSearchBar(
              hint: 'Rechercher une boutique, un produit, une référence...',
              onFilterTap: () {},
            ),
            SizedBox(height: s(14)),
            LoadingOrError(
              loading: _loading,
              error: _error,
              onRetry: _load,
              child: Column(
                children: [
                  _ProfileHero(
                    profile: _data?.profile,
                    fallback: _headerProfile,
                    unread: _unread,
                    onProfile: _openProfile,
                    onSecurity: _openProfile,
                    onNotifications: _openNotifications,
                  ),
                  SizedBox(height: s(10)),
                  _MenuGroup(
                    title: 'Mon compte',
                    rows: [
                      _MenuRow(icon: Icons.person_outline_rounded, title: 'Mon profil', onTap: _openProfile),
                      _MenuRow(icon: Icons.notifications_none_rounded, title: 'Notifications', badge: _unread, onTap: _openNotifications),
                      _MenuRow(icon: Icons.tune_rounded, title: 'Préférences', onTap: _openPreferences),
                    ],
                  ),
                  SizedBox(height: s(10)),
                  _MenuGroup(
                    title: 'Activité terrain',
                    rows: [
                      _MenuRow(icon: Icons.map_outlined, title: 'Mission de prospection', trailingText: 'Commune & quartiers', onTap: widget.onOpenProspecting),
                      _MenuRow(icon: Icons.storefront_outlined, title: 'Boutiques visitées', trailingText: '${_data?.visitedThisMonth ?? 0} ce mois', onTap: _openVisits),
                      _MenuRow(icon: Icons.camera_alt_outlined, title: 'Sessions produits', trailingText: '${_data?.sessionsInProgress ?? 0} en cours', onTap: () => menuTabNavigate(context, 3, menuRoot: true)),
                      _MenuRow(icon: Icons.note_add_outlined, title: 'Produits en brouillon', trailingText: '${_data?.draftsCount ?? 0}', onTap: _openDrafts),
                    ],
                  ),
                  SizedBox(height: s(10)),
                  _MenuGroup(
                    title: 'Assistance',
                    rows: [
                      _MenuRow(icon: Icons.support_agent_rounded, title: 'Aide & support', onTap: _support),
                      _MenuRow(icon: Icons.info_outline_rounded, title: 'À propos', onTap: _openAbout),
                    ],
                  ),
                  SizedBox(height: s(10)),
                  _MenuGroup(
                    title: 'Paramètres',
                    rows: [
                      _MenuRow(icon: Icons.sync_rounded, title: 'Synchronisation', onTap: _openSync),
                      _MenuRow(
                        icon: Icons.logout_rounded,
                        title: 'Déconnexion',
                        danger: true,
                        onTap: () async {
                          final confirmed = await showDialog<bool>(
                            context: context,
                            builder: (context) => AlertDialog(
                              title: const Text('Se déconnecter ?'),
                              content: const Text('Vous devrez vous reconnecter pour accéder à OVANIE Commercial.'),
                              actions: [
                                TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Annuler')),
                                FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Déconnexion')),
                              ],
                            ),
                          );
                          if (confirmed == true) await widget.onLogout();
                        },
                      ),
                    ],
                  ),
                  SizedBox(height: s(10)),
                  const MenuInfoStrip(text: 'Astuce : utilisez le menu pour accéder rapidement à vos actions terrain sans revenir à l’accueil.'),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _ProfileHero extends StatelessWidget {
  const _ProfileHero({required this.profile, required this.fallback, required this.unread, required this.onProfile, required this.onSecurity, required this.onNotifications});
  final MenuProfileData? profile;
  final CommercialProfile fallback;
  final int unread;
  final VoidCallback onProfile;
  final VoidCallback onSecurity;
  final VoidCallback onNotifications;

  @override
  Widget build(BuildContext context) {
    final s = menuScaleOf(context).call;
    final name = profile?.name ?? fallback.name;
    final avatar = profile?.avatarUrl ?? fallback.avatarUrl;
    return DarkMenuCard(
      padding: EdgeInsets.all(s(15)),
      child: Column(
        children: [
          Row(
            children: [
              Container(
                width: s(92),
                height: s(92),
                padding: EdgeInsets.all(s(4)),
                decoration: BoxDecoration(shape: BoxShape.circle, border: Border.all(color: const Color(0xFF147AE7), width: s(3))),
                child: ClipOval(
                  child: avatar == null
                      ? _InitialAvatar(name: name)
                      : Image.network(avatar, fit: BoxFit.cover, errorBuilder: (_, __, ___) => _InitialAvatar(name: name)),
                ),
              ),
              SizedBox(width: s(16)),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(name, maxLines: 1, overflow: TextOverflow.ellipsis, style: TextStyle(color: Colors.white, fontSize: s(22), fontWeight: FontWeight.w800)),
                    SizedBox(height: s(5)),
                    Row(
                      children: [
                        Flexible(child: Text(profile?.jobTitle ?? 'Commercial terrain', overflow: TextOverflow.ellipsis, style: TextStyle(color: menuBlue, fontSize: s(12.5)))),
                        SizedBox(width: s(8)),
                        Container(
                          padding: EdgeInsets.symmetric(horizontal: s(8), vertical: s(4)),
                          decoration: BoxDecoration(color: const Color(0xFF0B513F), borderRadius: BorderRadius.circular(s(14)), border: Border.all(color: const Color(0xFF16825C))),
                          child: Row(mainAxisSize: MainAxisSize.min, children: [
                            Container(width: s(7), height: s(7), decoration: const BoxDecoration(color: Color(0xFF37D883), shape: BoxShape.circle)),
                            SizedBox(width: s(5)),
                            Text(profile?.active == false ? 'Inactif' : 'Actif', style: TextStyle(color: const Color(0xFF57E19A), fontSize: s(10.5))),
                          ]),
                        ),
                      ],
                    ),
                    SizedBox(height: s(14)),
                    Row(children: [
                      Icon(Icons.storefront_outlined, color: Colors.white, size: s(18)),
                      SizedBox(width: s(8)),
                      Flexible(child: Text('OVANIE Commercial', overflow: TextOverflow.ellipsis, style: TextStyle(color: Colors.white, fontSize: s(12)))),
                      SizedBox(width: s(12)),
                      Container(width: 1, height: s(20), color: Colors.white24),
                      SizedBox(width: s(12)),
                      Icon(Icons.location_on_outlined, color: Colors.white, size: s(18)),
                      SizedBox(width: s(5)),
                      Flexible(child: Text(profile?.city ?? 'Abidjan', overflow: TextOverflow.ellipsis, style: TextStyle(color: Colors.white, fontSize: s(12)))),
                    ]),
                  ],
                ),
              ),
              Icon(Icons.chevron_right_rounded, color: Colors.white, size: s(30)),
            ],
          ),
          SizedBox(height: s(14)),
          Row(
            children: [
              Expanded(child: _HeroAction(icon: Icons.person_outline_rounded, label: 'Mon profil', onTap: onProfile)),
              SizedBox(width: s(10)),
              Expanded(child: _HeroAction(icon: Icons.shield_outlined, label: 'Sécurité', onTap: onSecurity)),
              SizedBox(width: s(10)),
              Expanded(child: _HeroAction(icon: Icons.notifications_none_rounded, label: 'Notifications', badge: unread, onTap: onNotifications)),
            ],
          ),
        ],
      ),
    );
  }
}

class _InitialAvatar extends StatelessWidget {
  const _InitialAvatar({required this.name});
  final String name;
  @override
  Widget build(BuildContext context) {
    final s = menuScaleOf(context).call;
    final initials = name.split(RegExp(r'\s+')).where((e) => e.isNotEmpty).take(2).map((e) => e[0].toUpperCase()).join();
    return Container(color: const Color(0xFFCBA36A), alignment: Alignment.center, child: Text(initials.isEmpty ? 'OC' : initials, style: TextStyle(color: Colors.white, fontSize: s(22), fontWeight: FontWeight.w800)));
  }
}

class _HeroAction extends StatelessWidget {
  const _HeroAction({required this.icon, required this.label, required this.onTap, this.badge = 0});
  final IconData icon;
  final String label;
  final VoidCallback onTap;
  final int badge;

  @override
  Widget build(BuildContext context) {
    final s = menuScaleOf(context).call;
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(s(10)),
      child: Container(
        height: s(47),
        decoration: BoxDecoration(color: const Color(0xFF08274F), borderRadius: BorderRadius.circular(s(10)), border: Border.all(color: const Color(0xFF315986))),
        child: Stack(
          children: [
            Center(child: Row(mainAxisSize: MainAxisSize.min, children: [Icon(icon, color: Colors.white, size: s(20)), SizedBox(width: s(8)), Flexible(child: Text(label, overflow: TextOverflow.ellipsis, style: TextStyle(color: Colors.white, fontSize: s(10.7))))])),
            if (badge > 0)
              Positioned(right: s(5), top: s(6), child: Container(width: s(20), height: s(20), alignment: Alignment.center, decoration: const BoxDecoration(color: OvanieColors.orange, shape: BoxShape.circle), child: Text('$badge', style: TextStyle(color: Colors.white, fontSize: s(8.5), fontWeight: FontWeight.w800)))),
          ],
        ),
      ),
    );
  }
}

class _MenuGroup extends StatelessWidget {
  const _MenuGroup({required this.title, required this.rows});
  final String title;
  final List<_MenuRow> rows;

  @override
  Widget build(BuildContext context) {
    final s = menuScaleOf(context).call;
    return DarkMenuCard(
      padding: EdgeInsets.zero,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Container(
            padding: EdgeInsets.fromLTRB(s(14), s(8), s(14), s(7)),
            decoration: BoxDecoration(color: const Color(0xFF073365).withOpacity(.55), borderRadius: BorderRadius.vertical(top: Radius.circular(s(14)))),
            child: MenuSectionTitle(title),
          ),
          for (var i = 0; i < rows.length; i++) ...[
            rows[i],
            if (i != rows.length - 1) Divider(height: 1, indent: s(12), endIndent: s(12), color: const Color(0xFF31537B)),
          ],
        ],
      ),
    );
  }
}

class _MenuRow extends StatelessWidget {
  const _MenuRow({required this.icon, required this.title, required this.onTap, this.trailingText, this.badge = 0, this.danger = false});
  final IconData icon;
  final String title;
  final VoidCallback onTap;
  final String? trailingText;
  final int badge;
  final bool danger;

  @override
  Widget build(BuildContext context) {
    final s = menuScaleOf(context).call;
    final color = danger ? const Color(0xFFFF3D35) : Colors.white;
    return InkWell(
      onTap: onTap,
      child: Padding(
        padding: EdgeInsets.symmetric(horizontal: s(14), vertical: s(11)),
        child: Row(
          children: [
            Icon(icon, color: color, size: s(22)),
            SizedBox(width: s(13)),
            Expanded(child: Text(title, style: TextStyle(color: color, fontSize: s(12.3), fontWeight: FontWeight.w500))),
            if (trailingText != null)
              Container(
                padding: EdgeInsets.symmetric(horizontal: s(9), vertical: s(5)),
                decoration: BoxDecoration(color: const Color(0xFF073365), borderRadius: BorderRadius.circular(s(9)), border: Border.all(color: const Color(0xFF156AC0))),
                child: Text(trailingText!, style: TextStyle(color: menuBlue, fontSize: s(9.5))),
              ),
            if (badge > 0)
              Container(
                width: s(22), height: s(22), margin: EdgeInsets.only(right: s(6)), alignment: Alignment.center,
                decoration: const BoxDecoration(color: OvanieColors.orange, shape: BoxShape.circle),
                child: Text('$badge', style: TextStyle(color: Colors.white, fontSize: s(9), fontWeight: FontWeight.w800)),
              ),
            SizedBox(width: s(5)),
            Icon(Icons.chevron_right_rounded, color: danger ? const Color(0xFFFF3D35) : Colors.white, size: s(23)),
          ],
        ),
      ),
    );
  }
}
