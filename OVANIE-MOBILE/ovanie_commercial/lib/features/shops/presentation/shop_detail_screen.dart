import 'package:flutter/material.dart';

import '../../../core/navigation/commercial_tab_bus.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/theme/ovanie_colors.dart';
import '../../clients/data/clients_service.dart';
import '../../clients/presentation/client_chrome.dart';
import '../../clients/presentation/clients_screen.dart';
import '../data/shops_service.dart';
import '../models/shop_data.dart';
import 'shop_chrome.dart';

class CommercialShopDetailScreen extends StatefulWidget {
  const CommercialShopDetailScreen({
    super.key,
    required this.shopId,
    required this.initialShop,
    required this.shopsService,
    required this.clientsService,
    required this.initialUser,
    required this.unreadNotifications,
    required this.onLogout,
  });

  final int shopId;
  final CommercialShop initialShop;
  final ShopsService shopsService;
  final ClientsService clientsService;
  final Map<String, dynamic> initialUser;
  final int unreadNotifications;
  final Future<void> Function() onLogout;

  @override
  State<CommercialShopDetailScreen> createState() => _CommercialShopDetailScreenState();
}

class _CommercialShopDetailScreenState extends State<CommercialShopDetailScreen> {
  CommercialShopDetail? _detail;
  bool _loading = true;
  String? _error;
  bool _following = false;
  int _tab = 0;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final detail = await widget.shopsService.detail(widget.shopId);
      if (!mounted) return;
      setState(() => _detail = detail);
    } on ApiException catch (error) {
      if (!mounted) return;
      setState(() => _error = error.message);
    } catch (_) {
      if (!mounted) return;
      setState(() => _error = 'Impossible de charger la boutique.');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _future(String title) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text('$title : fonctionnalité prévue dans un prochain lot.'), behavior: SnackBarBehavior.floating),
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
    final shop = _detail?.shop ?? widget.initialShop;

    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFD),
      body: RefreshIndicator(
        onRefresh: _load,
        color: OvanieColors.blue,
        child: CustomScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          slivers: [
            SliverToBoxAdapter(
              child: _Hero(
                shop: shop,
                scale: scale,
                onBack: () => Navigator.of(context).pop(),
                onShare: () => _future('Partager la boutique'),
                onFavorite: () => setState(() => _following = !_following),
                following: _following,
                onMore: () => _future('Actions boutique'),
              ),
            ),
            SliverToBoxAdapter(
              child: Container(
                color: Colors.white,
                padding: EdgeInsets.fromLTRB(s(16), s(12), s(16), s(13)),
                child: _Identity(
                  shop: shop,
                  detail: _detail,
                  following: _following,
                  scale: scale,
                  onFollow: () => setState(() => _following = !_following),
                ),
              ),
            ),
            SliverToBoxAdapter(
              child: Container(
                color: Colors.white,
                padding: EdgeInsets.fromLTRB(s(16), 0, s(16), s(11)),
                child: _TrustRow(scale: scale),
              ),
            ),
            SliverToBoxAdapter(
              child: Container(
                color: Colors.white,
                child: _Tabs(
                  scale: scale,
                  selected: _tab,
                  productCount: shop.productCount,
                  reviewsCount: _detail?.reviewsCount ?? 0,
                  onChanged: (value) => setState(() => _tab = value),
                ),
              ),
            ),
            if (_loading && _detail == null)
              SliverToBoxAdapter(
                child: Padding(
                  padding: EdgeInsets.all(s(36)),
                  child: const Center(child: CircularProgressIndicator()),
                ),
              )
            else if (_error != null)
              SliverToBoxAdapter(
                child: Padding(
                  padding: EdgeInsets.all(s(16)),
                  child: _Error(message: _error!, scale: scale, onRetry: _load),
                ),
              )
            else
              SliverPadding(
                padding: EdgeInsets.fromLTRB(s(10), s(10), s(10), s(100)),
                sliver: SliverList(
                  delegate: SliverChildListDelegate.fixed(_tabContent(scale, shop)),
                ),
              ),
          ],
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

  List<Widget> _tabContent(double scale, CommercialShop shop) {
    double s(double value) => value * scale;
    final detail = _detail;
    if (_tab == 1) {
      return [
        _SectionTitle(title: 'Tous les produits', action: '${shop.productCount} produits', scale: scale),
        SizedBox(height: s(8)),
        _ProductsGrid(products: detail?.products ?? const [], scale: scale),
      ];
    }
    if (_tab == 2) {
      return [
        _AboutCard(detail: detail, scale: scale),
      ];
    }
    if (_tab == 3) {
      return [
        _ReviewsCard(detail: detail, scale: scale),
      ];
    }

    return [
      _PromoBanner(shop: shop, scale: scale),
      SizedBox(height: s(12)),
      _SectionTitle(title: 'Catégories proposées', action: 'Voir tout', scale: scale),
      SizedBox(height: s(8)),
      _CategoriesRow(categories: detail?.categories ?? const [], scale: scale),
      SizedBox(height: s(13)),
      _SectionTitle(title: 'Produits en vedette', action: 'Voir tout', scale: scale),
      SizedBox(height: s(8)),
      _ProductsGrid(products: detail?.products ?? const [], scale: scale),
    ];
  }
}

