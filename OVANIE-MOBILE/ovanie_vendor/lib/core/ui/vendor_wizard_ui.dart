import 'package:flutter/material.dart';
import 'package:flutter/services.dart';


const wizardBlue = Color(0xFF0B4FC4);
const wizardOrange = Color(0xFFFF6500);
const wizardNavy = Color(0xFF061D49);
const wizardText = Color(0xFF0A2360);
const wizardMuted = Color(0xFF637297);
const wizardBorder = Color(0xFFD9E1EE);
const wizardSoftBlue = Color(0xFFEAF4FF);

class VendorWizardScaffold extends StatelessWidget {
  const VendorWizardScaffold({
    super.key,
    required this.header,
    required this.child,
    this.bottom,
    this.background = const Color(0xFFF7FAFF),
  });

  final Widget header;
  final Widget child;
  final Widget? bottom;
  final Color background;

  @override
  Widget build(BuildContext context) {
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.light.copyWith(
        statusBarColor: Colors.transparent,
      ),
      child: Scaffold(
        backgroundColor: background,
        body: Column(
          children: [
            header,
            Expanded(child: child),
          ],
        ),
        bottomNavigationBar: bottom,
      ),
    );
  }
}

class VendorWizardHeader extends StatelessWidget {
  const VendorWizardHeader({
    super.key,
    required this.currentStep,
    required this.totalSteps,
    this.titlePrefix = 'Ouverture de ',
    this.titleAccent = 'boutique',
    this.onBack,
  });

  final int currentStep;
  final int totalSteps;
  final String titlePrefix;
  final String titleAccent;
  final VoidCallback? onBack;

