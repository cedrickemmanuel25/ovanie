import 'package:flutter_test/flutter_test.dart';
import 'package:ovanie_app/app/app.dart';

void main() {
  testWidgets('OVANIE application démarre', (WidgetTester tester) async {
    await tester.pumpWidget(const OvanieApp());
    expect(find.byType(OvanieApp), findsOneWidget);
  });
}
