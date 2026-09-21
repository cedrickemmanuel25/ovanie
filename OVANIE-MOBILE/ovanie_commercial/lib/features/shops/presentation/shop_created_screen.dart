import 'package:flutter/material.dart';

import '../../../core/navigation/commercial_tab_bus.dart';

import '../../../core/theme/ovanie_colors.dart';
import '../../../core/widgets/brand_background.dart';
import '../../clients/data/clients_service.dart';
import '../../clients/presentation/client_chrome.dart';
import '../../clients/presentation/clients_screen.dart';
import '../../dashboard/models/dashboard_data.dart';
import '../data/shops_service.dart';
import '../models/shop_data.dart';
import 'shop_chrome.dart';
import 'shop_detail_screen.dart';

class CommercialShopCreatedScreen extends StatefulWidget {
  const CommercialShopCreatedScreen({
    super.key,
    required this.result,
    required this.shopsService,
    required this.clientsService,
    required this.initialUser,
    required this.unreadNotifications,
    required this.onLogout,
  });

  final CreateCommercialShopResult result;
  final ShopsService shopsService;
  final ClientsService clientsService;
  final Map<String, dynamic> initialUser;
  final int unreadNotifications;
  final Future<void> Function() onLogout;

  @override
  State<CommercialShopCreatedScreen> createState() => _CommercialShopCreatedScreenState();
}

class _CommercialShopCreatedScreenState extends State<CommercialShopCreatedScreen> {
  final TextEditingController _search = TextEditingController();

