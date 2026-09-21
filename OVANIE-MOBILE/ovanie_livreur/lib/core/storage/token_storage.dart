import 'package:shared_preferences/shared_preferences.dart';

/// Stockage local léger du jeton de session du livreur.
///
/// Laravel reste la seule source de vérité pour le compte et le statut
/// d'onboarding : ce stockage ne fait que conserver le jeton d'authentification
/// entre deux ouvertures de l'application.
class TokenStorage {
  TokenStorage._();

  static final TokenStorage instance = TokenStorage._();

  static const _tokenKey = 'ovanie_livreur_token';

  Future<String?> readToken() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      return prefs.getString(_tokenKey);
    } catch (_) {
      return null;
    }
  }

  Future<void> writeToken(String token) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(_tokenKey, token);
    } catch (_) {
      // La session reste valide en mémoire pour l'écran courant.
    }
  }

  Future<void> clear() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.remove(_tokenKey);
    } catch (_) {
      // Ignoré volontairement.
    }
  }
}
