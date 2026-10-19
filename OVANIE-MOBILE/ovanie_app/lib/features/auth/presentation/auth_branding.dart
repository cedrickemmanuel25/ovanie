import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

/// Composants partagés des écrans d'authentification et d'état du compte
/// (connexion, création de compte, mot de passe, session, hors connexion).
///
/// Parti pris des maquettes : photo du magasin et logo en tête, une carte
/// blanche qui la chevauche pour le formulaire, action principale orange.
const Color authNavy = Color(0xFF0A1B4F);
const Color authNavyDeep = Color(0xFF071643);
const Color authMuted = Color(0xFF55607C);
const Color authHint = Color(0xFF6B7591);
const Color authBorder = Color(0xFFD9DFEA);
const Color authFieldFill = Colors.white;
const Color authOrange = Color(0xFFFF6A00);
const Color authBlue = Color(0xFF0B5ED7);
const Color authDanger = Color(0xFFE5231B);
const Color authDangerSoft = Color(0xFFFFF1F1);
const Color authGreen = Color(0xFF16A34A);
const Color authPageBackground = Color(0xFFF4F7FC);
const String authFont = 'PlusJakartaSans';

const String _assets = 'assets/images/auth';

/// Thème local : police des maquettes, sans toucher au reste de l'application.
class OvanieAuthTheme extends StatelessWidget {
  const OvanieAuthTheme({super.key, required this.child, this.lightStatusBar = false});
  final Widget child;
  final bool lightStatusBar;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: (lightStatusBar ? SystemUiOverlayStyle.light : SystemUiOverlayStyle.dark).copyWith(
        statusBarColor: Colors.transparent,
      ),
      child: Theme(
        data: theme.copyWith(
          textTheme: theme.textTheme.apply(fontFamily: authFont),
          primaryTextTheme: theme.primaryTextTheme.apply(fontFamily: authFont),
        ),
        child: DefaultTextStyle.merge(
          style: const TextStyle(fontFamily: authFont),
          child: child,
        ),
      ),
    );
  }
}

/// Logo OVANIE des maquettes (blanc sur fond sombre, bleu nuit sur fond clair).
class OvanieLogo extends StatelessWidget {
  const OvanieLogo({super.key, this.white = false, this.width = 150});
  final bool white;
  final double width;

  @override
  Widget build(BuildContext context) => Image.asset(
    '$_assets/${white ? 'logo_white' : 'logo_navy'}.png',
    width: width,
    fit: BoxFit.contain,
    semanticLabel: 'OVANIE',
  );
}

/// Page d'authentification : photo du rayon et logo en tête, carte blanche
/// qui la chevauche, mention de sécurité dessous.
class OvanieAuthPage extends StatelessWidget {
  const OvanieAuthPage({
    super.key,
    required this.title,
    required this.subtitle,
    required this.child,
    this.onBack,
    this.secureNote = true,
  });

  final String title;
  final String subtitle;
  final Widget child;
  final VoidCallback? onBack;
  final bool secureNote;

