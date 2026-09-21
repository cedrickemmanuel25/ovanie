import 'dart:io';
import 'dart:ui' as ui;
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ovanie_vendor/core/network/api_client.dart';
import 'package:ovanie_vendor/features/finance/finance_screen.dart';
import 'package:ovanie_vendor/features/finance/payouts_screen.dart';
import 'package:ovanie_vendor/features/finance/payout_detail_screen.dart';
import 'package:ovanie_vendor/features/finance/payout_followup_screen.dart';
import 'package:ovanie_vendor/features/orders/order_tracking_screen.dart';

void main() {
  late Interceptor interceptor;
  late Map<String, dynamic> payout;
  var posts = 0;
  var fail = false;
  final summary = {
    'ready': 1245500,
    'paid': 3860000,
    'scheduled': 318500,
    'month_sales': 3860000,
    'month_commission': 193000,
    'month_orders_count': 42,
    'month_paid_count': 2,
    'next_payout_amount': 425000,
    'next_payout_method': 'Orange Money',
    'next_payment_at': '2026-09-13T12:00:00Z',
    'monthly_revenue': List.generate(
      6,
      (i) => {
        'month': '2026-${(i + 4).toString().padLeft(2, '0')}',
        'sales': (i + 1) * 500000,
        'commission': (i + 1) * 25000,
      },
    ),
  };
  final order = {
    'id': 37,
    'order_number': 'CMD-37',
    'created_at': '2026-09-09T10:00:00Z',
    'vendor_status': 'shipped',
    'delivery_status': 'in_transit',
    'delivery_status_label': 'En cours de livraison',
    'public_products_total': 78500,
    'timeline': {
      'received_at': '2026-09-09T10:00:00Z',
      'prepared_at': '2026-09-09T11:00:00Z',
      'shipped_at': '2026-09-09T12:00:00Z',
    },
    'tracking': {
      'driver': {
        'name': 'Livreur API',
        'phone': '+2250500000000',
        'rating': 4.8,
        'vehicle_label': 'Tricycle',
        'vehicle_plate': 'ABJ-2481',
      },
    },
    'items': [
      {'id': 2, 'name': 'Ciment', 'quantity': 10},
    ],
    'status_history': [
      {
        'label': 'En cours de livraison',
        'message': 'Prise en charge confirmée.',
        'created_at': '2026-09-09T12:00:00Z',
      },
    ],
  };
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
    posts = 0;
    fail = false;
    payout = {
      'id': 8,
      'reference': 'VRS-API-008',
      'status': 'paid',
      'status_label': 'Payé',
      'amount': 285000,
      'total_amount': 320000,
      'commission_amount': 32000,
      'processing_fee_amount': 3000,
      'phone': '+2250700000000',
      'beneficiary_name': 'Bénéficiaire API',
      'payment_method': 'orange_money',
      'payment_method_label': 'Orange Money',
      'created_at': '2026-09-01T10:00:00Z',
      'scheduled_for': '2026-09-02T10:00:00Z',
      'processing_at': '2026-09-03T10:00:00Z',
      'paid_at': '2026-09-04T10:00:00Z',
      'order': {
        'id': 37,
        'order_number': 'CMD-37',
        'created_at': '2026-09-01T10:00:00Z',
      },
      'has_receipt': false,
    };
    interceptor = InterceptorsWrapper(
      onRequest: (options, handler) {
        if (options.method == 'POST') {
          posts++;
          if (!fail) {
            payout['vendor_followup_requested_at'] = '2026-09-09T12:00:00Z';
          }
        }
        dynamic data;
        if (options.path.endsWith('/finance')) {
          data = {
            'summary': summary,
            'recent_payouts': [payout],
          };
        } else if (options.path.endsWith('/payouts')) {
          data = {
            'data': [payout],
            'meta': {'last_page': 1},
          };
        } else if (options.path.endsWith('/payouts/8')) {
          data = {'payout': payout};
        } else if (options.path.endsWith('/orders/37')) {
          data = {'order': order};
        } else {
          data = {'unread_notifications_count': 0};
        }
        handler.resolve(
          Response(
            requestOptions: options,
            statusCode: fail ? 500 : 200,
            data: data,
          ),
        );
      },
    );
    ApiClient.dio.interceptors.add(interceptor);
  });
  tearDown(() => ApiClient.dio.interceptors.remove(interceptor));
  Widget app(Widget child, {double scale = 1}) => MaterialApp(
    debugShowCheckedModeBanner: false,
    theme: ThemeData(fontFamily: 'ReviewFont'),
    builder: (context, child) => MediaQuery(
      data: MediaQuery.of(
        context,
      ).copyWith(textScaler: TextScaler.linear(scale)),
      child: child!,
    ),
    home: child,
  );

  testWidgets('five screens fit phone and tablet with live API-shaped data', (
    tester,
  ) async {
    tester.view.devicePixelRatio = 1;
    addTearDown(() {
      tester.view.resetPhysicalSize();
      tester.view.resetDevicePixelRatio();
    });
    final screens = <String, Widget>{
      'finance': const FinanceScreen(),
      'payouts': const PayoutsScreen(),
      'payout-detail': const PayoutDetailScreen(payoutId: 8),
      'payout-followup': const PayoutFollowUpScreen(payoutId: 8),
      'delivery-tracking': const OrderTrackingScreen(orderId: 37),
    };
    for (final width in [320.0, 470.0, 800.0]) {
      tester.view.physicalSize = Size(width, 2400);
      for (final entry in screens.entries) {
        final key = GlobalKey();
        await tester.pumpWidget(
          app(RepaintBoundary(key: key, child: entry.value)),
        );
        await tester.pumpAndSettle();
        expect(
          tester.takeException(),
          isNull,
          reason: '${entry.key} width $width',
        );
        if (width == 470) {
          await tester.runAsync(() async {
            final image =
                await (key.currentContext!.findRenderObject()!
                        as RenderRepaintBoundary)
                    .toImage();
            final bytes = await image.toByteData(
              format: ui.ImageByteFormat.png,
            );
            await Directory('build/design-review').create(recursive: true);
            await File(
              'build/design-review/${entry.key}.png',
            ).writeAsBytes(bytes!.buffer.asUint8List());
            image.dispose();
          });
        }
        await tester.pumpWidget(const SizedBox());
        await tester.pump();
      }
    }
    tester.view.physicalSize = const Size(320, 3000);
    for (final screen in screens.values) {
      await tester.pumpWidget(app(screen, scale: 1.5));
      await tester.pumpAndSettle();
      expect(tester.takeException(), isNull);
      await tester.pumpWidget(const SizedBox());
      await tester.pump();
    }
  });

  testWidgets(
    'followup is explicit, refresh never posts or invents a request',
    (tester) async {
      tester.view.physicalSize = const Size(470, 2400);
      tester.view.devicePixelRatio = 1;
      addTearDown(() {
        tester.view.resetPhysicalSize();
        tester.view.resetDevicePixelRatio();
      });
      await tester.pumpWidget(app(const PayoutFollowUpScreen(payoutId: 8)));
      await tester.pumpAndSettle();
      expect(posts, 0);
      expect(find.text('Aucune demande envoyée'), findsOneWidget);
      await tester.tap(find.text('Actualiser le statut'));
      await tester.pumpAndSettle();
      expect(posts, 0);
      await tester.tap(find.text('Envoyer la demande de suivi'));
      await tester.pumpAndSettle();
      expect(posts, 1);
      expect(find.text('Demande de suivi enregistrée'), findsOneWidget);
      await tester.tap(find.text('Actualiser le statut'));
      await tester.pumpAndSettle();
      expect(posts, 1);
    },
  );

  testWidgets('search and filter use payout data', (tester) async {
    await tester.pumpWidget(app(const PayoutsScreen()));
    await tester.pumpAndSettle();
    await tester.enterText(find.byType(TextField), 'absent');
    await tester.pump();
    expect(find.text('VRS-API-008'), findsNothing);
    await tester.enterText(find.byType(TextField), 'VRS-API');
    await tester.pump();
    expect(find.text('VRS-API-008'), findsOneWidget);
  });

  testWidgets('missing GPS renders no fictitious map or position', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(470, 2400);
    tester.view.devicePixelRatio = 1;
    addTearDown(() {
      tester.view.resetPhysicalSize();
      tester.view.resetDevicePixelRatio();
    });
    await tester.pumpWidget(app(const OrderTrackingScreen(orderId: 37)));
    await tester.pumpAndSettle();
    expect(
      find.text('La position de livraison n’est pas encore disponible.'),
      findsOneWidget,
    );
    expect(find.text('Livreur API'), findsOneWidget);
    await tester.pumpWidget(const SizedBox());
    await tester.pump();
  });
}
