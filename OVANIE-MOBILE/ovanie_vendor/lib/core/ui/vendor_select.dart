import 'package:flutter/material.dart';

import 'mk_dialogs.dart';

/// Liste de choix unique de l'application Vendeur : même champ, même feuille
/// de sélection partout (catégories, unités, communes, quartiers…).
const _ink = Color(0xFF0A2360);
const _muted = Color(0xFF5B6B86);
const _line = Color(0xFFD5DDE8);

Future<T?> showVendorSelect<T>(
  BuildContext context, {
  required String title,
  required List<T> items,
  required String Function(T) label,
  T? selected,
  bool? searchable,
  String? subtitle,
  bool boxed = false,
  IconData? icon,
  Widget Function(T)? leading,
}) => showMkChoiceSheet<T>(
  context,
  title: title,
  items: items,
  label: label,
  selected: selected,
  subtitle: subtitle,
  boxed: boxed,
  closeButton: !boxed,
  icon: icon,
  leading: leading,
  searchable: searchable ?? items.length > 8,
);

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
