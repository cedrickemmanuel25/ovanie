@Tags(['render'])
library;

import 'dart:io';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ovanie_app/app/theme.dart';
import 'package:ovanie_app/core/compatibility/schema_compatibility.dart';
import 'package:ovanie_app/features/auth/presentation/auth_branding.dart';
import 'package:ovanie_app/features/auth/presentation/forgot_password_screen.dart';
import 'package:ovanie_app/features/auth/presentation/login_screen.dart';
import 'package:ovanie_app/features/auth/presentation/register_screen.dart';
import 'package:ovanie_app/features/auth/presentation/reset_password_screen.dart';
import 'package:ovanie_app/features/auth/presentation/session_expired_screen.dart';
import 'package:ovanie_app/features/splash/presentation/splash_screen.dart';
import 'package:ovanie_app/shared/widgets/auth_required_view.dart';
import 'package:ovanie_app/shared/widgets/offline_network_screen.dart';

/// Rendu des écrans de connexion et d'état de l'app Client, pour les comparer
/// aux maquettes.
///
/// flutter test test/auth_render_test.dart --tags render
///   --dart-define=RENDER_OUT=dossier --dart-define=ICON_FONT=chemin/materialicons-regular.otf
void main() {
  const out = String.fromEnvironment('RENDER_OUT');
  const iconFont = String.fromEnvironment('ICON_FONT');

  setUpAll(() async {
    final loader = FontLoader('PlusJakartaSans');
    for (final weight in <String>['Regular', 'Medium', 'SemiBold', 'Bold', 'ExtraBold']) {
      final bytes = File('assets/fonts/PlusJakartaSans-$weight.ttf').readAsBytesSync();
      loader.addFont(Future<ByteData>.value(ByteData.view(bytes.buffer)));
    }
    await loader.load();
    if (iconFont.isNotEmpty) {
      final icons = FontLoader('MaterialIcons')
        ..addFont(Future<ByteData>.value(ByteData.view(File(iconFont).readAsBytesSync().buffer)));
      await icons.load();
    }
  });

  Future<void> shoot(
    WidgetTester tester,
    String name,
    Widget screen, {
    Future<void> Function()? act,
    double height = 640,
    bool app = true,
  }) async {
    tester.view.physicalSize = Size(360, height) * 2;
    tester.view.devicePixelRatio = 2;
    addTearDown(tester.view.reset);
    final boundary = GlobalKey();
    await tester.pumpWidget(
      app
          ? MaterialApp(
              theme: OvanieTheme.light,
              builder: (context, child) => RepaintBoundary(key: boundary, child: child),
              home: screen,
            )
          : RepaintBoundary(key: boundary, child: screen),
    );
    await tester.pump(const Duration(milliseconds: 300));
    if (act != null) await act();
    expect(tester.takeException(), isNull);
    if (out.isNotEmpty) {
      await tester.runAsync(() async {
        for (final element in find.byType(Image).evaluate()) {
          await precacheImage((element.widget as Image).image, element);
        }
      });
      await tester.pump(const Duration(milliseconds: 100));
      final bytes = await tester.runAsync(() async {
        final render = boundary.currentContext!.findRenderObject()! as RenderRepaintBoundary;
        final image = await render.toImage(pixelRatio: 2);
        final data = await image.toByteData(format: ui.ImageByteFormat.png);
        return data!.buffer.asUint8List();
      });
      File('$out/$name.png').writeAsBytesSync(bytes!);
    }
    await tester.pumpWidget(const SizedBox.shrink());
    await tester.pump(const Duration(seconds: 1));
  }

  testWidgets('connexion', (tester) async {
    await shoot(tester, 'login', const LoginScreen());
  });
  testWidgets('reconnexion avec erreurs', (tester) async {
    await shoot(tester, 'login_erreurs', const LoginScreen(sessionRecovery: true), act: () async {
      await tester.tap(find.text('Se connecter'));
      await tester.pump(const Duration(milliseconds: 300));
      expect(find.text('Renseignez votre mot de passe.'), findsOneWidget);
    });
  });
  testWidgets('créer un compte', (tester) async {
    await shoot(tester, 'register', const RegisterScreen(), height: 760);
  });
  testWidgets('créer un compte : erreurs', (tester) async {
    await shoot(tester, 'register_erreurs', const RegisterScreen(), height: 900, act: () async {
      await tester.enterText(find.byType(TextFormField).at(0), 'Awa');
      await tester.enterText(find.byType(TextFormField).at(1), 'Koné');
      await tester.enterText(find.byType(TextFormField).at(4), 'motdepasse1');
      await tester.ensureVisible(find.text('Créer mon compte'));
      await tester.tap(find.text('Créer mon compte'));
      await tester.pump(const Duration(milliseconds: 300));
      expect(find.text('Adresse e-mail invalide.'), findsNothing);
      expect(find.text('Renseignez votre adresse e-mail.'), findsOneWidget);
      expect(find.textContaining('Vous devez accepter'), findsOneWidget);
    });
  });
  testWidgets('indicatif du pays', (tester) async {
    await shoot(tester, 'indicatif', const RegisterScreen(), act: () async {
      await tester.tap(find.text('+225'));
      await tester.pump(const Duration(milliseconds: 500));
      expect(find.text('Indicatif du pays'), findsOneWidget);
      expect(find.text('Burkina Faso'), findsOneWidget);
    });
  });
  testWidgets('mot de passe oublié', (tester) async {
    await shoot(tester, 'forgot', const ForgotPasswordScreen());
  });
  testWidgets('nouveau mot de passe', (tester) async {
    await shoot(
      tester,
      'reset',
      const ResetPasswordScreen(token: 'jeton', email: 'aminata.kone@exemple.com'),
      act: () async {
        await tester.enterText(find.byType(TextFormField).at(1), 'motdepasse12');
        await tester.enterText(find.byType(TextFormField).at(2), 'motdepasse12');
        await tester.pump(const Duration(milliseconds: 200));
        expect(find.text('Les mots de passe correspondent'), findsOneWidget);
      },
    );
  });
  testWidgets('connexion indisponible', (tester) async {
    await shoot(tester, 'offline', const OfflineNetworkScreen());
  });
  testWidgets('session expirée', (tester) async {
    await shoot(tester, 'session', SessionExpiredScreen(onReconnect: () async {}));
  });
  testWidgets('mise à jour requise', (tester) async {
    await shoot(
      tester,
      'update',
      const OvanieSchemaIncompatibleApp(result: SchemaCompatibilityResult(state: SchemaCompatibilityState.incompatible)),
      app: false,
    );
  });
  testWidgets('accès réservé', (tester) async {
    await shoot(tester, 'gate', AuthRequiredPage(onOpenAccount: () {}));
  });
  testWidgets('drapeaux', (tester) async {
    await shoot(
      tester,
      'flags',
      Scaffold(
        body: Center(
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [for (final c in authCountries) Padding(padding: const EdgeInsets.all(6), child: OvanieFlag(c.$2, size: 40))],
          ),
        ),
      ),
      height: 120,
    );
  });

  testWidgets('démarrage', (tester) async {
    await shoot(tester, 'splash', const SplashScreen());
  });
  testWidgets('démarrage : serveur injoignable', (tester) async {
    await shoot(tester, 'splash_erreur', const SplashScreen(), act: () async {
      for (var i = 0; i < 12; i++) {
        await tester.pump(const Duration(milliseconds: 500));
      }
    });
  });
}
