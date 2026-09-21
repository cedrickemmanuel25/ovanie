import 'package:flutter/services.dart';

String normalizeDriverPhone(String value) {
  final digits = value.replaceAll(RegExp(r'\D'), '');
  if (digits.length == 13 && digits.startsWith('225')) {
    return digits.substring(3);
  }
  if (digits.length == 15 && digits.startsWith('00225')) {
    return digits.substring(5);
  }
  return digits;
}

String formatDriverPhone(String value) {
  final digits = normalizeDriverPhone(value);
  return [
    for (var i = 0; i < digits.length; i += 2)
      digits.substring(i, (i + 2).clamp(0, digits.length)),
  ].join(' ');
}

class DriverPhoneFormatter extends TextInputFormatter {
  const DriverPhoneFormatter();
  @override
  TextEditingValue formatEditUpdate(
    TextEditingValue oldValue,
    TextEditingValue newValue,
  ) {
    if (!newValue.composing.isCollapsed) return newValue;
    var digits = normalizeDriverPhone(newValue.text);
    var beforeCursor = normalizeDriverPhone(
      newValue.text.substring(
        0,
        newValue.selection.extentOffset.clamp(0, newValue.text.length),
      ),
    ).length;
    // Backspace on a separator removes the preceding digit instead of trapping the cursor.
    if (newValue.text.length < oldValue.text.length &&
        digits == normalizeDriverPhone(oldValue.text) &&
        oldValue.selection.isCollapsed &&
        newValue.selection.isCollapsed &&
        beforeCursor > 0) {
      digits = digits.replaceRange(beforeCursor - 1, beforeCursor, '');
      beforeCursor--;
    }
    if (digits.length > 10) digits = digits.substring(0, 10);
    final formatted = formatDriverPhone(digits);
    beforeCursor = beforeCursor.clamp(0, digits.length);
    final offset = beforeCursor == 0
        ? 0
        : beforeCursor + (beforeCursor - 1) ~/ 2;
    return TextEditingValue(
      text: formatted,
      selection: TextSelection.collapsed(
        offset: offset.clamp(0, formatted.length),
      ),
    );
  }
}
