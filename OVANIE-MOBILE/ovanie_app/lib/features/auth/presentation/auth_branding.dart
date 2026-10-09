import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../app/theme.dart';

/// Composants partagés des écrans d'authentification client.
///
/// Parti pris : une page blanche épurée, un en-tête compact (retour, pictogramme,
/// titre, sous-titre court), une carte unique pour le formulaire et une action
/// principale orange. Aucun wordmark ni bannière textuelle de marque.
const Color authNavy = Color(0xFF031A4D);
const Color authNavyDeep = Color(0xFF021338);
const Color authMuted = Color(0xFF6B7591);
const Color authBorder = Color(0xFFDDE2EB);
const Color authFieldFill = Color(0xFFF8F9FB);
const Color authOrange = Color(0xFFFF5100);
const Color authPageBackground = Color(0xFFF5F6FA);

class OvanieAuthPage extends StatelessWidget {
  final Widget child;
  final EdgeInsetsGeometry padding;

  const OvanieAuthPage({
    super.key,
    required this.child,
    this.padding = const EdgeInsets.fromLTRB(20, 12, 20, 32),
  });

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: authPageBackground,
      body: SafeArea(
        child: SingleChildScrollView(
          physics: const BouncingScrollPhysics(),
          keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
          padding: padding,
          child: Center(
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 480),
              child: child,
            ),
          ),
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
      shape: const CircleBorder(side: BorderSide(color: authBorder)),
      child: InkWell(
        onTap: onTap,
        customBorder: const CircleBorder(),
        child: const SizedBox(
          width: 42,
          height: 42,
          child: Icon(Icons.arrow_back_rounded, color: authNavy, size: 22),
        ),
      ),
    );
  }
}

/// En-tête commun : retour, pictogramme de l'écran, titre et sous-titre court.
class OvanieAuthHeader extends StatelessWidget {
  final String title;
  final String subtitle;
  final IconData icon;
  final VoidCallback onBack;

  const OvanieAuthHeader({
    super.key,
    required this.title,
    required this.subtitle,
    required this.icon,
    required this.onBack,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        OvanieAuthBackButton(onTap: onBack),
        const SizedBox(height: 22),
        OvanieAuthIconBadge(icon: icon),
        const SizedBox(height: 18),
        Text(
          title,
          style: const TextStyle(
            color: authNavy,
            fontSize: 26,
            height: 1.1,
            fontWeight: FontWeight.w900,
            letterSpacing: -.5,
          ),
        ),
        const SizedBox(height: 8),
        Text(
          subtitle,
          style: const TextStyle(
            color: authMuted,
            fontSize: 14,
            height: 1.45,
            fontWeight: FontWeight.w500,
          ),
        ),
      ],
    );
  }
}

/// Pictogramme carré navy avec un liseré orange, identité visuelle des écrans
/// d'authentification.
class OvanieAuthIconBadge extends StatelessWidget {
  final IconData icon;

