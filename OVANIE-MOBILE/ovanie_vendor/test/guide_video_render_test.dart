// Captures reproductibles des vrais écrans pour la vidéo guide, sans serveur.
// ignore_for_file: invalid_use_of_visible_for_testing_member
import 'dart:io';
import 'dart:convert';
import 'dart:ui' as ui;
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ovanie_vendor/app/theme.dart';
import 'package:ovanie_vendor/core/network/api_client.dart';
import 'package:ovanie_vendor/features/auth/vendor_journey_screen.dart';
import 'package:ovanie_vendor/features/auth/vendor_session.dart';
import 'package:ovanie_vendor/features/onboarding/shop_onboarding_screen.dart';

void main() {
  const out = String.fromEnvironment('RENDER_OUT');
  setUpAll(() async {
    for (final family in ['PlusJakartaSans']) {
      final loader = FontLoader(family);
      for (final weight in ['Regular', 'Medium', 'SemiBold', 'Bold', 'ExtraBold']) {
        final b = File('assets/fonts/$family-$weight.ttf').readAsBytesSync();
        loader.addFont(Future.value(ByteData.view(b.buffer)));
      }
      await loader.load();
    }
    final b = File('C:/flutter/bin/cache/artifacts/material_fonts/materialicons-regular.otf').readAsBytesSync();
    await (FontLoader('MaterialIcons')..addFont(Future.value(ByteData.view(b.buffer)))).load();
    Directory(out).createSync(recursive: true);
  });
  for (var step = -1; step < 5; step++) {
    testWidgets('capture guide $step', (tester) async {
      tester.view.physicalSize = const Size(412, 900);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.reset);
      VendorSession.instance.token = null;
      VendorSession.instance.user = null;
      ApiClient.dio.interceptors.add(InterceptorsWrapper(onRequest: (r, h) {
        h.resolve(Response(requestOptions: r, statusCode: 200,
          data: jsonDecode(File('test/fixtures/reference_data.json').readAsStringSync())));
      }));
      addTearDown(() => ApiClient.dio.interceptors.clear());
      final key = GlobalKey();
      await tester.pumpWidget(MaterialApp(debugShowCheckedModeBanner: false,
        theme: OvanieTheme.light,
        builder: (context, child) => RepaintBoundary(key: key, child: child),
        home: step < 0 ? const VendorJourneyScreen() : ShopOnboardingScreen(previewStep: step)));
      await tester.pumpAndSettle();
      await tester.runAsync(() async {
        for (final e in find.byType(Image).evaluate()) {
          await precacheImage((e.widget as Image).image, e);
        }
      });
      await tester.pumpAndSettle();
      final scrollable = tester.state<ScrollableState>(find.byType(Scrollable).first);
      final maximum = scrollable.position.maxScrollExtent;
      // Captures à intervalles réguliers : le montage les relie par un défilement.
      final count = (maximum / 220).ceil().clamp(1, 20);
      File('$out/s${step + 1}_scroll.txt').writeAsStringSync('$maximum\n$count');
      for (var i = 0; i <= count; i++) {
        scrollable.position.jumpTo(maximum * i / count);
        await tester.pumpAndSettle();
        expect(tester.takeException(), isNull);
        await tester.runAsync(() async {
          final image = await (key.currentContext!.findRenderObject() as RenderRepaintBoundary).toImage(pixelRatio: 2);
          final data = await image.toByteData(format: ui.ImageByteFormat.png);
          File('$out/s${step + 1}_$i.png').writeAsBytesSync(data!.buffer.asUint8List());
          image.dispose();
        });
      }
      await tester.pumpWidget(const SizedBox.shrink());
      await tester.pump(const Duration(seconds: 2));
    });
  }
}