class _Hero extends StatelessWidget {
  const _Hero({
    required this.shop,
    required this.scale,
    required this.onBack,
    required this.onShare,
    required this.onFavorite,
    required this.following,
    required this.onMore,
  });
  final CommercialShop shop;
  final double scale;
  final VoidCallback onBack;
  final VoidCallback onShare;
  final VoidCallback onFavorite;
  final bool following;
  final VoidCallback onMore;

  @override
  Widget build(BuildContext context) {
    double s(double value) => value * scale;
    return SizedBox(
      height: s(205),
      child: Stack(
        fit: StackFit.expand,
        children: [
          if (shop.heroUrl != null)
            Image.network(
              shop.heroUrl!,
              fit: BoxFit.cover,
              errorBuilder: (_, __, ___) => const _HeroFallback(),
            )
          else
            const _HeroFallback(),
          const DecoratedBox(
            decoration: BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topCenter,
                end: Alignment.bottomCenter,
                colors: [Color(0x44000000), Color(0x10000000), Color(0x77000000)],
              ),
            ),
          ),
          Positioned(
            left: s(16),
            top: s(28),
            child: _RoundIcon(icon: Icons.arrow_back_rounded, onTap: onBack, scale: scale),
          ),
          Positioned(
            right: s(16),
            top: s(28),
            child: Row(
              children: [
                _RoundIcon(icon: Icons.share_outlined, onTap: onShare, scale: scale),
                SizedBox(width: s(9)),
                _RoundIcon(icon: following ? Icons.favorite : Icons.favorite_border, onTap: onFavorite, scale: scale),
                SizedBox(width: s(9)),
                _RoundIcon(icon: Icons.more_vert_rounded, onTap: onMore, scale: scale),
              ],
            ),
          ),
          Positioned(
            left: s(22),
            right: s(22),
            bottom: s(16),
            child: Text(
              shop.displayName.toUpperCase(),
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(color: Colors.white, fontSize: s(24), fontWeight: FontWeight.w900, letterSpacing: 1.2, shadows: const [Shadow(color: Colors.black54, blurRadius: 8)]),
            ),
          ),
        ],
      ),
    );
  }
}

class _HeroFallback extends StatelessWidget {
  const _HeroFallback();
  @override
  Widget build(BuildContext context) => const DecoratedBox(
        decoration: BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [Color(0xFF06285B), Color(0xFF0B5AB7), Color(0xFF081D42)],
          ),
        ),
        child: Center(child: Icon(Icons.storefront_rounded, color: Colors.white54, size: 86)),
      );
}

class _RoundIcon extends StatelessWidget {
  const _RoundIcon({required this.icon, required this.onTap, required this.scale});
  final IconData icon;
  final VoidCallback onTap;
  final double scale;
  @override
  Widget build(BuildContext context) {
    final size = 43 * scale;
    return Material(
      color: const Color(0xA3122847),
      shape: const CircleBorder(),
      child: InkWell(
        onTap: onTap,
        customBorder: const CircleBorder(),
        child: SizedBox(width: size, height: size, child: Icon(icon, color: Colors.white, size: 23 * scale)),
      ),
    );
  }
}

