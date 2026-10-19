import 'package:flutter/material.dart';

import '../../../core/config/app_config.dart';
import '../../../core/utils/category_media.dart';
import '../../auth/presentation/auth_branding.dart';
import '../../home/data/marketplace_repository.dart';
import '../domain/category_model.dart';

/// Éléments communs aux écrans Catégories et Liste de produits.

const Color catalogSoft = Color(0xFFF2F4F8);
const Color catalogLine = Color(0xFFE4E8EF);
const Color catalogMuted = Color(0xFF5B6785);

/// Ordre d'affichage des catégories principales, celui de la maquette et
/// du site. Une catégorie ajoutée plus tard par l'administrateur se place à
/// la suite, dans l'ordre envoyé par le serveur.
const List<String> _displayOrder = <String>[
  'gros-oeuvre',
  'ecologique',
  'outillage',
  'finition',
  'energie-solaire',
  'electricite',
  'recondition',
  'carte-cadeau',
];

List<CategoryModel> categoriesInDisplayOrder(List<CategoryModel> roots) {
  int rank(CategoryModel category) {
    final index = _displayOrder.indexWhere(category.slug.contains);
    return index < 0 ? _displayOrder.length : index;
  }

  final indexed = roots.indexed.toList()
    ..sort((a, b) {
      final byRank = rank(a.$2).compareTo(rank(b.$2));
      return byRank != 0 ? byRank : a.$1.compareTo(b.$1);
    });
  return [for (final entry in indexed) entry.$2];
}

/// Visuels d'une catégorie principale : l'image choisie par l'administrateur
/// d'abord, puis le visuel officiel du projet envoyé par le serveur, puis les
/// noms de fichiers historiques.
List<String> rootCategoryImages(CategoryModel category) {
  final urls = <String>[];
  void add(String value) {
    final url = value.trim();
    if (url.isNotEmpty && !urls.contains(url)) urls.add(url);
  }

  add(category.imageUrl);
  final icon = category.icon.trim();
  if (icon.startsWith('http') || icon.startsWith('/')) {
    add(AppConfig.normalizeMediaUrl(icon));
  }
  categoryWebImageCandidates(category.slug).forEach(add);
  return urls;
}

/// Image distante qui essaie chaque adresse dans l'ordre, puis affiche
/// [placeholder] si aucune ne répond.
class CatalogImage extends StatelessWidget {
  const CatalogImage({super.key, required this.urls, required this.placeholder, this.fit = BoxFit.cover});

  final List<String> urls;
  final Widget placeholder;
  final BoxFit fit;

  Widget _build(int index) {
    if (index >= urls.length) return placeholder;
    return Image.network(
      urls[index],
      fit: fit,
      width: double.infinity,
      height: double.infinity,
      gaplessPlayback: true,
      excludeFromSemantics: true,
      errorBuilder: (_, __, ___) => _build(index + 1),
      frameBuilder: (context, child, frame, sync) {
        if (sync || frame != null) return child;
        return const ColoredBox(color: catalogSoft);
      },
    );
  }

  @override
  Widget build(BuildContext context) => _build(0);
}

/// Champ de recherche des maquettes : un bouton qui ouvre l'écran Recherche.
class CatalogSearchField extends StatelessWidget {
  const CatalogSearchField({super.key, required this.onTap, this.filled = false});

  final VoidCallback onTap;

  /// Fond gris sans bordure (liste de produits) ou blanc bordé (catégories).
  final bool filled;

