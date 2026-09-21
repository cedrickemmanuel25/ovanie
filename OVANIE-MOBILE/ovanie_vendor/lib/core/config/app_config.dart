import 'package:flutter/foundation.dart';

/// Configuration d'environnement de l'application OVANIE Vendeur.
///
/// Variables supportées :
///   OVANIE_ENV=local|test|production
///   OVANIE_API_BASE=http://192.168.x.x:8000/api
class AppConfig {
  AppConfig._();

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

  static bool get hasExplicitApiBase => _definedApiBase.trim().isNotEmpty;

  static String get apiBaseUrl {
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
      return 'http://10.0.2.2:8000/api';
    }
    return 'http://127.0.0.1:8000/api';
  }

  static bool get isLocalDevelopment => environment == 'local';
  static bool get isTestEnvironment => environment == 'test';
  static bool get isProduction => environment == 'production';

  static String get backendOrigin {
    final uri = Uri.tryParse(apiBaseUrl);
    if (uri == null || uri.host.isEmpty) {
      return environment == 'production'
          ? 'https://www.ovanie.com'
          : environment == 'test'
              ? 'https://test.ovanie.com'
              : 'http://127.0.0.1:8000';
    }
    return Uri(
      scheme: uri.scheme,
      host: uri.host,
      port: uri.hasPort ? uri.port : null,
    ).toString();
  }

  static String mediaUrl(Object? raw) {
    var value = '${raw ?? ''}'.trim();
    if (value.isEmpty) return '';
    final origin = backendOrigin;
    value = value
        .replaceFirst(RegExp(r'^http://127\.0\.0\.1(?::\d+)?'), origin)
        .replaceFirst(RegExp(r'^http://localhost(?::\d+)?'), origin)
        .replaceFirst(RegExp(r'^http://10\.0\.2\.2(?::\d+)?'), origin);
    if (value.startsWith('/')) return '$origin$value';
    if (!value.startsWith('http://') && !value.startsWith('https://')) {
      return '$origin/${value.replaceFirst(RegExp(r'^/+'), '')}';
    }
    return value;
  }

  static String _normalize(String value) {
    var url = value.trim();
    while (url.endsWith('/')) {
      url = url.substring(0, url.length - 1);
    }
    return url;
  }
}