  @override
  Widget build(BuildContext context) {
    final size = MediaQuery.sizeOf(context);
    final height =
        (size.width * .48).clamp(215.0, 340.0) +
        MediaQuery.paddingOf(context).top;
    return SizedBox(
      height: height,
      width: double.infinity,
      child: Stack(
        children: [
          const Positioned.fill(
            child: DecoratedBox(
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  colors: [Color(0xFF031B40), Color(0xFF124C8C)],
                  begin: Alignment.centerLeft,
                  end: Alignment.centerRight,
                ),
              ),
            ),
          ),
          Positioned.fill(
            child: Opacity(
              opacity: .12,
              child: Image.asset(
                'assets/images/vendor_construction_blue.png',
                fit: BoxFit.cover,
                alignment: Alignment.centerRight,
              ),
            ),
          ),
          Positioned(
            right: 0,
            top: 0,
            bottom: -8,
            width: size.width * .42,
            child: IgnorePointer(
              child: ShaderMask(
                shaderCallback: (rect) => const LinearGradient(
                  begin: Alignment.centerLeft,
                  end: Alignment.centerRight,
                  colors: [Colors.transparent, Colors.white, Colors.white],
                  stops: [0.0, 0.45, 1.0],
                ).createShader(rect),
                blendMode: BlendMode.dstIn,
                child: Image.asset(
                  'assets/images/vendor_worker_hero.png',
                  fit: BoxFit.cover,
                  alignment: Alignment.topCenter,
                ),
              ),
            ),
          ),
          SafeArea(
            bottom: false,
            child: Padding(
              padding: const EdgeInsets.fromLTRB(18, 8, 18, 26),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      _RoundBackButton(onPressed: onBack),
                      const SizedBox(width: 14),
                      Expanded(
                        child: Align(
                          alignment: Alignment.topCenter,
                          child: Image.asset(
                            'assets/images/ovanie_logo.png',
                            height: 65,
                            fit: BoxFit.contain,
                          ),
                        ),
                      ),
                      SizedBox(width: size.width * .20),
                    ],
                  ),
                  const Spacer(),
                  FittedBox(
                    fit: BoxFit.scaleDown,
                    alignment: Alignment.centerLeft,
                    child: RichText(
                      text: TextSpan(
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 31,
                          fontWeight: FontWeight.w900,
                          height: 1.02,
                        ),
                        children: [
                          TextSpan(text: titlePrefix),
                          TextSpan(
                            text: titleAccent,
                            style: const TextStyle(color: wizardOrange),
                          ),
                        ],
                      ),
                    ),
                  ),
                  const SizedBox(height: 7),
                  Text(
                    'Étape $currentStep sur $totalSteps',
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 18.5,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                  const SizedBox(height: 13),
                  SizedBox(
                    width: size.width * .60,
                    child: FittedBox(
                      fit: BoxFit.scaleDown,
                      alignment: Alignment.centerLeft,
                      child: SizedBox(
                        width: 300,
                        child: WizardProgress(
                          currentStep: currentStep,
                          totalSteps: totalSteps,
                          activeColor: wizardOrange,
                          completedColor: wizardOrange,
                          inactiveColor: const Color(0xFF9EB0C9),
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class ProductWizardHeader extends StatelessWidget {
  const ProductWizardHeader({
    super.key,
    required this.currentStep,
    required this.labels,
    this.onBack,
    this.title = 'Ajouter un produit',
  });

  final int currentStep;
  final List<String> labels;
  final VoidCallback? onBack;
  final String title;

  @override
  Widget build(BuildContext context) {
    final size = MediaQuery.sizeOf(context);
    return Container(
      width: double.infinity,
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          colors: [Color(0xFF052052), Color(0xFF0A4C90)],
          begin: Alignment.centerLeft,
          end: Alignment.centerRight,
        ),
      ),
      child: Stack(
        children: [
          Positioned.fill(
            child: Opacity(
              opacity: .13,
              child: Image.asset(
                'assets/images/vendor_construction_blue.png',
                fit: BoxFit.cover,
                alignment: Alignment.centerRight,
              ),
            ),
          ),
          SafeArea(
            bottom: false,
            child: Padding(
              padding: const EdgeInsets.fromLTRB(18, 8, 18, 0),
              child: Column(
                children: [
                  Row(
                    children: [
                      _RoundBackButton(onPressed: onBack),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Image.asset(
                          'assets/images/ovanie_logo.png',
                          height: 70,
                          fit: BoxFit.contain,
                          alignment: Alignment.center,
                        ),
                      ),
                      SizedBox(
                        width: size.width * .32,
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.end,
                          children: [
                            Text(
                              title,
                              textAlign: TextAlign.right,
                              style: const TextStyle(
                                color: Colors.white,
                                fontSize: 22,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                            const SizedBox(height: 3),
                            const Text(
                              'Mettez vos produits en ligne et\ntouchez plus de clients.',
                              textAlign: TextAlign.right,
                              style: TextStyle(
                                color: Color(0xFFE7EEF8),
                                fontSize: 12.5,
                                height: 1.25,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 10),
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.fromLTRB(18, 14, 18, 12),
                    decoration: const BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.vertical(
                        top: Radius.circular(28),
                      ),
                    ),
                    child: WizardProgress(
                      currentStep: currentStep,
                      totalSteps: labels.length,
                      labels: labels,
                      activeColor: wizardOrange,
                      completedColor: const Color(0xFF1976E9),
                      inactiveColor: const Color(0xFFB7C2D5),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class WizardProgress extends StatelessWidget {
  const WizardProgress({
    super.key,
    required this.currentStep,
    required this.totalSteps,
    required this.activeColor,
    required this.completedColor,
    required this.inactiveColor,
    this.labels,
  });

  final int currentStep;
  final int totalSteps;
  final Color activeColor;
  final Color completedColor;
  final Color inactiveColor;
  final List<String>? labels;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: List.generate(totalSteps, (index) {
        final step = index + 1;
        final isActive = step == currentStep;
        final isCompleted = step < currentStep;
        final color = isActive
            ? activeColor
            : isCompleted
            ? completedColor
            : inactiveColor;
        final label = labels != null && index < labels!.length
            ? labels![index]
            : null;
        return Expanded(
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Column(
                  children: [
                    Row(
                      children: [
                        if (index > 0)
                          Expanded(
                            child: Container(
                              height: 3,
                              color: isCompleted || isActive
                                  ? completedColor
                                  : inactiveColor.withValues(alpha: .5),
                            ),
                          )
                        else
                          const Spacer(),
                        Container(
                          width: 38,
                          height: 38,
                          alignment: Alignment.center,
                          decoration: BoxDecoration(
                            color: isActive || isCompleted
                                ? color
                                : Colors.transparent,
                            shape: BoxShape.circle,
                            border: Border.all(color: color, width: 2.2),
                            boxShadow: isActive
                                ? [
                                    BoxShadow(
                                      color: activeColor.withValues(alpha: .22),
                                      blurRadius: 7,
                                      spreadRadius: 2,
                                    ),
                                  ]
                                : null,
                          ),
                          child: Text(
                            '$step',
                            style: TextStyle(
                              color: isActive || isCompleted
                                  ? Colors.white
                                  : color,
                              fontSize: 16,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                        ),
                        if (index < totalSteps - 1)
                          Expanded(
                            child: Container(
                              height: 3,
                              color: isCompleted
                                  ? completedColor
                                  : inactiveColor.withValues(alpha: .5),
                            ),
                          )
                        else
                          const Spacer(),
                      ],
                    ),
                    if (label != null) ...[
                      const SizedBox(height: 5),
                      Text(
                        label,
                        textAlign: TextAlign.center,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                          color: isActive
                              ? activeColor
                              : isCompleted
                              ? completedColor
                              : const Color(0xFF4C5D7D),
                          fontSize: 11.5,
                          fontWeight: isActive
                              ? FontWeight.w800
                              : FontWeight.w500,
                        ),
                      ),
                    ],
                  ],
                ),
              ),
            ],
          ),
        );
      }),
    );
  }
}

class WizardCard extends StatelessWidget {
  const WizardCard({
    super.key,
    required this.child,
    this.margin = const EdgeInsets.fromLTRB(24, 0, 24, 24),
    this.padding = const EdgeInsets.fromLTRB(26, 26, 26, 24),
    this.radius = 26,
  });

  final Widget child;
  final EdgeInsets margin;
  final EdgeInsets padding;
  final double radius;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: margin,
      padding: padding,
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(radius),
        border: Border.all(color: const Color(0xFFE5EBF3)),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF0E3267).withValues(alpha: .06),
            blurRadius: 18,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: child,
    );
  }
}

class WizardIntro extends StatelessWidget {
  const WizardIntro({
    super.key,
    required this.icon,
    required this.title,
    required this.subtitle,
    this.orangeIcon = false,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final bool orangeIcon;

  @override
  Widget build(BuildContext context) {
    final compact = MediaQuery.sizeOf(context).width < 600;
    final color = orangeIcon ? wizardOrange : wizardText;
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          width: compact ? 50 : 76,
          height: compact ? 50 : 76,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: orangeIcon
                ? const Color(0xFFFFF0E7)
                : const Color(0xFFEAF3FF),
            borderRadius: BorderRadius.circular(14),
          ),
          child: Icon(icon, color: color, size: compact ? 28 : 39),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                style: TextStyle(
                  color: wizardText,
                  fontSize: compact ? 22 : 29,
                  fontWeight: FontWeight.w900,
                  height: 1.05,
                ),
              ),
              const SizedBox(height: 7),
              Text(
                subtitle,
                style: const TextStyle(
                  color: wizardMuted,
                  fontSize: 14,
                  height: 1.35,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class WizardFieldLabel extends StatelessWidget {
  const WizardFieldLabel(this.text, {super.key, this.required = false});
  final String text;
  final bool required;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(left: 2, bottom: 7),
      child: RichText(
        text: TextSpan(
          style: const TextStyle(
            color: wizardText,
            fontSize: 15,
            fontWeight: FontWeight.w800,
          ),
          children: [
            TextSpan(text: text),
            if (required)
              const TextSpan(
                text: ' *',
                style: TextStyle(color: Colors.red),
              ),
          ],
        ),
      ),
    );
  }
}

InputDecoration wizardInput({
  required String hint,
  IconData? icon,
  Widget? suffix,
  String? prefixText,
  bool readOnly = false,
}) {
  return InputDecoration(
    hintText: hint,
    hintStyle: const TextStyle(color: Color(0xFF93A0B9), fontSize: 15),
    prefixIcon: icon == null
        ? null
        : Icon(icon, color: const Color(0xFF365785), size: 24),
    prefixIconConstraints: const BoxConstraints(minWidth: 55),
    suffixIcon: suffix,
    prefixText: prefixText,
    prefixStyle: const TextStyle(
      color: wizardText,
      fontWeight: FontWeight.w700,
    ),
    filled: true,
    fillColor: readOnly ? const Color(0xFFF8FAFD) : Colors.white,
    contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 17),
    enabledBorder: OutlineInputBorder(
      borderRadius: BorderRadius.circular(11),
      borderSide: const BorderSide(color: wizardBorder, width: 1.2),
    ),
    focusedBorder: OutlineInputBorder(
      borderRadius: BorderRadius.circular(11),
      borderSide: const BorderSide(color: wizardBlue, width: 1.5),
    ),
    errorBorder: OutlineInputBorder(
      borderRadius: BorderRadius.circular(11),
      borderSide: const BorderSide(color: Colors.redAccent),
    ),
    focusedErrorBorder: OutlineInputBorder(
      borderRadius: BorderRadius.circular(11),
      borderSide: const BorderSide(color: Colors.redAccent, width: 1.5),
    ),
  );
}

class WizardPrimaryButton extends StatelessWidget {
  const WizardPrimaryButton({
    super.key,
    required this.label,
    required this.onPressed,
    this.loading = false,
    this.icon = Icons.arrow_forward_rounded,
    this.blue = false,
  });

  final String label;
  final VoidCallback? onPressed;
  final bool loading;
  final IconData icon;
  final bool blue;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 62,
      child: DecoratedBox(
        decoration: BoxDecoration(
          gradient: LinearGradient(
            colors: blue
                ? const [Color(0xFF062763), Color(0xFF083E92)]
                : const [Color(0xFFFF7600), Color(0xFFFF5800)],
          ),
          borderRadius: BorderRadius.circular(11),
        ),
        child: Material(
          color: Colors.transparent,
          child: InkWell(
            borderRadius: BorderRadius.circular(11),
            onTap: loading ? null : onPressed,
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 22),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  if (loading) ...[
                    const SizedBox(
                      width: 20,
                      height: 20,
                      child: CircularProgressIndicator(
                        strokeWidth: 2.2,
                        color: Colors.white,
                      ),
                    ),
                    const SizedBox(width: 10),
                  ],
                  Expanded(
                    child: Text(
                      label,
                      textAlign: TextAlign.center,
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 19,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ),
                  if (!loading) Icon(icon, color: Colors.white, size: 30),
                ],
              ),
            ),
          ),
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
    this.icon = Icons.arrow_back_rounded,
  });
  final String label;
  final VoidCallback? onPressed;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 62,
      child: OutlinedButton.icon(
        onPressed: onPressed,
        icon: Icon(icon, color: wizardText, size: 28),
        label: Text(
          label,
          maxLines: 1,
          softWrap: false,
          overflow: TextOverflow.ellipsis,
          style: const TextStyle(
            color: wizardText,
            fontSize: 18,
            fontWeight: FontWeight.w800,
          ),
        ),
        style: OutlinedButton.styleFrom(
          side: const BorderSide(color: Color(0xFFB8C7DF)),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(11),
          ),
          backgroundColor: Colors.white,
        ),
      ),
    );
  }
}

class WizardInfoBox extends StatelessWidget {
  const WizardInfoBox({
    super.key,
    required this.child,
    this.icon = Icons.info_rounded,
  });
  final Widget child;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: wizardSoftBlue,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0xFFCCE2FF)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: const Color(0xFF1378E8), size: 28),
          const SizedBox(width: 12),
          Expanded(child: child),
        ],
      ),
    );
  }
}

class WizardChoiceCard extends StatelessWidget {
  const WizardChoiceCard({
    super.key,
    required this.selected,
    required this.title,
    required this.subtitle,
    required this.icon,
    required this.onTap,
    this.orange = true,
    this.leading,
  });

  final bool selected;
  final String title;
  final String subtitle;
  final IconData icon;
  final VoidCallback onTap;
  final bool orange;
  final Widget? leading;

  @override
  Widget build(BuildContext context) {
    final accent = orange ? wizardOrange : wizardBlue;
    final radio = Container(
      width: 22,
      height: 22,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        border: Border.all(
          color: selected ? accent : const Color(0xFF9BABBF),
          width: 2,
        ),
      ),
      child: selected
          ? Center(
              child: Container(
                width: 11,
                height: 11,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: accent,
                ),
              ),
            )
          : null,
    );
    final iconBubble =
        leading ??
        Container(
          width: 44,
          height: 44,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            color: accent.withValues(alpha: .1),
          ),
          child: Icon(icon, color: accent, size: 23),
        );
    final texts = Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        Text(
          title,
          style: const TextStyle(
            color: wizardText,
            fontSize: 14.5,
            fontWeight: FontWeight.w900,
          ),
        ),
        const SizedBox(height: 3),
        Text(
          subtitle,
          style: const TextStyle(
            color: wizardMuted,
            fontSize: 12.5,
            height: 1.3,
          ),
        ),
      ],
    );
    return Material(
      color: Colors.transparent,
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: onTap,
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 160),
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: selected ? accent.withValues(alpha: .055) : Colors.white,
            borderRadius: BorderRadius.circular(12),
            border: Border.all(
              color: selected ? accent : wizardBorder,
              width: selected ? 1.8 : 1.2,
            ),
          ),
          child: LayoutBuilder(
            builder: (context, constraints) {
              // Sur les écrans étroits, un Row avec une icône fixe + le texte
              // en Expanded peut réduire la largeur disponible pour le texte à
              // presque 0, ce qui force Flutter à couper mot par mot, voire
              // lettre par lettre. On passe alors en disposition verticale.
              final horizontal = constraints.maxWidth >= 250;
              if (horizontal) {
                return Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Padding(
                      padding: const EdgeInsets.only(top: 2),
                      child: radio,
                    ),
                    const SizedBox(width: 10),
                    iconBubble,
                    const SizedBox(width: 12),
                    Expanded(child: texts),
                  ],
                );
              }
              return Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [iconBubble, radio],
                  ),
                  const SizedBox(height: 10),
                  texts,
                ],
              );
            },
          ),
        ),
      ),
    );
  }
}

