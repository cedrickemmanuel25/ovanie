import 'package:dio/dio.dart';

import '../config/api_config.dart';
import '../compatibility/schema_compatibility.dart';

/// Client HTTP unique de l'application Livreur.
///
/// Toute la logique réseau doit passer par ici (ou par un repository qui
/// l'utilise) afin qu'un écart mineur avec le vrai contrat d'API backend ne
/// nécessite de changer qu'un seul endroit.
class ApiClient {
  ApiClient._();

  static final Dio dio = _createDio();

  static Dio _createDio() {
    return Dio(
      BaseOptions(
        baseUrl: ApiConfig.baseUrl,
        connectTimeout: const Duration(seconds: 12),
        receiveTimeout: const Duration(seconds: 20),
        sendTimeout: const Duration(seconds: 30),
        headers: const {
          'Accept': 'application/json',
          'X-Ovanie-App': OvanieSchemaContract.appCode,
          'X-Ovanie-Schema-Version': OvanieSchemaContract.supportedSchemaVersion,
        },
        validateStatus: (status) =>
            status != null && status >= 200 && status < 500,
      ),
    );
  }

  static void setBearerToken(String? token) {
    final value = token?.trim() ?? '';
    if (value.isEmpty) {
      dio.options.headers.remove('Authorization');
      return;
    }
    dio.options.headers['Authorization'] = 'Bearer $value';
  }

  static void ensureSuccess(Response<dynamic> response) {
    final status = response.statusCode ?? 0;
    if (status >= 200 && status < 300) return;
    throw OvanieApiException(_extractMessage(response.data), statusCode: status);
  }

  static String _extractMessage(dynamic data) {
    if (data is Map) {
      final errors = data['errors'];
      if (errors is Map) {
        for (final value in errors.values) {
          if (value is List && value.isNotEmpty) return value.first.toString();
          if (value != null && value.toString().trim().isNotEmpty) {
            return value.toString();
          }
        }
      }
      if (data['message'] != null && data['message'].toString().trim().isNotEmpty) {
        return data['message'].toString();
      }
    }
    return 'Cette opération n’a pas pu aboutir. Réessayez dans quelques instants.';
  }

  static String friendlyError(Object error) {
    if (error is OvanieApiException) {
      if (error.statusCode == 401) {
        return 'Votre session n’est plus valide. Reconnectez-vous pour continuer.';
      }
      if (error.statusCode == 429) {
        return 'Trop de tentatives rapprochées. Patientez quelques instants puis réessayez.';
      }
      return error.message;
    }
    if (error is DioException) {
      switch (error.type) {
        case DioExceptionType.connectionTimeout:
        case DioExceptionType.receiveTimeout:
        case DioExceptionType.sendTimeout:
          return 'La connexion prend plus de temps que prévu. Vérifiez votre connexion internet puis réessayez.';
        case DioExceptionType.connectionError:
          return 'Impossible de contacter OVANIE. Vérifiez votre connexion internet puis réessayez.';
        case DioExceptionType.badResponse:
          return _extractMessage(error.response?.data);
        default:
          return 'Impossible de contacter OVANIE pour le moment.';
      }
    }
    return 'Un problème temporaire empêche le chargement. Réessayez dans quelques instants.';
  }
}

class OvanieApiException implements Exception {
  final String message;
  final int? statusCode;

  const OvanieApiException(this.message, {this.statusCode});

  @override
  String toString() => message;
}