  CommercialProfile get _profile => CommercialProfile.fromJson(widget.initialUser);

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  void _future(String title) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text('$title : cet écran sera relié dans le lot Capture produits.'), behavior: SnackBarBehavior.floating),
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
    final shop = widget.result.shop;

    return Scaffold(
      body: BrandBackground(
        child: SafeArea(
          bottom: false,
          child: CustomScrollView(
            slivers: [
              SliverPadding(
                padding: EdgeInsets.fromLTRB(s(15), s(9), s(15), s(100)),
                sliver: SliverList(
                  delegate: SliverChildListDelegate.fixed([
                    CommercialClientsHeader(
                      profile: _profile,
                      unreadNotifications: widget.unreadNotifications,
                      scale: scale,
                      onNotificationsTap: () => _future('Notifications'),
                      onAvatarTap: () {},
                    ),
                    SizedBox(height: s(13)),
                    CommercialShopSearch(
                      controller: _search,
                      scale: scale,
                      onChanged: (_) => setState(() {}),
                      onFilterTap: () => _future('Filtres boutiques'),
                    ),
                    SizedBox(height: s(13)),
                    Text('Boutique créée', style: TextStyle(color: Colors.white, fontSize: s(22.5), fontWeight: FontWeight.w800)),
                    SizedBox(height: s(3)),
                    Text('Le vendeur et sa boutique ont été enregistrés avec succès.', style: TextStyle(color: const Color(0xFFC8D2E2), fontSize: s(10.4))),
                    SizedBox(height: s(12)),
                    Container(
                      padding: EdgeInsets.fromLTRB(s(18), s(18), s(18), s(16)),
                      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(s(12))),
                      child: Column(
                        children: [
                          SizedBox(
                            height: s(130),
                            child: Stack(
                              alignment: Alignment.center,
                              children: [
                                Positioned(
                                  left: s(60),
                                  child: Container(
                                    width: s(76),
                                    height: s(76),
                                    decoration: const BoxDecoration(color: Color(0xFF096BF2), shape: BoxShape.circle),
                                    child: Icon(Icons.check_rounded, color: Colors.white, size: s(50)),
                                  ),
                                ),
                                Positioned(
                                  right: s(50),
                                  child: Container(
                                    width: s(118),
                                    height: s(90),
                                    decoration: BoxDecoration(color: const Color(0xFFEAF1FB), borderRadius: BorderRadius.circular(s(8)), border: Border.all(color: const Color(0xFFB4C6DE))),
                                    child: Column(
                                      mainAxisAlignment: MainAxisAlignment.center,
                                      children: [
                                        Icon(Icons.storefront_rounded, color: const Color(0xFF0B4E9B), size: s(45)),
                                        SizedBox(height: s(4)),
                                        Text(shop.displayName.toUpperCase(), maxLines: 1, overflow: TextOverflow.ellipsis, style: TextStyle(color: const Color(0xFF0B2047), fontSize: s(8.5), fontWeight: FontWeight.w900)),
                                      ],
                                    ),
                                  ),
                                ),
                              ],
                            ),
                          ),
                          Text('Création réussie', style: TextStyle(color: const Color(0xFF0A1B3D), fontSize: s(17), fontWeight: FontWeight.w900)),
                          SizedBox(height: s(4)),
                          Text('La boutique est maintenant enregistrée dans votre portefeuille commercial.', textAlign: TextAlign.center, style: TextStyle(color: const Color(0xFF35537E), fontSize: s(9.8))),
                          Divider(height: s(26), color: const Color(0xFFD9E1EC)),
                          Align(alignment: Alignment.centerLeft, child: Text('Résumé de la boutique', style: TextStyle(color: const Color(0xFF0C2047), fontSize: s(11.8), fontWeight: FontWeight.w800))),
                          SizedBox(height: s(9)),
                          _SummaryGrid(shop: shop, scale: scale),
                          SizedBox(height: s(11)),
                          _CompletedSteps(scale: scale),
                          SizedBox(height: s(11)),
                          SizedBox(
                            width: double.infinity,
                            child: FilledButton.icon(
                              onPressed: () => _nav(3),
                              style: FilledButton.styleFrom(backgroundColor: OvanieColors.orange, foregroundColor: Colors.white, padding: EdgeInsets.symmetric(vertical: s(12)), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(s(7)))),
                              icon: Icon(Icons.photo_camera_outlined, size: s(19)),
                              label: Row(mainAxisSize: MainAxisSize.min, children: [Text('Photographier les produits', style: TextStyle(fontSize: s(11.2), fontWeight: FontWeight.w600)), SizedBox(width: s(18)), Icon(Icons.arrow_forward_rounded, size: s(18))]),
                            ),
                          ),
                          SizedBox(height: s(7)),
                          SizedBox(
                            width: double.infinity,
                            child: OutlinedButton.icon(
                              onPressed: () {
                                Navigator.of(context).push(
                                  MaterialPageRoute(
                                    builder: (_) => CommercialShopDetailScreen(
                                      shopId: shop.id,
                                      initialShop: shop,
                                      shopsService: widget.shopsService,
                                      clientsService: widget.clientsService,
                                      initialUser: widget.initialUser,
                                      unreadNotifications: widget.unreadNotifications,
                                      onLogout: widget.onLogout,
                                    ),
                                  ),
                                );
                              },
                              style: OutlinedButton.styleFrom(foregroundColor: const Color(0xFF075EF0), side: const BorderSide(color: Color(0xFF075EF0)), padding: EdgeInsets.symmetric(vertical: s(11)), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(s(7)))),
                              icon: Icon(Icons.storefront_outlined, size: s(18)),
                              label: Text('Voir la boutique', style: TextStyle(fontSize: s(11))),
                            ),
                          ),
                          TextButton(onPressed: () => Navigator.of(context).pop(true), child: Text('Retour aux boutiques', style: TextStyle(color: const Color(0xFF075EF0), fontSize: s(10.4)))),
                        ],
                      ),
                    ),
                    SizedBox(height: s(8)),
                    Container(
                      padding: EdgeInsets.all(s(10)),
                      decoration: BoxDecoration(color: const Color(0xFF0A2A58), borderRadius: BorderRadius.circular(s(7)), border: Border.all(color: const Color(0xFF2C578A))),
                      child: Row(children: [Icon(Icons.info_outline, color: Colors.white, size: s(16)), SizedBox(width: s(8)), Expanded(child: Text('Vous pouvez maintenant commencer à capturer les produits pour cette boutique et les rendre visibles aux clients sur OVANIE.', style: TextStyle(color: Colors.white, fontSize: s(8.8))))]),
                    ),
                  ]),
                ),
              ),
            ],
          ),
        ),
      ),
      extendBody: true,
      bottomNavigationBar: CommercialBottomNavigation(scale: scale, currentIndex: 2, onTap: _nav),
    );
  }
}

