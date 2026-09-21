import 'dart:io';
import 'dart:ui' as ui;
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:ovanie_livreur/app/theme.dart';
import 'package:ovanie_livreur/core/network/api_client.dart';
import 'package:ovanie_livreur/features/auth/login_screen.dart';
import 'package:ovanie_livreur/features/auth/otp_screen.dart';
import 'package:ovanie_livreur/features/home/home_screen.dart';
import 'package:ovanie_livreur/features/onboarding/availability_screen.dart';
import 'package:ovanie_livreur/features/onboarding/confirmation_screen.dart';
import 'package:ovanie_livreur/features/onboarding/onboarding_data.dart';
import 'package:ovanie_livreur/features/onboarding/personal_info_screen.dart';
import 'package:ovanie_livreur/features/onboarding/phone_check_screen.dart';
import 'package:ovanie_livreur/features/onboarding/vehicle_screen.dart';
import 'package:ovanie_livreur/features/onboarding/zones_screen.dart';

void main() {
  setUp(() {
    GoogleFonts.config.allowRuntimeFetching = false;
    ApiClient.dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) {
          handler.resolve(
            Response(
              requestOptions: options,
              statusCode: 200,
              data: options.path.contains('communes')
                  ? {
                      'communes': [
                        for (var i = 0; i < kAbidjanCommunes.length; i++)
                          {'id': i + 1, 'name': kAbidjanCommunes[i]},
                      ],
                    }
                  : {
                      'id': 1,
                      'first_name': 'Amadou',
                      'last_name': 'Koné',
                      'phone': '0700000000',
                      'onboarding_status': 'pending_review',
                    },
            ),
          );
        },
      ),
    );
  });
  tearDown(() => ApiClient.dio.interceptors.clear());

  for (final size in [const Size(320, 568), const Size(390, 844)]) {
    testWidgets('Nine screens render and selections persist at $size', (
      tester,
    ) async {
      tester.view.physicalSize = size;
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      final data = OnboardingData()
        ..firstName = 'Amadou'
        ..lastName = 'Koné'
        ..phone = '0700000000'
        ..vehicleType = 'Moto'
        ..plateNumber = 'AB 1234 CI';
      final capture =
          Platform.environment['OVANIE_CAPTURE'] == '1' && size.width == 390;
      if (capture) {
        // A local font is used only for review captures, never shipped in the app.
        await tester.runAsync(() async {
          final bytes = await File(
            Platform.environment['OVANIE_PREVIEW_FONT']!,
          ).readAsBytes();
          for (final family in [
            'Roboto',
            'Ahem',
            'PlusJakartaSans',
            for (var weight = 100; weight <= 900; weight += 100)
              weight == 400
                  ? 'PlusJakartaSans_regular'
                  : 'PlusJakartaSans_$weight',
          ]) {
            await (FontLoader(
              family,
            )..addFont(Future.value(ByteData.sublistView(bytes)))).load();
          }
          await (FontLoader('MaterialIcons')
                ..addFont(rootBundle.load('fonts/MaterialIcons-Regular.otf')))
              .load();
        });
      }
      var screenIndex = 0;
      Future<void> show(Widget screen) async {
        await tester.pumpWidget(
          RepaintBoundary(
            key: const ValueKey('capture'),
            child: MaterialApp(
              debugShowCheckedModeBanner: false,
              theme: OvanieTheme.light,
              home: screen,
            ),
          ),
        );
        await tester.pumpAndSettle();
        expect(tester.takeException(), isNull);
        screenIndex++;
        if (capture) {
          await tester.runAsync(() async {
            final boundary = tester.renderObject<RenderRepaintBoundary>(
              find.byKey(const ValueKey('capture')),
            );
            final image = await boundary.toImage(pixelRatio: 2);
            final bytes = await image.toByteData(
              format: ui.ImageByteFormat.png,
            );
            final directory = Directory('design/previews')
              ..createSync(recursive: true);
            await File(
              '${directory.path}/$screenIndex-${screen.runtimeType}.png',
            ).writeAsBytes(bytes!.buffer.asUint8List());
            image.dispose();
          });
        }
      }

      await show(const LoginScreen());
      await show(const OtpScreen(phone: '0700000000', expiresIn: 600));
      await show(const PhoneCheckScreen());
      await show(PersonalInfoScreen(data: data));
      await show(ZonesScreen(data: data));
      await tester.ensureVisible(find.text('Cocody'));
      await tester.tap(find.text('Cocody'));
      await tester.pumpAndSettle();
      expect(data.zones.values, contains('Cocody'));
      await show(AvailabilityScreen(data: data));
      await tester.ensureVisible(find.text('Lundi'));
      await tester.tap(find.text('Lundi'));
      await tester.pumpAndSettle();
      expect(data.availabilities, contains('Lundi'));
      await tester.ensureVisible(find.text('Week-end'));
      await tester.tap(find.text('Week-end'));
      await tester.pumpAndSettle();
      expect(data.availabilities, unorderedEquals(['Samedi', 'Dimanche']));
      await tester.ensureVisible(find.text('En semaine'));
      await tester.tap(find.text('En semaine'));
      await tester.pumpAndSettle();
      expect(data.availabilities, unorderedEquals(kWeekDays.take(5)));
      await tester.ensureVisible(find.text('Tous les jours'));
      await tester.tap(find.text('Tous les jours'));
      await tester.pumpAndSettle();
      expect(data.availabilities, unorderedEquals(kWeekDays));
      await show(VehicleScreen(data: data));
      await tester.ensureVisible(find.text('Tricycle'));
      await tester.tap(find.text('Tricycle'));
      await tester.pumpAndSettle();
      expect(data.vehicleType, 'Tricycle');
      await show(ConfirmationScreen(data: data));
      await tester.tap(find.text('Envoyer mon dossier'));
      await tester.pumpAndSettle();
      expect(find.text('Dossier en vérification'), findsOneWidget);
      expect(tester.takeException(), isNull);
      await show(const HomeScreen());
      expect(find.text('Vérification en cours'), findsOneWidget);
    });
  }
}
