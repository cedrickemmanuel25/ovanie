import 'package:flutter/material.dart';

/// Liste de choix unique de l'application Vendeur : même champ, même feuille
/// de sélection partout (catégories, unités, communes, quartiers…).
const _ink = Color(0xFF0A2360);
const _muted = Color(0xFF5B6B86);
const _line = Color(0xFFD5DDE8);
const _accent = Color(0xFF0B4FC4);

Future<T?> showVendorSelect<T>(
  BuildContext context, {
  required String title,
  required List<T> items,
  required String Function(T) label,
  T? selected,
  bool? searchable,
}) {
  FocusManager.instance.primaryFocus?.unfocus();
  return showModalBottomSheet<T>(
    context: context,
    isScrollControlled: true,
    useSafeArea: true,
    backgroundColor: Colors.white,
    shape: const RoundedRectangleBorder(
      borderRadius: BorderRadius.vertical(top: Radius.circular(18)),
    ),
    builder: (_) => _VendorSelectSheet<T>(
      title: title,
      items: items,
      label: label,
      selected: selected,
      searchable: searchable ?? items.length > 8,
    ),
  );
}

class VendorSelectField<T> extends StatelessWidget {
  const VendorSelectField({
    super.key,
    required this.title,
    required this.items,
    required this.label,
    required this.onChanged,
    this.value,
    this.hint = 'Choisir',
    this.enabled = true,
    this.displayText,
  });

  /// Titre de la feuille de sélection.
  final String title;
  final List<T> items;
  final String Function(T) label;
  final ValueChanged<T> onChanged;
  final T? value;
  final String hint;
  final bool enabled;

  /// Texte affiché à la place de la valeur (ex. valeur saisie hors liste).
  final String? displayText;

  @override
  Widget build(BuildContext context) {
    final text = displayText ?? (value == null ? null : label(value as T));
    final active = enabled && items.isNotEmpty;
    return Semantics(
      button: true,
      label: title,
      value: text,
      child: InkWell(
        borderRadius: BorderRadius.circular(10),
        onTap: active
            ? () async {
                final picked = await showVendorSelect<T>(
                  context,
                  title: title,
                  items: items,
                  label: label,
                  selected: value,
                );
                if (picked != null) onChanged(picked);
              }
            : null,
        child: Container(
          constraints: const BoxConstraints(minHeight: 48),
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
          decoration: BoxDecoration(
            color: active ? Colors.white : const Color(0xFFF3F5F9),
            borderRadius: BorderRadius.circular(10),
            border: Border.all(color: _line),
          ),
          child: Row(
            children: [
              Expanded(
                child: Text(
                  text == null || text.trim().isEmpty ? hint : text,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: text == null || text.trim().isEmpty
                        ? const Color(0xFF98A4B8)
                        : _ink,
                    fontSize: 15,
                  ),
                ),
              ),
              Icon(
                Icons.expand_more_rounded,
                color: active ? _muted : const Color(0xFFB5BFCE),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _VendorSelectSheet<T> extends StatefulWidget {
  const _VendorSelectSheet({
    required this.title,
    required this.items,
    required this.label,
    required this.selected,
    required this.searchable,
  });
  final String title;
  final List<T> items;
  final String Function(T) label;
  final T? selected;
  final bool searchable;

  @override
  State<_VendorSelectSheet<T>> createState() => _VendorSelectSheetState<T>();
}

class _VendorSelectSheetState<T> extends State<_VendorSelectSheet<T>> {
  String _query = '';

  String _plain(String value) => value
      .toLowerCase()
      .replaceAll(RegExp('[éèêë]'), 'e')
      .replaceAll(RegExp('[àâä]'), 'a')
      .replaceAll(RegExp('[îï]'), 'i')
      .replaceAll(RegExp('[ôö]'), 'o')
      .replaceAll(RegExp('[ùûü]'), 'u')
      .replaceAll('ç', 'c');

  @override
  Widget build(BuildContext context) {
    final q = _plain(_query.trim());
    final items = q.isEmpty
        ? widget.items
        : widget.items.where((e) => _plain(widget.label(e)).contains(q)).toList();
    final maxHeight = MediaQuery.sizeOf(context).height * .75;
    return Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
      child: ConstrainedBox(
        constraints: BoxConstraints(maxHeight: maxHeight),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 14, 8, 4),
              child: Row(
                children: [
                  Expanded(
                    child: Text(
                      widget.title,
                      style: const TextStyle(
                        color: _ink,
                        fontSize: 17,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                  IconButton(
                    tooltip: 'Fermer',
                    onPressed: () => Navigator.pop(context),
                    icon: const Icon(Icons.close_rounded, color: _muted),
                  ),
                ],
              ),
            ),
            if (widget.searchable)
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 4, 16, 8),
                child: TextField(
                  onChanged: (v) => setState(() => _query = v),
                  decoration: InputDecoration(
                    hintText: 'Rechercher',
                    prefixIcon: const Icon(Icons.search_rounded, color: _muted),
                    isDense: true,
                    filled: true,
                    fillColor: const Color(0xFFF3F5F9),
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(10),
                      borderSide: BorderSide.none,
                    ),
                  ),
                ),
              ),
            Flexible(
              child: items.isEmpty
                  ? const Padding(
                      padding: EdgeInsets.all(24),
                      child: Text(
                        'Aucun résultat',
                        textAlign: TextAlign.center,
                        style: TextStyle(color: _muted),
                      ),
                    )
                  : ListView.builder(
                      shrinkWrap: true,
                      padding: const EdgeInsets.only(bottom: 12),
                      itemCount: items.length,
                      itemBuilder: (_, index) {
                        final item = items[index];
                        final selected = item == widget.selected;
                        return InkWell(
                          onTap: () => Navigator.pop(context, item),
                          child: Container(
                            constraints: const BoxConstraints(minHeight: 50),
                            padding: const EdgeInsets.symmetric(
                              horizontal: 20,
                              vertical: 12,
                            ),
                            color: selected
                                ? const Color(0xFFEFF4FF)
                                : Colors.transparent,
                            child: Row(
                              children: [
                                Expanded(
                                  child: Text(
                                    widget.label(item),
                                    style: TextStyle(
                                      color: selected ? _accent : _ink,
                                      fontSize: 15.5,
                                      fontWeight: selected
                                          ? FontWeight.w600
                                          : FontWeight.w400,
                                    ),
                                  ),
                                ),
                                if (selected)
                                  const Icon(
                                    Icons.check_rounded,
                                    color: _accent,
                                    size: 22,
                                  ),
                              ],
                            ),
                          ),
                        );
                      },
                    ),
            ),
          ],
        ),
      ),
    );
  }
}
