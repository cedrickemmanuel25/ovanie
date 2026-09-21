import 'package:dio/dio.dart';
import 'package:flutter/material.dart';

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
      home: Scaffold(
        body: SafeArea(
          child: Center(
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(28),
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 520),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const Icon(Icons.system_update_alt_rounded, size: 64),
                    const SizedBox(height: 22),
                    const Text(
                      'Mise à jour de l’application requise',
                      textAlign: TextAlign.center,
                      style: TextStyle(fontSize: 24, fontWeight: FontWeight.w800),
                    ),
                    const SizedBox(height: 14),
                    const Text(
                      'Cette version de l’application ne peut pas utiliser en toute sécurité le contrat de données actuellement déployé par OVANIE. Mettez à jour l’application avant de continuer.',
                      textAlign: TextAlign.center,
                    ),
                    const SizedBox(height: 22),
                    Container(
                      width: double.infinity,
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(
                        borderRadius: BorderRadius.circular(14),
                        color: Theme.of(context).colorScheme.surfaceContainerHighest,
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text('Application : ${OvanieSchemaContract.appLabel}'),
                          Text('Contrat APK : ${OvanieSchemaContract.supportedSchemaVersion}'),
                          Text('Contrat serveur : ${result.serverSchemaVersion ?? 'inconnu'}'),
                        ],
                      ),
                    ),
                    const SizedBox(height: 16),
                    Text(
                      'Aucune donnée n’a été modifiée. Fermez cette application et installez la version OVANIE la plus récente.',
                      textAlign: TextAlign.center,
                      style: TextStyle(color: const Color(0xffff6b00), fontWeight: FontWeight.w700),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
