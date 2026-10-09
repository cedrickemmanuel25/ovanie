import 'dart:convert';
import 'dart:io';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ovanie_vendor/app/theme.dart';
import 'package:ovanie_vendor/core/network/api_client.dart';
import 'package:ovanie_vendor/features/finance/finance_screen.dart';
import 'package:ovanie_vendor/features/finance/payout_detail_screen.dart';
import 'package:ovanie_vendor/features/finance/payout_followup_screen.dart';
import 'package:ovanie_vendor/features/finance/payout_settings_screen.dart';
import 'package:ovanie_vendor/features/finance/payouts_screen.dart';

/// Finances : accueil, versements, détail, suivi et paramètres s'affichent
/// sans débordement du petit téléphone à la tablette, et les actions clés
/// (filtres, validation du compte, demande de suivi) se comportent correctement.
void main() {
  late Map<String, dynamic> fixture;
  final requests = <String>[];

  setUpAll(() async {
    final loader = FontLoader('PlusJakartaSans');
    for (final weight in <String>['Regular', 'Medium', 'SemiBold', 'Bold', 'ExtraBold']) {
      final bytes = File('assets/fonts/PlusJakartaSans-$weight.ttf').readAsBytesSync();
      loader.addFont(Future<ByteData>.value(ByteData.view(bytes.buffer)));
    }
    await loader.load();
    fixture = jsonDecode(File('test/fixtures/finance.json').readAsStringSync()) as Map<String, dynamic>;
  });

  // 63 : versement effectué · 57 : en attente de livraison, suivi déjà demandé.
  Object payloadFor(String path) {
    if (path.endsWith('/finance')) return fixture['finance'];
    if (path.endsWith('/payouts')) return fixture['payouts'];
    if (path.endsWith('/payouts/63')) return fixture['paid'];
    if (path.endsWith('/payouts/57')) return fixture['blocked'];
    if (path.endsWith('/payment-settings')) return fixture['settings'];
    return <String, dynamic>{};
  }

  Future<void> open(WidgetTester tester, Widget screen, Size size, double scale) async {
    tester.view.physicalSize = size * 2;
    tester.view.devicePixelRatio = 2;
    addTearDown(tester.view.reset);
    requests.clear();
    ApiClient.dio.interceptors.add(InterceptorsWrapper(onRequest: (options, handler) {
      requests.add('${options.method} ${options.path}');
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

  final screens = <String, (Widget Function(), String)>{
    'accueil': (() => const FinanceScreen(), 'Dernières opérations'),
    'versements': (() => const PayoutsScreen(), 'Télécharger relevé'),
    'détail effectué': (() => const PayoutDetailScreen(payoutId: 63), 'Demander un suivi'),
    'détail en attente': (() => const PayoutDetailScreen(payoutId: 57), 'Suivre ma demande'),
    'suivi': (() => const PayoutFollowUpScreen(payoutId: 57), 'Contacter le support'),
    'paramètres': (() => const PayoutSettingsScreen(), 'Titulaire du compte'),
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
          final scrollable = find.byType(Scrollable).first;
          for (var i = 0; i < 12; i++) {
            await tester.drag(scrollable, const Offset(0, -400), warnIfMissed: false);
            await tester.pump(const Duration(milliseconds: 50));
            expect(tester.takeException(), isNull);
          }
          expect(find.textContaining(screen.value.$2, skipOffstage: false), findsWidgets);
        });
      }
    }
  }

  testWidgets('accueil : indicateurs réels et bascule 3 / 6 mois', (tester) async {
    await open(tester, const FinanceScreen(), const Size(412, 915), 1);
    expect(find.text('Solde disponible'), findsOneWidget);
    expect(find.text('Actif'), findsOneWidget);
    expect(find.text('Mai', skipOffstage: false), findsOneWidget);
    await tester.ensureVisible(find.text('3 derniers mois'));
    await tester.pump();
    await tester.tap(find.text('3 derniers mois'));
    await tester.pump();
    expect(find.text('Mai', skipOffstage: false), findsNothing);
    expect(find.text('Oct.', skipOffstage: false), findsOneWidget);
  });

  testWidgets('versements : les onglets et la feuille filtrent la liste', (tester) async {
    await open(tester, const PayoutsScreen(), const Size(412, 915), 1);
    await tester.tap(find.text('Effectués'));
    await tester.pump();
    expect(find.text('Effectué', skipOffstage: false), findsOneWidget);
    expect(find.text('En attente de livraison', skipOffstage: false), findsNothing);

    await tester.tap(find.text('Filtrer / Trier'));
    await tester.pumpAndSettle();
    expect(find.text('Trier par date'), findsOneWidget);
    await tester.tap(find.text('En traitement'));
    await tester.pump();
    await tester.tap(find.text('Afficher les versements'));
    await tester.pumpAndSettle();
    expect(find.text('Effectué', skipOffstage: false), findsNothing);
    expect(find.text('En traitement', skipOffstage: false), findsWidgets);
  });

  testWidgets('suivi : une demande déjà enregistrée n’est jamais renvoyée', (tester) async {
    await open(tester, const PayoutFollowUpScreen(payoutId: 57), const Size(412, 915), 1);
    expect(requests.where((r) => r.startsWith('POST')), isEmpty);
    await tester.tap(find.text('Actualiser le statut'));
    await tester.pump(const Duration(milliseconds: 400));
    expect(requests.where((r) => r.startsWith('POST')), isEmpty);
    expect(find.text('Demande de suivi en cours'), findsOneWidget);
  });

  testWidgets('paramètres : un numéro incomplet est refusé avant l’envoi', (tester) async {
    await open(tester, const PayoutSettingsScreen(), const Size(412, 915), 1);
    expect(find.text('Orange Money'), findsOneWidget);
    await tester.enterText(find.byType(TextFormField).first, '0700');
    await tester.tap(find.text('Enregistrer mes paramètres'));
    await tester.pump();
    expect(find.text('Le numéro Mobile Money doit contenir 10 chiffres.', skipOffstage: false), findsOneWidget);
    expect(requests.where((r) => r.startsWith('POST')), isEmpty);
  });

  testWidgets('paramètres : la feuille propose les quatre opérateurs', (tester) async {
    await open(tester, const PayoutSettingsScreen(), const Size(412, 915), 1);
    await tester.ensureVisible(find.text('Orange Money'));
    await tester.pump();
    await tester.tap(find.text('Orange Money'));
    await tester.pumpAndSettle();
    for (final name in ['MTN Mobile Money', 'Wave', 'Moov Money']) {
      expect(find.text(name), findsOneWidget);
    }
    await tester.tap(find.text('Wave'));
    await tester.pumpAndSettle();
    expect(find.text('Wave'), findsOneWidget);
    expect(find.text('Orange Money'), findsNothing);
  });
}
