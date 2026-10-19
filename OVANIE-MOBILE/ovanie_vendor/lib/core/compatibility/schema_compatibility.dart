import 'package:dio/dio.dart';
import 'package:flutter/material.dart';

import '../config/app_config.dart';
import '../ui/vendor_mockup_ui.dart';

/// Contrat de données compris par CETTE version de l'APK.
///
/// Cette constante est volontairement compilée dans l'application. Quand le
/// contrat OVANIE évolue de manière incompatible, une nouvelle APK doit être
/// publiée avec la nouvelle version avant d'autoriser son démarrage.
abstract final class OvanieSchemaContract {
  static const String appCode = 'vendor';
  static const String appLabel = 'OVANIE Vendeur';
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
        fontFamily: Mk.font,
        scaffoldBackgroundColor: const Color(0xFFF7F9FC),
        colorScheme: ColorScheme.fromSeed(seedColor: Mk.navy),
      ),
      home: MkTheme(
        lightStatusBar: false,
        child: Scaffold(
          body: SafeArea(
            child: Center(
              child: SingleChildScrollView(
                padding: const EdgeInsets.fromLTRB(20, 16, 20, 24),
                child: ConstrainedBox(
                  constraints: const BoxConstraints(maxWidth: 520),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Center(
                        child: Image.asset(
                          'assets/images/mockups/logo_navy.png',
                          height: 52,
                          semanticLabel: OvanieSchemaContract.appLabel,
                        ),
                      ),
                      const SizedBox(height: 10),
                      ClipRRect(
                        borderRadius: BorderRadius.circular(18),
                        child: Image.asset('assets/images/mockups/update_required.jpg', excludeFromSemantics: true),
                      ),
                      const SizedBox(height: 18),
                      const Center(child: Icon(Icons.sync_rounded, color: Mk.navy, size: 54)),
                      const SizedBox(height: 10),
                      const Text(
                        'Mise à jour de l’application requise',
                        textAlign: TextAlign.center,
                        style: TextStyle(color: Mk.navy, fontSize: 23, height: 1.2, fontWeight: FontWeight.w800),
                      ),
                      const SizedBox(height: 10),
                      const Text(
                        'Cette version de l’application ne peut pas utiliser en toute sécurité le contrat de données '
                        'actuellement déployé par OVANIE. Mettez à jour l’application avant de continuer.',
                        textAlign: TextAlign.center,
                        style: TextStyle(color: Mk.muted, fontSize: 14.5, height: 1.45, fontWeight: FontWeight.w500),
                      ),
                      const SizedBox(height: 18),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 4),
                        decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(16)),
                        child: Column(
                          children: [
                            _line(Icons.grid_view_rounded, 'Application', OvanieSchemaContract.appLabel),
                            const Divider(height: 1, color: Color(0xFFE4E9F1)),
                            _line(Icons.description_outlined, 'Contrat APK', OvanieSchemaContract.supportedSchemaVersion),
                            if ((result.serverSchemaVersion ?? '').isNotEmpty) ...[
                              const Divider(height: 1, color: Color(0xFFE4E9F1)),
                              _line(Icons.dns_outlined, 'Contrat serveur', result.serverSchemaVersion!),
                            ],
                          ],
                        ),
                      ),
                      const SizedBox(height: 14),
                      Container(
                        padding: const EdgeInsets.all(14),
                        decoration: BoxDecoration(
                          color: const Color(0xFFFFF4EC),
                          borderRadius: BorderRadius.circular(14),
                          border: Border.all(color: const Color(0xFFFFB27A)),
                        ),
                        child: const Row(
                          children: [
                            Icon(Icons.verified_user_outlined, color: Color(0xFFE85400), size: 30),
                            SizedBox(width: 12),
                            Expanded(
                              child: Text(
                                'Aucune donnée n’a été modifiée. Fermez cette application et installez la version OVANIE la plus récente.',
                                style: TextStyle(color: Color(0xFFC24400), fontSize: 14, height: 1.4, fontWeight: FontWeight.w700),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _line(IconData icon, String label, String value) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 13),
        child: Row(
          children: [
            Icon(icon, color: Mk.icon, size: 22),
            const SizedBox(width: 12),
            Expanded(
              child: Text('$label :', style: const TextStyle(color: Mk.muted, fontSize: 14.5, fontWeight: FontWeight.w500)),
            ),
            Flexible(
              child: Text(value, textAlign: TextAlign.right, style: const TextStyle(color: Mk.navy, fontSize: 14.5, fontWeight: FontWeight.w800)),
            ),
          ],
        ),
      );
}
