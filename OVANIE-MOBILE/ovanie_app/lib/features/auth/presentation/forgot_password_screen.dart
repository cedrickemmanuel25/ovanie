import 'package:flutter/material.dart';

import '../../../app/theme.dart';
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
      const SnackBar(content: Text('Support OVANIE : 01 61 78 18 18')),
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
            OvanieAuthTitleBlock(
              title: 'Mot de passe oublié',
              subtitle:
                  'Recevez les instructions nécessaires pour\nrécupérer l’accès à votre compte.',
              onBack: _openLogin,
            ),
            const SizedBox(height: 16),
            const Center(child: OvanieRecoveryHeroIcon(reset: false)),
            const SizedBox(height: 12),
            const Text(
              'Récupérez votre compte',
              textAlign: TextAlign.center,
              style: TextStyle(
                color: authNavy,
                fontSize: 18,
                fontWeight: FontWeight.w900,
              ),
            ),
            const SizedBox(height: 6),
            const Text(
              'Nous vous enverrons un lien sécurisé à l’adresse e-mail\nassociée à votre compte.',
              textAlign: TextAlign.center,
              style: TextStyle(
                color: authMuted,
                fontSize: 13,
                height: 1.45,
                fontWeight: FontWeight.w500,
              ),
            ),
            const SizedBox(height: 22),
            OvanieAuthCard(
              padding: const EdgeInsets.fromLTRB(22, 22, 22, 22),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Text(
                    'Identifier votre compte',
                    style: TextStyle(
                      color: authNavy,
                      fontSize: 18.5,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 7),
                  const Text(
                    'Utilisez l’adresse e-mail associée à votre compte OVANIE.',
                    style: TextStyle(
                      color: authMuted,
                      fontSize: 12.4,
                      height: 1.4,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                  if (_error != null) ...[
                    const SizedBox(height: 13),
                    OvanieAuthMessage(message: _error!),
                  ],
                  if (_success != null) ...[
                    const SizedBox(height: 13),
                    OvanieAuthMessage(message: _success!, success: true),
                  ],
                  const SizedBox(height: 18),
                  OvanieAuthField(
                    controller: _identifier,
                    hint: 'Adresse e-mail',
                    icon: Icons.alternate_email_rounded,
                    keyboardType: TextInputType.emailAddress,
                    textInputAction: TextInputAction.done,
                    enabled: !_loading,
                    onSubmitted: (_) => _submit(),
                    validator: (value) {
                      final email = (value ?? '').trim();
                      if (email.isEmpty) return 'Renseignez votre adresse e-mail.';
                      if (!email.contains('@')) return 'Adresse e-mail invalide.';
                      return null;
                    },
                  ),
                  const SizedBox(height: 18),
                  OvaniePrimaryButton(
                    label: 'Envoyer les instructions',
                    loading: _loading,
                    onPressed: _submit,
                  ),
                  const SizedBox(height: 14),
                  OvanieOrangeOutlineButton(
                    label: 'Retour à la connexion',
                    leadingIcon: Icons.login_rounded,
                    onPressed: _loading ? null : _openLogin,
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            OvanieSupportCard(onTap: _support),
          ],
        ),
      ),
    );
  }
}
