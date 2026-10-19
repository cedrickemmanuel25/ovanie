@Tags(['render'])
library;

import 'dart:convert';
import 'dart:io';
import 'dart:ui' as ui;

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ovanie_vendor/app/theme.dart';
import 'package:ovanie_vendor/core/network/api_client.dart';
import 'package:ovanie_vendor/features/auth/forgot_password_screen.dart';
import 'package:ovanie_vendor/features/menu/account_screens.dart';

/// Rendu des écrans « compte et boutique » dans chacun de leurs états, pour
/// les comparer aux maquettes.
///
/// flutter test test/account_render_test.dart --tags render
///   --dart-define=RENDER_OUT=dossier --dart-define=ICON_FONT=chemin/materialicons-regular.otf
void main() {
  const out = String.fromEnvironment('RENDER_OUT');
  const iconFont = String.fromEnvironment('ICON_FONT');
  late Map<String, dynamic> base;

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
    base = jsonDecode(File('test/fixtures/account.json').readAsStringSync()) as Map<String, dynamic>;
  });

  Map<String, dynamic> copy(String key) => jsonDecode(jsonEncode(base[key])) as Map<String, dynamic>;

  Map<String, dynamic> status(String tone) {
    final s = copy('shop/status');
    final steps = (s['steps'] as List).cast<Map<String, dynamic>>();
    final checks = (s['checks'] as List).cast<Map<String, dynamic>>();
    (s['products'] as Map)['active'] = 42;
    (s['products'] as Map)['total'] = 48;
    s['member_since'] = '2026-03-12T10:00:00Z';
    if (tone == 'pending') {
      s['status'] = {
        'label': 'Configuration logistique à compléter',
        'tone': 'pending',
        'description':
            'La boutique est ouverte, mais les nouveaux produits ne peuvent pas être publiés tant que la configuration logistique n’est pas complète.',
      };
      steps[2]
        ..['done'] = false
        ..['description'] = 'Éléments manquants : délai par défaut, volume maximum';
      steps[3]
        ..['done'] = false
        ..['description'] = 'La publication reste bloquée jusqu’à la résolution des éléments opérationnels manquants.';
      checks[3]
        ..['done'] = false
        ..['value'] = 'À compléter';
      s['logistics_missing'] = ['délai par défaut', 'volume maximum'];
    } else if (tone == 'inactive') {
      s['status'] = {
        'label': 'Boutique inactive',
        'tone': 'inactive',
        'description':
            'L’ouverture commerciale de la boutique est actuellement inactive. Vos obligations sur les commandes existantes restent accessibles.',
      };
      s['commercial_open'] = false;
      checks[1]
        ..['done'] = false
        ..['value'] = 'Fermée aux clients';
      steps[3]['done'] = false;
    } else if (tone == 'rejected') {
      s['status'] = {
        'label': 'Action administrative requise',
        'tone': 'rejected',
        'description':
            'La boutique fait l’objet d’une restriction administrative. Consultez la raison indiquée et contactez OVANIE si nécessaire.',
      };
      s['commercial_open'] = false;
      s['rejection_reason'] = 'Le document fiscal transmis est illisible.';
      checks[0]
        ..['done'] = false
        ..['value'] = 'Restreinte';
      checks[1]
        ..['done'] = false
        ..['value'] = 'Fermée aux clients';
      checks[2]
        ..['done'] = false
        ..['value'] = 'À renvoyer';
      for (final i in [1, 2, 3]) {
        steps[i]['done'] = false;
      }
      steps[1]['description'] = 'Complétez les documents d’identité manquants.';
    }
    return s;
  }

  Map<String, dynamic> documents(String state) {
    final d = copy('shop/documents');
    final docs = (d['documents'] as List).cast<Map<String, dynamic>>();
    Map<String, dynamic> doc(String key) => docs.firstWhere((x) => x['key'] == key);
    doc('rccm')
      ..['uploaded'] = true
      ..['file_type'] = 'pdf';
    doc('tax')
      ..['uploaded'] = true
      ..['file_type'] = 'pdf';
    if (state == 'pending') {
      d['kyc_status'] = 'pending';
      d['kyc_label'] = 'À compléter';
      d['kyc_description'] = 'Ajoutez les documents demandés pour faire vérifier votre boutique.';
      doc('identity_pdf')['uploaded'] = false;
      doc('rccm')['uploaded'] = false;
      doc('tax')['uploaded'] = false;
    } else if (state == 'verified') {
      d['kyc_status'] = 'verified';
      d['kyc_label'] = 'Vérifiés';
      d['kyc_description'] = 'Vos documents ont été vérifiés par OVANIE.';
    } else if (state == 'rejected') {
      d['kyc_status'] = 'rejected';
      d['kyc_label'] = 'À renvoyer';
      d['kyc_description'] = 'Un ou plusieurs documents ont été refusés. Envoyez une nouvelle version lisible.';
    }
    return d;
  }

  Map<String, dynamic> security(bool alone) {
    final s = copy('account/security');
    if (alone) {
      s['sessions'] = [(s['sessions'] as List).firstWhere((x) => (x as Map)['is_current'] == true)];
      s['email_verified'] = true;
      s['phone_verified'] = true;
    }
    return s;
  }

  Future<void> shoot(WidgetTester tester, String name, Widget screen, Object Function(String path) payload, {
    Future<void> Function()? act,
    double height = 1500,
  }) async {
    tester.view.physicalSize = Size(360, height) * 2;
    tester.view.devicePixelRatio = 2;
    addTearDown(tester.view.reset);
    ApiClient.dio.interceptors.add(InterceptorsWrapper(onRequest: (options, handler) {
      final data = payload(options.path);
      handler.resolve(Response(requestOptions: options, statusCode: data is int ? data : 200, data: data is int ? {'message': 'Le mot de passe actuel est incorrect.'} : data));
    }));
    addTearDown(() => ApiClient.dio.interceptors.clear());
    final boundary = GlobalKey();
    await tester.pumpWidget(
      MaterialApp(
        theme: OvanieTheme.light,
        // La capture englobe les feuilles et fenêtres ouvertes par l'écran.
        builder: (context, child) => RepaintBoundary(key: boundary, child: child),
        home: Material(child: screen),
      ),
    );
    for (var i = 0; i < 4; i++) {
      await tester.pump(const Duration(milliseconds: 250));
    }
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
    await tester.pump(const Duration(milliseconds: 100));
  }

  for (final tone in ['pending', 'approved', 'inactive', 'rejected']) {
    testWidgets('état de la boutique : $tone', (tester) async {
      await shoot(tester, 'etat_$tone', const ShopStatusScreen(), (_) => status(tone));
    });
  }

  for (final state in ['pending', 'checked', 'verified', 'rejected']) {
    testWidgets('documents : $state', (tester) async {
      await shoot(tester, 'documents_$state', const ShopDocumentsScreen(), (_) => documents(state));
    });
  }

  for (final alone in [false, true]) {
    testWidgets('sécurité : ${alone ? 'un appareil' : 'plusieurs appareils'}', (tester) async {
      await shoot(
        tester,
        'securite_${alone ? 'seul' : 'plusieurs'}',
        const AccountSecurityScreen(),
        (path) => path.endsWith('/push') ? base['push'] as Object : security(alone),
        height: 1000,
      );
    });
  }

  testWidgets('mot de passe : saisie', (tester) async {
    await shoot(tester, 'mdp_saisie', const ChangePasswordScreen(), (_) => <String, dynamic>{}, height: 800, act: () async {
      final fields = find.byType(TextField);
      await tester.enterText(fields.at(0), 'OvanieLocal2026');
      await tester.enterText(fields.at(1), 'motdepasseX');
      await tester.enterText(fields.at(2), 'motdepasseX');
      await tester.pump();
    });
  });

  testWidgets('mot de passe : erreurs', (tester) async {
    await shoot(tester, 'mdp_erreurs', const ChangePasswordScreen(), (_) => 422, height: 800, act: () async {
      final fields = find.byType(TextField);
      await tester.enterText(fields.at(0), 'mauvais');
      await tester.enterText(fields.at(1), 'Chantier2026');
      await tester.enterText(fields.at(2), 'Chantier2026');
      await tester.pump();
      await tester.tap(find.text('Enregistrer'));
      await tester.pump(const Duration(milliseconds: 400));
      await tester.enterText(fields.at(2), 'Chantier2027');
      await tester.pump();
      expect(find.text('Le mot de passe actuel est incorrect.'), findsOneWidget);
    });
  });

  Future<void> settle(WidgetTester tester) async {
    for (var i = 0; i < 4; i++) {
      await tester.pump(const Duration(milliseconds: 200));
    }
  }

  testWidgets('feuille : pièce d’identité', (tester) async {
    await shoot(tester, 'feuille_identite', const ShopDocumentsScreen(), (_) => documents('checked'), height: 800, act: () async {
      await tester.tap(find.text('Remplacer').at(1));
      await settle(tester);
      expect(find.text('Deux photos : recto puis verso'), findsOneWidget);
    });
  });

  testWidgets('feuille : selfie', (tester) async {
    await shoot(tester, 'feuille_selfie', const ShopDocumentsScreen(), (_) => documents('checked'), height: 800, act: () async {
      await tester.tap(find.text('Remplacer').first);
      await settle(tester);
      expect(find.text('Prendre une photo'), findsOneWidget);
    });
  });

  testWidgets('feuille : déconnecter un appareil', (tester) async {
    await shoot(
      tester,
      'feuille_deconnexion',
      const AccountSecurityScreen(),
      (path) => path.endsWith('/push') ? base['push'] as Object : security(false),
      height: 1100,
      act: () async {
        await tester.tap(find.text('Déconnecter').first);
        await settle(tester);
        expect(find.text('Déconnecter cet appareil ?'), findsOneWidget);
      },
    );
  });

  testWidgets('mot de passe oublié : saisie', (tester) async {
    await shoot(tester, 'oubli_saisie', const ForgotPasswordScreen(), (_) => <String, dynamic>{}, height: 760);
  });

  testWidgets('mot de passe oublié : lien envoyé', (tester) async {
    await shoot(
      tester,
      'oubli_envoye',
      const ForgotPasswordScreen(initialIdentifier: 'vendeur@exemple.ci', previewSentMessage: 'Lien envoyé.'),
      (_) => <String, dynamic>{},
      height: 760,
    );
  });

  testWidgets('feuille : ajouter un document d’entreprise', (tester) async {
    await shoot(tester, 'feuille_document', const ShopDocumentsScreen(), (_) => documents('pending'), height: 800, act: () async {
      await tester.dragUntilVisible(find.text('Ajouter').last, find.byType(Scrollable).first, const Offset(0, -200));
      await tester.pump(const Duration(milliseconds: 200));
      await tester.tap(find.text('Ajouter').last);
      await settle(tester);
      expect(find.text('Choisir un fichier PDF'), findsOneWidget);
    });
  });

  testWidgets('feuille : enregistrer un document', (tester) async {
    await shoot(tester, 'feuille_enregistrer', const ShopDocumentsScreen(), (_) => documents('checked'), height: 800, act: () async {
      await tester.tap(find.text('Télécharger').first);
      await settle(tester);
      expect(find.text('Enregistrer ce fichier ?'), findsOneWidget);
    });
  });
}