class _Identity extends StatelessWidget {
  const _Identity({required this.shop, required this.detail, required this.following, required this.scale, required this.onFollow});
  final CommercialShop shop;
  final CommercialShopDetail? detail;
  final bool following;
  final double scale;
  final VoidCallback onFollow;

  @override
  Widget build(BuildContext context) {
    double s(double value) => value * scale;
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          width: s(61),
          height: s(61),
          padding: EdgeInsets.all(s(4)),
          decoration: const BoxDecoration(color: Color(0xFF0A4B9D), shape: BoxShape.circle),
          child: ClipOval(
            child: shop.logoUrl != null
                ? Image.network(shop.logoUrl!, fit: BoxFit.cover, errorBuilder: (_, __, ___) => const Icon(Icons.storefront, color: Colors.white))
                : const Icon(Icons.storefront, color: Colors.white),
          ),
        ),
        SizedBox(width: s(10)),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Flexible(
                    child: Text(shop.displayName, maxLines: 1, overflow: TextOverflow.ellipsis, style: TextStyle(color: const Color(0xFF071735), fontSize: s(15), fontWeight: FontWeight.w800)),
                  ),
                  SizedBox(width: s(8)),
                  ShopStatusBadge(status: shop.status, label: shop.statusLabel, scale: scale),
                ],
              ),
              SizedBox(height: s(2)),
              Text(shop.category.isEmpty ? 'Boutique OVANIE' : shop.category, style: TextStyle(color: const Color(0xFF35598C), fontSize: s(10))),
              SizedBox(height: s(2)),
              Row(children: [Icon(Icons.location_on_outlined, size: s(14), color: const Color(0xFF285C9F)), SizedBox(width: s(3)), Expanded(child: Text(shop.locationLabel, style: TextStyle(color: const Color(0xFF48658F), fontSize: s(9.5))))]),
              SizedBox(height: s(3)),
              Row(
                children: [
                  Icon(Icons.star_rounded, color: const Color(0xFFFFB200), size: s(17)),
                  SizedBox(width: s(3)),
                  Text('${(detail?.rating ?? 0).toStringAsFixed(1)}', style: TextStyle(color: const Color(0xFF0A1C40), fontSize: s(10.6), fontWeight: FontWeight.w800)),
                  SizedBox(width: s(3)),
                  Text('(${detail?.reviewsCount ?? 0} avis)', style: TextStyle(color: const Color(0xFF47638C), fontSize: s(9.2))),
                ],
              ),
            ],
          ),
        ),
        SizedBox(width: s(8)),
        Column(
          children: [
            FilledButton.icon(
              onPressed: onFollow,
              style: FilledButton.styleFrom(
                backgroundColor: OvanieColors.orange,
                foregroundColor: Colors.white,
                padding: EdgeInsets.symmetric(horizontal: s(14), vertical: s(10)),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(s(10))),
              ),
              icon: Icon(following ? Icons.favorite : Icons.favorite_border, size: s(17)),
              label: Text(following ? 'Suivi' : 'Suivre', style: TextStyle(fontSize: s(10.4), fontWeight: FontWeight.w700)),
            ),
            SizedBox(height: s(4)),
            Text('${detail?.followersCount ?? 0} abonnés', style: TextStyle(color: const Color(0xFF506B93), fontSize: s(8.6))),
          ],
        ),
      ],
    );
  }
}

