import 'dart:io';
import 'dart:ui' as ui;
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
// The picker platform is replaced to exercise photo selection without a device.
// ignore: depend_on_referenced_packages
import 'package:image_picker_platform_interface/image_picker_platform_interface.dart';
import 'package:ovanie_vendor/core/network/api_client.dart';
import 'package:ovanie_vendor/core/ui/vendor_wizard_ui.dart';
import 'package:ovanie_vendor/features/auth/vendor_session.dart';
import 'package:ovanie_vendor/features/onboarding/shop_onboarding_screen.dart';

class _PhotoPicker extends ImagePickerPlatform {
  @override
  Future<XFile?> getImageFromSource({required ImageSource source, ImagePickerOptions options = const ImagePickerOptions()}) async {
    return XFile(File('assets/images/ovanie_logo.png').absolute.path);
  }
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  setUpAll(() async {
    const fontDir = String.fromEnvironment('PREVIEW_FONT_DIR');
    if (fontDir.isNotEmpty) {
      final font = FontLoader('Roboto');
      font.addFont(File('$fontDir/roboto-regular.ttf').readAsBytes().then((bytes) => ByteData.sublistView(bytes)));
      await font.load();
      final icons = FontLoader('MaterialIcons');
      icons.addFont(File('$fontDir/materialicons-regular.otf').readAsBytes().then((bytes) => ByteData.sublistView(bytes)));
      await icons.load();
    }
  });
  setUp(() {
    ApiClient.dio.interceptors.add(InterceptorsWrapper(onRequest: (options, handler) {
      handler.resolve(Response(requestOptions: options, statusCode: 200, data:
        options.path.contains('categories')
          ? [{'id': 1, 'slug': 'materiaux', 'name': 'Matériaux'}]
          : {'communes': [{'id': 1, 'name': 'Cocody'}]}));
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

  testWidgets('manual address and photo allow step 2 to advance without GPS', (tester) async {
    tester.view.physicalSize = const Size(412, 900);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final previousPicker = ImagePickerPlatform.instance;
    ImagePickerPlatform.instance = _PhotoPicker();
    addTearDown(() => ImagePickerPlatform.instance = previousPicker);
    VendorSession.instance.onboardingStep = 1;
    await tester.pumpWidget(const MaterialApp(home: ShopOnboardingScreen()));
    await tester.pumpAndSettle();
    final dynamic state = tester.state(find.byType(ShopOnboardingScreen));
    state.ctrl('shopName').text = 'Boutique test';
    state.ctrl('description').text = 'Matériaux de construction';
    await tester.ensureVisible(find.text('Choisir une photo'));
    await tester.tap(find.text('Choisir une photo'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Choisir une photo').last);
    await tester.pumpAndSettle();
    final commune = find.byWidgetPredicate((widget) => widget is WizardSelectField && widget.label == 'Commune');
    await tester.ensureVisible(commune);
    await tester.tap(commune);
    await tester.pumpAndSettle();
    await tester.tap(find.text('Cocody').last);
    await tester.pumpAndSettle();
    state.ctrl('district').text = 'Riviera';
    state.ctrl('landmark').text = 'En face du marché';
    await tester.ensureVisible(find.byType(WizardPrimaryButton));
    await tester.tap(find.byType(WizardPrimaryButton));
    await tester.pumpAndSettle();
    expect(find.text('Étape 3 sur 5'), findsOneWidget);
    expect(state.ctrl('latitude').text, isEmpty);
    expect(tester.takeException(), isNull);
  });

  for (final width in [360.0, 412.0, 600.0]) {
    for (var step = 0; step < 5; step++) {
      testWidgets('step ${step + 1} renders at $width without overflow', (tester) async {
        tester.view.physicalSize = Size(width, 900);
        tester.view.devicePixelRatio = 1;
        addTearDown(tester.view.resetPhysicalSize);
        addTearDown(tester.view.resetDevicePixelRatio);
        VendorSession.instance.onboardingStep = step;
        final boundaryKey = GlobalKey();
        await tester.pumpWidget(MaterialApp(home: RepaintBoundary(key: boundaryKey, child: const ShopOnboardingScreen())));
        await tester.pumpAndSettle();
        expect(tester.takeException(), isNull);
        if (width == 412) {
          final boundary = boundaryKey.currentContext!.findRenderObject()! as RenderRepaintBoundary;
          await tester.runAsync(() async {
            final image = await boundary.toImage();
            final bytes = await image.toByteData(format: ui.ImageByteFormat.png);
            await Directory('build/onboarding-preview').create(recursive: true);
            await File('build/onboarding-preview/step-${step + 1}.png').writeAsBytes(bytes!.buffer.asUint8List());
            image.dispose();
          });
        }
        await tester.scrollUntilVisible(find.byType(WizardPrimaryButton), 400, scrollable: find.byType(Scrollable).first);
        await tester.pumpAndSettle();
        expect(tester.takeException(), isNull);
        if (step == 1) {
          await tester.tap(find.byType(WizardPrimaryButton));
          await tester.pumpAndSettle();
          expect(find.text('Complétez les champs obligatoires indiqués en rouge.'), findsOneWidget);
          expect(find.text('Étape 2 sur 5'), findsOneWidget);
          expect(tester.takeException(), isNull);
        }
      });
    }
  }
}
