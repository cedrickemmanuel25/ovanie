import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../core/network/api_client.dart';
import '../../core/ui/vendor_design.dart';
import '../onboarding/shop_onboarding_screen.dart';
import '../shell/vendor_shell.dart';
import 'forgot_password_screen.dart';
import 'vendor_session.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _form = GlobalKey<FormState>();
  final _identifier = TextEditingController();
  final _password = TextEditingController();

  bool _obscure = true;
  bool _rememberMe = false;
  bool _submitting = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) _redirectExistingSession();
    });
  }

  void _redirectExistingSession() {
    final session = VendorSession.instance;
    if (!session.authenticated || !mounted) return;
    final target = session.hasShop ? const VendorShell() : const ShopOnboardingScreen();
    Navigator.of(context).pushReplacement(MaterialPageRoute(builder: (_) => target));
  }

  @override
  void dispose() {
    _identifier.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    FocusScope.of(context).unfocus();
    if (!(_form.currentState?.validate() ?? false)) return;

    setState(() {
      _submitting = true;
      _error = null;
    });

    try {
      await VendorSession.instance.login(
        _identifier.text,
        _password.text,
        persist: _rememberMe,
      );
      if (!mounted) return;

      final target = VendorSession.instance.hasShop
          ? const VendorShell()
          : const ShopOnboardingScreen();
      Navigator.of(context).pushAndRemoveUntil(
        MaterialPageRoute(builder: (_) => target),
        (_) => false,
      );
    } catch (error) {
      if (mounted) setState(() => _error = ApiClient.friendlyError(error));
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  void _openOnboarding() {
    Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => const ShopOnboardingScreen()),
    );
  }

  @override
  Widget build(BuildContext context) {
    final width = MediaQuery.sizeOf(context).width;
    final headerHeight = width * (516 / 941);

    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.light.copyWith(statusBarColor: Colors.transparent),
      child: Scaffold(
        backgroundColor: Colors.white,
        body: SingleChildScrollView(
          keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
          child: Column(
            children: [
              Stack(
                children: [
                  Image.asset(
                    'assets/images/vendor_auth_header.jpg',
                    width: double.infinity,
                    height: headerHeight,
                    fit: BoxFit.cover,
                    alignment: Alignment.topCenter,
                  ),
                  Positioned(
                    left: 14,
                    top: MediaQuery.paddingOf(context).top + 5,
                    child: const VendorBackButton(),
                  ),
                ],
              ),
              Transform.translate(
                offset: const Offset(0, -1),
                child: VendorSheet(
                  radius: 30,
                  padding: const EdgeInsets.fromLTRB(26, 0, 26, 30),
                  child: Form(
                    key: _form,
                    child: AutofillGroup(
                      child: Column(
                        children: [
                          Transform.translate(
                            offset: const Offset(0, -34),
                            child: Container(
                              width: 72,
                              height: 72,
                              decoration: const BoxDecoration(
                                color: Colors.white,
                                shape: BoxShape.circle,
                                boxShadow: [
                                  BoxShadow(
                                    color: Color(0x25001845),
                                    blurRadius: 15,
                                    offset: Offset(0, 7),
                                  ),
                                ],
                              ),
                              padding: const EdgeInsets.all(8),
                              child: const VendorCircleIcon(
                                icon: Icons.storefront_outlined,
                                color: vendorOrange,
                                background: vendorNavyDeep,
                                size: 56,
                                iconSize: 31,
                              ),
                            ),
                          ),
                          Transform.translate(
                            offset: const Offset(0, -15),
                            child: Column(
                              children: [
                                const Text(
                                  'Connexion vendeur',
                                  textAlign: TextAlign.center,
                                  style: TextStyle(
                                    color: vendorText,
                                    fontSize: 27,
                                    fontWeight: FontWeight.w900,
                                    letterSpacing: -.4,
                                  ),
                                ),
                                const SizedBox(height: 9),
                                const Text(
                                  'Accédez à votre espace vendeur pour gérer votre boutique, vos produits et vos commandes.',
                                  textAlign: TextAlign.center,
                                  style: TextStyle(
                                    color: vendorMuted,
                                    fontSize: 14.4,
                                    height: 1.45,
                                  ),
                                ),
                                if (_error != null) ...[
                                  const SizedBox(height: 18),
                                  VendorErrorBox(_error),
                                ],
                                const SizedBox(height: 23),
                                TextFormField(
                                  controller: _identifier,
                                  keyboardType: TextInputType.emailAddress,
                                  textInputAction: TextInputAction.next,
                                  enabled: !_submitting,
                                  autofillHints: const [
                                    AutofillHints.username,
                                    AutofillHints.email,
                                    AutofillHints.telephoneNumber,
                                  ],
                                  decoration: vendorInputDecoration(
                                    hint: 'Email ou numéro de téléphone',
                                    icon: Icons.person_outline_rounded,
                                  ),
                                  validator: (value) => (value ?? '').trim().isEmpty
                                      ? 'Renseignez votre email ou numéro de téléphone.'
                                      : null,
                                ),
                                const SizedBox(height: 13),
                                TextFormField(
                                  controller: _password,
                                  obscureText: _obscure,
                                  textInputAction: TextInputAction.done,
                                  enabled: !_submitting,
                                  autofillHints: const [AutofillHints.password],
                                  onFieldSubmitted: (_) => _submit(),
                                  decoration: vendorInputDecoration(
                                    hint: 'Mot de passe',
                                    icon: Icons.lock_outline_rounded,
                                    suffix: IconButton(
                                      onPressed: _submitting
                                          ? null
                                          : () => setState(() => _obscure = !_obscure),
                                      icon: Icon(
                                        _obscure
                                            ? Icons.visibility_outlined
                                            : Icons.visibility_off_outlined,
                                        color: const Color(0xFF333333),
                                      ),
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
                                        activeColor: vendorOrange,
                                        side: const BorderSide(
                                          color: Color(0xFF6E768A),
                                          width: 1.3,
                                        ),
                                        shape: RoundedRectangleBorder(
                                          borderRadius: BorderRadius.circular(3),
                                        ),
                                        onChanged: _submitting
                                            ? null
                                            : (value) => setState(
                                                  () => _rememberMe = value ?? false,
                                                ),
                                      ),
                                    ),
                                    const SizedBox(width: 8),
                                    const Expanded(
                                      child: Text(
                                        'Rester connecté',
                                        style: TextStyle(
                                          color: vendorText,
                                          fontSize: 12.5,
                                          fontWeight: FontWeight.w600,
                                        ),
                                      ),
                                    ),
                                    TextButton(
                                      style: TextButton.styleFrom(
                                        padding: const EdgeInsets.symmetric(
                                          horizontal: 4,
                                          vertical: 8,
                                        ),
                                      ),
                                      onPressed: _submitting
                                          ? null
                                          : () => Navigator.of(context).push(
                                                MaterialPageRoute(
                                                  builder: (_) {
                                                    final identifier = _identifier.text.trim();
                                                    return ForgotPasswordScreen(
                                                      initialIdentifier: identifier.contains('@') ? identifier : '',
                                                    );
                                                  },
                                                ),
                                              ),
                                      child: const Text(
                                        'Mot de passe oublié ?',
                                        style: TextStyle(
                                          color: vendorOrange,
                                          fontSize: 12.5,
                                          fontWeight: FontWeight.w700,
                                        ),
                                      ),
                                    ),
                                  ],
                                ),
                                const SizedBox(height: 12),
                                VendorPrimaryButton(
                                  label: 'Se connecter',
                                  onPressed: _submit,
                                  loading: _submitting,
                                ),
                                const SizedBox(height: 22),
                                const Row(
                                  children: [
                                    Expanded(child: Divider(color: Color(0xFFD8DDE5))),
                                    Padding(
                                      padding: EdgeInsets.symmetric(horizontal: 12),
                                      child: Text(
                                        'Nouveau vendeur sur OVANIE ?',
                                        style: TextStyle(
                                          color: vendorMuted,
                                          fontSize: 12.5,
                                        ),
                                      ),
                                    ),
                                    Expanded(child: Divider(color: Color(0xFFD8DDE5))),
                                  ],
                                ),
                                const SizedBox(height: 10),
                                TextButton.icon(
                                  onPressed: _submitting ? null : _openOnboarding,
                                  iconAlignment: IconAlignment.end,
                                  icon: const Icon(
                                    Icons.chevron_right_rounded,
                                    color: Color(0xFF073FE2),
                                  ),
                                  label: const Text(
                                    'Créer mon compte vendeur',
                                    style: TextStyle(
                                      color: Color(0xFF073FE2),
                                      fontSize: 15,
                                      fontWeight: FontWeight.w600,
                                    ),
                                  ),
                                ),
                                const SizedBox(height: 18),
                                const Divider(color: Color(0xFFEEF0F4)),
                                const SizedBox(height: 14),
                                const Row(
                                  mainAxisAlignment: MainAxisAlignment.center,
                                  children: [
                                    Icon(
                                      Icons.verified_user_outlined,
                                      color: Color(0xFF073FE2),
                                      size: 25,
                                    ),
                                    SizedBox(width: 9),
                                    Text(
                                      'Connexion sécurisée OVANIE',
                                      style: TextStyle(
                                        color: vendorMuted,
                                        fontSize: 12.8,
                                        fontWeight: FontWeight.w600,
                                      ),
                                    ),
                                  ],
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
