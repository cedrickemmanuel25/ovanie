import 'dart:io';
import 'dart:ui' as ui;

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ovanie_vendor/app/theme.dart';
import 'package:ovanie_vendor/core/network/api_client.dart';
import 'package:ovanie_vendor/features/products/products_screen.dart';

/// La liste « Mes produits » doit s'afficher sans débordement du petit
/// téléphone à la tablette, et ses feuilles (actions, tri, filtre) doivent s'ouvrir.
void main() {
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
  });

  Map<String, dynamic> product(int id, String name, num price, int stock, String unit, String status,
          {String parent = 'Matériaux gros œuvre', String category = 'Ciment', bool active = true}) =>
      <String, dynamic>{
        'id': id,
        'name': name,
        'price': price,
        'stock': stock,
        'unit': unit,
        'unit_label': unit,
        'status': status,
        'is_active': active,
        'category': {
          'id': 9,
          'name': category,
          'parent': {'id': 1, 'name': parent},
        },
      };

  final listing = <String, dynamic>{
    'data': [
      product(1, 'Ciment CPA 42.5R', 5900, 100, 'sac', 'actif'),
      product(2, 'Fer à béton HA 12', 4800, 350, 'barre', 'actif', category: 'Fer à béton'),
      product(3, 'Briques pleines', 250, 0, 'pièce', 'actif', category: 'Briques'),
      product(4, 'Peinture acrylique blanche satinée pour murs et plafonds', 1218500, 40, 'seau', 'draft',
          parent: '', category: 'Matériaux de finition', active: false),
    ],
    'meta': {'current_page': 1, 'last_page': 1},
    'stats': {'total': 48, 'active': 42, 'draft': 3, 'out_of_stock': 3, 'archived': 0},
    'unread_count': 3,
  };

  Future<void> open(WidgetTester tester, Size size, double scale, {GlobalKey? key}) async {
    tester.view.physicalSize = size * 2;
    tester.view.devicePixelRatio = 2;
    addTearDown(tester.view.reset);
    ApiClient.dio.interceptors.add(InterceptorsWrapper(onRequest: (options, handler) {
      handler.resolve(Response(requestOptions: options, statusCode: 200, data: listing));
    }));
    addTearDown(() => ApiClient.dio.interceptors.clear());

    await tester.pumpWidget(
      MaterialApp(
        debugShowCheckedModeBanner: false,
        theme: OvanieTheme.light,
        builder: (context, child) => MediaQuery(
          data: MediaQuery.of(context).copyWith(textScaler: TextScaler.linear(scale)),
          child: child!,
        ),
        home: RepaintBoundary(key: key, child: const ProductsScreen()),
      ),
    );
    await tester.pump(const Duration(milliseconds: 400));
    await tester.pump(const Duration(milliseconds: 400));
  }

  const sizes = <String, Size>{
    'petit téléphone': Size(320, 568),
    'téléphone courant': Size(360, 780),
    'grand téléphone': Size(412, 915),
    'tablette': Size(600, 960),
  };

  for (final size in sizes.entries) {
    for (final scale in <double>[1, 1.15]) {
      testWidgets('mes produits — ${size.key} — texte ×$scale', (tester) async {
        await open(tester, size.value, scale);
        expect(tester.takeException(), isNull);
        expect(find.text('Ciment CPA 42.5R'), findsOneWidget);
        expect(find.text('Matériaux gros œuvre • Ciment'), findsOneWidget);

        await tester.tap(find.byTooltip('Plus d’actions').first);
        await tester.pumpAndSettle();
        expect(find.text('Gérer les images'), findsOneWidget);
        expect(find.text('Archiver le produit'), findsOneWidget);
        expect(tester.takeException(), isNull);
      });
    }
  }

  testWidgets('un produit sans stock est marqué en rupture', (tester) async {
    await open(tester, const Size(412, 915), 1);
    expect(find.text('Stock : 0 Pièce', findRichText: true), findsOneWidget);
    expect(find.text('Brouillon'), findsOneWidget);
  });

  testWidgets('le tri et le filtre ouvrent leur feuille de choix', (tester) async {
    await open(tester, const Size(360, 780), 1);

    await tester.tap(find.byTooltip('Trier'));
    await tester.pumpAndSettle();
    expect(find.text('Trier les produits'), findsOneWidget);
    await tester.tap(find.text('Prix croissant'));
    await tester.pumpAndSettle();
    expect(find.text('Trier les produits'), findsNothing);

    await tester.tap(find.byTooltip('Filtrer'));
    await tester.pumpAndSettle();
    expect(find.text('Filtrer les produits'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  // Rendu complet pour comparaison avec la maquette (lancé à la demande).
  testWidgets('rendu', (tester) async {
    const out = String.fromEnvironment('SHOT_DIR');
    if (out.isEmpty) return;
    final key = GlobalKey();
    await open(tester, const Size(412, 915), 1, key: key);
    await tester.runAsync(() async {
      await precacheImage(
        const AssetImage('assets/images/mockups/logo_navy.png'),
        tester.element(find.byType(ProductsScreen)),
      );
    });
    await tester.pump(const Duration(milliseconds: 300));
    final boundary = key.currentContext!.findRenderObject()! as RenderRepaintBoundary;
    await tester.runAsync(() async {
      final image = await boundary.toImage(pixelRatio: 1.5);
      final bytes = await image.toByteData(format: ui.ImageByteFormat.png);
      await File('$out/prod.png').writeAsBytes(bytes!.buffer.asUint8List());
    });
  });
}
