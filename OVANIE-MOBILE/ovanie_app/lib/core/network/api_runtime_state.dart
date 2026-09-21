import 'package:flutter/foundation.dart';

/// Etat technique global de l'application OVANIE.
///
/// Il ne contient aucune donnée métier. Laravel reste la seule source de vérité
/// pour le compte, le panier, les commandes, etc. Ce store sert uniquement à
/// afficher proprement les coupures réseau et l'expiration réelle d'un token.
class ApiRuntimeState extends ChangeNotifier {
  ApiRuntimeState._();

  static final ApiRuntimeState instance = ApiRuntimeState._();

  bool _offline = false;
  bool _sessionExpired = false;
  bool _bootCompleted = false;
  String _networkMessage =
      'Impossible de joindre OVANIE. Vérifiez votre connexion internet puis réessayez.';
  int _connectivityRevision = 0;

  bool get isOffline => _offline;
  bool get isSessionExpired => _sessionExpired;
  bool get isBootCompleted => _bootCompleted;
  String get networkMessage => _networkMessage;
  int get connectivityRevision => _connectivityRevision;

  void reportOffline([String? message]) {
    final nextMessage = (message ?? '').trim().isEmpty
        ? 'Impossible de joindre OVANIE. Vérifiez votre connexion internet puis réessayez.'
        : message!.trim();

    final changed = !_offline || _networkMessage != nextMessage;
    _offline = true;
    _networkMessage = nextMessage;
    if (changed) notifyListeners();
  }

  void reportOnline() {
    if (!_offline) return;
    _offline = false;
    _connectivityRevision++;
    notifyListeners();
  }

  /// Uniquement appelé lorsqu'une requête qui portait déjà un Bearer token
  /// reçoit HTTP 401. Un mauvais mot de passe sur /login ne déclenche donc pas
  /// cet état.
  void reportSessionExpired() {
    if (_sessionExpired) return;
    _sessionExpired = true;
    notifyListeners();
  }

  void markBootCompleted() {
    if (_bootCompleted) return;
    _bootCompleted = true;
    notifyListeners();
  }

  void resolveSessionExpired() {
    if (!_sessionExpired) return;
    _sessionExpired = false;
    notifyListeners();
  }
}
