import 'package:flutter/material.dart';

import '../../features/auth/presentation/auth_branding.dart';

/// Carte « Connectez-vous pour accéder à ce contenu » : illustration du
/// magasin fermé, explication et accès au compte.
class AuthRequiredView extends StatelessWidget {
  final VoidCallback onOpenAccount;
  final EdgeInsetsGeometry padding;

  const AuthRequiredView({
    super.key,
    required this.onOpenAccount,
    this.padding = const EdgeInsets.fromLTRB(15, 20, 15, 24),
  });

  @override
  Widget build(BuildContext context) {
    return OvanieAuthTheme(
      child: SingleChildScrollView(
        padding: padding,
        child: Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 520),
            child: Container(
              padding: const EdgeInsets.fromLTRB(8, 8, 8, 16),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(18),
                boxShadow: const [BoxShadow(color: Color(0x120A1B4F), blurRadius: 24, offset: Offset(0, 8))],
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  ClipRRect(
                    borderRadius: BorderRadius.circular(12),
                    child: AspectRatio(
                      aspectRatio: 824 / 636,
                      child: Image.asset('assets/images/auth/gate.jpg', fit: BoxFit.cover, excludeFromSemantics: true),
                    ),
                  ),
                  const Padding(
                    padding: EdgeInsets.fromLTRB(10, 20, 10, 0),
                    child: Text(
                      'Connectez-vous pour accéder à ce contenu',
                      textAlign: TextAlign.center,
                      style: TextStyle(color: authNavy, fontSize: 22, height: 1.2, fontWeight: FontWeight.w800, letterSpacing: -.5),
                    ),
                  ),
                  const Padding(
                    padding: EdgeInsets.fromLTRB(14, 12, 14, 18),
                    child: Text(
                      'Si vous n’êtes pas encore enregistré, créez votre compte avec votre adresse e-mail.',
                      textAlign: TextAlign.center,
                      style: TextStyle(color: authMuted, fontSize: 14, height: 1.45, fontWeight: FontWeight.w500),
                    ),
                  ),
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 4),
                    child: OvaniePrimaryButton(label: 'Accéder au compte', onPressed: onOpenAccount),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

/// Page complète pour un onglet réservé aux clients connectés : bandeau bleu
/// nuit avec le logo, puis la carte d'accès au compte.
class AuthRequiredPage extends StatelessWidget {
  const AuthRequiredPage({super.key, required this.onOpenAccount});
  final VoidCallback onOpenAccount;

  @override
  Widget build(BuildContext context) {
    final top = MediaQuery.paddingOf(context).top;
    return OvanieAuthTheme(
      lightStatusBar: true,
      child: Scaffold(
        backgroundColor: const Color(0xFFF4F7FC),
        body: Column(
          children: [
            Container(
              width: double.infinity,
              color: const Color(0xFF0A2360),
              padding: EdgeInsets.fromLTRB(16, top + 22, 16, 22),
              child: const Center(child: OvanieLogo(white: true, width: 150)),
            ),
            Expanded(child: AuthRequiredView(onOpenAccount: onOpenAccount)),
          ],
        ),
      ),
    );
  }
}
