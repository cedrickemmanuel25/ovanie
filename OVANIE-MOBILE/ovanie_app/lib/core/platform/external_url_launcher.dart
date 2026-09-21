import 'package:flutter/services.dart';

/// Ouvre une URL avec une application Android externe (navigateur, app de paiement, etc.)
/// sans dépendance Flutter tierce.
class ExternalUrlLauncher {
  ExternalUrlLauncher._();

  static const MethodChannel _channel = MethodChannel('ovanie/external_url');

  static Future<bool> open(String url) async {
    final value = url.trim();
    if (value.isEmpty) return false;

    try {
      final opened = await _channel.invokeMethod<bool>(
        'openUrl',
        <String, dynamic>{'url': value},
      );
      return opened ?? false;
    } on PlatformException {
      return false;
    } on MissingPluginException {
      return false;
    }
  }
}
