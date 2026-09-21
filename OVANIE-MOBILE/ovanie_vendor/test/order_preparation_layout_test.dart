import 'dart:io';
import 'dart:ui' as ui;
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ovanie_vendor/core/network/api_client.dart';
import 'package:ovanie_vendor/features/orders/order_preparation_screen.dart';

void main() {
  final order = <String, dynamic>{
    'id': 73,
    'order_number': 'CMD-TEST-73',
    'created_at': '2026-09-09T09:20:00Z',
    'vendor_status': 'pending',
    'client': {'name': 'Client réel test', 'phone': '+225 0700000000'},
    'delivery_address': {'formatted': 'Cocody, Angré, Abidjan'},
    'delivery_provider_label': 'OVANIE Logistics',
    'tracking': {'estimated_delivery_at': '2026-09-12T12:00:00Z'},
    'items': [
      {
        'id': 101,
        'name': 'Ciment CPA 42.5R',
        'quantity': 10,
        'unit_label': 'sacs',
        'brand': 'CIMAF',
        'weight_label': '50 kg',
        'stock_available': true,
      },
      {
        'id': 102,
        'name': 'Tube PVC Évacuation Ø100',
        'quantity': 5,
        'unit_label': 'unités',
        'stock_available': false,
      },
      {
        'id': 103,
        'name': 'Peinture Pura Mat 15L',
        'quantity': 2,
        'unit_label': 'seaux',
      },
    ],
  };
  late Interceptor interceptor;
  var writes = 0;
  var fail = false;
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
  setUp(() {
    writes = 0;
    fail = false;
    order['vendor_status'] = 'pending';
    interceptor = InterceptorsWrapper(
      onRequest: (options, handler) {
        if (options.method == 'POST') {
          writes++;
          expect(options.data['vendor_status'], 'ready');
          if (!fail) order['vendor_status'] = 'ready';
        }
        handler.resolve(
          Response(
            requestOptions: options,
            statusCode: fail ? 500 : 200,
            data: fail
                ? {'message': 'Chargement impossible'}
                : options.path.endsWith('/73')
                ? {'order': order}
                : {'unread_notifications_count': 0},
          ),
        );
      },
    );
    ApiClient.dio.interceptors.add(interceptor);
  });
  tearDown(() => ApiClient.dio.interceptors.remove(interceptor));
  Widget app({Key? key, double scale = 1}) => MaterialApp(
    theme: ThemeData(fontFamily: 'ReviewFont'),
    builder: (context, child) => MediaQuery(
      data: MediaQuery.of(
        context,
      ).copyWith(textScaler: TextScaler.linear(scale)),
      child: child!,
    ),
    home: OrderPreparationScreen(key: key, orderId: 73),
  );

  testWidgets('preparation adapts and displays API stock, units and delivery', (
    tester,
  ) async {
    tester.view.devicePixelRatio = 1;
    addTearDown(() {
      tester.view.resetPhysicalSize();
      tester.view.resetDevicePixelRatio();
    });
    final boundary = GlobalKey();
    for (final width in [320.0, 390.0, 512.0, 800.0]) {
      tester.view.physicalSize = Size(width, 1600);
      await tester.pumpWidget(
        RepaintBoundary(
          key: boundary,
          child: app(key: ValueKey(width)),
        ),
      );
      await tester.pumpAndSettle();
      expect(find.text('Client réel test'), findsOneWidget);
      expect(find.text('10 sacs'), findsOneWidget);
      expect(find.text('Stock insuffisant'), findsOneWidget);
      expect(find.text('Stock à vérifier'), findsOneWidget);
      expect(tester.takeException(), isNull, reason: 'width $width');
      if (width == 512) {
        await tester.runAsync(() async {
          final image =
              await (boundary.currentContext!.findRenderObject()!
                      as RenderRepaintBoundary)
                  .toImage();
          final bytes = await image.toByteData(format: ui.ImageByteFormat.png);
          await Directory('build/design-review').create(recursive: true);
          await File(
            'build/design-review/order-preparation.png',
          ).writeAsBytes(bytes!.buffer.asUint8List());
          image.dispose();
        });
      }
    }
    tester.view.physicalSize = const Size(320, 2400);
    await tester.pumpWidget(app(key: const ValueKey('large'), scale: 1.5));
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
  });

  testWidgets('validation requires every article and all three confirmations', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(512, 1800);
    tester.view.devicePixelRatio = 1;
    addTearDown(() {
      tester.view.resetPhysicalSize();
      tester.view.resetDevicePixelRatio();
    });
    await tester.pumpWidget(app());
    await tester.pumpAndSettle();
    FilledButton button() => tester.widget<FilledButton>(
      find.widgetWithText(FilledButton, 'Valider la préparation'),
    );
    expect(button().onPressed, isNull);
    expect(
      tester
          .widgetList<Checkbox>(find.byType(Checkbox))
          .every((box) => box.value == false),
      isTrue,
    );
    await tester.tap(find.text('Tout cocher'));
    await tester.pump();
    expect(button().onPressed, isNull);
    for (final label in [
      'Tous les articles sont disponibles et en bon état',
      'Les quantités correspondent à la commande',
      'Les produits sont bien emballés et étiquetés',
    ]) {
      await tester.tap(find.text(label));
      await tester.pump();
    }
    expect(button().onPressed, isNotNull);
    expect(writes, 0);
    await tester.tap(find.text('Tout décocher'));
    await tester.pump();
    expect(button().onPressed, isNull);
    await tester.tap(find.text('Tout cocher'));
    await tester.pump();
    fail = true;
    await tester.tap(find.text('Valider la préparation'));
    await tester.pumpAndSettle();
    expect(writes, 1);
    expect(
      find.text(
        'OVANIE rencontre un problème technique. Réessayez dans quelques instants.',
      ),
      findsOneWidget,
    );
    expect(button().onPressed, isNull);
  });

  testWidgets('load error blocks validation and allows retry', (tester) async {
    fail = true;
    await tester.pumpWidget(app());
    await tester.pumpAndSettle();
    expect(find.text('Réessayer'), findsOneWidget);
    expect(
      tester
          .widget<FilledButton>(
            find.widgetWithText(FilledButton, 'Valider la préparation'),
          )
          .onPressed,
      isNull,
    );
    fail = false;
    await tester.tap(find.text('Réessayer'));
    await tester.pumpAndSettle();
    expect(find.text('Client réel test'), findsOneWidget);
    expect(writes, 0);
  });
  testWidgets('successful validation posts once and opens expedition', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(512, 1800);
    tester.view.devicePixelRatio = 1;
    addTearDown(() {
      tester.view.resetPhysicalSize();
      tester.view.resetDevicePixelRatio();
    });
    await tester.pumpWidget(app());
    await tester.pumpAndSettle();
    await tester.tap(find.text('Tout cocher'));
    await tester.pump();
    for (final label in [
      'Tous les articles sont disponibles et en bon état',
      'Les quantités correspondent à la commande',
      'Les produits sont bien emballés et étiquetés',
    ]) {
      await tester.tap(find.text(label));
      await tester.pump();
    }
    await tester.tap(find.text('Valider la préparation'));
    await tester.pumpAndSettle();
    expect(writes, 1);
    expect(find.text('Expédition de commande'), findsOneWidget);
  });

  testWidgets('cancelled order cannot be prepared', (tester) async {
    order['vendor_status'] = 'cancelled';
    await tester.pumpWidget(app());
    await tester.pumpAndSettle();
    expect(
      tester
          .widget<FilledButton>(
            find.widgetWithText(FilledButton, 'Valider la préparation'),
          )
          .onPressed,
      isNull,
    );
    expect(writes, 0);
  });
}
