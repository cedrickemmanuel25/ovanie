import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

/// Système de design OVANIE Vendeur : une seule palette, une seule police,
/// les mêmes composants sur tous les écrans de démarrage et de connexion.
class Ov {
  Ov._();

  // Couleurs de la marque.
  static const navy = Color(0xFF041A3A);
  static const navyDeep = Color(0xFF020E22);
  static const navySoft = Color(0xFF0B2C5F);
  static const blue = Color(0xFF0759C7);
  static const orange = Color(0xFFFF6500);
  static const orangeDeep = Color(0xFFE85400);

  // Surfaces et textes.
  static const page = Color(0xFFF4F7FB);
  static const surface = Colors.white;
  static const text = Color(0xFF0D1F3C);
  static const muted = Color(0xFF55657D);
  static const border = Color(0xFFDCE4EE);
  static const fieldFill = Color(0xFFF8FAFD);

  // États.
  static const success = Color(0xFF12805C);
  static const successSoft = Color(0xFFE8F6F0);
  static const danger = Color(0xFFC8261B);
  static const dangerSoft = Color(0xFFFDECEA);
  static const infoSoft = Color(0xFFEAF2FE);

  static const font = 'PlusJakartaSans';

  // Espacements (grille de 4) et rayons.
  static const double gutter = 24;
  static const double radius = 10;
  static const double radiusSheet = 20;
  static const double control = 52;

  // Durées d'animation partagées.
  static const fast = Duration(milliseconds: 160);
  static const medium = Duration(milliseconds: 320);
  static const slow = Duration(milliseconds: 520);

  static const heroGradient = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: <Color>[navySoft, navy, navyDeep],
    stops: <double>[0, .55, 1],
  );

  /// Thème des écrans de démarrage et de connexion.
  static ThemeData theme(ThemeData base) {
    final text = base.textTheme.apply(
      fontFamily: font,
      bodyColor: Ov.text,
      displayColor: Ov.text,
    );
    OutlineInputBorder border(Color color, [double width = 1.2]) =>
        OutlineInputBorder(
          borderRadius: BorderRadius.circular(radius),
          borderSide: BorderSide(color: color, width: width),
        );

    return base.copyWith(
      scaffoldBackgroundColor: surface,
      textTheme: text,
      primaryTextTheme: text,
      colorScheme: base.colorScheme.copyWith(
        primary: blue,
        secondary: orange,
        error: danger,
        surface: surface,
      ),
      textSelectionTheme: const TextSelectionThemeData(
        cursorColor: blue,
        selectionHandleColor: blue,
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: surface,
        isDense: false,
        contentPadding:
            const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
        hintStyle: const TextStyle(
          fontFamily: font,
          color: Color(0xFF8795A8),
          fontSize: 15,
          fontWeight: FontWeight.w500,
        ),
        errorStyle: const TextStyle(
          fontFamily: font,
          color: danger,
          fontSize: 12.5,
          fontWeight: FontWeight.w600,
          height: 1.3,
        ),
        errorMaxLines: 3,
        prefixIconColor: muted,
        suffixIconColor: muted,
        border: border(Ov.border),
        enabledBorder: border(Ov.border),
        disabledBorder: border(Ov.border),
        focusedBorder: border(blue, 1.8),
        errorBorder: border(danger),
        focusedErrorBorder: border(danger, 1.8),
      ),
    );
  }
}

/// Applique la police et le thème OVANIE à un écran.
class OvTheme extends StatelessWidget {
  const OvTheme({super.key, required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Theme(
      data: Ov.theme(Theme.of(context)),
      child: DefaultTextStyle.merge(
        style: const TextStyle(fontFamily: Ov.font, color: Ov.text),
        child: child,
      ),
    );
  }
}

/// Vrai quand le téléphone demande de réduire les animations.
bool ovReduceMotion(BuildContext context) =>
    MediaQuery.maybeOf(context)?.disableAnimations ?? false;

/// Apparition douce (fondu + léger glissement), décalable pour un effet en cascade.
class OvReveal extends StatefulWidget {
  const OvReveal({
    super.key,
    required this.child,
    this.delay = Duration.zero,
    this.offset = 18,
  });

  final Widget child;
  final Duration delay;
  final double offset;

  @override
  State<OvReveal> createState() => _OvRevealState();
}

class _OvRevealState extends State<OvReveal>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller =
      AnimationController(vsync: this, duration: Ov.slow);
  late final Animation<double> _curve =
      CurvedAnimation(parent: _controller, curve: Curves.easeOutCubic);

