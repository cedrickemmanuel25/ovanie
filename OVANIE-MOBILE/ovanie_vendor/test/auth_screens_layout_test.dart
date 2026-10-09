// ignore_for_file: invalid_use_of_visible_for_testing_member
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ovanie_vendor/app/theme.dart';
import 'package:ovanie_vendor/features/auth/forgot_password_screen.dart';
import 'package:ovanie_vendor/features/auth/login_screen.dart';
import 'package:ovanie_vendor/features/auth/reset_password_screen.dart';
import 'package:ovanie_vendor/features/auth/vendor_journey_screen.dart';

/// Les écrans de démarrage et de connexion doivent s'afficher sans débordement
/// sur un petit téléphone, un téléphone courant et un grand téléphone, y compris
/// quand l'utilisateur a agrandi la taille du texte.
void main() {
  // La vraie police de l'application est chargée : sans elle, les tests mesurent
  // le texte avec une police de remplacement deux fois plus large.
  setUpAll(() async {
    final loader = FontLoader('PlusJakartaSans');
    for (final weight in <String>['Regular', 'Medium', 'SemiBold', 'Bold', 'ExtraBold']) {
      final bytes = File('assets/fonts/PlusJakartaSans-$weight.ttf').readAsBytesSync();
      loader.addFont(Future<ByteData>.value(ByteData.view(bytes.buffer)));
    }
    await loader.load();
  });

  const sizes = <String, Size>{
    'petit téléphone': Size(320, 568),
    'téléphone courant': Size(360, 780),
    'grand téléphone': Size(412, 915),
  };

  final screens = <String, Widget Function()>{
    'parcours vendeur': () => const VendorJourneyScreen(),
    'connexion': () => const LoginScreen(),
    'mot de passe oublié': () => const ForgotPasswordScreen(),
    'mot de passe oublié, lien envoyé': () => const ForgotPasswordScreen(
          initialIdentifier: 'vendeur@exemple.ci',
          previewSentMessage: 'Si un compte correspond à ces informations, un lien de récupération a été envoyé.',
        ),
    'nouveau mot de passe': () => const ResetPasswordScreen(token: 't', email: 'vendeur@exemple.ci'),
    'mot de passe modifié': () => const ResetPasswordScreen(token: 't', email: 'vendeur@exemple.ci', previewDone: true),
  };

  for (final screen in screens.entries) {
    for (final size in sizes.entries) {
      for (final scale in <double>[1, 1.15]) {
        testWidgets('${screen.key} — ${size.key} — texte ×$scale', (tester) async {
          tester.view.physicalSize = size.value;
          tester.view.devicePixelRatio = 1;
          addTearDown(tester.view.reset);

          await tester.pumpWidget(
            MaterialApp(
              locale: const Locale('fr', 'FR'),
              supportedLocales: const <Locale>[Locale('fr', 'FR')],
              localizationsDelegates: GlobalMaterialLocalizations.delegates,
              theme: OvanieTheme.light,
              builder: (context, child) => MediaQuery(
                data: MediaQuery.of(context).copyWith(textScaler: TextScaler.linear(scale)),
                child: child!,
              ),
              home: screen.value(),
            ),
          );
          await tester.pump(const Duration(seconds: 2));

          expect(tester.takeException(), isNull);
        });
      }
    }
  }

  testWidgets('connexion : les champs vides affichent un message clair', (tester) async {
    await tester.pumpWidget(MaterialApp(theme: OvanieTheme.light, home: const LoginScreen()));
    await tester.pump(const Duration(seconds: 2));

    await tester.ensureVisible(find.text('Se connecter'));
    await tester.tap(find.text('Se connecter'));
    await tester.pump();

    expect(find.text('Renseignez votre e-mail ou votre numéro de téléphone.'), findsOneWidget);
    expect(find.text('Renseignez votre mot de passe.'), findsOneWidget);
  });

  testWidgets('mot de passe oublié : une adresse invalide est refusée', (tester) async {
    await tester.pumpWidget(MaterialApp(theme: OvanieTheme.light, home: const ForgotPasswordScreen()));
    await tester.pump(const Duration(seconds: 2));

    await tester.enterText(find.byType(TextFormField), 'pas-une-adresse');
    await tester.ensureVisible(find.text('Envoyer le lien'));
    await tester.tap(find.text('Envoyer le lien'));
    await tester.pump();

    expect(find.text('Cette adresse e-mail n’est pas valide.'), findsOneWidget);
  });

  testWidgets('nouveau mot de passe : les deux saisies doivent être identiques', (tester) async {
    await tester.pumpWidget(
      MaterialApp(theme: OvanieTheme.light, home: const ResetPasswordScreen(token: 't', email: 'vendeur@exemple.ci')),
    );
    await tester.pump(const Duration(seconds: 2));

    final fields = find.byType(TextFormField);
    await tester.enterText(fields.at(1), 'MotDePasse1');
    await tester.enterText(fields.at(2), 'Different1');
    await tester.pump();

    expect(find.text('Les deux mots de passe ne sont pas identiques.'), findsOneWidget);
  });
}
