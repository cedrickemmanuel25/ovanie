import 'package:flutter/material.dart';

import '../../../core/network/api_client.dart';
import '../../../core/platform/external_url_launcher.dart';
import '../../../core/reference_data/reference_data_store.dart';
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
      final message = await _repository.forgotPassword(email: _identifier.text);
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
      MaterialPageRoute<void>(builder: (_) => LoginScreen(initialIdentifier: _identifier.text.trim())),
    );
  }

  Future<void> _support() async {
    final store = OvanieReferenceDataStore.instance;
    if (await ExternalUrlLauncher.open(store.supportWhatsappUrl)) return;
    if (await ExternalUrlLauncher.open(store.supportTelUrl)) return;
    if (!mounted) return;
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text('Support client : ${store.supportNumberDisplay}')));
  }

  @override
  Widget build(BuildContext context) {
    final padding = MediaQuery.paddingOf(context);
    return OvanieAuthTheme(
      child: Scaffold(
        backgroundColor: const Color(0xFFF7F9FD),
        body: LayoutBuilder(
          builder: (context, viewport) {
            final width = viewport.maxWidth.clamp(0.0, 560.0);
            return SingleChildScrollView(
              keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
              child: ConstrainedBox(
                constraints: BoxConstraints(minHeight: viewport.maxHeight),
                child: Center(
                  child: SizedBox(
                    width: width,
                    child: Form(
                      key: _formKey,
                      child: Column(
                        children: [
                          Stack(
                            children: [
                              Padding(
                                padding: EdgeInsets.only(top: padding.top + width * .06),
                                child: ShaderMask(
                                  blendMode: BlendMode.dstIn,
                                  shaderCallback: (rect) => const LinearGradient(
                                    begin: Alignment.topCenter,
                                    end: Alignment.bottomCenter,
                                    colors: [Colors.transparent, Colors.white, Colors.white, Colors.transparent],
                                    stops: [0, .14, .86, 1],
                                  ).createShader(rect),
                                  child: AspectRatio(
                                    aspectRatio: 941 / 650,
                                    child: Image.asset(
                                      'assets/images/auth/forgot.jpg',
                                      fit: BoxFit.cover,
                                      excludeFromSemantics: true,
                                    ),
                                  ),
                                ),
                              ),
                              Positioned(
                                top: padding.top + width * .07,
                                left: 0,
                                right: 0,
                                child: Center(child: OvanieLogo(width: width * .33)),
                              ),
                              Positioned(
                                top: padding.top + 2,
                                left: 4,
                                child: IconButton(
                                  tooltip: 'Retour',
                                  onPressed: _openLogin,
                                  icon: const Icon(Icons.arrow_back_rounded, color: authNavy, size: 24),
                                ),
                              ),
                            ],
                          ),
                          Padding(
                            padding: EdgeInsets.fromLTRB(20, 8, 20, padding.bottom + 20),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.stretch,
                              children: [
                                const Text(
                                  'Mot de passe oublié',
                                  textAlign: TextAlign.center,
                                  style: TextStyle(
                                    color: authNavy,
                                    fontSize: 26,
                                    height: 1.2,
                                    fontWeight: FontWeight.w800,
                                    letterSpacing: -.7,
                                  ),
                                ),
                                const SizedBox(height: 8),
                                const Text(
                                  'Saisissez l’e-mail de votre compte : nous vous enverrons un lien de réinitialisation.',
                                  textAlign: TextAlign.center,
                                  style: TextStyle(
                                    color: authMuted,
                                    fontSize: 14,
                                    height: 1.45,
                                    fontWeight: FontWeight.w500,
                                  ),
                                ),
                                const SizedBox(height: 18),
                                if (_error != null) ...[
                                  OvanieAuthMessage(message: _error!),
                                  const SizedBox(height: 12),
                                ],
                                if (_success != null) ...[
                                  OvanieAuthMessage(message: _success!, success: true),
                                  const SizedBox(height: 12),
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
                                    if (!RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$').hasMatch(email)) {
                                      return 'Adresse e-mail invalide.';
                                    }
                                    return null;
                                  },
                                ),
                                const SizedBox(height: 12),
                                OvaniePrimaryButton(label: 'Envoyer le lien', loading: _loading, onPressed: _submit),
                                const SizedBox(height: 8),
                                OvanieAuthSwitchLink(
                                  question: 'Vous vous en souvenez ?',
                                  action: 'Retour à la connexion',
                                  onTap: _loading ? null : _openLogin,
                                ),
                                const SizedBox(height: 12),
                                Container(
                                  padding: const EdgeInsets.fromLTRB(14, 14, 14, 14),
                                  decoration: BoxDecoration(
                                    borderRadius: BorderRadius.circular(14),
                                    border: Border.all(color: const Color(0xFFE2E7F0)),
                                  ),
                                  child: Column(
                                    children: [
                                      const Text(
                                        'Plus accès à votre e-mail ?',
                                        style: TextStyle(color: authNavy, fontSize: 14, fontWeight: FontWeight.w700),
                                      ),
                                      InkWell(
                                        onTap: _support,
                                        borderRadius: BorderRadius.circular(6),
                                        child: const Padding(
                                          padding: EdgeInsets.symmetric(horizontal: 8, vertical: 8),
                                          child: Text(
                                            'Contacter le support',
                                            style: TextStyle(
                                              color: authBlue,
                                              fontSize: 14,
                                              fontWeight: FontWeight.w600,
                                            ),
                                          ),
                                        ),
                                      ),
                                      Text(
                                        'Support client : ${OvanieReferenceDataStore.instance.supportNumberDisplay}',
                                        style: const TextStyle(
                                          color: authMuted,
                                          fontSize: 13,
                                          fontWeight: FontWeight.w500,
                                        ),
                                      ),
                                    ],
                                  ),
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
            );
          },
        ),
      ),
    );
  }
}
