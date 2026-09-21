import 'package:flutter/material.dart';

import 'categories_screen.dart';

/// Route dédiée vers une catégorie racine OVANIE.
///
/// La catégorie sélectionnée s'affiche dans le rail gauche et ses
/// sous-catégories apparaissent à droite, conformément à la maquette.
class CategoryScreen extends StatelessWidget {
  final int? categoryId;
  final String categorySlug;
  final String? categoryName;

  const CategoryScreen({
    super.key,
    this.categoryId,
    required this.categorySlug,
    this.categoryName,
  });

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      body: CategoriesScreen(
        initialCategorySlug: categorySlug,
        showBackButton: true,
      ),
    );
  }
}
