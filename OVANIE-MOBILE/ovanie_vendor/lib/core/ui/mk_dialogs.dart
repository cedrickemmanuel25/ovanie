import 'package:flutter/material.dart';

import 'vendor_mockup_ui.dart';

/// Fenêtres des maquettes « OVANIE Vendeur » : feuille de confirmation qui
/// monte du bas et fenêtre centrée, avec pictogramme rond, titre et boutons.

/// Pastille ronde colorée portant le pictogramme d'une fenêtre.
class MkIconDisc extends StatelessWidget {
  const MkIconDisc(this.icon, {super.key, required this.color, this.size = 76});
  final IconData icon;
  final Color color;
  final double size;

  @override
  Widget build(BuildContext context) => Container(
    width: size,
    height: size,
    decoration: BoxDecoration(color: color.withValues(alpha: .13), shape: BoxShape.circle),
    child: Icon(icon, color: color, size: size * .48),
  );
}

/// Encadré clair qui résume l'objet de la confirmation (commande, retour…).
class MkSheetSummary extends StatelessWidget {
  const MkSheetSummary({super.key, required this.child});
  final Widget child;

  @override
  Widget build(BuildContext context) => Container(
    width: double.infinity,
    padding: const EdgeInsets.all(12),
    decoration: BoxDecoration(
      color: const Color(0xFFF8FAFD),
      borderRadius: BorderRadius.circular(14),
      border: Border.all(color: const Color(0xFFE4E9F1), width: 1.2),
    ),
    child: child,
  );
}

/// Enveloppe commune d'une feuille du bas : poignée, marges, clavier.
class MkSheetFrame extends StatelessWidget {
  const MkSheetFrame({super.key, required this.children, this.padding = const EdgeInsets.fromLTRB(16, 10, 16, 16)});
  final List<Widget> children;
  final EdgeInsets padding;

  @override
  Widget build(BuildContext context) => MkTheme(
    lightStatusBar: false,
    child: SafeArea(
      top: false,
      child: Padding(
        padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
        child: ConstrainedBox(
          constraints: BoxConstraints(maxHeight: MediaQuery.sizeOf(context).height * .92),
          child: SingleChildScrollView(
            padding: padding,
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Center(
                  child: Container(
                    width: 44,
                    height: 5,
                    decoration: BoxDecoration(color: const Color(0xFFD5DBE7), borderRadius: BorderRadius.circular(99)),
                  ),
                ),
                const SizedBox(height: 16),
                ...children,
              ],
            ),
          ),
        ),
      ),
    ),
  );
}

Future<T?> showMkSheet<T>(BuildContext context, {required WidgetBuilder builder}) => showModalBottomSheet<T>(
  context: context,
  isScrollControlled: true,
  backgroundColor: Colors.white,
  shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(24))),
  builder: builder,
);

/// Feuille de confirmation : pictogramme, titre, résumé, message et deux boutons.
/// Renvoie vrai lorsque l'utilisateur confirme.
Future<bool> showMkConfirmSheet(
  BuildContext context, {
  required IconData icon,
  required Color iconColor,
  required String title,
  Widget? summary,
  Widget? body,
  String? message,
  required String confirmLabel,
  IconData? confirmIcon,
  Color confirmColor = Mk.orange,
  String cancelLabel = 'Annuler',
  Color cancelColor = Mk.orange,
  bool equalButtons = false,
  Widget? header,
}) async {
  final result = await showMkSheet<bool>(
    context,
    builder: (sheet) => MkSheetFrame(
      children: [
        Center(child: header ?? MkIconDisc(icon, color: iconColor)),
        const SizedBox(height: 14),
        Text(
          title,
          textAlign: TextAlign.center,
          style: const TextStyle(color: Mk.navy, fontSize: 21, height: 1.2, fontWeight: FontWeight.w800),
        ),
        if (summary != null) ...[const SizedBox(height: 14), summary],
        if (body != null) ...[const SizedBox(height: 12), body],
        if (message != null) ...[
          const SizedBox(height: 12),
          Text(
            message,
            textAlign: TextAlign.center,
            style: const TextStyle(color: Mk.muted, fontSize: 14, height: 1.45, fontWeight: FontWeight.w500),
          ),
        ],
        const SizedBox(height: 18),
        Row(
          children: [
            Expanded(
              flex: equalButtons ? 5 : 4,
              child: MkBarButton(label: cancelLabel, color: cancelColor, onTap: () => Navigator.pop(sheet, false)),
            ),
            const SizedBox(width: 10),
            Expanded(
              flex: equalButtons ? 5 : 6,
              child: MkBarButton(
                label: confirmLabel,
                icon: confirmIcon,
                filled: true,
                color: confirmColor,
                onTap: () => Navigator.pop(sheet, true),
              ),
            ),
          ],
        ),
      ],
    ),
  );
  return result == true;
}

