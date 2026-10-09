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
import 'package:ovanie_vendor/core/reference_data/reference_data_store.dart';
import 'package:ovanie_vendor/data/vendor_repository.dart';
import 'package:ovanie_vendor/features/products/product_form_screen.dart';

/// Le formulaire produit en 5 étapes doit s'afficher sans débordement du petit
/// téléphone à la tablette, et se parcourir jusqu'à l'étape Médias.
void main() {
  late Map<String, dynamic> references;

  setUpAll(() async {
    final loader = FontLoader('PlusJakartaSans');
    for (final weight in <String>['Regular', 'Medium', 'SemiBold', 'Bold', 'ExtraBold']) {
      final bytes = File('assets/fonts/PlusJakartaSans-$weight.ttf').readAsBytesSync();
      loader.addFont(Future<ByteData>.value(ByteData.view(bytes.buffer)));
    }
    await loader.load();
    const iconFont = String.fromEnvironment('ICON_FONT');
    if (iconFont.isNotEmpty) {
      final icons = FontLoader('MaterialIcons')
        ..addFont(Future<ByteData>.value(ByteData.view(File(iconFont).readAsBytesSync().buffer)));
      await icons.load();
    }
    references = Map<String, dynamic>.from(
      jsonDecode(File('test/fixtures/reference_data.json').readAsStringSync()) as Map,
    );
  });

  final product = <String, dynamic>{
    'id': 991,
    'name': 'Ciment test',
    'brand': 'CIMAF',
    'short_description': 'Description courte',
    'description': 'Description complète',
    'category': {'id': 9},
    'category_id': 9,
    'type': 'materiau',
    'unit': 'sac',
    'sale_type': 'standard',
    'price': 6500,
    'promo_price': 6000,
    'stock': 100,
    'min_order_quantity': 1,
    'unit_label': 'sac',
    'technical_details': 'Détails',
    'weight_kg': 50,
    'length_cm': 40,
    'width_cm': 30,
    'height_cm': 20,
  };

  Future<void> open(WidgetTester tester, Size size, double scale, {bool edit = true, GlobalKey? key}) async {
    tester.view.physicalSize = size * 2;
    tester.view.devicePixelRatio = 2;
    addTearDown(tester.view.reset);
    VendorRepository.instance.invalidateProductCache();
    ApiClient.dio.interceptors.add(InterceptorsWrapper(onRequest: (options, handler) {
      handler.resolve(Response(
        requestOptions: options,
        statusCode: 200,
        data: options.path.endsWith('/991') ? <String, dynamic>{'product': product} : references,
      ));
    }));
    addTearDown(() => ApiClient.dio.interceptors.clear());
    await tester.runAsync(() => OvanieReferenceDataStore.instance.warmUp(force: true));

    await tester.pumpWidget(
      MaterialApp(
        debugShowCheckedModeBanner: false,
        theme: OvanieTheme.light,
        builder: (context, child) => MediaQuery(
          data: MediaQuery.of(context).copyWith(textScaler: TextScaler.linear(scale)),
          child: child!,
        ),
        home: RepaintBoundary(key: key, child: ProductFormScreen(productId: edit ? 991 : null)),
      ),
    );
    for (var i = 0; i < 4; i++) {
      await tester.runAsync(() => Future<void>.delayed(const Duration(milliseconds: 20)));
      await tester.pump(const Duration(milliseconds: 200));
    }
  }

  Future<void> next(WidgetTester tester) async {
    await tester.tap(find.text('Suivant'));
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
  }

  Finder field(String value) => find.byWidgetPredicate((w) => w is TextFormField && w.controller?.text == value);

  const sizes = <String, Size>{
    'petit téléphone': Size(320, 568),
    'téléphone courant': Size(360, 780),
    'grand téléphone': Size(412, 915),
    'tablette': Size(600, 960),
  };

  for (final size in sizes.entries) {
    for (final scale in <double>[1, 1.15]) {
      testWidgets('formulaire produit — ${size.key} — texte ×$scale', (tester) async {
        await open(tester, size.value, scale);
        expect(tester.takeException(), isNull);
        expect(find.text('Étape 1 sur 5 · Informations'), findsOneWidget);

        await next(tester);
        expect(find.text('Étape 2 sur 5 · Prix & unité'), findsOneWidget);
        expect(field('6500'), findsOneWidget);
        await next(tester);
        expect(find.text('Étape 3 sur 5 · Détail technique'), findsOneWidget);
        await next(tester);
        expect(find.text('Étape 4 sur 5 · Logistique'), findsOneWidget);
        await next(tester);
        expect(find.text('Étape 5 sur 5 · Médias'), findsOneWidget);
        expect(find.text('Prendre une photo'), findsOneWidget);
        expect(find.text('Enregistrer en brouillon'), findsOneWidget);
      });
    }
  }

  testWidgets('sur un grand téléphone, prix normal et prix promo sont côte à côte', (tester) async {
    await open(tester, const Size(412, 915), 1);
    await next(tester);
    expect(tester.getRect(field('6000')).left, greaterThan(tester.getRect(field('6500')).right));
  });

  testWidgets('sur un petit téléphone, les dimensions restent saisissables, l’une sous l’autre', (tester) async {
    await open(tester, const Size(320, 568), 1);
    await next(tester);
    await next(tester);
    await next(tester);
    expect(tester.getRect(field('40')).width, greaterThan(200));
    expect(tester.getRect(field('30')).top, greaterThan(tester.getRect(field('40')).bottom));
  });

  testWidgets('un nouveau produit sans nom ne passe pas à l’étape 2', (tester) async {
    await open(tester, const Size(360, 780), 1, edit: false);
    await tester.tap(find.text('Suivant'));
    await tester.pumpAndSettle();
    expect(find.text('Étape 1 sur 5 · Informations'), findsOneWidget);
    expect(find.text('Obligatoire'), findsWidgets);
  });

  // Rendu des 5 étapes pour comparaison avec les maquettes (lancé à la demande).
  testWidgets('rendu', (tester) async {
    const out = String.fromEnvironment('SHOT_DIR');
    if (out.isEmpty) return;
    final key = GlobalKey();
    await open(tester, const Size(412, 1000), 1, key: key);
    await tester.runAsync(() async {
      final context = tester.element(find.byType(ProductFormScreen));
      for (var step = 1; step <= 5; step++) {
        await precacheImage(AssetImage('assets/images/mockups/form_step$step.jpg'), context);
      }
      await precacheImage(const AssetImage('assets/images/mockups/logo_navy.png'), context);
    });
    for (var step = 1; step <= 5; step++) {
      await tester.pump(const Duration(milliseconds: 300));
      final boundary = key.currentContext!.findRenderObject()! as RenderRepaintBoundary;
      await tester.runAsync(() async {
        final image = await boundary.toImage(pixelRatio: 1);
        final bytes = await image.toByteData(format: ui.ImageByteFormat.png);
        await File('$out/form_$step.png').writeAsBytes(bytes!.buffer.asUint8List());
      });
      if (step < 5) await next(tester);
    }
  });
}
