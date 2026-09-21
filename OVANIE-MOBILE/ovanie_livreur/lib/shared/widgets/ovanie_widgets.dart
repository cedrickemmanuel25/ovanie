import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import '../../app/theme.dart';
import 'onboarding_identity.dart';
import 'phone_format.dart';
export 'phone_format.dart';

/// Bouton d'action principal, cohérent avec les autres applications OVANIE.
class OvaniePrimaryButton extends StatelessWidget {
  const OvaniePrimaryButton({
    super.key,
    required this.label,
    required this.onPressed,
    this.loading = false,
    this.height = 56,
  });

  final String label;
  final VoidCallback? onPressed;
  final bool loading;
  final double height;

  @override
  Widget build(BuildContext context) {
    final enabled = !loading && onPressed != null;
    return SizedBox(
      height: height,
      width: double.infinity,
      child: DecoratedBox(
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(28),
          boxShadow: enabled
              ? [
                  BoxShadow(
                    color: OvanieColors.green.withValues(alpha: .10),
                    blurRadius: 10,
                    offset: const Offset(0, 8),
                  ),
                ]
              : null,
        ),
        child: ElevatedButton(
          onPressed: loading ? null : onPressed,
          style: ElevatedButton.styleFrom(
            backgroundColor: OvanieColors.green,
            foregroundColor: Colors.white,
            disabledBackgroundColor: OvanieColors.green.withValues(alpha: .45),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(28),
            ),
            elevation: 0,
          ),
          child: loading
              ? const SizedBox.square(
                  dimension: 20,
                  child: CircularProgressIndicator(
                    strokeWidth: 2.2,
                    color: Colors.white,
                  ),
                )
              : Row(
                  children: [
                    const SizedBox(width: 20),
                    Expanded(
                      child: Text(
                        label,
                        textAlign: TextAlign.center,
                        style: GoogleFonts.plusJakartaSans(
                          fontSize: 15,
                          fontWeight: FontWeight.w700,
                          color: Colors.white,
                        ),
                      ),
                    ),
                    const Icon(Icons.arrow_forward_rounded, size: 20),
                  ],
                ),
        ),
      ),
    );
  }
}

/// Encart d'erreur discret réutilisé sur tous les formulaires.
class OvanieErrorBox extends StatelessWidget {
  const OvanieErrorBox(this.message, {super.key});
  final String? message;

  @override
  Widget build(BuildContext context) {
    if (message == null || message!.trim().isEmpty) {
      return const SizedBox.shrink();
    }
    return Container(
      width: double.infinity,
      margin: const EdgeInsets.only(bottom: 14),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: OvanieColors.danger.withValues(alpha: .07),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: OvanieColors.danger.withValues(alpha: .18)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 28,
            height: 28,
            decoration: BoxDecoration(
              color: OvanieColors.danger.withValues(alpha: .14),
              shape: BoxShape.circle,
            ),
            child: const Icon(
              Icons.priority_high_rounded,
              size: 16,
              color: OvanieColors.danger,
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Padding(
              padding: const EdgeInsets.only(top: 4),
              child: Text(
                message!,
                style: const TextStyle(
                  color: OvanieColors.danger,
                  height: 1.35,
                  fontSize: 13.5,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// Encart d'information neutre (bleu-vert) pour les messages de statut.
class OvanieInfoBox extends StatelessWidget {
  const OvanieInfoBox({
    super.key,
    required this.child,
    this.icon = Icons.info_rounded,
    this.color = OvanieColors.greenDark,
    this.background = OvanieColors.greenLight,
  });

  final Widget child;
  final IconData icon;
  final Color color;
  final Color background;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(18),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 36,
            height: 36,
            decoration: BoxDecoration(
              color: color.withValues(alpha: .14),
              shape: BoxShape.circle,
            ),
            child: Icon(icon, color: color, size: 20),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Padding(
              padding: const EdgeInsets.only(top: 6),
              child: child,
            ),
          ),
        ],
      ),
    );
  }
}

/// En-tête de section réutilisée sur les écrans d'inscription et de résumé.
class OvanieSectionHeading extends StatelessWidget {
  const OvanieSectionHeading({super.key, required this.title, this.subtitle});

  final String title;
  final String? subtitle;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          title,
          style: GoogleFonts.plusJakartaSans(
            color: OvanieColors.text,
            fontSize: 22,
            fontWeight: FontWeight.w800,
            letterSpacing: -.3,
            height: 1.15,
          ),
        ),
        if (subtitle != null) ...[
          const SizedBox(height: 8),
          Text(
            subtitle!,
            style: const TextStyle(
              color: OvanieColors.muted,
              fontSize: 14.5,
              height: 1.45,
            ),
          ),
        ],
      ],
    );
  }
}

