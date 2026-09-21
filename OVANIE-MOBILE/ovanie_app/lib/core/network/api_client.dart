import 'package:dio/dio.dart';

import '../config/app_config.dart';
import '../compatibility/schema_compatibility.dart';
import 'api_runtime_state.dart';

class ApiClient {
  ApiClient._();

  static final Dio dio = _createDio();

  static Dio _createDio() {
    final client = Dio(
      BaseOptions(
        baseUrl: AppConfig.apiBaseUrl,
        connectTimeout: const Duration(seconds: 12),
        receiveTimeout: const Duration(seconds: 18),
        sendTimeout: const Duration(seconds: 18),
        headers: const {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
          'X-Ovanie-App': OvanieSchemaContract.appCode,
          'X-Ovanie-Schema-Version': OvanieSchemaContract.supportedSchemaVersion,
        },
        validateStatus: (status) =>
            status != null && status >= 200 && status < 500,
      ),
    );

    client.interceptors.add(
      InterceptorsWrapper(
        onResponse: (response, handler) {
          // Une réponse HTTP, même 4xx, prouve que le réseau et Laravel sont
          // joignables. On peut donc fermer un éventuel écran hors connexion.
          ApiRuntimeState.instance.reportOnline();
          _reportUnauthorizedIfNeeded(response.requestOptions, response.statusCode);
          handler.next(response);
        },
        onError: (error, handler) {
          if (error.response != null) {
            ApiRuntimeState.instance.reportOnline();
            _reportUnauthorizedIfNeeded(
              error.requestOptions,
              error.response?.statusCode,
            );
          } else if (_isNetworkFailure(error)) {
            ApiRuntimeState.instance.reportOffline(friendlyError(error));
          }
          handler.next(error);
        },
      ),
    );

    return client;
  }

  static bool _isNetworkFailure(DioException error) {
    return error.type == DioExceptionType.connectionError ||
        error.type == DioExceptionType.connectionTimeout ||
        error.type == DioExceptionType.receiveTimeout ||
        error.type == DioExceptionType.sendTimeout;
  }

  static void _reportUnauthorizedIfNeeded(
    RequestOptions request,
    int? statusCode,
  ) {
    if (statusCode != 401) return;
    if (request.path.endsWith('/logout')) return;

    final authorization = '${request.headers['Authorization'] ?? ''}'.trim();
    if (!authorization.toLowerCase().startsWith('bearer ') ||
        authorization.length <= 7) {
      return;
    }

    ApiRuntimeState.instance.reportSessionExpired();
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

    var message =
        'Cette opération n’a pas pu aboutir. Réessayez dans quelques instants.';
    final data = response.data;
    if (data is Map) {
      final errors = data['errors'];
      if (errors is Map) {
        for (final value in errors.values) {
          if (value is List && value.isNotEmpty) {
            message = value.first.toString();
            break;
          }
          if (value != null && value.toString().trim().isNotEmpty) {
            message = value.toString();
            break;
          }
        }
      }
      if (message.startsWith('Cette opération') && data['message'] != null) {
        message = data['message'].toString();
      }
    }

    throw OvanieApiException(message, statusCode: status);
  }

  static String friendlyError(Object error) {
    if (error is OvanieApiException) {
      if (error.statusCode == 401) {
        return 'Votre session OVANIE n’est plus valide. Reconnectez-vous pour continuer.';
      }
      if (error.statusCode == 429) {
        return 'OVANIE a reçu plusieurs requêtes très rapprochées. Patientez quelques secondes puis réessayez une seule fois.';
      }

      final message = error.message.trim();
      final technical = message.contains('No query results for model') ||
          message.contains('App\\Models\\') ||
          message.contains('Illuminate\\') ||
          message.contains('SQLSTATE') ||
          message.contains('Maximum execution time') ||
          message.contains('Allowed memory size') ||
          message.contains('Stack trace');

      if (technical) {
        return 'L’opération prend plus de temps que prévu. Votre panier est conservé. Vérifiez Mes commandes avant de relancer un paiement.';
      }

      return message;
    }

    if (error is DioException) {
      switch (error.type) {
        case DioExceptionType.connectionTimeout:
        case DioExceptionType.receiveTimeout:
        case DioExceptionType.sendTimeout:
          return 'La connexion prend plus de temps que prévu. Vérifiez votre connexion internet puis réessayez.';
        case DioExceptionType.connectionError:
          return 'Impossible de se connecter à OVANIE. Vérifiez votre connexion internet puis réessayez.';
        case DioExceptionType.badResponse:
          final responseData = error.response?.data;
          if (responseData is Map) {
            var serverMessage = '';
            final errors = responseData['errors'];
            if (errors is Map) {
              for (final value in errors.values) {
                if (value is List && value.isNotEmpty) {
                  serverMessage = value.first.toString().trim();
                  break;
                }
              }
            }
            if (serverMessage.isEmpty && responseData['message'] != null) {
              serverMessage = responseData['message'].toString().trim();
            }
            final technical = serverMessage.contains('SQLSTATE') ||
                serverMessage.contains('App\\Models\\') ||
                serverMessage.contains('Illuminate\\') ||
                serverMessage.contains('Maximum execution time') ||
                serverMessage.contains('Allowed memory size') ||
                serverMessage.contains('Stack trace');
            if (serverMessage.isNotEmpty && !technical) {
              return serverMessage;
            }
          }
          return 'Impossible de finaliser l’opération OVANIE pour le moment. Réessayez dans quelques instants.';
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