/// Une action d'une fenêtre centrée.
class MkDialogAction<T> {
  const MkDialogAction(this.label, this.value, {this.icon, this.color = Mk.orange, this.style = MkDialogActionStyle.filled, this.caption});
  final String label;
  final T value;
  final IconData? icon;
  final Color color;
  final MkDialogActionStyle style;

  /// Précision affichée sous le bouton (« Sauvegardez et terminez plus tard »).
  final String? caption;
}

enum MkDialogActionStyle { filled, outlined, text }

/// Fenêtre centrée : image ou pictogramme, titre, message et actions empilées.
Future<T?> showMkDialog<T>(
  BuildContext context, {
  Widget? header,
  IconData? icon,
  Color iconColor = Mk.orange,
  required String title,
  String? message,
  Widget? extra,
  required List<MkDialogAction<T>> actions,
  bool dismissible = true,
}) => showDialog<T>(
  context: context,
  barrierDismissible: dismissible,
  builder: (dialog) => MkTheme(
    lightStatusBar: false,
    child: Dialog(
      backgroundColor: Colors.white,
      clipBehavior: Clip.antiAlias,
      insetPadding: const EdgeInsets.symmetric(horizontal: 24, vertical: 24),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(22)),
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 420),
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              ?header,
              Padding(
                padding: EdgeInsets.fromLTRB(20, header == null ? 24 : 6, 20, 18),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    if (icon != null) ...[Center(child: MkIconDisc(icon, color: iconColor, size: 70)), const SizedBox(height: 14)],
                    Text(
                      title,
                      textAlign: TextAlign.center,
                      style: const TextStyle(color: Mk.navy, fontSize: 20, height: 1.25, fontWeight: FontWeight.w800),
                    ),
                    if (message != null) ...[
                      const SizedBox(height: 8),
                      Text(
                        message,
                        textAlign: TextAlign.center,
                        style: const TextStyle(color: Mk.muted, fontSize: 14, height: 1.45, fontWeight: FontWeight.w500),
                      ),
                    ],
                    if (extra != null) ...[const SizedBox(height: 10), extra],
                    const SizedBox(height: 18),
                    for (var i = 0; i < actions.length; i++) ...[
                      if (i > 0) const SizedBox(height: 8),
                      _action(dialog, actions[i]),
                      if (actions[i].caption != null)
                        Padding(
                          padding: const EdgeInsets.only(top: 5),
                          child: Text(
                            actions[i].caption!,
                            textAlign: TextAlign.center,
                            style: const TextStyle(color: Mk.muted, fontSize: 12.5, fontWeight: FontWeight.w500),
                          ),
                        ),
                    ],
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    ),
  ),
);

Widget _action<T>(BuildContext dialog, MkDialogAction<T> action) {
  if (action.style == MkDialogActionStyle.text) {
    return TextButton(
      onPressed: () => Navigator.pop(dialog, action.value),
      style: TextButton.styleFrom(minimumSize: const Size.fromHeight(44)),
      child: Text(
        action.label,
        style: TextStyle(fontFamily: Mk.font, color: action.color, fontSize: 15, fontWeight: FontWeight.w600),
      ),
    );
  }
  return MkBarButton(
    label: action.label,
    icon: action.icon,
    filled: action.style == MkDialogActionStyle.filled,
    color: action.color,
    onTap: () => Navigator.pop(dialog, action.value),
  );
}

