import 'dart:io';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ovanie_vendor/core/network/api_client.dart';
import 'package:ovanie_vendor/features/products/product_form_screen.dart';

void main() {
  setUpAll(() async {
    final fonts = Directory(
      '${File(Platform.resolvedExecutable).parent.path}/../../material_fonts',
    );
    for (final entry in {
      'ReviewFont': 'roboto-regular.ttf',
      'MaterialIcons': 'materialicons-regular.otf',
    }.entries) {
      final loader = FontLoader(entry.key)
        ..addFont(
          Future.value(
            ByteData.sublistView(
              await File('${fonts.path}/${entry.value}').readAsBytes(),
            ),
          ),
        );
      await loader.load();
    }
  });

  testWidgets('product price and dimensions remain editable on narrow phones', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(390, 2400);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final interceptor = InterceptorsWrapper(
      onRequest: (o, h) => h.resolve(
        Response(
          requestOptions: o,
          statusCode: 200,
          data: o.path.endsWith('/991')
              ? {
                  'product': {
                    'id': 991,
                    'name': 'Ciment test',
                    'brand': 'CIMAF',
                    'short_description': 'Description courte',
                    'description': 'Description complète',
                    'category': {'id': 1},
                    'type': 'materiau',
                    'unit': 'sac',
                    'sale_type': 'standard',
                    'usage_area': 'construction',
                    'warranty': 'aucune',
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
                  },
                }
              : {
                  'product_categories': [
                    {'id': 1, 'name': 'Matériaux', 'children': []},
                  ],
                  'product_units': ['sac'],
                },
        ),
      ),
    );
    ApiClient.dio.interceptors.insert(0, interceptor);
    addTearDown(() => ApiClient.dio.interceptors.remove(interceptor));
    await tester.pumpWidget(
      MaterialApp(
        theme: ThemeData(fontFamily: 'ReviewFont'),
        home: const ProductFormScreen(productId: 991),
      ),
    );
    await tester.pumpAndSettle();
    Future<void> next() async {
      await tester.ensureVisible(find.text('Suivant'));
      await tester.tap(find.text('Suivant'));
      await tester.pumpAndSettle();
      expect(tester.takeException(), isNull);
    }

    Finder field(String value) => find.byWidgetPredicate(
      (w) => w is TextFormField && w.controller?.text == value,
    );
    await next();
    expect(field('6500'), findsOneWidget);
    expect(tester.getRect(field('6500')).width, greaterThan(250));
    expect(
      tester.getRect(field('6000')).top,
      greaterThan(tester.getRect(field('6500')).bottom),
    );
    await next();
    await next();
    expect(field('40'), findsOneWidget);
    expect(
      tester.getRect(field('30')).top,
      greaterThan(tester.getRect(field('40')).bottom),
    );
    expect(
      tester.getRect(field('20')).top,
      greaterThan(tester.getRect(field('30')).bottom),
    );
    await next();
    expect(find.text('Prendre une photo'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}
