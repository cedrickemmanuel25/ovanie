import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

/// Composants des écrans de démarrage, de connexion et d'ouverture de boutique,
/// repris des maquettes « OVANIE Vendeur » : photo de magasin en en-tête,
/// feuille blanche arrondie, bouton principal orange en pilule.
class Mk {
  Mk._();

  static const navy = Color(0xFF0A1B4F);
  static const blue = Color(0xFF0B4FC4);
  static const blueDeep = Color(0xFF062B7E);
  static const orange = Color(0xFFFF6A00);
  static const orangeLight = Color(0xFFFF8F1F);
  static const muted = Color(0xFF6B7896);
  static const hint = Color(0xFF8A95AE);
  static const icon = Color(0xFF24376B);
  static const border = Color(0xFFD6DEEC);
  static const page = Color(0xFFF5F8FE);
  static const softBlue = Color(0xFFEAF2FE);
  static const softOrange = Color(0xFFFFF5EE);
  static const readOnlyFill = Color(0xFFEEF1F7);
  static const danger = Color(0xFFC8261B);
  static const dangerSoft = Color(0xFFFDECEA);
  static const success = Color(0xFF12805C);
  static const successSoft = Color(0xFFE8F6F0);

  static const font = 'PlusJakartaSans';

  /// Largeur maximale du contenu : sur tablette, l'écran reste au format téléphone.
  static const double maxWidth = 520;

  static const orangeGradient = LinearGradient(
    begin: Alignment.topCenter,
    end: Alignment.bottomCenter,
    colors: <Color>[orangeLight, Color(0xFFFF5A00)],
  );

  static const blueGradient = LinearGradient(
    begin: Alignment.topCenter,
    end: Alignment.bottomCenter,
    colors: <Color>[Color(0xFF0B46B4), blueDeep],
  );

  static const cardShadow = <BoxShadow>[
    BoxShadow(color: Color(0x1A0A1B4F), blurRadius: 24, offset: Offset(0, 8)),
  ];
}

/// Police et couleurs des maquettes pour tout un écran.
class MkTheme extends StatelessWidget {
  const MkTheme({super.key, required this.child, this.lightStatusBar = true});

  final Widget child;

  /// Icônes de la barre d'état en blanc (écran qui commence par une photo).
  final bool lightStatusBar;

  @override
  Widget build(BuildContext context) {
    final base = Theme.of(context);
    // Les maquettes n'espacent pas les lettres : on annule l'espacement du thème Material.
    final text = base.textTheme.apply(fontFamily: Mk.font, bodyColor: Mk.navy, displayColor: Mk.navy, letterSpacingFactor: 0);
    final overlay = (lightStatusBar ? SystemUiOverlayStyle.light : SystemUiOverlayStyle.dark).copyWith(
      statusBarColor: Colors.transparent,
      systemNavigationBarColor: Colors.white,
      systemNavigationBarIconBrightness: Brightness.dark,
    );
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: overlay,
      child: Theme(
        data: base.copyWith(
          textTheme: text,
          primaryTextTheme: text,
          colorScheme: base.colorScheme.copyWith(primary: Mk.blue, secondary: Mk.orange, error: Mk.danger),
          textSelectionTheme: const TextSelectionThemeData(cursorColor: Mk.blue, selectionHandleColor: Mk.blue),
        ),
        child: DefaultTextStyle.merge(
          style: const TextStyle(fontFamily: Mk.font, color: Mk.navy, letterSpacing: 0),
          child: child,
        ),
      ),
    );
  }
}

/// Photo d'en-tête d'une maquette, affichée sur toute la largeur.
///
/// Le logo et les titres font partie de la photo. Derrière la barre d'état, la
/// première ligne de la photo est étirée pour que le logo ne passe pas dessous.
class MkPhoto extends StatelessWidget {
  const MkPhoto({super.key, required this.name, required this.width, required this.aspect, this.label});

  /// Nom du fichier dans `assets/images/mockups/`, sans extension.
  final String name;
  final double width;

  /// Largeur divisée par la hauteur de la photo.
  final double aspect;

  /// Texte lu par les lecteurs d'écran à la place de la photo.
  final String? label;

  static String asset(String name) => 'assets/images/mockups/$name.jpg';

