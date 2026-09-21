import 'dart:io';
import 'dart:ui' as ui;
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ovanie_vendor/core/network/api_client.dart';
import 'package:ovanie_vendor/features/menu/profile_screens.dart';
import 'package:ovanie_vendor/features/menu/delivery_screens.dart';
import 'package:ovanie_vendor/features/menu/statistics_screen.dart';
import 'package:ovanie_vendor/features/menu/reviews_screen.dart';
import 'package:ovanie_vendor/features/shell/vendor_menu_screen.dart';
import 'package:ovanie_vendor/features/after_sales/after_sales_screen.dart';
import 'package:ovanie_vendor/features/notifications/vendor_notifications_screen.dart';

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

  final shop = <String, dynamic>{
    'id': 1,
    'name': 'Boutique réelle de test',
    'owner_name': 'Titulaire réel',
    'kyc_status': 'verified',
    'main_category': 'quincaillerie',
    'description': 'Des matériaux de qualité pour tous vos travaux.',
    'city': 'Abidjan',
    'address': 'Cocody',
    'whatsapp': '0701234567',
    'business_email': 'boutique@example.test',
    'logistics_type': 'seller',
    'logistics_mode_label': 'Logistique vendeur',
    'processing_time': '24_48h',
    'presentation': {
      'business_phone': '0701234567',
      'preparation': {
        'days': [1, 2, 3, 4, 5],
        'start': '08:00',
        'end': '18:00',
      },
    },
  };
  final user = {
    'id': 1,
    'name': 'Titulaire réel',
    'phone': '0701234567',
    'email': 'titulaire@example.test',
    'city': 'Abidjan',
    'role': 'vendor',
    'status': 'active',
    'created_at': '2026-01-01T10:00:00Z',
  };
  final zone = <String, dynamic>{
    'id': 9,
    'commune_id': 1,
    'city': 'Abidjan',
    'commune': 'Cocody',
    'vehicle_code': 'tricycle',
    'delivery_price': 2500,
    'estimated_delay': '24 h',
    'is_active': true,
  };
  final item = <String, dynamic>{
    'id': 8,
    'reference': 'RET-8',
    'status': 'pending',
    'reason': 'Produit non conforme',
    'product_name': 'Produit réel de test',
    'client_name': 'Client réel',
    'order_number': 'CMD-8',
    'created_at': '2026-09-08T10:00:00Z',
    'product': {
      'name': 'Produit réel de test',
      'quantity': 2,
      'price': 1250,
      'amount': 2500,
    },
    'client': {'name': 'Client réel', 'phone': '0700000000', 'city': 'Abidjan'},
    'timeline': [
      {'label': 'Demande créée', 'date': '2026-09-08T10:00:00Z'},
    ],
    'attachments': [],
    'can_decide': true,
  };
  final notification = <String, dynamic>{
    'id': 'n1',
    'title': 'Nouvelle commande reçue',
    'message': 'Une commande de votre boutique a été confirmée.',
    'category': 'orders',
    'order_id': 8,
    'read': false,
    'created_at': '2026-09-08T10:00:00Z',
    'details': {'amount': 2500, 'client_name': 'Client réel'},
  };
  late Interceptor interceptor;
  final writes = <RequestOptions>[];
  setUp(() {
    writes.clear();
    interceptor = InterceptorsWrapper(
      onRequest: (o, h) {
        if (o.method != 'GET') writes.add(o);
        final p = o.path;
        dynamic data = {};
        if (p.endsWith('/menu')) {
          data = {
            'shop': shop,
            'user': user,
            'counts': {
              'orders': 8,
              'notifications': 3,
              'returns': 1,
              'disputes': 2,
            },
          };
        } else if (p.endsWith('/shop')) {
          data = {'shop': shop};
        } else if (p.endsWith('/profile')) {
          data = {'shop': shop, 'user': user};
        } else if (p.endsWith('/shop/delivery')) {
          data = {
            'zones': [zone],
            'uses_seller_logistics': true,
          };
        } else if (p.contains('/geo/communes')) {
          data = {
            'communes': [
              {'id': 1, 'name': 'Cocody'},
            ],
          };
        } else if (p.endsWith('/categories')) {
          data = [];
        } else if (p.endsWith('/statistics')) {
          data = {
            'revenue': 2500,
            'orders': 1,
            'average': 2500,
            'growth': {'revenue': 10, 'orders': 0, 'average': 10},
            'series': [
              {'date': '2026-09-01', 'amount': 0, 'orders': 0},
              {'date': '2026-09-02', 'amount': 2500, 'orders': 1},
            ],
            'statuses': {'pending': 1},
            'top_products': [
              {'id': 7, 'name': 'Produit réel', 'amount': 2500, 'quantity': 2},
            ],
          };
        } else if (p.endsWith('/reviews')) {
          data = {
            'summary': {
              'count': 1,
              'average': 5,
              'distribution': {'5': 1},
              'replied': 0,
              'this_month': 1,
            },
            'data': [
              {
                'id': 12,
                'client_name': 'Client réel',
                'rating': 5,
                'comment': 'Bon produit réel',
                'product_name': 'Produit réel',
                'created_at': '2026-09-08T10:00:00Z',
              },
            ],
          };
        } else if (p.endsWith('/returns') || p.endsWith('/disputes')) {
          data = {
            'data': [item],
            'meta': {'last_page': 1},
          };
        } else if (p.endsWith('/returns/8') || p.endsWith('/disputes/8')) {
          data = {'case': item};
        } else if (p.endsWith('/notifications')) {
          data = {
            'data': [notification],
            'meta': {'last_page': 1},
          };
        } else if (p.endsWith('/notifications/n1')) {
          data = {'notification': notification};
        }
        h.resolve(Response(requestOptions: o, statusCode: 200, data: data));
      },
    );
    ApiClient.dio.interceptors.insert(0, interceptor);
  });
  tearDown(() => ApiClient.dio.interceptors.remove(interceptor));
  testWidgets(
    'all menu screens fit narrow phones and tablets with API values',
    (tester) async {
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetDevicePixelRatio);
      addTearDown(tester.view.resetPhysicalSize);
      final pages = <String, Widget Function()>{
        'menu': () => VendorMenuScreen(onSelectTab: (_) {}),
        'statistics': () => const StatisticsScreen(),
        'reviews': () => const ReviewsScreen(),
        'shop': () => const ShopProfileScreen(),
        'shop_edit': () => ShopEditScreen(shop: shop),
        'delivery': () => const DeliverySettingsScreen(),
        'zones': () => const DeliveryZonesScreen(),
        'zone_edit': () => DeliveryZoneEditScreen(shop: shop, zone: zone),
        'disputes': () => const AfterSalesScreen(disputes: true),
        'dispute_detail': () => const CaseDetailScreen(id: 8, disputes: true),
        'returns': () => const AfterSalesScreen(),
        'return_detail': () => const CaseDetailScreen(id: 8),
        'notifications': () => const VendorNotificationsScreen(),
        'notification_detail': () => const NotificationDetailScreen(id: 'n1'),
        'profile': () => const PersonalProfileScreen(),
      };
      for (final width in [320.0, 470.0, 800.0]) {
        tester.view.physicalSize = Size(width, 3200);
        for (final entry in pages.entries) {
          final key = GlobalKey();
          await tester.pumpWidget(
            MaterialApp(
              theme: ThemeData(fontFamily: 'ReviewFont'),
              home: MediaQuery(
                data: MediaQueryData(
                  size: Size(width, 3200),
                  textScaler: TextScaler.linear(width == 320 ? 1.3 : 1),
                ),
                child: RepaintBoundary(key: key, child: entry.value()),
              ),
            ),
          );
          await tester.pumpAndSettle();
          expect(
            tester.takeException(),
            isNull,
            reason: '${entry.key} at $width',
          );
          if (width == 470) {
            await tester.runAsync(() async {
            final boundary =
                key.currentContext!.findRenderObject() as RenderRepaintBoundary;
            final image = await boundary.toImage();
            final bytes = await image.toByteData(
              format: ui.ImageByteFormat.png,
            );
            await File('build/design-review/menu_${entry.key}.png')
                .create(recursive: true)
                .then((f) => f.writeAsBytes(bytes!.buffer.asUint8List()));
            image.dispose();
            });
          }
          await tester.pumpWidget(const SizedBox.shrink());
        }
      }
      expect(writes, isEmpty);
    },
  );
  testWidgets('review reply posts entered text only after send', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(470, 2000);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    await tester.pumpWidget(
      MaterialApp(
        theme: ThemeData(fontFamily: 'ReviewFont'),
        home: const ReviewsScreen(),
      ),
    );
    await tester.pumpAndSettle();
    await tester.tap(find.text('Répondre'));
    await tester.pumpAndSettle();
    expect(writes, isEmpty);
    await tester.enterText(
      find.byType(TextField).last,
      'Merci pour votre avis',
    );
    await tester.tap(find.text('Envoyer'));
    await tester.pumpAndSettle();
    expect(writes.single.path, endsWith('/reviews/12/reply'));
    expect(writes.single.data['reply'], 'Merci pour votre avis');
  });
}
