import 'dart:convert';

import 'package:flutter/foundation.dart';

import '../../core/network/api_client.dart';
import '../../core/storage/device_storage.dart';
import '../../data/vendor_repository.dart';

class VendorSession extends ChangeNotifier {
  VendorSession._();
  static final VendorSession instance = VendorSession._();

  static const _tokenKey = 'vendor_token';
  static const _userKey = 'vendor_user';
  static const _onboardingStepPrefix = 'vendor_onboarding_step_';

  final _storage = DeviceStorage.instance;
  final _repo = VendorRepository.instance;

  String? token;
  Map<String, dynamic>? user;
  Map<String, dynamic>? shop;
  bool initialized = false;
  bool loading = false;
  bool _persistSession = true;
  int onboardingStep = 0;

  bool get authenticated => (token ?? '').trim().isNotEmpty && user != null;

  bool get hasShop {
    if (shop != null) return true;
    final raw = user?['has_shop'];
    if (raw is bool) return raw;
    if (raw is num) return raw != 0;
    final text = '${raw ?? ''}'.trim().toLowerCase();
    return const {'1', 'true', 'yes', 'oui'}.contains(text);
  }

  int? get userId {
    final raw = user?['id'];
    if (raw is int) return raw;
    return int.tryParse('${raw ?? ''}');
  }

  Future<void> restore() async {
    if (initialized) return;

    token = await _storage.readString(_tokenKey);
    final rawUser = await _storage.readString(_userKey);
    if (rawUser != null) {
      try {
        user = Map<String, dynamic>.from(jsonDecode(rawUser) as Map);
      } catch (_) {
        user = null;
      }
    }
    ApiClient.setToken(token);

    if ((token ?? '').trim().isNotEmpty) {
      try {
        // /auth/me est la source d'identité de la session. Le démarrage de
        // l'application ne dépend jamais de /vendor/context.
        await _refreshIdentity();
        await _restoreOnboardingStep();
        await _loadVendorContextBestEffort();
      } on VendorApiException catch (error) {
        if (error.statusCode == 401 || error.statusCode == 403) {
          await clearLocal();
        }
      } catch (_) {
        // Une panne réseau temporaire ne supprime pas une session locale.
      }
    }

    initialized = true;
    notifyListeners();
  }

  Future<void> login(
    String identifier,
    String password, {
    bool persist = false,
  }) async {
    loading = true;
    notifyListeners();

    try {
      final data = await _repo.login(identifier, password);
      final newToken = '${data['token'] ?? ''}'.trim();
      _persistSession = persist;
      final loginUser = data['user'] is Map
          ? Map<String, dynamic>.from(data['user'] as Map)
          : null;

      if (newToken.isEmpty) {
        throw const VendorApiException(
          'Le serveur OVANIE n’a pas renvoyé de jeton de connexion.',
        );
      }
      if (loginUser == null) {
        throw const VendorApiException(
          'Le serveur OVANIE n’a pas renvoyé le profil associé à cette connexion.',
        );
      }
      if ('${loginUser['role'] ?? ''}'.trim().toLowerCase() != 'vendor') {
        throw const VendorApiException(
          'Ce compte est un compte client. Utilisez l’application Client OVANIE.',
          statusCode: 403,
        );
      }

      // À partir d'ici la connexion est RÉUSSIE. Aucun endpoint vendeur
      // secondaire ne peut transformer cette authentification en échec.
      token = newToken;
      user = loginUser;
      shop = null;
      ApiClient.setToken(token);
      await _persistIdentity();

      // On confirme le profil avec l'endpoint d'authentification commun. Une
      // erreur réseau non-401 est tolérée puisque login a déjà renvoyé un
      // token et un utilisateur valides.
      try {
        await _refreshIdentity();
      } on VendorApiException catch (error) {
        if (error.statusCode == 401 || error.statusCode == 403) {
          await clearLocal();
          rethrow;
        }
      } catch (_) {}

      await _restoreOnboardingStep();

      // Le contexte boutique est seulement un enrichissement. S'il n'est pas
      // disponible, has_shop fourni par login/me suffit pour la redirection.
      await _loadVendorContextBestEffort();

      if (hasShop) {
        onboardingStep = 0;
        await _clearOnboardingStep();
      }
    } finally {
      loading = false;
      notifyListeners();
    }
  }

