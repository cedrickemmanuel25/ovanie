import 'package:flutter/material.dart';

import '../../../core/config/app_config.dart';
import '../../../core/network/api_client.dart';
import '../../../core/platform/external_url_launcher.dart';
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
  bool _acceptedTerms = false;
  bool _termsError = false;
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
    // Tous les oublis sont signalés en une fois : champs et conditions.
    final fieldsValid = _formKey.currentState?.validate() ?? false;
    setState(() => _termsError = !_acceptedTerms);
    if (!fieldsValid || !_acceptedTerms) return;

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
    final url = '${AppConfig.backendOrigin}$path';
    if (await ExternalUrlLauncher.open(url)) return;
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Consultez $url')));
  }

  void _openLogin() {
    Navigator.of(
      context,
    ).pushReplacement(MaterialPageRoute<void>(builder: (_) => LoginScreen(onLoggedIn: widget.onRegistered)));
  }

  @override
  Widget build(BuildContext context) {
    const termsStyle = TextStyle(color: authNavy, fontSize: 13, height: 1.4, fontWeight: FontWeight.w500);
    const termsLinkStyle = TextStyle(color: authBlue, fontSize: 13, height: 1.4, fontWeight: FontWeight.w600);

    return OvanieAuthPage(
      title: 'Créer un compte',
      subtitle: 'Quelques informations suffisent pour commencer vos achats.',
      onBack: Navigator.of(context).canPop() ? () => Navigator.of(context).maybePop() : null,
      child: Form(
        key: _formKey,
        child: AutofillGroup(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              if (_serverError != null) ...[OvanieAuthMessage(message: _serverError!), const SizedBox(height: 12)],
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    child: OvanieAuthField(
                      controller: _firstName,
                      hint: 'Prénom',
                      icon: Icons.person_outline_rounded,
                      textInputAction: TextInputAction.next,
                      textCapitalization: TextCapitalization.words,
                      autofillHints: const [AutofillHints.givenName],
                      enabled: !_loading,
                      validator: (value) => (value ?? '').trim().isEmpty ? 'Renseignez votre prénom.' : null,
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: OvanieAuthField(
                      controller: _lastName,
                      hint: 'Nom',
                      icon: Icons.person_outline_rounded,
                      textInputAction: TextInputAction.next,
                      textCapitalization: TextCapitalization.words,
                      autofillHints: const [AutofillHints.familyName],
                      enabled: !_loading,
                      validator: (value) => (value ?? '').trim().isEmpty ? 'Renseignez votre nom.' : null,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 10),
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
                  if (!RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$').hasMatch(email)) return 'Adresse e-mail invalide.';
                  return null;
                },
              ),
              const SizedBox(height: 10),
              OvaniePhoneField(
                controller: _phone,
                countryCode: _phoneCountry,
                onCountryChanged: (value) => setState(() => _phoneCountry = value),
                enabled: !_loading,
                validator: (value) {
                  final digits = (value ?? '').replaceAll(RegExp(r'\D+'), '');
                  if (digits.length < 6 || digits.length > 12) return 'Saisissez un numéro de téléphone valide.';
                  return null;
                },
              ),
              const SizedBox(height: 10),
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
                  onPressed: _loading ? null : () => setState(() => _showPassword = !_showPassword),
                ),
                validator: (value) => (value ?? '').length < 8 ? 'Minimum 8 caractères.' : null,
              ),
              const SizedBox(height: 10),
              OvanieAuthField(
                controller: _confirmation,
                hint: 'Confirmer le mot de passe',
                icon: Icons.lock_outline_rounded,
                obscureText: !_showPassword,
                textInputAction: TextInputAction.done,
                autofillHints: const [AutofillHints.newPassword],
                enabled: !_loading,
                onSubmitted: (_) => _submit(),
                validator: (value) =>
                    (value ?? '').isEmpty || value != _password.text ? 'Les mots de passe ne correspondent pas.' : null,
              ),
              const SizedBox(height: 14),
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Padding(
                    padding: const EdgeInsets.only(top: 1),
                    child: OvanieAuthCheckbox(
                      value: _acceptedTerms,
                      error: _termsError,
                      onChanged: _loading
                          ? null
                          : (value) => setState(() {
                              _acceptedTerms = value;
                              if (value) _termsError = false;
                            }),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Wrap(
                      crossAxisAlignment: WrapCrossAlignment.center,
                      children: [
                        const Text('J’accepte les ', style: termsStyle),
                        InkWell(
                          onTap: _loading ? null : () => _openLegalPage('/conditions-generales'),
                          child: const Text('Conditions d’utilisation', style: termsLinkStyle),
                        ),
                        const Text(' et la ', style: termsStyle),
                        InkWell(
                          onTap: _loading ? null : () => _openLegalPage('/politique-de-confidentialite'),
                          child: const Text('Politique de confidentialité', style: termsLinkStyle),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              if (_termsError) ...[
                const SizedBox(height: 10),
                const OvanieAuthMessage(
                  message: 'Vous devez accepter les Conditions d’utilisation et la Politique de confidentialité.',
                ),
              ],
              const SizedBox(height: 16),
              OvaniePrimaryButton(label: 'Créer mon compte', loading: _loading, onPressed: _submit),
              const SizedBox(height: 8),
              OvanieAuthDivider(
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
