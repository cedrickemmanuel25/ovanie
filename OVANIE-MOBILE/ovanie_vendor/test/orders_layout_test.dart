import 'dart:convert';
import 'dart:io';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ovanie_vendor/app/theme.dart';
import 'package:ovanie_vendor/core/network/api_client.dart';
import 'package:ovanie_vendor/features/orders/order_detail_screen.dart';
import 'package:ovanie_vendor/features/orders/order_expedition_screen.dart';
import 'package:ovanie_vendor/features/orders/order_preparation_screen.dart';
import 'package:ovanie_vendor/features/orders/order_tracking_screen.dart';
import 'package:ovanie_vendor/features/orders/orders_screen.dart';

/// Commandes : liste, détail, préparation, expédition et suivi s'affichent
/// sans débordement du petit téléphone à la tablette, et respectent les
/// règles métier (OVANIE Logistics ne transmet pas les coordonnées du client).
void main() {
  late Map<String, dynamic> fixture;

  setUpAll(() async {
    final loader = FontLoader('PlusJakartaSans');
    for (final weight in <String>['Regular', 'Medium', 'SemiBold', 'Bold', 'ExtraBold']) {
      final bytes = File('assets/fonts/PlusJakartaSans-$weight.ttf').readAsBytesSync();
      loader.addFont(Future<ByteData>.value(ByteData.view(bytes.buffer)));
    }
    await loader.load();
    fixture = jsonDecode(File('test/fixtures/orders.json').readAsStringSync()) as Map<String, dynamic>;
  });

  // 206 : livraison vendeur à préparer · 207 : OVANIE Logistics · 210 : en livraison.
  Object payloadFor(String path) {
    if (path.endsWith('/206')) return fixture['seller'];
    if (path.endsWith('/207')) return fixture['ovanie'];
    if (path.endsWith('/210')) return fixture['shipped'];
    return fixture['list'];
  }

  Future<void> open(WidgetTester tester, Widget screen, Size size, double scale) async {
    tester.view.physicalSize = size * 2;
    tester.view.devicePixelRatio = 2;
    addTearDown(tester.view.reset);
    ApiClient.dio.interceptors.add(InterceptorsWrapper(onRequest: (options, handler) {
      handler.resolve(Response(requestOptions: options, statusCode: 200, data: payloadFor(options.path)));
    }));
    addTearDown(() => ApiClient.dio.interceptors.clear());
    await tester.pumpWidget(
      MaterialApp(
        theme: OvanieTheme.light,
        builder: (context, child) => MediaQuery(
          data: MediaQuery.of(context).copyWith(textScaler: TextScaler.linear(scale)),
          child: child!,
        ),
        home: Material(child: screen),
      ),
    );
    await tester.pump(const Duration(milliseconds: 400));
    await tester.pump(const Duration(milliseconds: 400));
  }

  /// Retire l'écran pour arrêter ses minuteries (suivi : rafraîchissement 15 s).
  Future<void> close(WidgetTester tester) async {
    await tester.pumpWidget(const SizedBox.shrink());
    await tester.pump(const Duration(milliseconds: 100));
  }

  final screens = <String, (Widget Function(), String)>{
    'liste': (() => const OrdersScreen(), '#OVANIE-7EQBU664'),
    'détail vendeur': (() => const OrderDetailScreen(orderId: 206), 'Contacter le client'),
    'détail OVANIE': (() => const OrderDetailScreen(orderId: 207), 'Détail de la commande'),
    'préparation': (() => const OrderPreparationScreen(orderId: 206), 'Vérifications avant validation'),
    'expédition vendeur': (() => const OrderExpeditionScreen(orderId: 206), 'Expédition de commande'),
    'expédition OVANIE': (() => const OrderExpeditionScreen(orderId: 207), 'Expédition de commande'),
    'suivi vendeur': (() => const OrderTrackingScreen(orderId: 210), 'Dernières mises à jour'),
    'suivi OVANIE': (() => const OrderTrackingScreen(orderId: 207), 'Dernières mises à jour'),
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
          // Parcourt tout l'écran : chaque carte est construite et mesurée.
          final scrollable = find.byType(Scrollable).first;
          for (var i = 0; i < 12; i++) {
            await tester.drag(scrollable, const Offset(0, -400), warnIfMissed: false);
            await tester.pump(const Duration(milliseconds: 50));
            expect(tester.takeException(), isNull);
          }
          expect(find.text(screen.value.$2, skipOffstage: false), findsWidgets);
          await close(tester);
        });
      }
    }
  }

  testWidgets('liste : les onglets filtrent les commandes par état', (tester) async {
    await open(tester, const OrdersScreen(), const Size(412, 915), 1);
    expect(find.text('#OVANIE-7EQBU664', skipOffstage: false), findsOneWidget);
    expect(find.textContaining('mobile_money'), findsNothing);
    await close(tester);
  });

  testWidgets('OVANIE Logistics : ni téléphone ni adresse exacte du client', (tester) async {
    await open(tester, const OrderDetailScreen(orderId: 207), const Size(412, 915), 1);
    expect(find.text('Contacter le client'), findsNothing);
    expect(find.textContaining('rue des Jardins', skipOffstage: false), findsNothing);
    expect(find.textContaining('07 01 00 00 00', skipOffstage: false), findsNothing);
    await close(tester);
  });

  testWidgets('préparation : la validation exige les articles et les trois vérifications', (tester) async {
    await open(tester, const OrderPreparationScreen(orderId: 206), const Size(412, 915), 1);
    await tester.tap(find.text('Valider la préparation'));
    await tester.pump();
    expect(find.text('Cochez chaque article préparé.'), findsOneWidget);

    await tester.ensureVisible(find.text('Tout cocher'));
    await tester.pump();
    await tester.tap(find.text('Tout cocher'));
    await tester.pump();
    expect(find.text('2/2'), findsOneWidget);
    expect(find.text('Tout décocher'), findsOneWidget);
    await close(tester);
  });

  testWidgets('suivi OVANIE : mission, code de remise, pas de carte livreur', (tester) async {
    await open(tester, const OrderTrackingScreen(orderId: 207), const Size(412, 915), 1);
    expect(find.text('Prise en charge OVANIE Logistics', skipOffstage: false), findsOneWidget);
    expect(find.text('MIS-2026-000184', skipOffstage: false), findsOneWidget);
    expect(find.text('482913', skipOffstage: false), findsOneWidget);
    expect(find.text('Appeler le livreur', skipOffstage: false), findsNothing);
    expect(find.text('Livrée', skipOffstage: false), findsNothing);
    await close(tester);
  });

  testWidgets('suivi vendeur : le livreur de la boutique peut être appelé', (tester) async {
    await open(tester, const OrderTrackingScreen(orderId: 210), const Size(412, 915), 1);
    await tester.scrollUntilVisible(find.text('Appeler le livreur'), 250, scrollable: find.byType(Scrollable).first);
    expect(find.text('Yao Koffi Marcel'), findsOneWidget);
    expect(find.text('1234 AB 01'), findsOneWidget);
    await close(tester);
  });
}
