@Tags(['render'])
library;

import 'dart:io';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ovanie_app/app/theme.dart';
import 'package:ovanie_app/features/home/presentation/offer_collection_screen.dart';

/// Rendu des pages d'offres avec les vraies données du serveur local, pour
/// les comparer aux maquettes. Ne fait rien sans LIVE_OUT.
///
/// flutter test test/offers_live_test.dart --dart-define=LIVE_OUT=dossier
///   --dart-define=OVANIE_ENV=local --dart-define=OVANIE_API_BASE=http://127.0.0.1:8000/api
///   --dart-define=ICON_FONT=chemin/materialicons-regular.otf
void main() {
  const out = String.fromEnvironment('LIVE_OUT');
  const iconFont = String.fromEnvironment('ICON_FONT');

  setUpAll(() async {
    if (out.isEmpty) return;
    HttpOverrides.global = null;
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

  Future<void> shoot(WidgetTester tester, String name, Widget screen, {Future<void> Function()? act}) async {
    tester.view.physicalSize = const Size(360, 780) * 2;
    tester.view.devicePixelRatio = 2;
    addTearDown(tester.view.reset);
    final boundary = GlobalKey();
    await tester.pumpWidget(
      MaterialApp(
        theme: OvanieTheme.light,
        builder: (context, child) => RepaintBoundary(key: boundary, child: child),
        home: screen,
      ),
    );
    for (var i = 0; i < 30; i++) {
      await tester.runAsync(() => Future<void>.delayed(const Duration(seconds: 2)));
      await tester.pump(const Duration(milliseconds: 200));
      if (find.byType(CircularProgressIndicator).evaluate().isEmpty) break;
    }
    if (act != null) await act();
    await tester.runAsync(() async {
      for (final element in find.byType(Image).evaluate()) {
        try {
          await precacheImage((element.widget as Image).image, element).timeout(const Duration(seconds: 8));
        } catch (_) {}
      }
    });
    await tester.pump(const Duration(milliseconds: 200));
    final bytes = await tester.runAsync(() async {
      final render = boundary.currentContext!.findRenderObject()! as RenderRepaintBoundary;
      final image = await render.toImage(pixelRatio: 2);
      final data = await image.toByteData(format: ui.ImageByteFormat.png);
      return data!.buffer.asUint8List();
    });
    File('$out/$name.png').writeAsBytesSync(bytes!);
    await tester.pumpWidget(const SizedBox.shrink());
    await tester.pump(const Duration(seconds: 1));
  }

  for (final (name, kind) in const [
    ('offre_flash', OvanieOfferKind.flash),
    ('offre_meilleures', OvanieOfferKind.bestSellers),
    ('offre_nouveautes', OvanieOfferKind.latest),
    ('offre_blackfriday', OvanieOfferKind.blackFriday),
    ('offre_encours', OvanieOfferKind.promotions),
  ]) {
    testWidgets(name, (tester) async {
      await shoot(tester, name, OvanieOfferCollectionScreen(kind: kind));
    }, skip: out.isEmpty);
  }

  testWidgets('filtrer par catégorie', (tester) async {
    await shoot(
      tester,
      'offre_categories',
      const OvanieOfferCollectionScreen(kind: OvanieOfferKind.promotions),
      act: () async {
        await tester.tap(find.text('Filtres'));
        await tester.pump(const Duration(milliseconds: 600));
        expect(find.text('Filtrer par catégorie'), findsOneWidget);
      },
    );
  }, skip: out.isEmpty);

  testWidgets('trier les produits', (tester) async {
    await shoot(
      tester,
      'offre_tri',
      const OvanieOfferCollectionScreen(kind: OvanieOfferKind.promotions),
      act: () async {
        await tester.tap(find.text('Trier'));
        await tester.pump(const Duration(milliseconds: 600));
        expect(find.text('Trier les produits'), findsOneWidget);
      },
    );
  }, skip: out.isEmpty);
}
