@Tags(['live'])
library;

import 'dart:io';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ovanie_vendor/app/theme.dart';
import 'package:ovanie_vendor/core/network/api_client.dart';
import 'package:ovanie_vendor/core/reference_data/reference_data_store.dart';
import 'package:ovanie_vendor/features/after_sales/after_sales_screen.dart';
import 'package:ovanie_vendor/features/dashboard/vendor_dashboard_screen.dart';
import 'package:ovanie_vendor/features/finance/finance_screen.dart';
import 'package:ovanie_vendor/features/finance/payout_detail_screen.dart';
import 'package:ovanie_vendor/features/finance/payout_settings_screen.dart';
import 'package:ovanie_vendor/features/finance/payouts_screen.dart';
import 'package:ovanie_vendor/features/finance/wallet_screen.dart';
import 'package:ovanie_vendor/features/menu/account_screens.dart';
import 'package:ovanie_vendor/features/menu/delivery_screens.dart';
import 'package:ovanie_vendor/features/menu/info_screens.dart';
import 'package:ovanie_vendor/features/menu/profile_screens.dart';
import 'package:ovanie_vendor/features/menu/reviews_screen.dart';
import 'package:ovanie_vendor/features/menu/statistics_screen.dart';
import 'package:ovanie_vendor/features/notifications/vendor_notifications_screen.dart';
import 'package:ovanie_vendor/features/orders/order_detail_screen.dart';
import 'package:ovanie_vendor/features/orders/order_tracking_screen.dart';
import 'package:ovanie_vendor/features/orders/orders_screen.dart';
import 'package:ovanie_vendor/features/products/product_detail_screen.dart';
import 'package:ovanie_vendor/features/products/products_screen.dart';
import 'package:ovanie_vendor/features/shell/vendor_menu_screen.dart';
import 'package:ovanie_vendor/features/support/vendor_ai_chat_screen.dart';
import 'package:ovanie_vendor/features/support/vendor_support_screen.dart';

