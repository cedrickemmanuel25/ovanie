import 'dart:io';
import 'dart:ui' as ui;

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ovanie_vendor/app/theme.dart';
import 'package:ovanie_vendor/core/network/api_client.dart';
import 'package:ovanie_vendor/features/auth/vendor_session.dart';
import 'package:ovanie_vendor/features/dashboard/vendor_dashboard_screen.dart';

/// L'écran d'accueil doit s'afficher sans débordement sur un petit téléphone,
/// un téléphone courant et une tablette, avec de grands montants et un texte agrandi.
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

  Map<String, dynamic> dashboard({required bool withOrders}) => <String, dynamic>{
        'shop': {'name': 'Dupont Matériaux BTP', 'is_active': true, 'payment_mode': 'post_delivery'},
        'unread_notifications_count': 3,
        'stats': {
          'sales_today': 485000,
          'orders_today': 12,
          'active_products': 86,
          'orders_to_prepare': 4,
          'orders': 67,
          'payout_available': 1250000,
          'average_rating': 4.7,
          'reviews': 128,
        },
        'orders': withOrders
            ? [
                {'id': 1, 'order_number': 'CMD-4587', 'client_name': 'Kouassi Yao', 'public_products_total': 245000, 'vendor_status': 'preparing'},
                {'id': 2, 'order_number': 'CMD-4586', 'client_name': 'Aminata Koné', 'public_products_total': 78500, 'vendor_status': 'pending'},
                {'id': 3, 'order_number': 'CMD-20261006-000123', 'client_name': 'Serge Bamba Kouadio N’Guessan', 'public_products_total': 11120000, 'vendor_status': 'shipped'},
              ]
            : const [],
      };

  Future<void> open(WidgetTester tester, Size size, double scale, {bool withOrders = true, GlobalKey? key}) async {
    tester.view.physicalSize = size * 2;
    tester.view.devicePixelRatio = 2;
    addTearDown(tester.view.reset);
    ApiClient.dio.interceptors.add(InterceptorsWrapper(onRequest: (options, handler) {
      handler.resolve(Response(requestOptions: options, statusCode: 200, data: dashboard(withOrders: withOrders)));
    }));
    addTearDown(() => ApiClient.dio.interceptors.clear());
    VendorSession.instance.user = {'id': 1, 'name': 'M. Jean'};
    addTearDown(() => VendorSession.instance.user = null);

    await tester.pumpWidget(
      MaterialApp(
        debugShowCheckedModeBanner: false,
        theme: OvanieTheme.light,
        builder: (context, child) => MediaQuery(
          data: MediaQuery.of(context).copyWith(textScaler: TextScaler.linear(scale)),
          child: child!,
        ),
        home: Scaffold(
          body: RepaintBoundary(key: key, child: VendorDashboardScreen(onSelectTab: (_) {})),
        ),
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
      testWidgets('accueil — ${size.key} — texte ×$scale', (tester) async {
        await open(tester, size.value, scale);
        expect(tester.takeException(), isNull);
        expect(find.text('Dupont Matériaux BTP'), findsOneWidget);
        expect(find.text('Boutique active'), findsOneWidget);

        await tester.scrollUntilVisible(find.text('Conseil OVANIE'), 300, scrollable: find.byType(Scrollable).first);
        await tester.pump();
        expect(tester.takeException(), isNull);
      });
    }
  }

  testWidgets('accueil sans commande : message clair', (tester) async {
    await open(tester, const Size(360, 780), 1, withOrders: false);
    await tester.scrollUntilVisible(find.text('Aucune commande pour le moment.'), 300, scrollable: find.byType(Scrollable).first);
    expect(tester.takeException(), isNull);
  });

  // Rendu complet pour comparaison avec la maquette (lancé à la demande).
  testWidgets('rendu', (tester) async {
    const out = String.fromEnvironment('SHOT_DIR');
    if (out.isEmpty) return;
    final key = GlobalKey();
    await open(tester, const Size(404, 930), 1, key: key);
    await tester.runAsync(() async {
      final context = tester.element(find.byType(VendorDashboardScreen));
      for (final name in ['dash_header.jpg', 'dash_wallet.png', 'dash_tip.png', 'logo_white.png']) {
        await precacheImage(AssetImage('assets/images/mockups/$name'), context);
      }
    });
    await tester.pump(const Duration(milliseconds: 300));
    final boundary = key.currentContext!.findRenderObject()! as RenderRepaintBoundary;
    await tester.runAsync(() async {
      final image = await boundary.toImage(pixelRatio: 2);
      final bytes = await image.toByteData(format: ui.ImageByteFormat.png);
      await File('$out/dash.png').writeAsBytes(bytes!.buffer.asUint8List());
    });
  });
}
