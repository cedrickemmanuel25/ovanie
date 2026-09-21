import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class SessionStorage {
  const SessionStorage();

  static const _tokenKey = 'ovanie_commercial_token';
  static final _storage = FlutterSecureStorage(
    aOptions: AndroidOptions(encryptedSharedPreferences: true),
  );

  Future<String?> readToken() => _storage.read(key: _tokenKey);

  Future<void> saveToken(String token) => _storage.write(
        key: _tokenKey,
        value: token,
      );

  Future<void> clearToken() => _storage.delete(key: _tokenKey);
}
