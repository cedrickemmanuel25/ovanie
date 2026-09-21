import 'package:flutter/material.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/theme/ovanie_colors.dart';
import '../../../core/widgets/brand_background.dart';
import '../data/commercial_auth_service.dart';

class CommercialLoginScreen extends StatefulWidget {
  const CommercialLoginScreen({
    super.key,
    required this.authService,
    required this.onLoggedIn,
  });

  final CommercialAuthService authService;
  final Future<void> Function(
    CommercialSession session, {
    required bool rememberMe,
  }) onLoggedIn;

  @override
  State<CommercialLoginScreen> createState() => _CommercialLoginScreenState();
}

class _CommercialLoginScreenState extends State<CommercialLoginScreen> {
  final _formKey = GlobalKey<FormState>();
  final _identifier = TextEditingController();
  final _password = TextEditingController();

  bool _rememberMe = false;
  bool _obscurePassword = true;
  bool _loading = false;
  String? _error;

  @override
  void dispose() {
    _identifier.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    FocusScope.of(context).unfocus();
    if (!_formKey.currentState!.validate() || _loading) return;

    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final session = await widget.authService.login(
        identifier: _identifier.text,
        password: _password.text,
      );
      await widget.onLoggedIn(session, rememberMe: _rememberMe);
    } on ApiException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } on FormatException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } catch (_) {
      if (mounted) {
        setState(() {
          _error = 'Connexion impossible pour le moment. Réessayez.';
        });
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _showPasswordHelp() {
    showDialog<void>(
      context: context,
      builder: (context) => AlertDialog(
        backgroundColor: Colors.white,
        title: const Text(
          'Mot de passe oublié',
          style: TextStyle(color: OvanieColors.ink, fontWeight: FontWeight.w700),
        ),
        content: const Text(
          'Pour un compte Commercial, contactez votre administrateur OVANIE ou le support au 01 61 78 18 18 afin de réinitialiser votre accès professionnel.',
          style: TextStyle(color: Color(0xFF4B5871), height: 1.4),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(),
            child: const Text('Fermer'),
          ),
        ],
      ),
    );
  }

  void _showSupport() {
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Text('Support OVANIE : 01 61 78 18 18'),
        behavior: SnackBarBehavior.floating,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final width = MediaQuery.sizeOf(context).width;
    final scale = (width / 472).clamp(.82, 1.08).toDouble();
    double s(double value) => value * scale;

    return Scaffold(
      resizeToAvoidBottomInset: true,
      body: BrandBackground(
        child: SafeArea(
          child: SingleChildScrollView(
            keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
            padding: EdgeInsets.fromLTRB(s(28), s(24), s(28), s(18)),
            child: ConstrainedBox(
              constraints: BoxConstraints(
                minHeight: MediaQuery.sizeOf(context).height -
                    MediaQuery.paddingOf(context).vertical -
                    s(42),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  SizedBox(height: s(18)),
                  Center(
                    child: Image.asset(
                      'assets/images/ovanie_logo.png',
                      width: s(324),
                      fit: BoxFit.contain,
                    ),
                  ),
                  SizedBox(height: s(28)),
                  Text(
                    'Connexion Commercial',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      color: Colors.white,
                      fontSize: s(24),
                      fontWeight: FontWeight.w800,
                      letterSpacing: -.35,
                    ),
                  ),
                  SizedBox(height: s(10)),
                  Text(
                    'Accédez à votre espace commercial pour créer des\nclients, ouvrir des boutiques et capturer des produits\nsur le terrain.',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      color: const Color(0xFFC0C7D9),
                      fontSize: s(15.5),
                      height: 1.42,
                      fontWeight: FontWeight.w400,
                    ),
                  ),
                  SizedBox(height: s(31)),
                  Container(
                    padding: EdgeInsets.fromLTRB(s(34), s(32), s(34), s(34)),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(s(18)),
                      boxShadow: [
                        BoxShadow(
                          color: Colors.black.withOpacity(.13),
                          blurRadius: s(22),
                          offset: Offset(0, s(8)),
                        ),
                      ],
                    ),
                    child: Form(
                      key: _formKey,
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          _LoginField(
                            controller: _identifier,
                            hint: 'E-mail ou numéro de téléphone',
                            icon: Icons.person_outline_rounded,
                            keyboardType: TextInputType.emailAddress,
                            scale: scale,
                            validator: (value) {
                              if (value == null || value.trim().isEmpty) {
                                return 'Saisissez votre e-mail ou téléphone.';
                              }
                              return null;
                            },
                          ),
                          SizedBox(height: s(21)),
                          _LoginField(
                            controller: _password,
                            hint: 'Mot de passe',
                            icon: Icons.lock_outline_rounded,
                            obscureText: _obscurePassword,
                            scale: scale,
                            suffix: IconButton(
                              tooltip: _obscurePassword
                                  ? 'Afficher le mot de passe'
                                  : 'Masquer le mot de passe',
                              onPressed: () {
                                setState(() {
                                  _obscurePassword = !_obscurePassword;
                                });
                              },
                              icon: Icon(
                                _obscurePassword
                                    ? Icons.visibility_outlined
                                    : Icons.visibility_off_outlined,
                                color: const Color(0xFF082B6C),
                                size: s(24),
                              ),
                            ),
                            validator: (value) {
                              if (value == null || value.isEmpty) {
                                return 'Saisissez votre mot de passe.';
                              }
                              return null;
                            },
                            onSubmitted: (_) => _submit(),
                          ),
                          SizedBox(height: s(9)),
                          Align(
                            alignment: Alignment.centerRight,
                            child: TextButton(
                              onPressed: _showPasswordHelp,
                              style: TextButton.styleFrom(
                                padding: EdgeInsets.symmetric(
                                  horizontal: s(2),
                                  vertical: s(4),
                                ),
                              ),
                              child: Text(
                                'Mot de passe oublié ?',
                                style: TextStyle(
                                  color: const Color(0xFF0078ED),
                                  fontSize: s(13.5),
                                  fontWeight: FontWeight.w500,
                                ),
                              ),
                            ),
                          ),
                          SizedBox(height: s(2)),
                          InkWell(
                            borderRadius: BorderRadius.circular(s(6)),
                            onTap: () => setState(() => _rememberMe = !_rememberMe),
                            child: Padding(
                              padding: EdgeInsets.symmetric(vertical: s(2)),
                              child: Row(
                                children: [
                                  SizedBox(
                                    width: s(26),
                                    height: s(26),
                                    child: Checkbox(
                                      value: _rememberMe,
                                      onChanged: (value) => setState(
                                        () => _rememberMe = value ?? false,
                                      ),
                                      activeColor: OvanieColors.blue,
                                      checkColor: Colors.white,
                                      side: const BorderSide(
                                        color: Color(0xFF7A8499),
                                        width: 1.2,
                                      ),
                                      shape: RoundedRectangleBorder(
                                        borderRadius: BorderRadius.circular(s(4)),
                                      ),
                                    ),
                                  ),
                                  SizedBox(width: s(10)),
                                  Text(
                                    'Se souvenir de moi',
                                    style: TextStyle(
                                      color: OvanieColors.ink,
                                      fontSize: s(13.5),
                                      fontWeight: FontWeight.w600,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ),
                          if (_error != null) ...[
                            SizedBox(height: s(12)),
                            Container(
                              padding: EdgeInsets.all(s(10)),
                              decoration: BoxDecoration(
                                color: const Color(0xFFFFF1F1),
                                borderRadius: BorderRadius.circular(s(8)),
                              ),
                              child: Text(
                                _error!,
                                style: TextStyle(
                                  color: const Color(0xFFB42318),
                                  fontSize: s(12.2),
                                  height: 1.35,
                                ),
                              ),
                            ),
                          ],
                          SizedBox(height: s(20)),
                          SizedBox(
                            height: s(54),
                            child: DecoratedBox(
                              decoration: BoxDecoration(
                                gradient: const LinearGradient(
                                  colors: [
                                    Color(0xFFFF8A00),
                                    Color(0xFFFF6900),
                                  ],
                                ),
                                borderRadius: BorderRadius.circular(s(8)),
                                boxShadow: [
                                  BoxShadow(
                                    color: const Color(0xFFFF7900).withOpacity(.27),
                                    blurRadius: s(13),
                                    offset: Offset(0, s(7)),
                                  ),
                                ],
                              ),
                              child: ElevatedButton(
                                onPressed: _loading ? null : _submit,
                                style: ElevatedButton.styleFrom(
                                  backgroundColor: Colors.transparent,
                                  disabledBackgroundColor: Colors.transparent,
                                  shadowColor: Colors.transparent,
                                  foregroundColor: Colors.white,
                                  shape: RoundedRectangleBorder(
                                    borderRadius: BorderRadius.circular(s(8)),
                                  ),
                                ),
                                child: _loading
                                    ? SizedBox(
                                        width: s(23),
                                        height: s(23),
                                        child: const CircularProgressIndicator(
                                          strokeWidth: 2.2,
                                          color: Colors.white,
                                        ),
                                      )
                                    : Text(
                                        'Se connecter',
                                        style: TextStyle(
                                          fontSize: s(17.5),
                                          fontWeight: FontWeight.w800,
                                        ),
                                      ),
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                  SizedBox(height: s(24)),
                  Center(
                    child: InkWell(
                      onTap: _showSupport,
                      borderRadius: BorderRadius.circular(s(40)),
                      child: Padding(
                        padding: EdgeInsets.symmetric(
                          horizontal: s(14),
                          vertical: s(6),
                        ),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Container(
                              width: s(50),
                              height: s(50),
                              decoration: BoxDecoration(
                                shape: BoxShape.circle,
                                border: Border.all(
                                  color: const Color(0xFF195596),
                                  width: 1,
                                ),
                                color: const Color(0xFF062653),
                              ),
                              child: Icon(
                                Icons.support_agent_rounded,
                                color: const Color(0xFF68B7FF),
                                size: s(28),
                              ),
                            ),
                            SizedBox(width: s(14)),
                            Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  'Besoin d’aide ?',
                                  style: TextStyle(
                                    color: Colors.white,
                                    fontSize: s(15),
                                    fontWeight: FontWeight.w800,
                                  ),
                                ),
                                SizedBox(height: s(3)),
                                Text.rich(
                                  TextSpan(
                                    style: TextStyle(
                                      color: const Color(0xFFC2C9DA),
                                      fontSize: s(13.5),
                                    ),
                                    children: const [
                                      TextSpan(text: 'Contactez le '),
                                      TextSpan(
                                        text: 'support OVANIE',
                                        style: TextStyle(color: Color(0xFF3FA7FF)),
                                      ),
                                    ],
                                  ),
                                ),
                              ],
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                  SizedBox(height: s(26)),
                  Container(
                    height: 1,
                    decoration: BoxDecoration(
                      gradient: LinearGradient(
                        colors: [
                          Colors.transparent,
                          const Color(0xFF169BFF).withOpacity(.8),
                          Colors.white.withOpacity(.85),
                          const Color(0xFF169BFF).withOpacity(.8),
                          Colors.transparent,
                        ],
                      ),
                      boxShadow: [
                        BoxShadow(
                          color: const Color(0xFF169BFF).withOpacity(.6),
                          blurRadius: s(7),
                        ),
                      ],
                    ),
                  ),
                  SizedBox(height: s(15)),
                  Text(
                    'OVANIE Commercial',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      color: Colors.white,
                      fontSize: s(14.5),
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                  SizedBox(height: s(5)),
                  Text(
                    'Version commerciale 1.0.0',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      color: const Color(0xFF949DB2),
                      fontSize: s(12.5),
                    ),
                  ),
                  SizedBox(height: s(3)),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _LoginField extends StatelessWidget {
  const _LoginField({
    required this.controller,
    required this.hint,
    required this.icon,
    required this.scale,
    this.keyboardType,
    this.obscureText = false,
    this.suffix,
    this.validator,
    this.onSubmitted,
  });

  final TextEditingController controller;
  final String hint;
  final IconData icon;
  final double scale;
  final TextInputType? keyboardType;
  final bool obscureText;
  final Widget? suffix;
  final String? Function(String?)? validator;
  final ValueChanged<String>? onSubmitted;

  @override
  Widget build(BuildContext context) {
    double s(double value) => value * scale;
    return TextFormField(
      controller: controller,
      keyboardType: keyboardType,
      obscureText: obscureText,
      validator: validator,
      onFieldSubmitted: onSubmitted,
      autocorrect: false,
      enableSuggestions: !obscureText,
      style: TextStyle(
        color: OvanieColors.ink,
        fontSize: s(15),
        fontWeight: FontWeight.w500,
      ),
      decoration: InputDecoration(
        hintText: hint,
        hintStyle: TextStyle(
          color: const Color(0xFF8D95AC),
          fontSize: s(14.6),
          fontWeight: FontWeight.w400,
        ),
        prefixIcon: Padding(
          padding: EdgeInsets.symmetric(horizontal: s(13)),
          child: Icon(icon, color: const Color(0xFF082B6C), size: s(25)),
        ),
        prefixIconConstraints: BoxConstraints(minWidth: s(52)),
        suffixIcon: suffix,
        filled: true,
        fillColor: const Color(0xFFFDFDFE),
        contentPadding: EdgeInsets.symmetric(vertical: s(19)),
        errorMaxLines: 2,
        errorStyle: TextStyle(fontSize: s(11)),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(s(8)),
          borderSide: const BorderSide(color: Color(0xFFBBC3D0), width: 1),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(s(8)),
          borderSide: const BorderSide(color: OvanieColors.blue, width: 1.5),
        ),
        errorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(s(8)),
          borderSide: const BorderSide(color: Color(0xFFD92D20), width: 1),
        ),
        focusedErrorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(s(8)),
          borderSide: const BorderSide(color: Color(0xFFD92D20), width: 1.5),
        ),
      ),
    );
  }
}