class _SummaryGrid extends StatelessWidget {
  const _SummaryGrid({required this.shop, required this.scale});
  final CommercialShop shop;
  final double scale;
  @override
  Widget build(BuildContext context) {
    double s(double value) => value * scale;
    final items = [
      (Icons.storefront_outlined, 'Boutique', shop.name),
      (Icons.person_outline, 'Responsable', shop.ownerName),
      (Icons.sell_outlined, 'Nom affiché OVANIE', shop.displayName),
      (Icons.layers_outlined, 'Catégorie principale', shop.category),
      (Icons.local_offer_outlined, 'Sous-catégorie', shop.subcategory),
      (Icons.location_on_outlined, 'Localisation', shop.locationLabel),
    ];
    return GridView.builder(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(crossAxisCount: 2, mainAxisExtent: s(47), crossAxisSpacing: s(7), mainAxisSpacing: s(7)),
      itemCount: items.length,
      itemBuilder: (context, index) {
        final item = items[index];
        return Container(
          padding: EdgeInsets.symmetric(horizontal: s(9), vertical: s(6)),
          decoration: BoxDecoration(border: Border.all(color: const Color(0xFFD5DEEA)), borderRadius: BorderRadius.circular(s(6))),
          child: Row(children: [
            Icon(item.$1, color: const Color(0xFF075EF0), size: s(20)),
            SizedBox(width: s(7)),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisAlignment: MainAxisAlignment.center, children: [Text(item.$2, style: TextStyle(color: const Color(0xFF3B5780), fontSize: s(7.6))), Text(item.$3.isEmpty ? '—' : item.$3, maxLines: 1, overflow: TextOverflow.ellipsis, style: TextStyle(color: const Color(0xFF0B1D42), fontSize: s(9.4), fontWeight: FontWeight.w700))])),
          ]),
        );
      },
    );
  }
}

class _CompletedSteps extends StatelessWidget {
  const _CompletedSteps({required this.scale});
  final double scale;
  @override
  Widget build(BuildContext context) {
    double s(double value) => value * scale;
    const labels = ['Responsable', 'Boutique', 'Localisation &\nlogistique', 'Identité', 'Reversement', 'Récapitulatif'];
    return Container(
      padding: EdgeInsets.all(s(9)),
      decoration: BoxDecoration(color: const Color(0xFFFAFCFF), border: Border.all(color: const Color(0xFFD5DFEB)), borderRadius: BorderRadius.circular(s(7))),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text('Étapes complétées', style: TextStyle(color: const Color(0xFF0B1D42), fontSize: s(8.9), fontWeight: FontWeight.w700)),
        SizedBox(height: s(7)),
        Row(children: List.generate(labels.length, (index) => Expanded(child: Column(children: [Container(width: s(24), height: s(24), decoration: const BoxDecoration(color: Color(0xFF075EF0), shape: BoxShape.circle), child: Icon(index == labels.length - 1 ? Icons.looks_6_outlined : Icons.check_rounded, color: Colors.white, size: s(14))), SizedBox(height: s(3)), Text(labels[index], textAlign: TextAlign.center, style: TextStyle(color: const Color(0xFF173563), fontSize: s(6.8), height: 1.0))])))),
      ]),
    );
  }
}