class _TrustRow extends StatelessWidget {
  const _TrustRow({required this.scale});
  final double scale;
  @override
  Widget build(BuildContext context) {
    double s(double value) => value * scale;
    final items = [
      (Icons.local_shipping_outlined, 'Livraison avec OVANIE', 'Partout en Côte d’Ivoire'),
      (Icons.verified_user_outlined, 'Produits de qualité', 'Contrôlés et suivis'),
      (Icons.support_agent_rounded, 'Service client', 'Réactif et à l’écoute'),
    ];
    return Container(
      padding: EdgeInsets.symmetric(vertical: s(10)),
      decoration: const BoxDecoration(border: Border(top: BorderSide(color: Color(0xFFE4E9F1)), bottom: BorderSide(color: Color(0xFFE4E9F1)))),
      child: Row(
        children: [
          for (var i = 0; i < items.length; i++) ...[
            Expanded(
              child: Row(
                children: [
                  Icon(items[i].$1, color: const Color(0xFF116AE9), size: s(23)),
                  SizedBox(width: s(6)),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(items[i].$2, maxLines: 1, overflow: TextOverflow.ellipsis, style: TextStyle(color: const Color(0xFF102043), fontSize: s(8.7), fontWeight: FontWeight.w700)),
                        Text(items[i].$3, maxLines: 1, overflow: TextOverflow.ellipsis, style: TextStyle(color: const Color(0xFF526D94), fontSize: s(7.7))),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            if (i < items.length - 1) Container(width: 1, height: s(35), margin: EdgeInsets.symmetric(horizontal: s(7)), color: const Color(0xFFE2E8F0)),
          ],
        ],
      ),
    );
  }
}

class _Tabs extends StatelessWidget {
  const _Tabs({required this.scale, required this.selected, required this.productCount, required this.reviewsCount, required this.onChanged});
  final double scale;
  final int selected;
  final int productCount;
  final int reviewsCount;
  final ValueChanged<int> onChanged;
  @override
  Widget build(BuildContext context) {
    double s(double value) => value * scale;
    final labels = ['Accueil', 'Produits ($productCount)', 'À propos', 'Avis ($reviewsCount)'];
    return Row(
      children: List.generate(labels.length, (index) {
        final active = selected == index;
        return Expanded(
          child: InkWell(
            onTap: () => onChanged(index),
            child: Container(
              padding: EdgeInsets.symmetric(vertical: s(11)),
              decoration: BoxDecoration(border: Border(bottom: BorderSide(color: active ? const Color(0xFF1374FF) : Colors.transparent, width: 2))),
              alignment: Alignment.center,
              child: Text(labels[index], style: TextStyle(color: active ? const Color(0xFF096AF1) : const Color(0xFF284B7D), fontSize: s(10.5), fontWeight: active ? FontWeight.w700 : FontWeight.w500)),
            ),
          ),
        );
      }),
    );
  }
}

class _PromoBanner extends StatelessWidget {
  const _PromoBanner({required this.shop, required this.scale});
  final CommercialShop shop;
  final double scale;
  @override
  Widget build(BuildContext context) {
    double s(double value) => value * scale;
    return Container(
      height: s(112),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(s(8)),
        gradient: const LinearGradient(colors: [Color(0xFF032A63), Color(0xFF0750A7), Color(0xFF031C42)]),
      ),
      padding: EdgeInsets.all(s(15)),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Text(shop.displayName.toUpperCase(), style: TextStyle(color: Colors.white, fontSize: s(12), fontWeight: FontWeight.w900)),
                SizedBox(height: s(9)),
                Text('Des matériaux solides\npour des projets durables', style: TextStyle(color: Colors.white, fontSize: s(16), fontWeight: FontWeight.w800, height: 1.05)),
              ],
            ),
          ),
          Icon(Icons.construction_rounded, color: const Color(0xFFFFA30A), size: s(64)),
        ],
      ),
    );
  }
}

class _SectionTitle extends StatelessWidget {
  const _SectionTitle({required this.title, required this.action, required this.scale});
  final String title;
  final String action;
  final double scale;
  @override
  Widget build(BuildContext context) {
    double s(double value) => value * scale;
    return Row(
      children: [
        Expanded(child: Text(title, style: TextStyle(color: const Color(0xFF071735), fontSize: s(13.2), fontWeight: FontWeight.w800))),
        Text(action, style: TextStyle(color: const Color(0xFF0869EE), fontSize: s(9.8), fontWeight: FontWeight.w700)),
      ],
    );
  }
}