/// Audit en conditions réelles : chaque écran est affiché avec les données du
/// serveur local (compte vendeur de test). Les textes affichés sont relevés et
/// tout ce qui ressemble à une donnée absente ou brute est signalé.
///
/// Lancement : flutter test test/live_audit_test.dart --tags live
///   --dart-define=OVANIE_API_BASE=http://127.0.0.1:8000/api
///   --dart-define=LIVE_OUT=dossier (contenant token.txt, reçoit le rapport)
void main() {
  const out = String.fromEnvironment('LIVE_OUT');
  final token = out.isEmpty ? '' : File('$out/token.txt').readAsStringSync().trim();
  final report = StringBuffer();

  setUpAll(() async {
    HttpOverrides.global = null; // laisse passer les vraies requêtes réseau
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
    ApiClient.setToken(token);
  });

  tearDownAll(() {
    if (out.isNotEmpty) File('$out/live_audit.txt').writeAsStringSync(report.toString());
  });

  final screens = <String, Widget Function()>{
    'accueil': () => VendorDashboardScreen(onSelectTab: (_) {}),
    'produits': () => const ProductsScreen(),
    'detail_produit': () => const ProductDetailScreen(productId: 507),
    'commandes': () => const OrdersScreen(),
    'detail_commande': () => const OrderDetailScreen(orderId: 213),
    'detail_commande_vendeur': () => const OrderDetailScreen(orderId: 206),
    'suivi_commande': () => const OrderTrackingScreen(orderId: 213),
    'finances': () => const FinanceScreen(),
    'mon_solde': () => const WalletScreen(),
    'versements': () => const PayoutsScreen(),
    'detail_versement': () => const PayoutDetailScreen(payoutId: 62),
    'parametres_reversement': () => const PayoutSettingsScreen(),
    'menu': () => VendorMenuScreen(onSelectTab: (_) {}),
    'profil_boutique': () => const ShopProfileScreen(),
    'mon_profil': () => const PersonalProfileScreen(),
    'livraison': () => const DeliverySettingsScreen(),
    'zones': () => const DeliveryZonesScreen(configuring: true),
    'retours': () => const AfterSalesScreen(),
    'detail_retour': () => const CaseDetailScreen(id: 86),
    'litiges': () => const AfterSalesScreen(disputes: true),
    'notifications': () => const VendorNotificationsScreen(),
    'avis': () => const ReviewsScreen(),
    'statistiques': () => const StatisticsScreen(),
    'assistance': () => const VendorSupportScreen(),
    'discussion': () => const VendorAiChatScreen(),
    'conditions': () => const TermsScreen(),
    'a_propos': () => const AboutScreen(version: '0.1.11'),
    'etat_boutique': () => const ShopStatusScreen(),
    'documents': () => const ShopDocumentsScreen(),
    'securite': () => const AccountSecurityScreen(),
    'mot_de_passe': () => const ChangePasswordScreen(),
  };

  // Textes qui trahissent une donnée absente, brute ou mal formatée.
  final suspicious = <RegExp>[
    RegExp(r'\bnull\b'),
    RegExp(r'\bundefined\b'),
    RegExp(r'NaN'),
    RegExp(r'Instance of'),
    RegExp(r'\{.*\}'),
    RegExp(r'\b[a-z]+_[a-z_]+\b'), // code serveur (post_delivery, mobile_money…)
    RegExp(r'\d+\.\d{3,}'), // nombre non arrondi
    RegExp(r'Ã|â€'), // texte mal encodé
  ];

  for (final entry in screens.entries) {
    testWidgets('écran réel : ${entry.key}', (tester) async {
      tester.view.physicalSize = const Size(360, int.fromEnvironment('LIVE_HEIGHT', defaultValue: 800) + 0.0) * 2;
      tester.view.devicePixelRatio = 2;
      addTearDown(tester.view.reset);
      final boundary = GlobalKey();
      final errors = <String>[];
      final previous = FlutterError.onError;
      FlutterError.onError = (details) => errors.add(details.exceptionAsString().split('\n').first);
      addTearDown(() => FlutterError.onError = previous);

      await tester.runAsync(() async {
        await OvanieReferenceDataStore.instance.warmUp();
      });
      await tester.pumpWidget(
        MaterialApp(
          theme: OvanieTheme.light,
          home: RepaintBoundary(key: boundary, child: Material(child: entry.value())),
        ),
      );
      // Laisse le temps aux vraies requêtes de revenir.
      for (var i = 0; i < 24; i++) {
        await tester.runAsync(() => Future<void>.delayed(const Duration(milliseconds: 500)));
        await tester.pump(const Duration(milliseconds: 100));
      }

      final texts = <String>{};
      final scrollables = find.byType(Scrollable);
      for (var page = 0; page < 14; page++) {
        for (final element in find.byType(Text, skipOffstage: false).evaluate()) {
          final widget = element.widget as Text;
          final value = widget.data ?? widget.textSpan?.toPlainText() ?? '';
          if (value.trim().isNotEmpty) texts.add(value.trim());
        }
        for (final element in find.byType(RichText, skipOffstage: false).evaluate()) {
          final value = (element.widget as RichText).text.toPlainText();
          if (value.trim().isNotEmpty) texts.add(value.trim());
        }
        if (scrollables.evaluate().isEmpty) break;
        await tester.drag(scrollables.first, const Offset(0, -500), warnIfMissed: false);
        await tester.runAsync(() => Future<void>.delayed(const Duration(milliseconds: 150)));
        await tester.pump(const Duration(milliseconds: 100));
      }

      final flagged = <String>[
        for (final text in texts)
          if (suspicious.any((rx) => rx.hasMatch(text)) || text == '—' || text.contains('— FCFA')) text,
      ];
      final dashes = texts.where((t) => t == '—' || t == '-').length;
      report
        ..writeln('### ${entry.key}')
        ..writeln('  textes affichés : ${texts.length} · tirets « — » : $dashes')
        ..writeln('  erreurs Flutter : ${errors.isEmpty ? 'aucune' : errors.toSet().join(' | ')}')
        ..writeln('  textes suspects : ${flagged.isEmpty ? 'aucun' : flagged.take(12).join(' ¦ ')}')
        ..writeln('  extrait : ${texts.take(28).join(' ¦ ')}')
        ..writeln();

      if (out.isNotEmpty) {
        await tester.runAsync(() async {
          await tester.drag(find.byType(Scrollable).first, const Offset(0, 8000), warnIfMissed: false);
        });
        await tester.pump(const Duration(milliseconds: 300));
        final image = await tester.runAsync(() async {
          final render = boundary.currentContext!.findRenderObject()! as RenderRepaintBoundary;
          final img = await render.toImage(pixelRatio: 1);
          final data = await img.toByteData(format: ui.ImageByteFormat.png);
          return data!.buffer.asUint8List();
        });
        File('$out/live_${entry.key}.png').writeAsBytesSync(image!);
      }
      await tester.pumpWidget(const SizedBox.shrink());
      await tester.pump(const Duration(milliseconds: 100));
    });
  }
}
