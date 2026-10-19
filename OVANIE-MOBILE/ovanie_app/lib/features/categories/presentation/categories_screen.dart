import 'dart:async';

import 'package:flutter/material.dart';

import '../../../core/network/api_client.dart';
import '../../../shared/widgets/api_error_card.dart';
import '../../auth/presentation/auth_branding.dart';
import '../../catalog/domain/catalog_navigation_store.dart';
import '../../home/data/marketplace_repository.dart';
import '../../search/presentation/search_screen.dart';
import '../domain/category_model.dart';
import 'catalog_widgets.dart';
import 'subcategory_screen.dart';

/// Écran Catégories OVANIE reconstruit selon la maquette mobile :
/// - logo, titre et recherche en haut ;
/// - catégories principales dans un rail vertical à gauche, avec leur visuel ;
/// - bannière puis sous-catégories de la catégorie sélectionnée à droite
///   (les images des sous-catégories sont celles ajoutées par l'administrateur) ;
/// - clic sur une sous-catégorie => écran Sous-catégorie sans quitter
///   l'onglet Catégories du MainShell.
///
/// Les catégories proviennent uniquement de Laravel via
/// `/marketplace/categories`.
class CategoriesScreen extends StatefulWidget {
  final String? initialCategorySlug;
  final bool showBackButton;
  final CatalogNavigationStore? navigationStore;

  const CategoriesScreen({super.key, this.initialCategorySlug, this.showBackButton = false, this.navigationStore});

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
      final roots = categoriesInDisplayOrder(await _repository.getRootCategories(limit: 50));
      if (!mounted) return;

      CategoryModel? selectedRoot;
      CategoryModel? selectedSubcategory;
      final requestedSlug = widget.initialCategorySlug?.trim();