/// Défile verticalement si le contenu dépasse l'écran, sinon le centre.
/// Évite que les écrans courts (connexion, vérification...) restent collés
/// en haut avec un grand vide en dessous.
class OvanieCenteredScroll extends StatelessWidget {
  const OvanieCenteredScroll({
    super.key,
    required this.child,
    this.padding = const EdgeInsets.fromLTRB(24, 24, 24, 24),
    this.physics,
  });

  final Widget child;
  final EdgeInsetsGeometry padding;
  final ScrollPhysics? physics;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        return SingleChildScrollView(
          physics: physics,
          padding: padding,
          child: ConstrainedBox(
            constraints: BoxConstraints(
              minHeight: constraints.maxHeight - padding.vertical,
            ),
            child: IntrinsicHeight(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [child],
              ),
            ),
          ),
        );
      },
    );
  }
}

/// Corps d'écran standard pour le tunnel d'inscription : contenu défilant
/// ancré en haut (jamais centré dans le vide) et bouton d'action toujours
/// visible dans un pied de page fixe, séparé par une ligne discrète — le
/// motif utilisé par la quasi-totalité des apps de livraison sérieuses,
/// plutôt qu'un bouton qui remonte au milieu de l'écran sur un formulaire
/// court.
class OvanieWizardBody extends StatelessWidget {
  const OvanieWizardBody({
    super.key,
    required this.content,
    required this.footer,
    this.padding = const EdgeInsets.fromLTRB(18, 20, 18, 24),
  });

  final List<Widget> content;
  final List<Widget> footer;
  final EdgeInsetsGeometry padding;

  @override
  Widget build(BuildContext context) {
    final header = content.isNotEmpty && content.first is OvanieStepHeader
        ? content.first as OvanieStepHeader
        : null;
    return Column(
      children: [
        Expanded(
          child: SingleChildScrollView(
            keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                if (header != null) ...[
                  DriverProgress(step: header.step, total: header.total),
                  const SizedBox(height: 20),
                ],
                Container(
                  padding: padding,
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(26),
                    boxShadow: [
                      BoxShadow(
                        color: OvanieColors.green.withValues(alpha: .035),
                        blurRadius: 24,
                        offset: const Offset(0, 8),
                      ),
                    ],
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      if (header != null)
                        OvanieSectionHeading(title: header.title),
                      ...content.skip(header == null ? 0 : 1),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
        Padding(
          padding: const EdgeInsets.fromLTRB(20, 10, 20, 16),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: footer,
          ),
        ),
      ],
    );
  }
}

class OvanieFormBody extends StatelessWidget {
  const OvanieFormBody({
    super.key,
    required this.children,
    this.padding = const EdgeInsets.fromLTRB(24, 24, 24, 24),
  });

  final List<Widget> children;
  final EdgeInsetsGeometry padding;

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(14, 0, 14, 20),
      keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
      child: Container(
        padding: padding,
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(24),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: children,
        ),
      ),
    );
  }
}

InputDecoration ovanieInputDecoration({
  required String hint,
  String? label,
  IconData? icon,
}) {
  return InputDecoration(
    labelText: label,
    hintText: hint,
    prefixIcon: icon == null
        ? null
        : Padding(
            padding: const EdgeInsets.only(left: 4, right: 4),
            child: Icon(icon, color: OvanieColors.greenDark, size: 22),
          ),
  );
}

/// Puce sélectionnable réutilisée pour les zones, disponibilités et véhicules.
class OvanieChoiceChip extends StatelessWidget {
  const OvanieChoiceChip({
    super.key,
    required this.label,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Semantics(
    selected: selected,
    button: true,
    child: Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(16),
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 180),
          constraints: const BoxConstraints(minHeight: 48),
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 12),
          decoration: BoxDecoration(
            color: selected ? OvanieColors.greenLight : Colors.white,
            borderRadius: BorderRadius.circular(16),
            border: Border.all(
              color: selected ? OvanieColors.green : OvanieColors.border,
              width: selected ? 1.5 : 1,
            ),
          ),
          child: Text(
            label,
            textAlign: TextAlign.center,
            style: TextStyle(
              color: selected ? OvanieColors.greenDark : OvanieColors.text,
              fontWeight: FontWeight.w700,
              fontSize: 13,
            ),
          ),
        ),
      ),
    ),
  );
}