  @override
  void initState() {
    super.initState();
    Future<void>.delayed(widget.delay, () {
      if (mounted) _controller.forward();
    });
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (ovReduceMotion(context)) _controller.value = 1;
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: _curve,
      child: widget.child,
      builder: (context, child) => Opacity(
        opacity: _curve.value,
        child: Transform.translate(
          offset: Offset(0, (1 - _curve.value) * widget.offset),
          child: child,
        ),
      ),
    );
  }
}

/// Bouton principal (orange) ou secondaire (contour), avec état de chargement.
class OvButton extends StatelessWidget {
  const OvButton({
    super.key,
    required this.label,
    required this.onPressed,
    this.icon,
    this.loading = false,
    this.secondary = false,
    this.onDark = false,
  });

  final String label;
  final VoidCallback? onPressed;
  final IconData? icon;
  final bool loading;
  final bool secondary;

  /// Bouton secondaire posé sur un fond sombre.
  final bool onDark;

  @override
  Widget build(BuildContext context) {
    final enabled = onPressed != null && !loading;
    final foreground = secondary ? (onDark ? Colors.white : Ov.navy) : Colors.white;

    final content = Row(
      mainAxisAlignment: MainAxisAlignment.center,
      mainAxisSize: MainAxisSize.min,
      children: <Widget>[
        if (loading) ...<Widget>[
          SizedBox.square(
            dimension: 20,
            child: CircularProgressIndicator(strokeWidth: 2.4, color: foreground),
          ),
          const SizedBox(width: 12),
        ],
        Flexible(
          child: Text(
            label,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(
              fontFamily: Ov.font,
              color: foreground,
              fontSize: 16,
              fontWeight: FontWeight.w700,
              letterSpacing: .1,
            ),
          ),
        ),
        if (icon != null && !loading) ...<Widget>[
          const SizedBox(width: 10),
          Icon(icon, color: foreground, size: 20),
        ],
      ],
    );

    return Semantics(
      button: true,
      enabled: enabled,
      label: label,
      excludeSemantics: true,
      child: AnimatedOpacity(
        duration: Ov.fast,
        opacity: enabled || loading ? 1 : .5,
        child: Container(
          height: Ov.control,
          width: double.infinity,
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(Ov.radius),
            color: secondary
                ? (onDark ? Colors.white.withValues(alpha: .08) : Ov.surface)
                : Ov.orange,
            border: secondary
                ? Border.all(
                    color: onDark
                        ? Colors.white.withValues(alpha: .38)
                        : Ov.navy,
                    width: 1.2,
                  )
                : null,
          ),
          child: Material(
            color: Colors.transparent,
            child: InkWell(
              borderRadius: BorderRadius.circular(Ov.radius),
              onTap: enabled
                  ? () {
                      HapticFeedback.selectionClick();
                      onPressed!();
                    }
                  : null,
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 18),
                child: Center(child: content),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

/// Champ de saisie avec son libellé toujours visible au-dessus.
class OvField extends StatelessWidget {
  const OvField({
    super.key,
    required this.label,
    required this.controller,
    this.hint,
    this.icon,
    this.suffix,
    this.obscure = false,
    this.enabled = true,
    this.readOnly = false,
    this.keyboardType,
    this.textInputAction,
    this.autofillHints,
    this.validator,
    this.onSubmitted,
    this.onChanged,
    this.helper,
  });

  final String label;
  final TextEditingController controller;
  final String? hint;
  final IconData? icon;
  final Widget? suffix;
  final bool obscure;
  final bool enabled;
  final bool readOnly;
  final TextInputType? keyboardType;
  final TextInputAction? textInputAction;
  final Iterable<String>? autofillHints;
  final FormFieldValidator<String>? validator;
  final ValueChanged<String>? onSubmitted;
  final ValueChanged<String>? onChanged;
  final String? helper;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Padding(
          padding: const EdgeInsets.only(left: 2, bottom: 8),
          child: Text(
            label,
            style: const TextStyle(
              color: Ov.text,
              fontSize: 14,
              fontWeight: FontWeight.w700,
            ),
          ),
        ),
        TextFormField(
          controller: controller,
          obscureText: obscure,
          enabled: enabled,
          readOnly: readOnly,
          keyboardType: keyboardType,
          textInputAction: textInputAction,
          autofillHints: autofillHints,
          validator: validator,
          onFieldSubmitted: onSubmitted,
          onChanged: onChanged,
          autovalidateMode: AutovalidateMode.onUserInteraction,
          style: TextStyle(
            fontFamily: Ov.font,
            color: readOnly ? Ov.muted : Ov.text,
            fontSize: 16,
            fontWeight: FontWeight.w600,
          ),
          decoration: InputDecoration(
            hintText: hint,
            prefixIcon: icon == null ? null : Icon(icon, size: 22),
            suffixIcon: suffix,
            fillColor: readOnly ? const Color(0xFFEFF3F8) : Ov.surface,
          ),
        ),
        if (helper != null)
          Padding(
            padding: const EdgeInsets.only(left: 2, top: 7),
            child: Text(
              helper!,
              style: const TextStyle(color: Ov.muted, fontSize: 12.5, height: 1.35),
            ),
          ),
      ],
    );
  }
}

/// Bouton « œil » pour afficher ou masquer un mot de passe.
class OvPasswordToggle extends StatelessWidget {
  const OvPasswordToggle({super.key, required this.hidden, required this.onTap});

  final bool hidden;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return IconButton(
      onPressed: onTap,
      tooltip: hidden ? 'Afficher le mot de passe' : 'Masquer le mot de passe',
      icon: Icon(
        hidden ? Icons.visibility_outlined : Icons.visibility_off_outlined,
        size: 22,
      ),
    );
  }
}

enum OvNoticeKind { error, success, info }

/// Message d'erreur, de confirmation ou d'information.
class OvNotice extends StatelessWidget {
  const OvNotice(this.message, {super.key, this.kind = OvNoticeKind.error});

