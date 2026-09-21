import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:ovanie_livreur/app/app.dart';
import 'package:ovanie_livreur/app/theme.dart';
import 'package:ovanie_livreur/shared/widgets/ovanie_widgets.dart';

void main() {
  setUp(() => GoogleFonts.config.allowRuntimeFetching = false);

  testWidgets('Login and registration navigation remain available', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(390, 844);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    await tester.pumpWidget(const OvanieLivreurApp());
    await tester.pumpAndSettle();
    expect(find.text('OVANIE'), findsOneWidget);
    expect(find.text('Se connecter'), findsOneWidget);
    final registration = find.textContaining('Premi');
    await tester.ensureVisible(registration);
    await tester.tap(registration);
    await tester.pumpAndSettle();
    expect(find.byType(OvaniePhoneField), findsOneWidget);
    expect(find.text('Continuer'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  for (final size in [const Size(320, 568), const Size(390, 844)]) {
    testWidgets('Wizard stays usable at $size with keyboard', (tester) async {
      tester.view.physicalSize = size;
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      var continued = false;
      await tester.pumpWidget(
        MaterialApp(
          theme: OvanieTheme.light,
          home: OvanieHeroScaffold(
            showBack: true,
            appBarTitle: 'Inscription',
            child: OvanieWizardBody(
              content: const [
                OvanieStepHeader(step: 2, total: 5, title: 'Zones'),
                SizedBox(height: 600),
                Text('Fin du formulaire'),
              ],
              footer: [
                OvaniePrimaryButton(
                  label: 'Continuer',
                  onPressed: () => continued = true,
                ),
              ],
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();
      expect(tester.takeException(), isNull);
      await tester.tap(find.text('Continuer'));
      expect(continued, isTrue);
      tester.view.viewInsets = const FakeViewPadding(bottom: 280);
      addTearDown(tester.view.resetViewInsets);
      await tester.pumpAndSettle();
      expect(tester.takeException(), isNull);
      await tester.drag(
        find.byType(SingleChildScrollView),
        const Offset(0, -800),
      );
      await tester.pumpAndSettle();
      expect(find.text('Fin du formulaire').hitTestable(), findsOneWidget);
    });
  }
}
