import 'dart:async';

import 'package:flutter/material.dart';

import '../../../app/theme.dart';
import '../../../core/network/api_client.dart';
import '../../../shared/widgets/api_error_card.dart';
import '../../catalog/domain/catalog_navigation_store.dart';
import '../../home/data/marketplace_repository.dart';
import '../../search/presentation/search_screen.dart';
import '../domain/category_model.dart';
import 'subcategory_screen.dart';

/// Écran Catégories OVANIE reconstruit selon la maquette mobile :
/// - recherche en haut ;
/// - catégories racines dans un rail vertical à gauche ;
/// - sous-catégories de la catégorie sélectionnée à droite ;
/// - clic sur une sous-catégorie => écran Sous-catégorie sans quitter
///   l'onglet Catégories du MainShell.
///
/// Les catégories proviennent uniquement de Laravel via
/// `/marketplace/categories`.
class CategoriesScreen extends StatefulWidget {
  final String? initialCategorySlug;
  final bool showBackButton;
  final CatalogNavigationStore? navigationStore;

  const CategoriesScreen({
    super.key,
    this.initialCategorySlug,
    this.showBackButton = false,
    this.navigationStore,
  });

  @override
  State<CategoriesScreen> createState() => _CategoriesScreenState();
}

class _CategoriesScreenState extends State<CategoriesScreen> {
  final MarketplaceRepository _repository = const MarketplaceRepository();

  List<CategoryModel> _roots = const <CategoryModel>[];
  CategoryModel? _selectedRoot;
  CategoryModel? _selectedSubcategory;
  bool _loading = true;
  Object? _error;
  int _lastNavigationSerial = 0;

  @override
  void initState() {
    super.initState();
    widget.navigationStore?.addListener(_onExternalNavigation);
    _loadCategories();
  }

  @override
  void didUpdateWidget(covariant CategoriesScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.navigationStore != widget.navigationStore) {
      oldWidget.navigationStore?.removeListener(_onExternalNavigation);
      widget.navigationStore?.addListener(_onExternalNavigation);
    }
  }

  @override
  void dispose() {
    widget.navigationStore?.removeListener(_onExternalNavigation);
    super.dispose();
  }

  Future<void> _loadCategories() async {
    if (mounted) {
      setState(() {
        _loading = true;
        _error = null;
      });
    }

    try {
      final roots = await _repository.getRootCategories(limit: 50);
      if (!mounted) return;

      CategoryModel? selectedRoot;
      CategoryModel? selectedSubcategory;
      final requestedSlug = widget.initialCategorySlug?.trim();

      if (requestedSlug != null && requestedSlug.isNotEmpty) {
        final target = _repository.findCategoryInRoots(
          roots,
          slug: requestedSlug,
        );
        if (target != null) {
          if (target.parentId == null) {
            selectedRoot = target;
          } else {
            selectedSubcategory = target;
            selectedRoot = _repository.findRootForCategory(roots, target);
          }
        }
      }

      selectedRoot ??= roots.isNotEmpty ? roots.first : null;

      setState(() {
        _roots = roots;
        _selectedRoot = selectedRoot;
        _selectedSubcategory = selectedSubcategory;
        _loading = false;
      });

      final pending = widget.navigationStore?.request;
      if (pending != null && pending.serial > _lastNavigationSerial) {
        _applyNavigationRequest(pending);
      }
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _error = error;
        _loading = false;
      });
    }
  }

  void _onExternalNavigation() {
    final request = widget.navigationStore?.request;
    if (request == null || request.serial <= _lastNavigationSerial) return;
    if (_roots.isEmpty) return;
    _applyNavigationRequest(request);
  }

  void _applyNavigationRequest(CatalogNavigationRequest request) {
    if (!mounted || request.serial <= _lastNavigationSerial) return;
    _lastNavigationSerial = request.serial;

    final slug = request.categorySlug?.trim();
    CategoryModel? root;
    CategoryModel? child;

    if (slug != null && slug.isNotEmpty) {
      final target = _repository.findCategoryInRoots(_roots, slug: slug);
      if (target != null) {
        if (target.parentId == null) {
          root = target;
        } else {
          child = target;
          root = _repository.findRootForCategory(_roots, target);
        }
      }
    }

    root ??= _roots.isNotEmpty ? _roots.first : null;

    setState(() {
      _selectedRoot = root;
      _selectedSubcategory = child;
    });
  }

  void _selectRoot(CategoryModel category) {
    if (_selectedRoot?.id == category.id && _selectedSubcategory == null) return;
    setState(() {
      _selectedRoot = category;
      _selectedSubcategory = null;
    });
  }

  void _openSubcategory(CategoryModel category) {
    setState(() => _selectedSubcategory = category);
  }

  void _returnToCategory() {
    if (_selectedSubcategory == null) return;
    setState(() => _selectedSubcategory = null);
  }

  void _openSearch() {
    Navigator.of(context).push(
      MaterialPageRoute<void>(builder: (_) => const SearchScreen()),
    );
  }

  @override
  Widget build(BuildContext context) {
    if (_selectedSubcategory != null) {
      return SubcategoryScreen(
        categoryId: _selectedSubcategory!.id,
        categorySlug: _selectedSubcategory!.slug,
        categoryName: _selectedSubcategory!.name,
        parentCategoryName: _selectedRoot?.name,
        embedded: true,
        onBackToCategory: _returnToCategory,
      );
    }

    return ColoredBox(
      color: Colors.white,
      child: SafeArea(
        bottom: false,
        child: Column(
          children: [
            _CategorySearchBar(onTap: _openSearch),
            Expanded(child: _buildBody()),
          ],
        ),
      ),
    );
  }

  Widget _buildBody() {
    if (_loading) {
      return const Center(
        child: CircularProgressIndicator(color: OvanieColors.orange),
      );
    }

    if (_error != null) {
      return ListView(
        padding: const EdgeInsets.all(18),
        children: [
          const SizedBox(height: 80),
          ApiErrorCard(
            message: ApiClient.friendlyError(_error!),
            onRetry: _loadCategories,
          ),
        ],
      );
    }

    if (_roots.isEmpty) {
      return const Center(
        child: Text(
          'Aucune catégorie disponible pour le moment.',
          textAlign: TextAlign.center,
          style: TextStyle(
            color: OvanieColors.muted,
            fontSize: 14,
            fontWeight: FontWeight.w600,
          ),
        ),
      );
    }

    final selected = _selectedRoot ?? _roots.first;

    return LayoutBuilder(
      builder: (context, constraints) {
        final width = constraints.maxWidth;
        final railWidth = (width * .275).clamp(104.0, 126.0).toDouble();

        return Row(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            SizedBox(
              width: railWidth,
              child: _CategoryRail(
                categories: _roots,
                selected: selected,
                onSelect: _selectRoot,
              ),
            ),
            const VerticalDivider(
              width: 1,
              thickness: 1,
              color: Color(0xFFE9EDF3),
            ),
            Expanded(
              child: _SubcategoriesPanel(
                category: selected,
                onOpenSubcategory: _openSubcategory,
              ),
            ),
          ],
        );
      },
    );
  }
}

