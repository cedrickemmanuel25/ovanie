import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../core/network/api_client.dart';
import '../../cart/data/cart_api_repository.dart';
import '../../favorites/domain/favorites_store.dart';
import '../data/auth_repository.dart';
import '../domain/session_store.dart';
import 'auth_branding.dart';
import 'login_screen.dart';

class RegisterScreen extends StatefulWidget {
  final VoidCallback? onRegistered;

  const RegisterScreen({super.key, this.onRegistered});

  @override
  State<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends State<RegisterScreen> {
  final _formKey = GlobalKey<FormState>();
  final _firstName = TextEditingController();
  final _lastName = TextEditingController();
  final _email = TextEditingController();
  final _phone = TextEditingController();
  final _password = TextEditingController();
  final _confirmation = TextEditingController();
  final _repository = const AuthRepository();

  String _phoneCountry = '+225';
  bool _showPassword = false;
  bool _showConfirmation = false;
  bool _acceptedTerms = false;
  bool _loading = false;
  String? _serverError;

  @override
  void dispose() {
    _firstName.dispose();
    _lastName.dispose();
    _email.dispose();
    _phone.dispose();
    _password.dispose();
    _confirmation.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    FocusScope.of(context).unfocus();
    if (!(_formKey.currentState?.validate() ?? false)) return;
    if (!_acceptedTerms) {
      setState(() {
        _serverError =
            'Vous devez accepter les Conditions d’utilisation et la Politique de confidentialité.';
      });
      return;
    }

    setState(() {
      _loading = true;
      _serverError = null;
    });

    try {
      final result = await _repository.register(
        firstName: _firstName.text,
        lastName: _lastName.text,
        email: _email.text,
        phoneCountry: _phoneCountry,
        phone: _phone.text,
        password: _password.text,
        passwordConfirmation: _confirmation.text,
      );
      if (!mounted) return;

      await SessionStore.instance.openAuthenticatedSession(
        token: result.token,
        user: result.user,
        loginIdentifier: _email.text,
        persist: true,
      );

      try {
        await const CartApiRepository().syncAfterAuthentication();
        await FavoritesStore.instance.refreshFromServer();
      } catch (_) {}

      if (!mounted) return;
      widget.onRegistered?.call();
      Navigator.of(context).pop(true);
    } catch (error) {
      if (!mounted) return;
      setState(() => _serverError = ApiClient.friendlyError(error));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _openLegalPage(String path) async {
    const channel = MethodChannel('ovanie/external_url');
    try {
      await channel.invokeMethod<bool>('openUrl', {
        'url': 'https://www.ovanie.com$path',
      });
    } catch (_) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Consultez https://www.ovanie.com$path')),
      );
    }
  }

  void _openLogin() {
    Navigator.of(context).pushReplacement(
      MaterialPageRoute<void>(
        builder: (_) => LoginScreen(onLoggedIn: widget.onRegistered),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    const termsStyle = TextStyle(
      color: authNavy,
      fontSize: 12.5,
      height: 1.45,
      fontWeight: FontWeight.w500,
    );
    const termsLinkStyle = TextStyle(
      color: authOrange,
      fontSize: 12.5,
      height: 1.45,
      fontWeight: FontWeight.w800,
    );

    return OvanieAuthPage(
      child: Form(
        key: _formKey,
        child: AutofillGroup(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              OvanieAuthHeader(
                icon: Icons.person_add_alt_1_rounded,
                title: 'Créer un compte',
                subtitle: 'Quelques informations suffisent pour commencer vos achats.',
                onBack: () => Navigator.of(context).maybePop(),
              ),
              const SizedBox(height: 24),
              OvanieAuthCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    if (_serverError != null) ...[
                      OvanieAuthMessage(message: _serverError!),
                      const SizedBox(height: 14),
                    ],
                    OvanieAuthField(
                      controller: _firstName,
                      hint: 'Prénom',
                      icon: Icons.person_outline_rounded,
                      textInputAction: TextInputAction.next,
                      autofillHints: const [AutofillHints.givenName],
                      enabled: !_loading,
                      validator: (value) => (value ?? '').trim().isEmpty
                          ? 'Renseignez votre prénom.'
                          : null,
                    ),
                    const SizedBox(height: 12),
                    OvanieAuthField(
                      controller: _lastName,
                      hint: 'Nom',
                      icon: Icons.badge_outlined,
                      textInputAction: TextInputAction.next,
                      autofillHints: const [AutofillHints.familyName],
                      enabled: !_loading,
                      validator: (value) => (value ?? '').trim().isEmpty
                          ? 'Renseignez votre nom.'
                          : null,
                    ),
                    const SizedBox(height: 12),
                    OvanieAuthField(
                      controller: _email,
                      hint: 'Adresse e-mail',
                      icon: Icons.mail_outline_rounded,
                      keyboardType: TextInputType.emailAddress,
                      textInputAction: TextInputAction.next,
                      autofillHints: const [AutofillHints.email],
                      enabled: !_loading,
                      validator: (value) {
                        final email = (value ?? '').trim();
                        if (email.isEmpty) return 'Renseignez votre adresse e-mail.';
                        if (!email.contains('@')) return 'Adresse e-mail invalide.';
                        return null;
                      },
                    ),
                    const SizedBox(height: 12),
                    OvaniePhoneField(
                      controller: _phone,
                      countryCode: _phoneCountry,
                      onCountryChanged: (value) => setState(() => _phoneCountry = value),
                      enabled: !_loading,
                      validator: (value) {
                        final digits = (value ?? '').replaceAll(RegExp(r'\D+'), '');
                        if (digits.isEmpty) return 'Renseignez votre numéro.';
                        if (digits.length < 6 || digits.length > 12) {
                          return 'Saisissez un numéro de téléphone valide.';
                        }
                        return null;
                      },
                    ),
                    const SizedBox(height: 12),
                    OvanieAuthField(
                      controller: _password,
                      hint: 'Mot de passe (8 caractères min.)',
                      icon: Icons.lock_outline_rounded,
                      obscureText: !_showPassword,
                      textInputAction: TextInputAction.next,
                      autofillHints: const [AutofillHints.newPassword],
                      enabled: !_loading,
                      suffix: OvaniePasswordToggle(
                        visible: _showPassword,
                        onPressed: _loading
                            ? null
                            : () => setState(() => _showPassword = !_showPassword),
                      ),
                      validator: (value) => (value ?? '').length < 8
                          ? 'Minimum 8 caractères.'
                          : null,
                    ),
                    const SizedBox(height: 12),
                    OvanieAuthField(
                      controller: _confirmation,
                      hint: 'Confirmer le mot de passe',
                      icon: Icons.lock_outline_rounded,
                      obscureText: !_showConfirmation,
                      textInputAction: TextInputAction.done,
                      autofillHints: const [AutofillHints.newPassword],
                      enabled: !_loading,
                      onSubmitted: (_) => _submit(),
                      suffix: OvaniePasswordToggle(
                        visible: _showConfirmation,
                        onPressed: _loading
                            ? null
                            : () => setState(() => _showConfirmation = !_showConfirmation),
                      ),
                      validator: (value) => value != _password.text
                          ? 'Les mots de passe ne correspondent pas.'
                          : null,
                    ),
                    const SizedBox(height: 16),
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Padding(
                          padding: const EdgeInsets.only(top: 1),
                          child: OvanieAuthCheckbox(
                            value: _acceptedTerms,
                            onChanged: _loading
                                ? null
                                : (value) => setState(() => _acceptedTerms = value),
                          ),
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: Wrap(
                            crossAxisAlignment: WrapCrossAlignment.center,
                            children: [
                              const Text('J’accepte les ', style: termsStyle),
                              InkWell(
                                onTap: _loading ? null : () => _openLegalPage('/terms'),
                                child: const Text(
                                  'Conditions d’utilisation',
                                  style: termsLinkStyle,
                                ),
                              ),
                              const Text(' et la ', style: termsStyle),
                              InkWell(
                                onTap: _loading ? null : () => _openLegalPage('/privacy'),
                                child: const Text(
                                  'Politique de confidentialité',
                                  style: termsLinkStyle,
                                ),
                              ),
                              const Text('.', style: termsStyle),
                            ],
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 18),
                    OvaniePrimaryButton(
                      label: 'Créer mon compte',
                      loading: _loading,
                      onPressed: _submit,
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 16),
              OvanieAuthSwitchLink(
                question: 'Vous avez déjà un compte ?',
                action: 'Se connecter',
                onTap: _loading ? null : _openLogin,
              ),
            ],
          ),
        ),
      ),
    );
  }
}
