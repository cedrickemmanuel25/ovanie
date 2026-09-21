import 'dart:async';
import 'dart:convert';

import 'package:flutter/foundation.dart';

import '../../../core/network/api_client.dart';
import '../../../core/storage/device_storage.dart';
import '../../../core/utils/text_cleaner.dart';

/// Session mobile OVANIE persistante.
///
/// Le jeton et le profil minimal sont restaurés au redémarrage de l'app.
/// Laravel/Sanctum reste l'autorité : la session locale n'est considérée
/// valide qu'après vérification silencieuse du token au Splash.
class SessionStore extends ChangeNotifier {
  SessionStore._();

  static final SessionStore instance = SessionStore._();
  static const String _storageKey = 'ovanie_authenticated_session_v1';
  static const String _identifierStorageKey = 'ovanie_last_login_identifier_v1';

  bool _isAuthenticated = false;
  bool _restored = false;
  bool _persistentSession = true;
  String? _token;
  int? _userId;
  String? _name;
  String? _email;
  String? _phone;
  String? _whatsappPhone;
  String? _role;
  String? _lastIdentifier;

  bool get isAuthenticated => _isAuthenticated;
  bool get isRestored => _restored;
  String? get token => _token;
  int? get userId => _userId;
  String? get name => _name;
  String? get email => _email;
  String? get phone => _phone;
  String? get whatsappPhone => _whatsappPhone;
  String? get role => _role;
  String? get lastIdentifier => _lastIdentifier;

  /// Restaure d'abord la mémoire locale. Le Splash vérifiera ensuite le token
  /// auprès de Laravel avant d'afficher l'accueil.
  Future<void> restore() async {
    if (_restored) return;

    _lastIdentifier = (await DeviceStorage.instance.readString(
      _identifierStorageKey,
    ))
        ?.trim();

    final raw = await DeviceStorage.instance.readString(_storageKey);
    _restored = true;

    if (raw == null || raw.trim().isEmpty) return;

    try {
      final decoded = jsonDecode(raw);
      if (decoded is! Map) {
        await DeviceStorage.instance.remove(_storageKey);
        return;
      }

      final map = Map<String, dynamic>.from(decoded);
      final token = (map['token'] ?? '').toString().trim();
      final rawUser = map['user'];
      if (token.isEmpty || rawUser is! Map) {
        await DeviceStorage.instance.remove(_storageKey);
        return;
      }

      _applySession(
        token: token,
        user: Map<String, dynamic>.from(rawUser),
      );
      _persistentSession = true;
      if (!_isAuthenticated) {
        await DeviceStorage.instance.remove(_storageKey);
        ApiClient.setBearerToken(null);
        return;
      }
      ApiClient.setBearerToken(_token);
      notifyListeners();
    } catch (_) {
      await DeviceStorage.instance.remove(_storageKey);
    }
  }

  /// Ouvre une session après authentification réelle Laravel et la persiste
  /// systématiquement pour que le client reste connecté après redémarrage.
  Future<void> openAuthenticatedSession({
    required String token,
    required Map<String, dynamic> user,
    String? loginIdentifier,
    bool persist = true,
  }) async {
    _applySession(token: token, user: user);
    _persistentSession = persist;

    final identifier = (loginIdentifier ?? user['email'] ?? user['phone'] ?? '')
        .toString()
        .trim();
    if (identifier.isNotEmpty) {
      _lastIdentifier = identifier;
      await DeviceStorage.instance.writeString(
        _identifierStorageKey,
        identifier,
      );
    }

    ApiClient.setBearerToken(token);
    notifyListeners();

    if (persist) {
      await _persist();
    } else {
      await DeviceStorage.instance.remove(_storageKey);
    }
  }

  void _applySession({
    required String token,
    required Map<String, dynamic> user,
  }) {
    _token = token.trim();
    _userId = int.tryParse('${user['id'] ?? ''}');
    _name = cleanOvanieText((user['name'] ?? '').toString());
    _email = (user['email'] ?? '').toString().trim();
    _phone = (user['phone'] ?? '').toString().trim();
    _whatsappPhone = (user['whatsapp_phone'] ?? '').toString().trim();
    _role = (user['role'] ?? 'client').toString().trim().toLowerCase();
    _isAuthenticated = _token?.isNotEmpty == true && _role == 'client';
    if (_role != 'client') {
      _isAuthenticated = false;
      _token = null;
      _userId = null;
      _name = null;
      _email = null;
      _phone = null;
      _whatsappPhone = null;
      _role = null;
    }
  }

  Future<void> updateUser(Map<String, dynamic> user) async {
    if (!_isAuthenticated || (_token ?? '').isEmpty) return;
    _applySession(token: _token!, user: user);
    notifyListeners();
    if (_persistentSession) await _persist();
  }

  void updateSession({required bool authenticated, String? email}) {
    if (!authenticated) {
      unawaited(clear());
      return;
    }
    _isAuthenticated = true;
    _email = email;
    notifyListeners();
  }

  /// Expiration confirmée par Laravel (401 sur une requête Bearer).
  ///
  /// On retire uniquement l'authentification. Le panier local, les écrans et
  /// les autres données hors session restent intacts afin que le client puisse
  /// se reconnecter et reprendre son parcours.
  Future<void> expireFromServer() async {
    await _clearAuthentication(removeIdentifier: false);
  }

  Future<void> clear() async {
    await _clearAuthentication(removeIdentifier: false);
  }

  Future<void> _clearAuthentication({required bool removeIdentifier}) async {
    final hadSession = _isAuthenticated ||
        _token != null ||
        _userId != null ||
        _name != null ||
        _email != null ||
        _phone != null ||
        _whatsappPhone != null ||
        _role != null;

    _isAuthenticated = false;
    _persistentSession = true;
    _token = null;
    _userId = null;
    _name = null;
    _email = null;
    _phone = null;
    _whatsappPhone = null;
    _role = null;
    ApiClient.setBearerToken(null);

    if (removeIdentifier) {
      _lastIdentifier = null;
      await DeviceStorage.instance.remove(_identifierStorageKey);
    }

    if (hadSession) notifyListeners();
    await DeviceStorage.instance.remove(_storageKey);
  }

  Future<void> flush() async {
    if (_isAuthenticated && _persistentSession && (_token ?? '').isNotEmpty) {
      await _persist();
    }
  }

  Future<void> _persist() async {
    if (!_isAuthenticated || (_token ?? '').trim().isEmpty) {
      await DeviceStorage.instance.remove(_storageKey);
      return;
    }

    final payload = <String, dynamic>{
      'token': _token,
      'user': <String, dynamic>{
        'id': _userId,
        'name': _name,
        'email': _email,
        'phone': _phone,
        'whatsapp_phone': _whatsappPhone,
        'role': _role,
      },
    };

    await DeviceStorage.instance.writeString(
      _storageKey,
      jsonEncode(payload),
    );
  }
}