class WizardSelectField extends StatelessWidget {
  const WizardSelectField({
    super.key,
    required this.label,
    required this.value,
    required this.hint,
    required this.icon,
    required this.onTap,
    this.required = false,
    this.leading,
  });

  final String label;
  final String? value;
  final String hint;
  final IconData icon;
  final VoidCallback onTap;
  final bool required;
  final Widget? leading;

  @override
  Widget build(BuildContext context) {
    final hasValue = value != null && value!.trim().isNotEmpty;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        // Hauteur réservée pour 2 lignes de label : quand deux champs sont
        // côte à côte (ex. "Catégorie principale" / "Zone commerciale
        // principale"), un label plus long qui passe sur 2 lignes ne doit
        // pas décaler vers le bas la boîte du champ le plus court.
        SizedBox(
          height: 46,
          child: Align(
            alignment: Alignment.bottomLeft,
            child: WizardFieldLabel(label, required: required),
          ),
        ),
        InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(11),
          child: Container(
            height: 58,
            padding: const EdgeInsets.symmetric(horizontal: 14),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(11),
              border: Border.all(color: wizardBorder, width: 1.2),
            ),
            child: Row(
              children: [
                leading ?? Icon(icon, color: const Color(0xFF365785), size: 24),
                const SizedBox(width: 14),
                Expanded(
                  child: Text(
                    hasValue ? value! : hint,
                    style: TextStyle(
                      color: hasValue ? wizardText : const Color(0xFF93A0B9),
                      fontSize: 15.5,
                      fontWeight: hasValue ? FontWeight.w700 : FontWeight.w400,
                    ),
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
                const Icon(
                  Icons.keyboard_arrow_down_rounded,
                  color: wizardText,
                  size: 27,
                ),
              ],
            ),
          ),
        ),
      ],
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
  State<_WizardSelectorSheet<T>> createState() =>
      _WizardSelectorSheetState<T>();
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
        : widget.items
              .where((e) => widget.label(e).toLowerCase().contains(q))
              .toList();
    return SafeArea(
      top: false,
      child: Container(
        constraints: BoxConstraints(
          maxHeight: MediaQuery.sizeOf(context).height * .72,
        ),
        padding: const EdgeInsets.fromLTRB(18, 10, 18, 18),
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(26)),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 46,
              height: 5,
              decoration: BoxDecoration(
                color: const Color(0xFFD4DAE4),
                borderRadius: BorderRadius.circular(99),
              ),
            ),
            const SizedBox(height: 16),
            Row(
              children: [
                Expanded(
                  child: Text(
                    widget.title,
                    style: const TextStyle(
                      color: wizardText,
                      fontSize: 20,
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
              const SizedBox(height: 8),
              TextField(
                controller: _search,
                onChanged: (_) => setState(() {}),
                decoration: wizardInput(
                  hint: 'Rechercher...',
                  icon: Icons.search_rounded,
                ),
              ),
              const SizedBox(height: 10),
            ],
            Flexible(
              child: ListView.separated(
                shrinkWrap: true,
                itemCount: filtered.length,
                separatorBuilder: (_, __) => const Divider(height: 1),
                itemBuilder: (_, index) {
                  final item = filtered[index];
                  final selected = item == widget.selected;
                  return ListTile(
                    contentPadding: const EdgeInsets.symmetric(
                      horizontal: 4,
                      vertical: 4,
                    ),
                    leading: widget.leading?.call(item),
                    title: Text(
                      widget.label(item),
                      style: TextStyle(
                        color: wizardText,
                        fontSize: 16,
                        fontWeight: selected
                            ? FontWeight.w900
                            : FontWeight.w600,
                      ),
                    ),
                    trailing: selected
                        ? const Icon(
                            Icons.check_circle_rounded,
                            color: wizardBlue,
                          )
                        : null,
                    onTap: () => Navigator.pop(context, item),
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

class _RoundBackButton extends StatelessWidget {
  const _RoundBackButton({this.onPressed});
  final VoidCallback? onPressed;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white.withValues(alpha: .08),
      shape: const CircleBorder(),
      child: InkWell(
        customBorder: const CircleBorder(),
        onTap: onPressed ?? () => Navigator.maybePop(context),
        child: Container(
          width: 50,
          height: 50,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            border: Border.all(color: Colors.white.withValues(alpha: .13)),
          ),
          child: const Icon(
            Icons.arrow_back_rounded,
            color: Colors.white,
            size: 30,
          ),
        ),
      ),
    );
  }
}
