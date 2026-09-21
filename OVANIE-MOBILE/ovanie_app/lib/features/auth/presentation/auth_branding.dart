import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../app/theme.dart';

const Color authNavy = Color(0xFF08265F);
const Color authNavyDeep = Color(0xFF061B4B);
const Color authMuted = Color(0xFF707B98);
const Color authBorder = Color(0xFFDDE2EB);
const Color authFieldFill = Color(0xFFFCFCFD);
const Color authOrange = Color(0xFFFF5100);

class OvanieAuthPage extends StatelessWidget {
  final Widget child;
  final EdgeInsetsGeometry padding;

  const OvanieAuthPage({
    super.key,
    required this.child,
    this.padding = const EdgeInsets.fromLTRB(20, 18, 20, 36),
  });

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      body: SafeArea(
        child: LayoutBuilder(
          builder: (context, constraints) {
            return SingleChildScrollView(
              physics: const BouncingScrollPhysics(),
              keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
              padding: padding,
              child: Center(
                child: ConstrainedBox(
                  constraints: const BoxConstraints(maxWidth: 520),
                  child: child,
                ),
              ),
            );
          },
        ),
      ),
    );
  }
}

class OvanieAuthBackButton extends StatelessWidget {
  final VoidCallback onTap;

  const OvanieAuthBackButton({super.key, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white,
      elevation: 0,
      borderRadius: BorderRadius.circular(999),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(999),
        child: Container(
          width: 46,
          height: 46,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            color: Colors.white,
            border: Border.all(color: const Color(0xFFE4E7ED)),
            boxShadow: [
              BoxShadow(
                color: const Color(0xFF071D4B).withOpacity(.10),
                blurRadius: 16,
                offset: const Offset(0, 5),
              ),
            ],
          ),
          child: const Icon(
            Icons.arrow_back_rounded,
            color: authNavy,
            size: 25,
          ),
        ),
      ),
    );
  }
}

class OvanieAuthTitleBlock extends StatelessWidget {
  final String title;
  final String subtitle;
  final VoidCallback onBack;

  const OvanieAuthTitleBlock({
    super.key,
    required this.title,
    required this.subtitle,
    required this.onBack,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        OvanieAuthBackButton(onTap: onBack),
        const SizedBox(height: 16),
        Text(
          title,
          style: const TextStyle(
            color: authNavy,
            fontSize: 31,
            height: 1.04,
            fontWeight: FontWeight.w900,
            letterSpacing: -.7,
          ),
        ),
        const SizedBox(height: 8),
        Text(
          subtitle,
          style: const TextStyle(
            color: authMuted,
            fontSize: 14.5,
            height: 1.45,
            fontWeight: FontWeight.w500,
          ),
        ),
      ],
    );
  }
}

class OvanieWordmark extends StatelessWidget {
  final double fontSize;
  final Color lightColor;

  const OvanieWordmark({
    super.key,
    this.fontSize = 42,
    this.lightColor = Colors.white,
  });

  @override
  Widget build(BuildContext context) {
    final style = TextStyle(
      color: lightColor,
      fontSize: fontSize,
      height: .95,
      letterSpacing: .5,
      fontWeight: FontWeight.w900,
    );
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Text('OVA', style: style),
        Text('N', style: style.copyWith(color: authOrange)),
        Text('IE', style: style),
      ],
    );
  }
}

class OvanieAuthBrandBanner extends StatelessWidget {
  final bool registerMode;

  const OvanieAuthBrandBanner({super.key, this.registerMode = false});

