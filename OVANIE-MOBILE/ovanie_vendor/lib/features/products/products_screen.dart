import 'package:flutter/material.dart';

import '../../core/network/api_client.dart';
import '../../core/ui/vendor_design.dart';
import '../../data/vendor_repository.dart';
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

class _ProductsScreenState extends State<ProductsScreen> {
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
  }

  @override
  void dispose() {
    _scroll.removeListener(_onScroll);
    _scroll.dispose();
    _search.dispose();
    super.dispose();
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
      builder: (sheetContext) {
        final archived = '${product['status']}'.toLowerCase() == 'archived';
        final active = product['is_active'] == true || '${product['is_active']}' == '1';
        return SafeArea(
          top: false,
          child: Container(
            padding: const EdgeInsets.fromLTRB(18, 10, 18, 18),
            decoration: const BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
            ),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: <Widget>[
                Container(
                  width: 44,
                  height: 4,
                  decoration: BoxDecoration(
                    color: const Color(0xFFD5DBE5),
                    borderRadius: BorderRadius.circular(99),
                  ),
                ),
                const SizedBox(height: 12),
                ListTile(
                  leading: const Icon(Icons.photo_library_outlined, color: vendorBlue),
                  title: const Text('Gérer les images', style: TextStyle(fontWeight: FontWeight.w800)),
                  onTap: () {
                    Navigator.pop(sheetContext);
                    _openImages(product);
                  },
                ),
                if (!archived)
                  ListTile(
                    leading: Icon(active ? Icons.visibility_off_outlined : Icons.visibility_outlined, color: vendorBlue),
                    title: Text(
                      active ? 'Masquer le produit' : 'Remettre le produit en ligne',
                      style: const TextStyle(fontWeight: FontWeight.w800),
                    ),
                    onTap: () {
                      Navigator.pop(sheetContext);
                      _toggleVisibility(product);
                    },
                  ),
                ListTile(
                  leading: Icon(
                    archived ? Icons.restore_rounded : Icons.archive_outlined,
                    color: archived ? vendorBlue : productOrange,
                  ),
                  title: Text(
                    archived ? 'Restaurer le produit' : 'Archiver le produit',
                    style: const TextStyle(fontWeight: FontWeight.w800),
                  ),
                  onTap: () {
                    Navigator.pop(sheetContext);
                    _openArchiveAction(product);
                  },
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  void _selectStatus(String status) {
    setState(() => _status = status);
    _load();
  }

  @override
  Widget build(BuildContext context) {
    return ColoredBox(
      color: Colors.white,
      child: Stack(
        children: <Widget>[
          Column(
            children: <Widget>[
          ProductHeader(
            title: 'Mes produits',
            subtitle: 'Gérez votre catalogue en toute simplicité',
            bottom: TextField(
              controller: _search,
              textInputAction: TextInputAction.search,
              onSubmitted: (_) => _load(),
              style: const TextStyle(color: vendorText, fontWeight: FontWeight.w600),
              decoration: InputDecoration(
                hintText: 'Rechercher un produit...',
                hintStyle: const TextStyle(color: Color(0xFF7487A7), fontSize: 15),
                suffixIcon: IconButton(
                  onPressed: _load,
                  icon: const Icon(Icons.search_rounded, color: Color(0xFF061735), size: 29),
                ),
                filled: true,
                fillColor: Colors.white,
                contentPadding: const EdgeInsets.symmetric(horizontal: 20, vertical: 17),
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(18), borderSide: BorderSide.none),
                enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(18), borderSide: BorderSide.none),
                focusedBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(18),
                  borderSide: const BorderSide(color: productOrange, width: 1.3),
                ),
              ),
            ),
          ),
          Expanded(
            child: RefreshIndicator(
              onRefresh: () => _load(clearItems: false),
              child: ListView(
                controller: _scroll,
                padding: EdgeInsets.zero,
                children: <Widget>[
                  Container(
                    transform: Matrix4.translationValues(0, -1, 0),
                    padding: const EdgeInsets.fromLTRB(18, 14, 18, 22),
                    decoration: const BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: <Widget>[
                        _filters(),
                        const SizedBox(height: 12),
                        _statusTabs(),
                        const SizedBox(height: 14),
                        _StatsRow(stats: _stats),
                        const SizedBox(height: 18),
                        const Text(
                          'Liste des produits',
                          style: TextStyle(color: vendorText, fontSize: 19, fontWeight: FontWeight.w900),
                        ),
                        const SizedBox(height: 10),
                        if (_error != null) ...<Widget>[
                          VendorErrorBox(_error),
                          const SizedBox(height: 10),
                        ],
                        if (_loading)
                          const Padding(
                            padding: EdgeInsets.symmetric(vertical: 48),
                            child: Center(child: CircularProgressIndicator()),
                          )
                        else if (_items.isEmpty)
                          _EmptyProducts(onAdd: _openAdd)
                        else
                          ..._items.map((raw) {
                            final product = Map<String, dynamic>.from(raw as Map);
                            return Padding(
                              padding: const EdgeInsets.only(bottom: 10),
                              child: _ProductRow(
                                product: product,
                                onOpen: () => _openDetail(product),
                                onEdit: () => _openEdit(product),
                                onMore: () => _more(product),
                              ),
                            );
                          }),
                        if (_loadingMore) ...<Widget>[
                          const SizedBox(height: 6),
                          const Center(
                            child: SizedBox(
                              width: 24,
                              height: 24,
                              child: CircularProgressIndicator(strokeWidth: 2.4),
                            ),
                          ),
                        ],
                        const SizedBox(height: 76),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
            ],
          ),
          Positioned(
            left: 0,
            right: 0,
            bottom: 12,
            child: SafeArea(
              top: false,
              minimum: const EdgeInsets.symmetric(horizontal: 18),
              child: Center(
                child: FractionallySizedBox(
                  widthFactor: .62,
                  child: SizedBox(
                    height: 52,
                    child: FilledButton.icon(
                      onPressed: _openAdd,
                      icon: Container(
                        width: 28,
                        height: 28,
                        decoration: const BoxDecoration(color: Colors.white, shape: BoxShape.circle),
                        child: const Icon(Icons.add_rounded, color: productOrange, size: 22),
                      ),
                      label: const FittedBox(
                        fit: BoxFit.scaleDown,
                        child: Text('Ajouter un produit', style: TextStyle(fontSize: 15, fontWeight: FontWeight.w900)),
                      ),
                      style: FilledButton.styleFrom(
                        backgroundColor: productOrange,
                        elevation: 4,
                        shadowColor: const Color(0x33000000),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(13)),
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _filters() {
    return Row(
      children: <Widget>[
        Expanded(
          child: ProductSheetSelector<String>(
            value: _status,
            label: 'Filtrer les produits',
            items: _statusLabels.keys.toList(),
            display: (value) => _statusLabels[value]!,
            icon: Icons.filter_alt_outlined,
            buttonText: 'Filtres',
            onChanged: _selectStatus,
          ),
        ),
        const SizedBox(width: 8),
        Expanded(
          child: ProductSheetSelector<String>(
            value: _sort,
            label: 'Trier les produits',
            items: _sortLabels.keys.toList(),
            display: (value) => _sortLabels[value]!,
            icon: Icons.swap_vert_rounded,
            buttonText: 'Trier par',
            onChanged: (value) {
              setState(() => _sort = value);
              _load();
            },
          ),
        ),
      ],
    );
  }

  Widget _statusTabs() {
    return SizedBox(
      height: 43,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        physics: const BouncingScrollPhysics(),
        itemCount: _statusLabels.length,
        separatorBuilder: (_, __) => const SizedBox(width: 7),
        itemBuilder: (context, i) {
          final entry = _statusLabels.entries.elementAt(i);
          final active = _status == entry.key;
          return InkWell(
            onTap: () => _selectStatus(entry.key),
            borderRadius: BorderRadius.circular(12),
            child: AnimatedContainer(
              duration: const Duration(milliseconds: 160),
              alignment: Alignment.center,
              padding: const EdgeInsets.symmetric(horizontal: 16),
              decoration: BoxDecoration(
                color: active ? productNavy : Colors.white,
                border: Border.all(color: active ? productNavy : productBorder),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Text(
                entry.value,
                style: TextStyle(
                  color: active ? Colors.white : vendorText,
                  fontSize: 13,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ),
          );
        },
      ),
    );
  }
}

class _StatsRow extends StatelessWidget {
  const _StatsRow({required this.stats});

  final Map<String, dynamic> stats;

  @override
  Widget build(BuildContext context) {
    final items = <_StatData>[
      _StatData('${stats['total'] ?? 0}', 'produits', Icons.inventory_2_rounded, const Color(0xFF179C46)),
      _StatData('${stats['active'] ?? 0}', 'actifs', Icons.circle, const Color(0xFF179C46)),
      _StatData('${stats['draft'] ?? 0}', 'brouillons', Icons.description_outlined, const Color(0xFFF29900)),
      _StatData('${stats['out_of_stock'] ?? 0}', 'rupture', Icons.warning_amber_rounded, const Color(0xFFE53935)),
    ];
    return Container(
      decoration: BoxDecoration(color: Colors.white, border: Border.all(color: productBorder), borderRadius: BorderRadius.circular(14)),
      child: Row(
        children: List<Widget>.generate(items.length, (index) {
          return Expanded(
            child: Container(
              decoration: BoxDecoration(
                border: index == items.length - 1 ? null : const Border(right: BorderSide(color: productBorder)),
              ),
              child: _StatItem(data: items[index]),
            ),
          );
        }),
      ),
    );
  }
}

class _StatData {
  const _StatData(this.value, this.label, this.icon, this.color);
  final String value;
  final String label;
  final IconData icon;
  final Color color;
}

class _StatItem extends StatelessWidget {
  const _StatItem({required this.data});

  final _StatData data;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 11),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.center,
        children: <Widget>[
          VendorCircleIcon(
            icon: data.icon,
            color: data.color,
            background: data.color.withValues(alpha: .11),
            size: 34,
            iconSize: 17,
          ),
          const SizedBox(width: 6),
          Flexible(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: <Widget>[
                FittedBox(
                  fit: BoxFit.scaleDown,
                  alignment: Alignment.centerLeft,
                  child: Text(data.value, style: const TextStyle(color: vendorText, fontSize: 18, fontWeight: FontWeight.w900)),
                ),
                Text(data.label, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: vendorMuted, fontSize: 10.5)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _ProductRow extends StatelessWidget {
  const _ProductRow({required this.product, required this.onOpen, required this.onEdit, required this.onMore});

  final Map<String, dynamic> product;
  final VoidCallback onOpen;
  final VoidCallback onEdit;
  final VoidCallback onMore;

  @override
  Widget build(BuildContext context) {
    final category = product['category'] is Map ? '${(product['category'] as Map)['name'] ?? ''}' : '';
    final subcategory = product['subcategory'] is Map ? '${(product['subcategory'] as Map)['name'] ?? ''}' : '';
    final status = productStatus(product);
    final unit = '${product['unit_label'] ?? product['unit'] ?? ''}'.trim();

    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(13),
      child: InkWell(
        onTap: onOpen,
        borderRadius: BorderRadius.circular(13),
        child: Container(
          padding: const EdgeInsets.all(9),
          decoration: BoxDecoration(border: Border.all(color: productBorder), borderRadius: BorderRadius.circular(13)),
          child: Row(
            children: <Widget>[
              SizedBox(
                width: 78,
                height: 68,
                child: ProductNetworkImage(url: product['image_url'], fit: BoxFit.cover, borderRadius: 9),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(
                      '${product['name'] ?? 'Produit'}',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(color: vendorText, fontSize: 14, fontWeight: FontWeight.w900),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      <String>[category, subcategory].where((e) => e.isNotEmpty).join('  •  '),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(color: vendorMuted, fontSize: 10.5),
                    ),
                    const SizedBox(height: 7),
                    Row(
                      children: <Widget>[
                        Flexible(
                          child: Text(
                            productMoney(product['promo_price'] ?? product['price']),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(color: productOrange, fontSize: 13.5, fontWeight: FontWeight.w900),
                          ),
                        ),
                        const SizedBox(width: 8),
                        Flexible(
                          child: Text(
                            'Stock : ${product['stock'] ?? 0}${unit.isEmpty ? '' : ' $unit'}',
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(color: vendorMuted, fontSize: 10.5),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 6),
              SizedBox(
                width: 112,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: <Widget>[
                    ProductStatusBadge(status),
                    const SizedBox(height: 9),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.end,
                      children: <Widget>[
                        _ActionIcon(icon: Icons.edit_outlined, onTap: onEdit),
                        const SizedBox(width: 4),
                        _ActionIcon(icon: Icons.visibility_outlined, onTap: onOpen),
                        const SizedBox(width: 4),
                        _ActionIcon(icon: Icons.more_vert_rounded, onTap: onMore),
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

class _ActionIcon extends StatelessWidget {
  const _ActionIcon({required this.icon, required this.onTap});

  final IconData icon;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(8),
      child: Container(
        width: 32,
        height: 32,
        alignment: Alignment.center,
        decoration: BoxDecoration(border: Border.all(color: productBorder), borderRadius: BorderRadius.circular(8)),
        child: Icon(icon, color: vendorText, size: 18),
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
      padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 38),
      decoration: BoxDecoration(color: productSoft, borderRadius: BorderRadius.circular(14)),
      child: Column(
        children: <Widget>[
          const Icon(Icons.inventory_2_outlined, size: 48, color: vendorMuted),
          const SizedBox(height: 10),
          const Text('Aucun produit dans cette liste', style: TextStyle(color: vendorText, fontWeight: FontWeight.w900, fontSize: 16)),
          const SizedBox(height: 5),
          const Text('Ajoutez un produit ou changez le filtre sélectionné.', textAlign: TextAlign.center, style: TextStyle(color: vendorMuted)),
          const SizedBox(height: 14),
          OutlinedButton.icon(onPressed: onAdd, icon: const Icon(Icons.add), label: const Text('Ajouter un produit')),
        ],
      ),
    );
  }
}
