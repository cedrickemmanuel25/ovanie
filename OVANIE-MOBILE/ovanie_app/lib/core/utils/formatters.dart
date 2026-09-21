import 'package:flutter/services.dart';

String formatFcfa(num value) {
  final digits = value.round().toString();
  final buffer = StringBuffer();

  for (var i = 0; i < digits.length; i++) {
    if (i > 0 && (digits.length - i) % 3 == 0) {
      buffer.write(' ');
    }
    buffer.write(digits[i]);
  }

  return '${buffer.toString()} FCFA';
}

/// Retourne les 10 chiffres locaux ivoiriens quand ils sont disponibles.
String ciLocalPhoneDigits(String raw) {
  var digits = raw.replaceAll(RegExp(r'\D+'), '');
  if (digits.startsWith('225') && digits.length > 10) {
    digits = digits.substring(3);
  }
  if (digits.length > 10) {
    digits = digits.substring(digits.length - 10);
  }
  return digits;
}

String _groupPairs(String digits) {
  if (digits.isEmpty) return '';
  final parts = <String>[];
  for (var i = 0; i < digits.length; i += 2) {
    final end = i + 2 < digits.length ? i + 2 : digits.length;
    parts.add(digits.substring(i, end));
  }
  return parts.join(' ');
}

/// Affichage professionnel des numéros ivoiriens : 07 04 74 97 85.
/// Si le numéro source contient +225, il est conservé à l'affichage.
String formatCiPhoneDisplay(String raw, {bool includeCountryCode = true}) {
  final originalDigits = raw.replaceAll(RegExp(r'\D+'), '');
  final local = ciLocalPhoneDigits(raw);
  if (local.isEmpty) return raw.trim();
  final grouped = _groupPairs(local);
  final hadCountryCode = originalDigits.startsWith('225') && originalDigits.length > 10;
  return includeCountryCode && hadCountryCode ? '+225 $grouped' : grouped;
}

/// Format envoyé à Laravel pour les téléphones ivoiriens.
String normalizeCiPhoneForApi(String raw) {
  final local = ciLocalPhoneDigits(raw);
  if (local.length == 10) return '+225$local';
  final digits = raw.replaceAll(RegExp(r'\D+'), '');
  if (digits.isEmpty) return raw.trim();
  return raw.trim().startsWith('+') ? '+$digits' : digits;
}

/// Formateur de saisie : regroupe automatiquement les chiffres par paires.
/// [withCountryCode] est utilisé pour les champs qui n'ont pas de préfixe +225.
class CiPhoneInputFormatter extends TextInputFormatter {
  final bool withCountryCode;

  const CiPhoneInputFormatter({this.withCountryCode = false});

  @override
  TextEditingValue formatEditUpdate(
    TextEditingValue oldValue,
    TextEditingValue newValue,
  ) {
    var digits = newValue.text.replaceAll(RegExp(r'\D+'), '');
    if (withCountryCode && digits.startsWith('225')) {
      digits = digits.substring(3);
    }
    if (digits.length > 10) digits = digits.substring(0, 10);

    final grouped = _groupPairs(digits);
    final text = withCountryCode && grouped.isNotEmpty ? '+225 $grouped' : grouped;
    return TextEditingValue(
      text: text,
      selection: TextSelection.collapsed(offset: text.length),
    );
  }
}
