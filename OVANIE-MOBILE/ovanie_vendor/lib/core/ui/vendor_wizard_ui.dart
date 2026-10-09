import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

/// Kit UI de l'assistant d'ouverture de boutique.
///
/// Principes : en-tête compact (retour, « Étape X sur N », barre de
/// progression), un titre par étape, champs empilés, actions fixées en bas.
/// Aucun bandeau photo, aucun encadré explicatif.
const wizardBlue = Color(0xFF0B4FC4);
const wizardOrange = Color(0xFFFF6500);
const wizardNavy = Color(0xFF031A4D);
const wizardText = Color(0xFF0A2360);
const wizardMuted = Color(0xFF66738F);
const wizardBorder = Color(0xFFD9E1EE);
const wizardSoftBlue = Color(0xFFEAF4FF);
const wizardBackground = Color(0xFFF6F8FC);
const wizardFieldFill = Colors.white;

/// En-tête compact : bouton retour, « Étape X sur N » et barre de progression.
class WizardTopBar extends StatelessWidget {
  const WizardTopBar({
    super.key,
    required this.currentStep,
    required this.totalSteps,
    required this.onBack,
  });

  /// Étape courante, à partir de 1.
  final int currentStep;
  final int totalSteps;
  final VoidCallback onBack;

  @override
  Widget build(BuildContext context) {
    final progress = totalSteps <= 0 ? 0.0 : currentStep / totalSteps;
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.dark.copyWith(
        statusBarColor: Colors.transparent,
      ),
      child: Material(
        color: Colors.white,
        child: SafeArea(
          bottom: false,
          child: Padding(
            padding: const EdgeInsets.fromLTRB(8, 6, 20, 12),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Row(
                  children: [
                    IconButton(
                      onPressed: onBack,
                      tooltip: 'Retour',
                      icon: const Icon(
                        Icons.arrow_back_rounded,
                        color: wizardNavy,
                        size: 24,
                      ),
                    ),
                    const SizedBox(width: 2),
                    Expanded(
                      child: Text(
                        'Étape $currentStep sur $totalSteps',
                        style: const TextStyle(
                          color: wizardNavy,
                          fontSize: 15,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ),
                    Text(
                      '${(progress * 100).round()} %',
                      style: const TextStyle(
                        color: wizardMuted,
                        fontSize: 13,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                Padding(
                  padding: const EdgeInsets.only(left: 12),
                  child: ClipRRect(
                    borderRadius: BorderRadius.circular(99),
                    child: TweenAnimationBuilder<double>(
                      tween: Tween(end: progress.clamp(0.0, 1.0)),
                      duration: const Duration(milliseconds: 250),
                      builder: (context, value, _) => LinearProgressIndicator(
                        value: value,
                        minHeight: 5,
                        color: wizardOrange,
                        backgroundColor: const Color(0xFFE6EAF2),
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

/// Titre de l'étape (une seule ligne d'intention, sans paragraphe explicatif).
class WizardStepTitle extends StatelessWidget {
  const WizardStepTitle(this.title, {super.key});
  final String title;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 20),
      child: Text(
        title,
        style: const TextStyle(
          color: wizardNavy,
          fontSize: 24,
          height: 1.15,
          fontWeight: FontWeight.w900,
          letterSpacing: -.3,
        ),
      ),
    );
  }
}

/// Barre d'actions fixée en bas : « Retour » (facultatif) et action principale.
class WizardBottomBar extends StatelessWidget {
  const WizardBottomBar({
    super.key,
    required this.primaryLabel,
    required this.onPrimary,
    this.onBack,
    this.loading = false,
  });

  final String primaryLabel;
  final VoidCallback? onPrimary;
  final VoidCallback? onBack;
  final bool loading;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white,
      child: DecoratedBox(
        decoration: const BoxDecoration(
          border: Border(top: BorderSide(color: Color(0xFFE6EAF2))),
        ),
        child: SafeArea(
          top: false,
          child: Padding(
            padding: const EdgeInsets.fromLTRB(20, 12, 20, 12),
            child: Row(
              children: [
                if (onBack != null) ...[
                  Expanded(
                    flex: 2,
                    child: WizardSecondaryButton(label: 'Retour', onPressed: onBack),
                  ),
                  const SizedBox(width: 12),
                ],
                Expanded(
                  flex: 3,
                  child: WizardPrimaryButton(
                    label: primaryLabel,
                    onPressed: onPrimary,
                    loading: loading,
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

class WizardPrimaryButton extends StatelessWidget {
  const WizardPrimaryButton({
    super.key,
    required this.label,
    required this.onPressed,
    this.loading = false,
  });

  final String label;
  final VoidCallback? onPressed;
  final bool loading;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 52,
      child: FilledButton(
        onPressed: loading ? null : onPressed,
        style: FilledButton.styleFrom(
          backgroundColor: wizardOrange,
          foregroundColor: Colors.white,
          disabledBackgroundColor: wizardOrange.withValues(alpha: .55),
          disabledForegroundColor: Colors.white,
          elevation: 0,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        ),
        child: loading
            ? const SizedBox(
                width: 20,
                height: 20,
                child: CircularProgressIndicator(strokeWidth: 2.2, color: Colors.white),
              )
            : Text(
                label,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w800),
              ),
      ),
    );
  }
}

class WizardSecondaryButton extends StatelessWidget {
  const WizardSecondaryButton({
    super.key,
    required this.label,
    required this.onPressed,
  });

  final String label;
  final VoidCallback? onPressed;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 52,
      child: OutlinedButton(
        onPressed: onPressed,
        style: OutlinedButton.styleFrom(
          foregroundColor: wizardNavy,
          side: const BorderSide(color: Color(0xFFC4CFE0)),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          backgroundColor: Colors.white,
        ),
        child: Text(
          label,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w800),
        ),
      ),
    );
  }
}

class WizardFieldLabel extends StatelessWidget {
  const WizardFieldLabel(this.text, {super.key, this.optional = false});
  final String text;
  final bool optional;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(left: 2, bottom: 7),
      child: Text.rich(
        TextSpan(
          text: text,
          style: const TextStyle(
            color: wizardText,
            fontSize: 14,
            fontWeight: FontWeight.w700,
          ),
          children: [
            if (optional)
              const TextSpan(
                text: '  (facultatif)',
                style: TextStyle(
                  color: wizardMuted,
                  fontSize: 12.5,
                  fontWeight: FontWeight.w500,
                ),
              ),
          ],
        ),
      ),
    );
  }
}

OutlineInputBorder _border(Color color, [double width = 1.2]) {
  return OutlineInputBorder(
    borderRadius: BorderRadius.circular(12),
    borderSide: BorderSide(color: color, width: width),
  );
}

InputDecoration wizardInput({
  required String hint,
  IconData? icon,
  Widget? suffix,
  Widget? prefix,
  bool readOnly = false,
}) {
  return InputDecoration(
    hintText: hint,
    hintStyle: const TextStyle(color: Color(0xFF97A3BA), fontSize: 15),
    prefixIcon: prefix ??
        (icon == null ? null : Icon(icon, color: const Color(0xFF52627F), size: 22)),
    prefixIconConstraints: const BoxConstraints(minWidth: 48, minHeight: 48),
    suffixIcon: suffix,
    filled: true,
    fillColor: readOnly ? const Color(0xFFF3F5F9) : wizardFieldFill,
    isDense: true,
    errorMaxLines: 2,
    contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 16),
    border: _border(wizardBorder),
    enabledBorder: _border(wizardBorder),
    disabledBorder: _border(const Color(0xFFE6EAF2)),
    focusedBorder: _border(wizardNavy, 1.5),
    errorBorder: _border(const Color(0xFFD92D20)),
    focusedErrorBorder: _border(const Color(0xFFD92D20), 1.5),
  );
}

/// Champ de sélection ouvrant une liste (bottom sheet).
class WizardSelectField extends StatelessWidget {
  const WizardSelectField({
    super.key,
    required this.label,
    required this.value,
    required this.hint,
    required this.icon,
    required this.onTap,
    this.leading,
    this.optional = false,
  });

  final String label;
  final String? value;
  final String hint;
  final IconData icon;
  final VoidCallback? onTap;
  final Widget? leading;
  final bool optional;

  @override
  Widget build(BuildContext context) {
    final hasValue = value != null && value!.trim().isNotEmpty;
    final enabled = onTap != null;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        WizardFieldLabel(label, optional: optional),
        Material(
          color: enabled ? wizardFieldFill : const Color(0xFFF3F5F9),
          borderRadius: BorderRadius.circular(12),
          child: InkWell(
            onTap: onTap,
            borderRadius: BorderRadius.circular(12),
            child: Container(
              height: 52,
              padding: const EdgeInsets.symmetric(horizontal: 13),
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: wizardBorder, width: 1.2),
              ),
              child: Row(
                children: [
                  leading ?? Icon(icon, color: const Color(0xFF52627F), size: 22),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Text(
                      hasValue ? value! : hint,
                      style: TextStyle(
                        color: hasValue ? wizardText : const Color(0xFF97A3BA),
                        fontSize: 15,
                        fontWeight: hasValue ? FontWeight.w700 : FontWeight.w400,
                      ),
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                  Icon(
                    Icons.keyboard_arrow_down_rounded,
                    color: enabled ? wizardText : const Color(0xFFB4BDCD),
                    size: 24,
                  ),
                ],
              ),
            ),
          ),
        ),
      ],
    );
  }
}

/// Option exclusive (type de vendeur, logistique, reversement…) présentée
/// comme une ligne sélectionnable avec indicateur radio.
class WizardOptionTile extends StatelessWidget {
  const WizardOptionTile({
    super.key,
    required this.selected,
    required this.title,
    required this.onTap,
    this.subtitle,
    this.icon,
    this.leading,
  });

  final bool selected;
  final String title;
  final String? subtitle;
  final IconData? icon;
  final Widget? leading;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Material(
        color: selected ? const Color(0xFFFFF6F0) : Colors.white,
        borderRadius: BorderRadius.circular(14),
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(14),
          child: AnimatedContainer(
            duration: const Duration(milliseconds: 160),
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 13),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(14),
              border: Border.all(
                color: selected ? wizardOrange : wizardBorder,
                width: selected ? 1.6 : 1.2,
              ),
            ),
            child: Row(
              children: [
                if (leading != null)
                  leading!
                else if (icon != null)
                  Container(
                    width: 40,
                    height: 40,
                    decoration: BoxDecoration(
                      color: selected
                          ? wizardOrange.withValues(alpha: .12)
                          : const Color(0xFFF0F3F8),
                      borderRadius: BorderRadius.circular(11),
                    ),
                    child: Icon(
                      icon,
                      color: selected ? wizardOrange : const Color(0xFF52627F),
                      size: 22,
                    ),
                  ),
                if (leading != null || icon != null) const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        title,
                        style: const TextStyle(
                          color: wizardText,
                          fontSize: 15,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      if (subtitle != null && subtitle!.trim().isNotEmpty) ...[
                        const SizedBox(height: 2),
                        Text(
                          subtitle!,
                          style: const TextStyle(
                            color: wizardMuted,
                            fontSize: 12.5,
                            height: 1.3,
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
                const SizedBox(width: 8),
                Icon(
                  selected
                      ? Icons.radio_button_checked_rounded
                      : Icons.radio_button_unchecked_rounded,
                  color: selected ? wizardOrange : const Color(0xFFB4BDCD),
                  size: 22,
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// Ligne d'ajout de pièce jointe : état « à ajouter » ou « ajouté ».
class WizardUploadTile extends StatelessWidget {
  const WizardUploadTile({
    super.key,
    required this.title,
    required this.onTap,
    this.fileName,
    this.formats = 'PDF, JPG ou PNG',
    this.icon = Icons.upload_file_rounded,
  });

  final String title;
  final String? fileName;
  final String formats;
  final IconData icon;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final done = fileName != null && fileName!.isNotEmpty;
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Material(
        color: done ? const Color(0xFFF1FAF5) : Colors.white,
        borderRadius: BorderRadius.circular(14),
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(14),
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 13),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(14),
              border: Border.all(
                color: done ? const Color(0xFF9FD8B8) : wizardBorder,
                width: 1.2,
              ),
            ),
            child: Row(
              children: [
                Container(
                  width: 40,
                  height: 40,
                  decoration: BoxDecoration(
                    color: done ? const Color(0xFFDDF3E6) : const Color(0xFFF0F3F8),
                    borderRadius: BorderRadius.circular(11),
                  ),
                  child: Icon(
                    done ? Icons.check_rounded : icon,
                    color: done ? const Color(0xFF16804A) : const Color(0xFF52627F),
                    size: 22,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        title,
                        style: const TextStyle(
                          color: wizardText,
                          fontSize: 15,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        done ? fileName! : formats,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                          color: done ? const Color(0xFF16804A) : wizardMuted,
                          fontSize: 12.5,
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 8),
                Text(
                  done ? 'Modifier' : 'Ajouter',
                  style: const TextStyle(
                    color: wizardOrange,
                    fontSize: 13.5,
                    fontWeight: FontWeight.w800,
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

/// Case à cocher avec libellé cliquable.
class WizardCheckTile extends StatelessWidget {
  const WizardCheckTile({
    super.key,
    required this.value,
    required this.onChanged,
    required this.child,
  });

  final bool value;
  final ValueChanged<bool> onChanged;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(14),
      child: InkWell(
        onTap: () => onChanged(!value),
        borderRadius: BorderRadius.circular(14),
        child: Container(
          padding: const EdgeInsets.fromLTRB(6, 8, 14, 8),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: value ? wizardOrange : wizardBorder, width: 1.2),
          ),
          child: Row(
            children: [
              Checkbox(
                value: value,
                onChanged: (v) => onChanged(v ?? false),
                activeColor: wizardOrange,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(5)),
              ),
              const SizedBox(width: 2),
              Expanded(child: child),
            ],
          ),
        ),
      ),
    );
  }
}

Future<T?> showWizardSelector<T>(
  BuildContext context, {
  required String title,
  required List<T> items,
  required String Function(T) label,
  T? selected,
  Widget Function(T)? leading,
  bool searchable = false,
}) async {
  return showModalBottomSheet<T>(
    context: context,
    isScrollControlled: true,
    backgroundColor: Colors.transparent,
    barrierColor: Colors.black.withValues(alpha: .42),
    builder: (sheetContext) => _WizardSelectorSheet<T>(
      title: title,
      items: items,
      label: label,
      selected: selected,
      leading: leading,
      searchable: searchable,
    ),
  );
}

class _WizardSelectorSheet<T> extends StatefulWidget {
  const _WizardSelectorSheet({
    required this.title,
    required this.items,
    required this.label,
    this.selected,
    this.leading,
    this.searchable = false,
  });
  final String title;
  final List<T> items;
  final String Function(T) label;
  final T? selected;
  final Widget Function(T)? leading;
  final bool searchable;

  @override
  State<_WizardSelectorSheet<T>> createState() => _WizardSelectorSheetState<T>();
}

class _WizardSelectorSheetState<T> extends State<_WizardSelectorSheet<T>> {
  final _search = TextEditingController();

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final q = _search.text.trim().toLowerCase();
    final filtered = q.isEmpty
        ? widget.items
        : widget.items.where((e) => widget.label(e).toLowerCase().contains(q)).toList();
    return Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
      child: SafeArea(
        top: false,
        child: Container(
          constraints: BoxConstraints(maxHeight: MediaQuery.sizeOf(context).height * .72),
          padding: const EdgeInsets.fromLTRB(18, 10, 18, 12),
          decoration: const BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 42,
                height: 4,
                decoration: BoxDecoration(
                  color: const Color(0xFFD4DAE4),
                  borderRadius: BorderRadius.circular(99),
                ),
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: Text(
                      widget.title,
                      style: const TextStyle(
                        color: wizardNavy,
                        fontSize: 18,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ),
                  IconButton(
                    onPressed: () => Navigator.pop(context),
                    icon: const Icon(Icons.close_rounded, color: wizardText),
                  ),
                ],
              ),
              if (widget.searchable) ...[
                const SizedBox(height: 4),
                TextField(
                  controller: _search,
                  onChanged: (_) => setState(() {}),
                  decoration: wizardInput(hint: 'Rechercher…', icon: Icons.search_rounded),
                ),
                const SizedBox(height: 8),
              ],
              Flexible(
                child: filtered.isEmpty
                    ? const Padding(
                        padding: EdgeInsets.symmetric(vertical: 24),
                        child: Text('Aucun résultat', style: TextStyle(color: wizardMuted)),
                      )
                    : ListView.separated(
                        shrinkWrap: true,
                        itemCount: filtered.length,
                        separatorBuilder: (_, __) => const Divider(height: 1),
                        itemBuilder: (_, index) {
                          final item = filtered[index];
                          final selected = item == widget.selected;
                          return ListTile(
                            contentPadding: const EdgeInsets.symmetric(horizontal: 4, vertical: 2),
                            leading: widget.leading?.call(item),
                            title: Text(
                              widget.label(item),
                              style: TextStyle(
                                color: wizardText,
                                fontSize: 15.5,
                                fontWeight: selected ? FontWeight.w900 : FontWeight.w600,
                              ),
                            ),
                            trailing: selected
                                ? const Icon(Icons.check_circle_rounded, color: wizardOrange)
                                : null,
                            onTap: () => Navigator.pop(context, item),
                          );
                        },
                      ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
