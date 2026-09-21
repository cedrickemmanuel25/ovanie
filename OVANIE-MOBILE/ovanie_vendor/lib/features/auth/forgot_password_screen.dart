import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../core/network/api_client.dart';
import '../../core/ui/vendor_design.dart';
import '../../data/vendor_repository.dart';

class ForgotPasswordScreen extends StatefulWidget {
  final String? initialIdentifier;

  const ForgotPasswordScreen({super.key, this.initialIdentifier});

  @override
  State<ForgotPasswordScreen> createState() => _ForgotPasswordScreenState();
}

class _ForgotPasswordScreenState extends State<ForgotPasswordScreen> {
  final _form = GlobalKey<FormState>();
  late final TextEditingController _identifier;
  bool _sending = false;
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

  Future<void> _send() async {
    FocusScope.of(context).unfocus();
    if (!_form.currentState!.validate()) return;
    setState(() {
      _sending = true;
      _error = null;
      _success = null;
    });
    try {
      final message = await VendorRepository.instance.forgotPassword(_identifier.text);
      if (!mounted) return;
      setState(() => _success = message);
    } catch (error) {
      if (mounted) setState(() => _error = ApiClient.friendlyError(error));
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final width = MediaQuery.sizeOf(context).width;
    final headerHeight = width * (525 / 941);

    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.light.copyWith(statusBarColor: Colors.transparent),
      child: Scaffold(
        backgroundColor: Colors.white,
        body: SingleChildScrollView(
          child: Column(
            children: [
              Stack(
                children: [
                  Image.asset(
                    'assets/images/vendor_forgot_header.jpg',
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
                  padding: const EdgeInsets.fromLTRB(26, 0, 26, 32),
                  child: Form(
                    key: _form,
                    child: Column(
                      children: [
                        Transform.translate(
                          offset: const Offset(0, -34),
                          child: Container(
                            width: 72,
                            height: 72,
                            decoration: BoxDecoration(
                              color: Colors.white,
                              shape: BoxShape.circle,
                              boxShadow: const [BoxShadow(color: Color(0x22001A45), blurRadius: 15, offset: Offset(0, 7))],
                            ),
                            padding: const EdgeInsets.all(8),
                            child: const VendorCircleIcon(
                              icon: Icons.enhanced_encryption_outlined,
                              color: vendorOrange,
                              background: vendorNavyDeep,
                              size: 56,
                              iconSize: 29,
                            ),
                          ),
                        ),
                        Transform.translate(
                          offset: const Offset(0, -16),
                          child: Column(
                            children: [
                              const Text(
                                'Mot de passe oublié',
                                textAlign: TextAlign.center,
                                style: TextStyle(color: vendorText, fontSize: 27, fontWeight: FontWeight.w900),
                              ),
                              const SizedBox(height: 12),
                              const Text(
                                'Entrez l’adresse e-mail associée à votre compte vendeur\npour recevoir un lien sécurisé de réinitialisation.',
                                textAlign: TextAlign.center,
                                style: TextStyle(color: vendorMuted, fontSize: 14.5, height: 1.55),
                              ),
                              const SizedBox(height: 26),
                              TextFormField(
                                controller: _identifier,
                                keyboardType: TextInputType.emailAddress,
                                textInputAction: TextInputAction.done,
                                onFieldSubmitted: (_) => _send(),
                                decoration: vendorInputDecoration(
                                  hint: 'Adresse e-mail',
                                  icon: Icons.person_outline_rounded,
                                ),
                                validator: (value) {
                                  final email = (value ?? '').trim();
                                  if (email.isEmpty) return 'Renseignez votre adresse e-mail.';
                                  if (!email.contains('@')) return 'Adresse e-mail invalide.';
                                  return null;
                                },
                              ),
                              const SizedBox(height: 9),
                              const Text(
                                'Le lien sera envoyé à l’adresse e-mail associée au compte.',
                                textAlign: TextAlign.center,
                                style: TextStyle(color: vendorMuted, fontSize: 12.8),
                              ),
                              if (_error != null) ...[
                                const SizedBox(height: 12),
                                VendorErrorBox(_error),
                              ],
                              if (_success != null) ...[
                                const SizedBox(height: 12),
                                VendorInfoBox(
                                  icon: Icons.check_circle_outline,
                                  child: Text(_success!, style: const TextStyle(color: vendorText, fontSize: 12.8, height: 1.35)),
                                ),
                              ],
                              const SizedBox(height: 22),
                              VendorPrimaryButton(
                                label: _sending ? 'Envoi en cours…' : 'Envoyer les instructions',
                                onPressed: _send,
                                loading: _sending,
                              ),
                              const SizedBox(height: 18),
                              TextButton(
                                onPressed: () => Navigator.maybePop(context),
                                child: const Text(
                                  'Retour à la connexion',
                                  style: TextStyle(color: Color(0xFF073FE2), fontSize: 15.5),
                                ),
                              ),
                              const SizedBox(height: 30),
                              const Row(
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: [
                                  Icon(Icons.verified_user_outlined, color: Color(0xFF073FE2), size: 26),
                                  SizedBox(width: 10),
                                  Text('Réinitialisation sécurisée', style: TextStyle(color: vendorMuted, fontSize: 14.5)),
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
            ],
          ),
        ),
      ),
    );
  }
}