/// Champ en lecture seule pour les informations déjà enregistrées par
/// OVANIE Logistics (nom, prénom, téléphone du livreur).
class OvanieReadOnlyField extends StatelessWidget {
  const OvanieReadOnlyField({
    super.key,
    required this.label,
    required this.value,
    this.icon = Icons.person_outline_rounded,
  });

  final String label;
  final String value;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: const TextStyle(
            color: OvanieColors.text,
            fontSize: 13.5,
            fontWeight: FontWeight.w700,
          ),
        ),
        const SizedBox(height: 8),
        Container(
          width: double.infinity,
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 15),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(15),
            border: Border.all(color: OvanieColors.border),
          ),
          child: Row(
            children: [
              Icon(icon, size: 19, color: OvanieColors.muted),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  value,
                  style: const TextStyle(
                    color: OvanieColors.text,
                    fontSize: 15,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

/// Petit drapeau ivoirien (orange / blanc / vert) utilisé dans les champs
/// de téléphone, sans dépendre d'un asset.
class OvanieFlagBadge extends StatelessWidget {
  const OvanieFlagBadge({super.key});

  @override
  Widget build(BuildContext context) {
    return ClipRRect(
      borderRadius: BorderRadius.circular(3),
      child: SizedBox(
        width: 22,
        height: 16,
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: const [
            Expanded(child: ColoredBox(color: Color(0xFFF77F00))),
            Expanded(child: ColoredBox(color: Colors.white)),
            Expanded(child: ColoredBox(color: OvanieColors.green)),
          ],
        ),
      ),
    );
  }
}

/// Champ téléphone réutilisable : drapeau + indicatif +225 + numéro,
/// utilisable en saisie ou en lecture seule.
class OvaniePhoneField extends StatelessWidget {
  const OvaniePhoneField({
    super.key,
    this.controller,
    this.readOnly = false,
    this.value,
    this.hint = '07 00 00 00 00',
    this.validator,
    this.onFieldSubmitted,
  });

  final TextEditingController? controller;
  final bool readOnly;
  final String? value;
  final String hint;
  final String? Function(String?)? validator;
  final void Function(String?)? onFieldSubmitted;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(15),
        border: Border.all(color: OvanieColors.border),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: .03),
            blurRadius: 10,
            offset: const Offset(0, 3),
          ),
        ],
      ),
      child: Row(
        children: [
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 15),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                const OvanieFlagBadge(),
                const SizedBox(width: 8),
                const Text(
                  '+225',
                  style: TextStyle(
                    color: OvanieColors.text,
                    fontWeight: FontWeight.w700,
                    fontSize: 15,
                  ),
                ),
                if (!readOnly) ...[
                  const SizedBox(width: 4),
                  const Icon(
                    Icons.keyboard_arrow_down_rounded,
                    size: 18,
                    color: OvanieColors.muted,
                  ),
                ],
              ],
            ),
          ),
          Container(width: 1, height: 26, color: OvanieColors.border),
          Expanded(
            child: readOnly
                ? Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 14),
                    child: Text(
                      formatDriverPhone(value ?? ''),
                      style: const TextStyle(
                        color: OvanieColors.text,
                        fontWeight: FontWeight.w700,
                        fontSize: 15,
                      ),
                    ),
                  )
                : TextFormField(
                    controller: controller,
                    keyboardType: TextInputType.phone,
                    inputFormatters: const [DriverPhoneFormatter()],
                    autofillHints: const [
                      AutofillHints.telephoneNumberNational,
                    ],
                    textInputAction: TextInputAction.done,
                    validator: validator,
                    onFieldSubmitted: onFieldSubmitted,
                    style: const TextStyle(
                      color: OvanieColors.text,
                      fontWeight: FontWeight.w700,
                      fontSize: 15,
                    ),
                    decoration: InputDecoration(
                      hintText: hint,
                      filled: false,
                      border: InputBorder.none,
                      enabledBorder: InputBorder.none,
                      focusedBorder: InputBorder.none,
                      errorBorder: InputBorder.none,
                      contentPadding: const EdgeInsets.symmetric(
                        horizontal: 14,
                        vertical: 15,
                      ),
                    ),
                  ),
          ),
        ],
      ),
    );
  }
}

