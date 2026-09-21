import 'package:flutter/material.dart';

import '../../../app/theme.dart';
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
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            OvanieAuthTitleBlock(
              title: widget.sessionRecovery ? 'Reconnectez-vous' : 'Connexion client',
              subtitle: widget.sessionRecovery
                  ? 'Votre session a expiré. Connectez-vous à nouveau pour continuer.'
                  : 'Accédez à votre espace OVANIE et gérez vos commandes en toute simplicité.',
              onBack: () => Navigator.of(context).maybePop(),
            ),
            const SizedBox(height: 16),
            const OvanieAuthBrandBanner(),
            Transform.translate(
              offset: const Offset(0, -16),
              child: OvanieAuthCard(
                padding: const EdgeInsets.fromLTRB(22, 22, 22, 24),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    const Text(
                      'Bienvenue !',
                      style: TextStyle(
                        color: authNavy,
                        fontSize: 19,
                        height: 1.1,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 6),
                    const Text(
                      'Veuillez saisir vos identifiants pour continuer',
                      style: TextStyle(
                        color: authMuted,
                        fontSize: 12.7,
                        height: 1.35,
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                    if (_serverError != null) ...[
                      const SizedBox(height: 14),
                      OvanieAuthMessage(message: _serverError!),
                    ],
                    const SizedBox(height: 20),
                    OvanieAuthField(
                      controller: _identifierController,
                      hint: 'Email ou numéro de téléphone',
                      icon: Icons.person_outline_rounded,
                      keyboardType: TextInputType.emailAddress,
                      textInputAction: TextInputAction.next,
                      enabled: !_loading,
                      validator: (value) => (value ?? '').trim().isEmpty
                          ? 'Renseignez votre email ou numéro de téléphone.'
                          : null,
                    ),
                    const SizedBox(height: 14),
                    OvanieAuthField(
                      controller: _passwordController,
                      hint: 'Mot de passe',
                      icon: Icons.lock_outline_rounded,
                      obscureText: _obscurePassword,
                      textInputAction: TextInputAction.done,
                      enabled: !_loading,
                      onSubmitted: (_) => _submit(),
                      suffix: IconButton(
                        onPressed: _loading
                            ? null
                            : () => setState(() => _obscurePassword = !_obscurePassword),
                        icon: Icon(
                          _obscurePassword
                              ? Icons.visibility_outlined
                              : Icons.visibility_off_outlined,
                          color: const Color(0xFF7A8297),
                          size: 24,
                        ),
                      ),
                      validator: (value) => (value ?? '').isEmpty
                          ? 'Renseignez votre mot de passe.'
                          : null,
                    ),
                    const SizedBox(height: 10),
                    Row(
                      children: [
                        SizedBox(
                          width: 22,
                          height: 22,
                          child: Checkbox(
                            value: _rememberMe,
                            activeColor: authOrange,
                            side: const BorderSide(color: Color(0xFF6E768A), width: 1.3),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(3),
                            ),
                            onChanged: _loading
                                ? null
                                : (value) => setState(() => _rememberMe = value ?? false),
                          ),
                        ),
                        const SizedBox(width: 8),
                        const Expanded(
                          child: Text(
                            'Rester connecté',
                            style: TextStyle(
                              color: authNavy,
                              fontSize: 12,
                              fontWeight: FontWeight.w600,
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
                            style: TextStyle(
                              fontSize: 12,
                              fontWeight: FontWeight.w700,
                            ),
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
                    const SizedBox(height: 22),
                    const Row(
                      children: [
                        Expanded(child: Divider(color: Color(0xFFDEE2EA))),
                        Padding(
                          padding: EdgeInsets.symmetric(horizontal: 14),
                          child: Text(
                            'Nouveau sur OVANIE ?',
                            style: TextStyle(
                              color: authMuted,
                              fontSize: 11.7,
                              fontWeight: FontWeight.w500,
                            ),
                          ),
                        ),
                        Expanded(child: Divider(color: Color(0xFFDEE2EA))),
                      ],
                    ),
                    const SizedBox(height: 15),
                    OvanieOrangeOutlineButton(
                      label: 'Créer un compte',
                      onPressed: _loading ? null : _openRegister,
                    ),
                    const SizedBox(height: 22),
                    const Divider(color: Color(0xFFEEF0F4)),
                    const SizedBox(height: 14),
                    const Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(Icons.verified_user_outlined, color: Color(0xFF0B4FC4), size: 24),
                        SizedBox(width: 8),
                        Text(
                          'Connexion sécurisée OVANIE',
                          style: TextStyle(
                            color: authMuted,
                            fontSize: 12.5,
                            fontWeight: FontWeight.w600,
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
