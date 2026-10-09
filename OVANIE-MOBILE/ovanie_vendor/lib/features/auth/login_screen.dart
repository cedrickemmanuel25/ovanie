import 'dart:math' as math;

import 'package:flutter/material.dart';

import '../../core/network/api_client.dart';
import '../../core/ui/vendor_mockup_ui.dart';
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
  // Coché par défaut : le vendeur reste connecté tant qu'il ne se déconnecte pas.
  bool _rememberMe = true;
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
    Navigator.of(context).pushReplacement(MaterialPageRoute<void>(builder: (_) => target));
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
      await VendorSession.instance.login(_identifier.text, _password.text, persist: _rememberMe);
      if (!mounted) return;

      final target = VendorSession.instance.hasShop ? const VendorShell() : const ShopOnboardingScreen();
      Navigator.of(context).pushAndRemoveUntil(MaterialPageRoute<void>(builder: (_) => target), (_) => false);
    } catch (error) {
      if (mounted) setState(() => _error = ApiClient.friendlyError(error));
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  void _openForgotPassword() {
    final identifier = _identifier.text.trim();
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => ForgotPasswordScreen(initialIdentifier: identifier.contains('@') ? identifier : ''),
      ),
    );
  }

  void _openOnboarding() {
    Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => const ShopOnboardingScreen()));
  }

  // Repères de la maquette (941 px de large) : début de la feuille blanche et
  // pastille « boutique » à cheval sur la photo.
  static const _photoAspect = 941 / 800;
  static const _sheetTop = 718 / 941;
  static const _badgeCenter = 728 / 941;
  static const _badgeSize = 172 / 941;

  @override
  Widget build(BuildContext context) {
    final padding = MediaQuery.paddingOf(context);

    return MkTheme(
      child: Scaffold(
        backgroundColor: Colors.white,
        body: LayoutBuilder(
          builder: (context, viewport) {
            final width = math.min(viewport.maxWidth, Mk.maxWidth);
            final sheetTop = padding.top + width * _sheetTop;
            final badge = width * _badgeSize;

            return SingleChildScrollView(
              keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
              child: Center(
                child: SizedBox(
                  width: width,
                  child: Stack(
                    children: <Widget>[
                      MkPhoto(name: 'login', width: width, aspect: _photoAspect, label: 'OVANIE Vendeur'),
                      Padding(
                        padding: EdgeInsets.only(top: sheetTop),
                        child: Container(
                          constraints: BoxConstraints(minHeight: math.max(0, viewport.maxHeight - sheetTop)),
                          padding: EdgeInsets.fromLTRB(24, badge / 2 + 12, 24, padding.bottom + 20),
                          decoration: const BoxDecoration(
                            color: Colors.white,
                            borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
                          ),
                          child: _content(),
                        ),
                      ),
                      Positioned(
                        top: padding.top + width * _badgeCenter - badge / 2,
                        left: (width - badge) / 2,
                        child: MkBadge(icon: Icons.storefront_rounded, size: badge),
                      ),
                      Positioned(
                        top: padding.top + width * .02,
                        left: width * .012,
                        child: MkPhotoBack(onTap: () => Navigator.of(context).maybePop(), size: width * .13),
                      ),
                    ],
                  ),
                ),
              ),
            );
          },
        ),
      ),
    );
  }

  Widget _content() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        const Text(
          'Connexion vendeur',
          textAlign: TextAlign.center,
          style: TextStyle(color: Mk.navy, fontSize: 24, fontWeight: FontWeight.w800, height: 1.15, letterSpacing: -.5),
        ),
        const SizedBox(height: 8),
        const Text(
          'Accédez à votre espace vendeur pour gérer votre boutique, vos produits et vos commandes.',
          textAlign: TextAlign.center,
          style: TextStyle(color: Mk.muted, fontSize: 13.5, height: 1.4, fontWeight: FontWeight.w500),
        ),
        const SizedBox(height: 18),
        Form(
          key: _form,
          child: AutofillGroup(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: <Widget>[
                if (_error != null) ...<Widget>[MkNotice(_error!), const SizedBox(height: 14)],
                Semantics(
                  label: 'E-mail ou numéro de téléphone',
                  child: TextFormField(
                    controller: _identifier,
                    enabled: !_submitting,
                    style: mkFieldText,
                    keyboardType: TextInputType.emailAddress,
                    textInputAction: TextInputAction.next,
                    autovalidateMode: AutovalidateMode.onUserInteraction,
                    autofillHints: const <String>[
                      AutofillHints.username,
                      AutofillHints.email,
                      AutofillHints.telephoneNumber,
                    ],
                    decoration: mkInput(hint: 'Email ou numéro de téléphone', icon: Icons.person_rounded),
                    validator: (value) =>
                        (value ?? '').trim().isEmpty ? 'Renseignez votre e-mail ou votre numéro de téléphone.' : null,
                  ),
                ),
                const SizedBox(height: 12),
                Semantics(
                  label: 'Mot de passe',
                  child: TextFormField(
                    controller: _password,
                    enabled: !_submitting,
                    obscureText: _obscure,
                    style: mkFieldText,
                    textInputAction: TextInputAction.done,
                    autovalidateMode: AutovalidateMode.onUserInteraction,
                    autofillHints: const <String>[AutofillHints.password],
                    onFieldSubmitted: (_) => _submit(),
                    decoration: mkInput(
                      hint: 'Mot de passe',
                      icon: Icons.lock_rounded,
                      suffix: MkEye(
                        hidden: _obscure,
                        onTap: _submitting ? null : () => setState(() => _obscure = !_obscure),
                      ),
                    ),
                    validator: (value) => (value ?? '').isEmpty ? 'Renseignez votre mot de passe.' : null,
                  ),
                ),
                const SizedBox(height: 4),
                // Sur un écran étroit ou avec un texte agrandi, le lien passe à la ligne.
                Wrap(
                  alignment: WrapAlignment.spaceBetween,
                  crossAxisAlignment: WrapCrossAlignment.center,
                  children: <Widget>[
                    InkWell(
                      borderRadius: BorderRadius.circular(10),
                      onTap: _submitting ? null : () => setState(() => _rememberMe = !_rememberMe),
                      child: Padding(
                        padding: const EdgeInsets.symmetric(vertical: 12),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: <Widget>[
                            SizedBox.square(
                              dimension: 22,
                              child: Checkbox(
                                value: _rememberMe,
                                activeColor: Mk.blue,
                                side: const BorderSide(color: Mk.muted, width: 1.5),
                                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(5)),
                                onChanged: _submitting ? null : (value) => setState(() => _rememberMe = value ?? false),
                              ),
                            ),
                            const SizedBox(width: 10),
                            const Flexible(
                              child: Text(
                                'Rester connecté',
                                style: TextStyle(color: Mk.muted, fontSize: 14, fontWeight: FontWeight.w500),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                    TextButton(
                      onPressed: _submitting ? null : _openForgotPassword,
                      style: TextButton.styleFrom(
                        foregroundColor: Mk.orange,
                        minimumSize: const Size(48, 48),
                        padding: const EdgeInsets.symmetric(horizontal: 4),
                        textStyle: const TextStyle(fontFamily: Mk.font, fontSize: 14, fontWeight: FontWeight.w700),
                      ),
                      child: const Text('Mot de passe oublié ?'),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                MkButton(label: _submitting ? 'Connexion…' : 'Se connecter', loading: _submitting, onPressed: _submit),
              ],
            ),
          ),
        ),
        const SizedBox(height: 18),
        const MkDivider('Nouveau vendeur sur OVANIE ?'),
        Center(
          child: TextButton(
            onPressed: _submitting ? null : _openOnboarding,
            style: TextButton.styleFrom(
              foregroundColor: Mk.blue,
              minimumSize: const Size(48, 48),
              textStyle: const TextStyle(fontFamily: Mk.font, fontSize: 15, fontWeight: FontWeight.w700),
            ),
            child: const Row(
              mainAxisSize: MainAxisSize.min,
              children: <Widget>[
                Flexible(child: Text('Créer mon compte vendeur')),
                SizedBox(width: 4),
                Icon(Icons.chevron_right_rounded, size: 22),
              ],
            ),
          ),
        ),
        const SizedBox(height: 10),
        const MkSecureLine('Connexion sécurisée OVANIE'),
      ],
    );
  }
}
