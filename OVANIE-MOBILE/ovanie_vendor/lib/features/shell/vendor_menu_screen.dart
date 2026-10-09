import 'package:flutter/material.dart';

import '../../core/config/app_config.dart';
import '../../core/network/api_client.dart';
import '../after_sales/after_sales_screen.dart';
import '../auth/vendor_journey_screen.dart';
import '../auth/vendor_session.dart';
import '../menu/delivery_screens.dart';
import '../menu/menu_ui.dart';
import '../menu/profile_screens.dart';
import '../menu/reviews_screen.dart';
import '../menu/statistics_screen.dart';
import '../notifications/vendor_notifications_screen.dart';
import '../support/vendor_support_screen.dart';
import 'vendor_tabs.dart';

/// Onglet Menu : carte de la boutique, raccourcis vers les grands espaces,
/// suivi (notifications, avis, litiges, retours), paramètres et aide.
class VendorMenuScreen extends StatefulWidget {
  const VendorMenuScreen({super.key, required this.onSelectTab});
  final ValueChanged<VendorTab> onSelectTab;
  @override
  State<VendorMenuScreen> createState() => _MenuState();
}

class _MenuState extends State<VendorMenuScreen> {
  Map<String, dynamic> data = {};
  bool loading = true;
  String? error;

  /// À garder identique au numéro de version de pubspec.yaml.
  static const version = '0.1.11';

  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      data = await menuApi('menu');
    } catch (e) {
      error = ApiClient.friendlyError(e);
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  int count(String key) => number(mapOf(data['counts'])[key]).toInt();

  Future<void> open(Widget page) async {
    await Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => page));
    if (mounted) load();
  }

  Future<void> logout() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialog) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18)),
        title: const Text('Se déconnecter ?', style: TextStyle(color: menuBlue, fontSize: 19, fontWeight: FontWeight.w800)),
        content: const Text(
          'Vous devrez saisir à nouveau vos identifiants pour accéder à votre boutique.',
          style: TextStyle(color: menuMuted, fontSize: 14, height: 1.4),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(dialog, false), child: const Text('Annuler')),
          FilledButton(
            style: FilledButton.styleFrom(backgroundColor: menuRed),
            onPressed: () => Navigator.pop(dialog, true),
            child: const Text('Se déconnecter'),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;
    try {
      await VendorSession.instance.logout();
      if (mounted) {
        Navigator.of(context).pushAndRemoveUntil(
          MaterialPageRoute<void>(builder: (_) => const VendorJourneyScreen()),
          (_) => false,
        );
      }
    } catch (e) {
      if (mounted) menuError(context, e);
    }
  }

  Future<void> web(String path) async {
    try {
      await launchUrl(Uri.parse('${AppConfig.backendOrigin}$path'));
    } catch (e) {
      if (mounted) menuError(context, e);
    }
  }

  @override
  Widget build(BuildContext context) {
    final top = MediaQuery.paddingOf(context).top;
    final notifications = count('notifications');
    final hasData = data.isNotEmpty;
    return MkTheme(
      lightStatusBar: false,
      child: ColoredBox(
        color: const Color(0xFFF5F7FB),
        child: Column(
          children: [
            Container(
              color: Colors.white,
              padding: EdgeInsets.fromLTRB(16, top + 8, 6, 8),
              child: Row(
                children: [
                  Image.asset('assets/images/mockups/logo_navy.png', height: 34, semanticLabel: 'OVANIE Vendeur'),
                  const Spacer(),
                  IconButton(
                    tooltip: 'Notifications',
                    onPressed: () => open(const VendorNotificationsScreen()),
                    icon: Badge(
                      isLabelVisible: notifications > 0,
                      backgroundColor: menuOrange,
                      label: Text(notifications > 99 ? '99+' : '$notifications'),
                      child: const Icon(Icons.notifications_none_rounded, color: menuBlue, size: 28),
                    ),
                  ),
                ],
              ),
            ),
            const Divider(height: 1, color: orderBorder),
            if (loading && hasData)
              const LinearProgressIndicator(minHeight: 2, color: Mk.orange, backgroundColor: Color(0xFFFFEEE5)),
            Expanded(
              child: RefreshIndicator(
                color: Mk.orange,
                onRefresh: load,
                child: ListView(
                  physics: const AlwaysScrollableScrollPhysics(),
                  padding: const EdgeInsets.fromLTRB(12, 14, 12, 18),
                  children: [
                    const Padding(
                      padding: EdgeInsets.symmetric(horizontal: 4),
                      child: Text(
                        'Menu',
                        style: TextStyle(color: menuBlue, fontSize: 24, fontWeight: FontWeight.w800, letterSpacing: -.4),
                      ),
                    ),
                    const Padding(
                      padding: EdgeInsets.fromLTRB(4, 2, 4, 12),
                      child: Text(
                        'Tout pour gérer votre boutique',
                        style: TextStyle(color: menuMuted, fontSize: 13.5, fontWeight: FontWeight.w500),
                      ),
                    ),
                    if (error != null) ...[
                      MkNotice(error!),
                      const SizedBox(height: 10),
                      if (!hasData) ...[MkBarButton(label: 'Réessayer', filled: true, onTap: load), const SizedBox(height: 10)],
                    ],
                    if (loading && !hasData)
                      const Padding(
                        padding: EdgeInsets.symmetric(vertical: 40),
                        child: Center(child: CircularProgressIndicator(color: Mk.orange)),
                      )
                    else if (hasData)
                      _shopCard(),
                    const SizedBox(height: 10),
                    _shortcuts(),
                    const SizedBox(height: 10),
                    _group([
                      _tile('Notifications', 'Vos alertes et actualités', Icons.notifications_none_rounded, menuAzure,
                          () => open(const VendorNotificationsScreen()), badge: notifications),
                      _tile('Avis clients', 'Consultez les avis sur votre boutique', Icons.star_border_rounded,
                          const Color(0xFFF59E0B), () => open(const ReviewsScreen())),
                      _tile('Litiges', 'Gérez les réclamations et litiges', Icons.gavel_rounded, menuRed,
                          () => open(const AfterSalesScreen(disputes: true)), badge: count('disputes')),
                      _tile('Retours', 'Suivez les retours produits', Icons.inventory_2_outlined, menuPurple,
                          () => open(const AfterSalesScreen()), badge: count('returns')),
                    ]),
                    _settingsCard(),
                    _group([
                      _tile('Mon profil', 'Vos informations personnelles', Icons.person_outline_rounded, menuAzure,
                          () => open(const PersonalProfileScreen())),
                      _tile('Sécurité du compte', 'Mot de passe, authentification, sécurité', Icons.shield_outlined,
                          menuGreen, () => web('/profile'), external: true),
                    ]),
                    _group([
                      _tile('Centre d’aide', 'FAQ et support', Icons.help_outline_rounded, menuAzure,
                          () => open(const VendorSupportScreen())),
                      _tile('Conditions d’utilisation', 'Nos règles et engagements', Icons.description_outlined, Mk.icon,
                          () => web('/conditions-generales'), external: true),
                      _tile('À propos d’OVANIE', 'Notre mission, notre vision', Icons.info_outline_rounded, menuOrange,
                          () => web('/qui-sommes-nous'), external: true),
                    ]),
                    Material(
                      color: const Color(0xFFFDECEC),
                      borderRadius: BorderRadius.circular(14),
                      clipBehavior: Clip.antiAlias,
                      child: InkWell(
                        onTap: logout,
                        child: const Padding(
                          padding: EdgeInsets.symmetric(vertical: 14),
                          child: Row(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              Icon(Icons.logout_rounded, color: menuRed, size: 22),
                              SizedBox(width: 10),
                              Text('Se déconnecter', style: TextStyle(color: menuRed, fontSize: 15, fontWeight: FontWeight.w700)),
                            ],
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(height: 10),
                    const Center(
                      child: Text('Version $version', style: TextStyle(color: menuMuted, fontSize: 12, fontWeight: FontWeight.w500)),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  // ---- Carte boutique ----------------------------------------------------------
  Widget _shopCard() {
    final shop = mapOf(data['shop']);
    final user = mapOf(data['user']);
    final cover = screenText(mapOf(shop['presentation'])['cover_url'], '');
    final owner = screenText(shop['owner_name'] ?? user['name'], '');
    return Semantics(
      button: true,
      label: 'Ouvrir le profil de la boutique',
      child: Material(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: () => open(const ShopProfileScreen()),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Stack(
                clipBehavior: Clip.none,
                children: [
                  AspectRatio(aspectRatio: 776 / 185, child: ShopCoverImage(url: cover)),
                  Positioned(
                    left: 14,
                    bottom: -26,
                    child: Container(
                      padding: const EdgeInsets.all(3),
                      decoration: const BoxDecoration(color: Colors.white, shape: BoxShape.circle),
                      child: menuImage(shop['logo_url'], circle: true, size: 56),
                    ),
                  ),
                ],
              ),
              Padding(
                padding: const EdgeInsets.fromLTRB(14, 32, 8, 14),
                child: Row(
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            screenText(shop['name'], 'Ma boutique'),
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(color: menuBlue, fontSize: 19, height: 1.2, fontWeight: FontWeight.w800),
                          ),
                          const SizedBox(height: 4),
                          Wrap(
                            spacing: 10,
                            runSpacing: 6,
                            crossAxisAlignment: WrapCrossAlignment.center,
                            children: [
                              if (owner.isNotEmpty)
                                Text(owner, style: const TextStyle(color: menuMuted, fontSize: 13.5, fontWeight: FontWeight.w500)),
                              if (menuShopVerified(shop)) menuVerifiedPill(),
                            ],
                          ),
                        ],
                      ),
                    ),
                    const Icon(Icons.chevron_right_rounded, color: Mk.icon, size: 26),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  // ---- Quatre raccourcis ---------------------------------------------------------
  Widget _shortcuts() {
    final cells = <Widget>[
      _shortcut('Produits', 'Gérez votre catalogue', Icons.inventory_2_outlined, menuAzure,
          () => widget.onSelectTab(VendorTab.products)),
      _shortcut('Commandes', 'Suivez et traitez vos commandes', Icons.receipt_long_outlined, menuOrange,
          () => widget.onSelectTab(VendorTab.orders), badge: count('orders')),
      _shortcut('Finances', 'Solde, ventes et versements', Icons.account_balance_wallet_outlined, menuOrange,
          () => widget.onSelectTab(VendorTab.finance)),
      _shortcut('Statistiques', 'Analysez vos performances', Icons.bar_chart_rounded, menuGreen,
          () => open(const StatisticsScreen())),
    ];
    return LayoutBuilder(
      builder: (context, box) {
        final width = (box.maxWidth - 8) / 2;
        return Wrap(
          spacing: 8,
          runSpacing: 8,
          children: [for (final cell in cells) SizedBox(width: width, child: cell)],
        );
      },
    );
  }

  Widget _shortcut(String title, String subtitle, IconData icon, Color color, VoidCallback onTap, {int badge = 0}) {
    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(14),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Stack(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(10, 12, 10, 12),
              child: Row(
                children: [
                  menuIcon(icon, color: color, size: 42),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(title, style: const TextStyle(color: menuBlue, fontSize: 14.5, fontWeight: FontWeight.w800)),
                        const SizedBox(height: 2),
                        Text(
                          subtitle,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(color: menuMuted, fontSize: 12, height: 1.3, fontWeight: FontWeight.w500),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            if (badge > 0) Positioned(right: 8, top: 8, child: _badge(badge)),
          ],
        ),
      ),
    );
  }

  Widget _badge(int value) => Container(
        constraints: const BoxConstraints(minWidth: 22, minHeight: 22),
        padding: const EdgeInsets.symmetric(horizontal: 6),
        alignment: Alignment.center,
        decoration: BoxDecoration(color: menuOrange, borderRadius: BorderRadius.circular(99)),
        child: Text(
          value > 99 ? '99+' : '$value',
          style: const TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w800),
        ),
      );

  // ---- Groupes de lignes -----------------------------------------------------------
  Widget _group(List<Widget> rows) => Container(
        margin: const EdgeInsets.only(bottom: 10),
        clipBehavior: Clip.antiAlias,
        decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(14)),
        child: Column(
          children: [
            for (var i = 0; i < rows.length; i++) ...[
              if (i > 0) const Divider(height: 1, indent: 66, endIndent: 14, color: orderBorder),
              rows[i],
            ],
          ],
        ),
      );

  Widget _tile(String title, String subtitle, IconData icon, Color color, VoidCallback onTap,
      {int badge = 0, bool external = false}) {
    return Material(
      color: Colors.white,
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(12, 10, 8, 10),
          child: Row(
            children: [
              Container(
                width: 42,
                height: 42,
                decoration: BoxDecoration(color: color.withValues(alpha: .12), borderRadius: BorderRadius.circular(11)),
                child: Icon(icon, color: color, size: 23),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(title, style: const TextStyle(color: menuBlue, fontSize: 14.5, fontWeight: FontWeight.w700)),
                    const SizedBox(height: 1),
                    Text(
                      subtitle,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(color: menuMuted, fontSize: 12.5, height: 1.3, fontWeight: FontWeight.w500),
                    ),
                  ],
                ),
              ),
              if (badge > 0) ...[const SizedBox(width: 6), _badge(badge)],
              if (external) ...[
                const SizedBox(width: 6),
                const Icon(Icons.open_in_new_rounded, color: Mk.icon, size: 19, semanticLabel: 'S’ouvre dans le navigateur'),
              ],
              const SizedBox(width: 2),
              const Icon(Icons.chevron_right_rounded, color: Mk.icon, size: 24),
            ],
          ),
        ),
      ),
    );
  }

  Widget _settingsCard() => Padding(
        padding: const EdgeInsets.only(bottom: 10),
        child: Material(
          color: Colors.white,
          borderRadius: BorderRadius.circular(14),
          clipBehavior: Clip.antiAlias,
          child: InkWell(
            onTap: () => open(const DeliverySettingsScreen()),
            child: Padding(
              padding: const EdgeInsets.fromLTRB(12, 12, 8, 12),
              child: Row(
                children: [
                  Container(
                    width: 42,
                    height: 42,
                    decoration: BoxDecoration(color: menuAzure.withValues(alpha: .12), borderRadius: BorderRadius.circular(11)),
                    child: const Icon(Icons.storefront_outlined, color: menuAzure, size: 23),
                  ),
                  const SizedBox(width: 12),
                  const Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Paramètres Boutique & livraison',
                          style: TextStyle(color: menuBlue, fontSize: 14.5, height: 1.2, fontWeight: FontWeight.w700),
                        ),
                        SizedBox(height: 2),
                        Text(
                          'Mode logistique, délais et zones',
                          style: TextStyle(color: menuMuted, fontSize: 12.5, height: 1.3, fontWeight: FontWeight.w500),
                        ),
                      ],
                    ),
                  ),
                  if (MediaQuery.sizeOf(context).width >= 380)
                    Padding(
                      padding: const EdgeInsets.only(left: 6),
                      child: Image.asset('assets/images/mockups/menu_settings.jpg', width: 92, excludeFromSemantics: true),
                    ),
                  const Icon(Icons.chevron_right_rounded, color: Mk.icon, size: 24),
                ],
              ),
            ),
          ),
        ),
      );
}