class _CategoriesRow extends StatelessWidget {
  const _CategoriesRow({required this.categories, required this.scale});
  final List<ShopPublicCategory> categories;
  final double scale;
  @override
  Widget build(BuildContext context) {
    double s(double value) => value * scale;
    if (categories.isEmpty) {
      return _EmptyBox(text: 'Aucune catégorie de produit disponible.', scale: scale);
    }
    return SizedBox(
      height: s(94),
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        itemCount: categories.length,
        separatorBuilder: (_, __) => SizedBox(width: s(7)),
        itemBuilder: (context, index) {
          final category = categories[index];
          return Container(
            width: s(83),
            padding: EdgeInsets.all(s(6)),
            decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(s(8)), border: Border.all(color: const Color(0xFFDCE4EF))),
            child: Column(
              children: [
                Expanded(
                  child: ClipRRect(
                    borderRadius: BorderRadius.circular(s(6)),
                    child: Container(
                      width: double.infinity,
                      color: const Color(0xFFF0F4F9),
                      child: category.imageUrl != null
                          ? Image.network(category.imageUrl!, fit: BoxFit.cover, errorBuilder: (_, __, ___) => const Icon(Icons.category_outlined, color: Color(0xFF7994B8)))
                          : const Icon(Icons.category_outlined, color: Color(0xFF7994B8)),
                    ),
                  ),
                ),
                SizedBox(height: s(4)),
                Text(category.name, maxLines: 1, overflow: TextOverflow.ellipsis, style: TextStyle(color: const Color(0xFF0B1B3E), fontSize: s(8.2), fontWeight: FontWeight.w700)),
                Text('${category.productCount} produits', style: TextStyle(color: const Color(0xFF587095), fontSize: s(7.1))),
              ],
            ),
          );
        },
      ),
    );
  }
}

class _ProductsGrid extends StatelessWidget {
  const _ProductsGrid({required this.products, required this.scale});
  final List<ShopPublicProduct> products;
  final double scale;

  String money(double value) {
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
    if (products.isEmpty) return _EmptyBox(text: 'Aucun produit publié pour cette boutique.', scale: scale);
    final visible = products.take(8).toList();
    return GridView.builder(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 2,
        childAspectRatio: 1.15,
        crossAxisSpacing: s(8),
        mainAxisSpacing: s(8),
      ),
      itemCount: visible.length,
      itemBuilder: (context, index) {
        final product = visible[index];
        return Container(
          decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(s(9)), border: Border.all(color: const Color(0xFFDDE5EF))),
          child: Stack(
            children: [
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    child: ClipRRect(
                      borderRadius: BorderRadius.vertical(top: Radius.circular(s(9))),
                      child: Container(
                        width: double.infinity,
                        color: const Color(0xFFF2F4F7),
                        child: product.imageUrl != null
                            ? Image.network(product.imageUrl!, fit: BoxFit.contain, errorBuilder: (_, __, ___) => const Icon(Icons.inventory_2_outlined, color: Color(0xFF7890AF)))
                            : const Icon(Icons.inventory_2_outlined, color: Color(0xFF7890AF)),
                      ),
                    ),
                  ),
                  Padding(
                    padding: EdgeInsets.fromLTRB(s(8), s(5), s(8), s(7)),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(product.name, maxLines: 1, overflow: TextOverflow.ellipsis, style: TextStyle(color: const Color(0xFF091A3D), fontSize: s(8.6))),
                        SizedBox(height: s(3)),
                        Text(money(product.price), style: TextStyle(color: const Color(0xFF091A3D), fontSize: s(10.2), fontWeight: FontWeight.w800)),
                      ],
                    ),
                  ),
                ],
              ),
              if (product.discountPercent > 0)
                Positioned(
                  left: s(5),
                  top: s(5),
                  child: Container(
                    padding: EdgeInsets.symmetric(horizontal: s(5), vertical: s(3)),
                    decoration: BoxDecoration(color: const Color(0xFFFF3247), borderRadius: BorderRadius.circular(s(7))),
                    child: Text('-${product.discountPercent}%', style: TextStyle(color: Colors.white, fontSize: s(7.4), fontWeight: FontWeight.w700)),
                  ),
                ),
              Positioned(right: s(6), top: s(6), child: Icon(Icons.favorite_border, size: s(16), color: const Color(0xFF315986))),
            ],
          ),
        );
      },
    );
  }
}

