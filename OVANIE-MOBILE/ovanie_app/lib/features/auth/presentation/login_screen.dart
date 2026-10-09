import 'package:flutter/material.dart';

import '../../../core/network/api_client.dart';
import '../../../core/network/api_runtime_state.dart';
import '../../cart/data/cart_api_repository.dart';
import '../../favorites/domain/favorites_store.dart';
import '../data/auth_repository.dart';
import '../domain/session_store.dart';
import 'auth_branding.dart';
import 'forgot_password_screen.dart';
import 'register_screen.dart';

class LoginScreen extends StatefulWidget {
  final VoidCallback? onLoggedIn;
  final String? initialIdentifier;
  final bool sessionRecovery;

  const LoginScreen({
    super.key,
    this.onLoggedIn,
    this.initialIdentifier,
    this.sessionRecovery = false,
  });

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _identifierController;
  final _passwordController = TextEditingController();
  final _repository = const AuthRepository();

  bool _obscurePassword = true;
  // Même comportement que le Web : la persistance est un choix explicite.
  bool _rememberMe = false;
  bool _loading = false;
  String? _serverError;

  @override
  void initState() {
    super.initState();
    _identifierController = TextEditingController(
      text: widget.initialIdentifier ?? SessionStore.instance.lastIdentifier ?? '',
    );
  }

  @override
  void dispose() {
    _identifierController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    FocusScope.of(context).unfocus();
    if (!(_formKey.currentState?.validate() ?? false)) return;

    setState(() {
      _loading = true;
      _serverError = null;
    });

    try {
      await _repository.checkServer();
      final result = await _repository.login(
        identifier: _identifierController.text,
        password: _passwordController.text,
      );
      if (!mounted) return;

      await SessionStore.instance.openAuthenticatedSession(
        token: result.token,
        user: result.user,
        loginIdentifier: _identifierController.text,
        persist: _rememberMe,
      );

      try {
        await const CartApiRepository().syncAfterAuthentication();
        await FavoritesStore.instance.refreshFromServer();
      } catch (_) {}

      ApiRuntimeState.instance.resolveSessionExpired();
      if (!mounted) return;
      widget.onLoggedIn?.call();
      Navigator.of(context).pop(true);
    } catch (error) {
      if (!mounted) return;
      setState(() => _serverError = ApiClient.friendlyError(error));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _openRegister() {
    Navigator.of(context).pushReplacement(
      MaterialPageRoute<void>(
        builder: (_) => RegisterScreen(onRegistered: widget.onLoggedIn),
      ),
    );
  }

  Future<void> _openForgotPassword() async {
    await Navigator.of(context).push<void>(
      MaterialPageRoute<void>(
        builder: (_) {
          final identifier = _identifierController.text.trim();
          return ForgotPasswordScreen(
            initialIdentifier: identifier.contains('@') ? identifier : '',
          );
        },
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return OvanieAuthPage(
      child: Form(
        key: _formKey,
        child: AutofillGroup(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              OvanieAuthHeader(
                icon: Icons.person_rounded,
                title: widget.sessionRecovery ? 'Reconnectez-vous' : 'Connexion',
                subtitle: widget.sessionRecovery
                    ? 'Votre session a expiré. Connectez-vous pour reprendre là où vous en étiez.'
                    : 'Retrouvez vos commandes, votre panier et vos favoris.',
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
                      controller: _identifierController,
                      hint: 'E-mail ou téléphone',
                      icon: Icons.person_outline_rounded,
                      keyboardType: TextInputType.emailAddress,
                      textInputAction: TextInputAction.next,
                      autofillHints: const [AutofillHints.username, AutofillHints.email],
                      enabled: !_loading,
                      validator: (value) => (value ?? '').trim().isEmpty
                          ? 'Renseignez votre e-mail ou numéro de téléphone.'
                          : null,
                    ),
                    const SizedBox(height: 12),
                    OvanieAuthField(
                      controller: _passwordController,
                      hint: 'Mot de passe',
                      icon: Icons.lock_outline_rounded,
                      obscureText: _obscurePassword,
                      textInputAction: TextInputAction.done,
                      autofillHints: const [AutofillHints.password],
                      enabled: !_loading,
                      onSubmitted: (_) => _submit(),
                      suffix: OvaniePasswordToggle(
                        visible: !_obscurePassword,
                        onPressed: _loading
                            ? null
                            : () => setState(() => _obscurePassword = !_obscurePassword),
                      ),
                      validator: (value) => (value ?? '').isEmpty
                          ? 'Renseignez votre mot de passe.'
                          : null,
                    ),
                    const SizedBox(height: 8),
                    Row(
                      children: [
                        Expanded(
                          child: InkWell(
                            borderRadius: BorderRadius.circular(8),
                            onTap: _loading
                                ? null
                                : () => setState(() => _rememberMe = !_rememberMe),
                            child: Padding(
                              padding: const EdgeInsets.symmetric(vertical: 8),
                              child: Row(
                                children: [
                                  OvanieAuthCheckbox(
                                    value: _rememberMe,
                                    onChanged: _loading
                                        ? null
                                        : (value) => setState(() => _rememberMe = value),
                                  ),
                                  const SizedBox(width: 9),
                                  const Flexible(
                                    child: Text(
                                      'Rester connecté',
                                      style: TextStyle(
                                        color: authNavy,
                                        fontSize: 13,
                                        fontWeight: FontWeight.w600,
                                      ),
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ),
                        ),
                        TextButton(
                          onPressed: _loading ? null : _openForgotPassword,
                          style: TextButton.styleFrom(
                            foregroundColor: authOrange,
                            padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 6),
                          ),
                          child: const Text(
                            'Mot de passe oublié ?',
                            style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 12),
                    OvaniePrimaryButton(
                      label: 'Se connecter',
                      loading: _loading,
                      onPressed: _submit,
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 16),
              OvanieAuthSwitchLink(
                question: 'Pas encore de compte ?',
                action: 'Créer un compte',
                onTap: _loading ? null : _openRegister,
              ),
              const SizedBox(height: 18),
              const OvanieAuthSecureNote(),
            ],
          ),
        ),
      ),
    );
  }
}
