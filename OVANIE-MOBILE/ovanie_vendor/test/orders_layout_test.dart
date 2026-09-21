import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ovanie_vendor/core/network/api_client.dart';
import 'package:ovanie_vendor/features/orders/orders_screen.dart';

void main() {
  testWidgets('orders render API values without overflow on phone and tablet', (tester) async {
    final interceptor = InterceptorsWrapper(onRequest: (options, handler) {
      handler.resolve(Response(requestOptions: options, statusCode: 200, data:
        options.path.endsWith('/orders') ? {
          'data': [{'id': 37, 'order_number': 'CMD-TEST-37', 'client_display_name': 'Chantier de contrôle',
            'vendor_status': 'pending', 'payment_status': 'paid', 'payment_status_label': 'Payée',
            'payment_method_label': 'Paiement en ligne', 'public_products_total': 175000,
            'created_at': '2026-09-09T09:20:00Z'}],
          'stats': {'total': 42, 'pending': 6, 'to_process': 9, 'delivered': 27},
          'meta': {'current_page': 1, 'last_page': 1},
        } : {'unread_notifications_count': 0}));
    });
    ApiClient.dio.interceptors.add(interceptor);
    addTearDown(() { ApiClient.dio.interceptors.remove(interceptor); tester.view.resetPhysicalSize(); tester.view.resetDevicePixelRatio(); });
    tester.view.devicePixelRatio = 1;
    for (final width in [320.0, 390.0, 470.0, 800.0]) {
      tester.view.physicalSize = Size(width, 1100);
      await tester.pumpWidget(MaterialApp(home: Scaffold(body: OrdersScreen(key: ValueKey(width)))));
      await tester.pumpAndSettle();
      expect(find.text('#CMD-TEST-37'), findsOneWidget);
      expect(find.text('Chantier de contrôle'), findsOneWidget);
      expect(find.text('42'), findsOneWidget);
      expect(tester.takeException(), isNull, reason: 'width $width');
    }
    await tester.pumpWidget(const SizedBox());
  });
}
