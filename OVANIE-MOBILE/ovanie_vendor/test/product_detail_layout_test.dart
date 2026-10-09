import 'dart:io';
import 'dart:ui' as ui;

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ovanie_vendor/app/theme.dart';
import 'package:ovanie_vendor/core/network/api_client.dart';
import 'package:ovanie_vendor/data/vendor_repository.dart';
import 'package:ovanie_vendor/features/products/product_detail_screen.dart';

/// La fiche produit doit s'afficher sans débordement du petit téléphone à la
/// tablette, sur ses quatre onglets, et ouvrir sa feuille d'actions.
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

  final product = <String, dynamic>{
    'id': 7,
    'name': 'Ciment CPA 42.5R',
    'reference': 'CIM-425-50',
    'status': 'actif',
    'is_active': true,
    'stock': 100,
    'unit': 'sac',
    'unit_label': 'sac',
    'min_order_quantity': 1,
    'weight_kg': 50,
    'length_cm': 60,
    'width_cm': 40,
    'height_cm': 12,
    'volume_m3': 0.0288,
    'fragile': false,
    'requires_unloading': true,
    'delivery_mode': 'ovanie',
    'unloading_instructions': 'Chariot ou aide au déchargement nécessaire',
    'seller_price': 6500,
    'seller_promo_price': 5900,
    'seller_effective_price': 5900,
    'price': 6500,
    'rating': 4.6,
    'reviews_count': 18,
    'product_state_label': 'Neuf',
    'packaging': 'Sac de 50 kg',
    'availability_status': 'in_stock',
    'availability_label': 'En stock',
    'updated_at': '2026-09-12T10:00:00Z',
    'description':
        'Ciment Portland CPA 42.5R, idéal pour la réalisation de bétons armés, dalles et fondations. Offre une excellente résistance mécanique, une prise rapide et une grande durabilité.',
    'technical_details': 'Résiste à l’humidité, couleur grise, sac de 50 kg',
    'brand': 'CIMAF',
    'origin_country': 'Côte d’Ivoire',
    'usage_area': 'Construction',
    'material_grade': '42.5R',
    'color': 'Gris',
    'category': {
      'id': 9,
      'name': 'Ciment',
      'parent': {'id': 1, 'name': 'Matériaux gros œuvre'},
    },
    'images': const <String>[],
  };

  Future<void> open(WidgetTester tester, Size size, double scale, {GlobalKey? key}) async {
    tester.view.physicalSize = size * 2;
    tester.view.devicePixelRatio = 2;
    addTearDown(tester.view.reset);
    VendorRepository.instance.invalidateProductCache();
    ApiClient.dio.interceptors.add(InterceptorsWrapper(onRequest: (options, handler) {
      handler.resolve(Response(
        requestOptions: options,
        statusCode: 200,
        data: <String, dynamic>{'product': product, 'unread_count': 3},
      ));
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
        home: RepaintBoundary(key: key, child: const ProductDetailScreen(productId: 7)),
      ),
    );
    await tester.pump(const Duration(milliseconds: 400));
    await tester.pump(const Duration(milliseconds: 400));
  }

  Future<void> openTab(WidgetTester tester, String label) async {
    await tester.scrollUntilVisible(find.text(label), 200, scrollable: find.byType(Scrollable).first);
    await tester.tap(find.text(label));
    await tester.pumpAndSettle();
  }

  const sizes = <String, Size>{
    'petit téléphone': Size(320, 568),
    'téléphone courant': Size(360, 780),
    'grand téléphone': Size(412, 915),
    'tablette': Size(600, 960),
  };

  for (final size in sizes.entries) {
    for (final scale in <double>[1, 1.15]) {
      testWidgets('fiche produit — ${size.key} — texte ×$scale', (tester) async {
        await open(tester, size.value, scale);
        expect(tester.takeException(), isNull);
        expect(find.text('Mes produits  >  Matériaux gros œuvre'), findsOneWidget);
        expect(find.text('Désactiver'), findsOneWidget);
        expect(find.text('Modifier'), findsOneWidget);

        for (final tab in <String>['Caractéristiques', 'Logistique', 'Avis']) {
          await openTab(tester, tab);
          expect(tester.takeException(), isNull);
        }
        expect(find.text('18 avis clients'), findsOneWidget);
      });
    }
  }

  testWidgets('les trois points ouvrent les actions du produit', (tester) async {
    await open(tester, const Size(360, 780), 1);
    await tester.tap(find.byTooltip('Plus d’actions'));
    await tester.pumpAndSettle();

    expect(find.text('Modifier le produit'), findsOneWidget);
    expect(find.text('Gérer les images'), findsOneWidget);
    expect(find.text('Booster ce produit'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  // Rendu des onglets pour comparaison avec les maquettes (lancé à la demande).
  testWidgets('rendu', (tester) async {
    const out = String.fromEnvironment('SHOT_DIR');
    if (out.isEmpty) return;
    final key = GlobalKey();
    await open(tester, const Size(412, 900), 1, key: key);
    await tester.runAsync(() async {
      await precacheImage(
        const AssetImage('assets/images/mockups/logistics_truck.jpg'),
        tester.element(find.byType(ProductDetailScreen)),
      );
    });

    Future<void> shot(String name) async {
      await tester.pump(const Duration(milliseconds: 300));
      final boundary = key.currentContext!.findRenderObject()! as RenderRepaintBoundary;
      await tester.runAsync(() async {
        final image = await boundary.toImage(pixelRatio: 1.2);
        final bytes = await image.toByteData(format: ui.ImageByteFormat.png);
        await File('$out/det_$name.png').writeAsBytes(bytes!.buffer.asUint8List());
      });
    }

    await shot('top');
    await tester.drag(find.byType(Scrollable).first, const Offset(0, -430));
    await shot('description');
    await tester.tap(find.text('Logistique'));
    await tester.pumpAndSettle();
    await tester.drag(find.byType(Scrollable).first, const Offset(0, -300));
    await shot('logistique');
    await tester.tap(find.text('Avis'));
    await tester.pumpAndSettle();
    await shot('avis');
  });
}