  Future<void> acceptOnboarding(Map<String, dynamic> data) async {
    final newToken = '${data['token'] ?? ''}'.trim();
    final newUser = data['user'] is Map
        ? Map<String, dynamic>.from(data['user'] as Map)
        : user;
    final newShop = data['shop'] is Map
        ? Map<String, dynamic>.from(data['shop'] as Map)
        : shop;

    if (newToken.isNotEmpty) {
      token = newToken;
      ApiClient.setToken(token);
    }

    user = newUser;
    shop = newShop;
    if (user != null) {
      user!['has_shop'] = newShop != null || _asBool(data['has_shop']);
    }
    await _persistIdentity();

    onboardingStep = 0;
    await _clearOnboardingStep();
    notifyListeners();
  }

  /// Rafraîchit la session sans rendre l'application dépendante d'un endpoint
  /// vendeur facultatif. /auth/me est obligatoire pour valider le token ;
  /// /vendor/context ne sert qu'à charger l'objet boutique détaillé.
  Future<void> refreshContext({bool strict = true}) async {
    try {
      await _refreshIdentity();
    } on VendorApiException catch (error) {
      if (error.statusCode == 401 || strict) rethrow;
      return;
    } catch (_) {
      if (strict) rethrow;
      return;
    }

    await _loadVendorContextBestEffort(strict: strict);
  }

  Future<void> _refreshIdentity() async {
    final data = await _repo.me();
    if (data['user'] is! Map) {
      throw const VendorApiException(
        'Le profil de la session OVANIE est incomplet.',
      );
    }

    user = Map<String, dynamic>.from(data['user'] as Map);
    if ('${user?['role'] ?? ''}'.trim().toLowerCase() != 'vendor') {
      throw const VendorApiException(
        'Cette session appartient à un compte client.',
        statusCode: 403,
      );
    }
    if (!hasShop) shop = null;
    await _persistIdentity();
    notifyListeners();
  }

  Future<void> _loadVendorContextBestEffort({bool strict = false}) async {
    if (!authenticated) return;

    try {
      final data = await _repo.context();
      if (data['user'] is Map) {
        final contextUser = Map<String, dynamic>.from(data['user'] as Map);
        // Le contrôleur vendeur peut retourner un payload plus petit. On
        // fusionne sans perdre has_shop déjà reçu par /auth/me.
        user = {...?user, ...contextUser};
      }
      if (data.containsKey('has_shop') && user != null) {
        user!['has_shop'] = data['has_shop'];
      }
      shop = data['shop'] is Map
          ? Map<String, dynamic>.from(data['shop'] as Map)
          : null;
      await _persistIdentity();
      notifyListeners();
    } on VendorApiException catch (error) {
      if (error.statusCode == 401) rethrow;
      // Un 404 signifie que le module vendeur n'a pas encore été chargé sur
      // le serveur. Cela ne doit JAMAIS annuler une authentification réussie.
      if (strict && error.statusCode != 404) rethrow;
    } catch (_) {
      if (strict) rethrow;
    }
  }

  Future<void> _persistIdentity() async {
    if (!_persistSession) {
      await _storage.remove(_tokenKey);
      await _storage.remove(_userKey);
      return;
    }
    if ((token ?? '').trim().isNotEmpty) {
      await _storage.writeString(_tokenKey, token!.trim());
    }
    if (user != null) {
      await _storage.writeString(_userKey, jsonEncode(user));
    }
  }

  bool _asBool(Object? raw) {
    if (raw is bool) return raw;
    if (raw is num) return raw != 0;
    return const {'1', 'true', 'yes', 'oui'}
        .contains('${raw ?? ''}'.trim().toLowerCase());
  }

  Future<void> saveOnboardingStep(int step) async {
    onboardingStep = step.clamp(0, 4).toInt();
    final id = userId;
    if (id != null && !hasShop) {
      await _storage.writeString(
        '$_onboardingStepPrefix$id',
        onboardingStep.toString(),
      );
    }
    notifyListeners();
  }

  Future<void> _restoreOnboardingStep() async {
    final id = userId;
    if (id == null || hasShop) {
      onboardingStep = 0;
      return;
    }
    final raw = await _storage.readString('$_onboardingStepPrefix$id');
    onboardingStep = (int.tryParse(raw ?? '') ?? 0).clamp(0, 4).toInt();
  }

  Future<void> _clearOnboardingStep() async {
    final id = userId;
    if (id != null) {
      await _storage.remove('$_onboardingStepPrefix$id');
    }
  }

  Future<void> logout() async {
    try {
      await _repo.logout();
    } catch (_) {}
    await clearLocal();
    notifyListeners();
  }

  Future<void> clearLocal() async {
    token = null;
    user = null;
    shop = null;
    onboardingStep = 0;
    _persistSession = true;
    ApiClient.setToken(null);
    await _storage.remove(_tokenKey);
    await _storage.remove(_userKey);
  }
}