  @override
  Widget build(BuildContext context) {
    final top = MediaQuery.paddingOf(context).top;
    return Semantics(
      label: label,
      image: label != null,
      child: ExcludeSemantics(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            if (top > 0)
              Image.asset(
                'assets/images/mockups/${name}_top.png',
                width: width,
                height: top,
                fit: BoxFit.fill,
              ),
            Image.asset(asset(name), width: width, height: width / aspect, fit: BoxFit.fill),
          ],
        ),
      ),
    );
  }
}

/// Zone tactile invisible posée sur la flèche « retour » dessinée dans la photo.
class MkPhotoBack extends StatelessWidget {
  const MkPhotoBack({super.key, required this.onTap, this.size = 64});

  final VoidCallback onTap;
  final double size;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      button: true,
      label: 'Retour',
      child: Tooltip(
        message: 'Retour',
        child: Material(
          color: Colors.transparent,
          shape: const CircleBorder(),
          clipBehavior: Clip.antiAlias,
          child: InkWell(
            onTap: onTap,
            child: SizedBox(width: size, height: size),
          ),
        ),
      ),
    );
  }
}

/// Bouton principal en pilule : orange (défaut) ou bleu, avec flèche.
class MkButton extends StatelessWidget {
  const MkButton({
    super.key,
    required this.label,
    required this.onPressed,
    this.loading = false,
    this.blue = false,
    this.arrow = true,
    this.height = 46,
  });

  final String label;
  final VoidCallback? onPressed;
  final bool loading;
  final bool blue;
  final bool arrow;
  final double height;