/// Message bref en bas de l'écran : bandeau sombre arrondi, pastille verte
/// (ou rouge en cas d'échec) et texte blanc.
void mkToast(BuildContext context, String message, {bool error = false, bool plain = false}) {
  final messenger = ScaffoldMessenger.maybeOf(context);
  if (messenger == null) return;
  messenger
    ..hideCurrentSnackBar()
    ..showSnackBar(
      SnackBar(
        behavior: SnackBarBehavior.floating,
        backgroundColor: const Color(0xFF1F2937),
        elevation: 6,
        margin: const EdgeInsets.fromLTRB(14, 0, 14, 12),
        padding: const EdgeInsets.fromLTRB(12, 10, 14, 10),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        content: Row(
          children: [
            if (!plain)
              Container(
                width: 28,
                height: 28,
                decoration: BoxDecoration(color: error ? const Color(0xFFD92D20) : const Color(0xFF16A34A), shape: BoxShape.circle),
                child: Icon(error ? Icons.priority_high_rounded : Icons.check_rounded, color: Colors.white, size: 19),
              ),
            if (!plain) const SizedBox(width: 12),
            Expanded(
              child: Text(
                message,
                textAlign: plain ? TextAlign.center : TextAlign.start,
                style: const TextStyle(fontFamily: Mk.font, color: Colors.white, fontSize: 13.5, height: 1.35, fontWeight: FontWeight.w500),
              ),
            ),
          ],
        ),
      ),
    );
}

/// Fenêtre de choix unique des maquettes.
///
/// [boxed] : une carte par option, bouton radio à droite (opérateur, délai,
/// groupe de livraison). Sinon : liste à séparateurs, l'option retenue est
/// encadrée d'orange avec une coche. Un appui choisit et referme la fenêtre.
Future<T?> showMkChoiceSheet<T>(
  BuildContext context, {
  required String title,
  required List<T> items,
  required String Function(T) label,
  T? selected,
  String? subtitle,
  bool boxed = false,
  bool closeButton = true,
  bool searchable = false,
  String searchHint = 'Rechercher…',
  IconData? icon,
  Widget Function(T)? leading,
}) {
  FocusManager.instance.primaryFocus?.unfocus();
  return showMkSheet<T>(
    context,
    builder: (_) => _MkChoiceSheet<T>(
      title: title,
      items: items,
      label: label,
      selected: selected,
      subtitle: subtitle,
      boxed: boxed,
      closeButton: closeButton,
      searchable: searchable,
      searchHint: searchHint,
      icon: icon,
      leading: leading,
    ),
  );
}

class _MkChoiceSheet<T> extends StatefulWidget {
  const _MkChoiceSheet({
    required this.title,
    required this.items,
    required this.label,
    required this.selected,
    required this.subtitle,
    required this.boxed,
    required this.closeButton,
    required this.searchable,
    required this.searchHint,
    required this.icon,
    required this.leading,
  });
  final String title;
  final List<T> items;
  final String Function(T) label;
  final T? selected;
  final String? subtitle;
  final bool boxed, closeButton, searchable;
  final String searchHint;
  final IconData? icon;
  final Widget Function(T)? leading;

  @override
  State<_MkChoiceSheet<T>> createState() => _MkChoiceSheetState<T>();
}

class _MkChoiceSheetState<T> extends State<_MkChoiceSheet<T>> {
  String _query = '';

  static String _plain(String value) => value
      .toLowerCase()
      .replaceAll(RegExp('[éèêë]'), 'e')
      .replaceAll(RegExp('[àâä]'), 'a')
      .replaceAll(RegExp('[îï]'), 'i')
      .replaceAll(RegExp('[ôö]'), 'o')
      .replaceAll(RegExp('[ùûü]'), 'u')
      .replaceAll('ç', 'c');

