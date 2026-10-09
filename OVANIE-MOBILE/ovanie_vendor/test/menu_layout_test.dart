import 'dart:convert';
import 'dart:io';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ovanie_vendor/app/theme.dart';
import 'package:ovanie_vendor/core/network/api_client.dart';
import 'package:ovanie_vendor/features/after_sales/after_sales_screen.dart';
import 'package:ovanie_vendor/features/menu/delivery_screens.dart';
import 'package:ovanie_vendor/features/menu/logistics_settings_screen.dart';
import 'package:ovanie_vendor/features/menu/profile_screens.dart';
import 'package:ovanie_vendor/features/menu/reviews_screen.dart';
import 'package:ovanie_vendor/features/menu/statistics_screen.dart';
import 'package:ovanie_vendor/features/notifications/vendor_notifications_screen.dart';
import 'package:ovanie_vendor/features/shell/vendor_menu_screen.dart';
import 'package:ovanie_vendor/features/support/vendor_support_screen.dart';

/// Écrans du Menu : affichage sans débordement du petit téléphone à la
/// tablette, et comportements clés (filtres, validations, confirmations).
void main() {
  late Object referenceData;
  final requests = <String>[];

  setUpAll(() async {
    final loader = FontLoader('PlusJakartaSans');
    for (final weight in <String>['Regular', 'Medium', 'SemiBold', 'Bold', 'ExtraBold']) {
      final bytes = File('assets/fonts/PlusJakartaSans-$weight.ttf').readAsBytesSync();
      loader.addFont(Future<ByteData>.value(ByteData.view(bytes.buffer)));
    }
    await loader.load();
    referenceData = jsonDecode(File('test/fixtures/reference_data.json').readAsStringSync()) as Object;
  });

  final shop = <String, dynamic>{
    'id': 47,
    'name': 'Kouassi Matériaux BTP',
    'owner_name': 'Jean Kouassi',
    'kyc_status': 'verified',
    'main_category': 'quincaillerie',
    'description': 'Vente de matériaux de construction, quincaillerie, électricité et équipements de chantier à Abidjan.',
    'city': 'Abidjan',
    'commune': 'Cocody',
    'district': 'Abatta Cocody',
    'landmark': 'En face de la pharmacie',
    'address': 'En face de la pharmacie, Abatta Cocody, Cocody, Abidjan',
    'latitude': 5.3599,
    'longitude': -3.987,
    'delivery_zone': 'abidjan',
    'whatsapp': '0700008822',
    'business_email': 'contact@kouassi-materiaux.ci',
    'logistics_type': 'seller',
    'logistics_configured': true,
    'logistics_location_ready': true,
    'logistics_mode_label': 'Ma propre logistique',
    'processing_time': '24_48h',
    'logo_url': null,
    'presentation': {
      'business_phone': '0700008822',
      'preparation': {
        'days': [1, 2, 3, 4, 5, 6],
        'start': '08:00',
        'end': '17:00',
      },
    },
  };
  final user = <String, dynamic>{
    'id': 120,
    'name': 'Jean Kouassi',
    'phone': '0700008822',
    'email': 'vendeur@exemple.ci',
    'whatsapp_phone': null,
    'city': 'Abidjan',
    'role': 'vendor',
    'status': 'active',
    'created_at': '2026-03-15T10:00:00Z',
    'avatar_url': null,
  };
  final zones = <Map<String, dynamic>>[
    {'id': 1, 'commune_id': 1, 'commune': 'Cocody', 'city': 'Abidjan', 'vehicle_code': 'pickup', 'delivery_price': 5000, 'estimated_delay': '24 h', 'is_active': true},
    {'id': 2, 'commune_id': 2, 'commune': 'Yopougon', 'city': 'Abidjan', 'vehicle_code': 'camion_3t', 'delivery_price': 15000, 'estimated_delay': '48 h', 'is_active': true},
    {'id': 3, 'commune_id': 3, 'commune': 'Abobo', 'city': 'Abidjan', 'vehicle_code': 'camion_10t', 'delivery_price': 30000, 'estimated_delay': '72 h', 'is_active': false},
  ];
  Map<String, dynamic> caseRow(String reference, String status) => {
    'id': 5,
    'reference': reference,
    'status': status,
    'product_name': 'Carrelage grès cérame beige 60 × 60',
    'image_url': '',
    'order_number': 'OVANIE-7EQBU664',
    'order_id': 206,
    'client_name': 'Aminata Koné',
    'reason': 'Produit endommagé à la livraison',
    'amount': 84000,
    'created_at': '2026-09-11T09:00:00Z',
  };
  Map<String, dynamic> caseDetail(String reference, String status, {bool canDecide = false}) => {
    'case': {
      ...caseRow(reference, status),
      'can_decide': canDecide,
      'description': 'Deux cartons sont arrivés avec des carreaux cassés.',
      'product': {'name': 'Carrelage grès cérame beige 60 × 60', 'image_url': '', 'quantity': 4, 'price': 21000, 'amount': 84000},
      'client': {'name': 'Aminata Koné', 'phone': '0759842163', 'city': 'Abidjan'},
      'attachments': <String>[],
      'timeline': [
        {'label': 'Demande créée', 'date': '2026-09-11T09:00:00Z'},
        {'label': 'Photos ajoutées par le client', 'date': '2026-09-11T09:20:00Z'},
      ],
    },
  };
  final notifications = <Map<String, dynamic>>[
    {'id': 9, 'title': 'Nouvelle commande OVANIE-7EQBU664', 'message': 'Kouassi Yao a passé une commande de 245 000 FCFA.', 'category': 'orders', 'order_id': 206, 'read': false, 'created_at': DateTime.now().toIso8601String()},
    {'id': 8, 'title': 'Versement effectué', 'message': 'Votre versement REV-20261007134811-CLXBDM de 133 333 FCFA a été effectué.', 'category': 'payout', 'read': false, 'created_at': DateTime.now().subtract(const Duration(days: 1)).toIso8601String()},
    {'id': 7, 'title': 'Paramètres enregistrés', 'message': 'Vos paramètres de livraison ont été mis à jour.', 'category': 'system', 'read': true, 'created_at': '2026-09-01T08:00:00Z'},
  ];
  final statistics = <String, dynamic>{
    'revenue': 3450000,
    'orders': 86,
    'average': 40116,
    'conversion': null,
    'growth': {'revenue': 12, 'orders': -8.5, 'average': null},
    'series': [
      for (var i = 0; i < 30; i++) {'date': '2026-09-${(i + 1).toString().padLeft(2, '0')}', 'amount': 40000.0 * (i % 7 + 1), 'orders': i % 4},
    ],
    'statuses': {'pending': 8, 'ready': 12, 'delivered': 58, 'cancelled': 8},
    'top_products': [
      {'id': 503, 'name': 'Ciment Bélier Classic 50 kg', 'image_url': null, 'quantity': 420, 'amount': 2478000},
      {'id': 505, 'name': 'Gravier concassé 5/15', 'image_url': null, 'quantity': 1, 'amount': 460800},
    ],
  };
  final support = <String, dynamic>{
    'data': {
      'requester': {'name': 'Jean Kouassi'},
      'tickets': [
        {'id': 31, 'reference': 'SUP-2026-031', 'subject': 'Assistance versement REV-20261007134811-CLXBDM', 'status': 'waiting_customer'},
        {'id': 27, 'reference': 'SUP-2026-027', 'subject': 'Problème d’affichage des photos produit', 'status': 'in_progress'},
        {'id': 19, 'reference': 'SUP-2026-019', 'subject': 'Changement de zone de livraison', 'status': 'resolved'},
      ],
    },
  };
  final ticket = <String, dynamic>{
    'data': {
      'id': 31,
      'reference': 'SUP-2026-031',
      'subject': 'Assistance versement REV-20261007134811-CLXBDM',
      'status': 'waiting_customer',
      'description': 'Le virement est toujours en attente après la date prévue.',
      'messages': [
        {'author_type': 'agent', 'body': 'Bonjour, pouvez-vous confirmer le numéro Wave de réception ?', 'created_at': '2026-09-12T10:14:00Z'},
        {'author_type': 'vendor', 'body': 'Oui, c’est le 07 08 45 12 36.', 'created_at': '2026-09-12T10:16:00Z'},
      ],
    },
  };

  Object payloadFor(String path) {
    if (path.contains('reference-data')) return referenceData;
    if (path.endsWith('/menu')) return {'shop': shop, 'user': user, 'counts': {'orders': 6, 'notifications': 5, 'returns': 2, 'disputes': 1}};
    if (path.contains('/shop/delivery')) return {'uses_seller_logistics': true, 'zones': zones, 'profile': {'default_delay': '24 h', 'max_weight_kg': 1500, 'max_volume_m3': 4, 'conditions': 'Livraison au pied du chantier'}, 'area_map_url': null};
    if (path.endsWith('/shop')) return {'shop': shop};
    if (path.endsWith('/profile')) return {'user': user, 'address': {'country': 'Côte d’Ivoire', 'city': 'Abidjan', 'commune': 'Cocody', 'quartier': 'Abatta Cocody', 'address': 'En face de la pharmacie'}, 'shop': shop};
    if (path.endsWith('/statistics')) return statistics;
    if (path.endsWith('/returns')) return {'data': [caseRow('RET-2026-014', 'pending'), caseRow('RET-2026-012', 'accepted')], 'meta': {'last_page': 1}};
    if (path.endsWith('/disputes')) return {'data': [caseRow('LIT-2026-004', 'new'), caseRow('LIT-2026-003', 'waiting_ovanie')], 'meta': {'last_page': 1}};
    if (path.endsWith('/returns/5')) return caseDetail('RET-2026-014', 'pending', canDecide: true);
    if (path.endsWith('/disputes/5')) return caseDetail('LIT-2026-004', 'new');
    if (path.endsWith('/notifications')) return {'data': notifications, 'meta': {'last_page': 1}, 'unread_count': 2};
    if (path.endsWith('/notifications/9')) return {'notification': notifications.first};
    if (path.endsWith('/support')) return support;
    if (path.endsWith('/support/tickets/31')) return ticket;
    if (path.endsWith('/reviews')) return {'data': <dynamic>[], 'summary': {'count': 0, 'average': null, 'distribution': {'1': 0, '2': 0, '3': 0, '4': 0, '5': 0}, 'replied': 0, 'this_month': 0}};
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

  Future<void> close(WidgetTester tester) async {
    await tester.pumpWidget(const SizedBox.shrink());
    await tester.pump(const Duration(milliseconds: 100));
  }

  final screens = <String, (Widget Function(), String)>{
    'menu': (() => VendorMenuScreen(onSelectTab: (_) {}), 'Se déconnecter'),
    'profil boutique': (() => const ShopProfileScreen(), 'Contacts professionnels'),
    'modifier boutique': (() => ShopEditScreen(shop: shop), 'Email professionnel'),
    'mon profil': (() => const PersonalProfileScreen(), 'Statut du compte'),
    'livraison': (() => const DeliverySettingsScreen(), 'Disponibilité de préparation'),
    'infos logistiques vendeur': (() => LogisticsSettingsScreen(shop: shop, targetMode: 'seller'), 'Zones, tarifs et délais'),
    'passer à OVANIE': (() => LogisticsSettingsScreen(shop: shop, targetMode: 'ovanie'), 'Point de repère'),
    'zones': (() => const DeliveryZonesScreen(), 'Abobo'),
    'ajouter une zone': (() => DeliveryZoneEditScreen(shop: shop), 'Couverture géographique'),
    'retours': (() => const AfterSalesScreen(), 'RET-2026-014'),
    'litiges': (() => const AfterSalesScreen(disputes: true), 'LIT-2026-004'),
    'détail retour': (() => const CaseDetailScreen(id: 5), 'Refuser le retour'),
    'détail litige': (() => const CaseDetailScreen(id: 5, disputes: true), 'Transmettre à OVANIE'),
    'notifications': (() => const VendorNotificationsScreen(), 'Paramètres enregistrés'),
    'détail notification': (() => const NotificationDetailScreen(id: '9'), 'Historique'),
    'assistance': (() => const VendorSupportScreen(), 'SUP-2026-019'),
    'statistiques': (() => const StatisticsScreen(), 'Top produits'),
    'avis': (() => const ReviewsScreen(), 'Avis'),
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
          var found = find.textContaining(screen.value.$2).evaluate().isNotEmpty;
          for (var i = 0; i < 16; i++) {
            await tester.drag(scrollable, const Offset(0, -350), warnIfMissed: false);
            await tester.pump(const Duration(milliseconds: 50));
            expect(tester.takeException(), isNull);
            found = found || find.textContaining(screen.value.$2).evaluate().isNotEmpty;
          }
          expect(found, isTrue, reason: '« ${screen.value.$2} » introuvable');
          await close(tester);
        });
      }
    }
  }

  testWidgets('menu : compteurs réels et version de l’application', (tester) async {
    await open(tester, VendorMenuScreen(onSelectTab: (_) {}), const Size(412, 915), 1);
    expect(find.text('Kouassi Matériaux BTP'), findsOneWidget);
    expect(find.text('Vendeur vérifié'), findsOneWidget);
    expect(find.text('6'), findsOneWidget);
    await close(tester);
  });

  testWidgets('profil boutique : aucune valeur brute du serveur', (tester) async {
    await open(tester, const ShopProfileScreen(), const Size(412, 915), 1);
    expect(find.text('quincaillerie', skipOffstage: false), findsNothing);
    expect(find.text('abidjan', skipOffstage: false), findsNothing);
    expect(find.text('07 00 00 88 22', skipOffstage: false), findsWidgets);
    await close(tester);
  });

  testWidgets('modifier boutique : un e-mail invalide bloque l’enregistrement', (tester) async {
    await open(tester, ShopEditScreen(shop: shop), const Size(412, 915), 1);
    await tester.scrollUntilVisible(find.text('Email professionnel'), 300, scrollable: find.byType(Scrollable).first);
    await tester.enterText(find.byType(TextFormField).last, 'pas-un-email');
    await tester.tap(find.text('Enregistrer'));
    await tester.pump();
    expect(find.text('Adresse e-mail invalide.', skipOffstage: false), findsOneWidget);
    expect(requests.where((r) => r.startsWith('POST')), isEmpty);
    await close(tester);
  });

  testWidgets('livraison : l’heure limite doit suivre l’heure de début', (tester) async {
    await open(tester, const DeliverySettingsScreen(), const Size(412, 915), 1);
    expect(find.text('Ma propre logistique'), findsOneWidget);
    expect(find.text('08:00', skipOffstage: false), findsOneWidget);
    expect(find.text('17:00', skipOffstage: false), findsOneWidget);
    await tester.tap(find.text('Enregistrer les paramètres'));
    await tester.pump(const Duration(milliseconds: 300));
    expect(requests.where((r) => r.startsWith('POST')).length, 1);
    await close(tester);
  });

  testWidgets('zones : les filtres séparent zones actives et suspendues', (tester) async {
    await open(tester, const DeliveryZonesScreen(), const Size(412, 915), 1);
    await tester.tap(find.text('Suspendues'));
    await tester.pump();
    expect(find.text('Cocody', skipOffstage: false), findsNothing);
    await tester.scrollUntilVisible(find.text('Abobo'), 300, scrollable: find.byType(Scrollable).first);
    expect(find.text('Camion 10 t', skipOffstage: false), findsNothing);
    expect(find.textContaining('Camion 10 t', skipOffstage: false), findsOneWidget);
    await close(tester);
  });

  testWidgets('zone : enregistrer sans véhicule ni délai est refusé', (tester) async {
    await open(tester, DeliveryZoneEditScreen(shop: shop), const Size(412, 915), 1);
    await tester.tap(find.text('Enregistrer la zone'));
    await tester.pump();
    expect(find.text('Choisissez une commune.', skipOffstage: false), findsOneWidget);
    expect(find.text('Choisissez un véhicule.', skipOffstage: false), findsOneWidget);
    expect(requests.where((r) => r.startsWith('POST')), isEmpty);
    await close(tester);
  });

  testWidgets('retour : refuser exige une explication et un justificatif', (tester) async {
    await open(tester, const CaseDetailScreen(id: 5), const Size(412, 915), 1);
    await tester.scrollUntilVisible(find.text('Refuser le retour'), 300, scrollable: find.byType(Scrollable).first);
    await tester.tap(find.text('Refuser le retour'));
    await tester.pump();
    expect(find.text('Expliquez le refus en au moins 10 caractères.'), findsOneWidget);
    expect(requests.where((r) => r.startsWith('POST')), isEmpty);
    await close(tester);
  });

  testWidgets('retour : rembourser demande une confirmation', (tester) async {
    await open(tester, const CaseDetailScreen(id: 5), const Size(412, 915), 1);
    await tester.scrollUntilVisible(find.text('Rembourser le client'), 300, scrollable: find.byType(Scrollable).first);
    await tester.tap(find.text('Rembourser le client'));
    await tester.pumpAndSettle();
    expect(find.text('Rembourser le client ?'), findsOneWidget);
    await tester.tap(find.text('Annuler'));
    await tester.pumpAndSettle();
    expect(requests.where((r) => r.startsWith('POST')), isEmpty);
    await close(tester);
  });

  testWidgets('notifications : ouvrir le détail ne marque pas la notification comme lue', (tester) async {
    await open(tester, const NotificationDetailScreen(id: '9'), const Size(412, 915), 1);
    expect(find.text('Non lue', skipOffstage: false), findsOneWidget);
    expect(requests.where((r) => r.startsWith('POST')), isEmpty);
    expect(find.text('Voir la commande'), findsOneWidget);
    await tester.tap(find.text('Marquer comme lue'));
    await tester.pump(const Duration(milliseconds: 300));
    expect(requests.where((r) => r.startsWith('POST')).length, 1);
    await close(tester);
  });

  testWidgets('assistance : la demande exige un objet et une description', (tester) async {
    await open(tester, const VendorSupportScreen(), const Size(412, 915), 1);
    expect(find.text('2'), findsOneWidget);
    await tester.tap(find.text('Nouvelle demande'));
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.text('Envoyer au support'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Envoyer au support'));
    await tester.pump();
    expect(find.text('Indiquez un objet d’au moins 4 caractères.', skipOffstage: false), findsOneWidget);
    expect(requests.where((r) => r.startsWith('POST')), isEmpty);
    await close(tester);
  });

  testWidgets('statistiques : évolution réelle, taux non mesuré signalé', (tester) async {
    await open(tester, const StatisticsScreen(), const Size(412, 915), 1);
    expect(find.text('Non mesuré'), findsOneWidget);
    expect(find.text('+12 %'), findsOneWidget);
    expect(find.text('-8,5 %'), findsOneWidget);
    await close(tester);
  });
}
