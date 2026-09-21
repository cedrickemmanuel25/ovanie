import 'package:flutter/material.dart';
import 'package:dio/dio.dart';
import 'package:ovanie_vendor/core/network/api_client.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ovanie_vendor/features/onboarding/shop_onboarding_screen.dart';

void main() {
  testWidgets('ouverture boutique affiche uniquement l etape active', (tester) async {
    ApiClient.dio.interceptors.add(InterceptorsWrapper(onRequest: (request, handler) {
      handler.resolve(Response(requestOptions: request, statusCode: 200, data: <String, dynamic>{}));
    }));
    addTearDown(() => ApiClient.dio.interceptors.clear());
    await tester.pumpWidget(
      const MaterialApp(home: ShopOnboardingScreen()),
    );
    await tester.pumpAndSettle();

    expect(find.text('Informations personnelles'), findsOneWidget);
    expect(find.text('Informations boutique'), findsNothing);
    expect(find.text('Vérification d’identité'), findsNothing);
    expect(find.text('Paiement vendeur'), findsNothing);
    expect(find.text('Conditions vendeur'), findsNothing);
  });
}