/// Logotype "OVANIE" (vert foncé) / "Livreur" (vert vif) réutilisé sur les
/// écrans d'authentification et d'inscription.
class OvanieWordmark extends StatelessWidget {
  const OvanieWordmark({
    super.key,
    this.fontSize = 30,
    this.crossAxisAlignment = CrossAxisAlignment.start,
  });

  final double fontSize;
  final CrossAxisAlignment crossAxisAlignment;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: crossAxisAlignment,
      mainAxisSize: MainAxisSize.min,
      children: [
        Text(
          'OVANIE',
          style: GoogleFonts.plusJakartaSans(
            color: OvanieColors.text,
            fontSize: fontSize,
            fontWeight: FontWeight.w800,
            letterSpacing: -.3,
            height: 1.05,
          ),
        ),
        Text(
          'Livreur',
          style: GoogleFonts.plusJakartaSans(
            color: OvanieColors.green,
            fontSize: fontSize,
            fontWeight: FontWeight.w800,
            letterSpacing: -.3,
            height: 1.05,
          ),
        ),
      ],
    );
  }
}

/// Scaffold partagé : image "hero" (camion) surmontée d'une feuille blanche
/// à coins arrondis contenant le contenu réel de l'écran. Utilisé sur les
/// 8 écrans d'authentification / inscription pour une identité visuelle
/// cohérente.
class OvanieHeroScaffold extends StatelessWidget {
  const OvanieHeroScaffold({
    super.key,
    required this.child,
    this.heroHeightFactor = 0.25,
    this.showBack = false,
    this.appBarTitle,
    this.showWordmark = true,
    this.showTagline = true,
    this.wordmarkAlignment = CrossAxisAlignment.start,
    this.keepHeroWithKeyboard = false,
  });

  final Widget child;
  final double heroHeightFactor;
  final bool showBack;
  final String? appBarTitle;
  final bool showWordmark;
  final bool showTagline;
  final CrossAxisAlignment wordmarkAlignment;
  final bool keepHeroWithKeyboard;

  @override
  Widget build(BuildContext context) {
    final keyboardOpen = MediaQuery.viewInsetsOf(context).bottom > 0;
    return Scaffold(
      backgroundColor: const Color(0xFFEDFAF3),
      body: SafeArea(
        child: Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 600),
            child: LayoutBuilder(
              builder: (context, constraints) {
                final compact = keyboardOpen || constraints.maxHeight < 500;
                return Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    if (showBack || appBarTitle != null)
                      Padding(
                        padding: EdgeInsets.symmetric(
                          horizontal: 12,
                          vertical: showBack ? 4 : 16,
                        ),
                        child: Row(
                          children: [
                            if (showBack)
                              IconButton(
                                tooltip: 'Retour',
                                onPressed: () =>
                                    Navigator.of(context).maybePop(),
                                icon: const Icon(Icons.arrow_back_rounded),
                              ),
                            if (appBarTitle != null)
                              Expanded(
                                child: Text(
                                  appBarTitle!,
                                  style: const TextStyle(
                                    fontSize: 16,
                                    fontWeight: FontWeight.w700,
                                  ),
                                ),
                              ),
                          ],
                        ),
                      ),
                    if (!compact || keepHeroWithKeyboard)
                      DriverHero(
                        height: compact
                            ? (constraints.maxHeight * .22).clamp(48.0, 96.0)
                            : (constraints.maxHeight * heroHeightFactor).clamp(
                                125.0,
                                225.0,
                              ),
                        showWordmark: showWordmark && !compact,
                        showTagline: showTagline && !compact,
                        alignment: wordmarkAlignment,
                      ),
                    Expanded(child: child),
                  ],
                );
              },
            ),
          ),
        ),
      ),
    );
  }
}

/// Stepper horizontal à 5 noeuds pour le tunnel d'inscription (remplace
/// l'ancienne barre de progression segmentée).
class OvanieStepHeader extends StatelessWidget {
  const OvanieStepHeader({
    super.key,
    required this.step,
    required this.total,
    required this.title,
  });

  final int step;
  final int total;
  final String title;

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      DriverProgress(step: step, total: total),
      const SizedBox(height: 20),
      OvanieSectionHeading(title: title),
    ],
  );
}