  @override
  Widget build(BuildContext context) {
    final padding = MediaQuery.paddingOf(context);
    return OvanieAuthTheme(
      lightStatusBar: true,
      child: Scaffold(
        backgroundColor: authPageBackground,
        body: LayoutBuilder(
          builder: (context, viewport) {
            final width = viewport.maxWidth.clamp(0.0, 560.0);
            // Proportions de la maquette : photo de 941 × 597, carte à 505.
            final photoHeight = width * 597 / 941 + padding.top * .5;
            final cardTop = photoHeight - width * 92 / 941;
            final gutter = (width * .042).clamp(12.0, 22.0);
            return SingleChildScrollView(
              keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
              child: ConstrainedBox(
                constraints: BoxConstraints(minHeight: viewport.maxHeight),
                child: Stack(
                  children: [
                    Positioned(
                      top: 0,
                      left: 0,
                      right: 0,
                      height: photoHeight,
                      child: Image.asset(
                        '$_assets/header.jpg',
                        fit: BoxFit.cover,
                        alignment: Alignment.bottomCenter,
                        semanticLabel: 'OVANIE',
                      ),
                    ),
                    if (onBack != null)
                      Positioned(
                        top: padding.top + 2,
                        left: 4,
                        child: IconButton(
                          tooltip: 'Retour',
                          onPressed: onBack,
                          icon: const Icon(Icons.arrow_back_rounded, color: Colors.white, size: 24),
                        ),
                      ),
                    Padding(
                      padding: EdgeInsets.fromLTRB(gutter, cardTop, gutter, padding.bottom + 18),
                      child: Center(
                        child: ConstrainedBox(
                          constraints: const BoxConstraints(maxWidth: 520),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: [
                              Container(
                                padding: const EdgeInsets.fromLTRB(19, 22, 19, 22),
                                decoration: BoxDecoration(
                                  color: Colors.white,
                                  borderRadius: BorderRadius.circular(18),
                                  boxShadow: const [
                                    BoxShadow(color: Color(0x140A1B4F), blurRadius: 26, offset: Offset(0, 10)),
                                  ],
                                ),
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.stretch,
                                  children: [
                                    FittedBox(
                                      fit: BoxFit.scaleDown,
                                      alignment: Alignment.centerLeft,
                                      child: Text(
                                        title,
                                        style: const TextStyle(
                                          color: authNavy,
                                          fontSize: 28,
                                          height: 1.15,
                                          fontWeight: FontWeight.w800,
                                          letterSpacing: -.8,
                                        ),
                                      ),
                                    ),
                                    const SizedBox(height: 4),
                                    Text(
                                      subtitle,
                                      style: const TextStyle(
                                        color: authMuted,
                                        fontSize: 13.5,
                                        height: 1.35,
                                        fontWeight: FontWeight.w500,
                                      ),
                                    ),
                                    const SizedBox(height: 18),
                                    child,
                                  ],
                                ),
                              ),
                              if (secureNote) ...[const SizedBox(height: 20), const OvanieAuthSecureNote()],
                            ],
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            );
          },
        ),
      ),
    );
  }
}

InputDecoration authDecoration({
  required String hint,
  IconData? icon,
  Widget? suffix,
  bool readOnly = false,
  bool compact = false,
}) {
  OutlineInputBorder border(Color color, [double width = 1.2]) => OutlineInputBorder(
    borderRadius: BorderRadius.circular(11),
    borderSide: BorderSide(color: color, width: width),
  );

  return InputDecoration(
    hintText: hint,
    hintStyle: TextStyle(
      fontFamily: authFont,
      color: authHint,
      fontSize: compact ? 13 : 14,
      fontWeight: FontWeight.w500,
      letterSpacing: 0,
    ),
    prefixIcon: icon == null ? null : Icon(icon, color: authNavy, size: compact ? 20 : 22),
    prefixIconConstraints: BoxConstraints(minWidth: compact ? 36 : 46, minHeight: 46),
    suffixIcon: suffix,
    suffixIconConstraints: const BoxConstraints(minHeight: 46),
    filled: true,
    // Champ en erreur : fond rosé et contour rouge, comme sur les maquettes.
    fillColor: readOnly
        ? const Color(0xFFEEF1F6)
        : WidgetStateColor.resolveWith((states) => states.contains(WidgetState.error) ? authDangerSoft : authFieldFill),
    isDense: true,
    errorMaxLines: 3,
    errorStyle: const TextStyle(
      fontFamily: authFont,
      color: authDanger,
      fontSize: 12.5,
      height: 1.3,
      fontWeight: FontWeight.w500,
    ),
    contentPadding: EdgeInsets.fromLTRB(14, 13, compact ? 6 : 14, 13),
    border: border(authBorder),
    enabledBorder: border(readOnly ? const Color(0xFFDDE2EB) : authBorder),
    disabledBorder: border(const Color(0xFFE6EAF1)),
    focusedBorder: border(readOnly ? const Color(0xFFDDE2EB) : authBlue, 1.6),
    errorBorder: border(authDanger, 1.4),
    focusedErrorBorder: border(authDanger, 1.8),
  );
}

const TextStyle authFieldText = TextStyle(
  fontFamily: authFont,
  color: authNavy,
  fontSize: 14.5,
  fontWeight: FontWeight.w600,
  letterSpacing: 0,
);

class OvanieAuthField extends StatelessWidget {
  final TextEditingController? controller;
  final String? initialValue;
  final String hint;
  final IconData? icon;
  final TextInputType? keyboardType;
  final TextInputAction? textInputAction;
  final bool obscureText;
  final bool readOnly;
  final Widget? suffix;
  final String? Function(String?)? validator;
  final ValueChanged<String>? onSubmitted;
  final List<TextInputFormatter>? inputFormatters;
  final ValueChanged<String>? onChanged;
  final bool enabled;
  final Iterable<String>? autofillHints;
  final TextCapitalization textCapitalization;

  const OvanieAuthField({
    super.key,
    this.controller,
    this.initialValue,
    required this.hint,
    this.icon,
    this.keyboardType,
    this.textInputAction,
    this.obscureText = false,
    this.readOnly = false,
    this.suffix,
    this.validator,
    this.onSubmitted,
    this.inputFormatters,
    this.onChanged,
    this.enabled = true,
    this.autofillHints,
    this.textCapitalization = TextCapitalization.none,
  });

  @override
  Widget build(BuildContext context) {
    return TextFormField(
      controller: controller,
      initialValue: controller == null ? initialValue : null,
      enabled: enabled,
      readOnly: readOnly,
      keyboardType: keyboardType,
      textInputAction: textInputAction,
      textCapitalization: textCapitalization,
      obscureText: obscureText,
      validator: validator,
      onFieldSubmitted: onSubmitted,
      inputFormatters: inputFormatters,
      onChanged: onChanged,
      autofillHints: autofillHints,
      style: readOnly ? authFieldText.copyWith(color: authMuted, fontWeight: FontWeight.w500) : authFieldText,
      decoration: authDecoration(hint: hint, icon: icon, suffix: suffix, readOnly: readOnly),
    );
  }
}

/// Lien « Afficher » / « Masquer » à droite d'un champ mot de passe.
class OvaniePasswordToggle extends StatelessWidget {
  final bool visible;
  final VoidCallback? onPressed;

  /// Œil à la place du texte (écran « Nouveau mot de passe »).
  final bool eye;

  const OvaniePasswordToggle({super.key, required this.visible, required this.onPressed, this.eye = false});

  @override
  Widget build(BuildContext context) {
    // Hors de l'ordre de saisie : « Suivant » passe au champ d'après.
    return ExcludeFocus(
      child: eye
          ? IconButton(
              onPressed: onPressed,
              tooltip: visible ? 'Masquer le mot de passe' : 'Afficher le mot de passe',
              icon: Icon(
                visible ? Icons.visibility_off_outlined : Icons.visibility_outlined,
                color: authMuted,
                size: 22,
              ),
            )
          : TextButton(
              onPressed: onPressed,
              style: TextButton.styleFrom(
                foregroundColor: authBlue,
                padding: const EdgeInsets.symmetric(horizontal: 12),
                minimumSize: const Size(0, 44),
              ),
              child: Text(
                visible ? 'Masquer' : 'Afficher',
                style: const TextStyle(fontFamily: authFont, fontSize: 13.5, fontWeight: FontWeight.w600),
              ),
            ),
    );
  }
}

/// Indicatifs proposés à l'inscription.
const List<(String, String, String)> authCountries = [
  ('+225', 'ci', 'Côte d’Ivoire'),
  ('+221', 'sn', 'Sénégal'),
  ('+223', 'ml', 'Mali'),
  ('+226', 'bf', 'Burkina Faso'),
  ('+233', 'gh', 'Ghana'),
  ('+224', 'gn', 'Guinée'),
];

/// Drapeau dessiné (les drapeaux emoji ne s'affichent pas sur tous les téléphones).
class OvanieFlag extends StatelessWidget {
  const OvanieFlag(this.country, {super.key, this.size = 26, this.round = true});
  final String country;
  final double size;
  final bool round;

  @override
  Widget build(BuildContext context) {
    final box = SizedBox(
      width: round ? size : size * 1.3,
      height: size,
      child: CustomPaint(painter: _FlagPainter(country)),
    );
    return ExcludeSemantics(
      child: round ? ClipOval(child: box) : ClipRRect(borderRadius: BorderRadius.circular(2), child: box),
    );
  }
}

class _FlagPainter extends CustomPainter {
  const _FlagPainter(this.country);
  final String country;

  static const _orange = Color(0xFFFF8200), _green = Color(0xFF009E60), _red = Color(0xFFCE1126);
  static const _yellow = Color(0xFFFCD116), _white = Colors.white;

  @override
  void paint(Canvas canvas, Size size) {
    void vertical(List<Color> colors) {
      final w = size.width / colors.length;
      for (var i = 0; i < colors.length; i++) {
        canvas.drawRect(Rect.fromLTWH(i * w, 0, w + .5, size.height), Paint()..color = colors[i]);
      }
    }

    void horizontal(List<Color> colors) {
      final h = size.height / colors.length;
      for (var i = 0; i < colors.length; i++) {
        canvas.drawRect(Rect.fromLTWH(0, i * h, size.width, h + .5), Paint()..color = colors[i]);
      }
    }

    void star(Color color) {
      final center = size.center(Offset.zero);
      final r = size.height * .2;
      final path = Path();
      for (var i = 0; i < 10; i++) {
        final radius = i.isEven ? r : r * .42;
        final angle = -1.5708 + i * 0.6283;
        final point = center + Offset(radius * math.cos(angle), radius * math.sin(angle));
        i == 0 ? path.moveTo(point.dx, point.dy) : path.lineTo(point.dx, point.dy);
      }
      canvas.drawPath(path..close(), Paint()..color = color);
    }

    switch (country) {
      case 'sn':
        vertical(const [_green, _yellow, _red]);
        star(_green);
      case 'ml':
        vertical(const [_green, _yellow, _red]);
      case 'bf':
        horizontal(const [_red, _green]);
        star(_yellow);
      case 'gh':
        horizontal(const [_red, _yellow, _green]);
        star(Colors.black);
      case 'gn':
        vertical(const [_red, _yellow, _green]);
      default:
        vertical(const [_orange, _white, _green]);
    }
  }

  @override
  bool shouldRepaint(_FlagPainter old) => old.country != country;
}

/// Feuille « Indicatif du pays ». Renvoie l'indicatif choisi.
Future<String?> showAuthCountrySheet(BuildContext context, String selected) {
  FocusManager.instance.primaryFocus?.unfocus();
  return showModalBottomSheet<String>(
    context: context,
    backgroundColor: Colors.white,
    isScrollControlled: true,
    shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(24))),
    builder: (sheet) => OvanieAuthTheme(
      child: SafeArea(
        top: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(12, 10, 12, 12),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Center(
                child: Container(
                  width: 40,
                  height: 5,
                  decoration: BoxDecoration(color: const Color(0xFFC9D1DF), borderRadius: BorderRadius.circular(99)),
                ),
              ),
              const Padding(
                padding: EdgeInsets.fromLTRB(8, 18, 8, 12),
                child: Text(
                  'Indicatif du pays',
                  style: TextStyle(color: authNavy, fontSize: 21, fontWeight: FontWeight.w800, letterSpacing: -.4),
                ),
              ),
              for (final (code, flag, name) in authCountries)
                Semantics(
                  button: true,
                  selected: code == selected,
                  child: Material(
                    color: code == selected ? const Color(0xFFFFF3EA) : Colors.white,
                    borderRadius: BorderRadius.circular(10),
                    clipBehavior: Clip.antiAlias,
                    child: InkWell(
                      onTap: () => Navigator.pop(sheet, code),
                      child: Container(
                        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 10),
                        decoration: BoxDecoration(
                          border: Border(
                            bottom: BorderSide(color: code == selected ? Colors.transparent : const Color(0xFFE8ECF3)),
                          ),
                        ),
                        child: Row(
                          children: [
                            OvanieFlag(flag),
                            const SizedBox(width: 14),
                            Expanded(
                              child: Text(
                                name,
                                style: const TextStyle(color: authNavy, fontSize: 14.5, fontWeight: FontWeight.w500),
                              ),
                            ),
                            Text(
                              code,
                              style: const TextStyle(color: authNavy, fontSize: 14.5, fontWeight: FontWeight.w500),
                            ),
                            SizedBox(
                              width: 40,
                              child: code == selected
                                  ? const Icon(Icons.check_rounded, color: authOrange, size: 24)
                                  : null,
                            ),
                          ],
                        ),
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

class OvaniePhoneField extends StatelessWidget {
  final TextEditingController controller;
  final String countryCode;
  final ValueChanged<String> onCountryChanged;
  final String? Function(String?)? validator;
  final bool enabled;

  const OvaniePhoneField({
    super.key,
    required this.controller,
    required this.countryCode,
    required this.onCountryChanged,
    this.validator,
    this.enabled = true,
  });

  @override
  Widget build(BuildContext context) {
    final flag = authCountries.firstWhere((c) => c.$1 == countryCode, orElse: () => authCountries.first).$2;
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Semantics(
          button: true,
          label: 'Indicatif du pays $countryCode',
          excludeSemantics: true,
          child: Material(
            color: Colors.white,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(11),
              side: const BorderSide(color: authBorder, width: 1.2),
            ),
            clipBehavior: Clip.antiAlias,
            child: InkWell(
              onTap: !enabled
                  ? null
                  : () async {
                      final picked = await showAuthCountrySheet(context, countryCode);
                      if (picked != null) onCountryChanged(picked);
                    },
              child: SizedBox(
                height: 46,
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 8),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      OvanieFlag(flag, size: 14, round: false),
                      const SizedBox(width: 6),
                      Text(countryCode, style: authFieldText.copyWith(fontWeight: FontWeight.w500)),
                      const SizedBox(width: 2),
                      const Icon(Icons.keyboard_arrow_down_rounded, color: authNavy, size: 20),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ),
        const SizedBox(width: 6),
        Expanded(
          child: TextFormField(
            controller: controller,
            enabled: enabled,
            keyboardType: TextInputType.phone,
            textInputAction: TextInputAction.next,
            validator: validator,
            autofillHints: const [AutofillHints.telephoneNumberNational],
            inputFormatters: [FilteringTextInputFormatter.digitsOnly, LengthLimitingTextInputFormatter(12)],
            style: authFieldText,
            decoration: authDecoration(hint: 'Numéro de téléphone', icon: Icons.call_outlined, compact: true),
          ),
        ),
      ],
    );
  }
}

class OvaniePrimaryButton extends StatelessWidget {
  final String label;
  final VoidCallback? onPressed;
  final bool loading;

  /// Libellé affiché pendant le chargement (« Vérification… »), sur fond gris.
  final String? loadingLabel;
  final double height;

  const OvaniePrimaryButton({
    super.key,
    required this.label,
    required this.onPressed,
    this.loading = false,
    this.loadingLabel,
    this.height = 50,
  });

  @override
  Widget build(BuildContext context) {
    final waiting = loading && loadingLabel != null;
    return Semantics(
      button: true,
      enabled: !loading && onPressed != null,
      label: waiting ? loadingLabel : label,
      excludeSemantics: true,
      child: Material(
        color: waiting ? const Color(0xFFA6ADBC) : authOrange,
        borderRadius: BorderRadius.circular(12),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: loading ? null : onPressed,
          child: SizedBox(
            height: height,
            child: Center(
              child: loading && !waiting
                  ? const SizedBox.square(
                      dimension: 22,
                      child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2.4),
                    )
                  : Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        if (waiting) ...[
                          const SizedBox.square(
                            dimension: 20,
                            child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2.6),
                          ),
                          const SizedBox(width: 12),
                        ],
                        Text(
                          waiting ? loadingLabel! : label,
                          style: const TextStyle(
                            fontFamily: authFont,
                            color: Colors.white,
                            fontSize: 16,
                            fontWeight: FontWeight.w700,
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
}

/// Bouton à contour bleu (« Créer un compte »).
class OvanieOutlineButton extends StatelessWidget {
  const OvanieOutlineButton({super.key, required this.label, required this.onPressed});
  final String label;
  final VoidCallback? onPressed;

  @override
  Widget build(BuildContext context) => Semantics(
    button: true,
    enabled: onPressed != null,
    label: label,
    excludeSemantics: true,
    child: Material(
      color: Colors.white,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
        side: const BorderSide(color: authBlue, width: 1.5),
      ),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onPressed,
        child: SizedBox(
          height: 48,
          child: Center(
            child: Text(
              label,
              style: const TextStyle(fontFamily: authFont, color: authBlue, fontSize: 16, fontWeight: FontWeight.w700),
            ),
          ),
        ),
      ),
    ),
  );
}

/// Séparateur « ——— question (lien) ——— ».
class OvanieAuthDivider extends StatelessWidget {
  const OvanieAuthDivider({super.key, required this.question, this.action, this.onTap});
  final String question;
  final String? action;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) => LayoutBuilder(
    builder: (context, box) => Row(
      children: [
        const Expanded(child: Divider(height: 1, color: Color(0xFFD9DFEA))),
        ConstrainedBox(
          constraints: BoxConstraints(maxWidth: box.maxWidth - 36),
          child: FittedBox(
            fit: BoxFit.scaleDown,
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 10),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    question,
                    style: const TextStyle(color: authMuted, fontSize: 13, fontWeight: FontWeight.w500),
                  ),
                  if (action != null)
                    InkWell(
                      onTap: onTap,
                      borderRadius: BorderRadius.circular(6),
                      child: Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 10),
                        child: Text(
                          action!,
                          style: const TextStyle(color: authBlue, fontSize: 13, fontWeight: FontWeight.w700),
                        ),
                      ),
                    ),
                ],
              ),
            ),
          ),
        ),
        const Expanded(child: Divider(height: 1, color: Color(0xFFD9DFEA))),
      ],
    ),
  );
}

/// Ligne « question + lien » centrée (ex. « Vous vous en souvenez ? Retour à la connexion »).
class OvanieAuthSwitchLink extends StatelessWidget {
  final String question;
  final String action;
  final VoidCallback? onTap;

  const OvanieAuthSwitchLink({super.key, required this.question, required this.action, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Wrap(
      alignment: WrapAlignment.center,
      crossAxisAlignment: WrapCrossAlignment.center,
      children: [
        Text(
          question,
          style: const TextStyle(color: authMuted, fontSize: 13, fontWeight: FontWeight.w500),
        ),
        InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(6),
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 10),
            child: Text(
              action,
              style: const TextStyle(color: authBlue, fontSize: 13, fontWeight: FontWeight.w700),
            ),
          ),
        ),
      ],
    );
  }
}

/// Mention de sécurité sous la carte : bouclier vert coché.
class OvanieAuthSecureNote extends StatelessWidget {
  final String label;

  const OvanieAuthSecureNote({super.key, this.label = 'Connexion chiffrée et sécurisée'});

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        const Icon(Icons.verified_user_rounded, color: authGreen, size: 20),
        const SizedBox(width: 8),
        Text(
          label,
          style: const TextStyle(color: authMuted, fontSize: 12.5, fontWeight: FontWeight.w500),
        ),
      ],
    );
  }
}

