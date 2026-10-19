@Tags(['render'])
library;

import 'dart:convert';
import 'dart:io';
import 'dart:ui' as ui;

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ovanie_vendor/app/theme.dart';
import 'package:ovanie_vendor/core/network/api_client.dart';
import 'package:ovanie_vendor/core/ui/mk_dialogs.dart';
import 'package:ovanie_vendor/features/finance/wallet_screen.dart';
import 'package:ovanie_vendor/features/menu/info_screens.dart';
import 'package:ovanie_vendor/features/support/vendor_ai_chat_screen.dart';
import 'package:ovanie_vendor/features/support/vendor_support_screen.dart';

/// Rendu de la discussion, de l'assistance, du solde, des fenêtres de choix
/// et des pages d'information, pour les comparer aux maquettes.
///
/// flutter test test/screens_render_test.dart --tags render
///   --dart-define=RENDER_OUT=dossier --dart-define=ICON_FONT=chemin/materialicons-regular.otf
void main() {
  const out = String.fromEnvironment('RENDER_OUT');
  const iconFont = String.fromEnvironment('ICON_FONT');
  late Map<String, dynamic> finance;

  setUpAll(() async {
    final loader = FontLoader('PlusJakartaSans');
    for (final weight in <String>['Regular', 'Medium', 'SemiBold', 'Bold', 'ExtraBold']) {
      final bytes = File('assets/fonts/PlusJakartaSans-$weight.ttf').readAsBytesSync();
      loader.addFont(Future<ByteData>.value(ByteData.view(bytes.buffer)));
    }
    await loader.load();
    if (iconFont.isNotEmpty) {
      final icons = FontLoader('MaterialIcons')
        ..addFont(Future<ByteData>.value(ByteData.view(File(iconFont).readAsBytesSync().buffer)));
      await icons.load();
    }
    finance = jsonDecode(File('test/fixtures/finance.json').readAsStringSync()) as Map<String, dynamic>;
  });

  Future<void> shoot(
    WidgetTester tester,
    String name,
    Widget screen,
    Object Function(String path) payload, {
    Future<void> Function()? act,
    double height = 800,
  }) async {
    tester.view.physicalSize = Size(360, height) * 2;
    tester.view.devicePixelRatio = 2;
    addTearDown(tester.view.reset);
    ApiClient.dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) =>
            handler.resolve(Response(requestOptions: options, statusCode: 200, data: payload(options.path))),
      ),
    );
    addTearDown(() => ApiClient.dio.interceptors.clear());
    final boundary = GlobalKey();
    await tester.pumpWidget(
      MaterialApp(
        theme: OvanieTheme.light,
        builder: (context, child) => RepaintBoundary(key: boundary, child: child),
        home: Material(child: screen),
      ),
    );
    for (var i = 0; i < 5; i++) {
      await tester.pump(const Duration(milliseconds: 250));
    }
    if (act != null) await act();
    expect(tester.takeException(), isNull);
    if (out.isNotEmpty) {
      await tester.runAsync(() async {
        for (final element in find.byType(Image).evaluate()) {
          await precacheImage((element.widget as Image).image, element);
        }
      });
      await tester.pump(const Duration(milliseconds: 100));
      final bytes = await tester.runAsync(() async {
        final render = boundary.currentContext!.findRenderObject()! as RenderRepaintBoundary;
        final image = await render.toImage(pixelRatio: 2);
        final data = await image.toByteData(format: ui.ImageByteFormat.png);
        return data!.buffer.asUint8List();
      });
      File('$out/$name.png').writeAsBytesSync(bytes!);
    }
    await tester.pumpWidget(const SizedBox.shrink());
    await tester.pump(const Duration(seconds: 5));
  }

  final now = DateTime.now();
  String at(int minute) => DateTime(now.year, now.month, now.day, 10, minute).toIso8601String();
  Map<String, dynamic> chat({bool human = false, String status = 'open', bool advisor = false}) => {
    'status': status,
    'agent': {'name': 'N’Nan'},
    'requires_human': human,
    'ticket': advisor ? {'reference': 'SUP-2026-031', 'status': 'open'} : null,
    'messages': [
      {'sender': 'ai', 'body': 'Bonjour Jean, je suis N’Nan. Comment puis-je vous aider avec votre boutique aujourd’hui ?', 'created_at': at(14)},
      {'sender': 'customer', 'body': 'Mon versement VRS-20260829-031 est toujours en attente.', 'created_at': at(16)},
      {
        'sender': 'ai',
        'body': 'Ce versement VRS-20260829-031 est en attente de livraison : il sera libéré après confirmation de livraison.',
        'created_at': at(18),
      },
      if (advisor)
        {'sender': 'human', 'body': 'Bonjour Jean, je reprends votre dossier. Je vérifie le versement avec notre partenaire de paiement.', 'created_at': at(19)},
      {
        'sender': 'customer',
        'body': 'D’accord, merci.',
        'created_at': at(19),
        'attachments': advisor
            ? [
                {'name': 'capture_versement.jpg', 'mime': 'image/jpeg', 'path': 'support/chat/abc/attachments/4/0'},
              ]
            : <dynamic>[],
      },
    ],
  };
  final payouts = {
    'data': [
      {'id': 62, 'reference': 'VRS-20260829-031', 'amount': 420000, 'status': 'waiting_reception'},
    ],
    'meta': {'last_page': 1},
  };

  Object chatPayload(String path, Map<String, dynamic>? conversation) {
    if (path.endsWith('/support/chat/latest')) {
      return conversation == null ? {'exists': false} : {'exists': true, 'conversation_token': 'abc'};
    }
    if (path.endsWith('/support/chat/abc')) return conversation!;
    if (path.endsWith('/payouts')) return payouts;
    return <String, dynamic>{};
  }

  testWidgets('discussion : accueil', (tester) async {
    await shoot(tester, 'chat_accueil', const VendorAiChatScreen(), (p) => chatPayload(p, null));
  });
  testWidgets('discussion : dossier transmis', (tester) async {
    await shoot(tester, 'chat_dossier', const VendorAiChatScreen(), (p) => chatPayload(p, chat(human: true, advisor: true)), height: 900);
  });
  testWidgets('discussion : clôturée', (tester) async {
    await shoot(tester, 'chat_cloture', const VendorAiChatScreen(), (p) => chatPayload(p, chat(human: true, status: 'closed')));
  });
  testWidgets('discussion : rappel avant question', (tester) async {
    await shoot(tester, 'chat_rappel', const VendorAiChatScreen(), (p) => chatPayload(p, null), act: () async {
      await tester.tap(find.byTooltip('Être rappelé'));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 600));
    });
  });

  testWidgets('centre d’assistance', (tester) async {
    await shoot(
      tester,
      'assistance',
      const VendorSupportScreen(),
      (path) => path.endsWith('/shop')
          ? {
              'shop': {'name': 'Kouassi Matériaux BTP'},
            }
          : {
              'data': {
                'requester': {'name': 'Jean Kouassi'},
                'tickets': [
                  {'id': 31, 'reference': 'SUP-2026-031', 'subject': 'Assistance versement VRS-20260829-031', 'status': 'waiting_customer'},
                  {'id': 27, 'reference': 'SUP-2026-027', 'subject': 'Problème d’affichage des photos produit', 'status': 'in_progress'},
                  {'id': 19, 'reference': 'SUP-2026-019', 'subject': 'Changement de zone de livraison', 'status': 'resolved'},
                ],
              },
            },
      height: 1000,
    );
  });

  Map<String, dynamic> financeWith({required num ready, num requested = 0, bool rows = true}) {
    final data = jsonDecode(jsonEncode(finance['finance'])) as Map<String, dynamic>;
    (data['summary'] as Map)['ready'] = ready;
    (data['summary'] as Map)['withdrawal_requested'] = requested;
    if (!rows) data['recent_payouts'] = <dynamic>[];
    return data;
  }

  for (final (name, ready, requested, account, rows) in [
    ('solde_sans_compte', 1250000, 0, false, true),
    ('solde_retrait', 1250000, 0, true, true),
    ('solde_demande', 1250000, 1250000, true, true),
    ('solde_zero', 0, 0, true, false),
  ]) {
    testWidgets('mon solde : $name', (tester) async {
      await shoot(
        tester,
        name,
        const WalletScreen(),
        (path) => path.endsWith('/payment-settings')
            ? (account ? finance['settings'] as Object : <String, dynamic>{})
            : financeWith(ready: ready, requested: requested, rows: rows),
        height: 900,
      );
    });
  }

  testWidgets('conditions d’utilisation', (tester) async {
    await shoot(
      tester,
      'conditions',
      const TermsScreen(),
      (_) => {
        'title': 'Conditions générales d’utilisation',
        'intro': 'Les présentes CGU encadrent l’accès et l’utilisation de la plateforme OVANIE ainsi que des services associés.',
        'contact': {'phone_display': '01 61 78 00 00', 'email': 'contact@ovanie.com'},
        'sections': [
          for (final (i, title) in [
            'Objet',
            'Rôle d’OVANIE',
            'Compte utilisateur',
            'Produits, prix et disponibilité',
            'Commande',
            'Paiement',
            'Livraison',
            'Annulation',
            'Retours et remboursements',
            'Garanties',
            'Cartes et bons OVANIE',
            'Vendeurs',
            'Litiges et assistance',
            'Responsabilités',
            'Propriété intellectuelle',
            'Données personnelles',
            'Modification, droit applicable',
          ].indexed)
            {
              'number': i + 1,
              'title': title,
              'text': i == 12
                  ? 'Toute réclamation est adressée à l’assistance OVANIE par le formulaire de contact, par téléphone au 01 61 78 00 00 ou par e-mail à contact@ovanie.com, en indiquant le numéro de commande.'
                  : 'Les vendeurs référencés s’engagent à publier des informations exactes, à respecter les prix, quantités et délais annoncés, et à fournir des produits conformes.',
            },
        ],
      },
      height: 1900,
      act: () async {
        await tester.tap(find.text('Litiges et assistance'));
        await tester.pump(const Duration(milliseconds: 300));
      },
    );
  });

  testWidgets('à propos', (tester) async {
    await shoot(tester, 'a_propos', const AboutScreen(version: '0.1.11'), (_) => <String, dynamic>{}, height: 2500);
  });

  for (final (name, boxed) in [('choix_cartes', true), ('choix_liste', false)]) {
    testWidgets('fenêtre de choix : $name', (tester) async {
      await shoot(
        tester,
        name,
        Builder(
          builder: (context) => Center(
            child: TextButton(
              onPressed: () => boxed
                  ? showMkChoiceSheet<String>(
                      context,
                      title: 'Délai de livraison',
                      subtitle: 'Sélectionnez le délai nécessaire pour livrer cette commune.',
                      items: const ['24 h', '48 h', '72 h', '3 à 5 jours', '7 jours'],
                      label: (v) => v,
                      selected: '24 h',
                      boxed: true,
                      closeButton: false,
                      icon: Icons.schedule_rounded,
                    )
                  : showMkChoiceSheet<String>(
                      context,
                      title: 'Quartier',
                      subtitle: 'Cocody',
                      items: const ['Angré', 'Riviera 2', 'Riviera 3', 'Deux-Plateaux', 'Blockhaus', 'II Plateaux Vallons', 'Bonoumin'],
                      label: (v) => v,
                      selected: 'Angré',
                      searchable: true,
                      icon: Icons.location_on_outlined,
                    ),
              child: const Text('Ouvrir'),
            ),
          ),
        ),
        (_) => <String, dynamic>{},
        act: () async {
          await tester.tap(find.text('Ouvrir'));
          await tester.pump(const Duration(milliseconds: 600));
        },
      );
    });
  }
}
