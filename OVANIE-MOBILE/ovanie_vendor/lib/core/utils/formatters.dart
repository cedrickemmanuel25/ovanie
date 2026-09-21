String money(Object? value) {
  final number = double.tryParse('${value ?? 0}') ?? 0;
  final integer = number.round().toString();
  final buffer = StringBuffer();
  for (var i = 0; i < integer.length; i++) {
    if (i > 0 && (integer.length - i) % 3 == 0) buffer.write(' ');
    buffer.write(integer[i]);
  }
  return '${buffer.toString()} FCFA';
}

String dateTimeLabel(Object? raw) {
  final value = DateTime.tryParse('${raw ?? ''}');
  if (value == null) return '—';
  final local = value.toLocal();
  String two(int v) => v.toString().padLeft(2, '0');
  return '${two(local.day)}/${two(local.month)}/${local.year} ${two(local.hour)}:${two(local.minute)}';
}

String textOrDash(Object? value) {
  final text = '${value ?? ''}'.trim();
  return text.isEmpty ? '—' : text;
}
