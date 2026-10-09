import 'package:flutter/material.dart';

import '../../../app/theme.dart';
import '../../../core/network/api_client.dart';
import '../data/auth_repository.dart';
import 'auth_branding.dart';
import 'login_screen.dart';

class ResetPasswordScreen extends StatefulWidget {
  final String token;
  final String email;

  const ResetPasswordScreen({
    super.key,
    required this.token,
    required this.email,
  });

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
    return _password.text.isNotEmpty &&
        _confirmation.text.isNotEmpty &&
        _password.text == _confirmation.text;
  }

  Future<void> _submit() async {
    if (_busy) return;
    FocusScope.of(context).unfocus();

    if (widget.token.trim().isEmpty || widget.email.trim().isEmpty) {
      setState(() {
        _error =
            'Le lien de récupération est incomplet. Demandez un nouveau lien.';
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
        builder: (dialogContext) => AlertDialog(
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(20),
          ),
          icon: const Icon(
            Icons.check_circle_rounded,
            color: OvanieColors.success,
            size: 44,
          ),
          title: const Text(
            'Mot de passe modifié',
            textAlign: TextAlign.center,
            style: TextStyle(
              color: authNavy,
              fontWeight: FontWeight.w900,
            ),
          ),
          content: const Text(
            'Votre nouveau mot de passe a été enregistré. Vous pouvez maintenant vous connecter.',
            textAlign: TextAlign.center,
          ),
          actionsAlignment: MainAxisAlignment.center,
          actions: [
            FilledButton(
              onPressed: () => Navigator.of(dialogContext).pop(),
              style: FilledButton.styleFrom(backgroundColor: authOrange),
              child: const Text('Se connecter'),
            ),
          ],
        ),
      );

      if (!mounted) return;
      Navigator.of(context).pushAndRemoveUntil(
        MaterialPageRoute<void>(
          builder: (_) => LoginScreen(initialIdentifier: widget.email),
        ),
        (route) => route.isFirst,
      );
    } catch (error) {
      if (!mounted) return;
      setState(() => _error = ApiClient.friendlyError(error));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return OvanieAuthPage(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          OvanieAuthHeader(
            icon: Icons.password_rounded,
            title: 'Nouveau mot de passe',
            subtitle: 'Choisissez un mot de passe d’au moins 8 caractères.',
            onBack: () => Navigator.of(context).maybePop(),
          ),
          const SizedBox(height: 24),
          OvanieAuthCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                if (_error != null) ...[
                  OvanieAuthMessage(message: _error!),
                  const SizedBox(height: 14),
                ],
                OvanieAuthField(
                  initialValue: widget.email,
                  readOnly: true,
                  hint: 'Adresse e-mail',
                  icon: Icons.mail_outline_rounded,
                  keyboardType: TextInputType.emailAddress,
                ),
                const SizedBox(height: 12),
                OvanieAuthField(
                  controller: _password,
                  hint: 'Nouveau mot de passe',
                  icon: Icons.lock_outline_rounded,
                  obscureText: !_showPassword,
                  textInputAction: TextInputAction.next,
                  autofillHints: const [AutofillHints.newPassword],
                  enabled: !_busy,
                  suffix: OvaniePasswordToggle(
                    visible: _showPassword,
                    onPressed: _busy
                        ? null
                        : () => setState(() => _showPassword = !_showPassword),
                  ),
                ),
                const SizedBox(height: 12),
                OvanieAuthField(
                  controller: _confirmation,
                  hint: 'Confirmer le mot de passe',
                  icon: Icons.lock_outline_rounded,
                  obscureText: !_showConfirmation,
                  textInputAction: TextInputAction.done,
                  autofillHints: const [AutofillHints.newPassword],
                  enabled: !_busy,
                  onSubmitted: (_) => _submit(),
                  suffix: OvaniePasswordToggle(
                    visible: _showConfirmation,
                    onPressed: _busy
                        ? null
                        : () => setState(() => _showConfirmation = !_showConfirmation),
                  ),
                ),
                if (_passwordsMatch) ...[
                  const SizedBox(height: 12),
                  const Row(
                    children: [
                      Icon(Icons.check_circle_rounded, color: Color(0xFF15934E), size: 18),
                      SizedBox(width: 8),
                      Text(
                        'Les mots de passe correspondent',
                        style: TextStyle(
                          color: Color(0xFF15934E),
                          fontSize: 12.5,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ],
                  ),
                ],
                const SizedBox(height: 18),
                OvaniePrimaryButton(
                  label: 'Enregistrer le mot de passe',
                  loading: _busy,
                  onPressed: _submit,
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
