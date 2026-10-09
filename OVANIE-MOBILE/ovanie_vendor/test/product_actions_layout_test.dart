import 'dart:io';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ovanie_vendor/app/theme.dart';
import 'package:ovanie_vendor/core/network/api_client.dart';
import 'package:ovanie_vendor/data/vendor_repository.dart';
import 'package:ovanie_vendor/features/products/product_archive_screen.dart';
import 'package:ovanie_vendor/features/products/product_boost_screen.dart';
import 'package:ovanie_vendor/features/products/product_images_screen.dart';

/// Gestion des images, boost, archivage et restauration : affichage sans
/// débordement du petit téléphone à la tablette, et comportements clés.
void main() {
  setUpAll(() async {
    final loader = FontLoader('PlusJakartaSans');
    for (final weight in <String>['Regular', 'Medium', 'SemiBold', 'Bold', 'ExtraBold']) {
      final bytes = File('assets/fonts/PlusJakartaSans-$weight.ttf').readAsBytesSync();
      loader.addFont(Future<ByteData>.value(ByteData.view(bytes.buffer)));
    }
    await loader.load();
  });

  final payload = <String, dynamic>{
    'product': {
      'id': 7,
      'name': 'Peinture blanche mate intérieure et extérieure 15 L',
      'status': 'actif',
      'is_active': true,
      'stock': 60,
      'price': 24500,
      'promo_price': 22000,
      'category': {
        'id': 45,
        'name': 'Peinture',
        'parent': {'id': 4, 'name': 'Matériaux de finition'},
      },
      'images': [
        {'id': 1, 'url': '', 'is_main': true},
        {'id': 2, 'url': '', 'is_main': false},
        {'id': 3, 'url': '', 'is_main': false},
        {'id': 4, 'url': '', 'is_main': false},
      ],
    },
    'packages': [
      {'key': 'semaine_classic', 'label': 'SEMAINE - Classic', 'price': 1400, 'days': 7},
      {'key': 'semaine_all', 'label': 'SEMAINE - Classic + SMS + email', 'price': 5600, 'days': 7},
      {'key': 'mois_classic', 'label': 'MOIS - Classic', 'price': 5000, 'days': 30},
      {'key': 'premium_all', 'label': 'PREMIUM - Classic + SMS + email', 'price': 60000, 'days': 90},
    ],
  };

  Future<void> open(WidgetTester tester, Widget screen, Size size, double scale) async {
    tester.view.physicalSize = size * 2;
    tester.view.devicePixelRatio = 2;
    addTearDown(tester.view.reset);
    VendorRepository.instance.invalidateProductCache();
    ApiClient.dio.interceptors.add(InterceptorsWrapper(onRequest: (options, handler) {
      handler.resolve(Response(requestOptions: options, statusCode: 200, data: payload));
    }));
    addTearDown(() => ApiClient.dio.interceptors.clear());
    await tester.pumpWidget(
      MaterialApp(
        theme: OvanieTheme.light,
        builder: (context, child) => MediaQuery(
          data: MediaQuery.of(context).copyWith(textScaler: TextScaler.linear(scale)),
          child: child!,
        ),
        home: screen,
      ),
    );
    await tester.pump(const Duration(milliseconds: 400));
    await tester.pump(const Duration(milliseconds: 400));
  }

  final screens = <String, (Widget Function(), String)>{
    'images': (() => const ProductImagesScreen(productId: 7), 'Optimisation automatique'),
    'boost': (() => const ProductBoostScreen(productId: 7, productName: 'Peinture blanche mate intérieure et extérieure 15 L'), 'Numéro Mobile Money'),
    'archivage': (() => const ProductArchiveScreen(productId: 7), 'Ce qui va se passer'),
    'restauration': (() => const ProductRestoreScreen(productId: 7), 'Ce qui va se passer'),
  };
  const sizes = <String, Size>{
    'petit téléphone': Size(320, 568),
    'téléphone courant': Size(360, 780),
    'tablette': Size(600, 960),
  };

  for (final screen in screens.entries) {
    for (final size in sizes.entries) {
      for (final scale in <double>[1, 1.15]) {
        testWidgets('${screen.key} — ${size.key} — texte ×$scale', (tester) async {
          await open(tester, screen.value.$1(), size.value, scale);
          expect(tester.takeException(), isNull);
          await tester.scrollUntilVisible(find.text(screen.value.$2), 250, scrollable: find.byType(Scrollable).first);
          await tester.pump();
          expect(tester.takeException(), isNull);
        });
      }
    }
  }

  testWidgets('boost : changer d’onglet change les packs et le montant à payer', (tester) async {
    await open(tester, screens['boost']!.$1(), const Size(412, 915), 1);
    expect(find.text('Payer 1 400 FCFA'), findsOneWidget);
    expect(find.text('MOIS - Classic'), findsNothing);

    await tester.tap(find.text('MOIS'));
    await tester.pumpAndSettle();
    expect(find.text('MOIS - Classic'), findsOneWidget);
    expect(find.text('Payer 5 000 FCFA'), findsOneWidget);

    await tester.tap(find.text('SEMAINE'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('SEMAINE - Classic + SMS + email'));
    await tester.pumpAndSettle();
    expect(find.text('Payer 5 600 FCFA'), findsOneWidget);
  });

  testWidgets('boost : un numéro incomplet est refusé avant le paiement', (tester) async {
    await open(tester, screens['boost']!.$1(), const Size(412, 915), 1);
    await tester.enterText(find.byType(TextFormField), '0700');
    await tester.tap(find.text('Payer 1 400 FCFA'));
    await tester.pumpAndSettle();
    expect(find.text('Le numéro Mobile Money doit contenir 10 chiffres.'), findsOneWidget);
  });

  testWidgets('images : la galerie numérote les photos et propose l’ajout', (tester) async {
    await open(tester, screens['images']!.$1(), const Size(412, 915), 1);
    expect(find.text('4/10'), findsOneWidget);
    expect(find.text('Principale'), findsOneWidget);
    await tester.ensureVisible(find.text('Ajouter des photos'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Ajouter des photos'));
    await tester.pumpAndSettle();
    expect(find.text('Importer depuis la galerie'), findsOneWidget);
    expect(find.text('Prendre une photo'), findsOneWidget);
  });

  testWidgets('archivage : le nom du produit et les deux boutons sont affichés', (tester) async {
    await open(tester, screens['archivage']!.$1(), const Size(412, 915), 1);
    expect(find.text('Confirmer l’archivage'), findsOneWidget);
    expect(find.text('Oui, archiver le produit'), findsOneWidget);
    expect(find.text('Annuler'), findsOneWidget);
    expect(find.text('Matériaux de finition • Peinture'), findsOneWidget);
  });
}