      if (requestedSlug != null && requestedSlug.isNotEmpty) {
        final target = _repository.findCategoryInRoots(roots, slug: requestedSlug);
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
    Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => const SearchScreen()));
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

    return OvanieAuthTheme(
      child: Material(
        color: Colors.white,
        child: SafeArea(
          bottom: false,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(15, 12, 15, 10),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        if (widget.showBackButton)
                          Padding(
                            padding: const EdgeInsets.only(right: 6),
                            child: InkResponse(
                              onTap: () => Navigator.of(context).maybePop(),
                              radius: 22,
                              child: const Padding(
                                padding: EdgeInsets.all(4),
                                child: Icon(
                                  Icons.arrow_back_ios_new_rounded,
                                  size: 20,
                                  color: authNavy,
                                  semanticLabel: 'Retour',
                                ),
                              ),
                            ),
                          ),
                        const OvanieLogo(width: 112),
                      ],
                    ),
                    const SizedBox(height: 14),
                    const Text(
                      'Catégories',
                      style: TextStyle(color: authNavy, fontSize: 23, height: 1.1, fontWeight: FontWeight.w800),
                    ),
                    const SizedBox(height: 10),
                    CatalogSearchField(onTap: _openSearch),
                  ],
                ),
              ),
              Expanded(child: _buildBody()),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildBody() {
    if (_loading) {
      return const Center(child: CircularProgressIndicator(color: authOrange));
    }

    if (_error != null) {
      return ListView(
        padding: const EdgeInsets.all(18),
        children: [
          const SizedBox(height: 60),
          ApiErrorCard(message: ApiClient.friendlyError(_error!), onRetry: _loadCategories),
        ],
      );
    }

    if (_roots.isEmpty) {
      return const Center(
        child: CatalogEmptyState(
          icon: Icons.grid_view_outlined,
          message: 'Aucune catégorie disponible pour le moment.',
        ),
      );
    }

    final selected = _selectedRoot ?? _roots.first;

    return LayoutBuilder(
      builder: (context, constraints) {
        final railWidth = (constraints.maxWidth * .33).clamp(112.0, 156.0).toDouble();

        return Row(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            SizedBox(
              width: railWidth,
              child: _CategoryRail(categories: _roots, selected: selected, onSelect: _selectRoot),
            ),
            Expanded(
              child: _SubcategoriesPanel(
                key: ValueKey<int>(selected.id),
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

/// Rail des catégories principales : pastille ronde avec le visuel officiel
/// et nom sur deux lignes. La catégorie ouverte est blanche, barre orange.
class _CategoryRail extends StatelessWidget {
  final List<CategoryModel> categories;
  final CategoryModel selected;
  final ValueChanged<CategoryModel> onSelect;

  const _CategoryRail({required this.categories, required this.selected, required this.onSelect});

  @override
  Widget build(BuildContext context) {
    return ColoredBox(
      color: const Color(0xFFF3F5F9),
      child: ListView.builder(
        padding: const EdgeInsets.only(bottom: 18),
        physics: const BouncingScrollPhysics(),
        itemCount: categories.length,
        itemBuilder: (context, index) {
          final category = categories[index];
          return _CategoryRailTile(
            category: category,
            selected: selected.id == category.id,
            onTap: () => onSelect(category),
          );
        },
      ),
    );
  }
}

class _CategoryRailTile extends StatelessWidget {
  final CategoryModel category;
  final bool selected;
  final VoidCallback onTap;

  const _CategoryRailTile({required this.category, required this.selected, required this.onTap});

  @override
  Widget build(BuildContext context) {
    final style = TextStyle(
      color: authNavy,
      fontFamily: authFont,
      fontSize: 10.5,
      height: 1.28,
      fontWeight: selected ? FontWeight.w800 : FontWeight.w500,
    );

    return Semantics(
      button: true,
      selected: selected,
      child: Material(
        color: selected ? Colors.white : Colors.transparent,
        child: InkWell(
          onTap: onTap,
          child: Container(
            constraints: const BoxConstraints(minHeight: 56),
            decoration: BoxDecoration(
              border: Border(left: BorderSide(color: selected ? authOrange : Colors.transparent, width: 3)),
            ),
            padding: const EdgeInsets.fromLTRB(6, 8, 3, 8),
            child: Row(
              children: [
                ClipOval(
                  child: SizedBox(
                    width: 38,
                    height: 38,
                    child: CatalogImage(
                      urls: rootCategoryImages(category),
                      placeholder: const ColoredBox(
                        color: Color(0xFFE6EAF1),
                        child: Icon(Icons.category_outlined, size: 18, color: Color(0xFF9AA3B5)),
                      ),
                    ),
                  ),
                ),
                const SizedBox(width: 6),
                Expanded(
                  child: LayoutBuilder(
                    builder: (context, box) => Text(
                      category.name,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: style.copyWith(
                        fontSize: fitTextFontSize(
                          category.name,
                          // Marge d'un point : l'arrondi du moteur de texte ne
                          // doit pas faire basculer le dernier caractère.
                          box.maxWidth - 1,
                          DefaultTextStyle.of(context).style.merge(style),
                          textScaler: MediaQuery.textScalerOf(context),
                        ),
                      ),
                    ),
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

/// Partie droite : bannière de la catégorie puis grille des sous-catégories.
class _SubcategoriesPanel extends StatelessWidget {
  final CategoryModel category;
  final ValueChanged<CategoryModel> onOpenSubcategory;

  const _SubcategoriesPanel({super.key, required this.category, required this.onOpenSubcategory});

  @override
  Widget build(BuildContext context) {
    final children = category.children;

    return CustomScrollView(
      physics: const BouncingScrollPhysics(),
      slivers: [
        SliverPadding(
          padding: const EdgeInsets.fromLTRB(8, 3, 9, 0),
          sliver: SliverToBoxAdapter(child: _CategoryBanner(category: category)),
        ),
        if (children.isEmpty)
          const SliverFillRemaining(
            hasScrollBody: false,
            child: Center(
              child: CatalogEmptyState(
                icon: Icons.grid_view_outlined,
                discSize: 78,
                message: 'Aucune sous-catégorie disponible pour le moment.',
              ),
            ),
          )
        else
          SliverPadding(
            padding: const EdgeInsets.fromLTRB(8, 12, 9, 22),
            sliver: SliverLayoutBuilder(
              builder: (context, constraints) {
                final width = constraints.crossAxisExtent;
                final columns = width >= 520 ? 5 : (width >= 380 ? 4 : 3);
                const gap = 7.0;
                final tile = (width - gap * (columns - 1)) / columns;
                return SliverGrid(
                  delegate: SliverChildBuilderDelegate((context, index) {
                    final child = children[index];
                    return _SubcategoryCard(category: child, onTap: () => onOpenSubcategory(child));
                  }, childCount: children.length),
                  gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
                    crossAxisCount: columns,
                    crossAxisSpacing: gap,
                    mainAxisSpacing: 6,
                    mainAxisExtent: tile * 1.1 + 34,
                  ),
                );
              },
            ),
          ),
      ],
    );
  }
}

class _CategoryBanner extends StatelessWidget {
  final CategoryModel category;

  const _CategoryBanner({required this.category});

  @override
  Widget build(BuildContext context) {
    return ClipRRect(
      borderRadius: BorderRadius.circular(9),
      child: AspectRatio(
        aspectRatio: 562 / 293,
        child: Stack(
          fit: StackFit.expand,
          children: [
            CatalogImage(
              urls: rootCategoryImages(category),
              placeholder: const DecoratedBox(
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                    colors: [Color(0xFF16307A), authNavy],
                  ),
                ),
              ),
            ),
            const DecoratedBox(
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topCenter,
                  end: Alignment.bottomCenter,
                  colors: [Color(0x00000000), Color(0x26000000), Color(0xB3000000)],
                  stops: [.3, .55, 1],
                ),
              ),
            ),
            Positioned(
              left: 11,
              right: 11,
              bottom: 9,
              child: Text(
                category.name,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 15,
                  height: 1.2,
                  fontWeight: FontWeight.w800,
                  shadows: [Shadow(color: Color(0x80000000), blurRadius: 6, offset: Offset(0, 1))],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Sous-catégorie : vignette (image ajoutée par l'administrateur, sinon un
/// fond neutre) et nom centré dessous.
class _SubcategoryCard extends StatelessWidget {
  final CategoryModel category;
  final VoidCallback onTap;

  const _SubcategoryCard({required this.category, required this.onTap});

  @override
  Widget build(BuildContext context) {
    final image = category.imageUrl.trim();

    return Semantics(
      button: true,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(8),
        child: Column(
          children: [
            Expanded(
              child: ClipRRect(
                borderRadius: BorderRadius.circular(8),
                child: ColoredBox(
                  color: catalogSoft,
                  child: CatalogImage(
                    urls: image.isEmpty ? const <String>[] : <String>[image],
                    placeholder: const Center(child: Icon(Icons.image_outlined, size: 24, color: Color(0xFFC2C9D6))),
                  ),
                ),
              ),
            ),
            SizedBox(
              height: 34,
              child: Padding(
                padding: const EdgeInsets.only(top: 5),
                child: Text(
                  category.name,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  textAlign: TextAlign.center,
                  style: const TextStyle(color: authNavy, fontSize: 9.8, height: 1.2, fontWeight: FontWeight.w500),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