/// Case à cocher carrée à contour bleu nuit (rouge quand elle manque).
class OvanieAuthCheckbox extends StatelessWidget {
  final bool value;
  final ValueChanged<bool>? onChanged;
  final bool error;

  const OvanieAuthCheckbox({super.key, required this.value, required this.onChanged, this.error = false});

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 22,
      height: 22,
      child: Checkbox(
        value: value,
        activeColor: authOrange,
        side: BorderSide(color: error ? authDanger : authNavy, width: 1.5),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(5)),
        materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
        visualDensity: VisualDensity.compact,
        onChanged: onChanged == null ? null : (v) => onChanged!(v ?? false),
      ),
    );
  }
}

/// Encadré de message (rosé pour une erreur, vert pour une confirmation).
class OvanieAuthMessage extends StatelessWidget {
  final String message;
  final bool success;
  final bool centered;

  const OvanieAuthMessage({super.key, required this.message, this.success = false, this.centered = false});

  @override
  Widget build(BuildContext context) {
    final fg = success ? const Color(0xFF12894A) : authDanger;
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: success ? const Color(0xFFE9F7EF) : const Color(0xFFFDE8E8),
        borderRadius: BorderRadius.circular(9),
        border: Border.all(color: success ? const Color(0xFFBFE6CF) : const Color(0xFFF7C5C3)),
      ),
      child: Text(
        message,
        textAlign: centered ? TextAlign.center : TextAlign.start,
        style: TextStyle(color: fg, fontSize: 12.5, height: 1.4, fontWeight: FontWeight.w500),
      ),
    );
  }
}

