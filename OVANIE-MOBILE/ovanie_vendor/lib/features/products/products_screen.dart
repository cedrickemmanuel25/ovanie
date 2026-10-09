import 'package:flutter/material.dart';
import '../../core/sync/live_sync_service.dart';

import '../../core/network/api_client.dart';
import '../../core/ui/vendor_design.dart';
import '../../core/ui/vendor_mockup_ui.dart';
import '../../data/vendor_repository.dart';
import '../notifications/vendor_notifications_screen.dart';
import 'product_archive_screen.dart';
import 'product_detail_screen.dart';
import 'product_form_screen.dart';
import 'product_images_screen.dart';
import 'product_ui.dart';

class ProductsScreen extends StatefulWidget {
  const ProductsScreen({super.key});

  @override
  State<ProductsScreen> createState() => _ProductsScreenState();
}

class _ProductsScreenState extends State<ProductsScreen>
    with LiveSyncListener<ProductsScreen> {
  final TextEditingController _search = TextEditingController();
  final ScrollController _scroll = ScrollController();

  bool _loading = true;
  String? _error;
  String _status = 'active';
  String _sort = 'newest';
  List<dynamic> _items = const <dynamic>[];
  Map<String, dynamic> _stats = const <String, dynamic>{};
  int _page = 1;
  int _lastPage = 1;
  int _requestSerial = 0;
  bool _loadingMore = false;
  int _notificationCount = 0;

  static const Map<String, String> _statusLabels = <String, String>{
    'all': 'Tous',
    'active': 'Actifs',
    'draft': 'Brouillons',
    'out_of_stock': 'Rupture',
    'hidden': 'Masqués',
    'archived': 'Archivés',
  };

  static const Map<String, String> _sortLabels = <String, String>{
    'newest': 'Plus récents',
    'oldest': 'Plus anciens',
    'price_asc': 'Prix croissant',
    'price_desc': 'Prix décroissant',
    'stock_desc': 'Stock décroissant',
    'stock_asc': 'Stock croissant',
  };

  @override
  void initState() {
    super.initState();
    _scroll.addListener(_onScroll);
    _load();
    _loadNotifications();
    listenLiveSync({'shop', 'notifications'}, () {
      if (!_loadingMore) _load(clearItems: false);
      _loadNotifications();
    });
  }

  @override
  void dispose() {
    _scroll.removeListener(_onScroll);
    _scroll.dispose();
    _search.dispose();
    super.dispose();
  }

  /// Nombre de notifications non lues, pour la pastille de la cloche.
  Future<void> _loadNotifications() async {
    try {
      final notifications = await VendorRepository.instance.notifications(page: 1);
      final unread = int.tryParse('${notifications['unread_count'] ?? 0}') ?? 0;
      if (mounted) setState(() => _notificationCount = unread);
    } catch (_) {
      // La liste des produits reste utilisable sans la pastille.
    }
  }

  void _onScroll() {
    if (!_scroll.hasClients) return;
    if (_scroll.position.extentAfter < 700) {
      _loadMore();
    }
  }

  Future<void> _load({bool clearItems = true}) async {
    final requestId = ++_requestSerial;
    if (mounted) {
      setState(() {
        _loading = true;
        _loadingMore = false;
        _error = null;
        _page = 1;
        _lastPage = 1;
        if (clearItems) _items = const <dynamic>[];
      });
    }

    try {
      final first = await VendorRepository.instance.products(
        query: _search.text,
        status: _status,
        sort: _sort,
        page: 1,
      );
      if (!mounted || requestId != _requestSerial) return;

      final items = <dynamic>[...((first['data'] as List?) ?? const <dynamic>[])];
      final meta = first['meta'] is Map
          ? Map<String, dynamic>.from(first['meta'] as Map)
          : const <String, dynamic>{};
      final currentPage = int.tryParse('${meta['current_page'] ?? 1}') ?? 1;
      final lastPage = int.tryParse('${meta['last_page'] ?? 1}') ?? 1;

      setState(() {
        _items = items;
        _page = currentPage;
        _lastPage = lastPage;
        _stats = first['stats'] is Map
            ? Map<String, dynamic>.from(first['stats'] as Map)
            : const <String, dynamic>{};
        _loading = false;
      });
    } catch (e) {
      if (!mounted || requestId != _requestSerial) return;
      setState(() {
        _error = ApiClient.friendlyError(e);
        _loading = false;
      });
    }
  }

  Future<void> _loadMore() async {
    if (_loading || _loadingMore || _page >= _lastPage) return;
    final requestId = _requestSerial;
    final nextPage = _page + 1;
    setState(() => _loadingMore = true);

    try {
      final next = await VendorRepository.instance.products(
        query: _search.text,
        status: _status,
        sort: _sort,
        page: nextPage,
      );
      if (!mounted || requestId != _requestSerial) return;

      final newItems = (next['data'] as List?) ?? const <dynamic>[];
      final meta = next['meta'] is Map
          ? Map<String, dynamic>.from(next['meta'] as Map)
          : const <String, dynamic>{};
      setState(() {
        _items = <dynamic>[..._items, ...newItems];
        _page = int.tryParse('${meta['current_page'] ?? nextPage}') ?? nextPage;
        _lastPage = int.tryParse('${meta['last_page'] ?? _lastPage}') ?? _lastPage;
        _loadingMore = false;
      });
    } catch (e) {
      if (!mounted || requestId != _requestSerial) return;
      setState(() {
        _loadingMore = false;
        _error = ApiClient.friendlyError(e);
      });
    }
  }

  Future<void> _openAdd() async {
    final changed = await Navigator.push<bool>(
      context,
      MaterialPageRoute(builder: (_) => const ProductFormScreen()),
    );
    if (changed == true) _load();
  }

  Future<void> _openDetail(Map<String, dynamic> product) async {
    final changed = await Navigator.push<bool>(
      context,
      MaterialPageRoute(
        builder: (_) => ProductDetailScreen(productId: int.parse('${product['id']}'), initialProduct: product),
      ),
    );
    if (changed == true) _load();
  }

  Future<void> _openEdit(Map<String, dynamic> product) async {
    final changed = await Navigator.push<bool>(
      context,
      MaterialPageRoute(
        builder: (_) => ProductFormScreen(productId: int.parse('${product['id']}')),
      ),
    );
    if (changed == true) _load();
  }

  Future<void> _openImages(Map<String, dynamic> product) async {
    await Navigator.push<bool>(
      context,
      MaterialPageRoute(
        builder: (_) => ProductImagesScreen(productId: int.parse('${product['id']}')),
      ),
    );
    _load();
  }

  Future<void> _toggleVisibility(Map<String, dynamic> product) async {
    final id = int.parse('${product['id']}');
    final active = product['is_active'] == true || '${product['is_active']}' == '1';
    try {
      await VendorRepository.instance.toggleProduct(id, !active);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(active ? 'Produit masqué.' : 'Produit remis en ligne.')),
      );
      await _load();
    } catch (e) {
      if (mounted) setState(() => _error = ApiClient.friendlyError(e));
    }
  }

  Future<void> _openArchiveAction(Map<String, dynamic> product) async {
    final id = int.parse('${product['id']}');
    final archived = '${product['status']}'.toLowerCase() == 'archived';
    final changed = await Navigator.push<bool>(
      context,
      MaterialPageRoute(
        builder: (_) => archived
            ? ProductRestoreScreen(productId: id)
            : ProductArchiveScreen(productId: id),
      ),
    );
    if (changed == true) _load();
  }

  Future<void> _more(Map<String, dynamic> product) async {
    await showModalBottomSheet<void>(
      context: context,
      backgroundColor: Colors.transparent,
      isScrollControlled: true,
      builder: (sheetContext) {
        final archived = '${product['status']}'.toLowerCase() == 'archived';
        final active = product['is_active'] == true || '${product['is_active']}' == '1';
        void run(VoidCallback action) {
          Navigator.pop(sheetContext);
          action();
        }

        return _Sheet(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              Row(
                children: <Widget>[
                  SizedBox(
                    width: 84,
                    height: 84,
                    child: ProductNetworkImage(url: _productImage(product), fit: BoxFit.cover, borderRadius: 10),
                  ),
                  const SizedBox(width: 16),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: <Widget>[
                        Text(
                          '${product['name'] ?? 'Produit'}',
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(color: Mk.navy, fontSize: 18, fontWeight: FontWeight.w800, height: 1.2),
                        ),
                        const SizedBox(height: 8),
                        _StatusPill(productStatus(product)),
                      ],
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 14),
              const Divider(height: 1, color: _line),
              _SheetAction(
                icon: Icons.image_outlined,
                label: 'Gérer les images',
                onTap: () => run(() => _openImages(product)),
              ),
              if (!archived) ...<Widget>[
                const Divider(height: 1, color: _line),
                _SheetAction(
                  icon: active ? Icons.visibility_off_outlined : Icons.visibility_outlined,
                  label: active ? 'Masquer le produit' : 'Remettre le produit en ligne',
                  onTap: () => run(() => _toggleVisibility(product)),
                ),
              ],
              const Divider(height: 1, color: _line),
              _SheetAction(
                icon: archived ? Icons.restore_rounded : Icons.inventory_2_outlined,
                label: archived ? 'Restaurer le produit' : 'Archiver le produit',
                color: archived ? _blue : Mk.orange,
                onTap: () => run(() => _openArchiveAction(product)),
              ),
            ],
          ),
        );
      },
    );
  }

  void _selectStatus(String status) {
    if (status == _status) return;
    setState(() => _status = status);
    _load();
  }

  /// Feuille de choix unique (tri ou filtre) : titre centré et boutons radio.
  Future<String?> _pickOne(String title, Map<String, String> options, String selected) {
    return showModalBottomSheet<String>(
      context: context,
      backgroundColor: Colors.transparent,
      isScrollControlled: true,
      builder: (sheetContext) => _Sheet(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            Text(
              title,
              textAlign: TextAlign.center,
              style: const TextStyle(color: Mk.navy, fontSize: 20, fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 8),
            for (final entry in options.entries) ...<Widget>[
              if (entry.key != options.keys.first) const Divider(height: 1, color: _line),
              Semantics(
                inMutuallyExclusiveGroup: true,
                checked: entry.key == selected,
                button: true,
                child: InkWell(
                  onTap: () => Navigator.pop(sheetContext, entry.key),
                  child: Padding(
                    padding: const EdgeInsets.symmetric(vertical: 13),
                    child: Row(
                      children: <Widget>[
                        Expanded(
                          child: Text(
                            entry.value,
                            style: const TextStyle(color: Mk.navy, fontSize: 15.5, fontWeight: FontWeight.w600),
                          ),
                        ),
                        Icon(
                          entry.key == selected ? Icons.radio_button_checked_rounded : Icons.radio_button_unchecked_rounded,
                          color: entry.key == selected ? Mk.orange : const Color(0xFF8A95AE),
                          size: 26,
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }

  Future<void> _pickSort() async {
    final picked = await _pickOne('Trier les produits', _sortLabels, _sort);
    if (picked == null || picked == _sort) return;
    setState(() => _sort = picked);
    _load();
  }

  Future<void> _pickFilter() async {
    final picked = await _pickOne('Filtrer les produits', _statusLabels, _status);
    if (picked != null) _selectStatus(picked);
  }

  Future<void> _openNotifications() async {
    await Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => const VendorNotificationsScreen()));
    if (mounted) _loadNotifications();
  }

  @override
  Widget build(BuildContext context) {
    final top = MediaQuery.paddingOf(context).top;
    return MkTheme(
      lightStatusBar: false,
      child: Scaffold(
        backgroundColor: const Color(0xFFF5F7FB),
        floatingActionButton: _AddButton(onTap: _openAdd),
        body: Column(
          children: <Widget>[
            ColoredBox(
              color: Colors.white,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: <Widget>[
                  Padding(
                    padding: EdgeInsets.fromLTRB(16, top + 8, 6, 8),
                    child: Row(
                      children: <Widget>[
                        Image.asset('assets/images/mockups/logo_navy.png', height: 34, semanticLabel: 'OVANIE Vendeur'),
                        const Spacer(),
                        IconButton(
                          tooltip: 'Notifications',
                          onPressed: _openNotifications,
                          icon: Badge(
                            isLabelVisible: _notificationCount > 0,
                            backgroundColor: Mk.orange,
                            label: Text(_notificationCount > 99 ? '99+' : '$_notificationCount'),
                            child: const Icon(Icons.notifications_none_rounded, color: Mk.navy, size: 28),
                          ),
                        ),
                      ],
                    ),
                  ),
                  const Divider(height: 1, color: _line),
                  const Padding(
                    padding: EdgeInsets.fromLTRB(16, 14, 16, 0),
                    child: Text(
                      'Mes produits',
                      style: TextStyle(color: Mk.navy, fontSize: 24, fontWeight: FontWeight.w800, letterSpacing: -.4),
                    ),
                  ),
                  const Padding(
                    padding: EdgeInsets.fromLTRB(16, 2, 16, 12),
                    child: Text(
                      'Gérez votre catalogue en toute simplicité',
                      style: TextStyle(color: Mk.muted, fontSize: 13.5, fontWeight: FontWeight.w500),
                    ),
                  ),
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 14),
                    child: Row(
                      children: <Widget>[
                        Expanded(
                          child: SizedBox(
                            height: 46,
                            child: TextField(
                              controller: _search,
                              textInputAction: TextInputAction.search,
                              onSubmitted: (_) => _load(),
                              style: const TextStyle(fontFamily: Mk.font, color: Mk.navy, fontSize: 14.5, fontWeight: FontWeight.w600),
                              decoration: InputDecoration(
                                hintText: 'Rechercher un produit…',
                                hintStyle: const TextStyle(fontFamily: Mk.font, color: Mk.hint, fontSize: 14.5, fontWeight: FontWeight.w500),
                                prefixIcon: const Icon(Icons.search_rounded, color: Mk.icon, size: 24),
                                filled: true,
                                fillColor: const Color(0xFFF3F6FB),
                                contentPadding: EdgeInsets.zero,
                                border: _searchBorder(_line),
                                enabledBorder: _searchBorder(_line),
                                focusedBorder: _searchBorder(Mk.blue),
                              ),
                            ),
                          ),
                        ),
                        const SizedBox(width: 8),
                        _SquareButton(icon: Icons.filter_alt_outlined, tooltip: 'Filtrer', onTap: _pickFilter),
                        const SizedBox(width: 8),
                        _SquareButton(icon: Icons.swap_vert_rounded, tooltip: 'Trier', onTap: _pickSort),
                      ],
                    ),
                  ),
                  const SizedBox(height: 6),
                  _statusTabs(),
                ],
              ),
            ),
            Expanded(
              child: RefreshIndicator(
                onRefresh: () => _load(clearItems: false),
                color: Mk.orange,
                child: ListView(
                  controller: _scroll,
                  padding: const EdgeInsets.fromLTRB(12, 12, 12, 96),
                  children: <Widget>[
                    if (_error != null) ...<Widget>[
                      VendorErrorBox(_error),
                      const SizedBox(height: 10),
                    ],
                    if (_loading)
                      const Padding(
                        padding: EdgeInsets.symmetric(vertical: 48),
                        child: Center(child: CircularProgressIndicator(color: Mk.orange)),
                      )
                    else if (_items.isEmpty)
                      _EmptyProducts(onAdd: _openAdd)
                    else
                      ..._items.map((raw) {
                        final product = Map<String, dynamic>.from(raw as Map);
                        return Padding(
                          padding: const EdgeInsets.only(bottom: 10),
                          child: _ProductCard(
                            product: product,
                            onOpen: () => _openDetail(product),
                            onEdit: () => _openEdit(product),
                            onMore: () => _more(product),
                          ),
                        );
                      }),
                    if (_loadingMore)
                      const Padding(
                        padding: EdgeInsets.only(top: 6),
                        child: Center(
                          child: SizedBox(
                            width: 24,
                            height: 24,
                            child: CircularProgressIndicator(strokeWidth: 2.4, color: Mk.orange),
                          ),
                        ),
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

  int? _countFor(String status) {
    final key = switch (status) {
      'all' => 'total',
      'active' => 'active',
      'draft' => 'draft',
      'out_of_stock' => 'out_of_stock',
      'hidden' => 'hidden',
      'archived' => 'archived',
      _ => null,
    };
    if (key == null) return null;
    return int.tryParse('${_stats[key] ?? ''}');
  }

  /// Onglets soulignés avec le nombre de produits de chaque état.
  Widget _statusTabs() {
    return SizedBox(
      height: 46,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: 14),
        itemCount: _statusLabels.length,
        separatorBuilder: (_, __) => const SizedBox(width: 18),
        itemBuilder: (context, i) {
          final entry = _statusLabels.entries.elementAt(i);
          final active = _status == entry.key;
          final count = _countFor(entry.key);
          return Semantics(
            selected: active,
            button: true,
            child: InkWell(
              onTap: () => _selectStatus(entry.key),
              child: Column(
                mainAxisAlignment: MainAxisAlignment.end,
                children: <Widget>[
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 2),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: <Widget>[
                        Text(
                          entry.value,
                          style: TextStyle(
                            color: active ? Mk.navy : Mk.muted,
                            fontSize: 14,
                            fontWeight: active ? FontWeight.w800 : FontWeight.w500,
                          ),
                        ),
                        if (count != null) ...<Widget>[
                          const SizedBox(width: 6),
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
                            decoration: BoxDecoration(
                              color: const Color(0xFFEEF1F6),
                              borderRadius: BorderRadius.circular(99),
                            ),
                            child: Text(
                              '$count',
                              style: TextStyle(
                                color: active ? Mk.navy : Mk.muted,
                                fontSize: 12,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
                  const SizedBox(height: 9),
                  AnimatedContainer(
                    duration: const Duration(milliseconds: 160),
                    height: 3,
                    width: active ? 64 : 0,
                    decoration: BoxDecoration(color: Mk.orange, borderRadius: BorderRadius.circular(2)),
                  ),
                ],
              ),
            ),
          );
        },
      ),
    );
  }
}

const _line = Color(0xFFE4E9F1);
const _blue = Color(0xFF1668E3);

OutlineInputBorder _searchBorder(Color color) => OutlineInputBorder(
      borderRadius: BorderRadius.circular(12),
      borderSide: BorderSide(color: color, width: 1.2),
    );

/// Image d'un produit de la liste : vignette, sinon image principale.
Object? _productImage(Map<String, dynamic> product) {
  for (final key in const <String>['thumb_image_url', 'card_image_url', 'main_image_url', 'image_url']) {
    final value = '${product[key] ?? ''}'.trim();
    if (value.isNotEmpty) return value;
  }
  return null;
}

/// « Catégorie • Sous-catégorie » d'un produit.
String _productCategory(Map<String, dynamic> product) {
  String name(Object? raw) => raw is Map ? '${raw['name'] ?? ''}'.trim() : '';
  final category = product['category'];
  final parent = category is Map ? name(category['parent']) : '';
  final sub = name(product['subcategory']);
  return <String>[parent, name(category), sub].where((part) => part.isNotEmpty).toSet().join(' • ');
}

/// Unité de vente avec une majuscule (« Sac », « Pièce »).
String _productUnit(Map<String, dynamic> product) {
  final raw = '${product['unit_label'] ?? product['display_unit'] ?? product['unit'] ?? ''}'.trim();
  if (raw.isEmpty) return '';
  // Les symboles d'unité restent en minuscules : « m² », « kg », et non « M² ».
  if (RegExp(r'^(m²|m³|m2|m3|kg|ml|g|l)').hasMatch(raw)) return raw;
  return raw[0].toUpperCase() + raw.substring(1);
}

/// Pastille d'état d'un produit.
class _StatusPill extends StatelessWidget {
  const _StatusPill(this.status);

  final String status;

  @override
  Widget build(BuildContext context) {
    final color = productStatusColor(status);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 5),
      decoration: BoxDecoration(color: color.withValues(alpha: .13), borderRadius: BorderRadius.circular(99)),
      child: Text(status, style: TextStyle(color: color, fontSize: 12.5, fontWeight: FontWeight.w700)),
    );
  }
}

/// Bouton carré à contour (filtrer, trier).
class _SquareButton extends StatelessWidget {
  const _SquareButton({required this.icon, required this.tooltip, required this.onTap});

  final IconData icon;
  final String tooltip;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Tooltip(
      message: tooltip,
      child: Material(
        color: Colors.white,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(12),
          side: const BorderSide(color: _line, width: 1.2),
        ),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: onTap,
          child: SizedBox(width: 46, height: 46, child: Icon(icon, color: Mk.navy, size: 24)),
        ),
      ),
    );
  }
}

/// Bouton flottant « Ajouter un produit ».
class _AddButton extends StatelessWidget {
  const _AddButton({required this.onTap});

  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      button: true,
      label: 'Ajouter un produit',
      excludeSemantics: true,
      child: Material(
        color: Mk.orange,
        elevation: 6,
        shadowColor: const Color(0x66FF6A00),
        shape: const StadiumBorder(),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: onTap,
          child: Padding(
            padding: const EdgeInsets.fromLTRB(8, 8, 18, 8),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: <Widget>[
                Container(
                  width: 36,
                  height: 36,
                  decoration: const BoxDecoration(color: Colors.white, shape: BoxShape.circle),
                  child: const Icon(Icons.add_rounded, color: Mk.orange, size: 26),
                ),
                const SizedBox(width: 10),
                const Text(
                  'Ajouter un produit',
                  style: TextStyle(fontFamily: Mk.font, color: Colors.white, fontSize: 14.5, fontWeight: FontWeight.w700),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// Feuille basse blanche à coins arrondis, avec sa poignée.
class _Sheet extends StatelessWidget {
  const _Sheet({required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    return MkTheme(
      lightStatusBar: false,
      child: Container(
        constraints: BoxConstraints(maxHeight: MediaQuery.sizeOf(context).height * .86),
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(26)),
        ),
        child: SafeArea(
          top: false,
          child: SingleChildScrollView(
            padding: const EdgeInsets.fromLTRB(20, 10, 20, 14),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: <Widget>[
                Center(
                  child: Container(
                    width: 44,
                    height: 5,
                    decoration: BoxDecoration(color: const Color(0xFFC9CED8), borderRadius: BorderRadius.circular(3)),
                  ),
                ),
                const SizedBox(height: 16),
                child,
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// Ligne d'action d'une feuille : pictogramme, libellé et chevron.
class _SheetAction extends StatelessWidget {
  const _SheetAction({required this.icon, required this.label, required this.onTap, this.color = _blue});

  final IconData icon;
  final String label;
  final Color color;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final orange = color == Mk.orange;
    return InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 14),
        child: Row(
          children: <Widget>[
            Icon(icon, color: color, size: 26),
            const SizedBox(width: 18),
            Expanded(
              child: Text(
                label,
                style: TextStyle(color: orange ? Mk.orange : Mk.navy, fontSize: 15.5, fontWeight: FontWeight.w700),
              ),
            ),
            const Icon(Icons.chevron_right_rounded, color: Mk.icon, size: 24),
          ],
        ),
      ),
    );
  }
}

/// Carte d'un produit : photo, nom, catégorie, stock, prix, état et actions.
class _ProductCard extends StatelessWidget {
  const _ProductCard({required this.product, required this.onOpen, required this.onEdit, required this.onMore});

  final Map<String, dynamic> product;
  final VoidCallback onOpen;
  final VoidCallback onEdit;
  final VoidCallback onMore;

  @override
  Widget build(BuildContext context) {
    final category = _productCategory(product);
    final status = productStatus(product);
    final stock = int.tryParse('${product['stock'] ?? 0}') ?? 0;
    final unit = _productUnit(product);
    final stockColor = stock > 0 ? Mk.navy : const Color(0xFFE02D2D);

    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: _line, width: 1.1),
      ),
      clipBehavior: Clip.antiAlias,
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: onOpen,
          child: Padding(
            padding: const EdgeInsets.fromLTRB(10, 10, 0, 6),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                SizedBox(
                  width: 84,
                  height: 96,
                  child: ProductNetworkImage(url: _productImage(product), fit: BoxFit.cover, borderRadius: 10),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: <Widget>[
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: <Widget>[
                                Text(
                                  '${product['name'] ?? 'Produit'}',
                                  maxLines: 2,
                                  overflow: TextOverflow.ellipsis,
                                  style: const TextStyle(color: Mk.navy, fontSize: 14.5, fontWeight: FontWeight.w800, height: 1.2),
                                ),
                                if (category.isNotEmpty)
                                  Padding(
                                    padding: const EdgeInsets.only(top: 3),
                                    child: Text(
                                      category,
                                      maxLines: 1,
                                      overflow: TextOverflow.ellipsis,
                                      style: const TextStyle(color: Mk.muted, fontSize: 12, fontWeight: FontWeight.w500),
                                    ),
                                  ),
                                const SizedBox(height: 4),
                                Text.rich(
                                  TextSpan(
                                    text: 'Stock : $stock',
                                    style: const TextStyle(fontWeight: FontWeight.w700),
                                    children: <InlineSpan>[
                                      if (unit.isNotEmpty)
                                        TextSpan(text: ' $unit', style: const TextStyle(fontWeight: FontWeight.w500)),
                                    ],
                                  ),
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: TextStyle(color: stockColor, fontSize: 12.5),
                                ),
                              ],
                            ),
                          ),
                          const SizedBox(width: 6),
                          Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: <Widget>[
                              Text(
                                productMoney(product['promo_price'] ?? product['price']),
                                style: const TextStyle(color: Mk.navy, fontSize: 13.5, fontWeight: FontWeight.w800, height: 1.2),
                              ),
                              const SizedBox(height: 6),
                              _StatusPill(status),
                            ],
                          ),
                          SizedBox(
                            width: 36,
                            height: 36,
                            child: IconButton(
                              tooltip: 'Plus d’actions',
                              padding: EdgeInsets.zero,
                              onPressed: onMore,
                              icon: const Icon(Icons.more_vert_rounded, color: Mk.icon, size: 22),
                            ),
                          ),
                        ],
                      ),
                      const Padding(
                        padding: EdgeInsets.only(top: 6, right: 12),
                        child: Divider(height: 1, color: _line),
                      ),
                      Row(
                        children: <Widget>[
                          _CardAction(icon: Icons.edit_outlined, label: 'Modifier', onTap: onEdit),
                          Container(width: 1, height: 18, color: _line, margin: const EdgeInsets.symmetric(horizontal: 6)),
                          _CardAction(icon: Icons.visibility_outlined, label: 'Voir', onTap: onOpen),
                        ],
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _CardAction extends StatelessWidget {
  const _CardAction({required this.icon, required this.label, required this.onTap});

  final IconData icon;
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(8),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 10),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            Icon(icon, color: Mk.icon, size: 19),
            const SizedBox(width: 7),
            Text(label, style: const TextStyle(color: Mk.icon, fontSize: 13, fontWeight: FontWeight.w600)),
          ],
        ),
      ),
    );
  }
}

class _EmptyProducts extends StatelessWidget {
  const _EmptyProducts({required this.onAdd});

  final VoidCallback onAdd;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 34),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: _line, width: 1.1),
      ),
      child: Column(
        children: <Widget>[
          const Icon(Icons.inventory_2_outlined, size: 46, color: Mk.hint),
          const SizedBox(height: 10),
          const Text(
            'Aucun produit dans cette liste',
            textAlign: TextAlign.center,
            style: TextStyle(color: Mk.navy, fontWeight: FontWeight.w800, fontSize: 16),
          ),
          const SizedBox(height: 5),
          const Text(
            'Ajoutez un produit ou changez le filtre sélectionné.',
            textAlign: TextAlign.center,
            style: TextStyle(color: Mk.muted, fontSize: 13.5),
          ),
          const SizedBox(height: 16),
          MkOutlineButton(label: 'Ajouter un produit', icon: Icons.add_rounded, onPressed: onAdd, height: 44),
        ],
      ),
    );
  }
}
