import 'package:flutter/material.dart';

import '../../app/theme.dart';
import '../../features/search/presentation/search_screen.dart';

/// Barre de recherche globale OVANIE.
///
/// Elle reste volontairement légère : un appui ouvre l'écran de recherche
/// complet afin d'éviter de dupliquer la logique de recherche sur chaque page.
class OvanieSearchBar extends StatelessWidget {
  final String hintText;
  final EdgeInsetsGeometry margin;
  final double height;

  const OvanieSearchBar({
    super.key,
    this.hintText = 'Rechercher un produit…',
    this.margin = EdgeInsets.zero,
    this.height = 46,
  });

  void _openSearch(BuildContext context) {
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => const SearchScreen(),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: margin,
      child: Material(
        color: Colors.white,
        borderRadius: BorderRadius.circular(13),
        child: InkWell(
          onTap: () => _openSearch(context),
          borderRadius: BorderRadius.circular(13),
          child: Container(
            height: height,
            padding: const EdgeInsets.symmetric(horizontal: 13),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(13),
              border: Border.all(color: OvanieColors.border),
            ),
            child: Row(
              children: [
                const Icon(
                  Icons.search_rounded,
                  size: 21,
                  color: OvanieColors.muted,
                ),
                const SizedBox(width: 9),
                Expanded(
                  child: Text(
                    hintText,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: OvanieColors.muted,
                      fontSize: 12.5,
                      fontWeight: FontWeight.w500,
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
