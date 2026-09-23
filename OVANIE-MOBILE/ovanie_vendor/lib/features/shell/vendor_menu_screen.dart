import 'package:flutter/material.dart';
import '../../core/config/app_config.dart';
import '../../core/network/api_client.dart';
import '../auth/vendor_session.dart';
import '../auth/vendor_journey_screen.dart';
import '../menu/menu_ui.dart';
import '../menu/statistics_screen.dart';
import '../menu/reviews_screen.dart';
import '../menu/profile_screens.dart';
import '../after_sales/after_sales_screen.dart';
import '../notifications/vendor_notifications_screen.dart';
import '../support/vendor_support_screen.dart';
import 'vendor_tabs.dart';

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
  static const version = '1.0.0';
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

  Future<void> logout() async {
    try {
      await VendorSession.instance.logout();
      if (mounted) {
        Navigator.of(context).pushAndRemoveUntil(
          MaterialPageRoute(builder: (_) => const VendorJourneyScreen()),
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

  Widget tile(
    String title,
    String subtitle,
    IconData icon,
    VoidCallback tap, {
    String? count,
    Color color = menuBlue,
  }) {
    final badge = number(mapOf(data['counts'])[count]).toInt();
    return InkWell(
      onTap: tap,
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 4),
        child: Row(
          children: [
            menuIcon(icon, color: color),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: const TextStyle(color: menuBlue, fontWeight: FontWeight.w800, fontSize: 15),
                  ),
                  Text(subtitle, style: const TextStyle(color: menuMuted, fontSize: 12)),
                ],
              ),
            ),
            if (count != null && badge > 0)
              Padding(
                padding: const EdgeInsets.only(left: 6),
                child: Container(
                  constraints: const BoxConstraints(minWidth: 24, minHeight: 24),
                  padding: const EdgeInsets.symmetric(horizontal: 6),
                  alignment: Alignment.center,
                  decoration: const BoxDecoration(color: Color(0xFFE0231F), shape: BoxShape.circle),
                  child: Text('$badge', style: const TextStyle(color: Colors.white, fontSize: 11.5, fontWeight: FontWeight.w800)),
                ),
              ),
            const SizedBox(width: 4),
            const Icon(Icons.chevron_right, color: menuBlue),
          ],
        ),
      ),
    );
  }

  Widget group(List<Widget> list) => Container(
    margin: const EdgeInsets.only(bottom: 14),
    padding: const EdgeInsets.symmetric(horizontal: 10),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(14),
      border: Border.all(color: orderBorder),
    ),
    child: Column(
      children: [
        for (var i = 0; i < list.length; i++) ...[
          if (i > 0) const Divider(height: 1, indent: 60, color: Color(0xFFE9EFF8)),
          list[i],
        ],
      ],
    ),
  );

  @override
  Widget build(BuildContext c) => MenuPage(
    title: 'Menu',
    subtitle: 'Tout pour gérer votre boutique',
    root: true,
    loading: loading,
    error: error,
    refresh: load,
    children: [
      ShopIdentity(
        shop: mapOf(data['shop']),
        user: mapOf(data['user']),
        onTap: () => openMenuPage(c, const ShopProfileScreen()),
      ),
      const SizedBox(height: 14),
      group([
        tile(
          'Produits',
          'Gérez votre catalogue de produits',
          Icons.inventory_2_outlined,
          () => widget.onSelectTab(VendorTab.products),
          color: menuOrange,
        ),
        tile(
          'Commandes',
          'Suivez et traitez vos commandes',
          Icons.assignment_outlined,
          () => widget.onSelectTab(VendorTab.orders),
          count: 'orders',
        ),
        tile(
          'Finances',
          'Solde, ventes, commissions et versements',
          Icons.account_balance_wallet_outlined,
          () => widget.onSelectTab(VendorTab.finance),
          color: menuOrange,
        ),
        tile(
          'Statistiques',
          'Analysez vos performances',
          Icons.bar_chart,
          () => openMenuPage(c, const StatisticsScreen()),
        ),
      ]),
      group([
        tile(
          'Notifications',
          'Vos alertes et actualités',
          Icons.notifications_none,
          () => openMenuPage(c, const VendorNotificationsScreen()),
          count: 'notifications',
          color: menuOrange,
        ),
        tile(
          'Avis clients',
          'Consultez les avis sur votre boutique',
          Icons.star_border,
          () => openMenuPage(c, const ReviewsScreen()),
          color: menuOrange,
        ),
        tile(
          'Litiges',
          'Gérez les réclamations et litiges',
          Icons.gavel,
          () => openMenuPage(c, const AfterSalesScreen(disputes: true)),
          count: 'disputes',
        ),
        tile(
          'Retours',
          'Suivez les retours produits',
          Icons.inventory_2_outlined,
          () => openMenuPage(c, const AfterSalesScreen()),
          count: 'returns',
          color: menuOrange,
        ),
      ]),
      group([
        tile(
          'Paramètres Boutique & livraison',
          'Informations, livraison et préférences',
          Icons.storefront_outlined,
          () => openMenuPage(c, const ShopProfileScreen()),
        ),
      ]),
      group([
        tile(
          'Mon profil',
          'Vos informations personnelles',
          Icons.person_outline,
          () => openMenuPage(c, const PersonalProfileScreen()),
          color: menuOrange,
        ),
        tile(
          'Sécurité du compte',
          'Mot de passe, authentification, sécurité',
          Icons.shield_outlined,
          () => web('/profile'),
        ),
      ]),
      group([
        tile(
          'Centre d’aide',
          'FAQ et support',
          Icons.help_outline,
          () => openMenuPage(c, const VendorSupportScreen()),
          color: menuOrange,
        ),
        tile(
          'Conditions d’utilisation',
          'Nos règles et engagements',
          Icons.description_outlined,
          () => web('/conditions-generales'),
        ),
        tile(
          'À propos d’OVANIE',
          'Notre mission, notre vision',
          Icons.info_outline,
          () => web('/qui-sommes-nous'),
          color: menuOrange,
        ),
      ]),
      Container(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
        decoration: BoxDecoration(color: const Color(0xFFFFF0F0), borderRadius: BorderRadius.circular(14)),
        child: Row(
          children: [
            Expanded(
              child: TextButton.icon(
                onPressed: logout,
                style: TextButton.styleFrom(padding: EdgeInsets.zero, alignment: Alignment.centerLeft),
                icon: const Icon(Icons.logout, color: Color(0xFFD92D20)),
                label: const Text('Se déconnecter', style: TextStyle(color: Color(0xFFD92D20), fontWeight: FontWeight.w700)),
              ),
            ),
            Text('Version $version', style: const TextStyle(color: menuMuted, fontSize: 11)),
          ],
        ),
      ),
    ],
  );
}