class _CategorySearchBar extends StatelessWidget {
  final VoidCallback onTap;

  const _CategorySearchBar({required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
      child: Material(
        color: Colors.white,
        borderRadius: BorderRadius.circular(15),
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(15),
          child: Container(
            height: 54,
            padding: const EdgeInsets.symmetric(horizontal: 15),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(15),
              border: Border.all(color: const Color(0xFFDDE3EB)),
            ),
            child: const Row(
              children: [
                Icon(
                  Icons.search_rounded,
                  size: 27,
                  color: Color(0xFF52627D),
                ),
                SizedBox(width: 12),
                Expanded(
                  child: Text(
                    'Rechercher un produit, une marque...',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: Color(0xFF77839A),
                      fontSize: 14,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                ),
                SizedBox(width: 8),
                Icon(
                  Icons.center_focus_weak_rounded,
                  size: 28,
                  color: OvanieColors.navy,
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _CategoryRail extends StatelessWidget {
  final List<CategoryModel> categories;
  final CategoryModel selected;
  final ValueChanged<CategoryModel> onSelect;

  const _CategoryRail({
    required this.categories,
    required this.selected,
    required this.onSelect,
  });

  @override
  Widget build(BuildContext context) {
    return ListView.separated(
      padding: const EdgeInsets.fromLTRB(12, 2, 8, 18),
      physics: const BouncingScrollPhysics(),
      itemCount: categories.length,
      separatorBuilder: (_, __) => const SizedBox(height: 7),
      itemBuilder: (context, index) {
        final category = categories[index];
        return _CategoryRailTile(
          category: category,
          selected: selected.id == category.id,
          onTap: () => onSelect(category),
        );
      },
    );
  }
}

class _CategoryRailTile extends StatelessWidget {
  final CategoryModel category;
  final bool selected;
  final VoidCallback onTap;

  const _CategoryRailTile({
    required this.category,
    required this.selected,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(12),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(12),
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 160),
          constraints: const BoxConstraints(minHeight: 72),
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 10),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(12),
            border: Border.all(
              color: selected ? OvanieColors.orange : const Color(0xFFE0E5EC),
              width: selected ? 1.7 : 1,
            ),
          ),
          child: Center(
            child: Text(
              category.name,
              maxLines: 3,
              overflow: TextOverflow.ellipsis,
              textAlign: TextAlign.center,
              style: TextStyle(
                color: selected ? OvanieColors.orange : OvanieColors.navy,
                fontSize: 11.5,
                height: 1.12,
                fontWeight: selected ? FontWeight.w900 : FontWeight.w800,
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _SubcategoriesPanel extends StatelessWidget {
  final CategoryModel category;
  final ValueChanged<CategoryModel> onOpenSubcategory;

  const _SubcategoriesPanel({
    required this.category,
    required this.onOpenSubcategory,
  });

  @override
  Widget build(BuildContext context) {
    final children = category.children;

    return CustomScrollView(
      physics: const BouncingScrollPhysics(),
      slivers: [
        SliverPadding(
          padding: const EdgeInsets.fromLTRB(16, 14, 12, 0),
          sliver: SliverToBoxAdapter(
            child: Text(
              category.name,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                color: OvanieColors.navy,
                fontSize: 24,
                height: 1.05,
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
        ),
        if (children.isEmpty)
          const SliverFillRemaining(
            hasScrollBody: false,
            child: Center(
              child: Padding(
                padding: EdgeInsets.all(24),
                child: Text(
                  'Aucune sous-catégorie disponible pour le moment.',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    color: OvanieColors.muted,
                    fontSize: 13,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
            ),
          )
        else
          SliverPadding(
            padding: const EdgeInsets.fromLTRB(14, 24, 12, 22),
            sliver: SliverLayoutBuilder(
              builder: (context, constraints) {
                final availableWidth = constraints.crossAxisExtent;
                final columns = availableWidth >= 430
                    ? 5
                    : availableWidth >= 250
                        ? 4
                        : 3;
                return SliverGrid(
                  delegate: SliverChildBuilderDelegate(
                    (context, index) {
                      final child = children[index];
                      return _SubcategoryCard(
                        category: child,
                        onTap: () => onOpenSubcategory(child),
                      );
                    },
                    childCount: children.length,
                  ),
                  gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
                    crossAxisCount: columns,
                    crossAxisSpacing: 8,
                    mainAxisSpacing: 10,
                    childAspectRatio: .48,
                  ),
                );
              },
            ),
          ),
      ],
    );
  }
}

class _SubcategoryCard extends StatelessWidget {
  final CategoryModel category;
  final VoidCallback onTap;

  const _SubcategoryCard({
    required this.category,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(13),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(13),
        child: Container(
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(13),
            border: Border.all(color: const Color(0xFFE0E5EC)),
          ),
          child: Column(
            children: [
              Expanded(
                flex: 7,
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(5, 8, 5, 3),
                  child: _RemoteCategoryImage(
                    url: _categoryImage(category),
                    fallbackIcon: _fallbackCategoryIcon(category.name),
                  ),
                ),
              ),
              Expanded(
                flex: 3,
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(4, 2, 4, 8),
                  child: Center(
                    child: Text(
                      category.name,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      textAlign: TextAlign.center,
                      style: const TextStyle(
                        color: OvanieColors.navy,
                        fontSize: 10.7,
                        height: 1.08,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
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

class _RemoteCategoryImage extends StatelessWidget {
  final String url;
  final IconData fallbackIcon;

  const _RemoteCategoryImage({
    required this.url,
    required this.fallbackIcon,
  });

  @override
  Widget build(BuildContext context) {
    if (url.trim().isEmpty) {
      return Center(
        child: Icon(
          fallbackIcon,
          size: 36,
          color: const Color(0xFFAEB7C5),
        ),
      );
    }

    return Image.network(
      url,
      fit: BoxFit.contain,
      errorBuilder: (_, __, ___) => Center(
        child: Icon(
          fallbackIcon,
          size: 36,
          color: const Color(0xFFAEB7C5),
        ),
      ),
      loadingBuilder: (context, child, progress) {
        if (progress == null) return child;
        return const Center(
          child: SizedBox(
            width: 16,
            height: 16,
            child: CircularProgressIndicator(
              strokeWidth: 1.4,
              color: Color(0xFFD6DCE5),
            ),
          ),
        );
      },
    );
  }
}

String _categoryImage(CategoryModel category) {
  final direct = category.imageUrl.trim();
  if (direct.isNotEmpty) return direct;

  for (final child in category.children) {
    final childUrl = child.imageUrl.trim();
    if (childUrl.isNotEmpty) return childUrl;
  }
  return '';
}

IconData _fallbackCategoryIcon(String name) {
  final value = name.toLowerCase();
  if (value.contains('ciment') || value.contains('mortier')) {
    return Icons.inventory_2_outlined;
  }
  if (value.contains('fer') || value.contains('métal')) {
    return Icons.horizontal_rule_rounded;
  }
  if (value.contains('élect')) return Icons.electrical_services_outlined;
  if (value.contains('plomb') || value.contains('sanitaire')) {
    return Icons.plumbing_outlined;
  }
  if (value.contains('peint') || value.contains('enduit')) {
    return Icons.format_paint_outlined;
  }
  if (value.contains('outil')) return Icons.handyman_outlined;
  if (value.contains('sécur')) return Icons.health_and_safety_outlined;
  if (value.contains('bois')) return Icons.carpenter_outlined;
  if (value.contains('carrel')) return Icons.grid_on_rounded;
  if (value.contains('béton') || value.contains('gros')) {
    return Icons.view_in_ar_outlined;
  }
  return Icons.category_outlined;
}