  @override
  Widget build(BuildContext context) {
    final enabled = onPressed != null && !loading;
    return Semantics(
      button: true,
      enabled: enabled,
      label: label,
      excludeSemantics: true,
      child: Opacity(
        opacity: enabled || loading ? 1 : .55,
        child: Container(
          height: height,
          decoration: BoxDecoration(
            gradient: blue ? Mk.blueGradient : Mk.orangeGradient,
            borderRadius: BorderRadius.circular(height / 2),
            boxShadow: <BoxShadow>[
              BoxShadow(
                color: (blue ? Mk.blueDeep : Mk.orange).withValues(alpha: .28),
                blurRadius: 14,
                offset: const Offset(0, 6),
              ),
            ],
          ),
          child: Material(
            color: Colors.transparent,
            child: InkWell(
              borderRadius: BorderRadius.circular(height / 2),
              onTap: enabled
                  ? () {
                      HapticFeedback.selectionClick();
                      onPressed!();
                    }
                  : null,
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 14),
                child: Center(
                  child: FittedBox(
                    fit: BoxFit.scaleDown,
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: <Widget>[
                        if (loading) ...<Widget>[
                          const SizedBox.square(
                            dimension: 20,
                            child: CircularProgressIndicator(strokeWidth: 2.4, color: Colors.white),
                          ),
                          const SizedBox(width: 12),
                        ],
                        Text(
                          label,
                          maxLines: 1,
                          style: const TextStyle(
                            fontFamily: Mk.font,
                            color: Colors.white,
                            fontSize: 15,
                            letterSpacing: 0,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                        if (arrow && !loading) ...<Widget>[
                          const SizedBox(width: 8),
                          const Icon(Icons.arrow_forward_rounded, color: Colors.white, size: 21),
                        ],
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

/// Bouton secondaire en pilule : fond blanc, contour bleu.
class MkOutlineButton extends StatelessWidget {
  const MkOutlineButton({super.key, required this.label, required this.onPressed, this.icon, this.loading = false, this.height = 46});

  final String label;
  final VoidCallback? onPressed;
  final IconData? icon;
  final bool loading;
  final double height;

  @override
  Widget build(BuildContext context) {
    final enabled = onPressed != null && !loading;
    return Semantics(
      button: true,
      enabled: enabled,
      label: label,
      excludeSemantics: true,
      child: Opacity(
        opacity: enabled || loading ? 1 : .55,
        child: Material(
          color: Colors.white,
          shape: StadiumBorder(side: const BorderSide(color: Mk.blue, width: 1.6)),
          clipBehavior: Clip.antiAlias,
          child: InkWell(
            onTap: enabled ? onPressed : null,
            child: SizedBox(
              height: height,
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 14),
                child: Center(
                  child: FittedBox(
                    fit: BoxFit.scaleDown,
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: <Widget>[
                        if (loading) ...<Widget>[
                          const SizedBox.square(
                            dimension: 18,
                            child: CircularProgressIndicator(strokeWidth: 2.2, color: Mk.blue),
                          ),
                          const SizedBox(width: 10),
                        ] else if (icon != null) ...<Widget>[
                          Icon(icon, color: Mk.blue, size: 21),
                          const SizedBox(width: 10),
                        ],
                        Text(
                          label,
                          maxLines: 1,
                          style: const TextStyle(
                            fontFamily: Mk.font,
                            color: Mk.blue,
                            fontSize: 15,
                            letterSpacing: 0,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

/// Libellé d'un champ, avec l'astérisque orange des champs obligatoires.
class MkLabel extends StatelessWidget {
  const MkLabel(this.text, {super.key, this.required = false});

  final String text;
  final bool required;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 6),
      child: Text.rich(
        TextSpan(
          text: text,
          children: <InlineSpan>[
            if (required)
              const TextSpan(text: ' *', style: TextStyle(color: Mk.orange, fontWeight: FontWeight.w800)),
          ],
        ),
        style: const TextStyle(color: Mk.navy, fontSize: 13, fontWeight: FontWeight.w700),
      ),
    );
  }
}

OutlineInputBorder _mkBorder(Color color, [double width = 1.3]) => OutlineInputBorder(
      borderRadius: BorderRadius.circular(12),
      borderSide: BorderSide(color: color, width: width),
    );

/// Décoration des champs de saisie : contour fin, icône bleu nuit à gauche.
InputDecoration mkInput({required String hint, IconData? icon, Widget? suffix, bool readOnly = false}) {
  return InputDecoration(
    hintText: hint,
    hintStyle: const TextStyle(fontFamily: Mk.font, color: Mk.hint, fontSize: 13.5, fontWeight: FontWeight.w500, letterSpacing: 0),
    prefixIcon: icon == null ? null : Icon(icon, color: Mk.icon, size: 20),
    prefixIconConstraints: const BoxConstraints(minWidth: 44, minHeight: 44),
    suffixIcon: suffix,
    filled: true,
    fillColor: readOnly ? Mk.readOnlyFill : Colors.white,
    isDense: true,
    errorMaxLines: 3,
    errorStyle: const TextStyle(fontFamily: Mk.font, color: Mk.danger, fontSize: 12, fontWeight: FontWeight.w600, height: 1.3, letterSpacing: 0),
    helperStyle: const TextStyle(fontFamily: Mk.font, color: Mk.muted, fontSize: 12, letterSpacing: 0),
    contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
    border: _mkBorder(Mk.border),
    enabledBorder: _mkBorder(readOnly ? Mk.readOnlyFill : Mk.border),
    disabledBorder: _mkBorder(Mk.border),
    focusedBorder: _mkBorder(readOnly ? Mk.readOnlyFill : Mk.blue, 1.8),
    errorBorder: _mkBorder(Mk.danger),
    focusedErrorBorder: _mkBorder(Mk.danger, 1.8),
  );
}

const mkFieldText = TextStyle(fontFamily: Mk.font, color: Mk.navy, fontSize: 13.5, fontWeight: FontWeight.w600, letterSpacing: 0);

/// Bouton « œil » d'un champ mot de passe.
class MkEye extends StatelessWidget {
  const MkEye({super.key, required this.hidden, required this.onTap});

  final bool hidden;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    // Hors de l'ordre de saisie : « Suivant » sur le clavier passe au champ d'après, pas à l'œil.
    return ExcludeFocus(
      child: IconButton(
        onPressed: onTap,
        tooltip: hidden ? 'Afficher le mot de passe' : 'Masquer le mot de passe',
        icon: Icon(hidden ? Icons.visibility_rounded : Icons.visibility_off_rounded, color: Mk.icon, size: 23),
      ),
    );
  }
}

/// Champ qui ouvre une liste de choix : même aspect qu'un champ de saisie, avec chevron.
class MkSelect extends StatelessWidget {
  const MkSelect({
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
  final VoidCallback? onTap;
  final bool required;
  final Widget? leading;

  @override
  Widget build(BuildContext context) {
    final hasValue = value != null && value!.trim().isNotEmpty;
    final enabled = onTap != null;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        MkLabel(label, required: required),
        Semantics(
          button: true,
          enabled: enabled,
          label: '$label : ${hasValue ? value : hint}',
          excludeSemantics: true,
          child: Material(
            color: Colors.white,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(12),
              side: const BorderSide(color: Mk.border, width: 1.3),
            ),
            clipBehavior: Clip.antiAlias,
            child: InkWell(
              onTap: onTap,
              // Pas de voile gris quand la liste se referme et rend la main au champ.
              focusColor: Colors.transparent,
              child: ConstrainedBox(
                constraints: const BoxConstraints(minHeight: 44),
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
                  child: Row(
                    children: <Widget>[
                      leading ?? Icon(icon, color: Mk.icon, size: 20),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Text(
                          hasValue ? value! : hint,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: hasValue
                              ? mkFieldText
                              : const TextStyle(color: Mk.hint, fontSize: 13.5, fontWeight: FontWeight.w500),
                        ),
                      ),
                      const SizedBox(width: 6),
                      Icon(Icons.keyboard_arrow_down_rounded, color: enabled ? Mk.navy : Mk.hint, size: 24),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ),
      ],
    );
  }
}

/// Carte de choix exclusif : bouton radio, pictogramme, titre et précision.
class MkChoiceCard extends StatelessWidget {
  const MkChoiceCard({
    super.key,
    required this.selected,
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.onTap,
  });

  final bool selected;
  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      inMutuallyExclusiveGroup: true,
      checked: selected,
      button: true,
      label: '$title. $subtitle',
      excludeSemantics: true,
      child: Material(
        color: selected ? Mk.softOrange : Colors.white,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(12),
          side: BorderSide(color: selected ? Mk.orange : Mk.border, width: selected ? 1.6 : 1.3),
        ),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: onTap,
          child: Padding(
            padding: const EdgeInsets.fromLTRB(8, 10, 6, 10),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Container(
                  width: 16,
                  height: 16,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    color: Colors.white,
                    border: Border.all(color: selected ? Mk.orange : Mk.hint, width: selected ? 4.5 : 1.4),
                  ),
                ),
                const SizedBox(width: 5),
                Padding(
                  padding: const EdgeInsets.only(top: 6),
                  child: Icon(icon, color: Mk.icon, size: 23),
                ),
                const SizedBox(width: 7),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      Text(
                        title,
                        style: const TextStyle(color: Mk.navy, fontSize: 12.5, fontWeight: FontWeight.w700, height: 1.25),
                      ),
                      const SizedBox(height: 3),
                      Text(
                        subtitle,
                        style: const TextStyle(color: Mk.muted, fontSize: 10.5, fontWeight: FontWeight.w500, height: 1.3),
                      ),
                    ],
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

/// Deux cartes de choix côte à côte, de même hauteur.
class MkChoiceRow extends StatelessWidget {
  const MkChoiceRow({super.key, required this.first, required this.second});

  final Widget first;
  final Widget second;

  @override
  Widget build(BuildContext context) {
    return IntrinsicHeight(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          Expanded(child: first),
          const SizedBox(width: 8),
          Expanded(child: second),
        ],
      ),
    );
  }
}

/// Encadré bleu clair de réassurance, avec pictogramme rond à gauche.
class MkInfoBox extends StatelessWidget {
  const MkInfoBox({super.key, required this.child, this.icon = Icons.verified_user_rounded});

  final Widget child;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(12, 11, 12, 11),
      decoration: BoxDecoration(color: Mk.softBlue, borderRadius: BorderRadius.circular(12)),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Container(
            width: 34,
            height: 34,
            decoration: const BoxDecoration(color: Color(0xFFD5E5FD), shape: BoxShape.circle),
            child: Icon(icon, color: Mk.blue, size: 19),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: DefaultTextStyle.merge(
              style: const TextStyle(color: Mk.muted, fontSize: 12.5, height: 1.4, fontWeight: FontWeight.w500),
              child: Padding(padding: const EdgeInsets.only(top: 2), child: child),
            ),
          ),
        ],
      ),
    );
  }
}

/// Message d'erreur ou de confirmation.
class MkNotice extends StatelessWidget {
  const MkNotice(this.message, {super.key, this.success = false});

  final String message;
  final bool success;

  @override
  Widget build(BuildContext context) {
    final color = success ? Mk.success : Mk.danger;
    return Semantics(
      liveRegion: true,
      child: Container(
        width: double.infinity,
        padding: const EdgeInsets.fromLTRB(14, 12, 14, 12),
        decoration: BoxDecoration(
          color: success ? Mk.successSoft : Mk.dangerSoft,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: color.withValues(alpha: .25)),
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Icon(success ? Icons.check_circle_outline_rounded : Icons.error_outline_rounded, color: color, size: 20),
            const SizedBox(width: 10),
            Expanded(
              child: Text(
                message,
                style: TextStyle(
                  color: success ? Mk.navy : Mk.danger,
                  fontSize: 13.5,
                  fontWeight: FontWeight.w600,
                  height: 1.4,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Ligne de réassurance centrée en bas d'écran : bouclier bleu et texte gris.
class MkSecureLine extends StatelessWidget {
  const MkSecureLine(this.label, {super.key});

  final String label;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: <Widget>[
        const Icon(Icons.verified_user_rounded, color: Mk.blue, size: 19),
        const SizedBox(width: 8),
        Flexible(
          child: Text(
            label,
            style: const TextStyle(color: Mk.muted, fontSize: 12.5, fontWeight: FontWeight.w500, height: 1.35),
          ),
        ),
      ],
    );
  }
}

/// Pastille ronde bleu nuit cerclée de blanc, à cheval sur la photo et la feuille.
class MkBadge extends StatelessWidget {
  const MkBadge({super.key, required this.icon, required this.size});

  final IconData icon;
  final double size;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: size,
      height: size,
      padding: EdgeInsets.all(size * .09),
      decoration: const BoxDecoration(
        color: Colors.white,
        shape: BoxShape.circle,
        boxShadow: <BoxShadow>[BoxShadow(color: Color(0x1F0A1B4F), blurRadius: 12, offset: Offset(0, 4))],
      ),
      child: DecoratedBox(
        decoration: const BoxDecoration(color: Color(0xFF0B1F5B), shape: BoxShape.circle),
        child: Icon(icon, color: Mk.orange, size: size * .46),
      ),
    );
  }
}

/// Séparateur « ——— texte ——— ».
class MkDivider extends StatelessWidget {
  const MkDivider(this.label, {super.key});

  final String label;

  @override
  Widget build(BuildContext context) {
    // Le texte reste centré : les deux traits se partagent la place restante.
    return LayoutBuilder(
      builder: (context, box) => Row(
        children: <Widget>[
          const Expanded(child: Divider(color: Mk.border, thickness: 1)),
          ConstrainedBox(
            constraints: BoxConstraints(maxWidth: box.maxWidth * .76),
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 12),
              child: Text(
                label,
                textAlign: TextAlign.center,
                style: const TextStyle(color: Mk.muted, fontSize: 13.5, fontWeight: FontWeight.w500),
              ),
            ),
          ),
          const Expanded(child: Divider(color: Mk.border, thickness: 1)),
        ],
      ),
    );
  }
}

/// Cadre en pointillés (zones d'ajout de fichier).
class MkDashedBox extends StatelessWidget {
  const MkDashedBox({
    super.key,
    required this.child,
    this.onTap,
    this.done = false,
    this.orange = false,
    this.padding = const EdgeInsets.all(16),
  });

  final Widget child;
  final VoidCallback? onTap;
  final bool done;

  /// Pointillés et fond orange (zones d'ajout de photo).
  final bool orange;
  final EdgeInsets padding;

  @override
  Widget build(BuildContext context) {
    return CustomPaint(
      foregroundPainter: _DashedPainter(done ? Mk.success : (orange ? Mk.orange : const Color(0xFFAFBCD6))),
      child: Material(
        color: done ? Mk.successSoft : (orange ? Mk.softOrange : const Color(0xFFF7FAFF)),
        borderRadius: BorderRadius.circular(12),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: onTap,
          child: Padding(padding: padding, child: child),
        ),
      ),
    );
  }
}

class _DashedPainter extends CustomPainter {
  _DashedPainter(this.color);

  final Color color;

  @override
  void paint(Canvas canvas, Size size) {
    final path = Path()
      ..addRRect(RRect.fromRectAndRadius((Offset.zero & size).deflate(.6), const Radius.circular(12)));
    final paint = Paint()
      ..color = color
      ..style = PaintingStyle.stroke
      ..strokeWidth = 1.2;
    for (final metric in path.computeMetrics()) {
      var distance = 0.0;
      while (distance < metric.length) {
        canvas.drawPath(metric.extractPath(distance, math.min(distance + 5, metric.length)), paint);
        distance += 9;
      }
    }
  }

  @override
  bool shouldRepaint(_DashedPainter old) => old.color != color;
}

/// Structure des écrans « mot de passe » : flèche retour, photo arrondie avec
/// pastille, titre centré, puis carte blanche contenant le formulaire.
class MkCardAuthScaffold extends StatelessWidget {
  const MkCardAuthScaffold({
    super.key,
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.children,
    this.footer = 'Réinitialisation sécurisée OVANIE',
    this.showBack = true,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final List<Widget> children;
  final String footer;
  final bool showBack;

  @override
  Widget build(BuildContext context) {
    final padding = MediaQuery.paddingOf(context);
    return MkTheme(
      lightStatusBar: false,
      child: Scaffold(
        backgroundColor: Mk.page,
        body: LayoutBuilder(
          builder: (context, viewport) {
            final width = math.min(viewport.maxWidth, Mk.maxWidth);
            final gutter = width * .04;
            final photoWidth = width - gutter * 2;
            final photoHeight = photoWidth * 506 / 866;
            final badge = photoWidth * .172;
            // La pastille dessinée dans la photo est centrée à 96,6 % de sa hauteur.
            final badgeTop = photoHeight * .966 - badge / 2;

            return SingleChildScrollView(
              keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
              child: Center(
                child: ConstrainedBox(
                  constraints: BoxConstraints(maxWidth: width, minHeight: viewport.maxHeight),
                  child: Padding(
                    padding: EdgeInsets.fromLTRB(gutter, padding.top + 4, gutter, padding.bottom + 18),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: <Widget>[
                        SizedBox(
                          height: 48,
                          child: showBack
                              ? Align(
                                  alignment: Alignment.centerLeft,
                                  child: IconButton(
                                    onPressed: () => Navigator.of(context).maybePop(),
                                    tooltip: 'Retour',
                                    padding: EdgeInsets.zero,
                                    constraints: const BoxConstraints.tightFor(width: 48, height: 48),
                                    alignment: Alignment.centerLeft,
                                    icon: const Icon(Icons.arrow_back_ios_new_rounded, color: Mk.navy, size: 24),
                                  ),
                                )
                              : null,
                        ),
                        SizedBox(
                          height: badgeTop + badge,
                          child: Stack(
                            alignment: Alignment.topCenter,
                            children: <Widget>[
                              ClipRRect(
                                borderRadius: BorderRadius.circular(photoWidth * .05),
                                child: Image.asset(
                                  MkPhoto.asset('reset'),
                                  width: photoWidth,
                                  height: photoHeight,
                                  fit: BoxFit.fill,
                                  semanticLabel: 'OVANIE Vendeur',
                                ),
                              ),
                              Positioned(top: badgeTop, child: MkBadge(icon: icon, size: badge)),
                            ],
                          ),
                        ),
                        const SizedBox(height: 14),
                        Text(
                          title,
                          textAlign: TextAlign.center,
                          style: const TextStyle(
                            color: Mk.navy,
                            fontSize: 24,
                            fontWeight: FontWeight.w800,
                            height: 1.2,
                            letterSpacing: -.4,
                          ),
                        ),
                        const SizedBox(height: 8),
                        Padding(
                          padding: const EdgeInsets.symmetric(horizontal: 12),
                          child: Text(
                            subtitle,
                            textAlign: TextAlign.center,
                            style: const TextStyle(color: Mk.muted, fontSize: 13.5, height: 1.4, fontWeight: FontWeight.w500),
                          ),
                        ),
                        const SizedBox(height: 20),
                        Container(
                          padding: const EdgeInsets.fromLTRB(18, 20, 18, 18),
                          decoration: BoxDecoration(
                            color: Colors.white,
                            borderRadius: BorderRadius.circular(22),
                            boxShadow: Mk.cardShadow,
                          ),
                          child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: children),
                        ),
                        const SizedBox(height: 20),
                        MkSecureLine(footer),
                      ],
                    ),
                  ),
                ),
              ),
            );
          },
        ),
      ),
    );
  }
}

/// En-tête blanc des écrans secondaires : retour, titre et sous-titre centrés, logo facultatif.
class MkPageHeader extends StatelessWidget {
  const MkPageHeader({super.key, required this.title, this.subtitle, this.showLogo = false, this.onBack, this.trailing});

  final String title;
  final String? subtitle;
  final bool showLogo;
  final VoidCallback? onBack;

  /// Élément affiché à droite du titre, à la place du logo.
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: const BoxDecoration(
        color: Colors.white,
        border: Border(bottom: BorderSide(color: Color(0xFFE4E9F1))),
      ),
      child: SafeArea(
        bottom: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(4, 6, 4, 8),
          child: Row(
            children: <Widget>[
              IconButton(
                tooltip: 'Retour',
                onPressed: onBack ?? () => Navigator.of(context).maybePop(),
                icon: const Icon(Icons.arrow_back_rounded, color: Mk.navy, size: 26),
              ),
              Expanded(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: <Widget>[
                    FittedBox(
                      fit: BoxFit.scaleDown,
                      child: Text(
                        title,
                        maxLines: 1,
                        style: const TextStyle(color: Mk.navy, fontSize: 18, fontWeight: FontWeight.w800, letterSpacing: -.3),
                      ),
                    ),
                    if (subtitle != null)
                      Padding(
                        padding: const EdgeInsets.only(top: 2),
                        child: Text(
                          subtitle!,
                          textAlign: TextAlign.center,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(color: Mk.muted, fontSize: 12, height: 1.3, fontWeight: FontWeight.w500),
                        ),
                      ),
                  ],
                ),
              ),
              if (trailing != null)
                Padding(padding: const EdgeInsets.only(left: 6, right: 10), child: trailing)
              else if (showLogo)
                Padding(
                  padding: const EdgeInsets.only(left: 6, right: 10),
                  child: Image.asset('assets/images/mockups/logo_navy.png', height: 24, semanticLabel: 'OVANIE Vendeur'),
                )
              else
                const SizedBox(width: 48),
            ],
          ),
        ),
      ),
    );
  }
}

/// Bouton rectangulaire des barres d'action fixées en bas : plein ou à contour.
class MkBarButton extends StatelessWidget {
  const MkBarButton({
    super.key,
    required this.label,
    required this.onTap,
    this.icon,
    this.filled = false,
    this.color = Mk.orange,
    this.loading = false,
    this.height = 48,
  });

  final String label;
  final VoidCallback? onTap;
  final IconData? icon;
  final bool filled;
  final Color color;
  final bool loading;
  final double height;

  @override
  Widget build(BuildContext context) {
    final foreground = filled ? Colors.white : (color == Mk.orange ? const Color(0xFFE85400) : color);
    return Semantics(
      button: true,
      enabled: onTap != null,
      label: label,
      excludeSemantics: true,
      child: Opacity(
        opacity: onTap == null && !loading ? .55 : 1,
        child: Material(
          color: filled ? color : Colors.white,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(10),
            side: BorderSide(color: color, width: 1.4),
          ),
          clipBehavior: Clip.antiAlias,
          child: InkWell(
            onTap: loading ? null : onTap,
            child: SizedBox(
              height: height,
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 8),
                child: Center(
                  child: loading
                      ? SizedBox.square(
                          dimension: 22,
                          child: CircularProgressIndicator(strokeWidth: 2.4, color: foreground),
                        )
                      : FittedBox(
                          fit: BoxFit.scaleDown,
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: <Widget>[
                              if (icon != null) ...<Widget>[
                                Icon(icon, color: foreground, size: 22),
                                const SizedBox(width: 9),
                              ],
                              Text(
                                label,
                                maxLines: 1,
                                style: TextStyle(fontFamily: Mk.font, color: foreground, fontSize: 15, fontWeight: FontWeight.w700),
                              ),
                            ],
                          ),
                        ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

/// Barre blanche fixée en bas d'un écran, avec un ou deux boutons.
class MkActionBar extends StatelessWidget {
  const MkActionBar({super.key, required this.children, this.safeArea = false});

  final List<Widget> children;

  /// Vrai quand la barre est le dernier élément de l'écran (pas de barre d'onglets dessous).
  final bool safeArea;

  @override
  Widget build(BuildContext context) {
    final row = Padding(
      padding: const EdgeInsets.fromLTRB(12, 10, 12, 10),
      child: Row(
        children: <Widget>[
          for (var i = 0; i < children.length; i++) ...<Widget>[
            if (i > 0) const SizedBox(width: 10),
            Expanded(child: children[i]),
          ],
        ],
      ),
    );
    return Container(
      decoration: const BoxDecoration(
        color: Colors.white,
        border: Border(top: BorderSide(color: Color(0xFFE4E9F1))),
      ),
      child: safeArea ? SafeArea(top: false, child: row) : row,
    );
  }
}
