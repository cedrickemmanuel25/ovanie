import 'package:flutter/foundation.dart';

/// Configuration d'environnement de l'application OVANIE Livreur.
///
/// Variables supportées :
///   OVANIE_ENV=local|test|production
///   OVANIE_API_BASE=http://192.168.x.x:8000/api
class ApiConfig {
  ApiConfig._();

  static const String _definedEnvironment = String.fromEnvironment(
    'OVANIE_ENV',
    defaultValue: 'local',
  );

  static const String _definedApiBase = String.fromEnvironment(
    'OVANIE_API_BASE',
    defaultValue: '',
  );

  static String get environment {
    final value = _definedEnvironment.trim().toLowerCase();
    if (value == 'production' || value == 'prod') return 'production';
    if (value == 'test' || value == 'staging') return 'test';
    return 'local';
  }

  static bool get hasExplicitBaseUrl => _definedApiBase.trim().isNotEmpty;

  static String get baseUrl {
    final explicit = _definedApiBase.trim();
    if (explicit.isNotEmpty) return _normalize(explicit);

    switch (environment) {
      case 'production':
        return 'https://www.ovanie.com/api';
      case 'test':
        return 'https://test.ovanie.com/api';
      case 'local':
      default:
        return _defaultLocalApiBase();
    }
  }

  static String _defaultLocalApiBase() {
    if (kIsWeb) return 'http://127.0.0.1:8000/api';
    if (defaultTargetPlatform == TargetPlatform.android) {
      // Android Emulator uniquement. Pour un téléphone réel, le script
      // LOCAL-DEV/run-app.ps1 injecte l'IP LAN du PC.
      return 'http://10.0.2.2:8000/api';
    }
    return 'http://127.0.0.1:8000/api';
  }

  static bool get isLocal => environment == 'local';
  static bool get isTest => environment == 'test';
  static bool get isProduction => environment == 'production';

  static String resolveMediaUrl(String? url) {
    final value = url?.trim() ?? '';
    if (value.isEmpty) return '';

    final api = baseUrl;
    final origin = api.endsWith('/api') ? api.substring(0, api.length - 4) : api;

    if (value.startsWith('http://') || value.startsWith('https://')) {
      final media = Uri.tryParse(value);
      final apiOrigin = Uri.tryParse(origin);
      final isLocalhost = media != null &&
          (media.host == '127.0.0.1' ||
              media.host == 'localhost' ||
              media.host == '10.0.2.2');
      if (isLocalhost && apiOrigin != null && apiOrigin.host.isNotEmpty) {
        return apiOrigin.replace(
          path: media.path,
          query: media.hasQuery ? media.query : null,
          fragment: media.hasFragment ? media.fragment : null,
        ).toString();
      }
      return value;
    }

    return value.startsWith('/') ? '$origin$value' : '$origin/$value';
  }

  static String _normalize(String value) {
    var url = value.trim();
    while (url.endsWith('/')) {
      url = url.substring(0, url.length - 1);
    }
    return url;
  }
}
