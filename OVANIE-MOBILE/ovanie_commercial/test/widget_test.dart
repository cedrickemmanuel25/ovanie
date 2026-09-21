import 'package:flutter_test/flutter_test.dart';
import 'package:ovanie_commercial/features/dashboard/models/dashboard_data.dart';

void main() {
  test('Le profil commercial utilise le prénom reçu du backend', () {
    final profile = CommercialProfile.fromJson({
      'name': 'Rita Kouassi',
      'first_name': 'Rita',
    });

    expect(profile.firstName, 'Rita');
    expect(profile.name, 'Rita Kouassi');
  });
}
