import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';

import '../config/app_config.dart';
import '../compatibility/schema_compatibility.dart';

class ApiClient {
  ApiClient._();

  static final Dio dio = Dio(
    BaseOptions(
      baseUrl: AppConfig.apiBaseUrl,
      connectTimeout: const Duration(seconds: 15),
      receiveTimeout: const Duration(seconds: 25),
      sendTimeout: const Duration(seconds: 30),
      headers: const {
        'Accept': 'application/json',
        'X-Ovanie-App': OvanieSchemaContract.appCode,
        'X-Ovanie-Schema-Version': OvanieSchemaContract.supportedSchemaVersion,
      },
      // On laisse l'application lire proprement toutes les réponses HTTP et
      // décider du message fonctionnel à afficher. Une réponse 404/422/500 ne
      // doit pas être transformée en erreur Dio opaque avant lecture du JSON.
      validateStatus: (status) => status != null && status >= 200 && status < 600,
    ),
  );

  static void setToken(String? token) {
    final value = token?.trim() ?? '';
    if (value.isEmpty) {
      dio.options.headers.remove('Authorization');
    } else {
      dio.options.headers['Authorization'] = 'Bearer $value';
    }
  }

  static void ensureSuccess(Response<dynamic> response) {
    final status = response.statusCode ?? 0;
    if (status >= 200 && status < 300) return;
    if (kDebugMode) {
      debugPrint(
        '[OVANIE API] HTTP $status ${response.requestOptions.method} '
        '${response.requestOptions.uri}',
      );
      debugPrint('[OVANIE API] BODY ${response.data}');
    }
    throw VendorApiException(
      messageFromResponse(response),
      statusCode: status,
      requestPath: response.requestOptions.path,
    );
  }

  static String messageFromResponse(Response<dynamic> response) {
    final status = response.statusCode ?? 0;
    final data = response.data;

    // Les erreurs de validation Laravel doivent être affichées avant les
    // messages génériques afin que le vendeur sache exactement quel champ
    // corriger lors de l'ouverture de sa boutique.
    if (data is Map) {
      final errors = data['errors'];
      if (errors is Map) {
        for (final value in errors.values) {
          if (value is List && value.isNotEmpty) {
            return value.first.toString();
          }
          if (value != null && value.toString().trim().isNotEmpty) {
            return value.toString();
          }
        }
      }

      final candidates = [data['message'], data['error']];
      for (final candidate in candidates) {
        final text = '${candidate ?? ''}'.trim();
        if (text.isEmpty) continue;

        // Une erreur serveur 5xx peut contenir un TypeError, un chemin Windows,
        // une stack trace ou le nom d'une classe PHP. Ces détails restent dans
        // debugPrint() pour le développement mais ne doivent jamais être
        // affichés au vendeur dans l'interface mobile.
        if (status >= 500) continue;
        if (!_isTechnicalLaravelRouteMessage(text)) return text;
      }
    }

    if (status == 401) {
      return 'Votre session vendeur a expiré. Reconnectez-vous.';
    }
    if (status == 403) {
      return 'Cette action n’est pas autorisée pour ce compte vendeur.';
    }
    if (status == 404) {
      return 'La fonction demandée n’est pas disponible sur l’API OVANIE actuellement chargée.';
    }
    if (status == 409) {
      return 'Ces informations ont déjà été utilisées ou ont changé. Actualisez puis réessayez.';
    }
    if (status == 419) {
      return 'La requête a expiré. Réessayez depuis l’application OVANIE Vendeur.';
    }
    if (status == 422) {
      return 'Certaines informations sont invalides ou incomplètes.';
    }
    if (status == 429) {
      return 'Trop de tentatives rapprochées. Patientez quelques secondes.';
    }
    if (status >= 500) {
      return 'OVANIE rencontre un problème technique. Réessayez dans quelques instants.';
    }

    return 'Impossible de finaliser cette opération OVANIE.';
  }

  static bool _isTechnicalLaravelRouteMessage(String value) {
    final text = value.toLowerCase();
    return text.contains('the route') && text.contains('could not be found') ||
        text.contains('route [') && text.contains('not defined') ||
        text.contains('notfoundhttpexception');
  }

  static String friendlyError(Object error) {
    if (error is VendorApiException) return error.message;
    if (error is DioException) {
      if (error.response != null) return messageFromResponse(error.response!);
      switch (error.type) {
        case DioExceptionType.connectionTimeout:
        case DioExceptionType.sendTimeout:
        case DioExceptionType.receiveTimeout:
          return 'Le serveur OVANIE ne répond pas assez rapidement. Réessayez.';
        case DioExceptionType.connectionError:
          return 'Impossible de joindre OVANIE. Vérifiez que le serveur Laravel est démarré et accessible depuis cet appareil.';
        default:
          return 'Impossible de joindre OVANIE. Vérifiez votre connexion réseau.';
      }
    }
    return 'Une erreur inattendue empêche cette opération.';
  }
}

class VendorApiException implements Exception {
  final String message;
  final int? statusCode;
  final String? requestPath;

  const VendorApiException(
    this.message, {
    this.statusCode,
    this.requestPath,
  });

  bool get isMissingRoute => statusCode == 404;

  @override
  String toString() => message;
}