/// Encadré d'information : pastille ronde, trait vertical, texte.
class OvanieInfoStrip extends StatelessWidget {
  const OvanieInfoStrip({super.key, required this.icon, required this.text, this.green = true, this.divider = true});
  final IconData icon;
  final String text;
  final bool green;
  final bool divider;

  @override
  Widget build(BuildContext context) {
    final color = green ? const Color(0xFF0F7A3F) : authBlue;
    return Container(
      padding: const EdgeInsets.fromLTRB(14, 12, 14, 12),
      decoration: BoxDecoration(
        color: green ? const Color(0xFFE7F6EC) : const Color(0xFFE8F0FC),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: green ? const Color(0xFFCDEAD7) : const Color(0xFFD3E1F8)),
      ),
      child: Row(
        children: [
          Container(
            width: 38,
            height: 38,
            decoration: BoxDecoration(
              color: green ? const Color(0xFFCDEED9) : const Color(0xFFD2E2FB),
              shape: BoxShape.circle,
            ),
            child: Icon(icon, color: color, size: 21),
          ),
          if (divider) ...[
            const SizedBox(width: 12),
            Container(width: 1, height: 36, color: green ? const Color(0xFFB9DFC8) : const Color(0xFFBCD0F2)),
          ],
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              text,
              style: TextStyle(color: color, fontSize: 13.5, height: 1.4, fontWeight: FontWeight.w500),
            ),
          ),
        ],
      ),
    );
  }
}