  @override
  Widget build(BuildContext context) {
    final needle = _plain(_query.trim());
    final rows = needle.isEmpty ? widget.items : widget.items.where((e) => _plain(widget.label(e)).contains(needle)).toList();
    return MkTheme(
      lightStatusBar: false,
      child: SafeArea(
        top: false,
        child: Padding(
          padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
          child: ConstrainedBox(
            constraints: BoxConstraints(maxHeight: MediaQuery.sizeOf(context).height * .82),
            child: Padding(
              padding: const EdgeInsets.fromLTRB(14, 10, 14, 14),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Center(
                    child: Container(
                      width: 44,
                      height: 5,
                      decoration: BoxDecoration(color: const Color(0xFFD5DBE7), borderRadius: BorderRadius.circular(99)),
                    ),
                  ),
                  const SizedBox(height: 14),
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 4),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                widget.title,
                                style: const TextStyle(
                                  color: Mk.navy,
                                  fontSize: 21,
                                  height: 1.2,
                                  fontWeight: FontWeight.w800,
                                  letterSpacing: -.3,
                                ),
                              ),
                              if (widget.subtitle != null) ...[
                                const SizedBox(height: 3),
                                Text(
                                  widget.subtitle!,
                                  style: const TextStyle(color: Mk.muted, fontSize: 13.5, height: 1.4, fontWeight: FontWeight.w500),
                                ),
                              ],
                            ],
                          ),
                        ),
                        if (widget.closeButton)
                          Semantics(
                            button: true,
                            label: 'Fermer',
                            child: Material(
                              color: const Color(0xFFEDF0F5),
                              shape: const CircleBorder(),
                              clipBehavior: Clip.antiAlias,
                              child: InkWell(
                                onTap: () => Navigator.pop(context),
                                child: const SizedBox(
                                  width: 36,
                                  height: 36,
                                  child: Icon(Icons.close_rounded, color: Mk.icon, size: 22),
                                ),
                              ),
                            ),
                          ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 14),
                  if (widget.searchable) ...[
                    TextField(
                      onChanged: (v) => setState(() => _query = v),
                      textInputAction: TextInputAction.search,
                      style: const TextStyle(fontFamily: Mk.font, color: Mk.navy, fontSize: 14.5, fontWeight: FontWeight.w500),
                      decoration: InputDecoration(
                        hintText: widget.searchHint,
                        hintStyle: const TextStyle(fontFamily: Mk.font, color: Mk.hint, fontSize: 14.5, fontWeight: FontWeight.w400),
                        prefixIcon: const Icon(Icons.search_rounded, color: Mk.icon, size: 22),
                        isDense: true,
                        filled: true,
                        fillColor: const Color(0xFFEFF2F7),
                        contentPadding: const EdgeInsets.symmetric(vertical: 13),
                        border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
                        enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
                        focusedBorder: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(12),
                          borderSide: const BorderSide(color: Mk.border),
                        ),
                      ),
                    ),
                    const SizedBox(height: 10),
                  ],
                  Flexible(
                    child: rows.isEmpty
                        ? const Padding(
                            padding: EdgeInsets.symmetric(vertical: 24),
                            child: Text(
                              'Aucun résultat',
                              textAlign: TextAlign.center,
                              style: TextStyle(color: Mk.muted, fontSize: 14),
                            ),
                          )
                        : ListView.builder(
                            shrinkWrap: true,
                            itemCount: rows.length,
                            itemBuilder: (context, i) => _row(rows[i], last: i == rows.length - 1),
                          ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _row(T item, {required bool last}) {
    final selected = item == widget.selected;
    final boxed = widget.boxed;
    final framed = boxed || selected;
    final Widget? lead = widget.leading != null
        ? widget.leading!(item)
        : widget.icon != null
        ? Icon(widget.icon, color: selected ? Mk.orange : Mk.icon, size: 24)
        : null;
    final content = Padding(
      padding: EdgeInsets.fromLTRB(boxed ? 12 : 14, boxed ? 10 : 12, 12, boxed ? 10 : 12),
      child: Row(
        children: [
          if (lead != null) ...[lead, const SizedBox(width: 14)],
          Expanded(
            child: Text(
              widget.label(item),
              style: TextStyle(
                color: Mk.navy,
                fontSize: 15,
                height: 1.3,
                fontWeight: selected ? FontWeight.w800 : (boxed ? FontWeight.w600 : FontWeight.w500),
              ),
            ),
          ),
          const SizedBox(width: 8),
          if (boxed)
            Icon(
              selected ? Icons.radio_button_checked_rounded : Icons.radio_button_unchecked_rounded,
              color: selected ? Mk.orange : const Color(0xFF8A95AE),
              size: 26,
            )
          else if (selected)
            Container(
              width: 24,
              height: 24,
              decoration: const BoxDecoration(color: Mk.orange, shape: BoxShape.circle),
              child: const Icon(Icons.check_rounded, color: Colors.white, size: 17),
            ),
        ],
      ),
    );
    return Padding(
      padding: EdgeInsets.only(bottom: boxed ? 8 : 0),
      child: Semantics(
        button: true,
        selected: selected,
        inMutuallyExclusiveGroup: true,
        child: Material(
          color: selected ? const Color(0xFFFFF4EC) : Colors.white,
          shape: framed
              ? RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                  side: BorderSide(color: selected ? Mk.orange : const Color(0xFFDCE2EC), width: selected ? 1.4 : 1.2),
                )
              : Border(bottom: BorderSide(color: last ? Colors.transparent : const Color(0xFFE4E9F1))),
          clipBehavior: Clip.antiAlias,
          child: InkWell(
            onTap: () => Navigator.pop(context, item),
            child: ConstrainedBox(constraints: const BoxConstraints(minHeight: 50), child: content),
          ),
        ),
      ),
    );
  }
}
