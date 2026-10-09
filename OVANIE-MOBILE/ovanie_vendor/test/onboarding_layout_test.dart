import 'dart:io';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
// The picker platform is replaced to exercise photo selection without a device.
// ignore: depend_on_referenced_packages
import 'package:image_picker_platform_interface/image_picker_platform_interface.dart';
import 'package:ovanie_vendor/app/theme.dart';
import 'package:ovanie_vendor/core/network/api_client.dart';
import 'package:ovanie_vendor/features/auth/vendor_session.dart';
import 'package:ovanie_vendor/features/onboarding/shop_onboarding_screen.dart';

class _PhotoPicker extends ImagePickerPlatform {
  @override
  Future<XFile?> getImageFromSource({required ImageSource source, ImagePickerOptions options = const ImagePickerOptions()}) async {
    return XFile(File('assets/images/ovanie_logo.png').absolute.path);
  }
}

Finder _stepLabel(int step) => find.bySemanticsLabel(RegExp('étape $step sur 5'));

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

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

  setUp(() {
    ApiClient.dio.interceptors.add(InterceptorsWrapper(onRequest: (options, handler) {
      handler.resolve(Response(requestOptions: options, statusCode: 200, data: <String, dynamic>{}));
    }));
    VendorSession.instance.token = 'test';
    VendorSession.instance.user = {'id': 1, 'name': 'Jean', 'email': 'jean@example.test'};
    VendorSession.instance.shop = null;
  });
  tearDown(() {
    ApiClient.dio.interceptors.clear();
    VendorSession.instance.token = null;
    VendorSession.instance.user = null;
    VendorSession.instance.onboardingStep = 0;
  });

  Future<void> open(WidgetTester tester, {required int step, Size size = const Size(412, 900), double textScale = 1}) async {
    tester.view.physicalSize = size;
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.reset);
    VendorSession.instance.onboardingStep = step;
    await tester.pumpWidget(
      MaterialApp(
        theme: OvanieTheme.light,
        builder: (context, child) => MediaQuery(
          data: MediaQuery.of(context).copyWith(textScaler: TextScaler.linear(textScale)),
          child: child!,
        ),
        home: const ShopOnboardingScreen(),
      ),
    );
    await tester.pumpAndSettle();
  }

  for (final width in <double>[320, 360, 412, 600]) {
    for (var step = 0; step < 5; step++) {
      for (final scale in <double>[1, 1.15]) {
        testWidgets('étape ${step + 1} — largeur $width — texte ×$scale : aucun débordement', (tester) async {
          await open(tester, step: step, size: Size(width, 900), textScale: scale);
          expect(tester.takeException(), isNull);
          expect(_stepLabel(step + 1), findsOneWidget);

          final action = find.text(step == 4 ? 'Finaliser' : 'Suivant');
          await tester.scrollUntilVisible(action, 400, scrollable: find.byType(Scrollable).first);
          await tester.pumpAndSettle();
          expect(tester.takeException(), isNull);
        });
      }
    }
  }

  testWidgets('étape 2 : les champs obligatoires vides bloquent le passage à l’étape 3', (tester) async {
    await open(tester, step: 1);
    await tester.scrollUntilVisible(find.text('Suivant'), 400, scrollable: find.byType(Scrollable).first);
    await tester.tap(find.text('Suivant'));
    await tester.pumpAndSettle();

    expect(find.text('Nom de la boutique : champ obligatoire.'), findsOneWidget);
    expect(_stepLabel(2), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('étape 2 : la photo du responsable se choisit dans la galerie', (tester) async {
    final previousPicker = ImagePickerPlatform.instance;
    ImagePickerPlatform.instance = _PhotoPicker();
    addTearDown(() => ImagePickerPlatform.instance = previousPicker);

    await open(tester, step: 1);
    await tester.tap(find.text('Photo KYC privée — JPG ou PNG, 5 Mo maximum — obligatoire'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Choisir dans la galerie'));
    await tester.pumpAndSettle();

    expect(find.text('Photo ajoutée'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('« Précédent » revient à l’étape d’avant', (tester) async {
    await open(tester, step: 3);
    await tester.scrollUntilVisible(find.text('Précédent'), 400, scrollable: find.byType(Scrollable).first);
    await tester.tap(find.text('Précédent'));
    await tester.pumpAndSettle();

    expect(_stepLabel(3), findsOneWidget);
    expect(find.text('Vérification d’identité'), findsOneWidget);
  });
}