/// Écran d'état plein écran : logo, illustration, titre, texte, encadrés et
/// bouton (hors connexion, session expirée, mise à jour requise).
class OvanieStatusPage extends StatelessWidget {
  const OvanieStatusPage({
    super.key,
    required this.art,
    required this.artAspect,
    required this.title,
    required this.message,
    this.extras = const [],
    this.button,
  });

  /// Nom du fichier dans assets/images/auth (sans extension).
  final String art;
  final double artAspect;
  final String title;
  final String message;
  final List<Widget> extras;
  final Widget? button;

  @override
  Widget build(BuildContext context) {
    final padding = MediaQuery.paddingOf(context);
    return OvanieAuthTheme(
      child: Material(
        color: const Color(0xFFF7F9FD),
        child: LayoutBuilder(
          builder: (context, viewport) {
            final width = viewport.maxWidth.clamp(0.0, 560.0);
            return SingleChildScrollView(
              child: ConstrainedBox(
                constraints: BoxConstraints(minHeight: viewport.maxHeight),
                child: Center(
                  child: SizedBox(
                    width: width,
                    child: Column(
                      children: [
                        SizedBox(height: padding.top + width * .085),
                        OvanieLogo(width: width * .37),
                        SizedBox(height: width * .03),
                        // L'illustration se fond dans la page en haut et en bas.
                        ShaderMask(
                          blendMode: BlendMode.dstIn,
                          shaderCallback: (rect) => const LinearGradient(
                            begin: Alignment.topCenter,
                            end: Alignment.bottomCenter,
                            colors: [Colors.transparent, Colors.white, Colors.white, Colors.transparent],
                            stops: [0, .08, .9, 1],
                          ).createShader(rect),
                          child: AspectRatio(
                            aspectRatio: artAspect,
                            child: Image.asset('$_assets/$art.jpg', fit: BoxFit.cover, excludeFromSemantics: true),
                          ),
                        ),
                        Padding(
                          padding: EdgeInsets.fromLTRB(22, 14, 22, padding.bottom + 22),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: [
                              Text(
                                title,
                                textAlign: TextAlign.center,
                                style: const TextStyle(
                                  color: authNavy,
                                  fontSize: 26,
                                  height: 1.2,
                                  fontWeight: FontWeight.w800,
                                  letterSpacing: -.7,
                                ),
                              ),
                              const SizedBox(height: 10),
                              Text(
                                message,
                                textAlign: TextAlign.center,
                                style: const TextStyle(
                                  color: authMuted,
                                  fontSize: 14.5,
                                  height: 1.45,
                                  fontWeight: FontWeight.w500,
                                ),
                              ),
                              for (final extra in extras) ...[const SizedBox(height: 16), extra],
                              if (button != null) ...[const SizedBox(height: 18), button!],
                            ],
                          ),
                        ),
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
