import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:ovanie_livreur/app/theme.dart';
import 'package:ovanie_livreur/features/auth/login_screen.dart';
import 'package:ovanie_livreur/features/auth/otp_screen.dart';
import 'package:ovanie_livreur/features/onboarding/phone_check_screen.dart';
import 'package:ovanie_livreur/shared/widgets/phone_format.dart';

void main() {
  setUp(() => GoogleFonts.config.allowRuntimeFetching = false);
  test('Phone formatting, country prefix, editing and separator deletion', () {
    const formatter = DriverPhoneFormatter();
    TextEditingValue edit(String text, [int? offset]) => TextEditingValue(
      text: text,
      selection: TextSelection.collapsed(offset: offset ?? text.length),
    );
    expect(formatDriverPhone('+225 0701020304'), '07 01 02 03 04');
    expect(normalizeDriverPhone('07 01 02 03 04'), '0701020304');
    final pasted = formatter.formatEditUpdate(
      edit(''),
      edit('+225 07 01 02 03 04'),
    );
    expect(pasted.text, '07 01 02 03 04');
    expect(pasted.selection.extentOffset, 14);
    final deleted = formatter.formatEditUpdate(
      edit('07 01', 3),
      edit('0701', 2),
    );
    expect(deleted.text, '00 1');
    expect(deleted.selection.extentOffset, 1);
    final middle = formatter.formatEditUpdate(
      edit('07 01', 4),
      edit('07 901', 4),
    );
    expect(middle.text, '07 90 1');
    expect(middle.selection.extentOffset, 4);
  });

  for (final screen in <Widget>[
    const LoginScreen(),
    const PhoneCheckScreen(),
    const OtpScreen(phone: '0701020304', expiresIn: 600),
  ]) {
    testWidgets('${screen.runtimeType}: banner stays visible with keyboard', (
      tester,
    ) async {
      tester.view.physicalSize = const Size(320, 568);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      addTearDown(tester.view.resetViewInsets);
      await tester.pumpWidget(
        MaterialApp(theme: OvanieTheme.light, home: screen),
      );
      await tester.pumpAndSettle();
      tester.view.viewInsets = const FakeViewPadding(bottom: 280);
      await tester.pumpAndSettle();
      final image = find.byType(Image);
      expect(image.hitTestable(), findsOneWidget);
      expect(tester.getBottomLeft(image).dy, lessThan(288));
      final input = find.byType(TextField);
      await tester.enterText(
        input,
        screen is OtpScreen ? '123456' : '0701020304',
      );
      await tester.pump();
      if (screen is! OtpScreen) {
        expect(
          tester.widget<TextField>(input).controller!.text,
          '07 01 02 03 04',
        );
      } else {
        expect(find.textContaining('10:00'), findsOneWidget);
        expect(find.text('07 01 02 03 04'), findsOneWidget);
        await tester.pump(const Duration(seconds: 2));
        expect(find.text('Expire dans 09:58'), findsOneWidget);
      }
      expect(tester.takeException(), isNull);
      await tester.pumpWidget(const SizedBox.shrink());
    });
  }
}