  @override
  Widget build(BuildContext context) {
    final radius = BorderRadius.circular(filled ? 10 : 12);
    return Semantics(
      button: true,
      label: 'Rechercher un produit, une marque',
      child: Material(
        color: filled ? const Color(0xFFEEF1F5) : Colors.white,
        borderRadius: radius,
        child: InkWell(
          onTap: onTap,
          borderRadius: radius,
          child: Container(
            height: 42,
            padding: const EdgeInsets.symmetric(horizontal: 12),
            decoration: BoxDecoration(
              borderRadius: radius,
              border: filled ? null : Border.all(color: const Color(0xFFD9DEE8)),
            ),
            child: const Row(
              children: [
                Icon(Icons.search_rounded, size: 21, color: Color(0xFF3F4B68)),
                SizedBox(width: 10),
                Expanded(
                  child: ExcludeSemantics(
                    child: Text(
                      'Rechercher un produit, une marque...',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(color: Color(0xFF7A849C), fontSize: 13, fontWeight: FontWeight.w500),
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

/// État vide : pastille grise, pictogramme et message centré.
class CatalogEmptyState extends StatelessWidget {
  const CatalogEmptyState({super.key, required this.icon, required this.message, this.discSize = 112});

  final IconData icon;
  final String message;
  final double discSize;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 22, vertical: 24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: discSize,
            height: discSize,
            decoration: const BoxDecoration(color: Color(0xFFEEF0F4), shape: BoxShape.circle),
            child: Icon(icon, size: discSize * .44, color: const Color(0xFFA9AFBC)),
          ),
          const SizedBox(height: 20),
          Text(
            message,
            textAlign: TextAlign.center,
            style: const TextStyle(color: catalogMuted, fontSize: 13.5, height: 1.4, fontWeight: FontWeight.w500),
          ),
        ],
      ),
    );
  }
}

/// Plus grande taille de police (entre [min] et celle de [style]) pour que
/// [text] tienne en entier sur [maxLines] lignes de largeur [maxWidth], sans
/// couper un mot. [textScaler] est la taille de texte réglée sur le téléphone.
double fitTextFontSize(
  String text,
  double maxWidth,
  TextStyle style, {
  int maxLines = 2,
  double min = 7.5,
  TextScaler textScaler = TextScaler.noScaling,
}) {
  var size = style.fontSize ?? 12;
  final words = text.split(RegExp(r'\s+')).where((word) => word.isNotEmpty).toList();

  double widthOf(String value, double fontSize, {int? lines}) {
    final painter = TextPainter(
      text: TextSpan(
        text: value,
        style: style.copyWith(fontSize: fontSize),
      ),
      textDirection: TextDirection.ltr,
      textScaler: textScaler,
      maxLines: lines,
    )..layout(maxWidth: lines == null ? double.infinity : maxWidth);
    final result = lines != null && painter.didExceedMaxLines ? double.infinity : painter.width;
    painter.dispose();
    return result;
  }

  while (size > min) {
    final wordsFit = words.every((word) => widthOf(word, size) <= maxWidth);
    if (wordsFit && widthOf(text, size, lines: maxLines) <= maxWidth) break;
    size -= .5;
  }
  return size;
}

/// Poignée grise en tête des fenêtres du bas.
class CatalogSheetHandle extends StatelessWidget {
  const CatalogSheetHandle({super.key});

  @override
  Widget build(BuildContext context) => Center(
    child: Container(
      width: 38,
      height: 4,
      margin: const EdgeInsets.only(top: 9, bottom: 12),
      decoration: BoxDecoration(color: const Color(0xFFD9DDE5), borderRadius: BorderRadius.circular(99)),
    ),
  );
}

/// Fenêtre du bas des maquettes : poignée, titre, croix de fermeture.
Future<T?> showCatalogSheet<T>(
  BuildContext context, {
  required String title,
  required Widget Function(BuildContext context) builder,
}) {
  return showModalBottomSheet<T>(
    context: context,
    isScrollControlled: true,
    backgroundColor: Colors.white,
    barrierColor: const Color(0x99000000),
    constraints: BoxConstraints(maxHeight: MediaQuery.sizeOf(context).height * .8),
    shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
    builder: (context) => OvanieAuthTheme(
      child: SafeArea(
        top: false,
        child: Padding(
          padding: EdgeInsets.fromLTRB(18, 0, 18, 14 + MediaQuery.viewInsetsOf(context).bottom),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const CatalogSheetHandle(),
              Row(
                children: [
                  Expanded(
                    child: Text(
                      title,
                      style: const TextStyle(color: authNavy, fontSize: 17.5, fontWeight: FontWeight.w800),
                    ),
                  ),
                  IconButton(
                    onPressed: () => Navigator.of(context).pop(),
                    tooltip: 'Fermer',
                    visualDensity: VisualDensity.compact,
                    icon: const Icon(Icons.close_rounded, color: authNavy, size: 24),
                  ),
                ],
              ),
              Flexible(child: SingleChildScrollView(child: builder(context))),
            ],
          ),
        ),
      ),
    ),
  );
}

/// Ligne à bouton radio des fenêtres du bas (tri, marque, catégorie).
class CatalogRadioRow extends StatelessWidget {
  const CatalogRadioRow({super.key, required this.label, required this.selected, required this.onTap, this.leading});

  /// Vignette affichée avant le libellé (catégories).
  final Widget? leading;
  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      button: true,
      selected: selected,
      child: InkWell(
        onTap: onTap,
        child: ConstrainedBox(
          constraints: const BoxConstraints(minHeight: 46),
          child: Row(
            children: [
              if (leading != null) ...[leading!, const SizedBox(width: 12)],
              Expanded(
                child: Padding(
                  padding: const EdgeInsets.symmetric(vertical: 8),
                  child: Text(
                    label,
                    style: const TextStyle(color: authNavy, fontSize: 14, fontWeight: FontWeight.w500),
                  ),
                ),
              ),
              Container(
                width: 20,
                height: 20,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  border: Border.all(color: selected ? authOrange : const Color(0xFFA4ABBA), width: 1.6),
                ),
                child: selected
                    ? Container(
                        width: 10,
                        height: 10,
                        decoration: const BoxDecoration(color: authOrange, shape: BoxShape.circle),
                      )
                    : null,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Fenêtre de choix unique : une ligne à bouton radio par option.
Future<T?> showCatalogChoiceSheet<T>(
  BuildContext context, {
  required String title,
  required List<(T, String)> options,
  required T? selected,
}) {
  return showCatalogSheet<T>(
    context,
    title: title,
    builder: (context) => Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        for (var i = 0; i < options.length; i++) ...[
          if (i > 0) const Divider(height: 1, thickness: 1, color: catalogLine),
          CatalogRadioRow(
            label: options[i].$2,
            selected: options[i].$1 == selected,
            onTap: () => Navigator.of(context).pop(options[i].$1),
          ),
        ],
      ],
    ),
  );
}

/// Pastille de choix : bleu nuit quand elle est active, claire sinon.
class CatalogPill extends StatelessWidget {
  const CatalogPill({
    super.key,
    required this.label,
    required this.selected,
    required this.onTap,
    this.white = false,
    this.trailing,
    this.horizontal = 16,
  });

  final String label;
  final bool selected;
  final VoidCallback onTap;

  /// Fond blanc bordé (fenêtre de filtres) au lieu du gris clair.
  final bool white;
  final IconData? trailing;
  final double horizontal;

  @override
  Widget build(BuildContext context) {
    final foreground = selected ? Colors.white : const Color(0xFF2B3655);
    return Semantics(
      button: true,
      selected: selected,
      child: Material(
        color: selected ? authNavy : (white ? Colors.white : const Color(0xFFF2F4F8)),
        shape: StadiumBorder(
          side: BorderSide(color: selected ? authNavy : (white ? const Color(0xFFD9DEE8) : const Color(0xFFE3E7EE))),
        ),
        child: InkWell(
          onTap: onTap,
          customBorder: const StadiumBorder(),
          child: Padding(
            padding: EdgeInsets.symmetric(horizontal: horizontal, vertical: 8),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Flexible(
                  child: Text(
                    label,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: foreground,
                      fontSize: 12.5,
                      fontWeight: selected ? FontWeight.w700 : FontWeight.w500,
                    ),
                  ),
                ),
                if (trailing != null) ...[const SizedBox(width: 4), Icon(trailing, size: 14, color: foreground)],
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// Bouton bordé « Trier » / « Filtres », avec le nombre de filtres actifs.
class CatalogToolbarButton extends StatelessWidget {
  const CatalogToolbarButton({
    super.key,
    required this.icon,
    required this.label,
    required this.onTap,
    this.badge = 0,
    this.strong = false,
  });

  /// Bordure bleu nuit (pages d'offres) au lieu du gris clair.
  final bool strong;
  final IconData icon;
  final String label;
  final VoidCallback onTap;
  final int badge;

  @override
  Widget build(BuildContext context) {
    return Stack(
      clipBehavior: Clip.none,
      children: [
        Material(
          color: Colors.white,
          borderRadius: BorderRadius.circular(8),
          child: InkWell(
            onTap: onTap,
            borderRadius: BorderRadius.circular(8),
            child: Container(
              height: 34,
              padding: const EdgeInsets.symmetric(horizontal: 11),
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(8),
                border: Border.all(color: strong ? authNavy : const Color(0xFFD5DBE5), width: strong ? 1.2 : 1),
              ),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Icon(icon, size: 18, color: authNavy),
                  const SizedBox(width: 6),
                  Text(
                    label,
                    style: const TextStyle(color: authNavy, fontSize: 12.5, fontWeight: FontWeight.w600),
                  ),
                ],
              ),
            ),
          ),
        ),
        if (badge > 0)
          Positioned(
            right: -5,
            top: -6,
            child: Container(
              constraints: const BoxConstraints(minWidth: 16, minHeight: 16),
              padding: const EdgeInsets.symmetric(horizontal: 3),
              alignment: Alignment.center,
              decoration: BoxDecoration(color: authOrange, borderRadius: BorderRadius.circular(99)),
              child: Text(
                '$badge',
                semanticsLabel: '$badge filtre${badge > 1 ? 's' : ''} actif${badge > 1 ? 's' : ''}',
                style: const TextStyle(color: Colors.white, fontSize: 9, height: 1, fontWeight: FontWeight.w800),
              ),
            ),
          ),
      ],
    );
  }
}

/// Liste vide à cause des filtres ou de la recherche : loupe barrée, phrase
/// en gras et bouton « Effacer les filtres ».
class CatalogNoResult extends StatelessWidget {
  const CatalogNoResult({super.key, required this.message, this.onClear});

  final String message;
  final VoidCallback? onClear;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 22, vertical: 24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 106,
            height: 106,
            decoration: const BoxDecoration(color: Color(0xFFEEF0F4), shape: BoxShape.circle),
            child: const Icon(Icons.search_off_rounded, size: 50, color: Color(0xFF7B8296)),
          ),
          const SizedBox(height: 22),
          Text(
            message,
            textAlign: TextAlign.center,
            style: const TextStyle(color: authNavy, fontSize: 15.5, height: 1.3, fontWeight: FontWeight.w700),
          ),
          if (onClear != null) ...[
            const SizedBox(height: 18),
            OutlinedButton(
              onPressed: onClear,
              style: OutlinedButton.styleFrom(
                foregroundColor: authNavy,
                side: const BorderSide(color: Color(0xFFD5DBE5)),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                padding: const EdgeInsets.symmetric(horizontal: 26, vertical: 11),
              ),
              child: const Text(
                'Effacer les filtres',
                style: TextStyle(fontFamily: authFont, fontSize: 13.5, fontWeight: FontWeight.w600),
              ),
            ),
          ],
        ],
      ),
    );
  }
}

/// Filtres choisis dans la fenêtre « Filtrer le catalogue ».
class CatalogFilterSelection {
  const CatalogFilterSelection({this.stock, this.brand, this.minPrice, this.maxPrice});

  final String? stock;
  final String? brand;
  final double? minPrice;
  final double? maxPrice;

  /// Nombre de filtres actifs (la fourchette de prix compte pour un).
  int get count => (stock != null ? 1 : 0) + (brand != null ? 1 : 0) + (minPrice != null || maxPrice != null ? 1 : 0);

  bool get isEmpty => count == 0;
}

/// Fenêtre « Filtrer le catalogue » : disponibilité, marque, fourchette de prix.
/// Renvoie les filtres à appliquer, ou `null` si la fenêtre est fermée.
Future<CatalogFilterSelection?> showCatalogFilterSheet(
  BuildContext context, {
  required CatalogFilters? filters,
  required CatalogFilterSelection current,
}) {
  return showCatalogSheet<CatalogFilterSelection>(
    context,
    title: 'Filtrer le catalogue',
    builder: (context) => _CatalogFilterForm(filters: filters, current: current),
  );
}

class _CatalogFilterForm extends StatefulWidget {
  const _CatalogFilterForm({required this.filters, required this.current});

  final CatalogFilters? filters;
  final CatalogFilterSelection current;

  @override
  State<_CatalogFilterForm> createState() => _CatalogFilterFormState();
}

class _CatalogFilterFormState extends State<_CatalogFilterForm> {
  late String? _stock = widget.current.stock;
  late String? _brand = widget.current.brand;
  late final TextEditingController _min = TextEditingController(text: _priceText(widget.current.minPrice));
  late final TextEditingController _max = TextEditingController(text: _priceText(widget.current.maxPrice));
  String? _priceError;

  static String _priceText(double? value) => value == null ? '' : value.round().toString();

  static double? _parse(String raw) {
    final digits = raw.replaceAll(RegExp(r'[^0-9]'), '');
    return digits.isEmpty ? null : double.tryParse(digits);
  }

  @override
  void dispose() {
    _min.dispose();
    _max.dispose();
    super.dispose();
  }

  Future<void> _chooseBrand() async {
    final brands = widget.filters?.brands ?? const <String>[];
    final choice = await showCatalogChoiceSheet<String>(
      context,
      title: 'Marque',
      options: [('', 'Toutes les marques'), for (final brand in brands) (brand, brand)],
      selected: _brand ?? '',
    );
    if (choice == null || !mounted) return;
    setState(() => _brand = choice.isEmpty ? null : choice);
  }

  void _apply() {
    final min = _parse(_min.text), max = _parse(_max.text);
    if (min != null && max != null && min > max) {
      setState(() => _priceError = 'Le prix minimum dépasse le prix maximum.');
      return;
    }
    Navigator.of(context).pop(CatalogFilterSelection(stock: _stock, brand: _brand, minPrice: min, maxPrice: max));
  }

  InputDecoration _priceDecoration(String hint) {
    OutlineInputBorder border(Color color) => OutlineInputBorder(
      borderRadius: BorderRadius.circular(8),
      borderSide: BorderSide(color: color),
    );
    return InputDecoration(
      hintText: hint,
      hintStyle: const TextStyle(color: Color(0xFF8A93A8), fontSize: 13, fontWeight: FontWeight.w400),
      isDense: true,
      filled: true,
      fillColor: Colors.white,
      contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 13),
      enabledBorder: border(const Color(0xFFD9DEE8)),
      focusedBorder: border(authNavy),
    );
  }

  @override
  Widget build(BuildContext context) {
    final apiAvailability = widget.filters?.availability ?? const <CatalogOption>[];
    final availability = apiAvailability.isNotEmpty
        ? apiAvailability
        : const <CatalogOption>[CatalogOption(value: 'in-stock', label: 'En stock')];
    const heading = TextStyle(color: authNavy, fontSize: 14, fontWeight: FontWeight.w700);
    const fieldText = TextStyle(color: authNavy, fontSize: 13.5, fontWeight: FontWeight.w500, fontFamily: authFont);

    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const SizedBox(height: 6),
        const Text('Disponibilité', style: heading),
        const SizedBox(height: 9),
        Wrap(
          spacing: 6,
          runSpacing: 8,
          children: [
            CatalogPill(
              label: 'Toutes',
              white: true,
              horizontal: 22,
              selected: _stock == null,
              onTap: () => setState(() => _stock = null),
            ),
            for (final option in availability)
              CatalogPill(
                label: option.label,
                white: true,
                horizontal: 22,
                selected: _stock == option.value,
                onTap: () => setState(() => _stock = option.value),
              ),
          ],
        ),
        const SizedBox(height: 14),
        const Divider(height: 1, thickness: 1, color: catalogLine),
        const SizedBox(height: 12),
        const Text('Marque', style: heading),
        const SizedBox(height: 8),
        Semantics(
          button: true,
          child: InkWell(
            onTap: _chooseBrand,
            borderRadius: BorderRadius.circular(8),
            child: Container(
              height: 44,
              padding: const EdgeInsets.only(left: 12, right: 8),
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(8),
                border: Border.all(color: const Color(0xFFD9DEE8)),
              ),
              child: Row(
                children: [
                  Expanded(
                    child: Text(
                      _brand ?? 'Toutes les marques',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: fieldText,
                    ),
                  ),
                  const Icon(Icons.chevron_right_rounded, color: authNavy, size: 22),
                ],
              ),
            ),
          ),
        ),
        const SizedBox(height: 14),
        const Divider(height: 1, thickness: 1, color: catalogLine),
        const SizedBox(height: 12),
        const Text('Prix', style: heading),
        const SizedBox(height: 8),
        Row(
          children: [
            Expanded(
              child: TextField(
                controller: _min,
                keyboardType: TextInputType.number,
                style: fieldText,
                decoration: _priceDecoration('Minimum FCFA'),
                onChanged: (_) => _priceError == null ? null : setState(() => _priceError = null),
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: TextField(
                controller: _max,
                keyboardType: TextInputType.number,
                style: fieldText,
                decoration: _priceDecoration('Maximum FCFA'),
                onChanged: (_) => _priceError == null ? null : setState(() => _priceError = null),
              ),
            ),
          ],
        ),
        if (_priceError != null)
          Padding(
            padding: const EdgeInsets.only(top: 6),
            child: Text(
              _priceError!,
              style: const TextStyle(color: authDanger, fontSize: 12, fontWeight: FontWeight.w500),
            ),
          ),
        const SizedBox(height: 16),
        Row(
          children: [
            Expanded(
              flex: 10,
              child: Align(
                alignment: Alignment.centerLeft,
                child: TextButton(
                  onPressed: () => Navigator.of(context).pop(const CatalogFilterSelection()),
                  style: TextButton.styleFrom(
                    foregroundColor: authNavy,
                    padding: const EdgeInsets.symmetric(horizontal: 2),
                  ),
                  child: const Text(
                    'Effacer les filtres',
                    style: TextStyle(
                      fontFamily: authFont,
                      fontSize: 13,
                      fontWeight: FontWeight.w500,
                      decoration: TextDecoration.underline,
                    ),
                  ),
                ),
              ),
            ),
            Expanded(
              flex: 11,
              child: OvaniePrimaryButton(label: 'Afficher les résultats', height: 46, onPressed: _apply),
            ),
          ],
        ),
      ],
    );
  }
}
