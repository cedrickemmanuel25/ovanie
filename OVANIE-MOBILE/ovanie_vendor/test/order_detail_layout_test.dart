import 'dart:io';
import 'dart:ui' as ui;
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ovanie_vendor/core/network/api_client.dart';
import 'package:ovanie_vendor/features/orders/order_detail_screen.dart';

void main() {
  final order = <String, dynamic>{
    'id': 37,
    'order_number': 'CMD-TEST-37',
    'created_at': '2026-09-09T09:20:00Z',
    'vendor_status': 'preparing',
    'payment_status': 'paid',
    'payment_status_label': 'Payée',
    'payment_method_label': 'Paiement en ligne',
    'delivery_provider_label': 'OVANIE Logistics',
    'client': {'name': 'Client test', 'phone': '+225 0700000000'},
    'delivery_address': {
      'site_name': 'Chantier de contrôle',
      'recipient_name': 'Contact de contrôle',
      'formatted': 'Cocody Angré, Abidjan',
    },
    'public_products_total': 175000,
    'delivery_total': 15000,
    'public_total_for_vendor': 190000,
    'commission_total': 8750,
    'vendor_payout_total': 166250,
    'timeline': {'payment_confirmed_at': '2026-09-09T09:20:00Z'},
    'items': [
      {
        'name': 'Ciment CPA 42.5R',
        'category_name': 'Ciment & Liants',
        'quantity': 20,
        'unit_label': 'sacs',
        'public_unit_price': 6500,
        'public_subtotal': 130000,
      },
      {
        'name': 'Peinture Pura Mat 15L',
        'category_name': 'Peintures & Finitions',
        'quantity': 2,
        'unit_label': 'unités',
        'public_unit_price': 18000,
        'public_subtotal': 36000,
      },
      {
        'name': 'Tube PVC Évacuation Ø100',
        'category_name': 'Plomberie & Sanitaire',
        'quantity': 4,
        'unit_label': 'unités',
        'public_unit_price': 2250,
        'public_subtotal': 9000,
      },
    ],
  };
  setUpAll(() async {
    final fonts = Directory(
      '${File(Platform.resolvedExecutable).parent.path}/../../material_fonts',
    );
    final font = FontLoader('ReviewFont');
    font.addFont(
      Future.value(
        ByteData.sublistView(
          await File('${fonts.path}/roboto-regular.ttf').readAsBytes(),
        ),
      ),
    );
    await font.load();
    final icons = FontLoader('MaterialIcons');
    icons.addFont(
      Future.value(
        ByteData.sublistView(
          await File('${fonts.path}/materialicons-regular.otf').readAsBytes(),
        ),
      ),
    );
    await icons.load();
  });
  late Interceptor interceptor;
  var fail = false;
  setUp(() {
    fail = false;
    interceptor = InterceptorsWrapper(
      onRequest: (options, handler) {
        handler.resolve(
          Response(
            requestOptions: options,
            statusCode: fail ? 500 : 200,
            data: fail
                ? {'message': 'Détail indisponible'}
                : options.path.endsWith('/37')
                ? {'order': order}
                : {'unread_notifications_count': 0},
          ),
        );
      },
    );
    ApiClient.dio.interceptors.add(interceptor);
  });
  tearDown(() => ApiClient.dio.interceptors.remove(interceptor));

  testWidgets('detail renders API fields on phone/tablet and large text', (
    tester,
  ) async {
    tester.view.devicePixelRatio = 1;
    addTearDown(() {
      tester.view.resetPhysicalSize();
      tester.view.resetDevicePixelRatio();
    });
    final boundary = GlobalKey();
    for (final width in [320.0, 390.0, 470.0, 800.0]) {
      tester.view.physicalSize = Size(width, 1600);
      await tester.pumpWidget(
        MaterialApp(
          theme: ThemeData(fontFamily: 'ReviewFont'),
          home: RepaintBoundary(
            key: boundary,
            child: OrderDetailScreen(key: ValueKey(width), orderId: 37),
          ),
        ),
      );
      await tester.pumpAndSettle();
      expect(find.text('#CMD-TEST-37'), findsOneWidget);
      expect(find.text('Chantier de contrôle'), findsOneWidget);
      expect(find.text('Contact de contrôle'), findsOneWidget);
      expect(find.text('Ciment CPA 42.5R'), findsOneWidget);
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
            'build/design-review/order-detail.png',
          ).writeAsBytes(bytes!.buffer.asUint8List());
          image.dispose();
        });
      }
    }
    tester.view.physicalSize = const Size(320, 1800);
    await tester.pumpWidget(
      MaterialApp(
        builder: (context, child) => MediaQuery(
          data: MediaQuery.of(
            context,
          ).copyWith(textScaler: const TextScaler.linear(1.5)),
          child: child!,
        ),
        home: const OrderDetailScreen(orderId: 37),
      ),
    );
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
  });

  testWidgets('failed detail never presents list summary as full order', (
    tester,
  ) async {
    fail = true;
    await tester.pumpWidget(
      MaterialApp(
        theme: ThemeData(fontFamily: 'ReviewFont'),
        home: OrderDetailScreen(
          orderId: 37,
          initialOrder: {'order_number': 'LIST-ONLY'},
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.text('Réessayer'), findsOneWidget);
    expect(find.text('Net vendeur estimé'), findsNothing);
    fail = false;
    await tester.tap(find.text('Réessayer'));
    await tester.pumpAndSettle();
    expect(find.text('#CMD-TEST-37'), findsOneWidget);
  });

  testWidgets('cancelled unpaid order has no preparation or paid claim', (
    tester,
  ) async {
    final previous = Map<String, dynamic>.from(order);
    addTearDown(() {
      order
        ..clear()
        ..addAll(previous);
    });
    order['vendor_status'] = 'cancelled';
    order['payment_status'] = 'pending';
    order['payment_status_label'] = 'En attente';
    order['timeline'] = <String, dynamic>{};
    tester.view.physicalSize = const Size(470, 1600);
    tester.view.devicePixelRatio = 1;
    addTearDown(() {
      tester.view.resetPhysicalSize();
      tester.view.resetDevicePixelRatio();
    });
    await tester.pumpWidget(
      MaterialApp(
        theme: ThemeData(fontFamily: 'ReviewFont'),
        home: OrderDetailScreen(orderId: 37),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.text('Commande annulée'), findsOneWidget);
    expect(find.text('Préparer la commande'), findsNothing);
    expect(find.text('Total payé client'), findsNothing);
    await tester.tap(find.text('Contacter le client'));
    await tester.pumpAndSettle();
    expect(find.text('Copier le numéro'), findsOneWidget);
  });
}
