import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';

import '../../../core/network/api_client.dart';

class AuthResult {
  final String token;
  final Map<String, dynamic> user;

  const AuthResult({required this.token, required this.user});
}

/// Authentification mobile officielle OVANIE.
///
/// V75 : une seule API d'authentification est autorisée afin d'éviter les
/// divergences entre l'ancien /api/auth/* et l'API mobile versionnée.
///
/// Base URL de développement sur téléphone physique :
///   http://IP_DU_PC:8000/api
///
/// Endpoints finaux :
///   POST /api/mobile/v1/auth/login
///   POST /api/mobile/v1/auth/register
///   POST /api/mobile/v1/auth/forgot-password
///   GET  /api/mobile/v1/auth/me
///   POST /api/mobile/v1/auth/logout
class AuthRepository {
  const AuthRepository();

  String get _deviceName => switch (defaultTargetPlatform) {
        TargetPlatform.android => 'android',
        TargetPlatform.iOS => 'ios',
        TargetPlatform.macOS => 'macos',
        TargetPlatform.windows => 'windows',
        TargetPlatform.linux => 'linux',
        TargetPlatform.fuchsia => 'mobile',
      };

  static const String statusPath = '/mobile/v1/auth/status';
  static const String loginPath = '/mobile/v1/auth/login';
  static const String registerPath = '/mobile/v1/auth/register';
  static const String forgotPasswordPath = '/mobile/v1/auth/forgot-password';
  static const String resetPasswordPath = '/mobile/v1/auth/reset-password';
  static const String mePath = '/mobile/v1/auth/me';
  static const String logoutPath = '/mobile/v1/auth/logout';

  Future<void> checkServer() async {
    final response = await ApiClient.dio.get<dynamic>(statusPath);
    _throwFriendlyRouteError(response);
    ApiClient.ensureSuccess(response);

    final data = response.data;
    if (data is! Map || data['ok'] != true) {
      throw const OvanieApiException(
        'Le service de connexion est temporairement indisponible. Réessayez dans quelques instants.',
      );
    }
  }

  Future<AuthResult> login({
    required String identifier,
    required String password,
  }) async {
    final response = await ApiClient.dio.post<dynamic>(
      loginPath,
      data: {
        'email': identifier.trim(),
        'password': password,
        'device_name': _deviceName,
        'portal': 'client',
      },
    );

    _throwFriendlyRouteError(response);
    ApiClient.ensureSuccess(response);

    final result = _parseAuthResult(response);
    ApiClient.setBearerToken(result.token);
    return result;
  }

  Future<AuthResult> register({
    required String firstName,
    required String lastName,
    required String email,
    required String phoneCountry,
    required String phone,
    required String password,
    required String passwordConfirmation,
  }) async {
    final response = await ApiClient.dio.post<dynamic>(
      registerPath,
      data: {
        'first_name': firstName.trim(),
        'last_name': lastName.trim(),
        'email': email.trim().toLowerCase(),
        'phone_country': phoneCountry.trim(),
        'phone': phone.trim(),
        'password': password,
        'password_confirmation': passwordConfirmation,
        'terms': true,
        'device_name': _deviceName,
      },
    );

    _throwFriendlyRouteError(response);
    ApiClient.ensureSuccess(response);

    final result = _parseAuthResult(response);
    ApiClient.setBearerToken(result.token);
    return result;
  }


  Future<String> forgotPassword({required String email}) async {
    final response = await ApiClient.dio.post<dynamic>(
      forgotPasswordPath,
      data: {
        'email': email.trim().toLowerCase(),
        'portal': 'client',
      },
    );

    _throwFriendlyRouteError(response);
    ApiClient.ensureSuccess(response);

    final data = response.data;
    if (data is Map && data['message'] != null) {
      final message = data['message'].toString().trim();
      if (message.isNotEmpty) return message;
    }

    return 'Si un compte correspond à ces informations, un lien de récupération a été envoyé à l’adresse e-mail associée.';
  }

  Future<String> resetPassword({
    required String email,
    required String token,
    required String password,
    required String confirmation,
  }) async {
    final response = await ApiClient.dio.post<dynamic>(
      resetPasswordPath,
      data: {
        'email': email.trim().toLowerCase(),
        'token': token.trim(),
        'password': password,
        'password_confirmation': confirmation,
        'portal': 'client',
      },
    );
    _throwFriendlyRouteError(response);
    ApiClient.ensureSuccess(response);
    final data = response.data;
    if (data is Map && '${data['message'] ?? ''}'.trim().isNotEmpty) {
      return '${data['message']}'.trim();
    }
    return 'Votre mot de passe OVANIE a été réinitialisé.';
  }

  Future<Map<String, dynamic>> me() async {
    final response = await ApiClient.dio.get<dynamic>(mePath);
    _throwFriendlyRouteError(response);
    ApiClient.ensureSuccess(response);

    final data = response.data;
    if (data is! Map || data['user'] is! Map) {
      throw const OvanieApiException(
        'Impossible de charger votre profil pour le moment. Réessayez dans quelques instants.',
      );
    }

    return Map<String, dynamic>.from(data['user'] as Map);
  }

  Future<void> logout(String token) async {
    final response = await ApiClient.dio.post<dynamic>(
      logoutPath,
      data: const <String, dynamic>{},
      options: Options(headers: {'Authorization': 'Bearer $token'}),
    );

    // La déconnexion locale reste possible si le token est déjà expiré.
    final status = response.statusCode ?? 0;
    if (status != 401) {
      _throwFriendlyRouteError(response);
      ApiClient.ensureSuccess(response);
    }

    ApiClient.setBearerToken(null);
  }

  void _throwFriendlyRouteError(Response<dynamic> response) {
    if ((response.statusCode ?? 0) != 404) return;

    throw const OvanieApiException(
      'Le service de connexion est temporairement indisponible. Réessayez dans quelques instants.',
      statusCode: 404,
    );
  }

  AuthResult _parseAuthResult(Response<dynamic> response) {
    final data = response.data;
    if (data is! Map) {
      throw const OvanieApiException(
        'Impossible d’ouvrir votre session pour le moment. Réessayez.',
      );
    }

    final token = (data['token'] ?? '').toString().trim();
    final rawUser = data['user'];

    if (token.isEmpty || rawUser is! Map) {
      throw const OvanieApiException(
        'Impossible d’ouvrir votre session pour le moment. Réessayez.',
      );
    }

    return AuthResult(
      token: token,
      user: Map<String, dynamic>.from(rawUser),
    );
  }
}
