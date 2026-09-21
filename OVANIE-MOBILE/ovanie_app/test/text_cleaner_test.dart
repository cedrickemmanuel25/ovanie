import 'package:flutter_test/flutter_test.dart';
import 'package:ovanie_app/core/utils/text_cleaner.dart';

void main() {
  test('corrige les caractères perdus des noms produits', () {
    expect(
      cleanOvanieText('Pack WC ?? poser en c??ramique avec r??servoir'),
      'Pack WC à poser en céramique avec réservoir',
    );
    expect(
      cleanOvanieText('Brique creuse en ciment 15 ?? 20 ?? 40 cm'),
      'Brique creuse en ciment 15 × 20 × 40 cm',
    );
    expect(
      cleanOvanieText('Carrelage gr??s c??rame 60 ?? 60 cm ??? finition mate'),
      'Carrelage grès cérame 60 × 60 cm – finition mate',
    );
  });
}
