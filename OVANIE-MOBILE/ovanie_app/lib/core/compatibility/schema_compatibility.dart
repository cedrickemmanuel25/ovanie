import 'package:dio/dio.dart';
import 'package:flutter/material.dart';

import '../../features/auth/presentation/auth_branding.dart';
import '../config/app_config.dart';

/// Contrat de données compris par CETTE version de l'APK.
///
/// Cette constante est volontairement compilée dans l'application. Quand le
/// contrat OVANIE évolue de manière incompatible, une nouvelle APK doit être
/// publiée avec la nouvelle version avant d'autoriser son démarrage.
abstract final class OvanieSchemaContract {
  static const String appCode = 'client';
  static const String appLabel = 'OVANIE Client';
  static const String supportedSchemaVersion = '1.0.0';
}

enum SchemaCompatibilityState { compatible, incompatible, unavailable }

class SchemaCompatibilityResult {
  const SchemaCompatibilityResult({
    required this.state,
    this.serverSchemaVersion,
    this.reason,
  });

  final SchemaCompatibilityState state;
  final String? serverSchemaVersion;
  final String? reason;

  bool get isIncompatible => state == SchemaCompatibilityState.incompatible;
}

abstract final class OvanieSchemaCompatibility {
  static Future<SchemaCompatibilityResult> check() async {
    final dio = Dio(
      BaseOptions(
        baseUrl: AppConfig.apiBaseUrl,
        connectTimeout: const Duration(seconds: 6),
        receiveTimeout: const Duration(seconds: 8),
        sendTimeout: const Duration(seconds: 8),
        headers: const {
          'Accept': 'application/json',
          'X-Ovanie-App': OvanieSchemaContract.appCode,
          'X-Ovanie-Schema-Version': OvanieSchemaContract.supportedSchemaVersion,
        },
        validateStatus: (status) => status != null && status >= 200 && status < 500,
      ),
    );

    try {
      final response = await dio.get<dynamic>(
        '/mobile/v1/reference-data/compatibility',
        queryParameters: const {
          'app': OvanieSchemaContract.appCode,
          'schema_version': OvanieSchemaContract.supportedSchemaVersion,
        },
      );

      final data = response.data;
      if (data is! Map) {
        return const SchemaCompatibilityResult(
          state: SchemaCompatibilityState.unavailable,
          reason: 'invalid_response',
        );
      }

      final compatible = data['compatible'];
      final rawCompatibility = data['compatibility'];
      final compatibility = rawCompatibility is Map ? rawCompatibility : const {};
      final serverSchema = compatibility['server_schema_version']?.toString();
      final reason = compatibility['reason']?.toString() ?? data['message']?.toString();

      if (compatible == true) {
        return SchemaCompatibilityResult(
          state: SchemaCompatibilityState.compatible,
          serverSchemaVersion: serverSchema,
          reason: reason,
        );
      }

      if (compatible == false) {
        return SchemaCompatibilityResult(
          state: SchemaCompatibilityState.incompatible,
          serverSchemaVersion: serverSchema,
          reason: reason,
        );
      }
    } on DioException {
      // Une panne réseau ne doit pas transformer une application utilisable
      // hors connexion en application bloquée. Seule une incompatibilité
      // explicitement confirmée par Laravel bloque le démarrage.
    } catch (_) {
      // Même règle : en cas d'erreur de diagnostic, l'application continue.
    }

    return const SchemaCompatibilityResult(
      state: SchemaCompatibilityState.unavailable,
      reason: 'compatibility_check_unavailable',
    );
  }
}

class OvanieSchemaIncompatibleApp extends StatelessWidget {
  const OvanieSchemaIncompatibleApp({
    super.key,
    required this.result,
  });

  final SchemaCompatibilityResult result;

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      debugShowCheckedModeBanner: false,
      title: OvanieSchemaContract.appLabel,
      theme: ThemeData(
        useMaterial3: true,
        colorScheme: ColorScheme.fromSeed(seedColor: const Color(0xff0b2e5e)),
      ),
      home: OvanieStatusPage(
        art: 'update',
        artAspect: 941 / 715,
        title: 'Mise à jour de l’application requise',
        message: 'Une nouvelle version de l’application est disponible. Mettez-la à jour pour continuer à utiliser OVANIE.',
        extras: [
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 15),
            decoration: BoxDecoration(
              color: const Color(0xFFEFF2F7),
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: const Color(0xFFE0E5EE)),
            ),
            child: const Text(
              'Application : ${OvanieSchemaContract.appLabel}',
              textAlign: TextAlign.center,
              style: TextStyle(color: authNavy, fontSize: 14.5, fontWeight: FontWeight.w500),
            ),
          ),
          const OvanieInfoStrip(
            icon: Icons.verified_user_outlined,
            divider: false,
            text: 'Vos données sont conservées. Installez la dernière version puis reconnectez-vous.',
          ),
        ],
      ),
    );
  }
}
