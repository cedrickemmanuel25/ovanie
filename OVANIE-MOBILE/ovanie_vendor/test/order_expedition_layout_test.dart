import 'dart:io';
import 'dart:ui' as ui;
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ovanie_vendor/core/network/api_client.dart';
import 'package:ovanie_vendor/features/orders/order_expedition_screen.dart';

void main() {
  late Map<String, dynamic> order;
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
    order = {
      'id': 84,
      'order_number': 'CMD-TEST-84',
      'created_at': '2026-09-09T10:00:00Z',
      'vendor_status': 'ready',
      'delivery_status': 'assigned',
      'delivery_provider': 'ovanie',
      'delivery_provider_label': 'OVANIE Logistics',
      'client': {'name': 'Client test', 'phone': '+225 0700000000'},
      'delivery_address': {'formatted': 'Cocody, Angré, Abidjan'},
      'tracking': {
        'driver': {
          'name': 'Livreur test',
          'phone': '+225 0500000000',
          'vehicle_plate': 'ABJ-TEST',
          'vehicle_label': 'Tricycle',
        },
        'mission_number': 'MISSION-84',
      },
      'items': List.generate(
        4,
        (i) => {
          'id': i + 1,
          'name': 'Article réel $i',
          'quantity': i + 2,
          'unit_label': 'sacs',
          'vendor_status': 'ready',
          'delivery_status': 'assigned',
          'delivery_provider': 'ovanie',
        },
      ),
    };
    interceptor = InterceptorsWrapper(
      onRequest: (options, handler) {
        if (options.method == 'POST') {
          writes++;
          expect(options.data['delivery_provider'], 'ovanie');
        }
        handler.resolve(
          Response(
            requestOptions: options,
            statusCode: fail ? 500 : 200,
            data: fail
                ? {}
                : options.path.endsWith('/84')
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
    debugShowCheckedModeBanner: false,
    theme: ThemeData(fontFamily: 'ReviewFont'),
    builder: (context, child) => MediaQuery(
      data: MediaQuery.of(
        context,
      ).copyWith(textScaler: TextScaler.linear(scale)),
      child: child!,
    ),
    home: OrderExpeditionScreen(key: key, orderId: 84),
  );

  testWidgets('expedition layouts and real assignment state', (tester) async {
    tester.view.devicePixelRatio = 1;
    addTearDown(() {
      tester.view.resetPhysicalSize();
      tester.view.resetDevicePixelRatio();
    });
    final boundary = GlobalKey();
    for (final width in [320.0, 390.0, 470.0, 800.0]) {
      tester.view.physicalSize = Size(width, 1900);
      await tester.pumpWidget(
        RepaintBoundary(
          key: boundary,
          child: app(key: ValueKey(width)),
        ),
      );
      await tester.pumpAndSettle();
      expect(find.text('Livreur test'), findsOneWidget);
      expect(find.text('ABJ-TEST'), findsOneWidget);
      expect(find.text('Expédié'), findsNothing);
      expect(find.text('Quantité à expédier'), findsNWidgets(3));
      expect(tester.takeException(), isNull, reason: 'width $width');
      if (width == 470) {
        await tester.runAsync(() async {
          final image =
              await (boundary.currentContext!.findRenderObject()!
                      as RenderRepaintBoundary)
                  .toImage();
          final bytes = await image.toByteData(format: ui.ImageByteFormat.png);
          await Directory('build/design-review').create(recursive: true);
          await File(
            'build/design-review/order-expedition.png',
          ).writeAsBytes(bytes!.buffer.asUint8List());
          image.dispose();
        });
      }
    }
    await tester.tap(find.text('Tout voir'));
    await tester.pump();
    expect(find.text('Article réel 3'), findsOneWidget);
    tester.view.physicalSize = const Size(320, 2600);
    await tester.pumpWidget(app(key: const ValueKey('large'), scale: 1.5));
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
  });

  testWidgets('confirm requires three controls and calls ship API', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(470, 2000);
    tester.view.devicePixelRatio = 1;
    addTearDown(() {
      tester.view.resetPhysicalSize();
      tester.view.resetDevicePixelRatio();
    });
    await tester.pumpWidget(app());
    await tester.pumpAndSettle();
    final button = find.widgetWithText(FilledButton, 'Confirmer l’expédition');
    expect(tester.widget<FilledButton>(button).onPressed, isNull);
    for (final label in [
      'Les articles préparés ont été remis au livreur',
      'Le bon de livraison a été vérifié',
      'Le client a été notifié de l’expédition',
    ]) {
      await tester.tap(find.text(label));
      await tester.pump();
    }
    expect(tester.widget<FilledButton>(button).onPressed, isNotNull);
    fail = true;
    await tester.tap(button);
    await tester.pumpAndSettle();
    expect(writes, 1);
    expect(find.text('Réessayer'), findsOneWidget);
    expect(tester.widget<FilledButton>(button).onPressed, isNull);
  });

  testWidgets('driver call opens dialer with API phone', (tester) async {
    String? url;
    tester.binding.defaultBinaryMessenger.setMockMethodCallHandler(
      const MethodChannel('ovanie/external_url'),
      (call) async {
        url = call.arguments['url'];
        return true;
      },
    );
    addTearDown(
      () => tester.binding.defaultBinaryMessenger.setMockMethodCallHandler(
        const MethodChannel('ovanie/external_url'),
        null,
      ),
    );
    tester.view.physicalSize = const Size(470, 1900);
    tester.view.devicePixelRatio = 1;
    addTearDown(() {
      tester.view.resetPhysicalSize();
      tester.view.resetDevicePixelRatio();
    });
    await tester.pumpWidget(app());
    await tester.pumpAndSettle();
    await tester.tap(find.text('Appeler le livreur'));
    await tester.pumpAndSettle();
    expect(url, 'tel:+2250500000000');
    expect(writes, 0);
  });
}