  @override
  Widget build(BuildContext context) {
    final height = registerMode ? 128.0 : 170.0;
    return Container(
      height: height,
      width: double.infinity,
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(22),
        gradient: const LinearGradient(
          colors: [Color(0xFF0C347F), Color(0xFF061A4A), Color(0xFF08245F)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        boxShadow: [
          BoxShadow(
            color: authNavyDeep.withOpacity(.16),
            blurRadius: 20,
            offset: const Offset(0, 9),
          ),
        ],
      ),
      child: Stack(
        children: [
          const Positioned.fill(
            child: CustomPaint(painter: _ConstructionBackdropPainter()),
          ),
          Positioned(
            left: 22,
            top: registerMode ? 18 : 28,
            right: 18,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                OvanieWordmark(fontSize: registerMode ? 38 : 40),
                SizedBox(height: registerMode ? 12 : 16),
                Text(
                  registerMode
                      ? 'Votre chantier\ncommence ici.'
                      : 'La marketplace BTP, Énergie\net Immobilier de référence\nen Côte d’Ivoire.',
                  style: TextStyle(
                    color: Colors.white,
                    fontSize: registerMode ? 16 : 14.3,
                    height: registerMode ? 1.18 : 1.38,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 10),
                Container(
                  width: 34,
                  height: 2,
                  decoration: BoxDecoration(
                    color: authOrange,
                    borderRadius: BorderRadius.circular(99),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class OvanieAuthCard extends StatelessWidget {
  final Widget child;
  final EdgeInsetsGeometry padding;
  final double radius;

  const OvanieAuthCard({
    super.key,
    required this.child,
    this.padding = const EdgeInsets.all(22),
    this.radius = 22,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: padding,
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(radius),
        border: Border.all(color: const Color(0xFFE8EAF0)),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF0A1D47).withOpacity(.10),
            blurRadius: 28,
            offset: const Offset(0, 11),
          ),
        ],
      ),
      child: child,
    );
  }
}

class OvanieAuthField extends StatelessWidget {
  final TextEditingController controller;
  final String hint;
  final IconData icon;
  final TextInputType? keyboardType;
  final TextInputAction? textInputAction;
  final bool obscureText;
  final Widget? suffix;
  final String? Function(String?)? validator;
  final ValueChanged<String>? onSubmitted;
  final List<TextInputFormatter>? inputFormatters;
  final ValueChanged<String>? onChanged;
  final bool enabled;

  const OvanieAuthField({
    super.key,
    required this.controller,
    required this.hint,
    required this.icon,
    this.keyboardType,
    this.textInputAction,
    this.obscureText = false,
    this.suffix,
    this.validator,
    this.onSubmitted,
    this.inputFormatters,
    this.onChanged,
    this.enabled = true,
  });

  @override
  Widget build(BuildContext context) {
    return TextFormField(
      controller: controller,
      enabled: enabled,
      keyboardType: keyboardType,
      textInputAction: textInputAction,
      obscureText: obscureText,
      validator: validator,
      onFieldSubmitted: onSubmitted,
      inputFormatters: inputFormatters,
      onChanged: onChanged,
      style: const TextStyle(
        color: authNavy,
        fontSize: 14.5,
        fontWeight: FontWeight.w600,
      ),
      decoration: InputDecoration(
        hintText: hint,
        hintStyle: const TextStyle(
          color: Color(0xFF818AA2),
          fontSize: 14.2,
          fontWeight: FontWeight.w500,
        ),
        prefixIcon: Padding(
          padding: const EdgeInsets.fromLTRB(8, 7, 10, 7),
          child: Container(
            width: 42,
            height: 42,
            decoration: BoxDecoration(
              color: const Color(0xFFF1F2F5),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Icon(icon, color: authNavy, size: 22),
          ),
        ),
        prefixIconConstraints: const BoxConstraints(minWidth: 58, minHeight: 56),
        suffixIcon: suffix,
        filled: true,
        fillColor: authFieldFill,
        isDense: true,
        contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 18),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(11),
          borderSide: const BorderSide(color: authBorder, width: 1),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(11),
          borderSide: const BorderSide(color: authNavy, width: 1.25),
        ),
        errorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(11),
          borderSide: const BorderSide(color: OvanieColors.danger),
        ),
        focusedErrorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(11),
          borderSide: const BorderSide(color: OvanieColors.danger, width: 1.25),
        ),
      ),
    );
  }
}

class OvaniePhoneField extends StatelessWidget {
  final TextEditingController controller;
  final String countryCode;
  final ValueChanged<String> onCountryChanged;
  final String? Function(String?)? validator;
  final bool enabled;

  static const List<Map<String, String>> countries = [
    {'code': '+225', 'flag': '🇨🇮', 'name': 'Côte d’Ivoire'},
    {'code': '+221', 'flag': '🇸🇳', 'name': 'Sénégal'},
    {'code': '+223', 'flag': '🇲🇱', 'name': 'Mali'},
    {'code': '+226', 'flag': '🇧🇫', 'name': 'Burkina Faso'},
    {'code': '+233', 'flag': '🇬🇭', 'name': 'Ghana'},
    {'code': '+224', 'flag': '🇬🇳', 'name': 'Guinée'},
  ];

  const OvaniePhoneField({
    super.key,
    required this.controller,
    required this.countryCode,
    required this.onCountryChanged,
    this.validator,
    this.enabled = true,
  });

  Map<String, String> get selectedCountry => countries.firstWhere(
        (country) => country['code'] == countryCode,
        orElse: () => countries.first,
      );

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final compact = constraints.maxWidth < 390;
        final prefixWidth = compact ? 112.0 : 122.0;
        final gap = compact ? 7.0 : 9.0;
        final selected = selectedCountry;

        return Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            SizedBox(
              width: prefixWidth,
              height: 58,
              child: PopupMenuButton<String>(
                enabled: enabled,
                initialValue: countryCode,
                onSelected: onCountryChanged,
                tooltip: 'Indicatif du pays',
                itemBuilder: (context) => countries
                    .map(
                      (country) => PopupMenuItem<String>(
                        value: country['code'],
                        child: Row(
                          children: [
                            Text(country['flag'] ?? ''),
                            const SizedBox(width: 8),
                            Text(
                              country['code'] ?? '',
                              style: const TextStyle(fontWeight: FontWeight.w800),
                            ),
                            const SizedBox(width: 8),
                            Expanded(
                              child: Text(
                                country['name'] ?? '',
                                overflow: TextOverflow.ellipsis,
                              ),
                            ),
                          ],
                        ),
                      ),
                    )
                    .toList(growable: false),
                child: DecoratedBox(
                  decoration: BoxDecoration(
                    color: authFieldFill,
                    borderRadius: BorderRadius.circular(11),
                    border: Border.all(color: authBorder),
                  ),
                  child: Padding(
                    padding: EdgeInsets.symmetric(horizontal: compact ? 8 : 10),
                    child: Row(
                      mainAxisSize: MainAxisSize.max,
                      children: [
                        Text(
                          selected['flag'] ?? '🇨🇮',
                          style: const TextStyle(fontSize: 17),
                        ),
                        SizedBox(width: compact ? 6 : 8),
                        Expanded(
                          child: Text(
                            selected['code'] ?? '+225',
                            maxLines: 1,
                            style: const TextStyle(
                              color: authNavy,
                              fontSize: 14.0,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ),
                        const Icon(
                          Icons.arrow_drop_down_rounded,
                          color: Color(0xFF778098),
                          size: 18,
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
            SizedBox(width: gap),
            Expanded(
              child: TextFormField(
                controller: controller,
                enabled: enabled,
                keyboardType: TextInputType.phone,
                textInputAction: TextInputAction.next,
                validator: validator,
                inputFormatters: [
                  FilteringTextInputFormatter.digitsOnly,
                  LengthLimitingTextInputFormatter(12),
                ],
                style: const TextStyle(
                  color: authNavy,
                  fontSize: 14.5,
                  fontWeight: FontWeight.w600,
                ),
                decoration: InputDecoration(
                  hintText: 'Numéro de téléphone',
                  hintStyle: const TextStyle(
                    color: Color(0xFF818AA2),
                    fontSize: 14.2,
                    fontWeight: FontWeight.w500,
                  ),
                  filled: true,
                  fillColor: authFieldFill,
                  isDense: true,
                  contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 19),
                  enabledBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(11),
                    borderSide: const BorderSide(color: authBorder),
                  ),
                  focusedBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(11),
                    borderSide: const BorderSide(color: authNavy, width: 1.25),
                  ),
                  errorBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(11),
                    borderSide: const BorderSide(color: OvanieColors.danger),
                  ),
                  focusedErrorBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(11),
                    borderSide: const BorderSide(color: OvanieColors.danger),
                  ),
                ),
              ),
            ),
          ],
        );
      },
    );
  }
}

class _IvoryCoastFlag extends StatelessWidget {
  const _IvoryCoastFlag();

  @override
  Widget build(BuildContext context) {
    return ClipRRect(
      borderRadius: BorderRadius.circular(1.5),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(width: 6, height: 16, color: const Color(0xFFF77F00)),
          Container(width: 6, height: 16, color: Colors.white),
          Container(width: 6, height: 16, color: const Color(0xFF009E60)),
        ],
      ),
    );
  }
}

