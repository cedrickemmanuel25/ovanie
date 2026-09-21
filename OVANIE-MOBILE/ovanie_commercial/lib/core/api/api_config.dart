import 'package:flutter/foundation.dart';

/// Configuration réseau de l'application OVANIE Commercial.
///
/// Variables supportées :
///   OVANIE_ENV=local|test|production
///   OVANIE_API_BASE=http://192.168.x.x:8000/api
///
/// Compatibilité : OVANIE_API_URL reste acceptée.
abstract final class ApiConfig {
  static const String _definedEnvironment = String.fromEnvironment(
    'OVANIE_ENV',
    defaultValue: 'local',
  );

  static const String _definedApiBase = String.fromEnvironment(
    'OVANIE_API_BASE',
    defaultValue: '',
  );

  static const String _legacyDefinedApiUrl = String.fromEnvironment(
    'OVANIE_API_URL',
    defaultValue: '',
  );

  static String get environment {
    final value = _definedEnvironment.trim().toLowerCase();
    if (value == 'production' || value == 'prod') return 'production';
    if (value == 'test' || value == 'staging') return 'test';
    return 'local';
  }

  static bool get hasExplicitBaseUrl =>
      _definedApiBase.trim().isNotEmpty ||
      _legacyDefinedApiUrl.trim().isNotEmpty;

  static String get baseUrl {
    final apiBase = _definedApiBase.trim();
    if (apiBase.isNotEmpty) return _normalize(apiBase);

    final legacyUrl = _legacyDefinedApiUrl.trim();
    if (legacyUrl.isNotEmpty) return _normalize(legacyUrl);

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

  static String get commercialStatusUrl =>
      '$baseUrl/mobile/v1/commercial/auth/status';

  static Uri? get baseUri => Uri.tryParse(baseUrl);
  static String get host => baseUri?.host ?? '';

  static int? get port {
    final uri = baseUri;
    if (uri == null || !uri.hasPort) return null;
    return uri.port;
  }

  static bool get isLocal => environment == 'local';
  static bool get isTest => environment == 'test';
  static bool get isProduction => environment == 'production';

  static bool get isAndroidEmulatorFallback =>
      !hasExplicitBaseUrl &&
      isLocal &&
      !kIsWeb &&
      defaultTargetPlatform == TargetPlatform.android &&
      host == '10.0.2.2';

  static bool get isPrivateLanAddress {
    final value = host.toLowerCase();
    return value.startsWith('192.168.') ||
        value.startsWith('10.') ||
        _isPrivate172(value);
  }

  static bool _isPrivate172(String value) {
    final parts = value.split('.');
    if (parts.length != 4 || parts.first != '172') return false;
    final second = int.tryParse(parts[1]);
    return second != null && second >= 16 && second <= 31;
  }

  static String localConnectionHelp() {
    if (!isLocal) return 'Vérifiez votre connexion Internet puis réessayez.';

    if (isAndroidEmulatorFallback) {
      return 'Pour un téléphone physique, lancez l’application avec '
          'LOCAL-DEV/run-app.ps1 afin d’utiliser automatiquement l’IP LAN du PC.';
    }

    if (!kIsWeb &&
        defaultTargetPlatform == TargetPlatform.android &&
        isPrivateLanAddress) {
      return 'Le téléphone doit être sur le même réseau que le PC. '
          'Démarrez Laravel avec LOCAL-DEV/start-laravel.ps1 et autorisez '
          'PHP/port 8000 dans le pare-feu Windows.';
    }

    return 'Démarrez Laravel avec LOCAL-DEV/start-laravel.ps1.';
  }

  static String _normalize(String value) {
    var url = value.trim();
    while (url.endsWith('/')) {
      url = url.substring(0, url.length - 1);
    }
    return url;
  }
}