  const OvanieAuthIconBadge({super.key, required this.icon});

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 58,
      height: 58,
      child: Stack(
        children: [
          Container(
            width: 54,
            height: 54,
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(16),
              gradient: const LinearGradient(
                colors: [Color(0xFF0B3A8C), authNavy],
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
              ),
              boxShadow: [
                BoxShadow(
                  color: authNavy.withValues(alpha: .18),
                  blurRadius: 14,
                  offset: const Offset(0, 6),
                ),
              ],
            ),
            child: Icon(icon, color: Colors.white, size: 26),
          ),
          Positioned(
            right: 0,
            bottom: 0,
            child: Container(
              width: 16,
              height: 16,
              decoration: BoxDecoration(
                color: authOrange,
                shape: BoxShape.circle,
                border: Border.all(color: authPageBackground, width: 3),
              ),
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

  const OvanieAuthCard({
    super.key,
    required this.child,
    this.padding = const EdgeInsets.fromLTRB(18, 20, 18, 20),
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: padding,
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: const Color(0xFFE7EAF0)),
        boxShadow: [
          BoxShadow(
            color: authNavy.withValues(alpha: .06),
            blurRadius: 24,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: child,
    );
  }
}

InputDecoration _authDecoration({
  required String hint,
  IconData? icon,
  Widget? suffix,
}) {
  OutlineInputBorder border(Color color, [double width = 1]) {
    return OutlineInputBorder(
      borderRadius: BorderRadius.circular(12),
      borderSide: BorderSide(color: color, width: width),
    );
  }

  return InputDecoration(
    hintText: hint,
    hintStyle: const TextStyle(
      color: Color(0xFF8A93A9),
      fontSize: 14,
      fontWeight: FontWeight.w500,
    ),
    prefixIcon: icon == null
        ? null
        : Icon(icon, color: const Color(0xFF55607C), size: 21),
    prefixIconConstraints: const BoxConstraints(minWidth: 48, minHeight: 52),
    suffixIcon: suffix,
    filled: true,
    fillColor: authFieldFill,
    isDense: true,
    errorMaxLines: 2,
    contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 17),
    border: border(authBorder),
    enabledBorder: border(authBorder),
    disabledBorder: border(const Color(0xFFE9ECF2)),
    focusedBorder: border(authNavy, 1.4),
    errorBorder: border(OvanieColors.danger),
    focusedErrorBorder: border(OvanieColors.danger, 1.4),
  );
}

class OvanieAuthField extends StatelessWidget {
  final TextEditingController? controller;
  final String? initialValue;
  final String hint;
  final IconData icon;
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

  const OvanieAuthField({
    super.key,
    this.controller,
    this.initialValue,
    required this.hint,
    required this.icon,
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
      obscureText: obscureText,
      validator: validator,
      onFieldSubmitted: onSubmitted,
      inputFormatters: inputFormatters,
      onChanged: onChanged,
      autofillHints: autofillHints,
      style: TextStyle(
        color: readOnly ? authMuted : authNavy,
        fontSize: 14.5,
        fontWeight: FontWeight.w600,
      ),
      decoration: _authDecoration(hint: hint, icon: icon, suffix: suffix),
    );
  }
}

/// Bouton œil pour afficher / masquer un mot de passe.
class OvaniePasswordToggle extends StatelessWidget {
  final bool visible;
  final VoidCallback? onPressed;

  const OvaniePasswordToggle({
    super.key,
    required this.visible,
    required this.onPressed,
  });

  @override
  Widget build(BuildContext context) {
    return IconButton(
      onPressed: onPressed,
      tooltip: visible ? 'Masquer' : 'Afficher',
      icon: Icon(
        visible ? Icons.visibility_off_outlined : Icons.visibility_outlined,
        color: const Color(0xFF7A8297),
        size: 22,
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
    final selected = selectedCountry;
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SizedBox(
          width: 104,
          height: 52,
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
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: authBorder),
              ),
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 9),
                child: Row(
                  children: [
                    Text(
                      selected['flag'] ?? '🇨🇮',
                      style: const TextStyle(fontSize: 16),
                    ),
                    const SizedBox(width: 6),
                    Expanded(
                      child: Text(
                        selected['code'] ?? '+225',
                        maxLines: 1,
                        style: const TextStyle(
                          color: authNavy,
                          fontSize: 14,
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
        const SizedBox(width: 8),
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
            decoration: _authDecoration(hint: 'Numéro de téléphone'),
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

  const OvaniePrimaryButton({
    super.key,
    required this.label,
    required this.onPressed,
    this.loading = false,
  });

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 52,
      child: FilledButton(
        onPressed: loading ? null : onPressed,
        style: FilledButton.styleFrom(
          backgroundColor: authOrange,
          foregroundColor: Colors.white,
          disabledBackgroundColor: authOrange.withValues(alpha: .55),
          disabledForegroundColor: Colors.white,
          elevation: 0,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
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
            : Text(
                label,
                textAlign: TextAlign.center,
                style: const TextStyle(
                  fontSize: 15.5,
                  fontWeight: FontWeight.w800,
                ),
              ),
      ),
    );
  }
}

/// Ligne « question + lien » placée sous l'action principale
/// (ex. « Pas encore de compte ? Créer un compte »).
class OvanieAuthSwitchLink extends StatelessWidget {
  final String question;
  final String action;
  final VoidCallback? onTap;

  const OvanieAuthSwitchLink({
    super.key,
    required this.question,
    required this.action,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Wrap(
      alignment: WrapAlignment.center,
      crossAxisAlignment: WrapCrossAlignment.center,
      children: [
        Text(
          question,
          style: const TextStyle(
            color: authMuted,
            fontSize: 13.5,
            fontWeight: FontWeight.w500,
          ),
        ),
        TextButton(
          onPressed: onTap,
          style: TextButton.styleFrom(
            foregroundColor: authOrange,
            padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 4),
            minimumSize: const Size(0, 36),
            tapTargetSize: MaterialTapTargetSize.shrinkWrap,
          ),
          child: Text(
            action,
            style: const TextStyle(fontSize: 13.5, fontWeight: FontWeight.w800),
          ),
        ),
      ],
    );
  }
}

/// Mention discrète de sécurité en bas de page.
class OvanieAuthSecureNote extends StatelessWidget {
  final String label;

  const OvanieAuthSecureNote({super.key, this.label = 'Connexion chiffrée et sécurisée'});

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        const Icon(Icons.lock_outline_rounded, color: Color(0xFF8A93A9), size: 15),
        const SizedBox(width: 6),
        Text(
          label,
          style: const TextStyle(
            color: Color(0xFF8A93A9),
            fontSize: 12,
            fontWeight: FontWeight.w600,
          ),
        ),
      ],
    );
  }
}

/// Case à cocher compacte alignée sur le style des écrans d'authentification.
class OvanieAuthCheckbox extends StatelessWidget {
  final bool value;
  final ValueChanged<bool>? onChanged;

  const OvanieAuthCheckbox({super.key, required this.value, required this.onChanged});

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 22,
      height: 22,
      child: Checkbox(
        value: value,
        activeColor: authOrange,
        side: const BorderSide(color: Color(0xFF8A93A9), width: 1.4),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(5)),
        materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
        onChanged: onChanged == null ? null : (v) => onChanged!(v ?? false),
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
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: border),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(
            success ? Icons.check_circle_rounded : Icons.error_outline_rounded,
            color: fg,
            size: 18,
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              message,
              style: TextStyle(
                color: fg,
                fontSize: 12.5,
                height: 1.4,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