class OvaniePrimaryButton extends StatelessWidget {
  final String label;
  final VoidCallback? onPressed;
  final bool loading;
  final bool showArrow;

  const OvaniePrimaryButton({
    super.key,
    required this.label,
    required this.onPressed,
    this.loading = false,
    this.showArrow = true,
  });

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 58,
      child: DecoratedBox(
        decoration: BoxDecoration(
          gradient: const LinearGradient(
            colors: [Color(0xFFFF4B00), Color(0xFFFF6500)],
            begin: Alignment.centerLeft,
            end: Alignment.centerRight,
          ),
          borderRadius: BorderRadius.circular(11),
        ),
        child: TextButton(
          onPressed: loading ? null : onPressed,
          style: TextButton.styleFrom(
            foregroundColor: Colors.white,
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(11)),
          ),
          child: loading
              ? const SizedBox(
                  width: 22,
                  height: 22,
                  child: CircularProgressIndicator(
                    color: Colors.white,
                    strokeWidth: 2.3,
                  ),
                )
              : Stack(
                  alignment: Alignment.center,
                  children: [
                    Center(
                      child: Text(
                        label,
                        textAlign: TextAlign.center,
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 16,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ),
                    if (showArrow)
                      const Align(
                        alignment: Alignment.centerRight,
                        child: Padding(
                          padding: EdgeInsets.only(right: 14),
                          child: Icon(Icons.arrow_forward_rounded, color: Colors.white, size: 24),
                        ),
                      ),
                  ],
                ),
        ),
      ),
    );
  }
}

