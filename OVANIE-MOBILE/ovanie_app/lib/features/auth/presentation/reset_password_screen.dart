import 'package:flutter/material.dart';

import '../../../core/network/api_client.dart';
import '../data/auth_repository.dart';
import 'auth_branding.dart';
import 'login_screen.dart';

class ResetPasswordScreen extends StatefulWidget {
  final String token;
  final String email;

  const ResetPasswordScreen({super.key, required this.token, required this.email});

  @override
  State<ResetPasswordScreen> createState() => _ResetPasswordScreenState();
}

class _ResetPasswordScreenState extends State<ResetPasswordScreen> {
  final _password = TextEditingController();
  final _confirmation = TextEditingController();

  bool _busy = false;
  bool _showPassword = false;
  bool _showConfirmation = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _password.addListener(_refresh);
    _confirmation.addListener(_refresh);
  }

  void _refresh() {
    if (mounted) setState(() {});
  }

  @override
  void dispose() {
    _password.removeListener(_refresh);
    _confirmation.removeListener(_refresh);
    _password.dispose();
    _confirmation.dispose();
    super.dispose();
  }

  bool get _passwordsMatch {
    return _password.text.isNotEmpty && _confirmation.text.isNotEmpty && _password.text == _confirmation.text;
  }

  Future<void> _submit() async {
    if (_busy) return;
    FocusScope.of(context).unfocus();

    if (widget.token.trim().isEmpty || widget.email.trim().isEmpty) {
      setState(() {
        _error = 'Le lien de récupération est incomplet. Demandez un nouveau lien.';
      });
      return;
    }
    if (_password.text.length < 8) {
      setState(() {
        _error = 'Le nouveau mot de passe doit contenir au moins 8 caractères.';
      });
      return;
    }
    if (_password.text != _confirmation.text) {
      setState(() {
        _error = 'La confirmation du mot de passe ne correspond pas.';
      });
      return;
    }

    setState(() {
      _busy = true;
      _error = null;
    });

    try {
      await const AuthRepository().resetPassword(
        email: widget.email,
        token: widget.token,
        password: _password.text,
        confirmation: _confirmation.text,
      );
      if (!mounted) return;

      await showDialog<void>(
        context: context,
        barrierDismissible: false,
        builder: (dialogContext) => OvanieAuthTheme(
          child: Dialog(
            backgroundColor: Colors.white,
            surfaceTintColor: Colors.transparent,
            insetPadding: const EdgeInsets.symmetric(horizontal: 40),
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(22)),
            child: Padding(
              padding: const EdgeInsets.fromLTRB(16, 20, 16, 16),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Center(
                    child: Image.asset(
                      'assets/images/auth/password_done.jpg',
                      width: 116,
                      height: 112,
                      excludeFromSemantics: true,
                    ),
                  ),
                  const SizedBox(height: 10),
                  const Text(
                    'Mot de passe modifié',
                    textAlign: TextAlign.center,
                    style: TextStyle(color: authNavy, fontSize: 19, fontWeight: FontWeight.w800, letterSpacing: -.3),
                  ),
                  const SizedBox(height: 8),
                  const Text(
                    'Votre nouveau mot de passe a été enregistré. Vous pouvez maintenant vous connecter.',
                    textAlign: TextAlign.center,
                    style: TextStyle(color: authMuted, fontSize: 14, height: 1.4, fontWeight: FontWeight.w500),
                  ),
                  const SizedBox(height: 16),
                  OvaniePrimaryButton(
                    label: 'Se connecter',
                    height: 46,
                    onPressed: () => Navigator.of(dialogContext).pop(),
                  ),
                ],
              ),
            ),
          ),
        ),
      );

      if (!mounted) return;
      Navigator.of(context).pushAndRemoveUntil(
        MaterialPageRoute<void>(builder: (_) => LoginScreen(initialIdentifier: widget.email)),
        (route) => route.isFirst,
      );
    } catch (error) {
      if (!mounted) return;
      setState(() => _error = ApiClient.friendlyError(error));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Widget _label(String text) => Padding(
    padding: const EdgeInsets.only(bottom: 7),
    child: Text(
      text,
      style: const TextStyle(color: authNavy, fontSize: 14, fontWeight: FontWeight.w700),
    ),
  );

  @override
  Widget build(BuildContext context) {
    final padding = MediaQuery.paddingOf(context);
    return OvanieAuthTheme(
      child: Scaffold(
        backgroundColor: const Color(0xFFF7F9FD),
        body: SingleChildScrollView(
          keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
          padding: EdgeInsets.fromLTRB(18, padding.top + 34, 18, padding.bottom + 24),
          child: Center(
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 520),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Center(child: OvanieLogo(width: 148)),
                  const SizedBox(height: 22),
                  Center(
                    child: Container(
                      width: 104,
                      height: 104,
                      decoration: const BoxDecoration(color: Color(0xFFE8EEFB), shape: BoxShape.circle),
                      child: const Icon(Icons.lock_reset_rounded, color: authOrange, size: 52),
                    ),
                  ),
                  const SizedBox(height: 14),
                  const Text(
                    'Nouveau mot de passe',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      color: authNavy,
                      fontSize: 26,
                      height: 1.2,
                      fontWeight: FontWeight.w800,
                      letterSpacing: -.7,
                    ),
                  ),
                  const SizedBox(height: 6),
                  const Text(
                    'Choisissez un mot de passe d’au moins 8 caractères.',
                    textAlign: TextAlign.center,
                    style: TextStyle(color: authMuted, fontSize: 14, height: 1.4, fontWeight: FontWeight.w500),
                  ),
                  const SizedBox(height: 18),
                  Container(
                    padding: const EdgeInsets.fromLTRB(18, 20, 18, 22),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(18),
                      boxShadow: const [BoxShadow(color: Color(0x0F0A1B4F), blurRadius: 24, offset: Offset(0, 8))],
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        _label('Adresse e-mail'),
                        OvanieAuthField(
                          initialValue: widget.email,
                          readOnly: true,
                          hint: 'Adresse e-mail',
                          keyboardType: TextInputType.emailAddress,
                        ),
                        const SizedBox(height: 16),
                        _label('Nouveau mot de passe'),
                        OvanieAuthField(
                          controller: _password,
                          hint: 'Au moins 8 caractères',
                          obscureText: !_showPassword,
                          textInputAction: TextInputAction.next,
                          autofillHints: const [AutofillHints.newPassword],
                          enabled: !_busy,
                          suffix: OvaniePasswordToggle(
                            eye: true,
                            visible: _showPassword,
                            onPressed: _busy ? null : () => setState(() => _showPassword = !_showPassword),
                          ),
                        ),
                        const SizedBox(height: 16),
                        _label('Confirmer le mot de passe'),
                        OvanieAuthField(
                          controller: _confirmation,
                          hint: 'Saisissez-le une seconde fois',
                          obscureText: !_showConfirmation,
                          textInputAction: TextInputAction.done,
                          autofillHints: const [AutofillHints.newPassword],
                          enabled: !_busy,
                          onSubmitted: (_) => _submit(),
                          suffix: OvaniePasswordToggle(
                            eye: true,
                            visible: _showConfirmation,
                            onPressed: _busy ? null : () => setState(() => _showConfirmation = !_showConfirmation),
                          ),
                        ),
                        if (_passwordsMatch) ...[
                          const SizedBox(height: 12),
                          const Row(
                            children: [
                              Icon(Icons.check_circle_rounded, color: authGreen, size: 22),
                              SizedBox(width: 8),
                              Text(
                                'Les mots de passe correspondent',
                                style: TextStyle(color: Color(0xFF12894A), fontSize: 13, fontWeight: FontWeight.w500),
                              ),
                            ],
                          ),
                        ],
                        if (_error != null) ...[
                          const SizedBox(height: 12),
                          OvanieAuthMessage(message: _error!, centered: true),
                        ],
                        const SizedBox(height: 16),
                        OvaniePrimaryButton(label: 'Enregistrer le mot de passe', loading: _busy, onPressed: _submit),
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
  }
}