class _AboutCard extends StatelessWidget {
  const _AboutCard({required this.detail, required this.scale});
  final CommercialShopDetail? detail;
  final double scale;
  @override
  Widget build(BuildContext context) {
    double s(double value) => value * scale;
    return Container(
      padding: EdgeInsets.all(s(16)),
      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(s(12)), border: Border.all(color: const Color(0xFFDDE5EF))),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('À propos de la boutique', style: TextStyle(color: const Color(0xFF071735), fontSize: s(14), fontWeight: FontWeight.w800)),
          SizedBox(height: s(9)),
          Text(detail?.description.isNotEmpty == true ? detail!.description : 'Aucune description disponible.', style: TextStyle(color: const Color(0xFF49658E), fontSize: s(10.4), height: 1.45)),
          SizedBox(height: s(13)),
          _AboutLine(icon: Icons.local_shipping_outlined, label: 'Logistique', value: detail?.logisticsLabel ?? '', scale: scale),
          _AboutLine(icon: Icons.account_balance_wallet_outlined, label: 'Reversement', value: detail?.payoutLabel ?? '', scale: scale),
          _AboutLine(icon: Icons.phone_outlined, label: 'Téléphone responsable', value: detail?.ownerPhone ?? '', scale: scale),
          _AboutLine(icon: Icons.email_outlined, label: 'E-mail responsable', value: detail?.ownerEmail ?? '', scale: scale),
        ],
      ),
    );
  }
}

class _AboutLine extends StatelessWidget {
  const _AboutLine({required this.icon, required this.label, required this.value, required this.scale});
  final IconData icon;
  final String label;
  final String value;
  final double scale;
  @override
  Widget build(BuildContext context) {
    double s(double value) => value * scale;
    return Padding(
      padding: EdgeInsets.only(bottom: s(8)),
      child: Row(
        children: [
          Icon(icon, size: s(18), color: const Color(0xFF0B6DF1)),
          SizedBox(width: s(8)),
          SizedBox(width: s(110), child: Text(label, style: TextStyle(color: const Color(0xFF1B365F), fontSize: s(9.3), fontWeight: FontWeight.w700))),
          Expanded(child: Text(value.isEmpty ? 'Non renseigné' : value, style: TextStyle(color: const Color(0xFF516B91), fontSize: s(9.3)))),
        ],
      ),
    );
  }
}

class _ReviewsCard extends StatelessWidget {
  const _ReviewsCard({required this.detail, required this.scale});
  final CommercialShopDetail? detail;
  final double scale;
  @override
  Widget build(BuildContext context) {
    double s(double value) => value * scale;
    return Container(
      padding: EdgeInsets.all(s(22)),
      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(s(12)), border: Border.all(color: const Color(0xFFDDE5EF))),
      child: Column(
        children: [
          Icon(Icons.star_rounded, color: const Color(0xFFFFB200), size: s(40)),
          SizedBox(height: s(5)),
          Text((detail?.rating ?? 0).toStringAsFixed(1), style: TextStyle(color: const Color(0xFF071735), fontSize: s(24), fontWeight: FontWeight.w900)),
          Text('${detail?.reviewsCount ?? 0} avis clients', style: TextStyle(color: const Color(0xFF5A7194), fontSize: s(10))),
          SizedBox(height: s(12)),
          Text('Les avis détaillés de la boutique utilisent les données réelles OVANIE. Leur liste complète pourra être ajoutée au lot Avis.', textAlign: TextAlign.center, style: TextStyle(color: const Color(0xFF5A7194), fontSize: s(9.4), height: 1.4)),
        ],
      ),
    );
  }
}

class _EmptyBox extends StatelessWidget {
  const _EmptyBox({required this.text, required this.scale});
  final String text;
  final double scale;
  @override
  Widget build(BuildContext context) => Container(
        padding: EdgeInsets.all(22 * scale),
        decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(10 * scale), border: Border.all(color: const Color(0xFFDDE5EF))),
        alignment: Alignment.center,
        child: Text(text, textAlign: TextAlign.center, style: TextStyle(color: const Color(0xFF5A7194), fontSize: 10 * scale)),
      );
}

class _Error extends StatelessWidget {
  const _Error({required this.message, required this.scale, required this.onRetry});
  final String message;
  final double scale;
  final VoidCallback onRetry;
  @override
  Widget build(BuildContext context) => Container(
        padding: EdgeInsets.all(14 * scale),
        decoration: BoxDecoration(color: const Color(0xFFFFECEF), borderRadius: BorderRadius.circular(10 * scale)),
        child: Row(
          children: [
            const Icon(Icons.error_outline, color: Color(0xFFC42E45)),
            SizedBox(width: 8 * scale),
            Expanded(child: Text(message, style: TextStyle(color: const Color(0xFF9D2638), fontSize: 10 * scale))),
            TextButton(onPressed: onRetry, child: const Text('Réessayer')),
          ],
        ),
      );
}