  final String message;
  final OvNoticeKind kind;

  @override
  Widget build(BuildContext context) {
    final (Color background, Color foreground, IconData icon) = switch (kind) {
      OvNoticeKind.error => (Ov.dangerSoft, Ov.danger, Icons.error_outline_rounded),
      OvNoticeKind.success => (Ov.successSoft, Ov.success, Icons.check_circle_outline_rounded),
      OvNoticeKind.info => (Ov.infoSoft, Ov.blue, Icons.info_outline_rounded),
    };

    return Semantics(
      liveRegion: true,
      child: Container(
        width: double.infinity,
        padding: const EdgeInsets.fromLTRB(14, 12, 14, 12),
        decoration: BoxDecoration(
          color: background,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: foreground.withValues(alpha: .22)),
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Icon(icon, color: foreground, size: 20),
            const SizedBox(width: 10),
            Expanded(
              child: Text(
                message,
                style: TextStyle(
                  color: kind == OvNoticeKind.error ? Ov.danger : Ov.text,
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

/// Bouton retour. Sur une photo, il devient blanc translucide.
class OvBackButton extends StatelessWidget {
  const OvBackButton({super.key, this.onPhoto = false});

  final bool onPhoto;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: onPhoto ? Colors.black.withValues(alpha: .35) : Ov.page,
      shape: CircleBorder(
        side: BorderSide(color: onPhoto ? Colors.white.withValues(alpha: .35) : Ov.border),
      ),
      child: IconButton(
        onPressed: () => Navigator.of(context).maybePop(),
        tooltip: 'Retour',
        constraints: const BoxConstraints.tightFor(width: 44, height: 44),
        icon: Icon(Icons.arrow_back_rounded, color: onPhoto ? Colors.white : Ov.text, size: 22),
      ),
    );
  }
}

/// Signature de la marque : emblème sur pastille bleu nuit, « OVANIE » et « Vendeur ».
class OvBrand extends StatelessWidget {
  const OvBrand({super.key, this.size = 40});

  final double size;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      label: 'OVANIE Vendeur',
      excludeSemantics: true,
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          OvMark(size: size),
          SizedBox(width: size * .28),
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: <Widget>[
              Text(
                'OVANIE',
                style: TextStyle(
                  color: Ov.navy,
                  fontSize: size * .46,
                  fontWeight: FontWeight.w800,
                  letterSpacing: .6,
                  height: 1.05,
                ),
              ),
              Text(
                'Vendeur',
                style: TextStyle(
                  color: Ov.orangeDeep,
                  fontSize: size * .32,
                  fontWeight: FontWeight.w700,
                  height: 1.15,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

/// Emblème OVANIE sur sa pastille bleu nuit (la même que l'icône de l'application).
class OvMark extends StatelessWidget {
  const OvMark({super.key, this.size = 40});

  final double size;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: size,
      height: size,
      padding: EdgeInsets.all(size * .14),
      decoration: BoxDecoration(
        gradient: Ov.heroGradient,
        borderRadius: BorderRadius.circular(size * .26),
      ),
      child: Image.asset('assets/images/ovanie_mark.png', fit: BoxFit.contain),
    );
  }
}

/// Ligne discrète de réassurance en bas des écrans.
class OvSecureFooter extends StatelessWidget {
  const OvSecureFooter({super.key, this.label = 'Connexion sécurisée OVANIE'});

  final String label;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: <Widget>[
        const Icon(Icons.verified_user_outlined, color: Ov.success, size: 18),
        const SizedBox(width: 8),
        Flexible(
          child: Text(
            label,
            style: const TextStyle(color: Ov.muted, fontSize: 12.5, fontWeight: FontWeight.w600),
          ),
        ),
      ],
    );
  }
}

/// Structure commune des écrans de connexion : une photo réelle en bandeau avec
/// le logo OVANIE, puis le titre et le formulaire sur fond blanc.
class OvAuthScaffold extends StatelessWidget {
  const OvAuthScaffold({
    super.key,
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.children,
    this.showBack = true,
    this.iconColor = Ov.blue,
    this.iconBackground = Ov.infoSoft,
  });

  /// Conservés pour les écrans existants ; le bandeau photo remplace le pictogramme.
  final IconData icon;
  final Color iconColor;
  final Color iconBackground;

  final String title;
  final String subtitle;
  final List<Widget> children;
  final bool showBack;

  @override
  Widget build(BuildContext context) {
    final padding = MediaQuery.paddingOf(context);
    final headerHeight = padding.top + 168;

    return OvTheme(
      child: AnnotatedRegion<SystemUiOverlayStyle>(
        value: SystemUiOverlayStyle.light.copyWith(
          statusBarColor: Colors.transparent,
          systemNavigationBarColor: Ov.surface,
          systemNavigationBarIconBrightness: Brightness.dark,
        ),
        child: Scaffold(
          backgroundColor: Ov.surface,
          body: LayoutBuilder(
            builder: (context, viewport) => SingleChildScrollView(
              keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
              child: ConstrainedBox(
                constraints: BoxConstraints(minHeight: viewport.maxHeight),
                child: IntrinsicHeight(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: <Widget>[
                      SizedBox(
                        height: headerHeight,
                        child: Stack(
                          fit: StackFit.expand,
                          children: <Widget>[
                            Image.asset(
                              'assets/images/auth_header.jpg',
                              fit: BoxFit.cover,
                              alignment: Alignment.centerRight,
                              excludeFromSemantics: true,
                            ),
                            // Voile bleu nuit : il assure la lisibilité du logo.
                            const DecoratedBox(
                              decoration: BoxDecoration(
                                gradient: LinearGradient(
                                  begin: Alignment.centerLeft,
                                  end: Alignment.centerRight,
                                  colors: <Color>[Color(0xF2041A3A), Color(0xB3041A3A), Color(0x33041A3A)],
                                  stops: <double>[0, .55, 1],
                                ),
                              ),
                            ),
                            Padding(
                              padding: EdgeInsets.fromLTRB(Ov.gutter - 4, padding.top + 10, Ov.gutter, 0),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: <Widget>[
                                  SizedBox(
                                    height: 44,
                                    child: showBack ? const OvBackButton(onPhoto: true) : null,
                                  ),
                                  const Spacer(),
                                  Padding(
                                    padding: const EdgeInsets.only(left: 4, bottom: 22),
                                    child: Image.asset(
                                      'assets/images/ovanie_logo.png',
                                      height: 58,
                                      semanticLabel: 'OVANIE',
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ],
                        ),
                      ),
                      Expanded(
                        child: Padding(
                          padding: EdgeInsets.fromLTRB(Ov.gutter, 26, Ov.gutter, padding.bottom + 20),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: <Widget>[
                              Text(
                                title,
                                style: const TextStyle(
                                  color: Ov.text,
                                  fontSize: 25,
                                  fontWeight: FontWeight.w700,
                                  height: 1.2,
                                  letterSpacing: -.3,
                                ),
                              ),
                              const SizedBox(height: 6),
                              Text(
                                subtitle,
                                style: const TextStyle(
                                  color: Ov.muted,
                                  fontSize: 14.5,
                                  height: 1.5,
                                  fontWeight: FontWeight.w500,
                                ),
                              ),
                              const SizedBox(height: 24),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.stretch,
                                  children: children,
                                ),
                              ),
                            ],
                          ),
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
    );
  }
}

/// Transition en fondu entre deux écrans (utilisée à la sortie du lancement).
Route<T> ovFadeRoute<T>(Widget page) {
  return PageRouteBuilder<T>(
    transitionDuration: const Duration(milliseconds: 420),
    pageBuilder: (_, __, ___) => page,
    transitionsBuilder: (_, animation, __, child) => FadeTransition(
      opacity: CurvedAnimation(parent: animation, curve: Curves.easeOut),
      child: child,
    ),
  );
}

/// Ouvre un lien externe (WhatsApp, téléphone) avec l'application du téléphone.
Future<bool> ovOpenUrl(String url) async {
  try {
    final opened = await const MethodChannel('ovanie/external_url')
        .invokeMethod<bool>('openUrl', <String, String>{'url': url});
    return opened == true;
  } catch (_) {
    return false;
  }
}
