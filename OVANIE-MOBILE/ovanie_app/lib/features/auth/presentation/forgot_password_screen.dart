import 'package:flutter/material.dart';

import '../../../core/network/api_client.dart';
import '../data/auth_repository.dart';
import 'auth_branding.dart';
import 'login_screen.dart';

class ForgotPasswordScreen extends StatefulWidget {
  final String? initialIdentifier;

  const ForgotPasswordScreen({super.key, this.initialIdentifier});

  @override
  State<ForgotPasswordScreen> createState() => _ForgotPasswordScreenState();
}

class _ForgotPasswordScreenState extends State<ForgotPasswordScreen> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _identifier;
  final _repository = const AuthRepository();

  bool _loading = false;
  String? _error;
  String? _success;

  @override
  void initState() {
    super.initState();
    _identifier = TextEditingController(text: widget.initialIdentifier ?? '');
  }

  @override
  void dispose() {
    _identifier.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    FocusScope.of(context).unfocus();
    if (!(_formKey.currentState?.validate() ?? false)) return;

    setState(() {
      _loading = true;
      _error = null;
      _success = null;
    });

    try {
      final message = await _repository.forgotPassword(
        email: _identifier.text,
      );
      if (!mounted) return;
      setState(() => _success = message);
    } catch (error) {
      if (!mounted) return;
      setState(() => _error = ApiClient.friendlyError(error));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _openLogin() {
    final navigator = Navigator.of(context);
    if (navigator.canPop()) {
      navigator.pop();
      return;
    }
    navigator.pushReplacement(
      MaterialPageRoute<void>(
        builder: (_) => LoginScreen(initialIdentifier: _identifier.text.trim()),
      ),
    );
  }

  void _support() {
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('Support client : 01 61 78 18 18')),
    );
  }

  @override
  Widget build(BuildContext context) {
    return OvanieAuthPage(
      child: Form(
        key: _formKey,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            OvanieAuthHeader(
              icon: Icons.lock_reset_rounded,
              title: 'Mot de passe oublié',
              subtitle: 'Saisissez l’e-mail de votre compte : nous vous enverrons un lien de réinitialisation.',
              onBack: _openLogin,
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
                  if (_success != null) ...[
                    OvanieAuthMessage(message: _success!, success: true),
                    const SizedBox(height: 14),
                  ],
                  OvanieAuthField(
                    controller: _identifier,
                    hint: 'Adresse e-mail',
                    icon: Icons.mail_outline_rounded,
                    keyboardType: TextInputType.emailAddress,
                    textInputAction: TextInputAction.done,
                    autofillHints: const [AutofillHints.email],
                    enabled: !_loading,
                    onSubmitted: (_) => _submit(),
                    validator: (value) {
                      final email = (value ?? '').trim();
                      if (email.isEmpty) return 'Renseignez votre adresse e-mail.';
                      if (!email.contains('@')) return 'Adresse e-mail invalide.';
                      return null;
                    },
                  ),
                  const SizedBox(height: 16),
                  OvaniePrimaryButton(
                    label: 'Envoyer le lien',
                    loading: _loading,
                    onPressed: _submit,
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),
            OvanieAuthSwitchLink(
              question: 'Vous vous en souvenez ?',
              action: 'Retour à la connexion',
              onTap: _loading ? null : _openLogin,
            ),
            const SizedBox(height: 4),
            OvanieAuthSwitchLink(
              question: 'Plus accès à votre e-mail ?',
              action: 'Contacter le support',
              onTap: _support,
            ),
          ],
        ),
      ),
    );
  }
}
