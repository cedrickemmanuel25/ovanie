// Aperçu de design des écrans de démarrage et de connexion.
// Lancement : flutter run -t lib/main_preview.dart (avec ORG_GRADLE_PROJECT_ovaniePreview=true
// pour l'installer à côté de l'application réelle). Ce fichier n'est pas inclus dans l'application livrée.
// ignore_for_file: invalid_use_of_visible_for_testing_member
import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';

import 'app/theme.dart';
import 'core/ui/ovanie_ui.dart';
import 'features/auth/forgot_password_screen.dart';
import 'features/auth/login_screen.dart';
import 'features/auth/reset_password_screen.dart';
import 'features/auth/vendor_journey_screen.dart';
import 'features/onboarding/shop_onboarding_screen.dart';
import 'features/splash/splash_screen.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  runApp(const _PreviewApp());
}

class _PreviewApp extends StatelessWidget {
  const _PreviewApp();

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      locale: const Locale('fr', 'FR'),
      supportedLocales: const <Locale>[Locale('fr', 'FR')],
      localizationsDelegates: GlobalMaterialLocalizations.delegates,
      debugShowCheckedModeBanner: false,
      title: 'OVANIE Aperçu',
      theme: OvanieTheme.light,
      builder: (context, child) {
        final clamped = MediaQuery.textScalerOf(context).clamp(minScaleFactor: 0.9, maxScaleFactor: 1.15);
        return MediaQuery(data: MediaQuery.of(context).copyWith(textScaler: clamped), child: child!);
      },
      home: const _Gallery(),
    );
  }
}

class _Gallery extends StatelessWidget {
  const _Gallery();

  static final _screens = <(String, Widget Function())>[
    ('1. Lancement animé', () => const SplashScreen()),
    ('2. Parcours vendeur', () => const VendorJourneyScreen()),
    ('3. Connexion', () => const LoginScreen()),
    ('4. Mot de passe oublié', () => const ForgotPasswordScreen()),
    ('5. Oublié : lien envoyé', () => const ForgotPasswordScreen(
          initialIdentifier: 'vendeur@exemple.ci',
          previewSentMessage: 'Si un compte correspond à ces informations, un lien de récupération a été envoyé.',
        )),
    ('6. Nouveau mot de passe', () => const ResetPasswordScreen(token: 'apercu', email: 'vendeur@exemple.ci')),
    ('7. Mot de passe modifié', () => const ResetPasswordScreen(token: 'apercu', email: 'vendeur@exemple.ci', previewDone: true)),
    for (var step = 0; step < 5; step++)
      ('${8 + step}. Ouverture de boutique, étape ${step + 1}', () => ShopOnboardingScreen(previewStep: step)),
  ];

  @override
  Widget build(BuildContext context) {
    return OvTheme(
      child: Scaffold(
        backgroundColor: Ov.page,
        appBar: AppBar(title: const Text('Aperçu des écrans', style: TextStyle(fontFamily: Ov.font, fontWeight: FontWeight.w800))),
        body: ListView.separated(
          padding: const EdgeInsets.all(16),
          itemCount: _screens.length,
          separatorBuilder: (_, __) => const SizedBox(height: 10),
          itemBuilder: (context, index) {
            final (label, builder) = _screens[index];
            return SizedBox(
              height: 64,
              child: OvButton(
                label: label,
                secondary: true,
                onPressed: () => Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => builder())),
              ),
            );
          },
        ),
      ),
    );
  }
}
