import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../app/theme.dart';
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
    return OvanieAuthPage(
      child: Form(
        key: _formKey,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            OvanieAuthTitleBlock(
              title: 'Créer un compte',
              subtitle: 'Rejoignez OVANIE et gérez vos achats BTP\ndepuis un espace unique.',
              onBack: () => Navigator.of(context).maybePop(),
            ),
            const SizedBox(height: 14),
            const OvanieAuthBrandBanner(registerMode: true),
            Transform.translate(
              offset: const Offset(0, -13),
              child: OvanieAuthCard(
                padding: const EdgeInsets.fromLTRB(22, 21, 22, 24),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    const Text(
                      'Vos informations',
                      style: TextStyle(
                        color: authNavy,
                        fontSize: 19,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 5),
                    const Text(
                      'Renseignez vos informations pour créer votre compte OVANIE.',
                      style: TextStyle(
                        color: authMuted,
                        fontSize: 11.8,
                        height: 1.35,
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                    if (_serverError != null) ...[
                      const SizedBox(height: 12),
                      OvanieAuthMessage(message: _serverError!),
                    ],
                    const SizedBox(height: 17),
                    OvanieAuthField(
                      controller: _firstName,
                      hint: 'Prénom',
                      icon: Icons.person_outline_rounded,
                      textInputAction: TextInputAction.next,
                      enabled: !_loading,
                      validator: (value) => (value ?? '').trim().isEmpty
                          ? 'Renseignez votre prénom.'
                          : null,
                    ),
                    const SizedBox(height: 13),
                    OvanieAuthField(
                      controller: _lastName,
                      hint: 'Nom',
                      icon: Icons.person_outline_rounded,
                      textInputAction: TextInputAction.next,
                      enabled: !_loading,
                      validator: (value) => (value ?? '').trim().isEmpty
                          ? 'Renseignez votre nom.'
                          : null,
                    ),
                    const SizedBox(height: 13),
                    OvanieAuthField(
                      controller: _email,
                      hint: 'Adresse e-mail',
                      icon: Icons.mail_outline_rounded,
                      keyboardType: TextInputType.emailAddress,
                      textInputAction: TextInputAction.next,
                      enabled: !_loading,
                      validator: (value) {
                        final email = (value ?? '').trim();
                        if (email.isEmpty) return 'Renseignez votre adresse e-mail.';
                        if (!email.contains('@')) return 'Adresse e-mail invalide.';
                        return null;
                      },
                    ),
                    const SizedBox(height: 13),
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
                    const SizedBox(height: 13),
                    OvanieAuthField(
                      controller: _password,
                      hint: 'Mot de passe',
                      icon: Icons.lock_outline_rounded,
                      obscureText: !_showPassword,
                      textInputAction: TextInputAction.next,
                      enabled: !_loading,
                      suffix: IconButton(
                        onPressed: _loading
                            ? null
                            : () => setState(() => _showPassword = !_showPassword),
                        icon: Icon(
                          _showPassword
                              ? Icons.visibility_off_outlined
                              : Icons.visibility_outlined,
                          color: const Color(0xFF7A8297),
                          size: 23,
                        ),
                      ),
                      validator: (value) => (value ?? '').length < 8
                          ? 'Minimum 8 caractères.'
                          : null,
                    ),
                    const Padding(
                      padding: EdgeInsets.only(left: 61, top: 2),
                      child: Text(
                        'Minimum 8 caractères',
                        style: TextStyle(
                          color: authMuted,
                          fontSize: 10.2,
                          fontWeight: FontWeight.w500,
                        ),
                      ),
                    ),
                    const SizedBox(height: 10),
                    OvanieAuthField(
                      controller: _confirmation,
                      hint: 'Confirmer le mot de passe',
                      icon: Icons.lock_outline_rounded,
                      obscureText: !_showConfirmation,
                      textInputAction: TextInputAction.done,
                      enabled: !_loading,
                      onSubmitted: (_) => _submit(),
                      suffix: IconButton(
                        onPressed: _loading
                            ? null
                            : () => setState(
                                  () => _showConfirmation = !_showConfirmation,
                                ),
                        icon: Icon(
                          _showConfirmation
                              ? Icons.visibility_off_outlined
                              : Icons.visibility_outlined,
                          color: const Color(0xFF7A8297),
                          size: 23,
                        ),
                      ),
                      validator: (value) => value != _password.text
                          ? 'Les mots de passe ne correspondent pas.'
                          : null,
                    ),
                    const SizedBox(height: 15),
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        SizedBox(
                          width: 22,
                          height: 22,
                          child: Checkbox(
                            value: _acceptedTerms,
                            activeColor: authOrange,
                            side: const BorderSide(color: Color(0xFF6E768A), width: 1.2),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(3),
                            ),
                            onChanged: _loading
                                ? null
                                : (value) => setState(
                                      () => _acceptedTerms = value ?? false,
                                    ),
                          ),
                        ),
                        const SizedBox(width: 9),
                        Expanded(
                          child: Wrap(
                            crossAxisAlignment: WrapCrossAlignment.center,
                            children: [
                              const Text(
                                'En créant mon compte, j’accepte les ',
                                style: TextStyle(
                                  color: authNavy,
                                  fontSize: 11.5,
                                  height: 1.45,
                                  fontWeight: FontWeight.w500,
                                ),
                              ),
                              InkWell(
                                onTap: _loading
                                    ? null
                                    : () => _openLegalPage('/terms'),
                                child: const Text(
                                  'Conditions d’utilisation',
                                  style: TextStyle(
                                    color: authOrange,
                                    fontSize: 11.5,
                                    height: 1.45,
                                    fontWeight: FontWeight.w800,
                                  ),
                                ),
                              ),
                              const Text(
                                ' et la ',
                                style: TextStyle(
                                  color: authNavy,
                                  fontSize: 11.5,
                                  height: 1.45,
                                  fontWeight: FontWeight.w500,
                                ),
                              ),
                              InkWell(
                                onTap: _loading
                                    ? null
                                    : () => _openLegalPage('/privacy'),
                                child: const Text(
                                  'Politique de confidentialité.',
                                  style: TextStyle(
                                    color: authOrange,
                                    fontSize: 11.5,
                                    height: 1.45,
                                    fontWeight: FontWeight.w800,
                                  ),
                                ),
                              ),
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
                    const SizedBox(height: 17),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        const Flexible(
                          child: Text(
                            'Vous avez déjà un compte ?',
                            style: TextStyle(
                              color: authMuted,
                              fontSize: 11.8,
                              fontWeight: FontWeight.w500,
                            ),
                          ),
                        ),
                        const SizedBox(width: 4),
                        TextButton(
                          onPressed: _loading ? null : _openLogin,
                          style: TextButton.styleFrom(
                            padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 2),
                            foregroundColor: authOrange,
                          ),
                          child: const Text(
                            'Se connecter',
                            style: TextStyle(
                              fontSize: 11.8,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
