import '../../../core/api/api_client.dart';

class CommercialSession {
  const CommercialSession({
    required this.token,
    required this.user,
  });

  final String token;
  final Map<String, dynamic> user;
}

class CommercialAuthService {
  CommercialAuthService(this._api);

  final ApiClient _api;

  Future<CommercialSession> login({
    required String identifier,
    required String password,
  }) async {
    final data = await _api.postJson(
      '/mobile/v1/commercial/auth/login',
      body: {
        'identifier': identifier.trim(),
        'password': password,
        'device_name': 'OVANIE Commercial Flutter',
      },
    );

    final token = (data['token'] ?? '').toString();
    if (token.isEmpty) {
      throw const FormatException('Token de connexion absent.');
    }

    final rawUser = data['user'];
    final user = rawUser is Map
        ? Map<String, dynamic>.from(rawUser)
        : <String, dynamic>{};

    _api.setToken(token);
    return CommercialSession(token: token, user: user);
  }

  Future<Map<String, dynamic>> me(String token) async {
    _api.setToken(token);
    final data = await _api.getJson('/mobile/v1/commercial/auth/me');
    final rawUser = data['user'];
    return rawUser is Map
        ? Map<String, dynamic>.from(rawUser)
        : <String, dynamic>{};
  }

  Future<void> logout() async {
    try {
      await _api.postJson('/mobile/v1/commercial/auth/logout');
    } finally {
      _api.setToken(null);
    }
  }
}