class OvanieOrangeOutlineButton extends StatelessWidget {
  final String label;
  final VoidCallback? onPressed;
  final IconData? leadingIcon;

  const OvanieOrangeOutlineButton({
    super.key,
    required this.label,
    required this.onPressed,
    this.leadingIcon,
  });

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 54,
      child: OutlinedButton(
        onPressed: onPressed,
        style: OutlinedButton.styleFrom(
          side: const BorderSide(color: authOrange, width: 1.2),
          foregroundColor: authOrange,
          backgroundColor: Colors.white,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(11)),
        ),
        child: Stack(
          alignment: Alignment.center,
          children: [
            if (leadingIcon != null)
              Align(
                alignment: Alignment.centerLeft,
                child: Icon(leadingIcon, color: authNavy, size: 22),
              ),
            Center(
              child: Text(
                label,
                style: const TextStyle(
                  color: authOrange,
                  fontSize: 15.5,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class OvanieAuthMessage extends StatelessWidget {
  final String message;
  final bool success;

  const OvanieAuthMessage({
    super.key,
    required this.message,
    this.success = false,
  });

  @override
  Widget build(BuildContext context) {
    final fg = success ? const Color(0xFF148A4D) : const Color(0xFFB42318);
    final bg = success ? const Color(0xFFF0FBF5) : const Color(0xFFFFF3F1);
    final border = success ? const Color(0xFFBEEAD1) : const Color(0xFFFFD6D0);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: border),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(success ? Icons.check_circle_rounded : Icons.error_outline_rounded, color: fg, size: 18),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              message,
              style: TextStyle(
                color: fg,
                fontSize: 11.7,
                height: 1.35,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class OvanieRecoveryHeroIcon extends StatelessWidget {
  final bool reset;

  const OvanieRecoveryHeroIcon({super.key, required this.reset});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 112,
      height: 112,
      decoration: const BoxDecoration(
        shape: BoxShape.circle,
        gradient: RadialGradient(
          colors: [Color(0xFFF9FBFF), Color(0xFFEAF1FD)],
        ),
      ),
      child: Stack(
        alignment: Alignment.center,
        children: [
          const Icon(Icons.lock_open_rounded, color: Color(0xFF0C459F), size: 62),
          Positioned(
            right: 20,
            bottom: 21,
            child: Container(
              width: 34,
              height: 34,
              decoration: const BoxDecoration(
                color: Color(0xFFEAF1FD),
                shape: BoxShape.circle,
              ),
              child: Icon(
                reset ? Icons.verified_user_rounded : Icons.refresh_rounded,
                color: const Color(0xFF0C459F),
                size: 29,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class OvanieSupportCard extends StatelessWidget {
  final VoidCallback onTap;

  const OvanieSupportCard({super.key, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.fromLTRB(22, 18, 16, 18),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [Color(0xFFFFF2E9), Color(0xFFFFF8F3)],
          begin: Alignment.centerLeft,
          end: Alignment.centerRight,
        ),
        borderRadius: BorderRadius.circular(20),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF7F3A0A).withOpacity(.05),
            blurRadius: 20,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Row(
        children: [
          Container(
            width: 58,
            height: 58,
            decoration: const BoxDecoration(
              shape: BoxShape.circle,
              color: Color(0xFFFFDCC9),
            ),
            child: const Icon(Icons.support_agent_rounded, color: authOrange, size: 31),
          ),
          const SizedBox(width: 18),
          const Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Besoin d’aide ?',
                  style: TextStyle(
                    color: authNavy,
                    fontSize: 15.5,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                SizedBox(height: 7),
                Text(
                  'Notre équipe peut vous accompagner si vous n’avez plus accès à votre e-mail ou votre numéro.',
                  style: TextStyle(
                    color: authMuted,
                    fontSize: 11.7,
                    height: 1.42,
                    fontWeight: FontWeight.w500,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          TextButton(
            onPressed: onTap,
            style: TextButton.styleFrom(foregroundColor: authOrange),
            child: const Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text('Support', style: TextStyle(fontWeight: FontWeight.w800)),
                SizedBox(width: 2),
                Icon(Icons.chevron_right_rounded, size: 21),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class OvanieSocialButton extends StatelessWidget {
  final String provider;
  final VoidCallback onTap;

  const OvanieSocialButton({
    super.key,
    required this.provider,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    Widget logo;
    if (provider == 'google') {
      logo = const SizedBox(width: 30, height: 30, child: CustomPaint(painter: _GooglePainter()));
    } else if (provider == 'apple') {
      logo = const SizedBox(width: 28, height: 30, child: CustomPaint(painter: _ApplePainter()));
    } else {
      logo = Container(
        width: 28,
        height: 28,
        decoration: const BoxDecoration(color: Color(0xFF1877F2), shape: BoxShape.circle),
        alignment: Alignment.center,
        child: const Text(
          'f',
          style: TextStyle(color: Colors.white, fontSize: 22, height: 1, fontWeight: FontWeight.w900),
        ),
      );
    }

    return Material(
      color: Colors.white,
      shape: const CircleBorder(),
      child: InkWell(
        onTap: onTap,
        customBorder: const CircleBorder(),
        child: Container(
          width: 54,
          height: 54,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            border: Border.all(color: const Color(0xFFE0E3EA)),
          ),
          alignment: Alignment.center,
          child: logo,
        ),
      ),
    );
  }
}

class _ConstructionBackdropPainter extends CustomPainter {
  const _ConstructionBackdropPainter();

  @override
  void paint(Canvas canvas, Size size) {
    final line = Paint()
      ..color = Colors.white.withOpacity(.09)
      ..strokeWidth = 1
      ..style = PaintingStyle.stroke;
    final fill = Paint()
      ..color = Colors.white.withOpacity(.04)
      ..style = PaintingStyle.fill;

    final baseY = size.height + 2;
    final startX = size.width * .38;
    for (var i = 0; i < 10; i++) {
      final w = 22.0 + (i % 3) * 7.0;
      final h = 32.0 + (i % 4) * 15.0;
      final x = startX + i * 26.0;
      canvas.drawRect(Rect.fromLTWH(x, baseY - h, w, h), fill);
      canvas.drawRect(Rect.fromLTWH(x, baseY - h, w, h), line);
      for (var j = 1; j < 3; j++) {
        canvas.drawLine(Offset(x + 7 * j, baseY - h), Offset(x + 7 * j, baseY), line);
      }
    }

    _drawCrane(canvas, size, size.width * .72, 22, line);
    _drawCrane(canvas, size, size.width * .56, size.height * .52, line);
  }

  void _drawCrane(Canvas canvas, Size size, double x, double top, Paint paint) {
    final base = size.height;
    canvas.drawLine(Offset(x, top), Offset(x, base), paint);
    canvas.drawLine(Offset(x - 58, top), Offset(size.width - 12, top), paint);
    canvas.drawLine(Offset(x - 58, top), Offset(x, top + 17), paint);
    canvas.drawLine(Offset(x, top), Offset(size.width - 12, top + 9), paint);
    canvas.drawLine(Offset(x - 8, top + 14), Offset(x + 8, top + 14), paint);
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}

class _GooglePainter extends CustomPainter {
  const _GooglePainter();

  @override
  void paint(Canvas canvas, Size size) {
    final center = Offset(size.width / 2, size.height / 2);
    final radius = math.min(size.width, size.height) * .38;
    final rect = Rect.fromCircle(center: center, radius: radius);
    final stroke = math.max(3.2, size.width * .14);

    void arc(Color color, double start, double sweep) {
      canvas.drawArc(
        rect,
        start,
        sweep,
        false,
        Paint()
          ..color = color
          ..style = PaintingStyle.stroke
          ..strokeWidth = stroke
          ..strokeCap = StrokeCap.butt,
      );
    }

    arc(const Color(0xFF4285F4), -.18, 1.70);
    arc(const Color(0xFF34A853), 1.52, 1.25);
    arc(const Color(0xFFFBBC05), 2.77, .72);
    arc(const Color(0xFFEA4335), 3.49, 2.05);

    final bar = Paint()
      ..color = const Color(0xFF4285F4)
      ..strokeWidth = stroke
      ..strokeCap = StrokeCap.square;
    canvas.drawLine(
      Offset(center.dx, center.dy),
      Offset(size.width * .89, center.dy),
      bar,
    );
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}

class _ApplePainter extends CustomPainter {
  const _ApplePainter();

  @override
  void paint(Canvas canvas, Size size) {
    final p = Paint()..color = Colors.black;
    final w = size.width;
    final h = size.height;
    final body = Path()
      ..moveTo(w * .50, h * .30)
      ..cubicTo(w * .35, h * .20, w * .16, h * .32, w * .15, h * .54)
      ..cubicTo(w * .14, h * .73, w * .27, h * .91, w * .39, h * .91)
      ..cubicTo(w * .46, h * .91, w * .49, h * .87, w * .55, h * .87)
      ..cubicTo(w * .62, h * .87, w * .65, h * .92, w * .72, h * .91)
      ..cubicTo(w * .85, h * .89, w * .96, h * .70, w * .94, h * .53)
      ..cubicTo(w * .92, h * .35, w * .77, h * .22, w * .61, h * .30)
      ..cubicTo(w * .56, h * .32, w * .54, h * .32, w * .50, h * .30)
      ..close();
    canvas.drawPath(body, p);
    final leaf = Path()
      ..moveTo(w * .54, h * .22)
      ..cubicTo(w * .57, h * .09, w * .69, h * .03, w * .78, h * .05)
      ..cubicTo(w * .75, h * .16, w * .66, h * .24, w * .54, h * .22)
      ..close();
    canvas.drawPath(leaf, p);
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}
