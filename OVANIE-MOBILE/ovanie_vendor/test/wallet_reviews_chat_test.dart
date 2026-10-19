import 'dart:convert';
import 'dart:io';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ovanie_vendor/app/theme.dart';
import 'package:ovanie_vendor/core/compatibility/schema_compatibility.dart';
import 'package:ovanie_vendor/core/network/api_client.dart';
import 'package:ovanie_vendor/features/finance/wallet_screen.dart';
import 'package:ovanie_vendor/features/menu/reviews_screen.dart';
import 'package:ovanie_vendor/features/support/vendor_ai_chat_screen.dart';

/// Mon solde, Avis clients, discussion avec N'Nan et mise à jour requise :
/// affichage sans débordement et comportements clés.
void main() {
  late Map<String, dynamic> finance;
  final requests = <String>[];
  var chatClosed = false;

  setUpAll(() async {
    final loader = FontLoader('PlusJakartaSans');
    for (final weight in <String>['Regular', 'Medium', 'SemiBold', 'Bold', 'ExtraBold']) {
      final bytes = File('assets/fonts/PlusJakartaSans-$weight.ttf').readAsBytesSync();
      loader.addFont(Future<ByteData>.value(ByteData.view(bytes.buffer)));
    }
    await loader.load();
    finance = jsonDecode(File('test/fixtures/finance.json').readAsStringSync()) as Map<String, dynamic>;
    final summary = Map<String, dynamic>.from(finance['finance']['summary'] as Map);
    summary['ready'] = 1250000;
    summary['ready_count'] = 3;
    finance['finance']['summary'] = summary;
  });

  final reviews = <String, dynamic>{
    'data': [
      {
        'id': 1,
        'rating': 5,
        'comment': 'Livraison rapide et sacs en parfait état, je recommande.',
        'client_name': 'Kouassi Yao',
        'product_id': 503,
        'product_name': 'Ciment Bélier Classic 50 kg',
        'product_image_url': '',
        'created_at': '2026-09-12T10:00:00Z',
        'reply': 'Merci pour votre confiance !',
        'replied_at': '2026-09-13T10:00:00Z',
      },
      {
        'id': 2,
        'rating': 4,
        'comment': 'Bonne qualité, prix correct.',
        'client_name': 'Aminata Koné',
        'product_id': 505,
        'product_name': 'Fer à béton HA 12',
        'product_image_url': '',
        'created_at': '2026-09-10T10:00:00Z',
        'reply': null,
        'replied_at': null,
      },
    ],
    'summary': {
      'count': 2,
      'average': 4.5,
      'distribution': {'1': 0, '2': 0, '3': 0, '4': 1, '5': 1},
      'replied': 1,
      'this_month': 2,
    },
  };

  Object payloadFor(String path) {
    if (path.endsWith('/finance')) return finance['finance'];
    if (path.endsWith('/payment-settings')) return finance['settings'];
    if (path.endsWith('/reviews')) return reviews;
    if (path.endsWith('/support/chat/latest')) return {'exists': true, 'conversation_token': 'abc'};
    if (path.endsWith('/support/chat/abc')) {
      return {
        'status': chatClosed ? 'closed' : 'open',
        'agent': {'name': 'N’Nan'},
        'requires_human': false,
        'messages': [
          {'sender': 'ai', 'body': 'Bonjour Jean, je suis N’Nan. Comment puis-je vous aider ?', 'created_at': DateTime.now().toIso8601String()},
          {'sender': 'customer', 'body': 'Mon versement est toujours en attente.', 'created_at': DateTime.now().toIso8601String()},
        ],
      };
    }
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
    for (var i = 0; i < 4; i++) {
      await tester.pump(const Duration(milliseconds: 250));
    }
  }

  final screens = <String, (Widget Function(), String)>{
    'mon solde': (() => const WalletScreen(), 'Compte de versement'),
    'avis clients': (() => const ReviewsScreen(), 'Aminata Koné'),
    'discussion': (() => const VendorAiChatScreen(), 'Mon versement est toujours en attente.'),
    'mise à jour requise': (
      () => const OvanieSchemaIncompatibleApp(
        result: SchemaCompatibilityResult(state: SchemaCompatibilityState.incompatible, serverSchemaVersion: '1.1.0'),
      ),
      'Contrat serveur',
    ),
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
          chatClosed = false;
          await open(tester, screen.value.$1(), size.value, scale);
          expect(tester.takeException(), isNull);
          var found = find.textContaining(screen.value.$2).evaluate().isNotEmpty;
          final scrollables = find.byType(Scrollable);
          if (scrollables.evaluate().isNotEmpty) {
            for (var i = 0; i < 10; i++) {
              await tester.drag(scrollables.first, const Offset(0, -350), warnIfMissed: false);
              await tester.pump(const Duration(milliseconds: 50));
              expect(tester.takeException(), isNull);
              found = found || find.textContaining(screen.value.$2).evaluate().isNotEmpty;
            }
          }
          expect(found, isTrue, reason: '« ${screen.value.$2} » introuvable');
        });
      }
    }
  }

  testWidgets('mon solde : retirer ouvre la confirmation sans rien envoyer', (tester) async {
    await open(tester, const WalletScreen(), const Size(412, 915), 1);
    await tester.tap(find.text('Retirer mon solde'));
    await tester.pumpAndSettle();
    expect(find.text('Confirmer le retrait'), findsOneWidget);
    expect(find.text('3 versements validés'), findsOneWidget);
    await tester.tap(find.text('Annuler'));
    await tester.pumpAndSettle();
    expect(requests.where((r) => r.startsWith('POST')), isEmpty);
  });

  testWidgets('mon solde : l’icône ⓘ explique le solde', (tester) async {
    await open(tester, const WalletScreen(), const Size(412, 915), 1);
    await tester.tap(find.byTooltip('Comprendre mon solde'));
    await tester.pumpAndSettle();
    expect(find.text('J’ai compris'), findsOneWidget);
    expect(find.text('Paramètres de reversement'), findsOneWidget);
  });

  testWidgets('avis : une réponse vide n’est pas envoyée', (tester) async {
    await open(tester, const ReviewsScreen(), const Size(412, 915), 1);
    expect(find.textContaining('4,5', findRichText: true), findsOneWidget);
    await tester.scrollUntilVisible(find.text('Répondre'), 300, scrollable: find.byType(Scrollable).first);
    // Le bouton est ramené au-dessus de la barre d'onglets avant d'être touché.
    await tester.drag(find.byType(Scrollable).first, const Offset(0, -300));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Répondre'));
    await tester.pumpAndSettle();
    expect(find.text('Votre réponse'), findsWidgets);
    await tester.tap(find.text('Envoyer'));
    await tester.pump();
    expect(find.text('Écrivez votre réponse avant de l’envoyer.'), findsOneWidget);
    expect(requests.where((r) => r.startsWith('POST')), isEmpty);
  });

  testWidgets('discussion clôturée : la saisie est remplacée par une nouvelle conversation', (tester) async {
    chatClosed = true;
    await open(tester, const VendorAiChatScreen(), const Size(412, 915), 1);
    expect(find.text('Cette conversation est clôturée.'), findsOneWidget);
    expect(find.text('Nouvelle conversation'), findsOneWidget);
    expect(find.byType(TextField), findsNothing);
  });

  testWidgets('discussion : « Être rappelé » exige un numéro valide', (tester) async {
    chatClosed = false;
    await open(tester, const VendorAiChatScreen(), const Size(412, 915), 1);
    await tester.tap(find.byTooltip('Être rappelé'));
    await tester.pumpAndSettle();
    expect(find.text('Demander un rappel'), findsOneWidget);
    await tester.tap(find.text('Demander un rappel'));
    await tester.pump();
    expect(find.text('Le numéro doit contenir 10 chiffres.'), findsOneWidget);
    expect(requests.where((r) => r.contains('/callback')), isEmpty);
  });
}
