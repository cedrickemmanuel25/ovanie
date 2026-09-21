import 'package:dio/dio.dart';

import 'api_config.dart';
import 'api_exception.dart';
import '../compatibility/schema_compatibility.dart';

class ApiClient {
  ApiClient()
      : _dio = Dio(
          BaseOptions(
            baseUrl: ApiConfig.baseUrl,
            connectTimeout: Duration(seconds: ApiConfig.isLocal ? 8 : 15),
            receiveTimeout: Duration(seconds: ApiConfig.isLocal ? 15 : 25),
            sendTimeout: Duration(seconds: ApiConfig.isLocal ? 15 : 25),
            headers: const {
              'Accept': 'application/json',
              'Content-Type': 'application/json',
              'X-Ovanie-App': OvanieSchemaContract.appCode,
              'X-Ovanie-Schema-Version': OvanieSchemaContract.supportedSchemaVersion,
            },
          ),
        );

  final Dio _dio;
  String? _token;

  String get baseUrl => _dio.options.baseUrl;

  void setToken(String? token) {
    _token = token;
  }

  Future<Map<String, dynamic>> getJson(
    String path, {
    Map<String, dynamic>? queryParameters,
  }) async {
    try {
      final response = await _dio.get<dynamic>(
        path,
        queryParameters: queryParameters,
        options: _options(),
      );
      return _asMap(response.data);
    } on DioException catch (error) {
      throw _mapDioError(error);
    }
  }

  Future<Map<String, dynamic>> postJson(
    String path, {
    Map<String, dynamic>? body,
  }) async {
    try {
      final response = await _dio.post<dynamic>(
        path,
        data: body ?? const <String, dynamic>{},
        options: _options(),
      );
      return _asMap(response.data);
    } on DioException catch (error) {
      throw _mapDioError(error);
    }
  }


  Future<Map<String, dynamic>> postFormData(
    String path, {
    required FormData formData,
  }) async {
    try {
      final response = await _dio.post<dynamic>(
        path,
        data: formData,
        options: Options(
          headers: _token == null ? null : {'Authorization': 'Bearer $_token'},
          contentType: 'multipart/form-data',
        ),
      );
      return _asMap(response.data);
    } on DioException catch (error) {
      throw _mapDioError(error);
    }
  }

  Options _options() {
    return Options(
      headers: _token == null ? null : {'Authorization': 'Bearer $_token'},
    );
  }

  Map<String, dynamic> _asMap(dynamic data) {
    if (data is Map<String, dynamic>) return data;
    if (data is Map) return Map<String, dynamic>.from(data);
    return <String, dynamic>{};
  }

  ApiException _mapDioError(DioException error) {
    final status = error.response?.statusCode;
    final data = error.response?.data;
    String? message;

    if (status == 404) {
      message =
          'L’API Commercial n’est pas installée sur ce Laravel. Vérifiez les fichiers Laravel du ZIP puis exécutez : php artisan optimize:clear.';
    }

    if (message == null && data is Map) {
      final rawMessage = data['message'];
      if (rawMessage is String && rawMessage.trim().isNotEmpty) {
        message = rawMessage.trim();
      }

      if (message == null && data['errors'] is Map) {
        final errors = data['errors'] as Map;
        for (final value in errors.values) {
          if (value is List && value.isNotEmpty) {
            message = value.first.toString();
            break;
          }
        }
      }
    }

    if (message == null) {
      if (error.type == DioExceptionType.connectionTimeout ||
          error.type == DioExceptionType.sendTimeout ||
          error.type == DioExceptionType.receiveTimeout) {
        message = ApiConfig.isLocal
            ? 'Laravel ne répond pas à ${ApiConfig.baseUrl}. ${ApiConfig.localConnectionHelp()}'
            : 'La connexion au serveur OVANIE prend trop de temps. Vérifiez votre réseau et réessayez.';
      } else if (error.type == DioExceptionType.connectionError) {
        message = ApiConfig.isLocal
            ? 'Impossible de joindre Laravel à ${ApiConfig.baseUrl}. ${ApiConfig.localConnectionHelp()}'
            : 'Impossible de joindre OVANIE. Vérifiez votre connexion Internet.';
      } else if (error.type == DioExceptionType.badCertificate) {
        message = 'Le certificat du serveur OVANIE n’est pas valide.';
      } else {
        message = 'Une erreur est survenue. Réessayez dans quelques instants.';
      }
    }

    return ApiException(message, statusCode: status);
  }
}
